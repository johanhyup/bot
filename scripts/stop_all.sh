#!/usr/bin/env bash
set -euo pipefail
sudo systemctl stop uvicorn-bot || true
sudo systemctl stop apache2 || true
echo "[OK] stopped: uvicorn-bot + apache2"
