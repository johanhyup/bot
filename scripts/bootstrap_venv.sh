#!/usr/bin/env bash
set -euo pipefail

ROOT="/var/www/bot/bot"
VENV="/var/www/bot/.venv-bot"
REQ="${ROOT}/python/requirements.txt"
REQ_HASH_FILE="${VENV}/.req.sha256"

hash_file() {
  if command -v sha256sum >/dev/null 2>&1; then
    sha256sum "$1" | awk '{print $1}'
  else
    shasum -a 256 "$1" | awk '{print $1}'
  fi
}

ensure_venv() {
  if [[ ! -x "${VENV}/bin/python" ]]; then
    echo "[venv] create ${VENV}"
    python3 -m venv "${VENV}"
    # shellcheck disable=SC1090
    source "${VENV}/bin/activate"
    pip install -U pip setuptools wheel
    pip install -r "${REQ}"
    hash_file "${REQ}" > "${REQ_HASH_FILE}"
    echo "[venv] created"
  else
    # shellcheck disable=SC1090
    source "${VENV}/bin/activate"
    local new_hash old_hash
    new_hash="$(hash_file "${REQ}")"
    old_hash="$(cat "${REQ_HASH_FILE}" 2>/dev/null || echo '')"
    if [[ "${new_hash}" != "${old_hash}" ]]; then
      echo "[venv] requirements changed → installing"
      pip install -r "${REQ}"
      echo "${new_hash}" > "${REQ_HASH_FILE}"
      echo "[venv] updated"
    else
      echo "[venv] up-to-date"
    fi
  fi
}

ensure_venv
