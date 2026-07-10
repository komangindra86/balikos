let API_BASE = 'http://10.0.2.2/api/balikos';
let TOKEN = null;

export function setApiBase(value) {
  API_BASE = (value || API_BASE).replace(/\/$/, '');
}

export function setToken(value) {
  TOKEN = value;
}

export async function api(path, { method = 'GET', body, auth = true, isMultipart = false } = {}) {
  const headers = { Accept: 'application/json' };
  if (auth && TOKEN) headers.Authorization = `Bearer ${TOKEN}`;
  if (body && !isMultipart) headers['Content-Type'] = 'application/json';

  const response = await fetch(`${API_BASE}${path}`, {
    method,
    headers,
    body: body ? (isMultipart ? body : JSON.stringify(body)) : undefined,
  });

  const text = await response.text();
  const contentType = response.headers.get('content-type') || '';
  let json = {};
  if (text && contentType.includes('application/json')) {
    try {
      json = JSON.parse(text);
    } catch {
      json = {};
    }
  }
  if (!response.ok) {
    const fallback = text && !contentType.includes('application/json') ? text.slice(0, 180) : null;
    const details = json.errors ? Object.values(json.errors).flat().join('\n') : json.message;
    if (response.status === 401) throw new Error('Sesi login habis. Silakan login ulang.');
    throw new Error(details || 'Request gagal.');
  }
  return json;
}
