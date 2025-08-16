import os
import sqlite3
from datetime import datetime, date
from pathlib import Path
from typing import Dict, Any, List, Optional, Tuple
from typing import Dict as _Dict

from fastapi import FastAPI, APIRouter, HTTPException, Request, Body
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import ccxt
import asyncio
# (변경) PyMySQL 임포트 가드
try:
    import pymysql
    from pymysql.cursors import DictCursor
    PYMYSQL_OK = True
except Exception:
    PYMYSQL_OK = False
    pymysql = None
    DictCursor = None
import concurrent.futures

try:
    from dotenv import load_dotenv
    from pathlib import Path as _P
    _ENV_PATH = (_P(__file__).resolve().parents[2] / "python" / ".env")
    load_dotenv(str(_ENV_PATH))
except Exception:
    pass

import json  # (추가) 사용 중인 json 임포트

BASE_DIR = Path(__file__).resolve().parents[2]  # /var/www/bot/bot
DB_PATH = BASE_DIR / "php" / "database.db"

UPBIT_ACCESS_KEY = os.getenv("UPBIT_ACCESS_KEY", "")
UPBIT_SECRET_KEY = os.getenv("UPBIT_SECRET_KEY", "")
BINANCE_API_KEY = os.getenv("BINANCE_API_KEY", "")
BINANCE_API_SECRET = os.getenv("BINANCE_API_SECRET", "")

CCXT_TIMEOUT_MS = int(os.getenv("CCXT_TIMEOUT_MS", "10000"))
DASHBOARD_TIMEOUT_MS = int(os.getenv("DASHBOARD_TIMEOUT_MS", "5000"))

from .stream import PriceStore, start_stream_tasks, stop_tasks

# (추가) 환경 로더: .env 다중 경로 + PHP config 파싱으로 보강
import re
def _reload_env():
    try:
        # python-dotenv 재로딩(override)
        for p in [
            BASE_DIR / "python" / ".env",
            BASE_DIR / ".env",
        ]:
            if p.exists():
                load_dotenv(p, override=True)
    except Exception:
        pass

    # php/config.php에서 DB 상수 보강
    cfg = BASE_DIR / "php" / "config.php"
    if cfg.exists():
        try:
            txt = cfg.read_text(encoding="utf-8", errors="ignore")
            def pick(name):
                m = re.search(r"define\(\s*['\"]" + re.escape(name) + r"['\"]\s*,\s*['\"](.+?)['\"]\s*\)\s*;", txt)
                return m.group(1) if m else None
            for env_name, php_const in [
                ("MYSQL_HOST", "DB_HOST"),
                ("MYSQL_DB",   "DB_NAME"),
                ("MYSQL_USER", "DB_USER"),
                ("MYSQL_PASS", "DB_PASS"),
            ]:
                if not os.getenv(env_name):
                    v = pick(php_const)
                    if v:
                        os.environ[env_name] = v
        except Exception:
            pass

# 모듈 로드 시 1회 보강
_reload_env()

# MySQL 접속 정보(.env에서 주입) → get_db 안에서 즉시 읽기
def get_db():
    if not PYMYSQL_OK:
        raise RuntimeError("PyMySQL not installed")
    host = os.getenv("MYSQL_HOST", "127.0.0.1")
    name = os.getenv("MYSQL_DB", "botdb")
    user = os.getenv("MYSQL_USER", "botuser")
    pw   = os.getenv("MYSQL_PASS", "")

    if not pw:
        # 환경 재시도(서비스 재시작 없이 갱신될 수 있으므로)
        _reload_env()
        pw = os.getenv("MYSQL_PASS", "")
        if not pw:
            print("[warn] MYSQL_PASS 비어있음. php/config.php나 .env 확인 필요.")

    return pymysql.connect(
        host=host,
        user=user,
        password=pw,
        database=name,
        charset="utf8mb4",
        autocommit=True,
        cursorclass=DictCursor,
    )

# (추가) 로깅용
import sys, os  # (추가) 진단

app = FastAPI(title="Bot API", version="1.0.0")

