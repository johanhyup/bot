import os, sys
from pathlib import Path
try:
    from dotenv import load_dotenv
except Exception:
    load_dotenv = None

# .env 로드
base = Path(__file__).resolve().parents[2]  # /var/www/bot/bot
for p in [base / "python" / ".env", base / ".env"]:
    if p.exists() and load_dotenv:
        load_dotenv(p, override=True)

# php/config.php 보강
cfg = base / "php" / "config.php"
if cfg.exists():
    import re
    txt = cfg.read_text(encoding="utf-8", errors="ignore")
    def pick(name):
        m = re.search(r"define\(\s*['\"]" + re.escape(name) + r"['\"]\s*,\s*['\"](.+?)['\"]\s*\)\s*;", txt)
        return m.group(1) if m else None
    os.environ.setdefault("MYSQL_HOST", pick("DB_HOST") or "127.0.0.1")
    os.environ.setdefault("MYSQL_DB",   pick("DB_NAME") or "botdb")
    os.environ.setdefault("MYSQL_USER", pick("DB_USER") or "botuser")
    os.environ.setdefault("MYSQL_PASS", pick("DB_PASS") or "")

host = os.getenv("MYSQL_HOST")
db   = os.getenv("MYSQL_DB")
user = os.getenv("MYSQL_USER")
pwd  = os.getenv("MYSQL_PASS")

print(f"[env] host={host} db={db} user={user} has_pass={bool(pwd)}")

import pymysql
from pymysql.cursors import DictCursor
try:
    conn = pymysql.connect(host=host, user=user, password=pwd, database=db, cursorclass=DictCursor)
    with conn.cursor() as cur:
        cur.execute("SELECT 1 AS ok")
        print("[ok]", cur.fetchone())
    conn.close()
    sys.exit(0)
except Exception as e:
    print("[err]", e)
    sys.exit(1)
