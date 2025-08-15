#!/usr/bin/env bash
set -euo pipefail

BASE="$(cd "$(dirname "$0")/.." && pwd)"

is_tracked() {
  git -C "$BASE" ls-files --error-unmatch "$1" >/dev/null 2>&1
}

del() {
  local rel="$1"
  local path="$BASE/$rel"
  if [[ ! -e "$path" ]]; then
    echo "skip (not found): $rel"
    return
  fi
  if git -C "$BASE" rev-parse --is-inside-work-tree >/dev/null 2>&1 && is_tracked "$rel"; then
    echo "git rm: $rel"
    git -C "$BASE" rm -f "$rel" >/dev/null
  else
    echo "rm: $rel"
    rm -f "$path"
  fi
}

# 1) 중복 HTML(php 버전으로 대체됨)
del "index.html"
del "dashboard.html"
del "admin.html"

# 2) PHP 진단/교환소(파이썬 FastAPI+ccxt로 대체)
del "php/info.php"
del "php/exchange.php"

# 3) PHP 구 API(FastAPI로 대체). 관리자 API는 옵션으로 삭제
# 기본: 삭제, KEEP_ADMIN=1 이면 보존
if [[ "${KEEP_ADMIN:-0}" != "1" ]]; then
  del "php/api/users.php"
  del "php/api/add_user.php"
  del "php/api/get_user.php"
  del "php/api/update_user.php"
  del "php/api/delete_user.php"
fi
del "php/api/dashboard.php"
del "php/api/debug_binance.php"

# 4) 선택: PHP CLI(이제 파이썬으로 대체 가능). KEEP_PHP_CLI=1이면 보존
if [[ "${KEEP_PHP_CLI:-0}" != "1" ]]; then
  del "php/cli/upbit_balances.php" || true
fi

# 빈 디렉터리 정리
for d in "php/api" "php/cli"; do
  dir="$BASE/$d"
  if [[ -d "$dir" ]]; then
    rmdir "$dir" 2>/dev/null || true
  fi
done

echo "cleanup done."
