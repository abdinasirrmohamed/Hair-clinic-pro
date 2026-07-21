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
  shift: 'Morning',
  start_time: '08:00',
  end_time: '11:00',
  slot_minutes: 24,
  is_working: ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday'].includes(day),
});

function inputStyle() {
  return { background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)', color: 'var(--clr-text)' };
}

export default function Doctors() {
  const { lookups, refresh, user } = useAuth();
  const [scheduleDoctor, setScheduleDoctor] = useState(null);

  return (
    <>
      <CrudPage
        title="Doctors"
        subtitle="Clinical team profiles, schedules, and availability."
        config={modules.doctors}
        lookups={lookups}
        onDataChanged={refresh}
        onRecordSaved={(doctor, created) => { if (created && doctor) setScheduleDoctor(doctor); }}
        renderActions={(doctor) => user?.role === 'Administrator' ? (
          <button
            onClick={() => setScheduleDoctor(doctor)}
            title="Working hours and slots"
            className="p-2 rounded-lg transition-colors"
            style={{ color: 'var(--clr-muted)' }}
          >
            <CalendarClock size={15} />
          </button>
        ) : null}
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
        const rows = (data.schedules ?? []).map((found) => ({
            ...found,
            start_time: String(found.start_time).slice(0, 5),
            end_time: String(found.end_time).slice(0, 5),
            is_working: Boolean(found.is_working),
          }));
        setSchedules(rows.length ? rows : [defaultSchedule('Saturday')]);
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

  const remove = async (row, index) => {
    if (!window.confirm(`Delete ${row.day_of_week} ${row.shift} schedule?`)) return;
    if (row.id) await api.delete(`/doctors/${doctor.id}/schedules/${row.id}`);
    setSchedules((current) => current.filter((_, rowIndex) => rowIndex !== index));
  };

  return (
    <Modal title="Doctor Working Hours" subtitle={`${doctor.full_name} appointment slots and daily capacity.`} onClose={onClose}>
      {error && <div className="mb-4"><Alert message={error} /></div>}
      {loading ? <LoadingSpinner /> : (
        <div className="space-y-3">
          <div className="flex justify-end">
            <button type="button" onClick={() => setSchedules((current) => [...current, { ...defaultSchedule('Saturday'), is_working: true }])} className="px-3 py-2 rounded-lg text-xs font-bold text-white" style={{ background: 'var(--clr-accent)' }}>Add Schedule</button>
          </div>
          {schedules.map((row, index) => {
            const start = new Date(`2026-01-01T${row.start_time}:00`);
            const end = new Date(`2026-01-01T${row.end_time}:00`);
            const minutes = Math.max(0, (end - start) / 60000);
            const capacity = row.slot_minutes > 0 ? Math.floor(minutes / Number(row.slot_minutes)) : 0;
            return (
              <div key={row.id ?? `new-${index}`} className="grid grid-cols-1 md:grid-cols-[1fr_1fr_1fr_1fr_90px_70px_36px] gap-2 items-center rounded-lg p-3" style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)' }}>
                <select value={row.day_of_week} onChange={(e) => setSchedules((current) => current.map((item, i) => i === index ? { ...item, day_of_week: e.target.value } : item))} className="rounded-lg px-2 py-2 text-sm" style={inputStyle()}>{days.map((day) => <option key={day}>{day}</option>)}</select>
                <select value={row.shift ?? 'Morning'} onChange={(e) => setSchedules((current) => current.map((item, i) => i === index ? { ...item, shift: e.target.value } : item))} className="rounded-lg px-2 py-2 text-sm" style={inputStyle()}><option>Morning</option><option>Afternoon</option></select>
                <input type="time" value={row.start_time} onChange={(e) => setSchedules((current) => current.map((item, i) => i === index ? { ...item, start_time: e.target.value } : item))} className="rounded-lg px-2 py-2 text-sm outline-none" style={inputStyle()} />
                <input type="time" value={row.end_time} onChange={(e) => setSchedules((current) => current.map((item, i) => i === index ? { ...item, end_time: e.target.value } : item))} className="rounded-lg px-2 py-2 text-sm outline-none" style={inputStyle()} />
                <input type="number" min="5" value={row.slot_minutes} onChange={(e) => setSchedules((current) => current.map((item, i) => i === index ? { ...item, slot_minutes: Number(e.target.value) } : item))} className="rounded-lg px-2 py-2 text-sm outline-none" style={inputStyle()} />
                <span className="text-xs font-bold text-violet-600">{`${capacity} slots`}</span>
                <button type="button" onClick={() => remove(row, index)} aria-label="Delete schedule" className="text-red-500">×</button>
              </div>
            );
          })}

          <div className="flex justify-end gap-2 pt-4" style={{ borderTop: '1px solid var(--clr-border)' }}>
            <button onClick={onClose} className="px-4 py-2 rounded-lg text-sm font-bold" style={{ border: '1px solid var(--clr-border)', color: 'var(--clr-muted)' }}>Cancel</button>
            <button onClick={save} disabled={saving} className="px-5 py-2 rounded-lg text-sm font-bold" style={{ background: '#7c3aed', color: '#ffffff' }}>
              {saving ? 'Saving...' : 'Save Schedule'}
            </button>
          </div>
        </div>
      )}
    </Modal>
  );
}
