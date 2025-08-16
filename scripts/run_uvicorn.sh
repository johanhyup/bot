#!/usr/bin/env bash
set -euo pipefail
ROOT="/var/www/bot/bot"
VENV="${ROOT}/venv"
APP="python.app.main:app"
HOST="${HOST:-127.0.0.1}"
PORT="${PORT:-8000}"

cd "$ROOT"

if [[ ! -x "${VENV}/bin/python" ]]; then
  echo "[run] venv not found. Run: bash /Users/joko/bot/scripts/create_venv.sh" >&2
  exit 1
fi

# shellcheck disable=SC1090
source "${VENV}/bin/activate"
export PYTHONUNBUFFERED=1
export PYTHONPATH="$ROOT${PYTHONPATH:+:$PYTHONPATH}"

echo "[run] $(which python) | $(python -V)"
echo "[run] starting uvicorn ${APP} on ${HOST}:${PORT} (cwd=$(pwd))"
exec uvicorn "${APP}" --host "${HOST}" --port "${PORT}" --workers 1
