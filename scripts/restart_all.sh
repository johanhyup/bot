#!/usr/bin/env bash
set -euo pipefail
sudo systemctl daemon-reload
sudo systemctl restart uvicorn-bot
sudo systemctl reload apache2 || sudo systemctl restart apache2
echo "[OK] restarted: uvicorn-bot + apache2"
