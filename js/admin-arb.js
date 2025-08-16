const H = {
  get headers() {
    const h = {};
    if (window.ADMIN_TOKEN) h['X-Admin-Token'] = window.ADMIN_TOKEN;
    return h;
  }
};

async function fetchJSON(url, opt = {}) {
  const method = (opt.method || 'GET').toUpperCase();
  const baseHeaders = H.headers;
  const bodyHeaders = (method !== 'GET' && method !== 'HEAD') ? { 'Content-Type': 'application/json' } : {};
  const headers = { ...(opt.headers || {}), ...baseHeaders, ...bodyHeaders };
  const res = await fetch(url, { ...opt, headers, credentials: 'same-origin' });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

// (추가) 404면 다음 후보 경로로 재시도
async function postWithFallback(paths, bodyObj) {
  const payload = { method: 'POST', body: JSON.stringify(bodyObj) };
  for (const p of paths) {
    try {
      return await fetchJSON(p, payload);
    } catch (e) {
      if ((e?.message || '').includes('HTTP 404')) continue;
      throw e;
    }
  }
  throw new Error('HTTP 404 (all fallbacks)');
}

// (추가) GET/POST 공통 폴백 헬퍼
async function fetchWithFallback(paths, opt = {}) {
  let lastErr;
  for (const p of paths) {
    try {
      return await fetchJSON(p, opt);
    } catch (e) {
      lastErr = e;
      if ((e?.message || '').includes('HTTP 404')) continue;
      throw e;
    }
  }
  throw lastErr || new Error('HTTP 404 (all fallbacks)');
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
    // 캐시 회피 파라미터 추가
    const rows = await fetchJSON(`/php/api/users.php?_=${Date.now()}`);
    const sel = document.getElementById('targetUser');
    const prev = sel.value;
    sel.innerHTML = '<option value="">(선택)</option>';
    rows.forEach(u => {
      const opt = document.createElement('option');
      opt.value = u.id;
      opt.textContent = `${u.id} - ${u.username} (${u.name})`;
      sel.appendChild(opt);
    });
    // 기존 선택값 유지
    if (prev) sel.value = prev;
  } catch (e) {
    console.error('users load failed', e);
    alert('사용자 목록을 불러오지 못했습니다.');
  }
}

// (변경) 상태 로딩에 프록시 폴백 추가
async function loadStatus() {
  try {
    const s = await fetchWithFallback(
      [
        '/api/tri/status',
        '/php/api/tri_proxy.php?path=tri/status'
      ]
    );
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
    console.error('loadStatus error:', e);
  }
}

// (변경) 엔진 목록에 프록시 폴백 추가
async function loadEngines() {
  try {
    const rows = await fetchWithFallback(
      [
        '/api/tri/status_all',
        '/php/api/tri_proxy.php?path=tri/status_all'
      ]
    );
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
    console.error('loadEngines error:', e);
  }
}

// (변경) 설정 저장: 프록시 폴백 경로 추가
async function saveConfig() {
  const btn = document.getElementById('btnSave');
  if (btn) btn.disabled = true;
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
    await postWithFallback(
      [
        '/api/tri/config',
        '/api/tri/config/',
        '/api/tri/save',
        '/api/tri/config/set',
        '/php/api/tri_proxy.php?path=tri/config',
        '/php/api/tri_proxy.php?path=tri/save',
        '/php/api/tri_proxy.php?path=tri/config/set'
      ],
      body
    );
    await loadEngines();
    alert('설정이 저장되었습니다.');
  } catch (e) {
    console.error(e);
    alert(`설정 저장 실패: ${e.message || e}`);
  } finally {
    if (btn) btn.disabled = false;
  }
}

// (변경) 엔진 시작/중지: 프록시 폴백 경로 추가
async function startEngine() {
  const uid = parseInt(document.getElementById('targetUser').value || '0', 10);
  if (!uid) { alert('대상 사용자를 선택하세요.'); return; }
  const btn = document.getElementById('btnStart');
  if (btn) btn.disabled = true;
  try {
    await postWithFallback(
      [
        '/api/tri/start',
        '/api/tri/start/',
        '/php/api/tri_proxy.php?path=tri/start'
      ],
      { user_id: uid }
    );
    await loadEngines();
    alert('엔진이 시작되었습니다.');
  } catch (e) {
    console.error(e);
    alert(`시작 실패: ${e.message || e}`);
  } finally {
    if (btn) btn.disabled = false;
  }
}

async function stopEngine() {
  const uid = parseInt(document.getElementById('targetUser').value || '0', 10);
  if (!uid) { alert('대상 사용자를 선택하세요.'); return; }
  const btn = document.getElementById('btnStop');
  if (btn) btn.disabled = true;
  try {
    await postWithFallback(
      [
        '/api/tri/stop',
        '/api/tri/stop/',
        '/php/api/tri_proxy.php?path=tri/stop'
      ],
      { user_id: uid }
    );
    await loadEngines();
    alert('엔진이 중지되었습니다.');
  } catch (e) {
    console.error(e);
    alert(`중지 실패: ${e.message || e}`);
  } finally {
    if (btn) btn.disabled = false;
  }
}

// (추가) 테이블 액션용
async function startEngineFor(btn) {
  const uid = parseInt(btn.getAttribute('data-user') || '0', 10);
  if (!uid) return;
  try {
    await postWithFallback(
      [
        '/api/tri/start',
        '/api/tri/start/',
        '/php/api/tri_proxy.php?path=tri/start'
      ],
      { user_id: uid }
    );
    await loadEngines();
  } catch (e) { alert(`시작 실패: ${e.message || e}`); }
}

async function stopEngineFor(btn) {
  const uid = parseInt(btn.getAttribute('data-user') || '0', 10);
  if (!uid) return;
  try {
    await postWithFallback(
      [
        '/api/tri/stop',
        '/api/tri/stop/',
        '/php/api/tri_proxy.php?path=tri/stop'
      ],
      { user_id: uid }
    );
    await loadEngines();
  } catch (e) { alert(`중지 실패: ${e.message || e}`); }
}

// (변경) 시그널: 프록시 폴백 추가
async function loadSignals() {
  try {
    const rows = await fetchWithFallback(
      [
        '/api/arb/signals',
        '/php/api/tri_proxy.php?path=arb/signals'
      ]
    );
    const tbody = document.getElementById('signalsBody');
    if (!tbody) return;
    tbody.innerHTML = '';
    rows.forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${r.time}</td><td>${r.symbol}</td><td>${r.side}</td><td>${Number(r.spread_bp).toFixed(2)}</td>`;
      tbody.appendChild(tr);
    });
  } catch (e) {
    console.error('loadSignals error:', e);
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

  // (추가) 버튼 이벤트 바인딩
  document.getElementById('btnSave')?.addEventListener('click', saveConfig);
  document.getElementById('btnStart')?.addEventListener('click', startEngine);
  document.getElementById('btnStop')?.addEventListener('click', stopEngine);
  document.getElementById('btnRefresh')?.addEventListener('click', loadSignals);
  document.getElementById('btnReloadEngines')?.addEventListener('click', loadEngines);
  document.getElementById('btnReloadUsers')?.addEventListener('click', loadUsers);

  // (추가) 주기적 갱신
  setInterval(loadEngines, 8000);
});
