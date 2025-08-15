#!/usr/bin/env bash
set -euo pipefail
echo "== uvicorn-bot =="
sudo systemctl status uvicorn-bot --no-pager || true
echo
echo "== apache2 =="
sudo systemctl status apache2 --no-pager || true
echo
echo "== health checks =="
curl -s -i http://127.0.0.1:8000/api/health || true
echo
curl -s -I https://jkcorp5005.com/api/health || true
