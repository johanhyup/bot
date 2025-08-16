#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="/var/www/bot/bot"
VENV="/var/www/bot/.venv-bot"
LOG_DIR="${ROOT}/logs"
APP="python.app.main:app"
HOST="${HOST:-127.0.0.1}"
PORT="${PORT:-8000}"

mkdir -p "${LOG_DIR}"

# (추가) 권한 보정
chmod +x "${SCRIPT_DIR}/"*.sh || true

echo "[restart] bootstrap venv"
bash "${SCRIPT_DIR}/bootstrap_venv.sh"

echo "[restart] kill existing uvicorn (if any)"
pkill -f "uvicorn ${APP}" || true
sleep 1

echo "[restart] import check"
bash "${SCRIPT_DIR}/check_api_import.sh"

echo "[restart] start uvicorn in foreground"
# (변경) bash로 실행
exec bash "${SCRIPT_DIR}/run_uvicorn.sh"
