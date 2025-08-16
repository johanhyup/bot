import json, os, time, threading, math, queue, signal
from dataclasses import dataclass, asdict
from typing import Dict, List, Optional, Tuple
from pathlib import Path
from datetime import datetime, timedelta

import ccxt
from websocket import WebSocketApp

LOG_PATH = Path(__file__).resolve().parent / "arbitrage.log"

def log(msg: str):
    line = f"{datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S')} | {msg}"
    print(line)
    try:
        with open(LOG_PATH, "a", encoding="utf-8") as f:
            f.write(line + "\n")
    except Exception:
        pass

@dataclass
class TriConfig:
    routes: List[str]
    capital_usdt: float = 1000.0
    max_alloc_frac: float = 0.2
    min_edge_bp: float = 10.0
    maker_fee_bp: float = 7.5
    use_bnb_discount: bool = True
    depth_levels: int = 5
    interval_ms: int = 100
    volatility_stop_pct: float = 5.0
    simulate: bool = True
    ws_endpoint: str = "wss://stream.binance.com:9443/stream"

    @staticmethod
    def load(path: Path) -> "TriConfig":
        data = json.loads(path.read_text())
        return TriConfig(**data)

class BinanceBook:
    # bookTicker 최신 호가 저장
    def __init__(self):
        self.lock = threading.Lock()
        self.bid: Dict[str, Tuple[float, float]] = {}  # symbol -> (price, qty)
        self.ask: Dict[str, Tuple[float, float]] = {}

    def update(self, sym: str, bid_px: float, bid_qty: float, ask_px: float, ask_qty: float):
        with self.lock:
            self.bid[sym] = (bid_px, bid_qty)
            self.ask[sym] = (ask_px, ask_qty)

    def best(self, sym: str) -> Optional[Tuple[float, float, float, float]]:
        with self.lock:
            if sym not in self.bid or sym not in self.ask:
                return None
            b = self.bid[sym]
            a = self.ask[sym]
            return (b[0], b[1], a[0], a[1])

class WSClient:
    # Binance combined streams: <symbol>@bookTicker
    def __init__(self, symbols: List[str], endpoint: str, book: BinanceBook):
        streams = "/".join([f"{s.lower()}@bookTicker" for s in symbols])
        self.url = f"{endpoint}?streams={streams}"
        self.book = book
        self.ws: Optional[WebSocketApp] = None
        self.thread: Optional[threading.Thread] = None
        self._stop = threading.Event()

    def _on_msg(self, _, msg: str):
        try:
            data = json.loads(msg)
            x = data.get("data") or {}
            s = x.get("s")
            if not s:
                return
            bid_px = float(x.get("b", 0) or 0)
            bid_qty = float(x.get("B", 0) or 0)
            ask_px = float(x.get("a", 0) or 0)
            ask_qty = float(x.get("A", 0) or 0)
            if bid_px > 0 and ask_px > 0:
                self.book.update(s, bid_px, bid_qty, ask_px, ask_qty)
        except Exception:
            pass

    def _on_err(self, _, err):
        log(f"[ws] error: {err}")

    def _on_close(self, *_):
        log("[ws] closed")

    def _run(self):
        while not self._stop.is_set():
            try:
                self.ws = WebSocketApp(self.url, on_message=self._on_msg, on_error=self._on_err, on_close=self._on_close)
                self.ws.run_forever(ping_interval=20, ping_timeout=10, reconnect=5)
            except Exception as e:
                log(f"[ws] run error: {e}")
            time.sleep(1)

    def start(self):
        self.thread = threading.Thread(target=self._run, daemon=True)
        self.thread.start()
        log(f"[ws] connecting: {self.url}")

    def stop(self):
        self._stop.set()
        try:
            if self.ws:
                self.ws.close()
        except Exception:
            pass
        if self.thread:
            self.thread.join(timeout=3)

