# BYRemote audio capture

BYRemote captures audio only. BirdNET-Go, the bird/bat observation bridges,
Backyard API, storage and models stay on the central Backyard Pi. No model,
Docker, API secret or persistent recording is needed on the remote station.
This is independent of future sensor collectors on that station.

## Installation on BYRemote

Prerequisites: Raspberry Pi OS 64-bit, Python 3 and FFmpeg with ALSA/FLAC.
Copy this directory to BYRemote, then run there:

```sh
sudo apt install -y python3 ffmpeg
sudo sh remote_capture/install.sh
```

The installer preserves existing `/etc/byremote/birds.conf` and `bats.conf`.
Each microphone has an independent systemd instance and process. The example
configuration binds to all interfaces on ports 8085 and 8086; these are
unauthenticated LAN audio endpoints. Do not expose them to the internet.
Set `BIND` to the station's LAN address to restrict the listening interface.
Use a DHCP reservation for BYRemote before saving its URL in BirdNET-Go.

```sh
systemctl --no-pager status byremote-audio@birds byremote-audio@bats
journalctl --no-pager -u byremote-audio@birds -u byremote-audio@bats -n 60
```

Configuration uses ALSA card names, not changing numeric card indexes.
Revisit device identity if adding another generic `Device` USB microphone.
After editing a config file, restart its service. Stop both with:

```sh
sudo systemctl stop byremote-audio@birds byremote-audio@bats
```

## Behavior and central integration

GET `/birds.flac` captures mono PCM16 at 48000 Hz; GET `/bats.flac` captures
mono PCM16 at 384000 Hz. FFmpeg encodes losslessly, without changing rate.
Capture starts only after the HTTP request, avoiding opening ALSA while waiting
for a receiver. Disconnect releases the device and a new GET starts a fresh
capture. Each endpoint allows one active capture; another request waits up to
five seconds and then gets 503. HEAD does not open the microphone. No audio is
stored on BYRemote. This is live capture, with no offline backlog/replay.

BirdNET-Go 20261014 supports HTTP streams, per-stream `models` and source-rate
probing. Back up its configuration before replacing the local audio sources
with two `realtime.rtsp.streams` entries of type `http`, enabled, targeting
`birdnet` and `bat` respectively. Keep detection export/bridge/model settings.
Confirm that probing succeeds at 384000 Hz and that the bat stream is not
silently resampled; initial probe failure can fall back to 48000 Hz.
Do not restart the central bridges until the new capture pipeline is checked.

## Verification status, 2026-11-04

User-executed on the real Pis: direct ALSA recordings from both microphones;
separate 60-second network tests; simultaneous 120-second FLAC streams received
as mono PCM16 at 48000/384000 Hz and approximately real-time speed. Those tests
used FFmpeg's single-client HTTP listener, not this service. Each sender logged
one startup ALSA xrun; end-of-test disconnects caused expected sender errors.
Vogel microphone was heard without obvious distortion. Ultrasonic quality has
not yet been verified with a spectrogram.

Local HTTP lifecycle tests use fake capture processes; they do not verify ALSA,
FLAC encoding or ARM/systemd permissions. The on-demand service, reboot recovery,
BirdNET-Go integration and real detections remain to be verified on the Pis.
All four lifecycle tests passed on Windows using Python and real localhost HTTP
requests, with mocked capture processes.

Run local tests from repository root:

```sh
python -m unittest discover -s remote_capture -p 'test_*.py' -v
```
