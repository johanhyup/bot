#!/usr/bin/env bash
set -euo pipefail

SVC=uvicorn-bot
VENV=/var/www/bot/bot/python/venv
PY=$VENV/bin/python
UVICORN="$PY -m uvicorn"

echo "== systemd status =="
sudo systemctl status "$SVC" --no-pager || true

echo
echo "== recent logs =="
sudo journalctl -u "$SVC" -n 200 --no-pager || true

echo
echo "== venv binaries =="
ls -l "$VENV/bin" | sed -n '1,10p' || true

echo
echo "== python versions =="
$PY -V || true
$PY -c "import sys; print(sys.executable)" || true

echo
echo "== import checks =="
$PY - <<'PYCODE' || true
import sys
print("sys.path[0]:", sys.path[0])
import ccxt
print("ccxt ok:", ccxt.__version__)
import importlib
m = importlib.import_module("python.app.main")
print("module import ok:", m is not None)
PYCODE

echo
echo "== try local bind test (2s) =="
# 동일 옵션으로 짧게 실행해 즉시 에러 노출
set +e
$UVICORN python.app.main:app --host 127.0.0.1 --port 8001 --log-level info --timeout-keep-alive 2 &
PID=$!
sleep 2
curl -s -i http://127.0.0.1:8001/api/health || true
kill -9 $PID >/dev/null 2>&1 || true
set -e

echo
echo "== curl via service port =="
curl -s -i http://127.0.0.1:8000/api/health || true
