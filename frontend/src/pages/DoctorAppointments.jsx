import { useEffect, useState } from 'react';
import { useAuth } from '../context/AuthContext';
import api, { asRows } from '../api';
import DataTable from '../components/ui/DataTable';
import Alert from '../components/ui/Alert';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import StatusBadge from '../components/ui/StatusBadge';
import { CalendarCheck } from 'lucide-react';

const STATUS_OPTIONS = ['Pending', 'Approved', 'Rejected', 'Completed'];

export default function DoctorAppointments() {
  const [rows,    setRows]    = useState([]);
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState('');

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      const { data } = await api.get('/doctor/appointments');
      setRows(asRows(data, 'appointments'));
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const changeStatus = async (id, status) => {
    setError('');
    try {
      await api.patch(`/doctor/appointments/${id}/status`, { status });
      await load();
    } catch (err) {
      setError(err.message);
    }
  };

  const selectStyle = {
    fontSize: '.75rem',
    padding: '.375rem .625rem',
    borderRadius: '.5rem',
    border: '1px solid var(--clr-border)',
    background: 'var(--clr-search-bg)',
    color: 'var(--clr-text)',
    outline: 'none',
    fontFamily: 'inherit',
    cursor: 'pointer',
  };

  return (
    <div className="space-y-5 animate-fade-in">
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-xl font-bold" style={{ color: 'var(--clr-text)' }}>My Appointments</h1>
          <p className="mt-1 text-xs" style={{ color: 'var(--clr-muted)' }}>
            Doctor appointment approvals and daily worklist.
          </p>
        </div>
        <div
          className="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold"
          style={{ background: 'var(--clr-accent-soft)', color: '#7c3aed', border: '1px solid rgba(124,58,237,.2)' }}
        >
          <CalendarCheck size={13} />
          {rows.length} total
        </div>
      </div>

      {error && <Alert message={error} />}

      <div
        className="rounded-xl overflow-hidden"
        style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}
      >
        <div className="px-5 py-4" style={{ borderBottom: '1px solid var(--clr-border)' }}>
          <h2 className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>Appointment Queue</h2>
        </div>
        {loading ? <LoadingSpinner /> : (
          <DataTable
            columns={['appointment_date', 'appointment_time', 'patient.full_name', 'reason', 'status']}
            labels={{
              appointment_date: 'Date', appointment_time: 'Time',
              'patient.full_name': 'Patient', reason: 'Reason', status: 'Status',
            }}
            rows={rows}
            renderActions={(row) => (
              <select
                value={row.status}
                onChange={(e) => changeStatus(row.id, e.target.value)}
                style={selectStyle}
                onFocus={(e) => { e.target.style.borderColor = '#7c3aed'; }}
                onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; }}
              >
                {STATUS_OPTIONS.map((s) => (
                  <option key={s}>{s}</option>
                ))}
              </select>
            )}
          />
        )}
      </div>

      {/* Quick status stats */}
      {!loading && rows.length > 0 && (
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
          {STATUS_OPTIONS.map((s) => {
            const count = rows.filter((r) => r.status === s).length;
            return (
              <div
                key={s}
                className="rounded-xl p-4"
                style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}
              >
                <StatusBadge value={s} />
                <p className="mt-2 text-2xl font-bold" style={{ color: 'var(--clr-text)' }}>{count}</p>
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
