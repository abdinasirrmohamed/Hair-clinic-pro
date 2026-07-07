import axios from 'axios';

const TOKEN_KEY = 'hcp_api_token';

/* ─── Token helpers ─── */
export const token   = () => localStorage.getItem(TOKEN_KEY);
export const setToken = (v) => v
  ? localStorage.setItem(TOKEN_KEY, v)
  : localStorage.removeItem(TOKEN_KEY);

/* ─── Axios instance ─── */
const api = axios.create({
  baseURL: '/api',
  headers: { Accept: 'application/json' },
});

/* Attach Bearer token on every request */
api.interceptors.request.use((config) => {
  const t = token();
  if (t) config.headers.Authorization = `Bearer ${t}`;
  return config;
});

/* Normalize error messages from Laravel JSON responses */
api.interceptors.response.use(
  (res) => res,
  (err) => {
    const data   = err.response?.data;
    const errors = data?.errors
      ? Object.values(data.errors).flat().join(' ')
      : '';
    const message = errors || data?.message || `Request failed (${err.response?.status ?? 'network'})`;
    return Promise.reject(new Error(message));
  },
);

export default api;

/* ─── Auth helpers ─── */
export async function login(username, password) {
  const { data } = await api.post('/auth/login', { username, password });
  setToken(data.token);
  return data;
}

export async function logout() {
  try { await api.post('/auth/logout'); } finally { setToken(null); }
}

/* ─── Response normaliser ─── */
export function asRows(payload, payloadKey) {
  if (payloadKey) {
    const sub = payload?.[payloadKey];
    if (Array.isArray(sub)) return sub;
    if (Array.isArray(sub?.data)) return sub.data;
  }
  if (Array.isArray(payload))       return payload;
  if (Array.isArray(payload?.data)) return payload.data;
  return [];
}
