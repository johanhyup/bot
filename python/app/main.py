import os
import sqlite3
from datetime import datetime, date
from pathlib import Path
from typing import Dict, Any, List

from fastapi import FastAPI, APIRouter
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import ccxt

try:
    # 선택: .env 지원
    from dotenv import load_dotenv
    load_dotenv()
except Exception:
    pass

BASE_DIR = Path(__file__).resolve().parents[2]  # /Users/joko/bot
DB_PATH = BASE_DIR / "php" / "database.db"

UPBIT_ACCESS_KEY = os.getenv("UPBIT_ACCESS_KEY", "")
UPBIT_SECRET_KEY = os.getenv("UPBIT_SECRET_KEY", "")
BINANCE_API_KEY = os.getenv("BINANCE_API_KEY", "")
BINANCE_API_SECRET = os.getenv("BINANCE_API_SECRET", "")

app = FastAPI(title="Bot API", version="1.0.0")

# CORS: 프런트가 동일 호스트에서 reverse proxy되면 origins 제한 가능
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # 운영에선 도메인으로 제한
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

def get_db():
    conn = sqlite3.connect(DB_PATH)
    conn.row_factory = sqlite3.Row
    return conn

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
    if usdt_krw and usdt_krw > 0:
        krw_to_usdt = 1.0 / usdt_krw
    else:
        btc_usdt = price_safe(up, ["BTC/USDT"])
        btc_krw = price_safe(up, ["BTC/KRW"])
        if btc_usdt and btc_krw and btc_krw > 0:
            krw_to_usdt = btc_usdt / btc_krw

    total = 0.0
    # USDT 자체
    total += b["USDT"]
    # KRW 환산
    if b["KRW"] > 0:
        if not krw_to_usdt:
            raise RuntimeError("Upbit KRW→USDT 환산 실패")
        total += b["KRW"] * krw_to_usdt
    # XRP
    if b["XRP"] > 0:
        px = price_safe(up, ["XRP/USDT"]) or (price_safe(up, ["XRP/KRW"]) * krw_to_usdt if krw_to_usdt else None) or \
             (price_safe(up, ["XRP/BTC"]) * price_safe(up, ["BTC/USDT"]))
        if not px:
            raise RuntimeError("Upbit XRP 가격 조회 실패")
        total += b["XRP"] * px
    # BIT
    if b["BIT"] > 0:
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
    if amounts["XRP"] > 0:
        if not xrp_px:
            raise RuntimeError("Binance XRPUSDT 가격 조회 실패")
        total += amounts["XRP"] * xrp_px
    if amounts["BIT"] > 0:
        if not bit_px:
            raise RuntimeError("Binance BITUSDT 가격 조회 실패")
        total += amounts["BIT"] * bit_px
    return float(total)

api = APIRouter(prefix="/api")

@api.get("/health")
def health():
    return {"ok": True, "time": datetime.utcnow().isoformat()}

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

    try:
        upbit_val = upbit_total_usdt_valuation()
    except Exception as e:
        errors.append(f"Upbit 평가액 실패: {e}")

    try:
        binance_val = binance_total_usdt_valuation()
    except Exception as e:
        errors.append(f"Binance 평가액 실패: {e}")

    # DB에서 누적 수익/금일 거래(전체 합산; 필요 시 user_id 파라미터로 제한 가능)
    cum = 0.0
    trades: List[Dict[str, Any]] = []
    try:
        conn = get_db()
        cur = conn.cursor()
        cur.execute("SELECT COALESCE(SUM(profit),0) FROM trades")
        cum = float(cur.fetchone()[0] or 0)
        today_start = date.today().strftime("%Y-%m-%d") + " 00:00:00"
        cur.execute("SELECT time, type, amount, profit FROM trades WHERE time >= ? ORDER BY time DESC", (today_start,))
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

# 루트 확인용
@app.get("/")
def root():
    return {"ok": True, "msg": "Bot FastAPI running", "docs": "/docs", "health": "/api/health"}

# 라우터 등록
app.include_router(api)

# 개발 실행: uvicorn python.app.main:app --reload --host 0.0.0.0 --port 8000
