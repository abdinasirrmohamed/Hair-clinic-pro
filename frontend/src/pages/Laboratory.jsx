import { useEffect, useState } from 'react';
import api, { asRows } from '../api';
import { useAuth } from '../context/AuthContext';
import Alert from '../components/ui/Alert';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import DataTable from '../components/ui/DataTable';
import { FlaskConical, Plus, RefreshCw } from 'lucide-react';
import { money } from '../utils/formatters';

const testDefaults = { test_name: '', category: 'Hair & Scalp Lab', price: 0, sample_type: 'Blood', status: 'Active', description: '' };
const requestDefaults = { patient_id: '', appointment_id: '', doctor_id: '', lab_test_id: '', request_date: new Date().toISOString().slice(0, 10), status: 'Requested', notes: '', result: '' };
const statuses = ['Requested', 'In Progress', 'Completed', 'Cancelled'];

function inputStyle() {
  return { background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)', color: 'var(--clr-text)' };
}

export default function Laboratory() {
  const { lookups, refresh } = useAuth();
  const [tests, setTests] = useState([]);
  const [requests, setRequests] = useState([]);
  const [testForm, setTestForm] = useState(testDefaults);
  const [requestForm, setRequestForm] = useState(requestDefaults);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      const [testsRes, requestsRes] = await Promise.all([
        api.get('/lab/tests?per_page=100'),
        api.get('/lab/requests?per_page=100'),
      ]);
      setTests(asRows(testsRes.data));
      setRequests(asRows(requestsRes.data));
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const saveTest = async (event) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    try {
      await api.post('/lab/tests', { ...testForm, price: Number(testForm.price || 0) });
      setTestForm(testDefaults);
      setMessage('Lab service saved.');
      await load();
      refresh();
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const saveRequest = async (event) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    try {
      await api.post('/lab/requests', requestForm);
      setRequestForm(requestDefaults);
      setMessage('Lab request created.');
      await load();
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const selectedTest = tests.find((test) => String(test.id) === String(requestForm.lab_test_id));

  return (
    <div className="space-y-5 animate-fade-in">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold" style={{ color: 'var(--clr-text)' }}>Laboratory</h1>
          <p className="mt-1 text-xs" style={{ color: 'var(--clr-muted)' }}>Manage lab services, patient test requests, results, and lab reporting data.</p>
        </div>
        <button onClick={load} className="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold" style={{ border: '1px solid var(--clr-border)', color: 'var(--clr-muted)' }}>
          <RefreshCw size={13} /> Refresh
        </button>
      </div>

      {error && <Alert message={error} />}
      {message && <Alert message={message} variant="success" />}

      <div className="grid grid-cols-1 xl:grid-cols-2 gap-5 items-start">
        <form onSubmit={saveTest} className="rounded-xl overflow-hidden" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
          <div className="px-5 py-4 flex items-center gap-2" style={{ borderBottom: '1px solid var(--clr-border)' }}>
            <FlaskConical size={16} className="text-violet-600" />
            <h2 className="text-sm font-bold" style={{ color: 'var(--clr-text)' }}>Add Lab Service / Test</h2>
          </div>
          <div className="p-5 grid grid-cols-1 md:grid-cols-2 gap-3">
            <input required value={testForm.test_name} onChange={(e) => setTestForm({ ...testForm, test_name: e.target.value })} placeholder="Test name" className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()} />
            <select value={testForm.category} onChange={(e) => setTestForm({ ...testForm, category: e.target.value })} className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()}>
              {['Hair & Scalp Lab', 'Blood Test', 'Hormone Test', 'Allergy Test', 'Biopsy', 'Imaging'].map((item) => <option key={item}>{item}</option>)}
            </select>
            <input required type="number" min="0" value={testForm.price} onChange={(e) => setTestForm({ ...testForm, price: e.target.value })} placeholder="Price" className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()} />
            <select value={testForm.sample_type} onChange={(e) => setTestForm({ ...testForm, sample_type: e.target.value })} className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()}>
              {['Blood', 'Scalp Swab', 'Hair Sample', 'Skin Tissue', 'Image/Scan', 'No Sample'].map((item) => <option key={item}>{item}</option>)}
            </select>
            <textarea value={testForm.description} onChange={(e) => setTestForm({ ...testForm, description: e.target.value })} placeholder="Description" rows={2} className="md:col-span-2 rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()} />
          </div>
          <div className="px-5 py-4 flex justify-end" style={{ borderTop: '1px solid var(--clr-border)' }}>
            <button disabled={saving} className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold" style={{ background: '#7c3aed', color: '#ffffff' }}>
              <Plus size={14} /> Save Lab Service
            </button>
          </div>
        </form>

        <form onSubmit={saveRequest} className="rounded-xl overflow-hidden" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
          <div className="px-5 py-4 flex items-center gap-2" style={{ borderBottom: '1px solid var(--clr-border)' }}>
            <FlaskConical size={16} className="text-violet-600" />
            <h2 className="text-sm font-bold" style={{ color: 'var(--clr-text)' }}>New Patient Lab Request</h2>
          </div>
          <div className="p-5 grid grid-cols-1 md:grid-cols-2 gap-3">
            <select required value={requestForm.patient_id} onChange={(e) => setRequestForm({ ...requestForm, patient_id: e.target.value })} className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()}>
              <option value="">Select patient</option>
              {(lookups?.patients ?? []).map((p) => <option key={p.id} value={p.id}>{p.full_name} - {p.phone}</option>)}
            </select>
            <select value={requestForm.appointment_id} onChange={(e) => setRequestForm({ ...requestForm, appointment_id: e.target.value })} className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()}>
              <option value="">No appointment link</option>
              {(lookups?.appointments ?? []).map((a) => <option key={a.id} value={a.id}>#{a.id} - {a.appointment_date}</option>)}
            </select>
            <select value={requestForm.doctor_id} onChange={(e) => setRequestForm({ ...requestForm, doctor_id: e.target.value })} className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()}>
              <option value="">No doctor link</option>
              {(lookups?.doctors ?? []).map((d) => <option key={d.id} value={d.id}>{d.full_name}</option>)}
            </select>
            <select required value={requestForm.lab_test_id} onChange={(e) => setRequestForm({ ...requestForm, lab_test_id: e.target.value })} className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()}>
              <option value="">Select lab test</option>
              {tests.map((test) => <option key={test.id} value={test.id}>{test.test_name} - {money(test.price)}</option>)}
            </select>
            <input type="date" min={new Date().toISOString().slice(0, 10)} value={requestForm.request_date} onChange={(e) => setRequestForm({ ...requestForm, request_date: e.target.value })} className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()} />
            <select value={requestForm.status} onChange={(e) => setRequestForm({ ...requestForm, status: e.target.value })} className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()}>
              {statuses.map((status) => <option key={status}>{status}</option>)}
            </select>
            <textarea value={requestForm.notes} onChange={(e) => setRequestForm({ ...requestForm, notes: e.target.value })} placeholder="Notes" rows={2} className="md:col-span-2 rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()} />
            <textarea value={requestForm.result} onChange={(e) => setRequestForm({ ...requestForm, result: e.target.value })} placeholder="Result / findings" rows={2} className="md:col-span-2 rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()} />
            {selectedTest && <p className="md:col-span-2 text-xs text-violet-600 font-semibold">Selected price: {money(selectedTest.price)}</p>}
          </div>
          <div className="px-5 py-4 flex justify-end" style={{ borderTop: '1px solid var(--clr-border)' }}>
            <button disabled={saving} className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold" style={{ background: '#7c3aed', color: '#ffffff' }}>
              <Plus size={14} /> Create Request
            </button>
          </div>
        </form>
      </div>

      {loading ? <LoadingSpinner /> : (
        <div className="grid grid-cols-1 xl:grid-cols-2 gap-5">
          <DataTable
            columns={['test_name', 'category', 'sample_type', 'price', 'status']}
            labels={{ test_name: 'Test', category: 'Category', sample_type: 'Sample', price: 'Price', status: 'Status' }}
            rows={tests}
            noEdit
            noDelete
          />
          <DataTable
            columns={['request_number', 'patient.full_name', 'test.test_name', 'doctor.full_name', 'request_date', 'status']}
            labels={{ request_number: 'Request', 'patient.full_name': 'Patient', 'test.test_name': 'Test', 'doctor.full_name': 'Doctor', request_date: 'Date', status: 'Status' }}
            rows={requests}
            noEdit
            noDelete
          />
        </div>
      )}
    </div>
  );
}
