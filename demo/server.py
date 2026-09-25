"""Local, read-only AvianVisitors desktop server. Python 3.10+, standard library only.

Implements the public frontend's JSON contract using either synthetic demo detections
or a live BirdNET JSON feed. Never loads production PHP, station configuration,
audio or hardware modules.
"""
from __future__ import annotations

import argparse
import base64
from collections import Counter, defaultdict
from datetime import datetime, timedelta
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
import math
import threading
import logging
import mimetypes
import os
from pathlib import Path
import re
import sys
from urllib.parse import parse_qs, unquote, urlsplit
import webbrowser

ROOT = Path(__file__).resolve().parents[1]
FRONTEND = ROOT / "avian/frontend"
ILLUSTRATIONS = ROOT / "avian/assets/illustrations"
FIXTURE = Path(__file__).with_name("species.json")
LOCAL_ART = ROOT / ".avian/illustrations"
LOCAL_TABLES = ROOT / ".avian/frontend"
sys.path.insert(0, str(ROOT))
from demo.config import load_env

LOG = logging.getLogger("vogel.desktop")


def read_table(path):
    value = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(value, dict):
        raise ValueError("expected a JSON object")
    return value


def valid_plate(path):
    try:
        with path.open("rb") as stream:
            return stream.read(8) == b"\x89PNG\r\n\x1a\n"
    except OSError:
        return False


def local_catalog():
    """Only use complete image/table pairs; a broken optional cache is harmless."""
    try:
        dims = read_table(LOCAL_TABLES / "dims.json")
        masks = read_table(LOCAL_TABLES / "masks.json")
    except (OSError, ValueError):
        return {}, {}

    good_dims, good_masks = {}, {}

    for key, dim in dims.items():
        try:
            if not re.fullmatch(r"[a-z0-9]+(?:-[a-z0-9]+)*", key):
                continue

            mask = masks[key]

            if not (
                isinstance(dim, list)
                and len(dim) == 2
                and all(type(v) is int and 0 < v <= 560 for v in dim)
            ):
                continue

            w, h = mask["w"], mask["h"]

            if not (
                type(w) is int
                and type(h) is int
                and 0 < w <= 93
                and 0 < h <= 93
            ):
                continue

            if len(base64.b64decode(mask["bits"], validate=True)) != (w * h + 7) // 8:
                continue

            if not valid_plate(LOCAL_ART / (key + ".png")):
                continue

            good_dims[key], good_masks[key] = dim, mask

        except (KeyError, TypeError, ValueError):
            continue

    return good_dims, good_masks


def art_table(name):
    table = read_table(FRONTEND / name)
    table.update(local_catalog()[0 if name == "dims.json" else 1])
    return table


def illustration(sci, pose=1):
    name = slug(sci) + ("-2" if pose == 2 else "")

    if name in local_catalog()[0]:
        return LOCAL_ART / (name + ".png")

    bundled = ILLUSTRATIONS / (name + ".png")

    if valid_plate(bundled):
        return bundled

    if pose == 2:
        return illustration(sci, 1)

    return bundled


def slug(sci):
    return re.sub(r"[^a-z0-9]+", "-", sci.lower()).strip("-")


def load_species(path):
    species = json.loads(Path(path).read_text(encoding="utf-8"))
    dims = art_table("dims.json")
    masks = art_table("masks.json")

    if not isinstance(species, list) or len(species) > 100:
        raise ValueError("fixture must be an array of at most 100 species")

    seen = set()
    available = []

    for bird in species:
        if not isinstance(bird, dict):
            raise ValueError("each species must be an object")

        sci, com, count = bird.get("sci"), bird.get("com"), bird.get("count")

        if not isinstance(sci, str) or not re.fullmatch(
            r"[A-Za-z]{2,40}(?: [a-z]{2,40}){1,3}", sci
        ):
            raise ValueError("invalid scientific name")

        if not isinstance(com, str) or not com.strip() or len(com) > 100:
            raise ValueError("invalid common name")

        if type(count) is not int or not 0 <= count <= 1000 or sci in seen:
            raise ValueError("counts must be 0..1000 and species must be unique")

        seen.add(sci)
        key = slug(sci)

        if key not in dims or key not in masks or not valid_plate(illustration(sci)):
            LOG.warning(
                "Soort overgeslagen: %s; afbeelding of masker ontbreekt. "
                "Herstel de lokale cache of gebruik meegeleverde soorten.",
                sci,
            )
            continue

        available.append(bird)

    return available


