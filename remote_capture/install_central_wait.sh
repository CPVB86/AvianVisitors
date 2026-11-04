#!/bin/sh
set -eu
cd "$(dirname "$0")"
if [ "$(id -u)" -ne 0 ]; then
    echo 'Run with sudo sh install_central_wait.sh' >&2
    exit 1
fi
install -d -m 755 /opt/backyard-remote
install -m 644 wait_go.py /opt/backyard-remote/wait_go.py
for domain in birds bats; do
    directory="/etc/systemd/system/backyard-$domain.service.d"
    install -d -m 755 "$directory"
    target="$directory/30-wait-birdnet-go.conf"
    if [ -e "$target" ]; then
        cp -p "$target" "$target.bak-$(date +%Y%m%d%H%M%S)"
    fi
    if [ "$domain" = birds ]; then
        variable=BACKYARD_GO_BIRD_URL
    else
        variable=BACKYARD_BIRDNET_GO_URL
    fi
    cat > "$target" <<EOF
[Unit]
After=backyard-birdnet-go.service

[Service]
TimeoutStartSec=180
ExecStartPre=/usr/bin/python3 /opt/backyard-remote/wait_go.py --url-env $variable --timeout 90
EOF
done
systemctl daemon-reload
systemctl restart backyard-birds.service backyard-bats.service
systemctl --no-pager --full status backyard-birds.service backyard-bats.service
