import { useEffect, useState } from 'react';
import { CalendarClock } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import CrudPage from '../components/crud/CrudPage';
import Modal from '../components/ui/Modal';
import Alert from '../components/ui/Alert';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import api from '../api';
import { modules } from '../modules';

const days = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

const defaultSchedule = (day) => ({
  day_of_week: day,
  start_time: '08:00',
  end_time: '11:00',
  slot_minutes: 24,
  is_working: ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday'].includes(day),
});

function inputStyle() {
  return { background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)', color: 'var(--clr-text)' };
}

export default function Doctors() {
  const { lookups, refresh } = useAuth();
  const [scheduleDoctor, setScheduleDoctor] = useState(null);

  return (
    <>
      <CrudPage
        title="Doctors"
        subtitle="Clinical team profiles, schedules, and availability."
        config={modules.doctors}
        lookups={lookups}
        onDataChanged={refresh}
        renderActions={(doctor) => (
          <button
            onClick={() => setScheduleDoctor(doctor)}
            title="Working hours and slots"
            className="p-2 rounded-lg transition-colors"
            style={{ color: 'var(--clr-muted)' }}
          >
            <CalendarClock size={15} />
          </button>
        )}
      />

      {scheduleDoctor && (
        <ScheduleModal
          doctor={scheduleDoctor}
          onClose={() => setScheduleDoctor(null)}
        />
      )}
    </>
  );
}

function ScheduleModal({ doctor, onClose }) {
  const [schedules, setSchedules] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;
    api.get(`/doctors/${doctor.id}/schedules`)
      .then(({ data }) => {
        if (cancelled) return;
        const rows = days.map((day) => {
          const found = (data.schedules ?? []).find((item) => item.day_of_week === day);
          return found ? {
            ...found,
            start_time: String(found.start_time).slice(0, 5),
            end_time: String(found.end_time).slice(0, 5),
            is_working: Boolean(found.is_working),
          } : defaultSchedule(day);
        });
        setSchedules(rows);
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
    return () => { cancelled = true; };
  }, [doctor.id]);

  const setRow = (day, key, value) => {
    setSchedules((current) => current.map((row) => row.day_of_week === day ? { ...row, [key]: value } : row));
  };

  const save = async () => {
    setSaving(true);
    setError('');
    try {
      await api.put(`/doctors/${doctor.id}/schedules`, { schedules });
      onClose();
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal title="Doctor Working Hours" subtitle={`${doctor.full_name} appointment slots and daily capacity.`} onClose={onClose}>
      {error && <div className="mb-4"><Alert message={error} /></div>}
      {loading ? <LoadingSpinner /> : (
        <div className="space-y-3">
          {schedules.map((row) => {
            const start = new Date(`2026-01-01T${row.start_time}:00`);
            const end = new Date(`2026-01-01T${row.end_time}:00`);
            const minutes = Math.max(0, (end - start) / 60000);
            const capacity = row.slot_minutes > 0 ? Math.floor(minutes / Number(row.slot_minutes)) : 0;
            return (
              <div key={row.day_of_week} className="grid grid-cols-1 md:grid-cols-[120px_1fr_1fr_1fr_110px] gap-2 items-center rounded-lg p-3" style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)' }}>
                <label className="flex items-center gap-2 text-sm font-bold" style={{ color: 'var(--clr-text)' }}>
                  <input type="checkbox" checked={row.is_working} onChange={(e) => setRow(row.day_of_week, 'is_working', e.target.checked)} className="accent-green-500" />
                  {row.day_of_week}
                </label>
                <input type="time" value={row.start_time} onChange={(e) => setRow(row.day_of_week, 'start_time', e.target.value)} className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()} />
                <input type="time" value={row.end_time} onChange={(e) => setRow(row.day_of_week, 'end_time', e.target.value)} className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()} />
                <input type="number" min="5" value={row.slot_minutes} onChange={(e) => setRow(row.day_of_week, 'slot_minutes', Number(e.target.value))} className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()} />
                <span className="text-xs font-bold text-green-500">{row.is_working ? `${capacity} slots` : 'Off'}</span>
              </div>
            );
          })}

          <div className="flex justify-end gap-2 pt-4" style={{ borderTop: '1px solid var(--clr-border)' }}>
            <button onClick={onClose} className="px-4 py-2 rounded-lg text-sm font-bold" style={{ border: '1px solid var(--clr-border)', color: 'var(--clr-muted)' }}>Cancel</button>
            <button onClick={save} disabled={saving} className="px-5 py-2 rounded-lg text-sm font-bold" style={{ background: '#22c55e', color: '#052e10' }}>
              {saving ? 'Saving...' : 'Save Schedule'}
            </button>
          </div>
        </div>
      )}
    </Modal>
  );
}
