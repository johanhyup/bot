import asyncio
import json
import time
from typing import Dict, List, Tuple, Optional

import websockets

try:
    import ccxt.pro as ccxtpro  # 통합된 ccxt에서 ws 지원 시 존재
except Exception:
    ccxtpro = None

UPBIT_WS = "wss://api.upbit.com/websocket/v1"
BINANCE_WS = "wss://stream.binance.com:9443/stream"

class PriceStore:
    def __init__(self) -> None:
        # key: market(symbol), value: {"price": float, "ts": int(ms), "source": str}
        self._data: Dict[str, Dict] = {}
        self._lock = asyncio.Lock()

    async def update(self, market: str, price: float, source: str, ts_ms: Optional[int] = None):
        if ts_ms is None:
            ts_ms = int(time.time() * 1000)
        async with self._lock:
            self._data[market] = {"price": float(price), "ts": int(ts_ms), "source": source}

    async def bulk_update(self, items: List[Tuple[str, float, str, Optional[int]]]):
        async with self._lock:
            ts_now = int(time.time() * 1000)
            for market, price, source, ts_ms in items:
                self._data[market] = {"price": float(price), "ts": int(ts_ms or ts_now), "source": source}

    async def snapshot(self) -> Dict[str, Dict]:
        async with self._lock:
            return dict(self._data)

async def upbit_stream(store: PriceStore, codes: List[str]):
    # codes 예: ["KRW-USDT","USDT-XRP","USDT-BIT"]
    while True:
        try:
            async with websockets.connect(UPBIT_WS, ping_interval=15, ping_timeout=20, max_size=2**20) as ws:
                sub = [
                    {"ticket": "bot"},
                    {"type": "ticker", "codes": codes, "isOnlyRealtime": True},
                ]
                await ws.send(json.dumps(sub).encode("utf-8"))
                while True:
                    msg = await ws.recv()
                    if isinstance(msg, (bytes, bytearray)):
                        msg = msg.decode("utf-8", errors="ignore")
                    data = json.loads(msg)
                    market = data.get("code") or data.get("market")
                    price = data.get("trade_price")
                    ts = data.get("timestamp")  # ns 단위일 수 있음
                    if ts and ts > 10**12:
                        ts = ts // 10**6  # ns→ms 추정 변환
                    if market and price is not None:
                        await store.update(market, float(price), "upbit", ts)
        except Exception:
            await asyncio.sleep(3)  # 백오프 후 재연결

async def binance_stream(store: PriceStore, symbols: List[str]):
    # symbols 예: ["xrpusdt","bitusdt"]
    streams = "/".join([f"{s.lower()}@ticker" for s in symbols])
    url = f"{BINANCE_WS}?streams={streams}"
    while True:
        try:
            async with websockets.connect(url, ping_interval=15, ping_timeout=20, max_size=2**20) as ws:
                while True:
                    msg = await ws.recv()
                    data = json.loads(msg)
                    payload = data.get("data") or {}
                    sym = (payload.get("s") or "").upper()
                    last = payload.get("c")
                    ts = payload.get("E")
                    if sym and last is not None:
                        await store.update(sym, float(last), "binance", ts)
        except Exception:
            await asyncio.sleep(3)  # 백오프 후 재연결

async def upbit_ccxt_stream(store: PriceStore, symbols: List[str]):
    if not ccxtpro:
        return await upbit_stream(store, [s.replace('/', '-').upper() for s in symbols])
    ex = ccxtpro.upbit()
    await ex.load_markets()
    try:
        while True:
            for sym in symbols:
                try:
                    t = await ex.watch_ticker(sym)
                    last = t.get("last")
                    ts = t.get("timestamp") or int(time.time() * 1000)
                    if last:
                        # 키를 Upbit REST 코드 포맷과 유사하게 변환
                        key = sym.replace('/', '-').upper()
                        await store.update(key, float(last), "upbit-ccxt", int(ts))
                except Exception:
                    await asyncio.sleep(0)
            await asyncio.sleep(0)
    finally:
        try:
            await ex.close()
        except Exception:
            pass

async def binance_ccxt_stream(store: PriceStore, symbols: List[str]):
    if not ccxtpro:
        return await binance_stream(store, [s.replace('/', '').lower() for s in symbols])
    ex = ccxtpro.binance({"options": {"adjustForTimeDifference": True}})
    await ex.load_markets()
    try:
        while True:
            for sym in symbols:
                try:
                    t = await ex.watch_ticker(sym)
                    last = t.get("last")
                    ts = t.get("timestamp") or int(time.time() * 1000)
                    if last:
                        # 바이낸스는 원래 심볼 그대로 저장
                        await store.update(sym.replace('/', '').upper(), float(last), "binance-ccxt", int(ts))
                except Exception:
                    await asyncio.sleep(0)
            await asyncio.sleep(0)
    finally:
        try:
            await ex.close()
        except Exception:
            pass

def start_stream_tasks(loop: asyncio.AbstractEventLoop, store: PriceStore):
    # ccxt ws가 가능하면 ccxt 경로, 아니면 네이티브 경로 사용
    upbit_pairs = ["USDT/KRW", "XRP/USDT", "BIT/USDT"]
    binance_pairs = ["XRP/USDT", "BIT/USDT"]

    if ccxtpro:
        tasks = [
            loop.create_task(upbit_ccxt_stream(store, upbit_pairs)),
            loop.create_task(binance_ccxt_stream(store, binance_pairs)),
        ]
    else:
        upbit_codes = ["KRW-USDT", "USDT-XRP", "USDT-BIT"]
        binance_syms = ["xrpusdt", "bitusdt"]
        tasks = [
            loop.create_task(upbit_stream(store, upbit_codes)),
            loop.create_task(binance_stream(store, binance_syms)),
        ]
    return tasks

async def stop_tasks(tasks: List[asyncio.Task]):
    for t in tasks:
        t.cancel()
    for t in tasks:
        try:
            await t
        except Exception:
            pass
