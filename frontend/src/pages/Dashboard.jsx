import { useEffect, useState } from 'react';
import { useAuth } from '../context/AuthContext';
import api from '../api';
import MetricCard from '../components/ui/MetricCard';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import { initials, money } from '../utils/formatters';
import {
  AlertTriangle, BarChart2, Calendar, CalendarCheck, CreditCard,
  Pill, Users, UserCheck, Stethoscope, Activity, TrendingUp, Wallet,
} from 'lucide-react';

const ICON_MAP = {
  total_users:            Users,
  total_doctors:          Stethoscope,
  total_patients:         UserCheck,
  assigned_patients:      UserCheck,
  total_appointments:     Calendar,
  today_appointments:     Calendar,
  today_consultations:    CalendarCheck,
  upcoming_appointments:  Calendar,
  total_medicines:        Pill,
  low_stock_items:        AlertTriangle,
  expired_medicines:      AlertTriangle,
  stock_value:            Wallet,
  outstanding_balances:   Wallet,
  unpaid_appointments:    CreditCard,
  pharmacy_revenue_today: CreditCard,
  total_inventory_items:  BarChart2,
  revenue_today:          CreditCard,
  payments_collected:     Wallet,
  pharmacy_sales_today:   TrendingUp,
  pending_prescriptions:  Activity,
  expenses_month:         Wallet,
  total_treatments:       Activity,
};

function ReceptionStat({ value, label, tone = '#2563eb' }) {
  return (
    <div className="min-w-[135px]">
      <p className="text-2xl font-semibold leading-none" style={{ color: tone }}>{value}</p>
      <p className="mt-2 text-[11px]" style={{ color: '#8a94a6' }}>{label}</p>
    </div>
  );
}

