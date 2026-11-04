import json
from contextlib import closing
from pathlib import Path
import sqlite3
import tempfile
import unittest

from reset_central import AUDIO, prepare, reset


class ResetTests(unittest.TestCase):
    def setUp(self):
        workspace_temp = Path(__file__).resolve().parents[1] / ".avian"
        workspace_temp.mkdir(exist_ok=True)
        self.temp = tempfile.TemporaryDirectory(dir=workspace_temp)
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name).resolve()
        data = self.root / "data"
        data.mkdir()
        self.database = data / "backyard.sqlite3"
        with closing(sqlite3.connect(self.database)) as db:
            db.executescript("""
                CREATE TABLE observations(id INTEGER PRIMARY KEY);
                CREATE TABLE observation_candidates(id INTEGER PRIMARY KEY,
                    observation_id INTEGER REFERENCES observations(id));
                CREATE TABLE bird_detections(id INTEGER PRIMARY KEY);
                CREATE TABLE bird_audio(id INTEGER PRIMARY KEY,
                    detection_id INTEGER REFERENCES bird_detections(id));
                CREATE TABLE species_catalog(id INTEGER PRIMARY KEY);
                INSERT INTO observations VALUES(1);
                INSERT INTO observation_candidates VALUES(1,1);
                INSERT INTO bird_detections VALUES(1);
                INSERT INTO bird_audio VALUES(1,1);
                INSERT INTO species_catalog VALUES(1);
            """)
        self.paths = []
        for domain in ("bird", "bat"):
            path = data / f"{domain}-state.json"
            path.write_text(json.dumps({"instance": domain, "domain": domain,
                                       "date": "2026-01-01", "done": ["1"], "pending": None}))
            self.paths.append(path)
        self.env = self.root / "backyard.env"
        self.env.write_text(f"BACKYARD_GO_BIRD_STATE={self.paths[0]}\n"
                            f"BACKYARD_BAT_STATE={self.paths[1]}\n")
        for relative in AUDIO:
            folder = data / relative
            folder.mkdir(parents=True)
            (folder / "old.wav").write_bytes(b"test audio")
        self.asset = data / "generator" / "bird.png"
        self.asset.parent.mkdir()
        self.asset.write_bytes(b"keep artwork")

    def test_preview_is_read_only(self):
        plan = prepare(self.root, self.env)
        self.assertEqual(plan[6]["observations"], 1)
        self.assertFalse((self.root / "reset-backups").exists())
        self.assertTrue((self.root / "data" / AUDIO[0] / "old.wav").exists())

    def test_apply_preserves_catalog_assets_and_restorable_database(self):
        reset(prepare(self.root, self.env))
        self.assertEqual(self.asset.read_bytes(), b"keep artwork")
        self.assertTrue(all(not (self.root / "data" / p).exists() for p in AUDIO))
        with closing(sqlite3.connect(self.database)) as db:
            self.assertEqual(db.execute("SELECT COUNT(*) FROM observations").fetchone()[0], 0)
            self.assertEqual(db.execute("SELECT COUNT(*) FROM species_catalog").fetchone()[0], 1)
        backups = list((self.root / "reset-backups").glob("*/backyard.sqlite3"))
        self.assertEqual(len(backups), 1)
        with closing(sqlite3.connect(backups[0])) as db:
            self.assertEqual(db.execute("SELECT COUNT(*) FROM observations").fetchone()[0], 1)
        states = [json.loads(p.read_text()) for p in self.paths]
        self.assertEqual(states[0]["not_before"], states[1]["not_before"])
        self.assertEqual(states[0]["done"], [])

    def test_unknown_incoming_relationship_refuses_reset(self):
        with closing(sqlite3.connect(self.database)) as db:
            db.execute("CREATE TABLE new_module(id INTEGER REFERENCES observations(id))")
        with self.assertRaisesRegex(ValueError, "Unexpected reference"):
            prepare(self.root, self.env)

    def test_pending_import_refuses_reset(self):
        state = json.loads(self.paths[0].read_text())
        state["pending"] = {"record": {"id": 1}}
        self.paths[0].write_text(json.dumps(state))
        with self.assertRaisesRegex(ValueError, "Pending import"):
            prepare(self.root, self.env)


if __name__ == "__main__":
    unittest.main()