# (추가) 직접 핑/레디니스 별칭
@app.get("/ping")
def _ping():
    return {"ok": True}

@app.get("/api/ready2")
def _ready2():
    return {"ok": True, "time": datetime.utcnow().isoformat(), "via": "app-root"}

# (추가) 자기진단
@app.get("/whoami")
def whoami():
    return {
        "file": __file__,
        "cwd": os.getcwd(),
        "time": datetime.utcnow().isoformat(),
        "routes": len(app.routes),
    }

# CORS: 프런트가 동일 호스트에서 reverse proxy되면 origins 제한 가능
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # 운영에선 도메인으로 제한
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

price_store = PriceStore()
app.state.stream_tasks = []
app.state.tri_engine = None
# (추가) 다중 사용자 엔진 맵
app.state.tri_engines: _Dict[int, object] = {}

@app.on_event("startup")
async def _startup():
    print(f"[uvicorn] startup: app={__file__}", file=sys.stderr, flush=True)  # (추가) 로그
    print(f"[uvicorn] startup: before include routes={len(app.routes)}", file=sys.stderr, flush=True)
    loop = asyncio.get_running_loop()
    app.state.stream_tasks = start_stream_tasks(loop, price_store)
    # 라우트 나열(상위 10개만)
    try:
        paths = [getattr(r, "path", "") for r in app.routes][:10]
        print(f"[uvicorn] startup: after include routes={len(app.routes)} sample={paths}", file=sys.stderr, flush=True)
    except Exception:
        pass
    print("[uvicorn] startup: done", file=sys.stderr, flush=True)

@app.on_event("shutdown")
async def _shutdown():
    print("[uvicorn] shutdown: stopping tasks...", file=sys.stderr, flush=True)  # (추가) 로그
    await stop_tasks(app.state.stream_tasks)
    # (추가) 모든 tri 엔진 중지
    try:
        for uid, eng in list(app.state.tri_engines.items()):
            try:
                if eng and getattr(eng, "running", False):
                    eng.stop()
            except Exception:
                pass
    except Exception:
        pass
    print("[uvicorn] shutdown: done", file=sys.stderr, flush=True)  # (추가) 로그

def sum_balances_portfolio(bal: Dict[str, Any], symbols: List[str]) -> Dict[str, float]:
    out = {s: 0.0 for s in symbols}
    if not bal or "total" not in bal:
        return out
    total = bal["total"]
    for s in symbols:
        v = total.get(s, 0) or 0
        try:
            out[s] = float(v)
        except Exception:
            out[s] = 0.0
    return out

def ensure_markets(ex):
    if not getattr(ex, "markets", None):
        ex.load_markets()

def price_safe(ex, candidates: List[str]) -> float | None:
    # candidates: 우선순위 심볼 목록, 첫 성공 가격 반환
    ensure_markets(ex)
    for sym in candidates:
        try:
            if sym not in ex.markets:
                continue
            t = ex.fetch_ticker(sym)
            px = float(t["last"]) if t and t.get("last") is not None else None
            if px and px > 0:
                return px
        except Exception:
            continue
    return None

