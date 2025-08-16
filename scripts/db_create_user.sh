#!/usr/bin/env bash
set -euo pipefail

# 사용법:
#   DB_NAME=botdb DB_USER=botuser DB_PASS='strong-password' /var/www/bot/bot/scripts/db_create_user.sh
# 옵션:
#   AUTH_PLUGIN=mysql_native_password  # (선택) MySQL 8에서 플러그인 강제 필요 시

DB_NAME="${DB_NAME:-botdb}"
DB_USER="${DB_USER:-botuser}"
DB_PASS="${DB_PASS:-strong-password}"
AUTH_PLUGIN="${AUTH_PLUGIN:-}"

SQL_CREATE_DB="CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
SQL_CREATE_USER="CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
if [[ -n "$AUTH_PLUGIN" ]]; then
  SQL_CREATE_USER="CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED WITH ${AUTH_PLUGIN} BY '${DB_PASS}';"
fi
SQL_ALTER_PW="ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
if [[ -n "$AUTH_PLUGIN" ]]; then
  SQL_ALTER_PW="ALTER USER '${DB_USER}'@'localhost' IDENTIFIED WITH ${AUTH_PLUGIN} BY '${DB_PASS}';"
fi
SQL_GRANT="GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost'; FLUSH PRIVILEGES;"

echo "== applying DDL on server =="
if command -v mariadb >/dev/null 2>&1; then
  sudo mariadb -e "${SQL_CREATE_DB} ${SQL_CREATE_USER} ${SQL_ALTER_PW} ${SQL_GRANT}"
elif command -v mysql >/dev/null 2>&1; then
  sudo mysql -e "${SQL_CREATE_DB} ${SQL_CREATE_USER} ${SQL_ALTER_PW} ${SQL_GRANT}"
else
  echo "No mysql/mariadb client found. Install server/client first." >&2
  exit 1
fi

echo "== verify connectivity =="
if command -v mariadb >/dev/null 2>&1; then
  mariadb -u"${DB_USER}" -p"${DB_PASS}" -D "${DB_NAME}" -e "SELECT DATABASE() AS db, CURRENT_USER() AS user, 1 AS ok;"
else
  mysql -u"${DB_USER}" -p"${DB_PASS}" -D "${DB_NAME}" -e "SELECT DATABASE() AS db, CURRENT_USER() AS user, 1 AS ok;"
fi

echo "[OK] user '${DB_USER}' ready on database '${DB_NAME}'"
echo "- php/config.php: DB_HOST=127.0.0.1, DB_NAME=${DB_NAME}, DB_USER=${DB_USER}, DB_PASS=<같은 비번>"
echo "- python/.env   : MYSQL_HOST=127.0.0.1, MYSQL_DB=${DB_NAME}, MYSQL_USER=${DB_USER}, MYSQL_PASS=<같은 비번>"
