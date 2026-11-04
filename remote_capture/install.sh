#!/bin/sh
set -eu
cd "$(dirname "$0")"
if [ "$(id -u)" -ne 0 ]; then
    echo 'Run with sudo sh install.sh' >&2
    exit 1
fi
command -v ffmpeg >/dev/null
command -v python3 >/dev/null
getent group audio >/dev/null
install -d -m 755 /opt/byremote /etc/byremote
install -m 644 stream.py /opt/byremote/stream.py
install -m 644 byremote-audio@.service /etc/systemd/system/byremote-audio@.service
for source in birds bats; do
    if [ ! -e "/etc/byremote/$source.conf" ]; then
        install -m 644 "$source.conf.example" "/etc/byremote/$source.conf"
    fi
done
systemctl daemon-reload
# Restart explicitly so reinstalling also activates updated code.
systemctl enable byremote-audio@birds.service byremote-audio@bats.service
systemctl restart byremote-audio@birds.service byremote-audio@bats.service
systemctl --no-pager --full status byremote-audio@birds.service byremote-audio@bats.service
