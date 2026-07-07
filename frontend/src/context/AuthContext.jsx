import { createContext, useCallback, useContext, useEffect, useState } from 'react';
import api, { login as doLogin, logout as doLogout, token } from '../api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [bootstrap, setBootstrap] = useState(null);
  const [loading,   setLoading]   = useState(Boolean(token()));
  const [authError, setAuthError]  = useState('');

  const loadBootstrap = useCallback(async () => {
    if (!token()) { setBootstrap(null); setLoading(false); return; }
    setLoading(true);
    setAuthError('');
    try {
      const { data } = await api.get('/bootstrap');
      setBootstrap(data);
    } catch (err) {
      setAuthError(err.message);
      setBootstrap(null);
      // Clear invalid token
      localStorage.removeItem('hcp_api_token');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadBootstrap(); }, [loadBootstrap]);

  const login = async (username, password) => {
    await doLogin(username, password);
    await loadBootstrap();
  };

  const logout = async () => {
    await doLogout();
    setBootstrap(null);
  };

  const value = {
    bootstrap,
    user:        bootstrap?.user        ?? null,
    permissions: bootstrap?.permissions ?? [],
    lookups:     bootstrap?.lookups     ?? {},
    loading,
    authError,
    login,
    logout,
    refresh: loadBootstrap,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export const useAuth = () => {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error('useAuth must be used inside <AuthProvider>');
  return ctx;
};
