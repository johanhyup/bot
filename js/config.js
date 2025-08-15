/* api endpoint config + fetch helper */
window.API_BASES = [
  '',                                         // same-origin proxy: /api/...
  `http://${window.location.hostname}:8000`   // direct uvicorn on server host
];

async function apiFetch(path, init) {
  let lastErr = null;
  for (const base of window.API_BASES) {
    const url = (base ? base.replace(/\/+$/, '') : '') + path;
    try {
      const res = await fetch(url, init);
      if (!res.ok) {
        const text = await res.text().catch(() => '');
        throw new Error(`HTTP ${res.status} ${res.statusText} - ${text.slice(0, 200)}`);
      }
      return res;
    } catch (e) {
      lastErr = e;
      // try next base
    }
  }
  throw lastErr || new Error('All API endpoints failed');
}
