#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="/var/www/bot/bot"
VENV="/var/www/bot/.venv-bot"
APP="python.app.main:app"
HOST="${HOST:-127.0.0.1}"
PORT="${PORT:-8000}"

bash "${SCRIPT_DIR}/bootstrap_venv.sh"

cd "$ROOT"
# shellcheck disable=SC1090
source "${VENV}/bin/activate"
export PYTHONUNBUFFERED=1
export PYTHONPATH="$ROOT${PYTHONPATH:+:$PYTHONPATH}"

echo "[run] $(which python) | $(python -V)"
echo "[run] starting uvicorn ${APP} on ${HOST}:${PORT} (cwd=$(pwd))"
exec uvicorn "${APP}" --host "${HOST}" --port "${PORT}" --workers 1
