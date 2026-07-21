import { useEffect, useMemo, useState } from 'react';
import { Camera, Lock, Save, User } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import api from '../api';
import Alert from '../components/ui/Alert';
import PasswordInput from '../components/ui/PasswordInput';
import { initials } from '../utils/formatters';

export default function Profile() {
  const { user, refresh } = useAuth();
  const [form, setForm] = useState({ full_name: user?.full_name ?? '', old_password: '', password: '' });
  const [photo, setPhoto] = useState(null);
  const [preview, setPreview] = useState(user?.profile_photo_url ?? '');
  const [message, setMessage] = useState({ text: '', type: 'info' });
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    setForm((current) => ({ ...current, full_name: user?.full_name ?? '' }));
    setPreview(user?.profile_photo_url ?? '');
  }, [user]);

  const previewUrl = useMemo(() => (photo ? URL.createObjectURL(photo) : preview), [photo, preview]);

  useEffect(() => () => {
    if (previewUrl?.startsWith('blob:')) URL.revokeObjectURL(previewUrl);
  }, [previewUrl]);

  const submit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setMessage({ text: '', type: 'info' });

    try {
      const payload = new FormData();
      payload.append('_method', 'PUT');
      payload.append('full_name', form.full_name);
      payload.append('old_password', form.old_password);
      payload.append('password', form.password);
      if (photo) payload.append('profile_photo', photo);

      await api.post('/users/profile', payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });

      setMessage({ text: 'Profile updated successfully.', type: 'success' });
      setForm((current) => ({ ...current, old_password: '', password: '' }));
      setPhoto(null);
      refresh();
    } catch (err) {
      setMessage({ text: err.message, type: 'danger' });
    } finally {
      setSaving(false);
    }
  };

  const inputStyle = {
    width: '100%',
    padding: '.75rem 1rem',
    borderRadius: '.625rem',
    border: '1px solid var(--clr-border)',
    background: 'var(--clr-search-bg)',
    color: 'var(--clr-text)',
    fontSize: '.875rem',
    outline: 'none',
    fontFamily: 'inherit',
    transition: 'border-color .15s, box-shadow .15s',
  };
  const focusStyle = { borderColor: '#7c3aed', boxShadow: '0 0 0 3px rgba(124,58,237,.12)' };
  const labelClass = 'block text-[10px] font-semibold uppercase tracking-widest mb-1.5';

  return (
    <div className="space-y-5 animate-fade-in max-w-2xl">
      <div>
        <h1 className="text-xl font-bold" style={{ color: 'var(--clr-text)' }}>My Profile</h1>
        <p className="mt-1 text-xs" style={{ color: 'var(--clr-muted)' }}>
          Manage your account details, photo, and password.
        </p>
      </div>

      <div
        className="rounded-xl overflow-hidden"
        style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}
      >
        <div
          className="h-20 relative"
          style={{ background: 'linear-gradient(135deg, #0d1410 0%, #111912 50%, rgba(124,58,237,.08) 100%)', borderBottom: '1px solid var(--clr-border)' }}
        >
          <div
            className="absolute inset-0 pointer-events-none"
            style={{ background: 'radial-gradient(ellipse at right, rgba(124,58,237,.1), transparent 60%)' }}
          />
          <div className="absolute -bottom-7 left-6">
            <div
              className="w-14 h-14 rounded-xl bg-green-500 flex items-center justify-center shadow-lg overflow-hidden"
              style={{ border: '3px solid var(--clr-card)' }}
            >
              {previewUrl ? (
                <img src={previewUrl} alt={user?.full_name ?? 'Profile'} className="w-full h-full object-cover" />
              ) : (
                <span className="text-[#ffffff] text-lg font-bold">{initials(user?.full_name)}</span>
              )}
            </div>
          </div>
        </div>

        <div className="pt-10 px-6 pb-2 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
          <div>
            <p className="text-base font-bold" style={{ color: 'var(--clr-text)' }}>{user?.full_name}</p>
            <p className="text-xs mt-0.5" style={{ color: 'var(--clr-muted)' }}>
              {user?.role} - @{user?.username}
            </p>
          </div>

          <label
            className="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold cursor-pointer transition-colors"
            style={{ color: '#166534', background: '#dcfce7', border: '1px solid #bbf7d0' }}
          >
            <Camera size={14} />
            Upload Photo
            <input
              type="file"
              accept="image/png,image/jpeg,image/jpg,image/webp"
              className="hidden"
              onChange={(event) => setPhoto(event.target.files?.[0] ?? null)}
            />
          </label>
        </div>

        <form onSubmit={submit} className="p-6 space-y-4" style={{ borderTop: '1px solid var(--clr-border)', marginTop: '0.5rem' }}>
          {message.text && <Alert message={message.text} variant={message.type} />}

          <div>
            <label className={labelClass} style={{ color: 'var(--clr-section)' }}>
              <span className="flex items-center gap-1.5"><User size={10} /> Full Name</span>
            </label>
            <input
              style={inputStyle}
              value={form.full_name}
              required
              onChange={(e) => setForm({ ...form, full_name: e.target.value })}
              onFocus={(e) => Object.assign(e.target.style, focusStyle)}
              onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }}
              placeholder="Your full name"
            />
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className={labelClass} style={{ color: 'var(--clr-section)' }}>
                <span className="flex items-center gap-1.5"><Lock size={10} /> Current Password</span>
              </label>
              <PasswordInput
                style={inputStyle}
                value={form.old_password}
                onChange={(e) => setForm({ ...form, old_password: e.target.value })}
                onFocus={(e) => Object.assign(e.target.style, focusStyle)}
                onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }}
                placeholder="Required to change password"
              />
            </div>
            <div>
              <label className={labelClass} style={{ color: 'var(--clr-section)' }}>
                <span className="flex items-center gap-1.5"><Lock size={10} /> New Password</span>
              </label>
              <PasswordInput
                style={inputStyle}
                value={form.password}
                onChange={(e) => setForm({ ...form, password: e.target.value })}
                onFocus={(e) => Object.assign(e.target.style, focusStyle)}
                onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }}
                placeholder="Leave blank to keep current"
              />
            </div>
          </div>

          <div className="flex justify-end pt-2">
            <button
              type="submit"
              disabled={saving}
              className="flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold transition-all disabled:opacity-50"
              style={{ background: '#7c3aed', color: '#ffffff', border: 'none', cursor: saving ? 'not-allowed' : 'pointer' }}
              onMouseEnter={(e) => { if (!saving) e.currentTarget.style.background = '#6d28d9'; }}
              onMouseLeave={(e) => { e.currentTarget.style.background = '#7c3aed'; }}
            >
              <Save size={14} />
              {saving ? 'Saving...' : 'Save Changes'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
