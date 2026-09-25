"""Local, read-only AvianVisitors demo. Python 3.10+, standard library only.

Implements the public frontend's JSON contract with synthetic detections.
Never loads production PHP, station configuration, audio or hardware modules.
"""
from __future__ import annotations

import argparse
from collections import Counter, defaultdict
from datetime import datetime, timedelta
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
import mimetypes
from pathlib import Path
import re
from urllib.parse import parse_qs, unquote, urlsplit

ROOT = Path(__file__).resolve().parents[1]
FRONTEND = ROOT / "avian/frontend"
ILLUSTRATIONS = ROOT / "avian/assets/illustrations"
FIXTURE = Path(__file__).with_name("species.json")
LOCAL_ART = ROOT / ".avian/illustrations"
LOCAL_TABLES = ROOT / ".avian/frontend"


def art_table(name):
    table = json.loads((FRONTEND / name).read_text(encoding="utf-8"))
    local = LOCAL_TABLES / name
    if local.is_file():
        table.update(json.loads(local.read_text(encoding="utf-8")))
    return table


def illustration(sci, pose=1):
    name = slug(sci) + ("-2" if pose == 2 else "") + ".png"
    for directory in (LOCAL_ART, ILLUSTRATIONS):
        if (directory / name).is_file():
            return directory / name
    if pose == 2:
        return illustration(sci, 1)
    return ILLUSTRATIONS / name


def slug(sci):
    return re.sub(r"[^a-z0-9]+", "-", sci.lower()).strip("-")


def load_species(path):
    species = json.loads(Path(path).read_text(encoding="utf-8"))
    dims = art_table("dims.json")
    masks = art_table("masks.json")
    if not isinstance(species, list) or len(species) > 100:
        raise ValueError("fixture must be an array of at most 100 species")
    seen = set()
    for bird in species:
        if not isinstance(bird, dict):
            raise ValueError("each species must be an object")
        sci, com, count = bird.get("sci"), bird.get("com"), bird.get("count")
        if not isinstance(sci, str) or not re.fullmatch(r"[A-Za-z]{2,40}(?: [a-z]{2,40}){1,3}", sci):
            raise ValueError("invalid scientific name")
        if not isinstance(com, str) or not com.strip() or len(com) > 100:
            raise ValueError("invalid common name")
        if type(count) is not int or not 0 <= count <= 1000 or sci in seen:
            raise ValueError("counts must be 0..1000 and species must be unique")
        seen.add(sci)
        key = slug(sci)
        if key not in dims or key not in masks or not illustration(sci).is_file():
            raise ValueError(f"no bundled illustration and mask for {sci}")
    return species


def detections(species, now):
    """Relative timestamps keep the demo useful on any date, without a DB file."""
    result = []
    for i, bird in enumerate(species):
        for n in range(bird["count"]):
            age = 5 + i * 100 + (n * 30 % 600)
            result.append(dict(sci=bird["sci"], com=bird["com"],
                               at=now - timedelta(minutes=age), conf=0.95))
    return result


def group_species(rows):
    grouped = defaultdict(list)
    for row in rows:
        grouped[row["sci"]].append(row)
    return sorted([
        dict(sci=sci, com=items[0]["com"], n=len(items), total=len(items),
             first_seen=min(x["at"] for x in items).isoformat(" "),
             last_seen=max(x["at"] for x in items).isoformat(" "),
             best_conf=0.95, top_file=None, detection_id=None, top_at=None)
        for sci, items in grouped.items()
    ], key=lambda b: b["last_seen"], reverse=True)


def totals(rows):
    return dict(detections=len(rows), species=len({r["sci"] for r in rows}))