def upbit_total_usdt_valuation() -> float:
    up = ccxt.upbit({
        "apiKey": UPBIT_ACCESS_KEY,
        "secret": UPBIT_SECRET_KEY,
        "timeout": CCXT_TIMEOUT_MS,
    })
    ensure_markets(up)
    bal = up.fetch_balance()  # requires read-permission
    # 관심 자산
    want = ["KRW", "USDT", "XRP", "BIT"]
    b = sum_balances_portfolio(bal, want)

    # KRW->USDT: KRW-USDT 직상장(= USDT/KRW의 역수) 우선, 없으면 BTC 브릿지
    # ccxt 심볼 규칙: BASE/QUOTE
    usdt_krw = price_safe(up, ["USDT/KRW"])
    krw_to_usdt = None
    if (usdt_krw and usdt_krw > 0):
        krw_to_usdt = 1.0 / usdt_krw
    else:
        btc_usdt = price_safe(up, ["BTC/USDT"])
        btc_krw = price_safe(up, ["BTC/KRW"])
        if (btc_usdt and btc_krw and btc_krw > 0):
            krw_to_usdt = btc_usdt / btc_krw

    total = 0.0
    # USDT 자체
    total += b["USDT"]
    # KRW 환산
    if (b["KRW"] > 0):
        if not krw_to_usdt:
            raise RuntimeError("Upbit KRW→USDT 환산 실패")
        total += b["KRW"] * krw_to_usdt
    # XRP
    if (b["XRP"] > 0):
        px = price_safe(up, ["XRP/USDT"]) or (price_safe(up, ["XRP/KRW"]) * krw_to_usdt if krw_to_usdt else None) or \
             (price_safe(up, ["XRP/BTC"]) * price_safe(up, ["BTC/USDT"]))
        if not px:
            raise RuntimeError("Upbit XRP 가격 조회 실패")
        total += b["XRP"] * px
    # BIT
    if (b["BIT"] > 0):
        px = price_safe(up, ["BIT/USDT"]) or (price_safe(up, ["BIT/KRW"]) * krw_to_usdt if krw_to_usdt else None) or \
             (price_safe(up, ["BIT/BTC"]) * price_safe(up, ["BTC/USDT"]))
        if not px:
            raise RuntimeError("Upbit BIT 가격 조회 실패")
        total += b["BIT"] * px

    return float(total)

def binance_total_usdt_valuation() -> float:
    bz = ccxt.binance({
        "apiKey": BINANCE_API_KEY,
        "secret": BINANCE_API_SECRET,
        "options": {"adjustForTimeDifference": True},
        "timeout": CCXT_TIMEOUT_MS,
    })
    ensure_markets(bz)

    # 스팟
    spot_bal = {}
    try:
        spot_bal = bz.fetch_balance() or {}
    except Exception:
        spot_bal = {}

    # 펀딩(가능 시)
    funding_bal = {}
    try:
        # 일부 ccxt 버전은 funding 타입 미지원 → SAPI raw 호출로 대체
        # 우선 ccxt의 fetch_balance(params) 시도
        funding_bal = bz.fetch_balance(params={"type": "funding"}) or {}
    except Exception:
        # raw SAPI: /sapi/v1/asset/getFundingAsset
        try:
            res = bz.sapiPostAssetGetFundingAsset({})
            # res는 [{asset, free, locked, freeze, withdrawing, ...}]
            total = {}
            for row in res or []:
                sym = row.get("asset")
                if not sym:
                    continue
                free = float(row.get("free", 0) or 0)
                locked = float(row.get("locked", 0) or 0)
                freeze = float(row.get("freeze", 0) or 0)
                withdrawing = float(row.get("withdrawing", 0) or 0)
                total[sym] = total.get(sym, 0.0) + free + locked + freeze + withdrawing
            funding_bal = {"total": total}
        except Exception:
            funding_bal = {}

    def agg_total(symbol: str) -> float:
        s = 0.0
        try:
            s += float((spot_bal.get("total") or {}).get(symbol, 0) or 0)
        except Exception:
            pass
        try:
            s += float((funding_bal.get("total") or {}).get(symbol, 0) or 0)
        except Exception:
            pass
        return s

    amounts = {
        "USDT": agg_total("USDT"),
        "XRP": agg_total("XRP"),
        "BIT": agg_total("BIT"),
    }

    total = 0.0
    total += amounts["USDT"]
    # 가격
    xrp_px = price_safe(bz, ["XRP/USDT"])
    bit_px = price_safe(bz, ["BIT/USDT"])
    if (amounts["XRP"] > 0):
        if not xrp_px:
            raise RuntimeError("Binance XRPUSDT 가격 조회 실패")
        total += amounts["XRP"] * xrp_px
    if (amounts["BIT"] > 0):
        if not bit_px:
            raise RuntimeError("Binance BITUSDT 가격 조회 실패")
        total += amounts["BIT"] * bit_px
    return float(total)

api = APIRouter(prefix="/api")

