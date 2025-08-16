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

// (추가) ADMIN_TOKEN 확보
async function ensureAdminToken() {
  if (window.ADMIN_TOKEN) return;
  try {
    const r = await fetch('/php/api/admin_token.php', { credentials: 'same-origin' });
    if (r.ok) {
      const j = await r.json();
      if (j && j.token) window.ADMIN_TOKEN = j.token;
    }
  } catch (e) {
    console.warn('ADMIN_TOKEN fetch failed', e);
  }
}

async function loadUsers() {
  try {
    const rows = await fetchJSON('/php/api/users.php');
    const sel = document.getElementById('targetUser');
    sel.innerHTML = '<option value="">(선택)</option>';
    rows.forEach(u => {
      const opt = document.createElement('option');
      opt.value = u.id;
      opt.textContent = `${u.id} - ${u.username} (${u.name})`;
      sel.appendChild(opt);
    });
  } catch (e) {
    console.error('users load failed', e);
    alert('사용자 목록을 불러오지 못했습니다.');
  }
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
      if (s.config.target_user_id) {
        const sel = document.getElementById('targetUser');
        sel.value = String(s.config.target_user_id);
      }
    }
  } catch (e) {
    console.error(e);
  }
}

async function loadEngines() {
  try {
    const rows = await fetchJSON('/api/tri/status_all');
    const tbody = document.getElementById('enginesBody');
    tbody.innerHTML = '';
    rows.forEach(r => {
      const tr = document.createElement('tr');
      const routes = (r.config?.routes || []).join(', ');
      tr.innerHTML = `
        <td class="text-nowrap">${r.user_id} ${r.username ? `- ${r.username}` : ''}</td>
        <td>${r.running ? '<span class="badge bg-success">실행 중</span>' : '<span class="badge bg-secondary">중지</span>'}</td>
        <td>${routes || '-'}</td>
        <td>${r.config?.simulate ? 'Y' : 'N'}</td>
        <td class="text-nowrap">
          <button class="btn btn-sm btn-outline-success me-1" data-user="${r.user_id}" onclick="startEngineFor(this)">시작</button>
          <button class="btn btn-sm btn-outline-danger" data-user="${r.user_id}" onclick="stopEngineFor(this)">중지</button>
        </td>
      `;
      tbody.appendChild(tr);
    });
  } catch (e) {
    // 404 → 구서버 호환 폴백
    if ((e?.message || '').includes('HTTP 404')) {
      try {
        const s = await fetchJSON('/api/tri/status');
        const uid = parseInt(document.getElementById('targetUser').value || '0', 10) || '-';
        const rows = [{ user_id: uid, username: '', running: !!s.running, config: s.config || {} }];
        const tbody = document.getElementById('enginesBody');
        tbody.innerHTML = '';
        rows.forEach(r => {
          const tr = document.createElement('tr');
          const routes = (r.config?.routes || []).join(', ');
          tr.innerHTML = `
            <td class="text-nowrap">${r.user_id}</td>
            <td>${r.running ? '<span class="badge bg-success">실행 중</span>' : '<span class="badge bg-secondary">중지</span>'}</td>
            <td>${routes || '-'}</td>
            <td>${r.config?.simulate ? 'Y' : 'N'}</td>
            <td class="text-nowrap">
              <button class="btn btn-sm btn-outline-success me-1" data-user="${r.user_id}" onclick="startEngineFor(this)">시작</button>
              <button class="btn btn-sm btn-outline-danger" data-user="${r.user_id}" onclick="stopEngineFor(this)">중지</button>
            </td>
          `;
          tbody.appendChild(tr);
        });
      } catch (e2) {
        console.error('status fallback failed', e2);
      }
    } else {
      console.error('status_all failed', e);
    }
  }
}

async function saveConfig() {
  const btn = document.getElementById('btnSave');
  btn.disabled = true;
  try {
    const routes = document.getElementById('symbols').value.split(',').map(s => s.trim()).filter(Boolean);
    const target_user_id = parseInt(document.getElementById('targetUser').value || '0', 10);
    if (!target_user_id) {
      alert('대상 사용자를 선택하세요.');
      return;
    }
    const body = {
      routes,
      target_user_id,
      min_edge_bp: parseFloat(document.getElementById('minSpreadBp').value || '10'),
      maker_fee_bp: parseFloat(document.getElementById('feeBinance').value || '7.5'),
      interval_ms: Math.max(50, parseInt(document.getElementById('intervalSec').value || '1', 10) * 1000),
    };
    await fetchJSON('/api/tri/config', { method: 'POST', body: JSON.stringify(body) });
    await loadEngines();
    alert('설정이 저장되었습니다.');
  } catch (e) {
    console.error(e);
    alert(`설정 저장 실패: ${e.message || e}`);
  } finally {
    btn.disabled = false;
  }
}

async function startEngine() {
  const uid = parseInt(document.getElementById('targetUser').value || '0', 10);
  if (!uid) { alert('대상 사용자를 선택하세요.'); return; }
  const btn = document.getElementById('btnStart');
  btn.disabled = true;
  try {
    await fetchJSON('/api/tri/start', { method: 'POST', body: JSON.stringify({ user_id: uid }) });
    await loadEngines();
    alert('엔진이 시작되었습니다.');
  } catch (e) {
    console.error(e);
    alert(`시작 실패: ${e.message || e}`);
  } finally {
    btn.disabled = false;
  }
}

async function stopEngine() {
  const uid = parseInt(document.getElementById('targetUser').value || '0', 10);
  if (!uid) { alert('대상 사용자를 선택하세요.'); return; }
  const btn = document.getElementById('btnStop');
  btn.disabled = true;
  try {
    await fetchJSON('/api/tri/stop', { method: 'POST', body: JSON.stringify({ user_id: uid }) });
    await loadEngines();
    alert('엔진이 중지되었습니다.');
  } catch (e) {
    console.error(e);
    alert(`중지 실패: ${e.message || e}`);
  } finally {
    btn.disabled = false;
  }
}

async function startEngineFor(btn) {
  const uid = parseInt(btn.getAttribute('data-user'));
  try {
    await fetchJSON('/api/tri/start', { method: 'POST', body: JSON.stringify({ user_id: uid }) });
    await loadEngines();
  } catch (e) { alert(`시작 실패: ${e.message || e}`); }
}

async function stopEngineFor(btn) {
  const uid = parseInt(btn.getAttribute('data-user'));
  try {
    await fetchJSON('/api/tri/stop', { method: 'POST', body: JSON.stringify({ user_id: uid }) });
    await loadEngines();
  } catch (e) { alert(`중지 실패: ${e.message || e}`); }
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
  ensureAdminToken().then(() => {
    loadUsers().then(() => {
      loadStatus();
      loadEngines();
      loadSignals();
    });
  });
  document.getElementById('btnSave').addEventListener('click', saveConfig);
  document.getElementById('btnStart').addEventListener('click', startEngine);
  document.getElementById('btnStop').addEventListener('click', stopEngine);
  document.getElementById('btnRefresh').addEventListener('click', loadSignals);
  document.getElementById('btnReloadEngines').addEventListener('click', loadEngines);
  setInterval(loadEngines, 8000);
});