export default function Dashboard() {
  const { user, lookups } = useAuth();
  const [metrics, setMetrics] = useState({});
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/dashboard')
      .then(({ data }) => setMetrics(data))
      .catch(() => {})
      .finally(() => setLoading(false));
  }, []);

  const cards = Object.entries(metrics)
    .filter(([, val]) => val !== undefined)
    .map(([key, val]) => {
      const label = key.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
      const value = typeof val === 'number' &&
        (key.includes('revenue') || key.includes('payment') || key.includes('expenses') || key.includes('balance') || key.includes('value'))
        ? money(val) : val;
      return { label, value, icon: ICON_MAP[key] };
    });

  const recentPatients = (lookups?.patients ?? []).slice(0, 8);

  if (user?.role === 'Receptionist') {
    const receptionStats = [
      { key: 'total_patients', label: 'Total Patients', tone: '#3b72cf' },
      { key: 'today_appointments', label: 'Today Appointments', tone: '#14a59a' },
      { key: 'upcoming_appointments', label: 'Upcoming Appointments', tone: '#c34b7a' },
      { key: 'payments_collected', label: 'Payments Collected', tone: '#aa2f2f', money: true },
      { key: 'unpaid_appointments', label: 'Unpaid Appointments', tone: '#4f7fd8' },
    ];

    return (
      <div className="min-h-full animate-fade-in">
        <div className="rounded-none lg:rounded-sm bg-white px-4 py-5 sm:px-7 sm:py-7 shadow-[0_18px_60px_rgba(114,105,160,0.08)]">
          <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 className="text-xl font-semibold tracking-wide text-[#101828]">Reception Overview</h1>
              <p className="mt-1 text-xs text-[#8a94a6]">
                Welcome back, <span className="font-semibold text-[#3b72cf]">{user?.full_name}</span>. Today's front desk activity is below.
              </p>
            </div>
            <div className="inline-flex items-center gap-2 rounded-full border border-[#eef2f8] bg-white px-4 py-2 text-xs font-semibold text-[#667085]">
              <Calendar size={13} className="text-[#3b72cf]" />
              {new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' })}
            </div>
          </div>

          <div className="mt-7 rounded-sm bg-white px-4 py-5 shadow-[0_18px_45px_rgba(15,23,42,0.055)]">
            {loading ? (
              <LoadingSpinner text="Loading metrics..." />
            ) : (
              <div className="grid grid-cols-2 gap-5 md:grid-cols-5">
                {receptionStats.map((stat) => (
                  <ReceptionStat
                    key={stat.key}
                    value={stat.money ? money(metrics[stat.key] ?? 0) : (metrics[stat.key] ?? 0)}
                    label={stat.label}
                    tone={stat.tone}
                  />
                ))}
              </div>
            )}
          </div>

          <div className="mt-7 overflow-hidden rounded-sm bg-white shadow-[0_18px_45px_rgba(15,23,42,0.045)]">
            <div className="flex items-center justify-between border-b border-[#edf1f7] px-4 py-4">
              <div>
                <h2 className="text-sm font-semibold text-[#1f2937]">Recent Patients</h2>
                <p className="mt-1 text-[11px] text-[#8a94a6]">{recentPatients.length} latest records</p>
              </div>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full min-w-[680px] border-collapse text-left">
                <thead>
                  <tr className="border-b border-[#edf1f7] text-[11px] font-semibold text-[#5f6b7a]">
                    <th className="px-4 py-3">Patient Name</th>
                    <th className="px-4 py-3">Phone</th>
                    <th className="px-4 py-3">Gender</th>
                    <th className="px-4 py-3">Age</th>
                    <th className="px-4 py-3">Registered</th>
                  </tr>
                </thead>
                <tbody>
                  {recentPatients.map((patient) => (
                    <tr key={patient.id} className="border-b border-[#f1f4f8] text-xs text-[#344054] hover:bg-[#fbfcff]">
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2.5">
                          <span className="grid h-6 w-6 place-items-center rounded-full bg-[#edf5ff] text-[9px] font-bold text-[#4c7fd1]">
                            {initials(patient.full_name)}
                          </span>
                          <span className="font-medium text-[#1f2937]">{patient.full_name}</span>
                        </div>
                      </td>
                      <td className="px-4 py-3">{patient.phone ?? '-'}</td>
                      <td className="px-4 py-3">{patient.gender ?? '-'}</td>
                      <td className="px-4 py-3">{patient.age ?? '-'}</td>
                      <td className="px-4 py-3">{patient.created_at ? new Date(patient.created_at).toLocaleDateString() : '-'}</td>
                    </tr>
                  ))}
                  {recentPatients.length === 0 && (
                    <tr>
                      <td colSpan="5" className="px-4 py-10 text-center text-sm text-[#8a94a6]">
                        No patients found.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-5 animate-fade-in">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold" style={{ color: 'var(--clr-text)' }}>Dashboard</h1>
          <p className="mt-1 text-xs" style={{ color: 'var(--clr-muted)' }}>
            Welcome back,{' '}
            <span style={{ color: '#7c3aed' }}>{user?.full_name}</span>
            . Here's your overview.
          </p>
        </div>
        <div
          className="flex items-center gap-2 px-3 py-2 rounded-lg text-xs"
          style={{
            background: 'var(--clr-card)',
            border: '1px solid var(--clr-border)',
            color: 'var(--clr-muted)',
          }}
        >
          <Calendar size={13} className="text-violet-600" />
          {new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}
        </div>
      </div>

      {/* Metrics */}
      {loading ? (
        <LoadingSpinner text="Loading metrics…" />
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
          {cards.map(({ label, value, icon }, i) => (
            <MetricCard key={label} label={label} value={value} icon={icon} index={i} />
          ))}
          {cards.length === 0 && (
            <div className="col-span-full py-10 text-center text-sm" style={{ color: 'var(--clr-muted)' }}>
              No metrics available for your role.
            </div>
          )}
        </div>
      )}

      {/* Bottom panels */}
      <div className="grid grid-cols-1 lg:grid-cols-5 gap-5">

        {/* Recent Patients */}
        <div
          className="lg:col-span-3 rounded-xl overflow-hidden"
          style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}
        >
          <div
            className="flex items-center justify-between px-5 py-4"
            style={{ borderBottom: '1px solid var(--clr-border)' }}
          >
            <div>
              <h2 className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>Recent Patients</h2>
              <p className="text-xs mt-0.5" style={{ color: 'var(--clr-muted)' }}>
                {recentPatients.length} patients
              </p>
            </div>
          </div>
          <div>
            {recentPatients.length === 0 && (
              <p className="px-5 py-8 text-center text-sm" style={{ color: 'var(--clr-muted)' }}>
                No patients found.
              </p>
            )}
            {recentPatients.map((patient) => (
              <div
                key={patient.id}
                className="flex items-center gap-3 px-5 py-3 transition-colors cursor-default"
                style={{ borderBottom: '1px solid var(--clr-border)' }}
                onMouseEnter={(e) => { e.currentTarget.style.background = 'var(--clr-hover)'; }}
                onMouseLeave={(e) => { e.currentTarget.style.background = 'transparent'; }}
              >
                <div className="w-8 h-8 rounded-full bg-green-500 flex items-center justify-center shrink-0">
                  <span className="text-[#ffffff] text-xs font-bold">{initials(patient.full_name)}</span>
                </div>
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-medium truncate" style={{ color: 'var(--clr-text)' }}>
                    {patient.full_name}
                  </p>
                  <p className="text-xs truncate" style={{ color: 'var(--clr-muted)' }}>
                    {patient.phone ?? '—'}
                  </p>
                </div>
                <span className="w-1.5 h-1.5 rounded-full bg-green-500 shrink-0" />
              </div>
            ))}
          </div>
        </div>

        {/* System Access */}
        <div
          className="lg:col-span-2 rounded-xl p-6 relative overflow-hidden"
          style={{
            background: '#111912',
            border: '1px solid rgba(124,58,237,.2)',
          }}
        >
          {/* Glow */}
          <div
            className="absolute top-0 right-0 w-40 h-40 pointer-events-none"
            style={{
              background: 'radial-gradient(circle at top right, rgba(124,58,237,.12), transparent 70%)',
            }}
          />
          <div className="relative">
            <div
              className="w-10 h-10 rounded-xl mb-4 flex items-center justify-center"
              style={{ background: 'rgba(124,58,237,.12)', border: '1px solid rgba(124,58,237,.2)' }}
            >
              <Activity size={18} className="text-violet-600" />
            </div>
            <h2 className="text-base font-bold text-white">System Access</h2>
            <p className="mt-2 text-xs leading-relaxed" style={{ color: '#6b8f6e' }}>
              Your{' '}
              <span className="text-green-400 font-semibold">{user?.role}</span>{' '}
              account has full access to{' '}
              <span className="text-white font-bold text-lg">
                {lookups ? Object.keys(lookups).length : 0}
              </span>{' '}
              module sections with role-protected APIs.
            </p>
            <div className="mt-4 flex items-center gap-2">
              <span className="w-2 h-2 rounded-full bg-green-500 animate-pulse" />
              <span className="text-xs text-violet-600 font-medium">System Online</span>
            </div>

            {/* Green bar indicator */}
            <div
              className="mt-4 h-1 rounded-full"
              style={{ background: 'rgba(124,58,237,.12)' }}
            >
              <div
                className="h-full rounded-full bg-green-500 transition-all duration-1000"
                style={{ width: '70%' }}
              />
            </div>
          </div>
        </div>

      </div>
    </div>
  );
}
