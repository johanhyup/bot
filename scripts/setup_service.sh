#!/usr/bin/env bash
set -euo pipefail
SERVICE_SRC="/var/www/bot/bot/deploy/uvicorn-bot.service"
SERVICE_DST="/etc/systemd/system/uvicorn-bot.service"

test -f "$SERVICE_SRC" || { echo "missing: $SERVICE_SRC" >&2; exit 1; }
sudo cp -f "$SERVICE_SRC" "$SERVICE_DST"
sudo chmod 644 "$SERVICE_DST"
sudo systemctl daemon-reload
sudo systemctl enable uvicorn-bot
sudo systemctl restart uvicorn-bot
sudo systemctl status uvicorn-bot --no-pager || true
