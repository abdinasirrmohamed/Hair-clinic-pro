import { NavLink, useLocation } from 'react-router-dom';
import { useAuth } from '../../context/AuthContext';
import { initials } from '../../utils/formatters';
import {
  Activity, Archive, BarChart2, Calendar, CalendarCheck, ClipboardList,
  CreditCard, FileText, LayoutDashboard, LogOut, Pill, Scissors,
  Shield, ShoppingCart, Stethoscope, UserCheck, Users, Wallet, History, Settings, FlaskConical,
} from 'lucide-react';

/* ─── Nav groups & items ─── */
const NAV_GROUPS = [
  {
    label: 'OVERVIEW',
    items: [
      { path: '/dashboard', module: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
    ],
  },
  {
    label: 'CLINICAL',
    items: [
      { path: '/patients',            module: 'patients',            label: 'Patients',        icon: UserCheck },
      { path: '/doctors',             module: 'doctors',             label: 'Doctors',          icon: Stethoscope },
      { path: '/appointments',        module: 'appointments',        label: 'Appointments',     icon: Calendar },
      { path: '/doctor-appointments', module: 'doctor_appointments', label: 'My Appointments',  icon: CalendarCheck },
      { path: '/treatments',          module: 'treatments',          label: 'Treatments',       icon: Scissors },
      { path: '/followups',           module: 'followups',           label: 'Follow-Ups',       icon: ClipboardList },
      { path: '/prescriptions',       module: 'prescriptions',       label: 'Prescriptions',    icon: FileText },
    ],
  },
  {
    label: 'FINANCE',
    items: [
      { path: '/payments', module: 'payments', label: 'Payments', icon: CreditCard },
      { path: '/finance',  module: 'finance',  label: 'Finance',  icon: Wallet },
    ],
  },
  {
    label: 'INVENTORY',
    items: [
      { path: '/inventory', module: 'inventory', label: 'Inventory', icon: Archive },
      { path: '/pharmacy',  module: 'pharmacy',  label: 'Pharmacy',  icon: Pill },
      { path: '/laboratory', module: 'laboratory', label: 'Laboratory', icon: FlaskConical },
    ],
  },
  {
    label: 'ANALYTICS',
    items: [
      { path: '/reports',    module: 'reports',    label: 'Reports',    icon: BarChart2 },
      { path: '/audit-logs', module: 'audit_logs', label: 'Audit Logs', icon: Shield },
    ],
  },
  {
    label: 'SYSTEM',
    items: [
      { path: '/users', module: 'users', label: 'Users', icon: Users },
      { path: '/settings', module: 'settings', label: 'Settings', icon: Settings },
    ],
  },
];

const PHARMACY_ITEMS = [
  { path: '/pharmacy/dashboard', label: 'Dashboard', icon: BarChart2 },
  { path: '/pharmacy/medicines', label: 'Medicines', icon: Pill },
  { path: '/pharmacy/pos-sales', label: 'POS Sales', icon: ShoppingCart },
  { path: '/pharmacy/prescription-sales', label: 'Prescription Sales', icon: FileText },
  { path: '/pharmacy/sales-history', label: 'Sales History', icon: History },
  { path: '/pharmacy/reports', label: 'Reports', icon: Calendar },
];

export default function Sidebar({ onClose }) {
  const { user, permissions, logout } = useAuth();
  const location = useLocation();
  const isPharmacy = location.pathname.startsWith('/pharmacy');
  const avatarUrl = user?.profile_photo_url;

  const handleLogout = () => logout();

  return (
    <aside
      className="w-60 shrink-0 h-full flex flex-col"
      style={{
        background: 'var(--clr-sidebar)',
        borderRight: '1px solid var(--clr-sidebar-border)',
      }}
    >
      {/* ── Brand ── */}
      <div
        className="flex items-center gap-2.5 px-4 py-4"
        style={{ borderBottom: '1px solid var(--clr-border)' }}
      >
        <div className="w-8 h-8 rounded-lg bg-green-500 flex items-center justify-center shrink-0">
          <Activity size={16} className="text-[#052e10]" />
        </div>
        <div className="min-w-0">
          <p className="text-sm font-bold truncate" style={{ color: 'var(--clr-text)' }}>
            Hair Clinic Pro
          </p>
        </div>
      </div>

      {/* ── Navigation ── */}
      {isPharmacy ? (
        <nav className="flex-1 overflow-y-auto py-3 space-y-0.5 bg-white">
          <div className="px-4 pt-1 pb-4">
            <p className="text-xs font-medium" style={{ color: '#166534' }}>Pharmacy</p>
            <h1 className="text-xl font-bold leading-tight text-black">Workspace</h1>
          </div>

          {PHARMACY_ITEMS.map(({ path, label, icon: Icon }) => (
            <NavLink
              key={path}
              to={path}
              onClick={onClose}
              className="flex items-center gap-3 px-4 py-2.5 text-sm font-semibold transition-colors"
              style={({ isActive }) => ({
                background: isActive ? '#dcfce7' : 'transparent',
                color: isActive ? '#16a34a' : '#2f6b43',
              })}
            >
              <Icon size={16} />
              <span>{label}</span>
            </NavLink>
          ))}

          <button
            onClick={handleLogout}
            className="flex items-center gap-3 w-full px-4 py-2.5 text-sm font-semibold transition-colors text-left"
            style={{ color: '#ef4444' }}
          >
            <LogOut size={16} />
            <span>Logout</span>
          </button>
        </nav>
      ) : (
        <nav className="flex-1 overflow-y-auto px-2 py-3 space-y-0.5">
          {NAV_GROUPS.map(({ label, items }) => {
          const visible = items.filter((it) => permissions.includes(it.module));
          if (!visible.length) return null;
          return (
            <div key={label} className="mb-1">
              {/* Group label */}
              <p
                className="px-2 pt-3 pb-1 text-[10px] font-semibold tracking-widest select-none"
                style={{ color: 'var(--clr-section)' }}
              >
                {label}
              </p>
              {/* Items */}
              {visible.map(({ path, module, label: itemLabel, icon: Icon }) => (
                <NavLink
                  key={module}
                  to={path}
                  onClick={onClose}
                  className={({ isActive }) =>
                    `flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium transition-all ${
                      isActive
                        ? 'bg-green-500'
                        : ''
                    }`
                  }
                  style={({ isActive }) =>
                    isActive
                      ? { color: '#052e10' }
                      : { color: 'var(--clr-muted)' }
                  }
                >
                  {({ isActive }) => (
                    <>
                      <Icon
                        size={15}
                        style={{ color: isActive ? '#052e10' : 'var(--clr-muted)', flexShrink: 0 }}
                      />
                      <span>{itemLabel}</span>
                    </>
                  )}
                </NavLink>
              ))}
            </div>
          );
          })}
        </nav>
      )}

      {/* ── Profile + Logout ── */}
      {!isPharmacy && <div
        className="px-2 py-3 space-y-0.5"
        style={{ borderTop: '1px solid var(--clr-border)' }}
      >
        <NavLink
          to="/profile"
          onClick={onClose}
          className={({ isActive }) =>
            `flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm font-medium transition-all ${
              isActive ? 'bg-green-500' : ''
            }`
          }
          style={({ isActive }) =>
            isActive ? { color: '#052e10' } : { color: 'var(--clr-muted)' }
          }
        >
          {({ isActive }) => (
            <>
              <div
                className="w-7 h-7 rounded-full bg-green-500 flex items-center justify-center shrink-0 overflow-hidden"
              >
                {avatarUrl ? (
                  <img src={avatarUrl} alt={user?.full_name ?? 'Profile'} className="w-full h-full object-cover" />
                ) : (
                  <span className="text-[10px] font-bold text-[#052e10]">
                    {initials(user?.full_name)}
                  </span>
                )}
              </div>
              <div className="min-w-0 flex-1">
                <p
                  className="text-xs font-semibold truncate"
                  style={{ color: isActive ? '#052e10' : 'var(--clr-text)' }}
                >
                  {user?.full_name}
                </p>
                <p
                  className="text-[10px] truncate"
                  style={{ color: isActive ? '#052e10cc' : 'var(--clr-section)' }}
                >
                  {user?.role}
                </p>
              </div>
            </>
          )}
        </NavLink>

        <button
          onClick={handleLogout}
          className="flex items-center gap-2.5 w-full px-3 py-2 rounded-lg text-sm font-medium transition-all"
          style={{ color: 'var(--clr-muted)' }}
          onMouseEnter={(e) => {
            e.currentTarget.style.color = '#f87171';
            e.currentTarget.style.background = 'rgba(248,113,113,0.08)';
          }}
          onMouseLeave={(e) => {
            e.currentTarget.style.color = 'var(--clr-muted)';
            e.currentTarget.style.background = 'transparent';
          }}
        >
          <LogOut size={15} />
          <span>Sign Out</span>
        </button>
      </div>}
    </aside>
  );
}
