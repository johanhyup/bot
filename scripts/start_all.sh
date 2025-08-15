#!/usr/bin/env bash
set -euo pipefail
sudo systemctl enable uvicorn-bot
sudo systemctl start uvicorn-bot
sudo systemctl start apache2
echo "[OK] started: uvicorn-bot + apache2"
