#!/usr/bin/env bash
set -euo pipefail
# 실행: bash /Users/joko/bot/scripts/run_uvicorn.sh
ROOT="/Users/joko/bot"
cd "$ROOT"

export PYTHONUNBUFFERED=1
export PYTHONPATH="$ROOT${PYTHONPATH:+:$PYTHONPATH}"

HOST="${HOST:-127.0.0.1}"
PORT="${PORT:-8000}"
APP="python.app.main:app"

echo "[run] starting uvicorn ${APP} on ${HOST}:${PORT} (cwd=$(pwd))"
exec uvicorn "${APP}" --host "${HOST}" --port "${PORT}" --workers 1
