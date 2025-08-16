#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="/var/www/bot/bot/python"
VENV_DIR="${APP_ROOT}/venv"
REQ_FILE="${APP_ROOT}/requirements.txt"

if [[ ! -d "$VENV_DIR" ]]; then
  echo "== create venv =="
  python3 -m venv "$VENV_DIR"
fi

echo "== upgrade pip =="
"${VENV_DIR}/bin/pip" install -U pip

echo "== install requirements =="
"${VENV_DIR}/bin/pip" install -r "$REQ_FILE"

echo "== show installed PyMySQL =="
"${VENV_DIR}/bin/pip" show PyMySQL || true

echo "Done. Restart uvicorn-bot after this."
