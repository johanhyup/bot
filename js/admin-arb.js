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
    const s = await fetchJSON('/api/arb/status');
    document.getElementById('arbStatus').textContent = s.running ? '실행 중' : '중지';
    document.getElementById('arbStatus').className = `badge ${s.running ? 'bg-success' : 'bg-secondary'}`;
    // 설정 반영
    if (s.config) {
      document.getElementById('symbols').value = (s.config.symbols || []).join(', ');
      document.getElementById('minSpreadBp').value = s.config.minSpreadBp ?? 30;
      document.getElementById('feeUpbit').value = s.config.takerFeeBpUpbit ?? 8;
      document.getElementById('feeBinance').value = s.config.takerFeeBpBinance ?? 10;
      document.getElementById('intervalSec').value = s.config.intervalSec ?? 15;
    }
  } catch (e) {
    console.error(e);
  }
}

async function saveConfig() {
  const body = {
    symbols: document.getElementById('symbols').value.split(',').map(s => s.trim()).filter(Boolean),
    minSpreadBp: parseFloat(document.getElementById('minSpreadBp').value || '30'),
    takerFeeBpUpbit: parseFloat(document.getElementById('feeUpbit').value || '8'),
    takerFeeBpBinance: parseFloat(document.getElementById('feeBinance').value || '10'),
    intervalSec: parseInt(document.getElementById('intervalSec').value || '15', 10),
  };
  await fetchJSON('/api/arb/config', { method: 'POST', body: JSON.stringify(body) });
  await loadStatus();
}

async function startEngine() {
  await fetchJSON('/api/arb/start', { method: 'POST', body: '{}' });
  await loadStatus();
}
async function stopEngine() {
  await fetchJSON('/api/arb/stop', { method: 'POST', body: '{}' });
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
