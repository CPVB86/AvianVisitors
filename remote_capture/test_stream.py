"""HTTP lifecycle tests; fake capture is deliberately not an ALSA/audio test."""

import http.client
import io
import threading
import unittest
from unittest.mock import patch

from stream import CaptureServer


class FakeCapture:
    def __init__(self, data=b"fLaC" + b"\0" * 20000):
        self.stdout = io.BytesIO(data)
        self.stopped = False

    def poll(self):
        return None if not self.stopped else 0

    def terminate(self):
        self.stopped = True

    def wait(self, timeout=None):
        return 0


class HTTPTests(unittest.TestCase):
    def setUp(self):
        self.server = CaptureServer(("127.0.0.1", 0), "hw:CARD=Test,DEV=0", 384000, "/bats.flac")
        self.thread = threading.Thread(target=self.server.serve_forever, daemon=True)
        self.thread.start()

    def tearDown(self):
        self.server.shutdown()
        self.server.server_close()
        self.thread.join()

    def request(self, method, path):
        connection = http.client.HTTPConnection(*self.server.server_address, timeout=3)
        try:
            connection.request(method, path)
            response = connection.getresponse()
            return response.status, response.read()
        finally:
            connection.close()

    @patch("stream.subprocess.Popen")
    def test_head_and_unknown_path_do_not_open_microphone(self, popen):
        self.assertEqual(self.request("HEAD", "/bats.flac")[0], 200)
        self.assertEqual(self.request("GET", "/other")[0], 404)
        popen.assert_not_called()

    @patch("stream.subprocess.Popen")
    def test_probe_then_decoder_get_fresh_capture_and_preserve_rate(self, popen):
        captures = [FakeCapture(), FakeCapture()]
        popen.side_effect = captures
        for _ in range(2):
            status, body = self.request("GET", "/bats.flac")
            self.assertEqual(status, 200)
            self.assertTrue(body.startswith(b"fLaC"))
        self.assertEqual(popen.call_count, 2)
        for capture in captures:
            self.assertTrue(capture.stopped)
            self.assertTrue(capture.stdout.closed)
        args = popen.call_args.args[0]
        self.assertEqual(args[args.index("-sample_rate") + 1], "384000")
        self.assertNotIn("-ar", args)

    @patch("stream.subprocess.Popen")
    def test_busy_microphone_returns_503_without_second_capture(self, popen):
        self.server.lock_timeout = 0.01
        self.server.capture_lock.acquire()
        try:
            self.assertEqual(self.request("GET", "/bats.flac")[0], 503)
            popen.assert_not_called()
        finally:
            self.server.capture_lock.release()

    @patch("stream.subprocess.Popen")
    def test_device_failure_returns_502_then_releases_microphone(self, popen):
        popen.side_effect = [FakeCapture(b""), FakeCapture()]
        self.assertEqual(self.request("GET", "/bats.flac")[0], 502)
        self.assertEqual(self.request("GET", "/bats.flac")[0], 200)


if __name__ == "__main__":
    unittest.main()
