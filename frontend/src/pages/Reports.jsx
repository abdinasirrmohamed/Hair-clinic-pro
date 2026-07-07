import { useEffect, useMemo, useState } from 'react';
import api from '../api';
import MetricCard from '../components/ui/MetricCard';
import Alert from '../components/ui/Alert';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import { money } from '../utils/formatters';
import { BarChart2, Calendar, Download, Filter, RotateCcw } from 'lucide-react';
import { useAuth } from '../context/AuthContext';

const PERIODS = [
  { key: 'daily', label: 'Today' },
  { key: 'weekly', label: 'This Week' },
  { key: 'monthly', label: 'This Month' },
  { key: 'custom', label: 'Custom' },
];

const REPORT_TYPES = [
  { key: 'overview', label: 'Overview' },
  { key: 'users', label: 'User Reports' },
  { key: 'finance', label: 'Finance' },
  { key: 'pharmacy', label: 'Pharmacy' },
  { key: 'appointments', label: 'Appointments' },
  { key: 'doctor_performance', label: 'Doctor Performance' },
  { key: 'inventory', label: 'Inventory Audit' },
  { key: 'activity', label: 'Activity Logs' },
];

const ROLES = ['Administrator', 'Receptionist', 'Doctor', 'Inventory Officer', 'Pharmacy User'];
const PAYMENT_METHODS = ['Cash', 'Card', 'EVC Plus', 'Zaad', 'Sahal', 'Bank Transfer', 'Mixed Payment'];
const STATUSES = ['Paid', 'Partial', 'Pending', 'Completed', 'Cancelled', 'Returned', 'Dispensed'];

const today = () => new Date().toISOString().slice(0, 10);
const firstDayOfMonth = () => {
  const date = new Date();
  return new Date(date.getFullYear(), date.getMonth(), 1).toISOString().slice(0, 10);
};