def public_data(species, query, now=None):
    now = (now or datetime.now()).replace(microsecond=0)
    date = datetime.strptime(query.get("date", now.date().isoformat()), "%Y-%m-%d").date()
    anchor = now if date == now.date() else datetime.combine(date, datetime.max.time()).replace(microsecond=0)
    hours = max(1, min(1000000, int(query.get("hours", 24))))
    all_rows = detections(species, now)
    rows = [r for r in all_rows if r["at"] <= anchor]
    day = [r for r in rows if r["at"].date() == date]
    recent = [r for r in rows if r["at"] > anchor - timedelta(hours=hours)]
    result = dict(educator_scope=None, demo=True, as_of=now.isoformat(),
                  date=date.isoformat(), station_date=now.date().isoformat(),
                  is_today=date == now.date(), anchor=anchor.isoformat(" "))
    action = query.get("action", "stats")
    if action == "recent":
        result.update(hours=hours, species=group_species(recent),
                      site_name="Vogel Bezoeken - gesimuleerde detecties",
                      reset_at_midnight=False, midnight_clamped=False,
                      window_start=(anchor - timedelta(hours=hours)).isoformat(" "))
    elif action == "stats":
        result.update(totals=totals(rows), today=totals(day),
                      last_hour=totals([r for r in rows if r["at"] > anchor - timedelta(hours=1)]),
                      week=totals([r for r in rows if r["at"] > anchor - timedelta(days=7)]),
                      started=min((r["at"].date().isoformat() for r in rows), default=None))
    elif action in ("lifelist", "firstseen"):
        birds = group_species(all_rows if action == "lifelist" else rows)
        birds.sort(key=lambda b: b["first_seen"], reverse=action == "firstseen")
        result["species"] = birds if action == "lifelist" else birds[:max(1, min(50, int(query.get("limit", 10))))]
    elif action in ("calendar", "timeseries"):
        dates = sorted({r["at"].date().isoformat() for r in all_rows})
        daily = [dict(date=d, **totals([r for r in all_rows if r["at"].date().isoformat() == d])) for d in dates]
        if action == "calendar":
            result.update(days=daily, first_date=dates[0] if dates else None, last_date=dates[-1] if dates else None)
        else:
            days = max(1, min(90, int(query.get("days", 30))))
            cutoff = (now.date() - timedelta(days=days - 1)).isoformat()
            result.update(days=days, daily=[d for d in daily if d["date"] >= cutoff],
                          by_hour=[dict(hour=h, detections=n) for h, n in sorted(Counter(r["at"].hour for r in all_rows).items())])
    elif action == "hourly":
        birds = group_species(day)
        birds.sort(key=lambda b: (-b["total"], b["sci"]))
        for bird in birds:
            counts = Counter(r["at"].hour for r in day if r["sci"] == bird["sci"])
            bird["hours"] = [dict(hour=h, n=n) for h, n in sorted(counts.items())]
        result.update(species=birds[:max(1, min(30, int(query.get("limit", 15))))], anchor_hour=anchor.hour)
    elif action == "rhythm":
        week = hours == 168
        selected = recent if week else day
        counts = Counter(r["at"].hour * 60 + r["at"].minute for r in selected)
        slot = anchor.hour * 60 + anchor.minute if date == now.date() and not week else 1439
        result.update(days=7, hours=hours, mode="week" if week else "all-day" if hours >= 1000000 else "day",
                      slots=1440, today=[dict(slot=s, detections=round(n / 7, 2) if week else n) for s, n in sorted(counts.items())],
                      avg=[], now_slot=slot, now_hour=slot // 60,
                      range_start_slot=max(0, slot - (59 if hours == 1 else 719)) if hours <= 12 else 0,
                      range_end_slot=slot if hours <= 12 else 1439)
    elif action == "species":
        sci = query.get("sci", "")
        selected = sorted([r for r in all_rows if r["sci"] == sci], key=lambda r: r["at"], reverse=True)
        summary = group_species(selected)
        limit, offset = max(1, min(1000, int(query.get("limit", 500)))), max(0, int(query.get("offset", 0)))
        page = selected[offset:offset + limit]
        result.update(sci=sci, summary=summary[0] if summary else None,
                      detections=[dict(d=r["at"].date().isoformat(), t=r["at"].time().isoformat(), file=None, conf=r["conf"], detection_id=None) for r in page],
                      page=dict(limit=limit, offset=offset, returned=len(page)))
    else:
        raise KeyError(action)
    return result


