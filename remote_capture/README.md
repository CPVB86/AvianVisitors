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
FLAC encoding or ARM/systemd permissions. Subsequent user-run Pi checks verified
both on-demand services, two sequential probes per endpoint, automatic startup
after BYRemote reboot and reconnection from Backyard. Central model processing
and bat WAV export at 384000 Hz were observed; three bat events were imported
before a short clip and later an audio 404 blocked the bridge. This does not
verify the biological correctness of model labels or ultrasonic signal quality.
All four lifecycle tests passed on Windows using Python and real localhost HTTP
requests, with mocked capture processes.

Run local tests from repository root:

```sh
python -m unittest discover -s remote_capture -p 'test_*.py' -v
```

## Explicit reset on the CENTRAL Backyard Pi

`reset_central.py` is a separate maintenance tool, not part of the remote
capture service. Its default preview is read-only. It expects the inspected
Backyard schema, default database/storage paths and both current bridge state
paths in `/etc/backyard/backyard.env`. Unknown relationships, symlinked paths,
non-default storage and pending imports cause refusal before reset.

```sh
sudo python3 reset_central.py
# Stop central writers and timers before applying; BYRemote stays running.
sudo systemctl stop 'backyard-*.timer' 'backyard-*.service'
sudo python3 reset_central.py --apply
df -h /home/cpvb86/Backyard/data
```

Apply backs up the SQLite database and current bridge checkpoints under
`Backyard/reset-backups/<timestamp>/`. It clears ONLY observation_candidates,
bird_audio, observations and bird_detections, and permanently deletes ONLY
data/birds/audio, data/bats/audio, data/review/bird/audio and
data/review/bat/audio. Audio is NOT backed up. Catalog, generator files,
Samsung settings, code, environment and models remain. The current checkpoints
retain instance IDs but get empty done lists and a shared new UTC cutoff.
The upstream BirdNET-Go database and clips are not reset by this tool.

Do not restart central writers if any reset step fails. Check the printed
backup/manifest and failure first; database and filesystem changes are not one
atomic transaction. Previously deleted audio cannot be restored from the DB
backup. Restart only the required API/capture/bridge services after success;
previously active optional timers must be deliberately restored separately.

The four reset tests use synthetic SQLite/audio files and pass locally on
Windows. The user subsequently executed the reset on the central Pi and reported
success with backup `reset-backups/20261104T103227635675Z`. Disk use fell from
84% to 41%, with 30 GB available. Required central services were started again.
Post-reset service health, imports, retained catalog inventory and restoration
of wanted optional timers still need runtime verification. Do not repeat the
reset merely because those checks are pending.