def detections(species, now):
    """Relative timestamps keep the demo useful on any date, without a DB file."""
    result = []

    for i, bird in enumerate(species):
        for n in range(bird["count"]):
            age = 5 + i * 100 + (n * 30 % 600)
            result.append(
                dict(
                    sci=bird["sci"],
                    com=bird["com"],
                    at=now - timedelta(minutes=age),
                    conf=0.95,
                )
            )

    return result


def birdnet_rows(path):
    """Read live BirdNET JSON and convert detections to AvianVisitors rows."""
    path = Path(path)

    if not path.is_file():
        raise OSError(f"BirdNET JSON ontbreekt: {path}")

    state = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(state, dict):
        raise ValueError("BirdNET JSON moet een object zijn")
    raw = state.get("detections")

    if not isinstance(raw, list):
        raise ValueError("BirdNET JSON bevat geen geldige detections-lijst")

    rows = []

    for detection in raw:
        if not isinstance(detection, dict):
            continue

        sci = detection.get("scientific_name")
        com = detection.get("common_name")
        confidence = detection.get("confidence")
        detected_at = detection.get("detected_at")

        if not isinstance(sci, str) or not re.fullmatch(r"[A-Za-z]{2,40}(?: [a-z]{2,40}){1,3}", sci.strip()):
            continue

        if not isinstance(com, str) or not com.strip():
            com = sci

        try:
            confidence = float(confidence)
        except (TypeError, ValueError):
            continue

        if not math.isfinite(confidence) or not 0 <= confidence <= 1:
            continue

        try:
            at = datetime.fromisoformat(detected_at)
        except (TypeError, ValueError):
            continue

        # BirdNET writes timezone-aware ISO timestamps. The existing desktop
        # server uses naive local datetimes, so normalize to local time first.
        if at.tzinfo is not None:
            at = at.astimezone().replace(tzinfo=None)

        rows.append(
            {
                "sci": sci.strip(),
                "com": com.strip(),
                "at": at.replace(microsecond=0),
                "conf": confidence,
            }
        )

    return rows


def group_species(rows):
    grouped = defaultdict(list)

    for row in rows:
        grouped[row["sci"]].append(row)

    return sorted(
        [
            dict(
                sci=sci,
                com=items[0]["com"],
                n=len(items),
                total=len(items),
                first_seen=min(x["at"] for x in items).isoformat(" "),
                last_seen=max(x["at"] for x in items).isoformat(" "),
                best_conf=max(x.get("conf", 0.0) for x in items),
                top_file=None,
                detection_id=None,
                top_at=None,
            )
            for sci, items in grouped.items()
        ],
        key=lambda b: b["last_seen"],
        reverse=True,
    )


def totals(rows):
    return dict(
        detections=len(rows),
        species=len({r["sci"] for r in rows}),
    )


