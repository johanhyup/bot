#!/usr/bin/env bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="/var/www/bot/bot"
VENV="/var/www/bot/.venv-bot"

# venv 보장(없으면 생성)
[[ -x "${VENV}/bin/python" ]] || bash "${SCRIPT_DIR}/bootstrap_venv.sh"

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
