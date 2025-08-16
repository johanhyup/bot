#!/usr/bin/env bash
set -euo pipefail

ROOT="/var/www/bot/bot"
VENV="${ROOT}/venv"

echo "[venv] creating at ${VENV}"
python3 -m venv "${VENV}"

# shellcheck disable=SC1090
source "${VENV}/bin/activate"
python -V
pip install -U pip setuptools wheel
pip install -r "${ROOT}/python/requirements.txt"

echo "[venv] done. activate with: source ${VENV}/bin/activate"
