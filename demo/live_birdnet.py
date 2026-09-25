"""Continuous microphone producer, bounded queue, sequential BirdNET WAV consumer.
Run separately from demo/server.py. No OpenAI calls.
"""
import argparse
import csv
import json
import logging
import queue
import signal
import tempfile
import threading
import wave
from datetime import datetime, timedelta
from pathlib import Path

# Spawned BirdNET workers must not independently react to console Ctrl+C.
if __name__ == "__mp_main__":
    signal.signal(signal.SIGINT, signal.SIG_IGN)

SAMPLE_RATE = 48000
CHANNELS = 1
RECORD_SECONDS = 6
MIN_CONFIDENCE = 0.60
MIC_DEVICE = None
MAX_STORED_DETECTIONS = 500
QUEUE_SIZE = 3
JSON_OUTPUT = Path(__file__).with_name("live-detections.json")
LOG = logging.getLogger("vogel.capture")


def run_pipeline(read_block, analyse_block, stop, queue_size=QUEUE_SIZE, max_blocks=None):
    """Producer keeps reading while the calling thread consumes.

    read_block returns (PCM bytes, capture timestamp). Overflow drops the oldest
    waiting block, never the active block. No WAV is created until consumption.
    """
    pending = queue.Queue(maxsize=queue_size)
    finished = threading.Event()
    failures = []
    counts = dict(recorded=0, analysed=0, dropped=0, failed=0)

    def produce():
        try:
            while not stop.is_set():
                audio, timestamp = read_block()
                if stop.is_set():
                    LOG.info("Actief opnameblok geannuleerd bij stoppen")
                    break
                counts["recorded"] += 1
                item = (counts["recorded"], audio, timestamp)
                while True:
                    try:
                        pending.put_nowait(item)
                        break
                    except queue.Full:
                        try:
                            discarded = pending.get_nowait()
                        except queue.Empty:
                            continue
                        counts["dropped"] += 1
                        LOG.warning("Analyse loopt achter: blok %03d overgeslagen (queue vol)", discarded[0])
                LOG.info("Blok %03d opgenomen | queue=%d", item[0], pending.qsize())
                if max_blocks and counts["recorded"] >= max_blocks:
                    break
        except Exception as error:
            failures.append(error)
            LOG.error("Opname mislukt: %s", error)
        finally:
            finished.set()

    producer = threading.Thread(target=produce, name="microphone-producer")
    producer.start()
    try:
        while not stop.is_set():
            try:
                item = pending.get(timeout=.1)
            except queue.Empty:
                if finished.is_set():
                    break
                continue
            LOG.info("Blok %03d analyseren | queue=%d", item[0], pending.qsize())
            try:
                analyse_block(*item)
                counts["analysed"] += 1
            except Exception as error:
                counts["failed"] += 1
                LOG.error("Blok %03d analyse mislukt: %s", item[0], error)
    finally:
        stop.set()
        producer.join()  # A blocking read completes within one 6-second block.
        while True:
            try:
                item = pending.get_nowait()
                counts["dropped"] += 1
                LOG.warning("Blok %03d overgeslagen bij afsluiten", item[0])
            except queue.Empty:
                break
    if failures:
        raise RuntimeError("Audioproducer gestopt door een opnamefout") from failures[0]
    return counts


def analyse_pcm(model, number, audio, recorded_at):
    with tempfile.TemporaryDirectory(prefix="birdnet_live_") as folder:
        path = Path(folder) / "recording.wav"
        with wave.open(str(path), "wb") as wav:
            wav.setnchannels(CHANNELS)
            wav.setsampwidth(2)
            wav.setframerate(SAMPLE_RATE)
            wav.writeframes(audio)
        predictions = model.predict(str(path))
        results = parse_predictions(predictions)
        for detection in results:
            LOG.info("%s %.1f%%", detection["scientific_name"], detection["confidence"] * 100)
        save_detections(results, recorded_at)


def parse_predictions(predictions):
    detections = []

    with tempfile.TemporaryDirectory(
        prefix="birdnet_results_"
    ) as temp_dir:

        csv_path = (
            Path(temp_dir)
            / "predictions.csv"
        )

        predictions.to_csv(
            str(csv_path)
        )

        with csv_path.open(
            "r",
            encoding="utf-8-sig",
            newline="",
        ) as file:

            reader = csv.DictReader(file)

            for row in reader:

                try:
                    confidence = float(
                        row.get(
                            "confidence",
                            0,
                        )
                    )
                except (TypeError, ValueError):
                    confidence = 0.0

                if confidence < MIN_CONFIDENCE:
                    continue

                species_name = (
                    row.get("species_name")
                    or ""
                )

                if "_" in species_name:
                    scientific_name, common_name = (
                        species_name.split(
                            "_",
                            1,
                        )
                    )
                else:
                    scientific_name = species_name
                    common_name = species_name

                detections.append(
                    {
                        "start_time": row.get(
                            "start_time"
                        ),
                        "end_time": row.get(
                            "end_time"
                        ),
                        "scientific_name": scientific_name,
                        "common_name": common_name,
                        "confidence": confidence,
                    }
                )

    return detections


def empty_detection_state():
    return {
        "source": "birdnet-live",
        "updated_at": None,
        "detections": [],
        "species": [],
    }