def public_data_rows(all_rows, query, now=None, demo=True):
    now = (now or datetime.now()).replace(microsecond=0)

    date = datetime.strptime(
        query.get("date", now.date().isoformat()),
        "%Y-%m-%d",
    ).date()

    anchor = (
        now
        if date == now.date()
        else datetime.combine(date, datetime.max.time()).replace(microsecond=0)
    )

    hours = max(1, min(1000000, int(query.get("hours", 24))))

    rows = [r for r in all_rows if r["at"] <= anchor]
    day = [r for r in rows if r["at"].date() == date]
    recent = [r for r in rows if r["at"] > anchor - timedelta(hours=hours)]

    result = dict(
        educator_scope=None,
        demo=demo,
        as_of=now.isoformat(),
        date=date.isoformat(),
        station_date=now.date().isoformat(),
        is_today=date == now.date(),
        anchor=anchor.isoformat(" "),
    )

    action = query.get("action", "stats")

    if action == "recent":
        result.update(
            hours=hours,
            species=group_species(recent),
            site_name=(
                "Vogel Bezoeken - gesimuleerde detecties"
                if demo
                else "Vogel bezoeken in de tuin"
            ),
            reset_at_midnight=False,
            midnight_clamped=False,
            window_start=(anchor - timedelta(hours=hours)).isoformat(" "),
        )

    elif action == "stats":
        result.update(
            totals=totals(rows),
            today=totals(day),
            last_hour=totals(
                [r for r in rows if r["at"] > anchor - timedelta(hours=1)]
            ),
            week=totals(
                [r for r in rows if r["at"] > anchor - timedelta(days=7)]
            ),
            started=min(
                (r["at"].date().isoformat() for r in rows),
                default=None,
            ),
        )

    elif action in ("lifelist", "firstseen"):
        birds = group_species(all_rows if action == "lifelist" else rows)
        birds.sort(
            key=lambda b: b["first_seen"],
            reverse=action == "firstseen",
        )
        result["species"] = (
            birds
            if action == "lifelist"
            else birds[: max(1, min(50, int(query.get("limit", 10))))]
        )

    elif action in ("calendar", "timeseries"):
        dates = sorted({r["at"].date().isoformat() for r in all_rows})
        daily = [
            dict(
                date=d,
                **totals(
                    [r for r in all_rows if r["at"].date().isoformat() == d]
                ),
            )
            for d in dates
        ]

        if action == "calendar":
            result.update(
                days=daily,
                first_date=dates[0] if dates else None,
                last_date=dates[-1] if dates else None,
            )
        else:
            days = max(1, min(90, int(query.get("days", 30))))
            cutoff = (now.date() - timedelta(days=days - 1)).isoformat()
            result.update(
                days=days,
                daily=[d for d in daily if d["date"] >= cutoff],
                by_hour=[
                    dict(hour=h, detections=n)
                    for h, n in sorted(
                        Counter(r["at"].hour for r in all_rows).items()
                    )
                ],
            )

    elif action == "hourly":
        birds = group_species(day)
        birds.sort(key=lambda b: (-b["total"], b["sci"]))

        for bird in birds:
            counts = Counter(
                r["at"].hour
                for r in day
                if r["sci"] == bird["sci"]
            )
            bird["hours"] = [
                dict(hour=h, n=n)
                for h, n in sorted(counts.items())
            ]

        result.update(
            species=birds[: max(1, min(30, int(query.get("limit", 15))))],
            anchor_hour=anchor.hour,
        )

    elif action == "rhythm":
        week = hours == 168
        selected = recent if week else day
        counts = Counter(
            r["at"].hour * 60 + r["at"].minute
            for r in selected
        )
        slot = (
            anchor.hour * 60 + anchor.minute
            if date == now.date() and not week
            else 1439
        )

        result.update(
            days=7,
            hours=hours,
            mode=(
                "week"
                if week
                else "all-day"
                if hours >= 1000000
                else "day"
            ),
            slots=1440,
            today=[
                dict(
                    slot=s,
                    detections=round(n / 7, 2) if week else n,
                )
                for s, n in sorted(counts.items())
            ],
            avg=[],
            now_slot=slot,
            now_hour=slot // 60,
            range_start_slot=(
                max(0, slot - (59 if hours == 1 else 719))
                if hours <= 12
                else 0
            ),
            range_end_slot=slot if hours <= 12 else 1439,
        )

    elif action == "species":
        sci = query.get("sci", "")
        selected = sorted(
            [r for r in all_rows if r["sci"] == sci],
            key=lambda r: r["at"],
            reverse=True,
        )
        summary = group_species(selected)
        limit = max(1, min(1000, int(query.get("limit", 500))))
        offset = max(0, int(query.get("offset", 0)))
        page = selected[offset : offset + limit]

        result.update(
            sci=sci,
            summary=summary[0] if summary else None,
            detections=[
                dict(
                    d=r["at"].date().isoformat(),
                    t=r["at"].time().isoformat(),
                    file=None,
                    conf=r["conf"],
                    detection_id=None,
                )
                for r in page
            ],
            page=dict(
                limit=limit,
                offset=offset,
                returned=len(page),
            ),
        )

    else:
        raise KeyError(action)

    return result


def public_data(species, query, now=None):
    now = (now or datetime.now()).replace(microsecond=0)
    rows = detections(species, now)

    return public_data_rows(
        rows,
        query,
        now=now,
        demo=True,
    )


class DemoDetectionSource:
    """Small datasource boundary; no production or hardware imports."""

    def __init__(self, fixture):
        self.species = load_species(fixture)

    def query(self, query):
        return public_data(self.species, query)


class BirdNETDetectionSource:
    """Live datasource backed by live-detections.json."""

    def __init__(self, path):
        self.path = Path(path)

        self._rows = []
        self._lock = threading.Lock()
        self._unavailable = False

    def snapshot(self):
        with self._lock:
            try:
                rows = birdnet_rows(self.path)
            except (OSError, ValueError):
                if not self._unavailable:
                    LOG.warning("BirdNET JSON ontbreekt of is ongeldig; laatste geldige detecties blijven beschikbaar")
                self._unavailable = True
            else:
                self._rows = rows
                if self._unavailable:
                    LOG.info("BirdNET JSON weer beschikbaar")
                self._unavailable = False
            return list(self._rows)

    @property
    def species(self):
        """Species currently present in the retained BirdNET event log."""
        rows = self.snapshot()
        seen = {}

        for row in rows:
            seen[row["sci"]] = {
                "sci": row["sci"],
                "com": row["com"],
                "count": 1,
            }

        return list(seen.values())

    def query(self, query):
        rows = self.snapshot()

        return public_data_rows(
            rows,
            query,
            demo=False,
        )