function DataPanel({ title, columns, rows, empty = 'No records found.' }) {
  return (
    <div className="rounded-xl overflow-hidden" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
      <div className="px-4 py-3 flex items-center justify-between" style={{ borderBottom: '1px solid var(--clr-border)' }}>
        <h2 className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>{title}</h2>
        <span className="text-[11px]" style={{ color: 'var(--clr-muted)' }}>{rows.length} rows</span>
      </div>
      <div className="overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr style={{ background: 'var(--clr-search-bg)' }}>
              {columns.map((column) => (
                <th key={column} className="px-4 py-3 text-left text-[11px] uppercase font-bold" style={{ color: 'var(--clr-section)' }}>
                  {column}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.length ? rows.map((row, index) => (
              <tr key={index} style={{ borderTop: '1px solid var(--clr-border)' }}>
                {row.map((cell, cellIndex) => (
                  <td key={`${index}-${cellIndex}`} className="px-4 py-3 whitespace-nowrap" style={{ color: 'var(--clr-text)' }}>
                    {cell ?? '-'}
                  </td>
                ))}
              </tr>
            )) : (
              <tr>
                <td colSpan={columns.length} className="px-4 py-8 text-center text-sm" style={{ color: 'var(--clr-muted)' }}>
                  {empty}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export default function Reports() {
  const { lookups } = useAuth();
  const [filters, setFilters] = useState({
    period: 'monthly',
    from: firstDayOfMonth(),
    to: today(),
    report_type: 'overview',
    user_id: '',
    role: '',
    doctor_id: '',
    payment_method: '',
    status: '',
  });
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [exporting, setExporting] = useState(false);

  const queryParams = useMemo(() => {
    const params = {};
    Object.entries(filters).forEach(([key, value]) => {
      if (value) params[key] = value;
    });
    return params;
  }, [filters]);

  useEffect(() => {
    setLoading(true);
    setError('');
    api.get('/reports', { params: queryParams })
      .then(({ data }) => setData(data))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [queryParams]);

  const setFilter = (key, value) => {
    setFilters((current) => ({ ...current, [key]: value }));
  };

  const setPeriod = (period) => {
    const to = today();
    const date = new Date();
    const fromDate = new Date();

    if (period === 'daily') fromDate.setDate(date.getDate());
    if (period === 'weekly') fromDate.setDate(date.getDate() - 6);
    if (period === 'monthly') fromDate.setDate(1);

    setFilters((current) => ({
      ...current,
      period,
      ...(period === 'custom' ? {} : { from: fromDate.toISOString().slice(0, 10), to }),
    }));
  };

  const resetFilters = () => {
    setFilters({
      period: 'monthly',
      from: firstDayOfMonth(),
      to: today(),
      report_type: 'overview',
      user_id: '',
      role: '',
      doctor_id: '',
      payment_method: '',
      status: '',
    });
  };

  const exportCsv = async () => {
    setExporting(true);
    try {
      const response = await api.get('/reports/export', { params: queryParams, responseType: 'blob' });
      const url = URL.createObjectURL(response.data);
      const link = document.createElement('a');
      link.href = url;
      link.download = `reports-${filters.from}-to-${filters.to}.csv`;
      link.click();
      URL.revokeObjectURL(url);
    } catch (err) {
      setError(err.message);
    } finally {
      setExporting(false);
    }
  };

  const summaryCards = data
    ? Object.entries(data.summary).map(([key, val], i) => {
        const label = key.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
        const isMoney = key.includes('revenue') || key.includes('expenses') || key.includes('profit');
        return { label, value: isMoney ? money(val) : val, index: i };
      })
    : [];

  const apptByStatus = data?.appointments_by_status ?? [];
  const maxTotal = Math.max(...apptByStatus.map((row) => row.total), 1);
  const users = lookups?.users ?? [];
  const doctors = lookups?.doctors ?? [];

  const inputStyle = {
    width: '100%',
    border: '1px solid var(--clr-border)',
    background: 'var(--clr-search-bg)',
    color: 'var(--clr-text)',
    borderRadius: '.6rem',
    padding: '.65rem .75rem',
    fontSize: '.8125rem',
    outline: 'none',
  };

  const labelStyle = { color: 'var(--clr-section)' };
  const tabStyle = (active) => ({
    padding: '.4rem .875rem',
    borderRadius: '.5rem',
    fontSize: '.8125rem',
    fontWeight: 600,
    cursor: 'pointer',
    border: 'none',
    transition: 'all .15s',
    background: active ? 'var(--clr-card)' : 'transparent',
    color: active ? '#22c55e' : 'var(--clr-muted)',
    boxShadow: active ? '0 1px 3px rgba(0,0,0,0.15)' : 'none',
  });

  return (
    <div className="space-y-5 animate-fade-in">
      <div className="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold" style={{ color: 'var(--clr-text)' }}>Reports</h1>
          <p className="mt-1 text-xs" style={{ color: 'var(--clr-muted)' }}>
            Admin reports with date, user, role, doctor, payment, and status filters.
          </p>
        </div>

        <div className="flex gap-1 p-1 rounded-xl" style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)' }}>
          {PERIODS.map(({ key, label }) => (
            <button key={key} onClick={() => setPeriod(key)} style={tabStyle(filters.period === key)}>
              {label}
            </button>
          ))}
        </div>
      </div>

      <div className="rounded-xl p-4 space-y-4" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
        <div className="flex items-center justify-between gap-3">
          <div className="flex items-center gap-2">
            <Filter size={15} className="text-green-500" />
            <h2 className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>Report Filters</h2>
          </div>
          <div className="flex gap-2">
            <button onClick={resetFilters} className="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold" style={{ color: 'var(--clr-muted)', border: '1px solid var(--clr-border)' }}>
              <RotateCcw size={13} /> Reset
            </button>
            <button onClick={exportCsv} disabled={exporting} className="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold disabled:opacity-60" style={{ background: 'var(--clr-accent-soft)', color: '#22c55e', border: '1px solid rgba(34,197,94,.2)' }}>
              <Download size={13} /> {exporting ? 'Exporting...' : 'Export CSV'}
            </button>
          </div>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <label className="space-y-1">
            <span className="text-[10px] font-bold uppercase tracking-widest" style={labelStyle}>Report Type</span>
            <select style={inputStyle} value={filters.report_type} onChange={(e) => setFilter('report_type', e.target.value)}>
              {REPORT_TYPES.map((type) => <option key={type.key} value={type.key}>{type.label}</option>)}
            </select>
          </label>
          <label className="space-y-1">
            <span className="text-[10px] font-bold uppercase tracking-widest" style={labelStyle}>From Date</span>
            <input type="date" style={inputStyle} value={filters.from} onChange={(e) => setFilter('from', e.target.value)} />
          </label>
          <label className="space-y-1">
            <span className="text-[10px] font-bold uppercase tracking-widest" style={labelStyle}>To Date</span>
            <input type="date" style={inputStyle} value={filters.to} onChange={(e) => setFilter('to', e.target.value)} />
          </label>
          <label className="space-y-1">
            <span className="text-[10px] font-bold uppercase tracking-widest" style={labelStyle}>User</span>
            <select style={inputStyle} value={filters.user_id} onChange={(e) => setFilter('user_id', e.target.value)}>
              <option value="">All users</option>
              {users.map((user) => <option key={user.id} value={user.id}>{user.full_name} ({user.role})</option>)}
            </select>
          </label>
          <label className="space-y-1">
            <span className="text-[10px] font-bold uppercase tracking-widest" style={labelStyle}>Role</span>
            <select style={inputStyle} value={filters.role} onChange={(e) => setFilter('role', e.target.value)}>
              <option value="">All roles</option>
              {ROLES.map((role) => <option key={role} value={role}>{role}</option>)}
            </select>
          </label>
          <label className="space-y-1">
            <span className="text-[10px] font-bold uppercase tracking-widest" style={labelStyle}>Doctor</span>
            <select style={inputStyle} value={filters.doctor_id} onChange={(e) => setFilter('doctor_id', e.target.value)}>
              <option value="">All doctors</option>
              {doctors.map((doctor) => <option key={doctor.id} value={doctor.id}>{doctor.full_name}</option>)}
            </select>
          </label>
          <label className="space-y-1">
            <span className="text-[10px] font-bold uppercase tracking-widest" style={labelStyle}>Payment Method</span>
            <select style={inputStyle} value={filters.payment_method} onChange={(e) => setFilter('payment_method', e.target.value)}>
              <option value="">All methods</option>
              {PAYMENT_METHODS.map((method) => <option key={method} value={method}>{method}</option>)}
            </select>
          </label>
          <label className="space-y-1">
            <span className="text-[10px] font-bold uppercase tracking-widest" style={labelStyle}>Status</span>
            <select style={inputStyle} value={filters.status} onChange={(e) => setFilter('status', e.target.value)}>
              <option value="">All statuses</option>
              {STATUSES.map((status) => <option key={status} value={status}>{status}</option>)}
            </select>
          </label>
        </div>
      </div>

      {error && <Alert message={error} />}

      {loading ? (
        <LoadingSpinner text="Loading reports..." />
      ) : (
        <>
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            {summaryCards.map(({ label, value, index }) => (
              <MetricCard key={label} label={label} value={value} index={index} />
            ))}
          </div>

          {(filters.report_type === 'overview' || filters.report_type === 'users') && (
            <DataPanel
              title="User Performance"
              columns={['User', 'Role', 'Clinic Revenue', 'Pharmacy Revenue', 'Expenses', 'Payments', 'Sales', 'Activities']}
              rows={(data?.user_totals ?? []).map((row) => [
                row.name,
                row.role,
                money(row.clinic_revenue),
                money(row.pharmacy_revenue),
                money(row.expenses),
                row.payments_count,
                row.sales_count,
                row.activities,
              ])}
            />
          )}

          {(filters.report_type === 'overview' || filters.report_type === 'finance') && (
            <DataPanel
              title="Clinic Payments"
              columns={['Reference', 'Patient', 'Amount', 'Method', 'Status', 'User', 'Date']}
              rows={(data?.clinic_payments ?? []).map((row) => [
                row.reference_number,
                row.patient?.full_name,
                money(row.amount),
                row.payment_method,
                row.payment_status,
                row.creator?.full_name,
                row.created_at?.slice(0, 10),
              ])}
            />
          )}

          {(filters.report_type === 'overview' || filters.report_type === 'pharmacy') && (
            <DataPanel
              title="Pharmacy Sales"
              columns={['Sale No', 'Customer', 'Total', 'Method', 'Payment', 'Status', 'Cashier', 'Date']}
              rows={(data?.pharmacy_sales ?? []).map((row) => [
                row.sale_number,
                row.customer_name || row.patient?.full_name || 'Walk-in',
                money(row.total_amount),
                row.payment_method,
                row.payment_status,
                row.status,
                row.creator?.full_name,
                row.created_at?.slice(0, 10),
              ])}
            />
          )}

          {(filters.report_type === 'overview' || filters.report_type === 'appointments') && (
            <>
              {apptByStatus.length > 0 && (
                <div className="rounded-xl p-6" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
                  <div className="flex items-center gap-2 mb-6">
                    <BarChart2 size={16} className="text-green-500" />
                    <h2 className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>Appointment Status Breakdown</h2>
                  </div>
                  <div className="space-y-4">
                    {apptByStatus.map((row) => (
                      <div key={row.status} className="flex items-center gap-4">
                        <span className="w-24 text-xs font-medium text-right shrink-0" style={{ color: 'var(--clr-muted)' }}>{row.status}</span>
                        <div className="flex-1 h-2 rounded-full overflow-hidden" style={{ background: 'var(--clr-hover)' }}>
                          <div className="h-full rounded-full transition-all duration-700" style={{ width: `${Math.min(100, (row.total / maxTotal) * 100)}%`, background: '#22c55e' }} />
                        </div>
                        <span className="w-7 text-sm font-bold shrink-0" style={{ color: 'var(--clr-text)' }}>{row.total}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}
              <DataPanel
                title="Appointments"
                columns={['Patient', 'Doctor', 'Date', 'Time', 'Status', 'Reason']}
                rows={(data?.appointments ?? []).map((row) => [
                  row.patient?.full_name,
                  row.doctor?.full_name,
                  row.appointment_date,
                  row.appointment_time,
                  row.status,
                  row.reason,
                ])}
              />
            </>
          )}

          {(filters.report_type === 'overview' || filters.report_type === 'doctor_performance') && (
            <DataPanel
              title="Doctor Performance"
              columns={['Doctor', 'Specialization', 'Appointments', 'Completed', 'Revenue']}
              rows={(data?.doctor_performance ?? []).map((row) => [
                row.doctor,
                row.specialization,
                row.appointments,
                row.completed,
                money(row.revenue),
              ])}
            />
          )}

          {(filters.report_type === 'overview' || filters.report_type === 'inventory') && (
            <DataPanel
              title="Inventory Audit Trail"
              columns={['Transaction', 'Medicine', 'Type', 'Qty', 'Old Qty', 'New Qty', 'User', 'Date']}
              rows={(data?.inventory_audit ?? []).map((row) => [
                row.transaction_number,
                row.medicine?.medicine_name,
                row.movement_type,
                row.quantity,
                row.old_quantity,
                row.new_quantity,
                row.user?.full_name,
                row.movement_date?.replace('T', ' ').slice(0, 16),
              ])}
            />
          )}

          {(filters.report_type === 'overview' || filters.report_type === 'activity') && (
            <DataPanel
              title="User Activity Logs"
              columns={['User', 'Role', 'Module', 'Action', 'IP', 'Date']}
              rows={(data?.user_activity ?? []).map((row) => [
                row.user_name,
                row.user_role,
                row.module_name,
                row.action,
                row.ip_address,
                row.created_at?.replace('T', ' ').slice(0, 16),
              ])}
            />
          )}

          {data?.range && (
            <div className="flex items-center gap-2 text-xs" style={{ color: 'var(--clr-muted)' }}>
              <Calendar size={12} className="text-green-500" />
              Showing from <strong style={{ color: 'var(--clr-text)' }}>{data.range.from}</strong> to <strong style={{ color: 'var(--clr-text)' }}>{data.range.to}</strong>
            </div>
          )}
        </>
      )}
    </div>
  );
}
