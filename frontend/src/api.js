const API_URL = (import.meta.env.VITE_API_URL || '/api').replace(/\/$/, '');
const TOKEN_KEY = 'hcp_api_token';

export function token() {
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(value) {
  if (value) localStorage.setItem(TOKEN_KEY, value);
  else localStorage.removeItem(TOKEN_KEY);
}

export async function api(path, options = {}) {
  const isForm = options.body instanceof FormData;
  const headers = {
    Accept: 'application/json',
    ...(isForm ? {} : { 'Content-Type': 'application/json' }),
    ...(token() ? { Authorization: `Bearer ${token()}` } : {}),
    ...options.headers,
  };
  const response = await fetch(`${API_URL}${path}`, { ...options, headers });
  if (response.status === 204) return null;
  const type = response.headers.get('content-type') || '';
  const payload = type.includes('application/json') ? await response.json() : await response.text();
  if (!response.ok) {
    const errors = payload?.errors ? Object.values(payload.errors).flat().join(' ') : '';
    throw new Error(errors || payload?.message || `Request failed (${response.status})`);
  }
  return payload;
}

export async function login(username, password, pharmacy = false) {
  const result = await api(pharmacy ? '/auth/pharmacy-login' : '/auth/login', {
    method: 'POST',
    body: JSON.stringify({ username, password }),
  });
  setToken(result.token);
  return result;
}

export async function logout() {
  try {
    await api('/auth/logout', { method: 'POST' });
  } finally {
    setToken(null);
  }
}

export function asRows(payload) {
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.data)) return payload.data;
  if (Array.isArray(payload?.expenses?.data)) return payload.expenses.data;
  return [];
}
