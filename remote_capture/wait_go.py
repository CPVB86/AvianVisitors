"""Wait for a valid local BirdNET-Go detection API before starting a bridge."""
import argparse
import http.client
import json
import os
import time
from urllib.parse import urlsplit


def wait_ready(origin, timeout=90, interval=1):
    parsed = urlsplit(origin)
    if (parsed.scheme != "http" or parsed.hostname not in ("127.0.0.1", "localhost", "::1")
            or parsed.username or parsed.password or parsed.path not in ("", "/")
            or parsed.query or parsed.fragment):
        raise ValueError("Expected a local HTTP origin without credentials")
    deadline = time.monotonic() + timeout
    while time.monotonic() < deadline:
        connection = http.client.HTTPConnection(
            parsed.hostname, parsed.port or 80,
            timeout=max(0.01, min(3, deadline - time.monotonic())),
        )
        try:
            connection.request("GET", "/api/v2/detections?limit=1")
            response = connection.getresponse()
            body = response.read(262145)
            if response.status == 200 and len(body) <= 262144:
                page = json.loads(body)
                if (isinstance(page, dict) and isinstance(page.get("data"), list)
                        and type(page.get("total")) is int and page["total"] >= 0):
                    print("BirdNET-Go detection API ready.", flush=True)
                    return
        except (OSError, http.client.HTTPException, ValueError):
            pass
        finally:
            connection.close()
        time.sleep(min(interval, max(0, deadline - time.monotonic())))
    raise TimeoutError("BirdNET-Go detection API did not become ready in time")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--url-env", required=True,
                        choices=("BACKYARD_GO_BIRD_URL", "BACKYARD_BIRDNET_GO_URL"))
    parser.add_argument("--timeout", type=float, default=90)
    args = parser.parse_args()
    try:
        wait_ready(os.environ.get(args.url_env, "http://127.0.0.1:8082"), args.timeout)
    except (ValueError, TimeoutError) as error:
        raise SystemExit(str(error)) from None


if __name__ == "__main__":
    main()