# (추가) 레디니스 체크: 서버 기동 여부만 확인
@api.get("/ready")
def ready():
    return {"ok": True, "time": datetime.utcnow().isoformat()}

@api.get("/tickers")
async def tickers():
    return await price_store.snapshot()

@api.get("/health")
async def health():
    # DB 체크
    db_ok, db_err = True, None
    try:
        conn = get_db()
        with conn.cursor() as cur:
            cur.execute("SELECT 1")
            cur.fetchone()
        conn.close()
    except Exception as e:
        db_ok, db_err = False, str(e)

    # WS 최신성(최근 120초)
    fresh = 0
    try:
        snap = await price_store.snapshot()
        now_ms = int(datetime.utcnow().timestamp() * 1000)
        fresh = sum(1 for v in snap.values() if isinstance(v, dict) and (now_ms - int(v.get("ts", 0))) <= 120_000)
    except Exception:
        pass

    return {
        "ok": db_ok,
        "time": datetime.utcnow().isoformat(),
        "db": {"ok": db_ok, "error": db_err, "host": os.getenv("MYSQL_HOST"), "user": os.getenv("MYSQL_USER"), "has_pass": bool(os.getenv("MYSQL_PASS"))},
        "ws": {"fresh": fresh},
        "version": "1.0.0",
    }

class DashboardResponse(BaseModel):
    upbitBalance: float
    binanceBalance: float
    cumulativeProfit: float
    trades: List[Dict[str, Any]]
    errors: List[str]

@api.get("/dashboard", response_model=DashboardResponse)
def dashboard():
    errors: List[str] = []
    upbit_val = 0.0
    binance_val = 0.0

    # 외부 거래소 평가는 병렬 + 타임아웃 처리
    try:
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as ex:
            fu_up = ex.submit(upbit_total_usdt_valuation)
            fu_bz = ex.submit(binance_total_usdt_valuation)
            try:
                upbit_val = float(fu_up.result(timeout=DASHBOARD_TIMEOUT_MS / 1000))
            except Exception as e:
                errors.append(f"Upbit 평가액 실패/타임아웃: {e}")
            try:
                binance_val = float(fu_bz.result(timeout=DASHBOARD_TIMEOUT_MS / 1000))
            except Exception as e:
                errors.append(f"Binance 평가액 실패/타임아웃: {e}")
    except Exception as e:
        errors.append(f"평가액 처리 실패: {e}")

    # DB에서 누적 수익/금일 거래
    cum = 0.0
    trades: List[Dict[str, Any]] = []
    try:
        conn = get_db()
        cur = conn.cursor()
        cur.execute("SELECT COALESCE(SUM(profit),0) AS s FROM trades")
        row = cur.fetchone()
        cum = float((row.get("s") if isinstance(row, dict) else row[0]) or 0)

        today_start = date.today().strftime("%Y-%m-%d") + " 00:00:00"
        # PyMySQL는 %s 플레이스홀더 사용
        cur.execute("SELECT time, type, amount, profit FROM trades WHERE time >= %s ORDER BY time DESC", (today_start,))
        rows = cur.fetchall()
        trades = [{"time": r["time"], "type": r["type"], "amount": r["amount"], "profit": r["profit"]} for r in rows]
        conn.close()
    except Exception as e:
        errors.append(f"DB 조회 실패: {e}")

    return {
        "upbitBalance": round(upbit_val, 8),
        "binanceBalance": round(binance_val, 8),
        "cumulativeProfit": round(cum, 8),
        "trades": trades,
        "errors": errors,
    }

class AdminTestRequest(BaseModel):
    userId: int
    kind: str  # 'api' | 'ws'

class AdminTestResponse(BaseModel):
    ok: bool
    userId: int
    kind: str
    details: List[str]

