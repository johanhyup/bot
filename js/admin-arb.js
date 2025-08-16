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
