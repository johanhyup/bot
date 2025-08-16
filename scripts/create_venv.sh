#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
bash "${SCRIPT_DIR}/bootstrap_venv.sh"
echo "[venv] ready at /var/www/bot/.venv-bot"
