import json
import os
from pathlib import Path
import tempfile
import threading
import unittest
import urllib.request
import urllib.error
from unittest.mock import patch
from datetime import datetime
from demo import live_birdnet as live
from demo.server import BirdNETDetectionSource, birdnet_rows, make_server
from demo.config import load_env


class LiveTests(unittest.TestCase):
    def test_producer_continues_during_slow_consumer_and_bounds_queue(self):
        analysing = threading.Event()
        captured = threading.Event()
        n = 0
        def read():
            nonlocal n
            n += 1
            if n == 2:
                self.assertTrue(analysing.wait(2))
            if n == 8:
                captured.set()
            return b"audio", datetime.now()
        processed = []
        def analyse(number, audio, stamp):
            processed.append(number)
            if number == 1:
                analysing.set()
                self.assertTrue(captured.wait(2), "capture blocked behind analysis")
        with self.assertLogs(live.LOG, level="WARNING"):
            counts = live.run_pipeline(read, analyse, threading.Event(), queue_size=2, max_blocks=8)
        self.assertEqual(counts["recorded"], 8)
        self.assertGreaterEqual(counts["dropped"], 4)
        self.assertEqual(counts["analysed"] + counts["dropped"], 8)
        self.assertEqual(processed[-1], 8)

    def test_shutdown_and_analysis_failure_cleanup(self):
        stop = threading.Event()
        def analyse(*args):
            stop.set()
            raise RuntimeError("test failure")
        with self.assertLogs(live.LOG, level="WARNING"):
            counts = live.run_pipeline(lambda: (b"pcm", datetime.now()), analyse, stop, max_blocks=1)
        self.assertEqual(counts["failed"], 1)
        class BrokenModel:
            def predict(self, path):
                self.path = Path(path)
                assert self.path.exists()
                raise RuntimeError("inference failed")
        model = BrokenModel()
        with self.assertRaises(RuntimeError):
            live.analyse_pcm(model, 1, b"\x00\x00" * 100, datetime.now())
        self.assertFalse(model.path.parent.exists())

    def test_persistent_counts_buffer_and_capture_time(self):
        with tempfile.TemporaryDirectory() as folder, patch.object(live, "JSON_OUTPUT", Path(folder)/"state.json"):
            event = dict(scientific_name="Columba livia", common_name="Rock Dove", confidence=.944)
            stamp = datetime.now().astimezone().replace(microsecond=0)
            live.save_detections([event] * 501, stamp)
            live.save_detections([event], stamp)
            state = json.loads(live.JSON_OUTPUT.read_text())
            self.assertEqual(len(state["detections"]), 500)
            self.assertEqual(state["species"][0]["count"], 502)
            self.assertEqual(state["detections"][-1]["detected_at"], stamp.isoformat(timespec="seconds"))
            self.assertFalse(live.JSON_OUTPUT.with_suffix(".tmp").exists())

    def test_json_recovery_empty_invalid_unknown_species(self):
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder)/"live.json"
            source = BirdNETDetectionSource(path)
            with self.assertLogs("vogel.desktop", level="WARNING"):
                self.assertEqual(source.query({"action":"recent"})["species"], [])
            event = dict(scientific_name="Unknown species", common_name="Unknown", confidence=.9, detected_at=datetime.now().astimezone().isoformat())
            path.write_text(json.dumps(dict(detections=[event])))
            self.assertEqual(source.query({"action":"recent"})["species"][0]["sci"], "Unknown species")
            for invalid in ("", "{", "[]", '{"detections":null}'):
                path.write_text(invalid)
                self.assertEqual(len(source.snapshot()), 1)
            path.write_text('{"detections":[]}')
            self.assertEqual(source.snapshot(), [])
            event["confidence"] = float("nan")
            path.write_text(json.dumps(dict(detections=[event])))
            self.assertEqual(birdnet_rows(path), [])

    def test_unknown_art_does_not_break_live_api(self):
        with tempfile.TemporaryDirectory() as folder:
            path = Path(folder)/"state.json"
            event = dict(scientific_name="Unknown species", common_name="Unknown", confidence=.8, detected_at=datetime.now().astimezone().isoformat())
            path.write_text(json.dumps(dict(detections=[event])))
            server = make_server(0, source=BirdNETDetectionSource(path))
            thread = threading.Thread(target=server.serve_forever)
            thread.start()
            try:
                url = f"http://127.0.0.1:{server.server_port}"
                with self.assertRaises(urllib.error.HTTPError) as caught:
                    urllib.request.urlopen(url + "/avian/api/cutout.php?sci=Unknown%20species")
                self.assertEqual(caught.exception.code, 404)
                result = json.load(urllib.request.urlopen(url + "/avian/api/birdnet-api.php?action=recent"))
                self.assertFalse(result["demo"])
                self.assertEqual(len(result["species"]), 1)
            finally:
                server.shutdown(); server.server_close(); thread.join()

    def test_config_and_server_modes(self):
        with tempfile.TemporaryDirectory() as folder, patch.dict(os.environ, {}, clear=True):
            path = Path(folder)/".env"
            path.write_text('APP_MODE=birdnet\nBIRDNET_JSON_PATH=C:\\BirdNET\\live-detections.json\n')
            load_env(path)
            self.assertEqual(os.environ["APP_MODE"], "birdnet")
            self.assertIn("live-detections.json", os.environ["BIRDNET_JSON_PATH"])
            for source in (None, BirdNETDetectionSource(Path(folder)/"missing.json")):
                server = make_server(0, source=source)
                try:
                    self.assertGreater(server.server_port, 0)
                finally:
                    server.server_close()


if __name__ == "__main__":
    unittest.main()
