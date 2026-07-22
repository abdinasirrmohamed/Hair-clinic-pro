import { useState } from 'react';
import { login } from '../api.js';
import BrandLogo from './ui/BrandLogo.jsx';

export function Login({ onLogin }) {
  const [form, setForm] = useState({ username: '', password: '' });
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);
  const submit = async (event) => {
    event.preventDefault();
    setBusy(true); setError('');
    try { await login(form.username, form.password); onLogin(); }
    catch (err) { setError(err.message); }
    finally { setBusy(false); }
  };
  return (
    <div className="login-page">
      <section className="react-login-card">
        <div className="login-brand"><BrandLogo size="lg" /><div><h1>Hair Clinic Pro</h1><p>Clinic Management System</p></div></div>
        <h2>Welcome back</h2><p>Sign in to continue to your workspace.</p>
        {error && <div className="alert alert-danger">{error}</div>}
        <form onSubmit={submit}>
          <label><span>Username</span><input autoFocus value={form.username} onChange={(e) => setForm({ ...form, username: e.target.value })} required /></label>
          <label><span>Password</span><input type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} required /></label>
          <button className="btn btn-primary login-submit" disabled={busy}>{busy ? 'Signing in...' : 'Sign In'}</button>
        </form>
      </section>
    </div>
  );
}
