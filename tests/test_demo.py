"""Run with: python -m unittest discover -s tests -p test_demo.py -v"""
from datetime import datetime
import http.client
import json
from pathlib import Path
import sys
import tempfile
import threading
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from demo.server import FIXTURE, load_species, make_server, public_data


class DemoTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.species = load_species(FIXTURE)
        cls.server = make_server(0)
        cls.thread = threading.Thread(target=cls.server.serve_forever, daemon=True)
        cls.thread.start()

    @classmethod
    def tearDownClass(cls):
        cls.server.shutdown()
        cls.server.server_close()
        cls.thread.join()

    def request(self, path, method="GET"):
        connection = http.client.HTTPConnection("127.0.0.1", self.server.server_port)
        connection.request(method, path)
        response = connection.getresponse()
        result = response.status, response.getheader("Content-Type"), response.read()
        connection.close()
        return result

    def test_counts_and_time_windows(self):
        now = datetime(2026, 9, 25, 12)
        recent = public_data(self.species, {"action": "recent"}, now)
        self.assertEqual([b["n"] for b in recent["species"]], [14, 9, 7, 5, 4, 2, 1])
        short = public_data(self.species, {"action": "recent", "hours": "1"}, now)
        self.assertEqual([(b["com"], b["n"]) for b in short["species"]], [("Huismus", 2)])
        stats = public_data(self.species, {"action": "stats"}, now)
        self.assertEqual(stats["totals"], {"detections": 42, "species": 7})

    def test_changed_fixture_flows_to_api(self):
        birds = [dict(self.species[0], count=3)]
        recent = public_data(birds, {"action": "recent"})
        self.assertEqual(len(recent["species"]), 1)
        self.assertEqual(recent["species"][0]["n"], 3)
        self.assertEqual(public_data([], {"action": "recent"})["species"], [])

    def test_midnight_and_empty_history(self):
        now = datetime(2026, 9, 25, 0, 1)
        stats = public_data(self.species, {}, now)
        self.assertEqual(stats["today"]["detections"], 0)
        self.assertEqual(stats["totals"]["detections"], 42)
        past = public_data(self.species, {"action": "recent", "date": "2020-01-01"}, now)
        self.assertEqual(past["species"], [])

    def test_all_initial_frontend_endpoints(self):
        for action in ("stats", "recent", "lifelist", "timeseries", "firstseen", "calendar", "rhythm", "hourly", "species"):
            with self.subTest(action=action):
                status, _, body = self.request("/avian/api/birdnet-api.php?action=" + action + "&sci=Passer%20domesticus")
                self.assertEqual(status, 200)
                self.assertTrue(json.loads(body)["demo"])

    def test_assets_and_head(self):
        status, _, body = self.request("/")
        self.assertEqual(status, 200)
        self.assertIn(b"gesimuleerde detecties", body)
        for name in ("sparrow-blossom-single-v2.png", "sparrow-blossom-pair-v2.png"):
            self.assertEqual(self.request("/avian/assets/references/" + name)[0], 200)
        for bird in self.species:
            for pose in (1, 2):
                url = "/avian/api/cutout.php?sci=" + bird["sci"].replace(" ", "%20") + "&pose=" + str(pose)
                status, content_type, image = self.request(url)
                self.assertEqual((status, content_type), (200, "image/png"))
                self.assertTrue(image.startswith(b"\x89PNG"))
                self.assertEqual(self.request(url, "HEAD")[2], b"")

    def test_private_paths_and_mutations_are_unavailable(self):
        for path in ("/.env", "/.git/config", "/AGENTS.md", "/../AGENTS.md", "/%2e%2e/AGENTS.md", "/avian/api/config.php", "/avian/api/generate.php"):
            with self.subTest(path=path):
                self.assertEqual(self.request(path)[0], 404)
        self.assertEqual(self.request("/avian/api/config.php", "POST")[0], 405)
        self.assertEqual(self.request("/avian/api/birdnet-api.php?hours=bad")[0], 400)

    def test_invalid_fixtures_fail_before_startup(self):
        for fixture in ([dict(self.species[0], count=-1)], [dict(self.species[0], sci="Turdus merula")], [None]):
            with tempfile.TemporaryDirectory() as folder:
                path = Path(folder) / "birds.json"
                path.write_text(json.dumps(fixture), encoding="utf-8")
                with self.assertRaises(ValueError):
                    load_species(path)


if __name__ == "__main__":
    unittest.main()
