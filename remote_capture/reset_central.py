"""Explicit reset of Backyard observations/audio, preserving catalog and assets.

Run on the CENTRAL Backyard Pi, with all backyard services/timers stopped.
Default is read-only. --apply permanently deletes the four audio trees.
The database and bridge checkpoints are backed up; audio is NOT backed up.
"""
import argparse
from contextlib import closing
from datetime import datetime, timezone
import json
import os
from pathlib import Path
import shutil
import sqlite3
import subprocess
from zoneinfo import ZoneInfo

TABLES = ("observation_candidates", "bird_audio", "observations", "bird_detections")
AUDIO = ("birds/audio", "bats/audio", "review/bird/audio", "review/bat/audio")
STATE_KEYS = ("BACKYARD_GO_BIRD_STATE", "BACKYARD_BAT_STATE")


def read_settings(path):
    settings = {}
    for line in path.read_text().splitlines():
        key, sep, value = line.partition("=")
        if sep and not key.lstrip().startswith("#"):
            settings[key.strip()] = value.strip().strip("\"'")
    return settings


def within(root, path):
    path = Path(path)
    if path.is_symlink():
        raise ValueError(f"Symlink refused: {path}")
    resolved = path.resolve()
    if resolved != path.absolute() or resolved == root or not resolved.is_relative_to(root):
        raise ValueError(f"Path outside reset scope: {resolved}")
    return resolved


def prepare(root, envfile):
    root = root.resolve(strict=True)
    data = within(root, root / "data")
    settings = read_settings(envfile)
    # Refuse non-default storage rather than guessing which data to erase.
    for source in (settings, read_settings(root / ".env") if (root / ".env").exists() else {}):
        for key, expected in (("BACKYARD_DATABASE_PATH", data / "backyard.sqlite3"),
                              ("BACKYARD_STORAGE_ROOT", data)):
            if source.get(key):
                value = Path(source[key]).expanduser()
                if (value if value.is_absolute() else root / value).resolve() != expected:
                    raise ValueError(f"Non-default {key}; reset refused")
    database = within(data, data / "backyard.sqlite3")
    states = []
    for key in STATE_KEYS:
        path = Path(settings[key])
        path = within(data, path if path.is_absolute() else root / path)
        state = json.loads(path.read_text())
        if state.get("pending") is not None:
            raise ValueError(f"Pending import in {path}; reset refused")
        if not state.get("instance") or state.get("domain") not in ("bird", "bat"):
            raise ValueError(f"Unknown checkpoint format: {path}")
        states.append((path, state))
    folders = []
    sizes = {}
    for relative in AUDIO:
        folder = within(data, data / relative)
        size = 0
        if folder.exists():
            for directory, dirs, files in os.walk(folder, followlinks=False):
                for name in dirs + files:
                    entry = Path(directory) / name
                    within(data, entry)
                    if entry.is_file():
                        size += entry.stat().st_size
            folders.append(folder)
        sizes[relative] = size
    with closing(sqlite3.connect(database.as_uri() + "?mode=ro", uri=True)) as db:
        names = {row[0] for row in db.execute("SELECT name FROM sqlite_master WHERE type='table'")}
        if not set(TABLES).issubset(names) or "species_catalog" not in names:
            raise ValueError("Expected Backyard tables missing; reset refused")
        for name in names:
            # Quote schema-owned identifiers; reject new incoming relationships.
            quoted = '"' + name.replace('"', '""') + '"'
            for fk in db.execute(f"PRAGMA foreign_key_list({quoted})"):
                if fk[2] in TABLES and name not in TABLES:
                    raise ValueError(f"Unexpected reference from {name}; reset refused")
        if db.execute("PRAGMA quick_check").fetchall() != [("ok",)]:
            raise ValueError("Database integrity check failed")
        counts = {t: db.execute(f"SELECT COUNT(*) FROM {t}").fetchone()[0] for t in TABLES}
        catalog = db.execute("SELECT COUNT(*) FROM species_catalog").fetchone()[0]
    return root, data, database, states, folders, sizes, counts, catalog


def reset(plan):
    root, data, database, states, folders, sizes, counts, catalog = plan
    now = datetime.now(timezone.utc)
    stamp = now.strftime("%Y%m%dT%H%M%S%fZ")
    backup = root / "reset-backups" / stamp
    backup.mkdir(parents=True, mode=0o700)
    with closing(sqlite3.connect(database.as_uri() + "?mode=ro", uri=True)) as source:
        with closing(sqlite3.connect(backup / "backyard.sqlite3")) as target:
            source.backup(target)
            if target.execute("PRAGMA quick_check").fetchall() != [("ok",)]:
                raise ValueError("Backup integrity check failed")
    for path, _ in states:
        shutil.copy2(path, backup / path.name)
    (backup / "manifest.json").write_text(json.dumps({
        "cutoff": now.isoformat(), "database": str(database), "deleted_rows": counts,
        "audio_bytes": sizes, "catalog_preserved": catalog,
        "audio_backed_up": False, "checkpoints": [str(p) for p, _ in states],
    }, indent=2))
    with closing(sqlite3.connect(database, isolation_level=None)) as db:
        db.execute("PRAGMA foreign_keys=ON")
        db.execute("BEGIN IMMEDIATE")
        try:
            for table in TABLES:
                db.execute(f"DELETE FROM {table}")
            if db.execute("PRAGMA foreign_key_check").fetchall():
                raise ValueError("Foreign-key check failed")
            db.execute("COMMIT")
        except BaseException:
            db.execute("ROLLBACK")
            raise
    # Services must remain stopped until the whole operation succeeds.
    for path, state in states:
        state.update(date=now.astimezone(ZoneInfo("Europe/Amsterdam")).date().isoformat(),
                     not_before=now.isoformat(), done=[], pending=None, phase="starting")
        path.write_text(json.dumps(state))  # preserves existing ownership/mode
    for folder in folders:
        checked = within(data, folder)
        shutil.rmtree(checked)
    with closing(sqlite3.connect(database)) as db:
        db.execute("VACUUM")
        assert db.execute("SELECT COUNT(*) FROM species_catalog").fetchone()[0] == catalog
        assert all(db.execute(f"SELECT COUNT(*) FROM {t}").fetchone()[0] == 0 for t in TABLES)
    print("Reset completed. Database/checkpoint backup:", backup)
    print("Audio permanently removed; catalog, generator assets and models preserved.")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", type=Path, default=Path("/home/cpvb86/Backyard"))
    parser.add_argument("--env", type=Path, default=Path("/etc/backyard/backyard.env"))
    parser.add_argument("--apply", action="store_true")
    args = parser.parse_args()
    plan = prepare(args.root, args.env)
    print(json.dumps({"database": str(plan[2]), "rows_to_delete": plan[6],
                      "audio_GiB": {k: round(v / 1024**3, 3) for k, v in plan[5].items()},
                      "catalog_rows_preserved": plan[7]}, indent=2))
    if not args.apply:
        print("Read-only preview. No changes made.")
        return
    if os.name != "posix" or os.geteuid() != 0:
        raise SystemExit("Apply requires sudo on the central Linux Pi")
    active = subprocess.check_output([
        "systemctl", "list-units", "--state=active", "--no-legend", "--plain",
        "backyard-*.service", "backyard-*.timer",
    ], text=True).strip()
    if active:
        raise SystemExit("Stop all backyard services/timers first:\n" + active)
    reset(plan)


if __name__ == "__main__":
    main()
