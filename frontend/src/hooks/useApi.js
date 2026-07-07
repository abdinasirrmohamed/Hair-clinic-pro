import { useCallback, useEffect, useRef, useState } from 'react';
import api, { asRows } from '../api';

/**
 * Generic data-fetching hook.
 * @param {string}  endpoint  - API path, e.g. "/patients"
 * @param {string}  [payloadKey] - Nested key in response, e.g. "expenses"
 * @param {boolean} [autoLoad]   - Whether to fetch on mount (default true)
 */
export function useApi(endpoint, payloadKey, autoLoad = true) {
  const [rows,    setRows]    = useState([]);
  const [summary, setSummary] = useState(null);
  const [loading, setLoading] = useState(autoLoad);
  const [error,   setError]   = useState('');
  const searchRef = useRef('');

  const load = useCallback(async (search = searchRef.current) => {
    setLoading(true);
    setError('');
    try {
      const params = search ? `?search=${encodeURIComponent(search)}` : '';
      const { data } = await api.get(`${endpoint}${params}`);
      setRows(asRows(data, payloadKey));
      setSummary(data?.summary ?? null);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, [endpoint, payloadKey]);

  useEffect(() => {
    if (autoLoad) load();
  }, [load, autoLoad]);

  const setSearch = (s) => { searchRef.current = s; };

  return { rows, summary, loading, error, load, setSearch };
}
