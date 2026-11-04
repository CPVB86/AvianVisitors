import unittest
from unittest.mock import patch

from wait_go import wait_ready


def response(status, body):
    result = unittest.mock.Mock()
    result.status = status
    result.read.return_value = body
    return result


class ReadinessTests(unittest.TestCase):
    @patch("wait_go.time.sleep")
    @patch("wait_go.http.client.HTTPConnection")
    def test_retries_reset_and_invalid_json_before_ready(self, connection, sleep):
        client = connection.return_value
        client.getresponse.side_effect = [
            ConnectionResetError(), response(200, b"not json"),
            response(503, b"{}"), response(200, b'{"data": [], "total": 0}'),
        ]
        wait_ready("http://127.0.0.1:8082", timeout=2, interval=0)
        self.assertEqual(client.getresponse.call_count, 4)
        self.assertEqual(client.close.call_count, 4)
        client.request.assert_called_with("GET", "/api/v2/detections?limit=1")

    @patch("wait_go.http.client.HTTPConnection")
    def test_http_200_without_detection_contract_is_not_ready(self, connection):
        connection.return_value.getresponse.return_value = response(200, b"{}")
        with self.assertRaises(TimeoutError):
            wait_ready("http://127.0.0.1:8082", timeout=0.01, interval=0.02)

    def test_rejects_external_hosts_and_credentials(self):
        for origin in ("http://example.com", "http://user:secret@localhost",
                       "http://localhost/path", "https://localhost"):
            with self.subTest(origin=origin), self.assertRaises(ValueError):
                wait_ready(origin)

    @patch("wait_go.http.client.HTTPConnection")
    def test_expired_deadline_does_not_attempt_connection(self, connection):
        with self.assertRaises(TimeoutError):
            wait_ready("http://localhost:8082", timeout=0)
        connection.assert_not_called()


if __name__ == "__main__":
    unittest.main()
