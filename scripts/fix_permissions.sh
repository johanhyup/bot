#!/usr/bin/env bash
set -euo pipefail

ROOT="/var/www/bot/bot"
PHP_DIR="$ROOT/php"
DB="$PHP_DIR/database.db"
USER_NAME="${USER_NAME:-ubuntu}"

echo "[1/3] chown/chmod recursively on $PHP_DIR"
sudo chown -R www-data:www-data "$PHP_DIR"
# 디렉터리: 2775(setgid), 파일: 664
sudo find "$PHP_DIR" -type d -exec chmod 2775 {} +
sudo find "$PHP_DIR" -type f -name "*.db*" -exec chmod 664 {} + || true

echo "[2/3] add user '$USER_NAME' to www-data group (if needed)"
if id -nG "$USER_NAME" | grep -qw www-data; then
  echo " - $USER_NAME already in www-data"
else
  sudo usermod -aG www-data "$USER_NAME"
  echo " - added $USER_NAME to www-data (re-login required)"
fi

echo "[3/3] show final perms"
ls -ld "$PHP_DIR" || true
ls -l "$DB" || true

echo "done."
echo "tip:"
echo " - re-login shell to apply group change"
echo " - test (without sudo): php $ROOT/php/cli/create_admin.php --username admin --password '1234' --name 'admin'"
