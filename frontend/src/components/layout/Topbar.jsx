import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Bell, Menu, Moon, Plus, Search, Sun } from 'lucide-react';
import { useAuth } from '../../context/AuthContext';
import { initials } from '../../utils/formatters';

export default function Topbar({ onMenuToggle }) {
  const { user } = useAuth();
  const navigate  = useNavigate();
  const [dark,   setDark]   = useState(localStorage.getItem('hcp_theme') === 'dark');
  const [search, setSearch] = useState('');
  const avatarUrl = user?.profile_photo_url;

  const toggleDark = () => {
    const next = !dark;
    setDark(next);
    if (next) {
      document.documentElement.setAttribute('data-theme', 'dark');
    } else {
      document.documentElement.removeAttribute('data-theme');
    }
    localStorage.setItem('hcp_theme', next ? 'dark' : 'light');
  };

  const handleSearch = (e) => {
    if (e.key === 'Enter' && search.trim()) {
      navigate(`/patients?search=${encodeURIComponent(search.trim())}`);
      setSearch('');
    }
  };

  return (
    <header
      className="shrink-0 h-14 flex items-center gap-3 px-4 z-10"
      style={{
        background: 'var(--clr-topbar)',
        borderBottom: '1px solid var(--clr-border)',
      }}
    >
      {/* Mobile menu */}
      {onMenuToggle && (
        <button
          onClick={onMenuToggle}
          className="lg:hidden p-2 rounded-lg transition-colors"
          style={{ color: 'var(--clr-muted)' }}
          onMouseEnter={(e) => { e.currentTarget.style.background = 'var(--clr-hover)'; }}
          onMouseLeave={(e) => { e.currentTarget.style.background = 'transparent'; }}
        >
          <Menu size={18} />
        </button>
      )}

      {/* Search */}
      <div className="relative hidden sm:block">
        <Search
          size={14}
          className="absolute left-3 top-1/2 -translate-y-1/2"
          style={{ color: 'var(--clr-muted)' }}
        />
        <input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onKeyDown={handleSearch}
          placeholder="Search anything…"
          className="pl-8 pr-16 py-2 rounded-lg text-sm outline-none transition-all w-56 focus:w-72"
          style={{
            background: 'var(--clr-search-bg)',
            border: '1px solid var(--clr-search-border)',
            color: 'var(--clr-text)',
          }}
        />
        <span
          className="absolute right-3 top-1/2 -translate-y-1/2 text-[10px] px-1.5 py-0.5 rounded border font-mono"
          style={{
            color: 'var(--clr-section)',
            borderColor: 'var(--clr-border)',
            background: 'var(--clr-card)',
          }}
        >
          ⌘K
        </span>
      </div>

      <div className="flex-1" />

      {/* Right actions */}
      <div className="flex items-center gap-1">

        {/* Dark mode toggle */}
        <button
          onClick={toggleDark}
          title={dark ? 'Switch to light' : 'Switch to dark'}
          className="p-2 rounded-lg transition-colors"
          style={{ color: 'var(--clr-muted)' }}
          onMouseEnter={(e) => { e.currentTarget.style.background = 'var(--clr-hover)'; e.currentTarget.style.color = '#7c3aed'; }}
          onMouseLeave={(e) => { e.currentTarget.style.background = 'transparent'; e.currentTarget.style.color = 'var(--clr-muted)'; }}
        >
          {dark ? <Sun size={16} /> : <Moon size={16} />}
        </button>

        {/* Notifications */}
        <button
          onClick={() => navigate('/notifications')}
          className="relative p-2 rounded-lg transition-colors"
          style={{ color: 'var(--clr-muted)' }}
          onMouseEnter={(e) => { e.currentTarget.style.background = 'var(--clr-hover)'; }}
          onMouseLeave={(e) => { e.currentTarget.style.background = 'transparent'; }}
        >
          <Bell size={16} />
          <span className="absolute top-1.5 right-1.5 w-1.5 h-1.5 rounded-full bg-violet-600" />
        </button>

        {/* Divider */}
        <div className="w-px h-5 mx-1" style={{ background: 'var(--clr-border)' }} />

        {/* Avatar */}
        <button
          onClick={() => navigate('/profile')}
          className="flex items-center gap-2 pl-1 group"
        >
          <div className="hidden sm:block text-right">
            <p className="text-xs font-semibold leading-tight" style={{ color: 'var(--clr-text)' }}>
              {user?.full_name ?? 'User'}
            </p>
            <p className="text-[10px]" style={{ color: 'var(--clr-section)' }}>{user?.role}</p>
          </div>
          <div className="w-8 h-8 rounded-full bg-violet-600 flex items-center justify-center ring-2 ring-transparent group-hover:ring-violet-500/40 transition-all overflow-hidden">
            {avatarUrl ? (
              <img src={avatarUrl} alt={user?.full_name ?? 'Profile'} className="w-full h-full object-cover" />
            ) : (
              <span className="text-[#ffffff] text-xs font-bold">{initials(user?.full_name)}</span>
            )}
          </div>
        </button>
      </div>
    </header>
  );
}
