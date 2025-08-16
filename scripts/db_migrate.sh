#!/usr/bin/env bash
set -euo pipefail

# 사용법
#   bash /Users/joko/bot/scripts/db_migrate.sh
# 또는 환경변수로 오버라이드
#   DB_HOST=127.0.0.1 DB_NAME=botdb DB_USER=botuser DB_PASS='password' bash /Users/joko/bot/scripts/db_migrate.sh

BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SQL_DIR="${BASE_DIR}/php/sql"
CONF="${BASE_DIR}/php/config.php"

DB_HOST="${DB_HOST:-}"
DB_NAME="${DB_NAME:-}"
DB_USER="${DB_USER:-}"
DB_PASS="${DB_PASS:-}"

# php/config.php에서 기본값 파싱
pick() {
  local key="$1"
  if [[ -f "$CONF" ]]; then
    php -r "include '${CONF}'; echo defined('${key}') ? constant('${key}') : '';" 2>/dev/null || true
  fi
}
[[ -z "${DB_HOST}" ]] && DB_HOST="$(pick DB_HOST || true)"
[[ -z "${DB_NAME}" ]] && DB_NAME="$(pick DB_NAME || true)"
[[ -z "${DB_USER}" ]] && DB_USER="$(pick DB_USER || true)"
[[ -z "${DB_PASS}" ]] && DB_PASS="$(pick DB_PASS || true)"

if [[ -z "${DB_HOST}" || -z "${DB_NAME}" || -z "${DB_USER}" ]]; then
  echo "[ERR] DB_HOST/DB_NAME/DB_USER 비어있음. ENV 또는 php/config.php 확인." >&2
  exit 1
fi

CLIENT=""
if command -v mariadb >/dev/null 2>&1; then
  CLIENT="mariadb"
elif command -v mysql >/dev/null 2>&1; then
  CLIENT="mysql"
else
  echo "[ERR] mysql/mariadb 클라이언트 없음." >&2
  exit 1
fi

if [[ ! -d "${SQL_DIR}" ]]; then
  echo "[ERR] 스키마 디렉토리 없음: ${SQL_DIR}" >&2
  exit 1
fi

shopt -s nullglob
files=("${SQL_DIR}"/*.sql)
if [[ ${#files[@]} -eq 0 ]]; then
  echo "[WARN] 적용할 .sql 파일이 없습니다(${SQL_DIR})."
  exit 0
fi

echo "[INFO] applying schema to ${DB_USER}@${DB_HOST}/${DB_NAME} via ${CLIENT}"
for f in $(ls -1 "${SQL_DIR}"/*.sql | sort); do
  echo "  - ${f}"
  "${CLIENT}" -h "${DB_HOST}" -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < "${f}"
done

echo "[OK] schema applied."
echo "검증 예시:"
echo "  ${CLIENT} -h ${DB_HOST} -u${DB_USER} -p'***' ${DB_NAME} -e \"SHOW TABLES;\""
