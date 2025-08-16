const H = {
  get headers() {
    const h = { 'Content-Type': 'application/json' };
    if (window.ADMIN_TOKEN) h['X-Admin-Token'] = window.ADMIN_TOKEN;
    return h;
  }
};

async function fetchJSON(url, opt = {}) {
  const res = await fetch(url, { ...opt, headers: { ...(opt.headers || {}), ...H.headers } });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

async function loadStatus() {
  try {
    const s = await fetchJSON('/api/tri/status');
    document.getElementById('arbStatus').textContent = s.running ? '실행 중' : '중지';
    document.getElementById('arbStatus').className = `badge ${s.running ? 'bg-success' : 'bg-secondary'}`;
    if (s.config) {
      document.getElementById('symbols').value = (s.config.routes || []).join(', ');
      document.getElementById('minSpreadBp').value = s.config.min_edge_bp ?? 10;
      document.getElementById('feeBinance').value = s.config.maker_fee_bp ?? 7.5;
      document.getElementById('intervalSec').value = Math.max(1, Math.round((s.config.interval_ms ?? 100) / 1000));
    }
  } catch (e) {
    console.error(e);
  }
}

async function saveConfig() {
  const routes = document.getElementById('symbols').value.split(',').map(s => s.trim()).filter(Boolean);
  const body = {
    routes,
    min_edge_bp: parseFloat(document.getElementById('minSpreadBp').value || '10'),
    maker_fee_bp: parseFloat(document.getElementById('feeBinance').value || '7.5'),
    interval_ms: Math.max(50, parseInt(document.getElementById('intervalSec').value || '1', 10) * 1000),
    // 기본값 유지: capital_usdt/max_alloc_frac/depth_levels/use_bnb_discount/simulate
  };
  await fetchJSON('/api/tri/config', { method: 'POST', body: JSON.stringify(body) });
  await loadStatus();
}

async function startEngine() {
  await fetchJSON('/api/tri/start', { method: 'POST', body: '{}' });
  await loadStatus();
}

async function stopEngine() {
  await fetchJSON('/api/tri/stop', { method: 'POST', body: '{}' });
  await loadStatus();
}

async function loadSignals() {
  try {
    const rows = await fetchJSON('/api/arb/signals');
    const tbody = document.getElementById('signalsBody');
    tbody.innerHTML = '';
    rows.forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${r.time}</td><td>${r.symbol}</td><td>${r.side}</td><td>${Number(r.spread_bp).toFixed(2)}</td>`;
      tbody.appendChild(tr);
    });
  } catch (e) {
    console.error(e);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  document.getElementById('btnSave').addEventListener('click', saveConfig);
  document.getElementById('btnStart').addEventListener('click', startEngine);
  document.getElementById('btnStop').addEventListener('click', stopEngine);
  document.getElementById('btnRefresh').addEventListener('click', loadSignals);
  loadStatus();
  loadSignals();
  setInterval(loadStatus, 10000);
});
