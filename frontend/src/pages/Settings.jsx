import { useEffect, useState } from 'react';
import { CreditCard, Save, Settings as SettingsIcon } from 'lucide-react';
import api from '../api';
import Alert from '../components/ui/Alert';
import LoadingSpinner from '../components/ui/LoadingSpinner';

const fields = [
  ['clinic_name', 'Clinic Name'],
  ['clinic_phone', 'Clinic Phone'],
  ['clinic_address', 'Clinic Address'],
  ['currency', 'Currency'],
  ['tax_percent', 'Tax Percent'],
  ['slot_minutes', 'Appointment Slot Minutes'],
  ['working_days', 'Working Days'],
];

export default function Settings() {
  const [form, setForm] = useState(null);
  const [message, setMessage] = useState({ text: '', type: 'info' });
  const [saving, setSaving] = useState(false);
  const [waafi, setWaafi] = useState(null);
  const [waafiTest, setWaafiTest] = useState({ account_no: '', amount: '0.01' });
  const [testing, setTesting] = useState(false);

  useEffect(() => {
    api.get('/settings')
      .then(({ data }) => setForm(data))
      .catch((err) => setMessage({ text: err.message, type: 'danger' }));
    api.get('/settings/waafi/status')
      .then(({ data }) => setWaafi(data))
      .catch((err) => setMessage({ text: err.message, type: 'danger' }));
  }, []);

  const testWaafi = async (event) => {
    event.preventDefault();
    if (!window.confirm(`This will send a real $${waafiTest.amount} WaafiPay charge request. Continue?`)) return;
    setTesting(true);
    setMessage({ text: '', type: 'info' });
    try {
      const { data } = await api.post('/settings/waafi/test', waafiTest);
      setMessage({ text: `${data.message} Transaction: ${data.transaction_id || 'N/A'}`, type: 'success' });
    } catch (err) {
      setMessage({ text: err.message, type: 'danger' });
    } finally {
      setTesting(false);
    }
  };

  const submit = async (event) => {
    event.preventDefault();
    setSaving(true);
    setMessage({ text: '', type: 'info' });
    try {
      const { data } = await api.put('/settings', form);
      setForm(data);
      setMessage({ text: 'Settings saved successfully.', type: 'success' });
    } catch (err) {
      setMessage({ text: err.message, type: 'danger' });
    } finally {
      setSaving(false);
    }
  };

  const inputStyle = {
    width: '100%',
    border: '1px solid var(--clr-border)',
    background: 'var(--clr-search-bg)',
    color: 'var(--clr-text)',
    borderRadius: '.625rem',
    padding: '.7rem .8rem',
    fontSize: '.875rem',
    outline: 'none',
  };

  if (!form) return <LoadingSpinner text="Loading settings..." />;

  return (
    <div className="space-y-5 animate-fade-in max-w-4xl">
      <div>
        <h1 className="text-xl font-bold" style={{ color: 'var(--clr-text)' }}>Settings</h1>
        <p className="mt-1 text-xs" style={{ color: 'var(--clr-muted)' }}>
          Clinic info, currency, tax, and appointment defaults.
        </p>
      </div>

      {message.text && <Alert message={message.text} variant={message.type} />}

      <form onSubmit={submit} className="rounded-xl overflow-hidden" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
        <div className="px-5 py-4 flex items-center gap-2" style={{ borderBottom: '1px solid var(--clr-border)' }}>
          <SettingsIcon size={16} className="text-violet-600" />
          <h2 className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>System Settings</h2>
        </div>
        <div className="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
          {fields.map(([name, label]) => (
            <label key={name}>
              <span className="block text-[10px] font-bold uppercase tracking-widest mb-1.5" style={{ color: 'var(--clr-section)' }}>{label}</span>
              <input
                type={['tax_percent', 'slot_minutes'].includes(name) ? 'number' : 'text'}
                style={inputStyle}
                value={form[name] ?? ''}
                onChange={(e) => setForm((current) => ({ ...current, [name]: e.target.value }))}
              />
            </label>
          ))}
        </div>
        <div className="px-5 py-4 flex justify-end" style={{ borderTop: '1px solid var(--clr-border)' }}>
          <button disabled={saving} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold disabled:opacity-60" style={{ background: '#7c3aed', color: '#ffffff', border: 'none' }}>
            <Save size={14} />
            {saving ? 'Saving...' : 'Save Settings'}
          </button>
        </div>
      </form>

      <form onSubmit={testWaafi} className="rounded-xl overflow-hidden" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
        <div className="px-5 py-4 flex items-center justify-between gap-3" style={{ borderBottom: '1px solid var(--clr-border)' }}>
          <div className="flex items-center gap-2">
            <CreditCard size={16} className="text-violet-600" />
            <div>
              <h2 className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>WaafiPay Test</h2>
              <p className="text-xs" style={{ color: 'var(--clr-muted)' }}>Sends a real test charge; maximum $1.</p>
            </div>
          </div>
          <span className="px-2 py-1 rounded-full text-xs font-bold" style={{ color: waafi?.configured ? '#7c3aed' : '#f87171', background: waafi?.configured ? '#7c3aed18' : '#f8717118' }}>
            {waafi?.configured ? 'Configured' : 'Not configured'}
          </span>
        </div>
        <div className="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
          <label>
            <span className="block text-[10px] font-bold uppercase tracking-widest mb-1.5" style={{ color: 'var(--clr-section)' }}>Mobile Account</span>
            <input required style={inputStyle} placeholder="25261XXXXXXX" value={waafiTest.account_no} onChange={(e) => setWaafiTest((current) => ({ ...current, account_no: e.target.value }))} />
          </label>
          <label>
            <span className="block text-[10px] font-bold uppercase tracking-widest mb-1.5" style={{ color: 'var(--clr-section)' }}>Test Amount (USD)</span>
            <input required type="number" min="0.01" max="1" step="0.01" style={inputStyle} value={waafiTest.amount} onChange={(e) => setWaafiTest((current) => ({ ...current, amount: e.target.value }))} />
          </label>
          <p className="md:col-span-2 text-xs" style={{ color: 'var(--clr-muted)' }}>
            Endpoint: {waafi?.endpoint ?? '—'} · Merchant: {waafi?.merchant || '—'}
          </p>
        </div>
        <div className="px-5 py-4 flex justify-end" style={{ borderTop: '1px solid var(--clr-border)' }}>
          <button disabled={testing || !waafi?.configured} className="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold disabled:opacity-50" style={{ background: '#7c3aed', color: '#ffffff', border: 'none' }}>
            <CreditCard size={14} />
            {testing ? 'Testing...' : 'Send Test Charge'}
          </button>
        </div>
      </form>
    </div>
  );
}
