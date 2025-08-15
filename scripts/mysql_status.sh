#!/usr/bin/env bash
set -euo pipefail

DB_NAME="${DB_NAME:-botdb}"
DB_USER="${DB_USER:-botuser}"
DB_PASS="${DB_PASS:-strong-password}"

echo "== version =="
mariadb --version || mysql --version || { echo "no client found"; }

echo
echo "== service =="
systemctl status mariadb --no-pager || systemctl status mysql --no-pager || echo "no service found"

echo
echo "== ping =="
mariadb-admin ping || mysqladmin ping || echo "no admin client found"

echo
echo "== test query =="
if command -v mariadb >/dev/null 2>&1; then
  mariadb -u"${DB_USER}" -p"${DB_PASS}" -e "SELECT DATABASE(); SHOW DATABASES LIKE '${DB_NAME}';" || true
elif command -v mysql >/dev/null 2>&1; then
  mysql -u"${DB_USER}" -p"${DB_PASS}" -e "SELECT DATABASE(); SHOW DATABASES LIKE '${DB_NAME}';" || true
else
  echo "no mysql/mariadb client found"
fi
