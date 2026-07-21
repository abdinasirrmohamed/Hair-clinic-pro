import { useEffect, useState } from 'react';
import { AlertTriangle, Bell, CalendarCheck, CreditCard, Pill, RefreshCw } from 'lucide-react';
import api from '../api';
import Alert from '../components/ui/Alert';
import LoadingSpinner from '../components/ui/LoadingSpinner';

const iconMap = {
  Inventory: Pill,
  Pharmacy: Pill,
  Appointments: CalendarCheck,
  Payments: CreditCard,
};

const colorMap = {
  danger: '#ef4444',
  warning: '#f59e0b',
  info: '#7c3aed',
};

export default function Notifications() {
  const [data, setData] = useState({ count: 0, items: [] });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = () => {
    setLoading(true);
    setError('');
    api.get('/notifications')
      .then(({ data }) => setData(data))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  };

  useEffect(load, []);

  return (
    <div className="space-y-5 animate-fade-in">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold" style={{ color: 'var(--clr-text)' }}>Notifications</h1>
          <p className="mt-1 text-xs" style={{ color: 'var(--clr-muted)' }}>
            Low stock, expired medicines, today appointments, unpaid balances, and pending prescriptions.
          </p>
        </div>
        <button onClick={load} className="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold" style={{ color: 'var(--clr-muted)', border: '1px solid var(--clr-border)' }}>
          <RefreshCw size={13} /> Refresh
        </button>
      </div>

      {error && <Alert message={error} />}

      {loading ? <LoadingSpinner text="Loading notifications..." /> : (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          {data.items.length === 0 && (
            <div className="md:col-span-2 xl:col-span-3 rounded-xl p-8 text-center" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)', color: 'var(--clr-muted)' }}>
              No active notifications.
            </div>
          )}
          {data.items.map((item, index) => {
            const Icon = iconMap[item.module] ?? AlertTriangle;
            const color = colorMap[item.severity] ?? '#7c3aed';
            return (
              <div key={`${item.type}-${index}`} className="rounded-xl p-4" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
                <div className="flex items-start gap-3">
                  <div className="w-9 h-9 rounded-lg flex items-center justify-center shrink-0" style={{ background: `${color}18`, color }}>
                    <Icon size={17} />
                  </div>
                  <div className="min-w-0">
                    <p className="text-[11px] font-bold uppercase tracking-widest" style={{ color }}>{item.type}</p>
                    <h2 className="text-sm font-bold mt-1" style={{ color: 'var(--clr-text)' }}>{item.title}</h2>
                    <p className="text-xs mt-1" style={{ color: 'var(--clr-muted)' }}>{item.message}</p>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
