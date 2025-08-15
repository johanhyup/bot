#!/usr/bin/env bash
set -euo pipefail

echo "== systemd services =="
systemctl status uvicorn-bot --no-pager || true
systemctl status apache2 --no-pager || true
systemctl status mariadb --no-pager || systemctl status mysql --no-pager || true

echo
echo "== listening ports =="
ss -ltnp | egrep ':8000|:80|:443' || true

echo
echo "== recent logs =="
journalctl -u uvicorn-bot -n 150 --no-pager || true
echo
sudo tail -n 150 /var/log/apache2/error.log 2>/dev/null || true
sudo tail -n 150 /var/log/apache2/access.log 2>/dev/null || true
