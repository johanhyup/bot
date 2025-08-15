#!/usr/bin/env bash
set -euo pipefail

DB_NAME="${DB_NAME:-botdb}"
DB_USER="${DB_USER:-botuser}"
DB_PASS="${DB_PASS:-strong-password}"

echo "== Step 1: install mariadb-server/client (if missing) =="
if ! command -v mariadb >/dev/null 2>&1; then
  sudo apt update
  sudo DEBIAN_FRONTEND=noninteractive apt install -y mariadb-server mariadb-client
else
  echo "mariadb client already installed: $(mariadb --version)"
fi

echo "== Step 2: enable & start service =="
if systemctl list-unit-files | grep -q '^mariadb\.service'; then
  sudo systemctl enable --now mariadb
else
  echo "mariadb.service not found, trying mysql.service"
  sudo systemctl enable --now mysql
fi

echo "== Step 3: create database and user =="
SQL="
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
"
if command -v mariadb >/dev/null 2>&1; then
  sudo mariadb -e "$SQL"
else
  sudo mysql -e "$SQL"
fi

echo "== Step 4: verify connection =="
if command -v mariadb >/dev/null 2>&1; then
  mariadb -u"${DB_USER}" -p"${DB_PASS}" -e "SHOW DATABASES;" | grep -q "${DB_NAME}" && echo "[OK] DB ready"
else
  mysql -u"${DB_USER}" -p"${DB_PASS}" -e "SHOW DATABASES;" | grep -q "${DB_NAME}" && echo "[OK] DB ready"
fi

echo "Done."
echo "- If CLI 'mysql' not found, use 'mariadb' instead."
echo "- Restart backend: sudo bash /var/www/bot/bot/scripts/restart_all.sh"
