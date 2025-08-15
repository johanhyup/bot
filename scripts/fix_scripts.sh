#!/usr/bin/env bash
set -euo pipefail

SCRIPTS_DIR="/var/www/bot/bot/scripts"

echo "[1/2] normalize line endings -> LF"
for f in "$SCRIPTS_DIR"/*.sh; do
  sudo sed -i 's/\r$//' "$f" || true
done

echo "[2/2] chmod +x for all scripts"
sudo chmod +x "$SCRIPTS_DIR"/*.sh

echo "Done. Listing:"
ls -l "$SCRIPTS_DIR"
