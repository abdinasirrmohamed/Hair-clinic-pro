import { useEffect, useRef, useState } from 'react';
import { NavLink, useNavigate, useLocation } from 'react-router-dom';
import { Bell, Activity, ArrowLeft, BarChart3, Boxes, CalendarDays, ChevronDown, ClipboardList, History, House, LogOut, Menu, Package, Plus, Search, Settings, ShoppingCart, Store, Truck, Users } from 'lucide-react';
import api from '../../api';
import { useAuth } from '../../context/AuthContext';
import { initials } from '../../utils/formatters';

const links = [
  ['dashboard', 'Dashboard', House], ['pos-sales', 'POS Sales', ShoppingCart],
  ['sales-history', 'Sales History', History], ['medicines', 'Products', Package],
  ['customers', 'Customers', Users], ['inventory', 'Inventory', Boxes],
  ['prescription-sales', 'Prescriptions', ClipboardList], ['suppliers', 'Suppliers', Truck],
  ['purchases', 'Purchases', ShoppingCart], ['movements', 'Stock Movements', Activity],
  ['alerts', 'Stock & Expiry Alerts', Activity], ['batches', 'Medicine Batches', Boxes],
  ['orders', 'Purchase Orders', ClipboardList], ['payables', 'Supplier Balances', Truck],
  ['register', 'Cash Register', Store], ['labels', 'Barcode Labels', Package],
  ['stocktakes', 'Stocktake', ClipboardList], ['profit', 'Profit Report', BarChart3],
  ['reports', 'Reports', BarChart3],
];

export function PharmacySidebar({ onClose }) {
  const { user, permissions, logout } = useAuth();
  return <aside className="ph-sidebar">
    <NavLink to="/pharmacy/dashboard" className="ph-brand" onClick={onClose}><span className="ph-brand-icon"><Plus size={27} strokeWidth={5} /></span><span><strong><em>POS</em> System</strong><small>Pharmacy & Retail</small></span></NavLink>
    <nav aria-label="Pharmacy navigation">{links.map(([slug, label, Icon]) => <NavLink key={slug} to={`/pharmacy/${slug}`} onClick={onClose} className={({ isActive }) => `ph-nav-link ${isActive ? 'active' : ''}`}><Icon size={17} strokeWidth={1.65} /><span>{label}</span></NavLink>)}
      {user?.role === 'Administrator' && <NavLink to="/pharmacy/backups" onClick={onClose} className="ph-nav-link"><History size={17} />Database Backups</NavLink>}
      {permissions.includes('settings') && <NavLink to="/settings" onClick={onClose} className="ph-nav-link"><Settings size={17} />Settings</NavLink>}
    </nav>
    <div className="ph-sidebar-bottom"><div className="ph-store-card"><span className="ph-store-icon"><Store size={19} /></span><strong>Pharmacy</strong><p>Better health.<br />A brighter tomorrow.</p><Activity className="ph-heartline" size={45} strokeWidth={1} /></div>{user?.role !== 'Pharmacy User' && <NavLink to="/dashboard" onClick={onClose} className="ph-nav-link"><ArrowLeft size={16} />Clinic workspace</NavLink>}<button className="ph-nav-link" onClick={logout}><LogOut size={16} />Sign out</button></div>
  </aside>;
}

export function PharmacyTopbar({ onMenuToggle }) {
  const { user } = useAuth();
  const navigate = useNavigate();
  const { pathname } = useLocation();
  const [alertCount, setAlertCount] = useState(null);
  useEffect(() => {
    let active = true;
    const load = () => api.get('/pharmacy/alerts', {params:{days:30}}).then(({data}) => {
      if (active) setAlertCount(new Set([...data.low_stock, ...data.expired, ...data.expiring].map(m => m.id)).size);
    }).catch(() => { if (active) setAlertCount(null); });
    load(); const timer = setInterval(load, 60000);
    return () => { active = false; clearInterval(timer); };
  }, [pathname]);
  const [query, setQuery] = useState('');
  const [now, setNow] = useState(new Date());
  const input = useRef(null);
  useEffect(() => {
    const timer = setInterval(() => setNow(new Date()), 60000);
    const shortcut = e => { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); input.current?.focus(); } };
    window.addEventListener('keydown', shortcut);
    return () => { clearInterval(timer); window.removeEventListener('keydown', shortcut); };
  }, []);
  return <header className="ph-topbar"><button aria-label="Open menu" className="lg:hidden" onClick={onMenuToggle}><Menu size={21} /></button><form className="ph-global-search" onSubmit={e => { e.preventDefault(); navigate(`/pharmacy/pos-sales?q=${encodeURIComponent(query)}`); }}><Search size={16} /><input ref={input} aria-label="Search pharmacy products" value={query} onChange={e => setQuery(e.target.value)} placeholder="Search for products, medicine or scan barcode…" /><kbd>Ctrl + K</kbd></form><div className="ph-date"><CalendarDays size={17} /><span>{now.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}<small>{now.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })}</small></span></div><button className="ph-alert-button" aria-label={`Stock and expiry alerts${alertCount === null ? '' : `: ${alertCount}`}`} onClick={() => navigate('/pharmacy/alerts')}><Bell size={21} />{alertCount > 0 && <span>{alertCount > 99 ? '99+' : alertCount}</span>}</button><button className="ph-user" onClick={() => navigate('/profile')}><span className="ph-avatar">{user?.profile_photo_url ? <img src={user.profile_photo_url} alt="" /> : initials(user?.full_name)}</span><span><strong>{user?.full_name ?? 'Pharmacy'}</strong><small>{user?.role}</small></span><ChevronDown size={14} /></button></header>;
}
