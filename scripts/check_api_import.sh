#!/usr/bin/env bash
set -euo pipefail
ROOT="/var/www/bot/bot"
VENV="${ROOT}/venv"

cd "$ROOT"
# shellcheck disable=SC1090
source "${VENV}/bin/activate"

export PYTHONPATH="$ROOT${PYTHONPATH:+:$PYTHONPATH}"
python - <<'PY'
import sys
print("python:", sys.version)
print("sys.path[0]:", sys.path[0])
from python.app.main import app
print("import OK:", type(app).__name__)
PY
