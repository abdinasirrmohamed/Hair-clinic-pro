import { useEffect, useState } from 'react';
import api from '../api';
import MetricCard from '../components/ui/MetricCard';
import Alert from '../components/ui/Alert';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import { money } from '../utils/formatters';
import { BarChart2, Calendar, Download } from 'lucide-react';

const PERIODS = [
  { key: 'daily',   label: 'Today' },
  { key: 'weekly',  label: 'This Week' },
  { key: 'monthly', label: 'This Month' },
];

export default function Reports() {
  const [period,  setPeriod]  = useState('monthly');
  const [data,    setData]    = useState(null);
  const [error,   setError]   = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    setError('');
    api.get(`/reports?period=${period}`)
      .then(({ data }) => setData(data))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [period]);

  const summaryCards = data
    ? Object.entries(data.summary).map(([key, val], i) => {
        const label = key.replaceAll('_', ' ').replace(/\b\w/g, (c) => c.toUpperCase());
        const isM   = key.includes('revenue') || key.includes('expenses') || key.includes('profit');
        return { label, value: isM ? money(val) : val, index: i };
      })
    : [];

  const apptByStatus = data?.appointments_by_status ?? [];
  const maxTotal = Math.max(...apptByStatus.map((r) => r.total), 1);

  /* Tab button style */
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
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold" style={{ color: 'var(--clr-text)' }}>Reports</h1>
          <p className="mt-1 text-xs" style={{ color: 'var(--clr-muted)' }}>
            Clinic analytics across patients, appointments, and revenue.
          </p>
        </div>

        {/* Period tabs */}
        <div
          className="flex gap-1 p-1 rounded-xl"
          style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)' }}
        >
          {PERIODS.map(({ key, label }) => (
            <button key={key} onClick={() => setPeriod(key)} style={tabStyle(period === key)}>
              {label}
            </button>
          ))}
        </div>
      </div>

      {/* Export link */}
      <div className="flex justify-end">
        <a
          href="/api/reports/export"
          target="_blank"
          className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all"
          style={{
            background: 'var(--clr-accent-soft)',
            color: '#22c55e',
            border: '1px solid rgba(34,197,94,.2)',
            textDecoration: 'none',
          }}
          onMouseEnter={(e) => { e.currentTarget.style.background = 'rgba(34,197,94,.18)'; }}
          onMouseLeave={(e) => { e.currentTarget.style.background = 'var(--clr-accent-soft)'; }}
        >
          <Download size={14} />
          Export CSV
        </a>
      </div>

      {error && <Alert message={error} />}

      {loading ? (
        <LoadingSpinner text="Loading reports…" />
      ) : (
        <>
          {/* Summary cards */}
          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            {summaryCards.map(({ label, value, index }) => (
              <MetricCard key={label} label={label} value={value} index={index} />
            ))}
          </div>

          {/* Appointment status chart */}
          {apptByStatus.length > 0 && (
            <div
              className="rounded-xl p-6"
              style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}
            >
              <div className="flex items-center gap-2 mb-6">
                <BarChart2 size={16} className="text-green-500" />
                <h2 className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>
                  Appointment Status Breakdown
                </h2>
              </div>
              <div className="space-y-4">
                {apptByStatus.map((row) => (
                  <div key={row.status} className="flex items-center gap-4">
                    <span className="w-24 text-xs font-medium text-right shrink-0" style={{ color: 'var(--clr-muted)' }}>
                      {row.status}
                    </span>
                    <div
                      className="flex-1 h-2 rounded-full overflow-hidden"
                      style={{ background: 'var(--clr-hover)' }}
                    >
                      <div
                        className="h-full rounded-full transition-all duration-700"
                        style={{
                          width: `${Math.min(100, (row.total / maxTotal) * 100)}%`,
                          background: '#22c55e',
                        }}
                      />
                    </div>
                    <span className="w-7 text-sm font-bold shrink-0" style={{ color: 'var(--clr-text)' }}>
                      {row.total}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Date range */}
          {data?.range && (
            <div className="flex items-center gap-2 text-xs" style={{ color: 'var(--clr-muted)' }}>
              <Calendar size={12} className="text-green-500" />
              Showing from{' '}
              <strong style={{ color: 'var(--clr-text)' }}>{data.range.from}</strong>
              {' '}to{' '}
              <strong style={{ color: 'var(--clr-text)' }}>{data.range.to}</strong>
            </div>
          )}
        </>
      )}
    </div>
  );
}
