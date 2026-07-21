import { useEffect, useState } from 'react';
import api, { asRows } from '../api';
import DataTable from '../components/ui/DataTable';
import Alert from '../components/ui/Alert';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import { Shield } from 'lucide-react';

export default function AuditLogs() {
  const [rows,    setRows]    = useState([]);
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState('');

  useEffect(() => {
    api.get('/audit-logs')
      .then(({ data }) => setRows(asRows(data)))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, []);

  return (
    <div className="space-y-5 animate-fade-in">
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-xl font-bold" style={{ color: 'var(--clr-text)' }}>Audit Logs</h1>
          <p className="mt-1 text-xs" style={{ color: 'var(--clr-muted)' }}>
            System activity and accountability trail.
          </p>
        </div>
        <div
          className="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold"
          style={{
            background: 'var(--clr-accent-soft)',
            color: '#7c3aed',
            border: '1px solid rgba(124,58,237,.2)',
          }}
        >
          <Shield size={13} />
          {rows.length} events
        </div>
      </div>

      {error && <Alert message={error} />}

      <div
        className="rounded-xl overflow-hidden"
        style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}
      >
        <div className="px-5 py-4" style={{ borderBottom: '1px solid var(--clr-border)' }}>
          <h2 className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>Activity Trail</h2>
          <p className="text-xs mt-0.5" style={{ color: 'var(--clr-muted)' }}>
            All system events sorted by most recent
          </p>
        </div>
        {loading ? <LoadingSpinner /> : (
          <DataTable
            columns={['created_at', 'user_name', 'user_role', 'module_name', 'action', 'ip_address']}
            labels={{
              created_at: 'Date & Time', user_name: 'User', user_role: 'Role',
              module_name: 'Module', action: 'Action', ip_address: 'IP',
            }}
            rows={rows}
            noEdit noDelete
          />
        )}
      </div>
    </div>
  );
}
