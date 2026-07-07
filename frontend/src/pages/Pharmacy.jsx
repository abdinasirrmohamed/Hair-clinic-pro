import { useEffect, useState } from 'react';
import { useAuth } from '../context/AuthContext';
import api, { asRows } from '../api';
import Alert from '../components/ui/Alert';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import { money } from '../utils/formatters';
import { Minus, Plus, ShoppingCart, Trash2 } from 'lucide-react';

export default function Pharmacy() {
  const { lookups, refresh } = useAuth();
  const [sales,   setSales]   = useState([]);
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState('');
  const [message, setMessage] = useState('');
  const [cart,    setCart]    = useState([]);
  const [notes,   setNotes]   = useState('');
  const [saving,  setSaving]  = useState(false);
  const [paymentMethod, setPaymentMethod] = useState('Cash');
  const [accountNo, setAccountNo] = useState('');
  const [discountType, setDiscountType] = useState('None');
  const [discountValue, setDiscountValue] = useState(0);
  const [taxPercent, setTaxPercent] = useState(0);
  const [customerName, setCustomerName] = useState('');
  const [patientId, setPatientId] = useState('');

  const loadSales = async () => {
    setLoading(true);
    try {
      const { data } = await api.get('/pharmacy/sales');
      setSales(asRows(data));
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadSales(); }, []);

  const addToCart = (med) => {
    setCart((prev) => {
      const idx = prev.findIndex((i) => i.medicine_id === med.id);
      if (idx >= 0) {
        const updated = [...prev];
        updated[idx] = { ...updated[idx], quantity: updated[idx].quantity + 1 };
        return updated;
      }
      return [...prev, { medicine_id: med.id, medicine_name: med.medicine_name, unit_price: Number(med.unit_price) || 0, quantity: 1 }];
    });
  };

  const changeQty = (id, delta) => {
    setCart((prev) => prev.map((i) =>
      i.medicine_id === id ? { ...i, quantity: Math.max(1, i.quantity + delta) } : i,
    ));
  };

  const removeItem = (id) => setCart((prev) => prev.filter((i) => i.medicine_id !== id));

  const subtotal = cart.reduce((s, i) => s + i.unit_price * i.quantity, 0);
  let discountAmount = 0;
  if (discountType === 'Fixed') discountAmount = Math.min(Number(discountValue), subtotal);
  else if (discountType === 'Percentage') discountAmount = subtotal * (Math.min(Number(discountValue), 100) / 100);
  const afterDiscount = subtotal - discountAmount;
  const taxAmount = afterDiscount * (Number(taxPercent) / 100);
  const total = afterDiscount + taxAmount;

  const isMobileMethod = paymentMethod === 'EVC Plus' || paymentMethod === 'Sahal';

  const copyPhoneFromPatient = () => {
    if (!patientId) return alert('Select a linked patient first.');
    const patient = lookups?.patients?.find(p => p.id === Number(patientId));
    if (patient?.phone) {
      setAccountNo(patient.phone.replace(/\D/g, ''));
      if (!isMobileMethod) setPaymentMethod('EVC Plus');
    } else {
      alert('This patient does not have a valid phone number.');
    }
  };

  const checkout = async () => {
    if (!cart.length) return;
    setSaving(true);
    setError('');
    setMessage('');
    try {
      await api.post('/pharmacy/sales', {
        patient_id: patientId ? Number(patientId) : null,
        customer_name: customerName,
        payment_method: paymentMethod,
        account_no: (paymentMethod === 'EVC Plus' || paymentMethod === 'Sahal') ? accountNo : '',
        discount_type: discountType,
        discount_value: Number(discountValue),
        tax_percent: Number(taxPercent),
        medicines: cart.map(({ medicine_id, quantity, unit_price }) => ({ medicine_id, quantity, unit_price })),
        notes,
      });
      setCart([]);
      setNotes('');
      setPaymentMethod('Cash');
      setAccountNo('');
      setDiscountType('None');
      setDiscountValue(0);
      setTaxPercent(0);
      setCustomerName('');
      setPatientId('');
      setMessage('Sale completed successfully!');
      await loadSales();
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

  return (
    <div className="space-y-5 animate-fade-in">
      <div>
        <h1 className="text-xl font-bold" style={{ color: 'var(--clr-text)' }}>Pharmacy POS</h1>
        <p className="mt-1 text-xs" style={{ color: 'var(--clr-muted)' }}>Process sales and dispense medicines.</p>
      </div>

      {error && <Alert message={error} />}
      {message && <Alert message={message} variant="success" />}

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

        {/* Medicine picker */}
        <div
          className="lg:col-span-5 rounded-xl overflow-hidden"
          style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}
        >
          <div className="px-4 py-3.5" style={{ borderBottom: '1px solid var(--clr-border)' }}>
            <h2 className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>Medicines</h2>
          </div>
          <div className="max-h-96 overflow-y-auto">
            {(lookups?.medicines ?? []).map((med) => (
              <button key={med.id} onClick={() => addToCart(med)}
                className="w-full flex items-center justify-between px-4 py-3 text-left transition-colors"
                style={{ borderBottom: '1px solid var(--clr-border)' }}
                onMouseEnter={(e) => { e.currentTarget.style.background = 'var(--clr-hover)'; }}
                onMouseLeave={(e) => { e.currentTarget.style.background = 'transparent'; }}
              >
                <div>
                  <p className="text-sm font-medium" style={{ color: 'var(--clr-text)' }}>{med.medicine_name}</p>
                  <p className="text-xs" style={{ color: 'var(--clr-muted)' }}>{med.quantity} in stock</p>
                </div>
                <div className="text-right">
                  <p className="text-sm font-bold text-green-500">{money(Number(med.unit_price) || 0)}</p>
                  <div
                    className="mt-1 flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full"
                    style={{ background: 'var(--clr-accent-soft)', color: '#22c55e' }}
                  >
                    <Plus size={9} /> Add
                  </div>
                </div>
              </button>
            ))}
          </div>
        </div>

        {/* Cart */}
        <div
          className="lg:col-span-7 rounded-xl overflow-hidden flex flex-col"
          style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}
        >
          <div className="flex items-center gap-2 px-4 py-3.5" style={{ borderBottom: '1px solid var(--clr-border)' }}>
            <ShoppingCart size={14} className="text-green-500" />
            <h2 className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>Cart</h2>
            <span className="ml-auto text-xs" style={{ color: 'var(--clr-muted)' }}>{cart.length} items</span>
          </div>

          {cart.length === 0 ? (
            <div className="flex flex-col items-center gap-2 py-12">
              <ShoppingCart size={28} style={{ color: 'var(--clr-section)' }} />
              <p className="text-xs" style={{ color: 'var(--clr-muted)' }}>Cart is empty — add medicines</p>
            </div>
          ) : (
            <>
              <div className="flex-1 overflow-y-auto max-h-64">
                {cart.map((item) => (
                  <div key={item.medicine_id}
                    className="flex items-center gap-3 px-4 py-3"
                    style={{ borderBottom: '1px solid var(--clr-border)' }}
                  >
                    <div className="flex-1 min-w-0">
                      <p className="text-sm font-medium truncate" style={{ color: 'var(--clr-text)' }}>{item.medicine_name}</p>
                      <p className="text-xs text-green-500">{money(item.unit_price)} each</p>
                    </div>
                    <div className="flex items-center gap-1">
                      <button onClick={() => changeQty(item.medicine_id, -1)}
                        className="w-6 h-6 rounded-lg flex items-center justify-center transition-colors"
                        style={{ background: 'var(--clr-hover)', color: 'var(--clr-muted)' }}
                        onMouseEnter={(e) => { e.currentTarget.style.color = 'var(--clr-text)'; }}
                        onMouseLeave={(e) => { e.currentTarget.style.color = 'var(--clr-muted)'; }}
                      ><Minus size={11} /></button>
                      <span className="w-7 text-center text-sm font-bold" style={{ color: 'var(--clr-text)' }}>{item.quantity}</span>
                      <button onClick={() => changeQty(item.medicine_id, 1)}
                        className="w-6 h-6 rounded-lg flex items-center justify-center transition-colors"
                        style={{ background: 'var(--clr-hover)', color: 'var(--clr-muted)' }}
                        onMouseEnter={(e) => { e.currentTarget.style.color = 'var(--clr-text)'; }}
                        onMouseLeave={(e) => { e.currentTarget.style.color = 'var(--clr-muted)'; }}
                      ><Plus size={11} /></button>
                    </div>
                    <p className="w-16 text-right text-sm font-semibold text-green-500">
                      {money(item.unit_price * item.quantity)}
                    </p>
                    <button onClick={() => removeItem(item.medicine_id)}
                      className="p-1.5 rounded-lg transition-colors ml-1"
                      style={{ color: 'var(--clr-muted)' }}
                      onMouseEnter={(e) => { e.currentTarget.style.color = '#f87171'; }}
                      onMouseLeave={(e) => { e.currentTarget.style.color = 'var(--clr-muted)'; }}
                    ><Trash2 size={12} /></button>
                  </div>
                ))}
              </div>

              <div className="p-4 space-y-3">
                <div className="grid grid-cols-2 gap-3">
                  <input
                    type="text"
                    placeholder="Customer Name (optional)"
                    value={customerName}
                    onChange={(e) => setCustomerName(e.target.value)}
                    style={inputStyle}
                    onFocus={(e) => { e.target.style.borderColor = '#22c55e'; e.target.style.boxShadow = '0 0 0 3px rgba(34,197,94,.12)'; }}
                    onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }}
                  />
                  <select
                    value={patientId}
                    onChange={(e) => setPatientId(e.target.value)}
                    style={inputStyle}
                    onFocus={(e) => { e.target.style.borderColor = '#22c55e'; e.target.style.boxShadow = '0 0 0 3px rgba(34,197,94,.12)'; }}
                    onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }}
                  >
                    <option value="">Link Patient (optional)</option>
                    {(lookups?.patients ?? []).map(p => (
                      <option key={p.id} value={p.id}>{p.full_name} - {p.phone}</option>
                    ))}
                  </select>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <select
                    value={paymentMethod}
                    onChange={(e) => setPaymentMethod(e.target.value)}
                    style={inputStyle}
                    onFocus={(e) => { e.target.style.borderColor = '#22c55e'; e.target.style.boxShadow = '0 0 0 3px rgba(34,197,94,.12)'; }}
                    onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }}
                  >
                    <option value="Cash">Cash</option>
                    <option value="EVC Plus">EVC Plus</option>
                    <option value="Sahal">Sahal</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                  </select>

                  {isMobileMethod && (
                    <div className="flex gap-2">
                      <input
                        type="text"
                        placeholder="Account Number (Waafipay)"
                        value={accountNo}
                        onChange={(e) => setAccountNo(e.target.value)}
                        style={{...inputStyle, flex: 1}}
                        onFocus={(e) => { e.target.style.borderColor = '#22c55e'; e.target.style.boxShadow = '0 0 0 3px rgba(34,197,94,.12)'; }}
                        onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }}
                      />
                      <button 
                        type="button"
                        onClick={copyPhoneFromPatient}
                        className="px-3 rounded-lg flex items-center justify-center transition-colors"
                        style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)', color: 'var(--clr-muted)' }}
                        onMouseEnter={(e) => { e.currentTarget.style.color = '#22c55e'; e.currentTarget.style.borderColor = '#22c55e'; }}
                        onMouseLeave={(e) => { e.currentTarget.style.color = 'var(--clr-muted)'; e.currentTarget.style.borderColor = 'var(--clr-border)'; }}
                        title="Copy phone from linked patient"
                      >
                        <ShoppingCart size={15} />
                      </button>
                    </div>
                  )}
                </div>

                <div className="grid grid-cols-3 gap-3">
                  <select
                    value={discountType}
                    onChange={(e) => { setDiscountType(e.target.value); if (e.target.value === 'None') setDiscountValue(0); }}
                    style={inputStyle}
                    onFocus={(e) => { e.target.style.borderColor = '#22c55e'; e.target.style.boxShadow = '0 0 0 3px rgba(34,197,94,.12)'; }}
                    onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }}
                  >
                    <option value="None">No Discount</option>
                    <option value="Fixed">Fixed Amount</option>
                    <option value="Percentage">Percentage (%)</option>
                  </select>

                  <input
                    type="number"
                    placeholder="Discount Value"
                    value={discountValue}
                    onChange={(e) => setDiscountValue(e.target.value)}
                    disabled={discountType === 'None'}
                    style={{ ...inputStyle, opacity: discountType === 'None' ? 0.5 : 1 }}
                    onFocus={(e) => { if(discountType !== 'None') { e.target.style.borderColor = '#22c55e'; e.target.style.boxShadow = '0 0 0 3px rgba(34,197,94,.12)'; } }}
                    onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }}
                  />

                  <input
                    type="number"
                    placeholder="Tax % (optional)"
                    value={taxPercent}
                    onChange={(e) => setTaxPercent(e.target.value)}
                    style={inputStyle}
                    onFocus={(e) => { e.target.style.borderColor = '#22c55e'; e.target.style.boxShadow = '0 0 0 3px rgba(34,197,94,.12)'; }}
                    onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }}
                  />
                </div>

                <textarea
                  rows={2}
                  placeholder="Notes (optional)"
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  style={inputStyle}
                  onFocus={(e) => { e.target.style.borderColor = '#22c55e'; e.target.style.boxShadow = '0 0 0 3px rgba(34,197,94,.12)'; }}
                  onBlur={(e) => { e.target.style.borderColor = 'var(--clr-border)'; e.target.style.boxShadow = 'none'; }}
                />

                {isMobileMethod && accountNo && total > 0 && (
                  <div className="px-4 py-3 rounded-lg flex items-center gap-3 animate-fade-in" style={{ background: 'rgba(34, 197, 94, 0.1)', border: '1px solid rgba(34, 197, 94, 0.2)' }}>
                    <ShoppingCart size={18} className="text-green-500" />
                    <p className="text-sm" style={{ color: 'var(--clr-text)' }}>
                      Charging <strong className="text-green-500">{money(total)}</strong> to <strong>{accountNo}</strong> via <strong>{paymentMethod}</strong> — customer will receive a prompt on their phone.
                    </p>
                  </div>
                )}

                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-xs" style={{ color: 'var(--clr-muted)' }}>Total</p>
                    <p className="text-2xl font-bold text-green-500">{money(total)}</p>
                  </div>
                  <div className="flex flex-col items-end gap-2">
                    {error && <p className="text-red-500 text-xs font-semibold max-w-xs text-right animate-fade-in">{error}</p>}
                    <button
                      onClick={checkout}
                      disabled={saving}
                      className="flex items-center gap-2 px-6 py-2.5 rounded-lg text-sm font-bold transition-all disabled:opacity-50"
                      style={{ background: '#22c55e', color: '#052e10', border: 'none', cursor: saving ? 'not-allowed' : 'pointer' }}
                      onMouseEnter={(e) => { if (!saving) e.currentTarget.style.background = '#16a34a'; }}
                      onMouseLeave={(e) => { e.currentTarget.style.background = '#22c55e'; }}
                    >
                      <ShoppingCart size={15} />
                      {saving ? 'Processing…' : (isMobileMethod ? 'Send Payment Request' : 'Complete Sale')}
                    </button>
                  </div>
                </div>
              </div>
            </>
          )}
        </div>
      </div>

      {/* Recent sales */}
      <div
        className="rounded-xl overflow-hidden"
        style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}
      >
        <div className="px-5 py-4" style={{ borderBottom: '1px solid var(--clr-border)' }}>
          <h2 className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>Recent Sales</h2>
        </div>
        {loading ? <LoadingSpinner /> : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr style={{ borderBottom: '1px solid var(--clr-border)' }}>
                  {['Receipt No', 'Total', 'Status', 'Date'].map((h) => (
                    <th key={h} className="px-5 py-3 text-left text-[10px] font-semibold uppercase tracking-widest"
                      style={{ color: 'var(--clr-section)', background: 'var(--clr-card)' }}>
                      {h}
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {sales.slice(0, 10).map((s) => (
                  <tr key={s.id} style={{ borderBottom: '1px solid var(--clr-border)' }}
                    onMouseEnter={(e) => { e.currentTarget.style.background = 'var(--clr-hover)'; }}
                    onMouseLeave={(e) => { e.currentTarget.style.background = 'transparent'; }}
                  >
                    <td className="px-5 py-3 font-mono text-xs" style={{ color: 'var(--clr-text)' }}>{s.sale_number}</td>
                    <td className="px-5 py-3 font-semibold text-green-500">{money(s.total_amount)}</td>
                    <td className="px-5 py-3" style={{ color: 'var(--clr-muted)' }}>{s.payment_status}</td>
                    <td className="px-5 py-3 text-xs" style={{ color: 'var(--clr-muted)' }}>{s.created_at}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