@api.post("/admin/test", response_model=AdminTestResponse)
async def admin_test(req: AdminTestRequest):
    # 사용자 존재 확인
    try:
        conn = get_db()
        cur = conn.cursor()
        # PyMySQL는 %s 플레이스홀더 사용
        cur.execute("SELECT id, username FROM users WHERE id = %s", (req.userId,))
        row = cur.fetchone()
        conn.close()
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"DB 에러: {e}")
    if not row:
        raise HTTPException(status_code=404, detail="사용자를 찾을 수 없습니다.")

    details: List[str] = []
    kind = req.kind.lower().strip()
    ok = True

    if kind == "api":
        # 공개 API 빠른 체크
        try:
            up = ccxt.upbit()
            ensure_markets(up)
            t = up.fetch_ticker("BTC/USDT")
            if not t or t.get("last") is None:
                ok = False
                details.append("Upbit 공개 API 실패")
            else:
                details.append("Upbit 공개 API OK")
        except Exception as e:
            ok = False
            details.append(f"Upbit 공개 API 에러: {e}")

        try:
            bz = ccxt.binance({"options": {"adjustForTimeDifference": True}})
            ensure_markets(bz)
            t = bz.fetch_ticker("BTC/USDT")
            if not t or t.get("last") is None:
                ok = False
                details.append("Binance 공개 API 실패")
            else:
                details.append("Binance 공개 API OK")
        except Exception as e:
            ok = False
            details.append(f"Binance 공개 API 에러: {e}")

        # 프라이빗(키 존재 시만 시도)
        if UPBIT_ACCESS_KEY and UPBIT_SECRET_KEY:
            try:
                up_priv = ccxt.upbit({"apiKey": UPBIT_ACCESS_KEY, "secret": UPBIT_SECRET_KEY})
                up_priv.fetch_balance()
                details.append("Upbit 프라이빗 API OK")
            except Exception as e:
                ok = False
                details.append(f"Upbit 프라이빗 API 에러: {e}")
        if BINANCE_API_KEY and BINANCE_API_SECRET:
            try:
                bz_priv = ccxt.binance({"apiKey": BINANCE_API_KEY, "secret": BINANCE_API_SECRET, "options": {"adjustForTimeDifference": True}})
                bz_priv.fetch_balance()
                details.append("Binance 프라이빗 API OK")
            except Exception as e:
                ok = False
                details.append(f"Binance 프라이빗 API 에러: {e}")

    elif kind == "ws":
        # 스트림 캐시 최신성 체크(최근 120초 내 틱 존재)
        snap = await price_store.snapshot()
        now_ms = int(datetime.utcnow().timestamp() * 1000)
        fresh = [k for k, v in snap.items() if isinstance(v, dict) and (now_ms - int(v.get("ts", 0))) <= 120_000]
        if fresh:
            details.append(f"WS OK: 최근 업데이트 {len(fresh)}건")
        else:
            ok = False
            details.append("WS 데이터 없음(스트리머 미기동 또는 네트워크 문제)")
    else:
        raise HTTPException(status_code=400, detail="kind 는 'api' 또는 'ws' 이어야 합니다.")

    return {"ok": ok, "userId": int(row["id"]), "kind": kind, "details": details}

# 루트 확인용
@app.get("/")
def root():
    return {"ok": True, "msg": "Bot FastAPI running", "docs": "/docs", "health": "/api/health"}

# ...existing code...

@api.post("/tri/stop")
@api.post("/tri/stop/")
def tri_stop(req: Request, body: Dict[str, Any] = Body(default={})):
    _require_admin_token(req)
    if not TRI_OK:
        raise HTTPException(500, "engine not available")
    body = body or {}
    user_id = int(body.get("user_id") or body.get("target_user_id") or 0)
    if not user_id:
        raise HTTPException(400, "user_id required")
    eng = app.state.tri_engines.get(user_id)
    if eng and getattr(eng, "running", False):
        try:
            eng.stop()
        except Exception:
            pass
    return {"ok": True, "running": False, "user_id": user_id}

# (추가) 라우트 덤프
@api.get("/routes")
def api_routes():
    out = []
    for r in app.routes:
        try:
            out.append({
                "path": getattr(r, "path", None),
                "name": getattr(r, "name", None),
                "methods": sorted(list(getattr(r, "methods", []) or [])),
            })
        except Exception:
            continue
    return out

# 라우터 등록(모든 @api.* 선언 이후에 호출)
app.include_router(api)