class TriArbEngine:
    def __init__(self, cfg: TriConfig, api_key: str = "", api_secret: str = ""):
        self.cfg = cfg
        self.book = BinanceBook()
        self.ws: Optional[WSClient] = None
        self.running = False
        self.thread: Optional[threading.Thread] = None
        self._stop = threading.Event()
        self.ccxt = ccxt.binance({
            "apiKey": api_key,
            "secret": api_secret,
            "enableRateLimit": True,
            "options": {"adjustForTimeDifference": True},
            "timeout": 10000,
        })
        self.routes = self._build_routes_symbols(cfg.routes)
        self._symbols = sorted({sym for r in self.routes for sym in r})

    @staticmethod
    def _build_routes_symbols(routes: List[str]) -> List[Tuple[str, str, str]]:
        # 반환: [(SYMa, SYMb, SYMc), ...] 각각이 (USDT leg, cross leg, USDT leg)
        out = []
        mapping = {
            "BTC-ETH-USDT": ("BTCUSDT", "ETHBTC", "ETHUSDT"),
            "BNB-BTC-USDT": ("BNBUSDT", "BNBBTC", "BTCUSDT"),  # cross는 BNBBTC (BNB base, BTC quote)
            "XRP-BTC-USDT": ("XRPUSDT", "XRPBTC", "BTCUSDT")
        }
        for r in routes:
            if r in mapping:
                out.append(mapping[r])
        return out

    def _fee_rate(self) -> float:
        if self.cfg.use_bnb_discount:
            return 0.0005625  # 0.05625%
        return (self.cfg.maker_fee_bp or 7.5) / 10000.0

    def start(self):
        if self.running:
            return
        self.running = True
        self._stop.clear()
        # WS
        self.ws = WSClient(self._symbols, self.cfg.ws_endpoint, self.book)
        self.ws.start()
        # Loop
        self.thread = threading.Thread(target=self._loop, daemon=True)
        self.thread.start()
        log(f"[tri] started with cfg={asdict(self.cfg)} symbols={self._symbols}")

    def stop(self):
        self.running = False
        self._stop.set()
        if self.ws:
            self.ws.stop()
        if self.thread:
            self.thread.join(timeout=3)
        log("[tri] stopped")

    def _enough_liquidity(self, need_qty: float, best_qty: float) -> bool:
        # 간단: best bid/ask 수량으로 체크(실제는 depth@5 권장)
        return best_qty >= need_qty * 1.05

    def _try_route(self, usdt_leg1: str, cross_leg: str, usdt_leg2: str) -> Optional[Dict]:
        # 방향 1: USDT -> BASE(usdt_leg1) 매수(ask), BASE -> ALT(cross bid/ask 조합), ALT -> USDT 매도(bid)
        b1 = self.book.best(usdt_leg1)
        b2 = self.book.best(cross_leg)
        b3 = self.book.best(usdt_leg2)
        if not b1 or not b2 or not b3:
            return None

        fee = self._fee_rate()
        cap = self.cfg.capital_usdt
        alloc = min(self.cfg.max_alloc_frac, 1.0) * cap

        # 심볼 파싱
        # usdt_leg1 like BTCUSDT => BASE1=BTC, QUOTE1=USDT
        base1 = usdt_leg1[:-4]
        base2 = usdt_leg2[:-4]
        # cross_leg like ETHBTC (ETH base, BTC quote)
        base_cross = cross_leg[:-3]
        quote_cross = cross_leg[-3:]

        # 라우트 A: USDT -> base1(BTC) -> base_cross(ETH) -> USDT
        # step1: buy base1 with USDT at ask
        btc_ask, btc_ask_qty = b1[2], b1[3]
        step1_qty_base1 = alloc / btc_ask  # BTC 수량
        if not self._enough_liquidity(step1_qty_base1, btc_ask_qty):
            step1_qty_base1 = min(step1_qty_base1, btc_ask_qty)

        # step2: convert base1(BTC) -> base_cross(ETH) via cross_leg
        # If cross is ETHBTC (ETH priced in BTC), to buy ETH with BTC use ask(ETHBTC)
        cross_bid, cross_bid_qty, cross_ask, cross_ask_qty = b2
        if quote_cross == base1 and base_cross != base1:
            # buy base_cross with base1 using ask
            eth_qty = step1_qty_base1 / cross_ask
            if not self._enough_liquidity(eth_qty, cross_ask_qty):
                eth_qty = min(eth_qty, cross_ask_qty)
        else:
            # fallback unsupported routing
            return None

        # step3: sell base_cross(ETH) to USDT at bid ETHUSDT
        if base2 != base_cross:
            # usdt_leg2 must be ETHUSDT in this route
            pass
        ethusdt_bid, ethusdt_bid_qty = b3[0], b3[1]
        if not self._enough_liquidity(eth_qty, ethusdt_bid_qty):
            eth_qty = min(eth_qty, ethusdt_bid_qty)

        usdt_back = eth_qty * ethusdt_bid

        # 수수료 3회 반영(대략)
        usdt_back_net = usdt_back * (1 - fee) * (1 - fee) * (1 - fee)
        edge = (usdt_back_net - alloc) / max(1e-12, alloc) * 100  # %
        return {
            "route": f"{base1}-{base_cross}-USDT",
            "alloc": alloc,
            "usdt_back": usdt_back_net,
            "edge_pct": edge,
            "steps": [
                {"sym": usdt_leg1, "side": "buy", "px": btc_ask, "qty": step1_qty_base1},
                {"sym": cross_leg, "side": "buy_base", "px": cross_ask, "qty": eth_qty},  # buy ETH with BTC
                {"sym": usdt_leg2, "side": "sell", "px": ethusdt_bid, "qty": eth_qty},
            ]
        }

    def _place_orders(self, plan: Dict):
        if self.cfg.simulate:
            log(f"[sim] {plan['route']} edge={plan['edge_pct']:.3f}% alloc={plan['alloc']:.4f} -> {plan['usdt_back']:.4f}")
            return

        try:
            # postOnly maker 주문 시도
            for step in plan["steps"]:
                sym = step["sym"]
                side = step["side"]
                qty = float(step["qty"])
                px = float(step["px"])
                ord_side = "buy" if side in ("buy", "buy_base") else "sell"
                self.ccxt.create_order(
                    symbol=self._ccxt_symbol(sym),
                    type="limit",
                    side=ord_side,
                    amount=qty,
                    price=px,
                    params={"postOnly": True}
                )
            log(f"[live] submitted 3 maker orders for {plan['route']} edge={plan['edge_pct']:.3f}%")
        except Exception as e:
            log(f"[live] order error: {e}")

    @staticmethod
    def _ccxt_symbol(binance_sym: str) -> str:
        # BTCUSDT -> BTC/USDT
        if binance_sym.endswith("USDT"):
            return f"{binance_sym[:-4]}/USDT"
        return f"{binance_sym[:-3]}/{binance_sym[-3:]}"

    def _loop(self):
        min_bp = self.cfg.min_edge_bp
        while not self._stop.is_set():
            try:
                for (leg1, cross, leg2) in self.routes:
                    plan = self._try_route(leg1, cross, leg2)
                    if not plan:
                        continue
                    if (plan["edge_pct"] * 100) >= (min_bp * 100):  # bp 비교를 퍼센트 동등 비교로 환산
                        log(f"[opp] {plan['route']} edge={plan['edge_pct']:.3f}%")
                        self._place_orders(plan)
            except Exception as e:
                log(f"[tri] loop err: {e}")
            time.sleep(max(0.02, (self.cfg.interval_ms or 100) / 1000.0))

