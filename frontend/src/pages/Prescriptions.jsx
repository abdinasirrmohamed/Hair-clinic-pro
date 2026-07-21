import { useEffect, useState } from 'react';
import { FileText, Plus, Trash2 } from 'lucide-react';
import { useAuth } from '../context/AuthContext';
import api, { asRows } from '../api';
import Alert from '../components/ui/Alert';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import Modal from '../components/ui/Modal';

const emptyMedicine = () => ({ medicine_id: '', quantity: 1, frequency: '', instructions: '' });

export default function Prescriptions() {
  const { lookups, refresh } = useAuth();
  const [rows, setRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [selected, setSelected] = useState(null);
  const [form, setForm] = useState({ patient_id: '', doctor_id: '', medicines: [emptyMedicine()] });

  const load = async () => {
    setLoading(true);
    try {
      const { data } = await api.get('/prescriptions?per_page=100');
      setRows(asRows(data));
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };
  useEffect(() => { load(); }, []);

  const setMedicine = (index, key, value) => {
    setForm((current) => ({
      ...current,
      medicines: current.medicines.map((row, rowIndex) => rowIndex === index ? { ...row, [key]: value } : row),
    }));
  };

  const submit = async (event) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    setSuccess('');
    try {
      await api.post('/prescriptions', {
        ...form,
        medicines: form.medicines.map((row) => ({ ...row, quantity: Number(row.quantity) })),
      });
      setForm({ patient_id: '', doctor_id: '', medicines: [emptyMedicine()] });
      setSuccess('Prescription and all medicine items were saved successfully.');
      await load();
      refresh();
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const view = async (id) => {
    try {
      const { data } = await api.get(`/prescriptions/${id}`);
      setSelected(data);
    } catch (err) {
      setError(err.message);
    }
  };

  const inputClass = 'w-full rounded-lg border px-3 py-2 text-sm outline-none focus:ring-2';
  const inputStyle = { background: 'var(--clr-search-bg)', borderColor: 'var(--clr-border)', color: 'var(--clr-text)', '--tw-ring-color': 'var(--clr-accent-soft)' };

  return (
    <div className="space-y-5 animate-fade-in">
      <div>
        <h1 className="text-xl font-bold" style={{ color: 'var(--clr-text)' }}>Prescriptions</h1>
        <p className="mt-1 text-xs" style={{ color: 'var(--clr-muted)' }}>Create and review multi-medicine prescriptions.</p>
      </div>
      {error && <Alert message={error} />}
      {success && <Alert message={success} variant="success" />}

      <form onSubmit={submit} className="rounded-xl p-5 space-y-5" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
        <div className="grid gap-4 md:grid-cols-2">
          {[['patient_id', 'Patient', lookups?.patients ?? []], ['doctor_id', 'Doctor', lookups?.doctors ?? []]].map(([key, label, options]) => (
            <label key={key} className="space-y-1">
              <span className="text-xs font-semibold" style={{ color: 'var(--clr-muted)' }}>{label}</span>
              <select required className={inputClass} style={inputStyle} value={form[key]} onChange={(e) => setForm({ ...form, [key]: e.target.value })}>
                <option value="">Select {label.toLowerCase()}</option>
                {options.map((option) => <option key={option.id} value={option.id}>{option.full_name}</option>)}
              </select>
            </label>
          ))}
        </div>

        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-bold" style={{ color: 'var(--clr-text)' }}>Medicines</h2>
            <button type="button" onClick={() => setForm({ ...form, medicines: [...form.medicines, emptyMedicine()] })} className="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-bold text-white" style={{ background: 'var(--clr-accent)' }}>
              <Plus size={14} /> Add medicine
            </button>
          </div>
          {form.medicines.map((row, index) => (
            <div key={index} className="grid gap-3 rounded-xl p-4 md:grid-cols-[2fr_100px_1fr_2fr_40px]" style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)' }}>
              <select required className={inputClass} style={inputStyle} value={row.medicine_id} onChange={(e) => setMedicine(index, 'medicine_id', e.target.value)}>
                <option value="">Select medicine</option>
                {(lookups?.medicines ?? []).map((medicine) => <option key={medicine.id} value={medicine.id}>{medicine.medicine_name}</option>)}
              </select>
              <input required type="number" min="1" step="1" className={inputClass} style={inputStyle} value={row.quantity} onChange={(e) => setMedicine(index, 'quantity', e.target.value)} placeholder="Qty" />
              <input required className={inputClass} style={inputStyle} value={row.frequency} onChange={(e) => setMedicine(index, 'frequency', e.target.value)} placeholder="e.g. Twice daily" />
              <input required className={inputClass} style={inputStyle} value={row.instructions} onChange={(e) => setMedicine(index, 'instructions', e.target.value)} placeholder="Instructions" />
              <button type="button" disabled={form.medicines.length === 1} onClick={() => setForm({ ...form, medicines: form.medicines.filter((_, i) => i !== index) })} aria-label={`Remove medicine ${index + 1}`} className="rounded-lg text-red-500 disabled:opacity-30">
                <Trash2 size={17} />
              </button>
            </div>
          ))}
        </div>
        <div className="flex justify-end">
          <button disabled={saving} className="rounded-lg px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50" style={{ background: 'var(--clr-accent)' }}>
            {saving ? 'Saving...' : 'Save Prescription'}
          </button>
        </div>
      </form>

      <div className="rounded-xl overflow-hidden" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
        {loading ? <LoadingSpinner /> : rows.length === 0 ? (
          <p className="p-8 text-center text-sm" style={{ color: 'var(--clr-muted)' }}>No prescriptions found.</p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead><tr>{['Number', 'Patient', 'Doctor', 'Medicines', 'Status', ''].map((heading) => <th key={heading} className="p-3 text-left" style={{ color: 'var(--clr-muted)' }}>{heading}</th>)}</tr></thead>
              <tbody>{rows.map((row) => (
                <tr key={row.id} style={{ borderTop: '1px solid var(--clr-border)' }}>
                  <td className="p-3 font-mono">{row.prescription_number}</td>
                  <td className="p-3">{row.patient?.full_name}</td>
                  <td className="p-3">{row.doctor?.full_name}</td>
                  <td className="p-3">{row.medicines_count}</td>
                  <td className="p-3">{row.status}</td>
                  <td className="p-3"><button onClick={() => view(row.id)} className="flex items-center gap-1 text-xs font-bold" style={{ color: 'var(--clr-accent)' }}><FileText size={14} /> View</button></td>
                </tr>
              ))}</tbody>
            </table>
          </div>
        )}
      </div>

      {selected && (
        <Modal title={`Prescription ${selected.prescription_number}`} subtitle={`${selected.patient?.full_name} · ${selected.prescription_date}`} onClose={() => setSelected(null)} size="lg">
          <div className="mb-4 grid gap-3 sm:grid-cols-2 text-sm">
            <p><span style={{ color: 'var(--clr-muted)' }}>Patient:</span> {selected.patient?.full_name}</p>
            <p><span style={{ color: 'var(--clr-muted)' }}>Doctor:</span> {selected.doctor?.full_name}</p>
          </div>
          <div className="space-y-3">
            {(selected.medicines ?? []).map((item) => (
              <div key={item.id} className="rounded-lg p-4" style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)' }}>
                <p className="font-bold" style={{ color: 'var(--clr-text)' }}>{item.medicine?.medicine_name}</p>
                <p className="mt-1 text-sm" style={{ color: 'var(--clr-muted)' }}>Quantity: {item.quantity} · Frequency: {item.frequency}</p>
                <p className="mt-1 text-sm" style={{ color: 'var(--clr-text)' }}>{item.instructions}</p>
              </div>
            ))}
          </div>
        </Modal>
      )}
    </div>
  );
}