class DemoHandler(BaseHTTPRequestHandler):
    def setup(self):
        super().setup()
        self.connection.settimeout(15)

    def log_message(self, format, *args):
        # Do not log raw URLs: they may contain accidentally pasted credentials.
        if len(args) > 1 and str(args[1]).isdigit() and int(args[1]) >= 400:
            LOG.warning("HTTP %s (%s)", args[1], self.command)

    def do_GET(self):
        try:
            self.serve_get()
        except (BrokenPipeError, ConnectionResetError, TimeoutError):
            pass
        except (OSError, ValueError, TypeError):
            LOG.error("Verzoek mislukt: lokaal bestand ontbreekt of is ongeldig")
            self.send_json(
                {
                    "error": (
                        "Lokale bestanden niet beschikbaar. "
                        "Controleer de terminal."
                    )
                },
                503,
            )

    def send_bytes(self, body, content_type, status=200):
        self.send_response(status)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.send_header("X-Content-Type-Options", "nosniff")
        self.end_headers()

        if self.command != "HEAD":
            self.wfile.write(body)

    def send_json(self, value, status=200):
        self.send_bytes(
            json.dumps(value).encode(),
            "application/json; charset=utf-8",
            status,
        )

    def do_HEAD(self):
        self.do_GET()

    def do_POST(self):
        self.send_json(
            {"error": "Read-only desktop mode: station controls are unavailable."},
            405,
        )

    def serve_get(self):
        parsed = urlsplit(self.path)
        path = unquote(parsed.path)
        query = {
            k: v[-1]
            for k, v in parse_qs(parsed.query).items()
        }

        if path in ("/dims.json", "/masks.json"):
            self.send_json(art_table(path[1:]))
            return

        if path == "/avian/api/birdnet-api.php":
            try:
                self.send_json(self.server.source.query(query))
            except (ValueError, OverflowError):
                self.send_json(
                    {"error": "Invalid date or numeric parameter"},
                    400,
                )
            except KeyError:
                self.send_json(
                    {"error": "Unknown desktop action"},
                    404,
                )
            return

        if path == "/avian/api/menu.php":
            self.send_json(
                {
                    "items": [],
                    "auth": {},
                    "chroma": 0,
                }
            )
            return

        if path == "/avian/api/wiki.php":
            if isinstance(self.server.source, BirdNETDetectionSource):
                text = (
                    "Lokale desktopmodus met live BirdNET-detecties. "
                    "Geen stationaudio of externe encyclopedie gekoppeld."
                )
            else:
                text = (
                    "Lokale demo met gesimuleerde detecties. "
                    "Geen audio of externe encyclopedie gekoppeld."
                )

            self.send_json({"extract": text})
            return

        if path == "/avian/api/cutout.php":
            sci = query.get("sci", "")

            if sci not in {
                b["sci"]
                for b in self.server.source.species
            }:
                self.send_error(404)
                return

            target = illustration(
                sci,
                2 if query.get("pose") == "2" else 1,
            )

        elif path in (
            "/avian/assets/references/sparrow-blossom-single-v2.png",
            "/avian/assets/references/sparrow-blossom-pair-v2.png",
        ):
            target = ROOT / path.lstrip("/")

        elif path == "/favicon.png":
            target = ROOT / "avian/assets/favicon.png"

        elif path.startswith("/avian/api/"):
            self.send_json(
                {"error": "Unavailable in local desktop mode"},
                404,
            )
            return

        else:
            # Serve only frontend assets, never the repository root or PHP source.
            relative = "index.html" if path == "/" else path.lstrip("/")
            target = (FRONTEND / relative).resolve()

            if not target.is_relative_to(FRONTEND.resolve()) or any(
                p.startswith(".")
                for p in Path(relative).parts
            ):
                self.send_error(404)
                return

        if not target.is_file() or target.suffix.lower() not in {
            ".html",
            ".js",
            ".css",
            ".json",
            ".png",
            ".webp",
            ".jpg",
            ".woff2",
            ".ttf",
        }:
            self.send_error(404)
            return

        body = target.read_bytes()

        if target == FRONTEND / "index.html":
            live = isinstance(self.server.source, BirdNETDetectionSource)

            notice_text = (
                "Lokale desktop · live BirdNET-detecties · geen stationbeheer"
                if live
                else "Lokale demo · gesimuleerde detecties · geen audio of stationbeheer"
            )

            notice = (
                '<style>#menuShell{display:none!important}'
                '.demo-notice{position:fixed;bottom:8px;left:50%;'
                'transform:translateX(-50%);z-index:200;background:#faf5e8;'
                'color:#38382d;padding:6px 12px;border-radius:12px;'
                'font:12px system-ui;text-align:center;max-width:90vw}</style>'
                f'<aside class="demo-notice">{notice_text}</aside>'
            )

            body = body.replace(
                b"</body>",
                notice.encode() + b"</body>",
            )

        self.send_bytes(
            body,
            mimetypes.guess_type(target.name)[0]
            or "application/octet-stream",
        )


