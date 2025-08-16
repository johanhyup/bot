#!/usr/bin/env bash
set -euo pipefail
ROOT="/Users/joko/bot"
cd "$ROOT"
export PYTHONPATH="$ROOT${PYTHONPATH:+:$PYTHONPATH}"
python3 - <<'PY'
import sys
print("sys.path[0]:", sys.path[0])
from python.app.main import app
print("import OK:", type(app).__name__)
PY
