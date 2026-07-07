import { useEffect, useState } from 'react';
import { useAuth } from '../context/AuthContext';
import api, { asRows } from '../api';
import DataTable from '../components/ui/DataTable';
import Alert from '../components/ui/Alert';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import { FileText, Plus } from 'lucide-react';

export default function Prescriptions() {
  const { lookups, refresh } = useAuth();
  const [rows,    setRows]    = useState([]);
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState('');
  const [saving,  setSaving]  = useState(false);
  const [form,    setForm]    = useState({
    patient_id: '', doctor_id: '', medicine_id: '', quantity: 1, instructions: '',
  });

  const load = async () => {
    setLoading(true);
    try {
      const { data } = await api.get('/prescriptions');
      setRows(asRows(data));
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const submit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      await api.post('/prescriptions', {
        patient_id: form.patient_id,
        doctor_id:  form.doctor_id,
        medicines:  [{ medicine_id: form.medicine_id, quantity: +form.quantity, instructions: form.instructions }],
      });
      setForm({ patient_id: '', doctor_id: '', medicine_id: '', quantity: 1, instructions: '' });
      await load();
      refresh();
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const inputStyle = {
    width: '100%', padding: '.625rem .75rem', borderRadius: '.625rem',
    border: '1px solid var(--clr-border)', background: 'var(--clr-search-bg)',
    color: 'var(--clr-text)', fontSize: '.875rem', outline: 'none', fontFamily: 'inherit',
    transition: 'border-color .15s, box-shadow .15s',
  };
  const focusStyle = { borderColor: '#22c55e', boxShadow: '0 0 0 3px rgba(34,197,94,.12)' };
  const labelClass = 'block text-[10px] font-semibold uppercase tracking-widest mb-1.5';

  return (
    <div className="space-y-5 animate-fade-in">
      <div>
        <h1 className="text-xl font-bold" style={{ color: 'var(--clr-text)' }}>Prescriptions</h1>
        <p className="mt-1 text-xs" style={{ color: 'var(--clr-muted)' }}>Doctor prescriptions and dispensing status.</p>
      </div>

      {error && <Alert message={error} />}

      <div className="grid grid-cols-1 lg:grid-cols-5 gap-5 items-start">
        {/* New Prescription Form */}
        <div
          className="lg:col-span-2 rounded-xl p-5"
          style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}
        >
          <div className="flex items-center gap-2 mb-5">
            <div
              className="w-7 h-7 rounded-lg flex items-center justify-center"
              style={{ background: 'var(--clr-accent-soft)', border: '1px solid rgba(34,197,94,.2)' }}
            >
              <Plus size={14} className="text-green-500" />
            </div>
            <h2 className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>New Prescription</h2>
          </div>

          <form onSubmit={submit} className="space-y-4">
            {[
              { key: 'patient_id', label: 'Patient', opts: lookups?.patients ?? [], field: 'full_name' },
              { key: 'doctor_id',  label: 'Doctor',  opts: lookups?.doctors  ?? [], field: 'full_name' },
              { key: 'medicine_id',label: 'Medicine',opts: lookups?.medicines ?? [], field: 'medicine_name' },
            ].map(({ key, label, opts, field }) => (
              <div key={key}>
                <label className={labelClass} style={{ color: 'var(--clr-section)' }}>{label}</label>
                <select required style={inputStyle} value={form[key]}
                  onChange={(e) => setForm({ ...form, [key]: e.target.value })}
                  onFocus={(e) => Object.assign(e.target.style, focusStyle)}
                  onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }}
                >
                  <option value="">Select {label.toLowerCase()}…</option>
                  {opts.map((o) => (
                    <option key={o.id} value={o.id}>{o[field]}</option>
                  ))}
                </select>
              </div>
            ))}

            <div>
              <label className={labelClass} style={{ color: 'var(--clr-section)' }}>Quantity</label>
              <input type="number" min="1" style={inputStyle} value={form.quantity}
                onChange={(e) => setForm({ ...form, quantity: e.target.value })}
                onFocus={(e) => Object.assign(e.target.style, focusStyle)}
                onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }} />
            </div>

            <div>
              <label className={labelClass} style={{ color: 'var(--clr-section)' }}>Instructions</label>
              <textarea rows={3} style={inputStyle} value={form.instructions}
                onChange={(e) => setForm({ ...form, instructions: e.target.value })}
                onFocus={(e) => Object.assign(e.target.style, focusStyle)}
                onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }} />
            </div>

            <button type="submit" disabled={saving}
              className="w-full py-2.5 rounded-lg text-sm font-semibold transition-all disabled:opacity-50"
              style={{ background: '#22c55e', color: '#052e10', border: 'none', cursor: saving ? 'not-allowed' : 'pointer' }}
              onMouseEnter={(e) => { if (!saving) e.currentTarget.style.background = '#16a34a'; }}
              onMouseLeave={(e) => { e.currentTarget.style.background = '#22c55e'; }}
            >
              {saving ? 'Creating…' : 'Create Prescription'}
            </button>
          </form>
        </div>

        {/* Table */}
        <div
          className="lg:col-span-3 rounded-xl overflow-hidden"
          style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}
        >
          <div className="flex items-center gap-2 px-5 py-4" style={{ borderBottom: '1px solid var(--clr-border)' }}>
            <FileText size={14} className="text-green-500" />
            <h2 className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>All Prescriptions</h2>
            <span className="ml-auto text-xs" style={{ color: 'var(--clr-muted)' }}>{rows.length} records</span>
          </div>
          {loading ? <LoadingSpinner /> : (
            <DataTable
              columns={['patient.full_name', 'doctor.full_name', 'status', 'created_at']}
              labels={{ 'patient.full_name': 'Patient', 'doctor.full_name': 'Doctor', status: 'Status', created_at: 'Date' }}
              rows={rows} noEdit noDelete
            />
          )}
        </div>
      </div>
    </div>
  );
}
