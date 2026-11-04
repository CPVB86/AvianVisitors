"""On-demand, lossless ALSA audio over HTTP; no inference or disk recordings."""

import argparse
import subprocess
import threading
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer


class CaptureServer(ThreadingHTTPServer):
    daemon_threads = True

    def __init__(self, address, device, rate, path, ffmpeg="/usr/bin/ffmpeg"):
        self.device = device
        self.rate = rate
        self.path = path
        self.ffmpeg = ffmpeg
        self.capture_lock = threading.Lock()
        self.lock_timeout = 5
        super().__init__(address, CaptureHandler)


class CaptureHandler(BaseHTTPRequestHandler):
    # A close-delimited response is deliberate: FLAC is an unbounded live stream.
    protocol_version = "HTTP/1.0"

    def do_HEAD(self):
        if self.path != self.server.path:
            self.send_error(404)
            return
        self.send_response(200)
        self.send_header("Content-Type", "audio/flac")
        self.send_header("Cache-Control", "no-store")
        self.end_headers()

    def do_GET(self):
        server = self.server
        if self.path != server.path:
            self.send_error(404)
            return
        # ffprobe and the actual decoder may connect in quick succession.
        # Wait briefly for the previous capture to finish releasing ALSA.
        if not server.capture_lock.acquire(timeout=server.lock_timeout):
            self.send_error(503, "Microphone is already in use")
            return
        process = None
        try:
            self.connection.settimeout(10)
            try:
                process = subprocess.Popen([
                    server.ffmpeg, "-nostdin", "-hide_banner", "-loglevel", "warning",
                    "-thread_queue_size", "1024", "-f", "alsa",
                    "-sample_rate", str(server.rate), "-channels", "1",
                    "-i", server.device, "-c:a", "flac", "-compression_level", "0",
                    "-flush_packets", "1", "-f", "flac", "pipe:1",
                ], stdout=subprocess.PIPE, bufsize=0)
            except OSError:
                self.send_error(502, "Cannot start audio capture")
                return
            # Do not advertise a working stream if opening the device failed.
            first = process.stdout.read(8192)
            if not first:
                self.send_error(502, "Audio capture produced no data")
                return
            self.send_response(200)
            self.send_header("Content-Type", "audio/flac")
            self.send_header("Cache-Control", "no-store")
            self.end_headers()
            self.wfile.write(first)
            while data := process.stdout.read(8192):
                self.wfile.write(data)
        except (ConnectionError, TimeoutError):
            # A probe, test or decoder disconnected. The next GET starts fresh.
            pass
        finally:
            try:
                if process is not None:
                    try:
                        if process.poll() is None:
                            process.terminate()
                        try:
                            process.wait(timeout=3)
                        except subprocess.TimeoutExpired:
                            process.kill()
                            process.wait()
                    finally:
                        process.stdout.close()
            finally:
                server.capture_lock.release()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--bind", default="127.0.0.1")
    parser.add_argument("--port", type=int, required=True)
    parser.add_argument("--device", required=True)
    parser.add_argument("--rate", type=int, choices=(48000, 384000), required=True)
    parser.add_argument("--path", required=True)
    args = parser.parse_args()
    if not args.path.startswith("/"):
        parser.error("--path must start with /")
    with CaptureServer((args.bind, args.port), args.device, args.rate, args.path) as server:
        print(f"Listening on {args.bind}:{args.port}{args.path}; rate={args.rate}", flush=True)
        try:
            server.serve_forever()
        except KeyboardInterrupt:
            pass


if __name__ == "__main__":
    main()