class DemoHandler(BaseHTTPRequestHandler):
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
        self.send_bytes(json.dumps(value).encode(), "application/json; charset=utf-8", status)

    def do_HEAD(self):
        self.do_GET()

    def do_POST(self):
        self.send_json({"error": "Read-only demo: station controls are unavailable."}, 405)

    def do_GET(self):
        parsed = urlsplit(self.path)
        path = unquote(parsed.path)
        query = {k: v[-1] for k, v in parse_qs(parsed.query).items()}
        if path in ("/dims.json", "/masks.json"):
            self.send_json(art_table(path[1:]))
            return
        if path == "/avian/api/birdnet-api.php":
            try:
                self.send_json(public_data(self.server.species, query))
            except (ValueError, OverflowError):
                self.send_json({"error": "Invalid date or numeric parameter"}, 400)
            except KeyError:
                self.send_json({"error": "Unknown demo action"}, 404)
            return
        if path == "/avian/api/menu.php":
            self.send_json({"items": [], "auth": {}, "chroma": 0})
            return
        if path == "/avian/api/wiki.php":
            self.send_json({"extract": "Lokale demo met gesimuleerde detecties. Geen audio of externe encyclopedie gekoppeld."})
            return
        if path == "/avian/api/cutout.php":
            sci = query.get("sci", "")
            if sci not in {b["sci"] for b in self.server.species}:
                self.send_error(404)
                return
            target = illustration(sci, 2 if query.get("pose") == "2" else 1)
        elif path in ("/avian/assets/references/sparrow-blossom-single-v2.png",
                      "/avian/assets/references/sparrow-blossom-pair-v2.png"):
            target = ROOT / path.lstrip("/")
        elif path.startswith("/avian/api/"):
            self.send_json({"error": "Unavailable in local demo"}, 404)
            return
        else:
            # Serve only frontend assets, never the repository root or PHP source.
            relative = "index.html" if path == "/" else path.lstrip("/")
            target = (FRONTEND / relative).resolve()
            if not target.is_relative_to(FRONTEND.resolve()) or any(p.startswith(".") for p in Path(relative).parts):
                self.send_error(404)
                return
        if not target.is_file() or target.suffix.lower() not in {".html", ".js", ".css", ".json", ".png", ".webp", ".jpg", ".woff2", ".ttf"}:
            self.send_error(404)
            return
        body = target.read_bytes()
        if target == FRONTEND / "index.html":
            notice = ('<style>#menuShell{display:none!important}.demo-notice{position:fixed;bottom:8px;left:50%;'
                      'transform:translateX(-50%);z-index:200;background:#faf5e8;color:#38382d;'
                      'padding:6px 12px;border-radius:12px;font:12px system-ui;text-align:center;max-width:90vw}</style>'
                      '<aside class="demo-notice">Lokale demo · gesimuleerde detecties · geen audio of stationbeheer</aside>')
            body = body.replace(b"</body>", notice.encode() + b"</body>")
        self.send_bytes(body, mimetypes.guess_type(target.name)[0] or "application/octet-stream")


def make_server(port=8000, fixture=FIXTURE):
    species = load_species(fixture)
    server = ThreadingHTTPServer(("127.0.0.1", port), DemoHandler)
    server.species = species
    return server


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--port", type=int, default=8000)
    parser.add_argument("--fixture", type=Path, default=FIXTURE)
    args = parser.parse_args()
    try:
        server = make_server(args.port, args.fixture)
    except (OSError, ValueError) as error:
        parser.exit(1, f"Cannot start demo: {error}\n")
    print(f"AvianVisitors demo: http://127.0.0.1:{server.server_port}/", flush=True)
    print("Synthetic detections only. Ctrl+C to stop. No API keys or hardware required.", flush=True)
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        pass
    finally:
        server.server_close()


if __name__ == "__main__":
    main()
