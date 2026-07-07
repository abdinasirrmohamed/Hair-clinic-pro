import { useEffect, useState } from 'react';
import { useAuth } from '../context/AuthContext';
import api, { asRows } from '../api';
import CrudPage from '../components/crud/CrudPage';
import DataTable from '../components/ui/DataTable';
import Alert from '../components/ui/Alert';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import { modules } from '../modules';
import { Archive, ArrowDown, ArrowUp } from 'lucide-react';

const TABS = [
  { key: 'medicines', label: 'Medicines' },
  { key: 'movements', label: 'Stock Movements' },
  { key: 'suppliers', label: 'Suppliers' },
];

export default function Inventory() {
  const { lookups, refresh } = useAuth();
  const [tab,       setTab]      = useState('medicines');
  const [movements, setMovements] = useState([]);
  const [loadingM,  setLoadingM]  = useState(false);
  const [message,   setMessage]   = useState('');
  const [saving,    setSaving]    = useState(false);
  const [move, setMove] = useState({
    movement_type: 'stock-in', medicine_id: '', quantity: 1, unit_cost: 0, department: '', purpose: '',
  });

  const loadMovements = async () => {
    setLoadingM(true);
    try {
      const { data } = await api.get('/inventory/movements');
      setMovements(asRows(data));
    } catch (err) {
      setMessage(err.message);
    } finally {
      setLoadingM(false);
    }
  };

  useEffect(() => { if (tab === 'movements') loadMovements(); }, [tab]);

  const submitMovement = async (e) => {
    e.preventDefault();
    setSaving(true);
    setMessage('');
    const endpoint = move.movement_type === 'stock-in' ? '/inventory/stock-in' : '/inventory/stock-out';
    const payload  = move.movement_type === 'stock-in'
      ? { medicine_id: move.medicine_id, quantity: +move.quantity, unit_cost: +move.unit_cost }
      : { medicine_id: move.medicine_id, quantity: +move.quantity, department: move.department, purpose: move.purpose };
    try {
      await api.post(endpoint, payload);
      setMessage('Stock movement recorded.');
      refresh();
      loadMovements();
    } catch (err) {
      setMessage(err.message);
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
        <h1 className="text-xl font-bold" style={{ color: 'var(--clr-text)' }}>Inventory</h1>
        <p className="mt-1 text-xs" style={{ color: 'var(--clr-muted)' }}>
          Stock levels, suppliers, and movement history.
        </p>
      </div>

      {/* Tabs */}
      <div
        className="flex gap-1 p-1 rounded-xl w-fit"
        style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)' }}
      >
        {TABS.map(({ key, label }) => (
          <button key={key} onClick={() => setTab(key)}
            className="px-4 py-2 rounded-lg text-sm font-semibold transition-all"
            style={{
              background: tab === key ? 'var(--clr-card)' : 'transparent',
              color: tab === key ? '#22c55e' : 'var(--clr-muted)',
              border: 'none', cursor: 'pointer',
              boxShadow: tab === key ? '0 1px 3px rgba(0,0,0,0.15)' : 'none',
            }}
          >
            {label}
          </button>
        ))}
      </div>

      {tab === 'medicines' && (
        <CrudPage title="Medicines" subtitle="Manage medicine stock, pricing, and expiry dates."
          config={modules.inventory} lookups={lookups} onDataChanged={refresh} />
      )}

      {tab === 'suppliers' && (
        <CrudPage title="Suppliers" subtitle="Manage medicine and equipment suppliers."
          config={modules.suppliers} lookups={lookups} onDataChanged={refresh} />
      )}

      {tab === 'movements' && (
        <div className="grid grid-cols-1 lg:grid-cols-5 gap-5 items-start">
          {/* Movement Form */}
          <div
            className="lg:col-span-2 rounded-xl p-5"
            style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}
          >
            <div className="flex items-center gap-2 mb-5">
              <div
                className="w-7 h-7 rounded-lg flex items-center justify-center"
                style={{ background: 'var(--clr-accent-soft)', border: '1px solid rgba(34,197,94,.2)' }}
              >
                <Archive size={13} className="text-green-500" />
              </div>
              <h2 className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>Record Movement</h2>
            </div>

            {message && (
              <div className="mb-4">
                <Alert message={message} variant={message.includes('success') || message.includes('recorded') ? 'success' : 'danger'} />
              </div>
            )}

            <form onSubmit={submitMovement} className="space-y-4">
              {/* Movement type toggle */}
              <div>
                <label className={labelClass} style={{ color: 'var(--clr-section)' }}>Movement Type</label>
                <div className="grid grid-cols-2 gap-2">
                  {[
                    { key: 'stock-in',  label: 'Stock In',  icon: ArrowDown },
                    { key: 'stock-out', label: 'Stock Out', icon: ArrowUp },
                  ].map(({ key, label, icon: Icon }) => {
                    const active = move.movement_type === key;
                    return (
                      <button type="button" key={key}
                        onClick={() => setMove({ ...move, movement_type: key })}
                        className="flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-sm font-semibold transition-all"
                        style={{
                          border: `2px solid ${active ? '#22c55e' : 'var(--clr-border)'}`,
                          background: active ? 'var(--clr-accent-soft)' : 'transparent',
                          color: active ? '#22c55e' : 'var(--clr-muted)',
                          cursor: 'pointer',
                        }}
                      >
                        <Icon size={13} />
                        {label}
                      </button>
                    );
                  })}
                </div>
              </div>

              <div>
                <label className={labelClass} style={{ color: 'var(--clr-section)' }}>Medicine</label>
                <select style={inputStyle} required value={move.medicine_id}
                  onChange={(e) => setMove({ ...move, medicine_id: e.target.value })}
                  onFocus={(e) => Object.assign(e.target.style, focusStyle)}
                  onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }}
                >
                  <option value="">Select medicine…</option>
                  {(lookups?.medicines ?? []).map((m) => (
                    <option key={m.id} value={m.id}>{m.medicine_name} ({m.quantity} in stock)</option>
                  ))}
                </select>
              </div>

              <div>
                <label className={labelClass} style={{ color: 'var(--clr-section)' }}>Quantity</label>
                <input type="number" min="1" style={inputStyle} value={move.quantity}
                  onChange={(e) => setMove({ ...move, quantity: e.target.value })}
                  onFocus={(e) => Object.assign(e.target.style, focusStyle)}
                  onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }} />
              </div>

              {move.movement_type === 'stock-in' ? (
                <div>
                  <label className={labelClass} style={{ color: 'var(--clr-section)' }}>Unit Cost ($)</label>
                  <input type="number" min="0" step="0.01" style={inputStyle} value={move.unit_cost}
                    onChange={(e) => setMove({ ...move, unit_cost: e.target.value })}
                    onFocus={(e) => Object.assign(e.target.style, focusStyle)}
                    onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }} />
                </div>
              ) : (
                <>
                  <div>
                    <label className={labelClass} style={{ color: 'var(--clr-section)' }}>Department</label>
                    <input style={inputStyle} value={move.department}
                      onChange={(e) => setMove({ ...move, department: e.target.value })}
                      onFocus={(e) => Object.assign(e.target.style, focusStyle)}
                      onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }} />
                  </div>
                  <div>
                    <label className={labelClass} style={{ color: 'var(--clr-section)' }}>Purpose</label>
                    <input style={inputStyle} value={move.purpose}
                      onChange={(e) => setMove({ ...move, purpose: e.target.value })}
                      onFocus={(e) => Object.assign(e.target.style, focusStyle)}
                      onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }} />
                  </div>
                </>
              )}

              <button type="submit" disabled={saving}
                className="w-full py-2.5 rounded-lg text-sm font-bold transition-all disabled:opacity-50"
                style={{ background: '#22c55e', color: '#052e10', border: 'none', cursor: saving ? 'not-allowed' : 'pointer' }}
                onMouseEnter={(e) => { if (!saving) e.currentTarget.style.background = '#16a34a'; }}
                onMouseLeave={(e) => { e.currentTarget.style.background = '#22c55e'; }}
              >
                {saving ? 'Saving…' : 'Save Movement'}
              </button>
            </form>
          </div>

          {/* History table */}
          <div
            className="lg:col-span-3 rounded-xl overflow-hidden"
            style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}
          >
            <div className="px-5 py-4" style={{ borderBottom: '1px solid var(--clr-border)' }}>
              <h2 className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>Movement History</h2>
            </div>
            {loadingM ? <LoadingSpinner /> : (
              <DataTable
                columns={['transaction_number', 'medicine.medicine_name', 'movement_type', 'quantity', 'unit_cost', 'created_at']}
                labels={{ transaction_number: 'Ref', 'medicine.medicine_name': 'Medicine', movement_type: 'Type', quantity: 'Qty', unit_cost: 'Cost', created_at: 'Date' }}
                rows={movements} noEdit noDelete
              />
            )}
          </div>
        </div>
      )}
    </div>
  );
}
