import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import Alert from '../components/ui/Alert';
import { Activity, Eye, EyeOff, Lock, User } from 'lucide-react';

export default function Login() {
  const { login } = useAuth();
  const navigate  = useNavigate();
  const [form,  setForm]  = useState({ username: '', password: '' });
  const [error, setError] = useState('');
  const [busy,  setBusy]  = useState(false);
  const [showPw, setShowPw] = useState(false);

  const submit = async (e) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await login(form.username, form.password);
      navigate('/dashboard', { replace: true });
    } catch (err) {
      setError(err.message);
    } finally {
      setBusy(false);
    }
  };

  const inputStyle = {
    width: '100%',
    padding: '.75rem 1rem .75rem 2.5rem',
    borderRadius: '.75rem',
    border: '1px solid rgba(34,197,94,.2)',
    background: 'rgba(34,197,94,.04)',
    color: '#e8f4ea',
    fontSize: '.875rem',
    outline: 'none',
    fontFamily: 'inherit',
    transition: 'border-color .15s, box-shadow .15s',
    caretColor: '#22c55e',
  };
  const focusStyle = { borderColor: '#22c55e', boxShadow: '0 0 0 3px rgba(34,197,94,.15)' };

  return (
    <div
      className="min-h-screen flex items-center justify-center p-4 relative overflow-hidden"
      style={{ background: '#080e0a' }}
    >
      {/* Background glow orbs */}
      <div
        className="absolute w-96 h-96 rounded-full pointer-events-none"
        style={{
          top: '-8rem', right: '-8rem',
          background: 'radial-gradient(circle, rgba(34,197,94,.08) 0%, transparent 70%)',
        }}
      />
      <div
        className="absolute w-96 h-96 rounded-full pointer-events-none"
        style={{
          bottom: '-8rem', left: '-8rem',
          background: 'radial-gradient(circle, rgba(34,197,94,.06) 0%, transparent 70%)',
        }}
      />

      <div className="relative w-full max-w-sm animate-slide-up">
        {/* Card */}
        <div
          className="rounded-2xl p-8"
          style={{
            background: '#0d1410',
            border: '1px solid rgba(34,197,94,.15)',
            boxShadow: '0 25px 60px rgba(0,0,0,0.5), 0 0 0 1px rgba(34,197,94,.08)',
          }}
        >
          {/* Brand */}
          <div className="flex items-center gap-3 mb-8">
            <div className="w-10 h-10 rounded-xl bg-green-500 flex items-center justify-center">
              <Activity size={20} className="text-[#052e10]" />
            </div>
            <div>
              <p className="text-base font-bold text-white">Hair Clinic Pro</p>
              <p className="text-xs" style={{ color: '#3a5c42' }}>Management System</p>
            </div>
          </div>

          <h1 className="text-2xl font-bold text-white mb-1">Welcome back</h1>
          <p className="text-sm mb-6" style={{ color: '#6b8f6e' }}>
            Sign in to continue to your workspace.
          </p>

          {error && <div className="mb-4"><Alert message={error} /></div>}

          <form onSubmit={submit} className="space-y-4">
            {/* Username */}
            <div>
              <label
                className="block text-[10px] font-semibold uppercase tracking-widest mb-1.5"
                style={{ color: '#3a5c42' }}
              >
                Username
              </label>
              <div className="relative">
                <User size={14} className="absolute left-3.5 top-1/2 -translate-y-1/2" style={{ color: '#6b8f6e' }} />
                <input
                  type="text"
                  autoFocus
                  required
                  value={form.username}
                  onChange={(e) => setForm({ ...form, username: e.target.value })}
                  placeholder="Enter username"
                  style={inputStyle}
                  onFocus={(e) => Object.assign(e.target.style, focusStyle)}
                  onBlur={(e) => { e.target.style.borderColor = 'rgba(34,197,94,.2)'; e.target.style.boxShadow = 'none'; }}
                />
              </div>
            </div>

            {/* Password */}
            <div>
              <label
                className="block text-[10px] font-semibold uppercase tracking-widest mb-1.5"
                style={{ color: '#3a5c42' }}
              >
                Password
              </label>
              <div className="relative">
                <Lock size={14} className="absolute left-3.5 top-1/2 -translate-y-1/2" style={{ color: '#6b8f6e' }} />
                <input
                  type={showPw ? 'text' : 'password'}
                  required
                  value={form.password}
                  onChange={(e) => setForm({ ...form, password: e.target.value })}
                  placeholder="Enter password"
                  style={{ ...inputStyle, paddingRight: '2.75rem' }}
                  onFocus={(e) => Object.assign(e.target.style, focusStyle)}
                  onBlur={(e) => { e.target.style.borderColor = 'rgba(34,197,94,.2)'; e.target.style.boxShadow = 'none'; }}
                />
                <button
                  type="button"
                  onClick={() => setShowPw(!showPw)}
                  className="absolute right-3.5 top-1/2 -translate-y-1/2 transition-colors"
                  style={{ color: '#6b8f6e', background: 'none', border: 'none', cursor: 'pointer' }}
                  onMouseEnter={(e) => { e.currentTarget.style.color = '#22c55e'; }}
                  onMouseLeave={(e) => { e.currentTarget.style.color = '#6b8f6e'; }}
                >
                  {showPw ? <EyeOff size={14} /> : <Eye size={14} />}
                </button>
              </div>
            </div>

            {/* Submit */}
            <button
              type="submit"
              disabled={busy}
              className="w-full py-3 rounded-xl text-sm font-bold transition-all mt-2 disabled:opacity-50 disabled:cursor-not-allowed"
              style={{
                background: '#22c55e',
                color: '#052e10',
                border: 'none',
                boxShadow: '0 4px 20px rgba(34,197,94,.3)',
                cursor: busy ? 'not-allowed' : 'pointer',
              }}
              onMouseEnter={(e) => { if (!busy) e.currentTarget.style.background = '#16a34a'; }}
              onMouseLeave={(e) => { e.currentTarget.style.background = '#22c55e'; }}
            >
              {busy ? (
                <span className="flex items-center justify-center gap-2">
                  <span
                    className="w-4 h-4 rounded-full animate-spin"
                    style={{ border: '2px solid rgba(5,46,16,.3)', borderTopColor: '#052e10' }}
                  />
                  Signing in…
                </span>
              ) : (
                'Sign In'
              )}
            </button>
          </form>

          <p className="mt-6 text-center text-[10px]" style={{ color: '#3a5c42' }}>
            Hair Clinic Pro · All rights reserved © {new Date().getFullYear()}
          </p>
        </div>
      </div>
    </div>
  );
}
