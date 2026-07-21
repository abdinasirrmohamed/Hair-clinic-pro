import { useState } from 'react';
import { Clock } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import CrudPage from '../components/crud/CrudPage';
import Modal from '../components/ui/Modal';
import Alert from '../components/ui/Alert';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import { modules } from '../modules';
import api from '../api';
import { money } from '../utils/formatters';

export default function Patients() {
  const { lookups, refresh } = useAuth();
  const [timeline, setTimeline] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const openTimeline = async (patient) => {
    setLoading(true);
    setError('');
    try {
      const { data } = await api.get(`/patients/${patient.id}/timeline`);
      setTimeline(data);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      {error && <div className="mb-4"><Alert message={error} /></div>}
      <CrudPage
        title="Patient Management"
        subtitle="Manage records, medical history, and scheduled sessions."
        config={modules.patients}
        lookups={lookups}
        onDataChanged={refresh}
        renderActions={(row) => (
          <button
            onClick={() => openTimeline(row)}
            title="Patient timeline"
            className="p-2 rounded-lg transition-colors"
            style={{ color: 'var(--clr-muted)' }}
            onMouseEnter={(e) => { e.currentTarget.style.color = '#7c3aed'; e.currentTarget.style.background = 'var(--clr-accent-soft)'; }}
            onMouseLeave={(e) => { e.currentTarget.style.color = 'var(--clr-muted)'; e.currentTarget.style.background = 'transparent'; }}
          >
            <Clock size={14} />
          </button>
        )}
      />

      {loading && <LoadingSpinner text="Loading timeline..." />}

      {timeline && (
        <Modal
          title={`${timeline.patient.full_name} Timeline`}
          subtitle="Appointments, payments, prescriptions, and treatments."
          onClose={() => setTimeline(null)}
        >
          <div className="space-y-3">
            {timeline.events.length === 0 && (
              <p className="text-sm" style={{ color: 'var(--clr-muted)' }}>No timeline events found.</p>
            )}
            {timeline.events.map((event, index) => (
              <div key={index} className="rounded-lg p-3" style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)' }}>
                <div className="flex justify-between gap-3">
                  <div>
                    <p className="text-[11px] font-bold uppercase tracking-widest text-violet-600">{event.type}</p>
                    <h3 className="text-sm font-bold mt-1" style={{ color: 'var(--clr-text)' }}>{event.title}</h3>
                    <p className="text-xs mt-1" style={{ color: 'var(--clr-muted)' }}>{event.description}</p>
                  </div>
                  <div className="text-right shrink-0">
                    <p className="text-xs" style={{ color: 'var(--clr-muted)' }}>{event.date}</p>
                    {event.amount !== null && <p className="text-sm font-bold text-violet-600 mt-1">{money(event.amount)}</p>}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </Modal>
      )}
    </>
  );
}