def make_server(port=8000, source=None):
    if source is None:
        source = DemoDetectionSource(FIXTURE)

    server = ThreadingHTTPServer(
        ("127.0.0.1", port),
        DemoHandler,
    )
    server.source = source

    return server


def main():
    logging.basicConfig(
        level=logging.INFO,
        format="%(levelname)s %(message)s",
    )

    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--port",
        type=int,
        help="Override APP_PORT (default 8000)",
    )
    parser.add_argument(
        "--fixture",
        type=Path,
        help="Override DEMO_FIXTURE; relative to repository root",
    )
    parser.add_argument(
        "--birdnet-json",
        type=Path,
        help="Pad naar live-detections.json",
    )
    parser.add_argument(
        "--open-browser",
        action="store_true",
        help="Open the local page in your default browser",
    )
    args = parser.parse_args()

    try:
        load_env()

        mode = (
            os.environ
            .get("APP_MODE", "demo")
            .strip()
            .lower()
        )

        if mode not in {"demo", "birdnet"}:
            raise ValueError("APP_MODE moet demo of birdnet zijn")

        port = (
            args.port
            if args.port is not None
            else int(os.environ.get("APP_PORT", "8000"))
        )

        if not 1 <= port <= 65535:
            raise ValueError(
                "APP_PORT/--port moet tussen 1 en 65535 liggen"
            )

        fixture = (
            args.fixture
            or Path(
                os.environ.get("DEMO_FIXTURE")
                or str(FIXTURE)
            )
        )

        if not fixture.is_absolute():
            fixture = ROOT / fixture

        if mode == "demo":
            source = DemoDetectionSource(fixture)

        else:
            birdnet_path = (
                args.birdnet_json
                or os.environ.get("BIRDNET_JSON_PATH")
            )

            if not birdnet_path:
                raise ValueError("BIRDNET_JSON_PATH ontbreekt")

            birdnet_path = Path(birdnet_path)

            if not birdnet_path.is_absolute():
                birdnet_path = ROOT / birdnet_path

            source = BirdNETDetectionSource(birdnet_path)

        server = make_server(
            port=port,
            source=source,
        )

    except (OSError, ValueError) as error:
        LOG.error(
            "Starten mislukt (%s). Controleer .env, datasource en of de poort vrij is.",
            type(error).__name__,
        )
        parser.exit(
            1,
            "Gebruik --help voor opties. "
            "Ongeldige configuratiewaarden worden niet gelogd.\n",
        )

    LOG.info(
        "Vogel Bezoeken gestart | modus=%s | datasource=%s",
        mode,
        type(source).__name__,
    )

    if mode == "demo":
        LOG.info(
            "Fixture: %s | %d soorten | %d gesimuleerde detecties",
            fixture,
            len(source.species),
            sum(b["count"] for b in source.species),
        )
    else:
        LOG.info("BirdNET JSON: %s", source.path)
        LOG.info(
            "BirdNET: %d bewaarde detecties | %d soorten",
            len(source.snapshot()),
            len(source.species),
        )

    if LOCAL_ART.exists() and not local_catalog()[0]:
        LOG.warning(
            "Lokale beeldcache ontbreekt of is ongeldig; "
            "meegeleverde beelden blijven beschikbaar"
        )

    url = f"http://127.0.0.1:{server.server_port}/"
    LOG.info(
        "%s | Stoppen: Ctrl+C | Geen automatische API-aanvragen",
        url,
    )

    if args.open_browser:
        try:
            if not webbrowser.open(url):
                LOG.warning(
                    "Browser kon niet worden geopend; open de URL handmatig"
                )
        except webbrowser.Error:
            LOG.warning(
                "Browser kon niet worden geopend; open de URL handmatig"
            )

    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()
        LOG.info("Applicatie gestopt")


if __name__ == "__main__":
    main()