def load_detection_state():
    if not JSON_OUTPUT.exists():
        return empty_detection_state()

    try:
        with JSON_OUTPUT.open(
            "r",
            encoding="utf-8",
        ) as file:
            state = json.load(file)
            if not isinstance(state, dict) or not isinstance(state.get("detections"), list) or not isinstance(state.get("species"), list):
                raise ValueError("Ongeldig JSON-contract; bestaande historie wordt niet overschreven")
            # Older writers did not store first_seen. Keep it absent rather
            # than inventing a lifetime first detection from the bounded buffer.
            for item in state["species"]:
                if not isinstance(item, dict) or not all(k in item for k in ("scientific_name", "common_name", "count", "max_confidence", "last_seen")) or type(item["count"]) is not int or item["count"] < 0:
                    raise ValueError("Ongeldige species-totalen; herstel de bestaande historie")
            return state

    except (
        OSError,
        json.JSONDecodeError,
    ):
        raise ValueError("Bestaande detectiehistorie onleesbaar; herstel JSON voordat opname start") from None


def save_detections(detections, recorded_at=None):
    if not detections:
        return

    state = load_detection_state()

    now = (recorded_at or datetime.now().astimezone()).isoformat(timespec="seconds")

    # Bestaande species-index opbouwen
    species_index = {
        item["scientific_name"]: item
        for item in state.get("species", [])
    }

    # --------------------------------------------------------
    # Nieuwe detecties verwerken
    # --------------------------------------------------------

    for detection in detections:
        scientific_name = detection["scientific_name"]
        common_name = detection["common_name"]
        confidence = round(
            detection["confidence"],
            4,
        )

        # Los event bewaren
        state["detections"].append(
            {
                "scientific_name": scientific_name,
                "common_name": common_name,
                "confidence": confidence,
                "detected_at": now,
            }
        )

        # Persistent totaal per soort bijwerken
        if scientific_name not in species_index:
            species_index[scientific_name] = {
                "scientific_name": scientific_name,
                "common_name": common_name,
                "count": 0,
                "max_confidence": 0.0,
                "first_seen": now,
                "last_seen": now,
            }

        item = species_index[scientific_name]

        item["count"] += 1

        item["max_confidence"] = max(
            item["max_confidence"],
            confidence,
        )

        item["last_seen"] = now

        # Common name eventueel bijwerken
        item["common_name"] = common_name

    # --------------------------------------------------------
    # Alleen event-log begrenzen
    # --------------------------------------------------------

    state["detections"] = state["detections"][
        -MAX_STORED_DETECTIONS:
    ]

    # --------------------------------------------------------
    # Species-totalen blijven volledig behouden
    # --------------------------------------------------------

    state["species"] = sorted(
        species_index.values(),
        key=lambda item: (
            -item["count"],
            item["common_name"],
        ),
    )

    state["updated_at"] = now

    # --------------------------------------------------------
    # Atomisch schrijven
    # --------------------------------------------------------

    temp_path = JSON_OUTPUT.with_suffix(".tmp")

    try:
        with temp_path.open("w", encoding="utf-8") as file:
            json.dump(state, file, indent=2, ensure_ascii=False)
        temp_path.replace(JSON_OUTPUT)
    finally:
        temp_path.unlink(missing_ok=True)

    LOG.info("JSON bijgewerkt: %s", JSON_OUTPUT.name)


def main():
    global JSON_OUTPUT
    handler = logging.StreamHandler()
    handler.setFormatter(logging.Formatter("%(asctime)s %(levelname)s %(message)s", datefmt="%H:%M:%S"))
    LOG.handlers = [handler]
    LOG.setLevel(logging.INFO)
    LOG.propagate = False
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output", type=Path, default=JSON_OUTPUT)
    parser.add_argument("--blocks", type=int, help="Stop after N captured blocks (smoke test)")
    args = parser.parse_args()
    if args.blocks is not None and args.blocks < 1:
        parser.error("--blocks must be positive")
    JSON_OUTPUT = args.output.resolve()
    JSON_OUTPUT.parent.mkdir(parents=True, exist_ok=True)
    load_detection_state()  # Fail before recording if persistent history is corrupt.
    stop = threading.Event()

    def request_stop(signum, frame):
        stop.set()
        LOG.info("Stop aangevraagd: opname stopt; actieve analyse wordt afgerond")

    signal.signal(signal.SIGINT, request_stop)
    import birdnet
    import sounddevice as sd
    LOG.info("BirdNET acoustic 3.0 ONNX laden (eenmalig)")
    model = birdnet.load("acoustic", "3.0", "onnx")
    LOG.info("Microfoon=%s | confidence=%.2f | blok=%ds | queue=%d | output=%s",
             sd.query_devices(MIC_DEVICE, "input")["name"], MIN_CONFIDENCE, RECORD_SECONDS, QUEUE_SIZE, JSON_OUTPUT)
    with sd.RawInputStream(samplerate=SAMPLE_RATE, channels=CHANNELS,
                           dtype="int16", device=MIC_DEVICE) as stream:
        def read_block():
            data, overflow = stream.read(SAMPLE_RATE * RECORD_SECONDS)
            if overflow:
                LOG.warning("Audio-input overflow: samples verloren door apparaat/CPU-achterstand")
            return bytes(data), datetime.now().astimezone() - timedelta(seconds=RECORD_SECONDS)
        counts = run_pipeline(read_block, lambda *block: analyse_pcm(model, *block),
                              stop, max_blocks=args.blocks)
    LOG.info("BirdNET gestopt: %s", counts)


if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        LOG.error("BirdNET gestopt: %s", error)
        raise SystemExit(1)
