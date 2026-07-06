import { useCallback, useEffect, useMemo, useState } from 'react';
import { Bell, Moon, Search, Sun } from 'lucide-react';
import { api, logout, token } from './api.js';
import { modules } from './modules.js';
import { moduleRoutes, navigate, routeForPath } from './routes.js';
import { CrudPage } from './components/CrudPage.jsx';
import { Login } from './components/Login.jsx';
import { AuditLogs, Dashboard, DoctorAppointments, Inventory, Pharmacy, Prescriptions, Profile, Reports } from './components/SpecialPages.jsx';

function initials(name = '') {
  return name.trim().split(/\s+/).map((part) => part[0]).slice(0, 2).join('').toUpperCase();
}

function Link({ href, children, className = '', title }) {
  return <a href={href} className={className} title={title} onClick={(event) => { if (!event.ctrlKey && !event.metaKey) { event.preventDefault(); navigate(href); } }}>{children}</a>;
}

function Shell({ bootstrap, route, children, onLogout }) {
  const [dark, setDark] = useState(localStorage.getItem('hcp_theme') === 'dark');
  useEffect(() => {
    document.documentElement.toggleAttribute('data-theme', dark);
    localStorage.setItem('hcp_theme', dark ? 'dark' : 'light');
  }, [dark]);
  const menu = moduleRoutes.filter((item) => item.module !== 'profile' && bootstrap.permissions.includes(item.module));
  const roleKey = bootstrap.user.role.toLowerCase().replaceAll(' ', '_');
  return (
    <div className={`dashboard-shell role-${roleKey}`}>
      <aside className="clinic-sidebar">
        <div>
          <Link className="brand-mark" href="/dashboard"><span>Hair Clinic Pro</span><span className={`role-sidebar-badge rbadge-${roleKey}`}>{bootstrap.user.role}</span></Link>
          <nav className="side-nav">{menu.map((item) => <Link className={route.module === item.module ? 'active' : ''} href={item.path} key={item.module}><i className={`bi ${item.icon}`} /><span>{item.title}</span></Link>)}</nav>
        </div>
        <div className="side-bottom"><Link href="/profile"><i className="bi bi-person-circle" /><span>My Profile</span></Link><button type="button" onClick={onLogout}><i className="bi bi-box-arrow-right" /><span>Logout</span></button></div>
      </aside>
      <section className="clinic-main">
        <header className="clinic-topbar patient-topbar">
          <div className="top-search patient-search"><Search size={18} /><input placeholder="Search patients, records..." onKeyDown={(event) => { if (event.key === 'Enter') navigate(`/patients?search=${encodeURIComponent(event.currentTarget.value)}`); }} /></div>
          <div className="top-actions admin-profile"><button className="dark-toggle" type="button" onClick={() => setDark(!dark)}>{dark ? <Sun size={19} /> : <Moon size={19} />}</button><button type="button"><Bell size={18} /></button><span className="profile-divider" /><span className="admin-copy"><strong>{bootstrap.user.full_name}</strong><small>{bootstrap.user.role}</small></span><Link className="admin-avatar-link" href="/profile"><div className="admin-avatar">{initials(bootstrap.user.full_name)}</div></Link></div>
        </header>
        <main className="clinic-content legacy-content">{children}</main>
      </section>
    </div>
  );
}

export function App() {
  const [bootstrap, setBootstrap] = useState(null);
  const [route, setRoute] = useState(() => routeForPath(window.location.pathname));
  const [loading, setLoading] = useState(Boolean(token()));
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    if (!token()) { setBootstrap(null); setLoading(false); return; }
    setLoading(true); setError('');
    try { setBootstrap(await api('/bootstrap')); }
    catch (err) { setError(err.message); localStorage.removeItem('hcp_api_token'); setBootstrap(null); }
    finally { setLoading(false); }
  }, []);

  useEffect(() => { load(); }, [load]);
  useEffect(() => {
    const change = () => setRoute(routeForPath(window.location.pathname));
    addEventListener('popstate', change);
    if (window.location.pathname === '/' || window.location.pathname === '/index.html') navigate('/dashboard');
    return () => removeEventListener('popstate', change);
  }, []);

  const content = useMemo(() => {
    if (!bootstrap) return null;
    const props = { route, bootstrap, lookups: bootstrap.lookups, refresh: load };
    if (route.module === 'dashboard') return <Dashboard {...props} />;
    if (route.module === 'reports') return <Reports {...props} />;
    if (route.module === 'audit_logs') return <AuditLogs {...props} />;
    if (route.module === 'doctor_appointments') return <DoctorAppointments {...props} />;
    if (route.module === 'prescriptions') return <Prescriptions {...props} />;
    if (route.module === 'inventory') return <Inventory {...props} />;
    if (route.module === 'pharmacy') return <Pharmacy {...props} />;
    if (route.module === 'profile') return <Profile {...props} user={bootstrap.user} />;
    const config = modules[route.module];
    return config ? <CrudPage route={route} config={config} lookups={bootstrap.lookups} onDataChanged={load} /> : null;
  }, [bootstrap, route, load]);

  if (loading) return <div className="react-state">Loading Hair Clinic Pro...</div>;
  if (!bootstrap) return <Login onLogin={load} />;
  if (error) return <div className="react-state">{error}</div>;
  return <Shell bootstrap={bootstrap} route={route} onLogout={async () => { await logout(); setBootstrap(null); navigate('/dashboard'); }}>{content}</Shell>;
}