# 간단 백테스트(OHLCV 기반 근사)
def backtest(cfg_path: Path, months: int = 1):
    cfg = TriConfig.load(cfg_path)
    ex = ccxt.binance({"enableRateLimit": True})
    routes = TriArbEngine(cfg)._build_routes_symbols(cfg.routes)
    since = int((datetime.utcnow() - timedelta(days=30 * months)).timestamp() * 1000)
    total_trades = 0
    total_profit = 0.0
    for (a, b, c) in routes:
        # mid price 근사로 간단 평가
        def ohlcv_mid(sym):
            rows = ex.fetch_ohlcv(TriArbEngine._ccxt_symbol(sym), timeframe="1m", since=since, limit=1000)
            return [(t, (o + h + l + c) / 4.0) for t, o, h, l, c, _ in rows]

        A = ohlcv_mid(a)
        B = ohlcv_mid(b)
        C = ohlcv_mid(c)
        n = min(len(A), len(B), len(C))
        fee = 0.0005625 if cfg.use_bnb_discount else (cfg.maker_fee_bp / 10000.0)
        for i in range(n):
            a_px = A[i][1]; b_px = B[i][1]; c_px = C[i][1]
            if a_px <= 0 or b_px <= 0 or c_px <= 0:
                continue
            # 근사 루프 이익
            # USDT->BASE1 at a_px; BASE1->BASE2 via b_px; BASE2->USDT at c_px
            alloc = cfg.capital_usdt * cfg.max_alloc_frac
            base1 = alloc / a_px
            base2 = base1 / b_px
            usdt_back = base2 * c_px
            usdt_back *= (1 - fee) ** 3
            edge = (usdt_back - alloc) / alloc * 100
            if edge >= (cfg.min_edge_bp / 100.0):
                total_trades += 1
                total_profit += (usdt_back - alloc)
    roi = (total_profit / max(1e-9, cfg.capital_usdt)) * 100
    print(f"[backtest] months={months} trades={total_trades} profit={total_profit:.4f} USDT ROI={roi:.3f}%")

if __name__ == "__main__":
    import argparse
    p = argparse.ArgumentParser(description="Binance Triangular Arbitrage")
    p.add_argument("--config", default=str(Path(__file__).resolve().parent / "config.json"))
    p.add_argument("--backtest", action="store_true")
    p.add_argument("--months", type=int, default=1)
    args = p.parse_args()

    cfg = TriConfig.load(Path(args.config))
    if args.backtest:
        backtest(Path(args.config), months=args.months)
        raise SystemExit(0)

    api_key = os.getenv("BINANCE_API_KEY", "")
    api_secret = os.getenv("BINANCE_API_SECRET", "")
    eng = TriArbEngine(cfg, api_key, api_secret)

    def _sig(_1, _2):
        eng.stop(); os._exit(0)
    signal.signal(signal.SIGINT, _sig)
    signal.signal(signal.SIGTERM, _sig)

    eng.start()
    while True:
        time.sleep(1)
