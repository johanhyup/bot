#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="/var/www/bot/bot"
SERVICE_SRC="$REPO_DIR/deploy/uvicorn-bot.service"
SERVICE_DST="/etc/systemd/system/uvicorn-bot.service"
APACHE_SRC="$REPO_DIR/deploy/apache-bot.conf"
APACHE_DST="/etc/apache2/sites-available/bot.conf"

# 1) Python venv 및 의존성
cd "$REPO_DIR/python"
python3 -m venv venv
source venv/bin/activate
pip install -U pip
pip install -r requirements.txt

# 2) systemd 서비스 설치/기동
sudo cp -f "$SERVICE_SRC" "$SERVICE_DST"
sudo systemctl daemon-reload
sudo systemctl enable uvicorn-bot
sudo systemctl restart uvicorn-bot
sudo systemctl status uvicorn-bot --no-pager || true

# 3) Apache 프록시/SSL 설정
sudo a2enmod proxy proxy_http headers ssl rewrite
sudo cp -f "$APACHE_SRC" "$APACHE_DST"
sudo a2ensite bot.conf || true
sudo systemctl reload apache2

echo "Done. Check: curl -s https://jkcorp5005.com/api/health"
