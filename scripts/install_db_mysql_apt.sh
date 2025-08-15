#!/usr/bin/env bash
set -euo pipefail

DB_NAME="${DB_NAME:-botdb}"
DB_USER="${DB_USER:-botuser}"
DB_PASS="${DB_PASS:-strong-password}"

echo "== Install prerequisites =="
sudo apt update
sudo DEBIAN_FRONTEND=noninteractive apt install -y wget lsb-release gnupg

echo "== Add MySQL APT repository (Oracle) =="
TMP_DEB="/tmp/mysql-apt-config.deb"
wget -O "$TMP_DEB" https://dev.mysql.com/get/mysql-apt-config_0.8.32-1_all.deb
# 기본 옵션으로 설치(필요 시 TUI에서 Server & Tools enabled 확인)
sudo DEBIAN_FRONTEND=noninteractive dpkg -i "$TMP_DEB" || true
sudo apt update

echo "== Install mysql-server =="
sudo DEBIAN_FRONTEND=noninteractive apt install -y mysql-server mysql-client

echo "== Enable & start mysql =="
sudo systemctl enable --now mysql

echo "== Create database and user =="
sudo mysql -e "
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;"

echo "== Verify connection =="
mysql -u"${DB_USER}" -p"${DB_PASS}" -e "SHOW DATABASES;" | grep -q "${DB_NAME}" && echo "[OK] DB ready" || { echo "[ERR] DB verify failed"; exit 1; }

echo "Done.
- PHP 설정: /var/www/bot/bot/php/config.php(DB_HOST/DB_NAME/DB_USER/DB_PASS 확인)
- Python .env: MYSQL_HOST=127.0.0.1, MYSQL_DB=${DB_NAME}, MYSQL_USER=${DB_USER}, MYSQL_PASS=<비밀번호>
- 재시작: sudo bash /var/www/bot/bot/scripts/restart_all.sh
"
