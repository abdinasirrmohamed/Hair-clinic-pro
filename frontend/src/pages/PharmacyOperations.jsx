import { useEffect, useState } from 'react';
import api, { asRows } from '../api';
import Alert from '../components/ui/Alert';
import { money } from '../utils/formatters';

const style = { background: 'var(--clr-card)', color: 'var(--clr-text)', border: '1px solid var(--clr-border)' };
export const control = 'w-full rounded-lg px-3 py-2 text-sm';
export function Button({ children, ...props }) {
  return <button {...props} className="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">{children}</button>;
}
export function Field({ label, children }) {
  return <label className="block space-y-1 text-sm"><span style={{ color: 'var(--clr-muted)' }}>{label}</span>{children}</label>;
}
export function GridTable({ columns, rows }) {
  return <div className="overflow-x-auto rounded-xl" style={style}><table className="w-full text-sm"><thead><tr>{columns.map(c => <th key={c} className="p-3 text-left whitespace-nowrap">{c}</th>)}</tr></thead><tbody>{rows.length ? rows.map((row, i) => <tr key={i} style={{ borderTop: '1px solid var(--clr-border)' }}>{row.map((cell, j) => <td key={j} className="p-3">{cell ?? '—'}</td>)}</tr>) : <tr><td colSpan={columns.length} className="p-8 text-center">No records yet.</td></tr>}</tbody></table></div>;
}
export async function allRows(url) {
  const rows = [];
  let page = 1;
  let last = 1;
  do {
    const { data } = await api.get(url, { params: { page, per_page: 100 } });
    rows.push(...asRows(data));
    last = data.last_page ?? 1;
    page++;
  } while (page <= last);
  return rows;
}
function Pager({ page, last, setPage }) {
  return <div className="flex gap-3 items-center"><Button disabled={page <= 1} onClick={() => setPage(page - 1)}>Previous</Button><span>Page {page} of {last}</span><Button disabled={page >= last} onClick={() => setPage(page + 1)}>Next</Button></div>;
}
export function Directory({ kind, onChanged }) {
  const customer = kind === 'customers';
  const endpoint = customer ? '/pharmacy/customers' : '/suppliers';
  const fields = customer ? [['full_name', 'Full name', true], ['phone', 'Phone'], ['email', 'Email'], ['address', 'Address'], ['notes', 'Notes']] : [['company_name', 'Company name', true], ['contact_person', 'Contact person'], ['phone', 'Phone', true], ['email', 'Email'], ['address', 'Address']];
  const [rows, setRows] = useState([]), [page, setPage] = useState(1), [last, setLast] = useState(1);
  const [search, setSearch] = useState(''), [query, setQuery] = useState('');
  const [form, setForm] = useState(null), [history, setHistory] = useState(null);
  const [error, setError] = useState(''), [busy, setBusy] = useState(false), [loading, setLoading] = useState(false);
  const load = async () => {
    setLoading(true); setError('');
    try { const { data } = await api.get(endpoint, { params: { page, search: query } }); setRows(asRows(data)); setLast(data.last_page || 1); }
    catch (e) { setError(e.message); } finally { setLoading(false); }
  };
  useEffect(() => { load(); }, [endpoint, page, query]);
  const save = async e => {
    e.preventDefault(); setBusy(true); setError('');
    try { await api[form.id ? 'put' : 'post'](endpoint + (form.id ? `/${form.id}` : ''), form); setForm(null); await load(); await onChanged?.(); }
    catch (e) { setError(e.message); } finally { setBusy(false); }
  };
  return <div className="space-y-4">
    <div className="flex flex-wrap justify-between gap-3"><h2 className="text-xl font-bold">{customer ? 'Customers' : 'Suppliers'}</h2><Button onClick={() => { setForm({}); setHistory(null); }}>Add {customer ? 'customer' : 'supplier'}</Button></div>
    {error && <Alert message={error} />}
    <form onSubmit={e => { e.preventDefault(); setPage(1); setQuery(search); }} className="flex gap-2"><input aria-label="Search records" className={control} style={style} placeholder={customer ? 'Search name or phone' : 'Search company'} value={search} onChange={e => setSearch(e.target.value)} /><Button>Search</Button></form>
    {form && <form onSubmit={save} className="rounded-xl p-5 space-y-4" style={style}><h3 className="font-semibold">{form.id ? 'Edit details' : 'New record'}</h3><div className="grid md:grid-cols-2 gap-4">{fields.map(([key, label, required]) => <Field key={key} label={label}><input required={required} type={key === 'email' ? 'email' : 'text'} maxLength={key === 'notes' ? 5000 : 255} className={control} style={style} value={form[key] ?? ''} onChange={e => setForm({ ...form, [key]: e.target.value })} /></Field>)}</div><div className="flex gap-2"><Button disabled={busy}>{busy ? 'Saving…' : 'Save'}</Button><Button type="button" onClick={() => setForm(null)}>Cancel</Button></div></form>}
    {loading ? <p>Loading…</p> : <GridTable columns={customer ? ['Customer', 'Phone', 'Email', 'Sales', 'Balance', 'Actions'] : ['Company', 'Contact', 'Phone', 'Email', 'Actions']} rows={rows.map(row => [row[customer ? 'full_name' : 'company_name'], ...(customer ? [row.phone, row.email, row.sales_count, money(row.outstanding_balance)] : [row.contact_person, row.phone, row.email]), <div className="flex gap-2"><Button onClick={() => setForm(row)}>Edit</Button>{customer && <Button onClick={async () => { try { setHistory((await api.get(`${endpoint}/${row.id}`)).data); } catch (e) { setError(e.message); } }}>History</Button>}</div>])} />}
    <Pager page={page} last={last} setPage={setPage} />
    {history && <div className="space-y-3"><div className="flex justify-between"><h3 className="font-bold">{history.full_name} — purchase history</h3><Button onClick={() => setHistory(null)}>Close</Button></div><GridTable columns={['Receipt', 'Date', 'Total', 'Paid', 'Balance', 'Status']} rows={(history.sales ?? []).map(s => [s.sale_number, String(s.created_at).slice(0, 10), money(s.total_amount), money(s.amount_paid), money(s.remaining_balance), s.status])} /></div>}
  </div>;
}

const today = () => new Date().toLocaleDateString('en-CA');
export function PurchaseReceiving({ medicines, onChanged, order, onOrderDone }) {
  const blank = () => ({ medicine_id: '', quantity: 1, unit_cost: '', batch_number: '', expiry_date: '' });
  const [suppliers, setSuppliers] = useState([]), [rows, setRows] = useState([]), [page, setPage] = useState(1), [last, setLast] = useState(1);
  const [form, setForm] = useState({ supplier_id: '', invoice_number: '', received_date: today(), notes: '', order_id: null, amount_paid: 0, payment_method: 'Cash', due_date: '' });
  const [items, setItems] = useState([blank()]), [busy, setBusy] = useState(false), [error, setError] = useState(''), [message, setMessage] = useState('');
  const load = async () => { try { const { data } = await api.get('/pharmacy/purchases', { params: { page } }); setRows(asRows(data)); setLast(data.last_page || 1); } catch (e) { setError(e.message); } };
  useEffect(() => { allRows('/suppliers').then(setSuppliers).catch(e => setError(e.message)); }, []);
  useEffect(() => { load(); }, [page]);
  const line = (i, patch) => setItems(items.map((x, j) => i === j ? { ...x, ...patch } : x));
  useEffect(() => {
    if (!order) return;
    setForm(f => ({...f, supplier_id:String(order.supplier_id), order_id:order.id, invoice_number:''}));
    setItems(order.items.filter(i => Number(i.quantity)>Number(i.received_quantity)).map(i => {
      const m=medicines.find(m=>m.id===i.medicine_id);
      return {medicine_id:String(i.medicine_id),quantity:i.quantity-i.received_quantity,unit_cost:i.unit_cost,batch_number:m?.batch_number||'',expiry_date:String(m?.expiry_date||'').slice(0,10)};
    }));
  }, [order]);
  const receive = async e => {
    e.preventDefault(); setBusy(true); setError(''); setMessage('');
    try { await api.post('/pharmacy/purchases', { ...form, due_date: form.due_date || null, items }); setItems([blank()]); setForm({ ...form, order_id:null, amount_paid:0, invoice_number: '', notes: '' }); onOrderDone?.(); setMessage('Purchase received. Stock and purchase history updated.'); await load(); await onChanged(); }
    catch (e) { setError(e.message); } finally { setBusy(false); }
  };
  return <div className="space-y-4"><h2 className="text-xl font-bold">Purchases & stock receiving</h2><p className="text-sm">Receive a supplier invoice. Create additional batches in Medicine Batches, then receive stock into the matching batch.</p>
    {error && <Alert message={error} />}{message && <Alert message={message} variant="success" />}
    <form onSubmit={receive} className="space-y-4 rounded-xl p-5" style={style}>
      {order && <div className="flex gap-3 items-center"><strong>Receiving {order.order_number}</strong><Button type="button" onClick={() => {onOrderDone?.();setForm({...form,order_id:null});setItems([blank()]);}}>Use standalone invoice</Button></div>}
      <div className="grid md:grid-cols-3 gap-3"><Field label="Amount already paid"><input required type="number" min="0" step="0.01" className={control} style={style} value={form.amount_paid} onChange={e=>setForm({...form,amount_paid:e.target.value})}/></Field><Field label="Payment method"><select className={control} style={style} value={form.payment_method} onChange={e=>setForm({...form,payment_method:e.target.value})}>{['Cash','Card','Bank Transfer','EVC Plus','Zaad','Sahal'].map(m=><option key={m}>{m}</option>)}</select></Field><Field label="Payment due date"><input type="date" min={form.received_date} className={control} style={style} value={form.due_date} onChange={e=>setForm({...form,due_date:e.target.value})}/></Field></div>
      <div className="grid md:grid-cols-3 gap-4"><Field label="Supplier"><select required className={control} style={style} value={form.supplier_id} onChange={e => setForm({ ...form, supplier_id: e.target.value })}><option value="">Select supplier</option>{suppliers.map(s => <option key={s.id} value={s.id}>{s.company_name}</option>)}</select></Field><Field label="Supplier invoice number"><input required className={control} style={style} value={form.invoice_number} onChange={e => setForm({ ...form, invoice_number: e.target.value })} /></Field><Field label="Received date"><input required type="date" max={today()} className={control} style={style} value={form.received_date} onChange={e => setForm({ ...form, received_date: e.target.value })} /></Field></div>
      {items.map((item, i) => <div key={i} className="grid md:grid-cols-6 gap-3 items-end"><Field label="Medicine"><select required className={control} style={style} value={item.medicine_id} onChange={e => { const m = medicines.find(m => String(m.id) === e.target.value); line(i, { medicine_id: e.target.value, unit_cost: m?.buying_price ?? '', batch_number: m?.batch_number ?? '', expiry_date: String(m?.expiry_date ?? '').slice(0, 10) }); }}><option value="">Select</option>{medicines.map(m => <option key={m.id} value={m.id}>{m.medicine_name} · {m.batch_number || 'No batch'}</option>)}</select></Field>{[['quantity', 'Quantity', 'number'], ['unit_cost', 'Unit cost', 'number'], ['batch_number', 'Batch', 'text'], ['expiry_date', 'Expiry', 'date']].map(([key, label, type]) => <Field key={key} label={label}><input required={key !== 'batch_number'} type={type} min={key === 'expiry_date' ? today() : key === 'quantity' ? 1 : 0} step={key === 'unit_cost' ? '.01' : undefined} className={control} style={style} value={item[key]} onChange={e => line(i, { [key]: e.target.value })} /></Field>)}<Button type="button" disabled={items.length === 1} onClick={() => setItems(items.filter((_, j) => j !== i))}>Remove</Button></div>)}
      <Button type="button" onClick={() => setItems([...items, blank()])}>Add medicine</Button><Field label="Notes"><input className={control} style={style} value={form.notes} onChange={e => setForm({ ...form, notes: e.target.value })} /></Field><div className="flex justify-between items-center"><strong>Total: {money(items.reduce((s, i) => s + Number(i.quantity) * Number(i.unit_cost), 0))}</strong><Button disabled={busy}>{busy ? 'Receiving…' : 'Receive stock'}</Button></div>
    </form>
    <GridTable columns={['Purchase', 'Supplier / invoice', 'Date', 'Items', 'Total']} rows={rows.map(p => [p.purchase_number, `${p.company_name} / ${p.invoice_number}`, p.received_date, p.items?.map(i => `${i.medicine_name} × ${i.quantity}`).join(', '), money(p.total_amount)])} /><Pager page={page} last={last} setPage={setPage} />
  </div>;
}

export function ExternalPrescription({ medicines, customers, onSaved, onClose }) {
  const blank = () => ({ medicine_id: '', quantity: 1, frequency: '', instructions: '' });
  const [form, setForm] = useState({ customer_id: '', prescriber_name: '', prescription_date: today(), instructions: '' });
  const [items, setItems] = useState([blank()]), [busy, setBusy] = useState(false), [error, setError] = useState('');
  const save = async e => { e.preventDefault(); setBusy(true); setError(''); try { await api.post('/pharmacy/prescriptions', { ...form, medicines: items }); await onSaved(); } catch (e) { setError(e.message); } finally { setBusy(false); } };
  return <form onSubmit={save} className="space-y-4 rounded-xl p-5" style={style}><h3 className="font-bold">Record an external prescription</h3><p className="text-sm">Enter the medicines and instructions from the customer's prescription.</p>{error && <Alert message={error} />}<div className="grid md:grid-cols-3 gap-4"><Field label="Customer"><select required className={control} style={style} value={form.customer_id} onChange={e => setForm({ ...form, customer_id: e.target.value })}><option value="">Select customer</option>{customers.map(c => <option key={c.id} value={c.id}>{c.full_name} · {c.phone}</option>)}</select></Field><Field label="Prescriber name"><input required className={control} style={style} value={form.prescriber_name} onChange={e => setForm({ ...form, prescriber_name: e.target.value })} /></Field><Field label="Prescription date"><input required type="date" max={today()} className={control} style={style} value={form.prescription_date} onChange={e => setForm({ ...form, prescription_date: e.target.value })} /></Field></div>
    {items.map((item, i) => <div key={i} className="grid md:grid-cols-5 gap-3 items-end"><Field label="Medicine"><select required className={control} style={style} value={item.medicine_id} onChange={e => setItems(items.map((x, j) => i === j ? { ...x, medicine_id: e.target.value } : x))}><option value="">Select</option>{medicines.map(m => <option key={m.id} value={m.id}>{m.medicine_name} · {m.batch_number}</option>)}</select></Field>{[['quantity', 'Quantity'], ['frequency', 'Frequency'], ['instructions', 'Instructions']].map(([key, label]) => <Field key={key} label={label}><input required type={key === 'quantity' ? 'number' : 'text'} min={1} className={control} style={style} value={item[key]} onChange={e => setItems(items.map((x, j) => i === j ? { ...x, [key]: e.target.value } : x))} /></Field>)}<Button type="button" disabled={items.length === 1} onClick={() => setItems(items.filter((_, j) => j !== i))}>Remove</Button></div>)}
    <div className="flex gap-2"><Button type="button" onClick={() => setItems([...items, blank()])}>Add medicine</Button><Button disabled={busy}>{busy ? 'Saving…' : 'Save prescription'}</Button><Button type="button" onClick={onClose}>Cancel</Button></div>
  </form>;
}

export function StockMovements({ medicines, onChanged }) {
  const [rows, setRows] = useState([]), [page, setPage] = useState(1), [last, setLast] = useState(1), [error, setError] = useState('');
  const [form, setForm] = useState({ medicine_id: '', quantity: 1, purpose: '' }), [busy, setBusy] = useState(false);
  const load = async () => { try { const { data } = await api.get('/inventory/movements', { params: { page } }); setRows(asRows(data)); setLast(data.last_page || 1); } catch (e) { setError(e.message); } };
  useEffect(() => { load(); }, [page]);
  const removeStock = async e => {
    e.preventDefault(); setBusy(true); setError('');
    try { await api.post('/inventory/stock-out', form); setForm({ medicine_id: '', quantity: 1, purpose: '' }); await load(); await onChanged(); }
    catch (e) { setError(e.message); } finally { setBusy(false); }
  };
  return <div className="space-y-4"><h2 className="text-xl font-bold">Stock movements</h2>{error && <Alert message={error} />}
    <form onSubmit={removeStock} className="space-y-3 rounded-xl p-5" style={style}><h3 className="font-bold">Remove damaged, expired, or used stock</h3><div className="grid md:grid-cols-3 gap-3"><Field label="Medicine"><select required className={control} style={style} value={form.medicine_id} onChange={e => setForm({ ...form, medicine_id: e.target.value })}><option value="">Select medicine</option>{medicines.map(m => <option key={m.id} value={m.id}>{m.medicine_name} - {m.batch_number} ({m.quantity} in stock)</option>)}</select></Field><Field label="Quantity"><input required type="number" min="1" step="1" className={control} style={style} value={form.quantity} onChange={e => setForm({ ...form, quantity: e.target.value })} /></Field><Field label="Reason"><input required className={control} style={style} value={form.purpose} onChange={e => setForm({ ...form, purpose: e.target.value })} /></Field></div><Button disabled={busy}>{busy ? 'Saving...' : 'Record stock removal'}</Button></form>
    <GridTable columns={['Reference', 'Medicine', 'Movement', 'Quantity', 'Before', 'After', 'Supplier / Reason', 'Date']} rows={rows.map(r => [r.transaction_number, r.medicine?.medicine_name, r.movement_type, r.quantity, r.old_quantity, r.new_quantity, r.supplier?.company_name || r.purpose, String(r.created_at).slice(0, 16)])} /><Pager page={page} last={last} setPage={setPage} /></div>;
}

export function BalancePayment({ sale, onSaved, onClose }) {
  const [amount, setAmount] = useState(sale.remaining_balance), [method, setMethod] = useState('Cash'), [reference, setReference] = useState(''), [busy, setBusy] = useState(false), [error, setError] = useState('');
  return <form className="space-y-3 p-5 rounded-xl" style={style} onSubmit={async e => { e.preventDefault(); setBusy(true); setError(''); try { await api.post(`/pharmacy/sales/${sale.id}/payments`, { amount, payment_method: method, reference }); await onSaved(); } catch (e) { setError(e.message); } finally { setBusy(false); } }}><h3 className="font-bold">Collect balance — {sale.sale_number}</h3>{error && <Alert message={error} />}<div className="grid md:grid-cols-3 gap-3"><Field label={`Amount (balance ${money(sale.remaining_balance)})`}><input required type="number" min="0.01" max={sale.remaining_balance} step="0.01" className={control} style={style} value={amount} onChange={e => setAmount(e.target.value)} /></Field><Field label="Payment method"><select className={control} style={style} value={method} onChange={e => setMethod(e.target.value)}>{['Cash', 'Card', 'Bank Transfer'].map(m => <option key={m}>{m}</option>)}</select></Field><Field label="Reference"><input className={control} style={style} value={reference} onChange={e => setReference(e.target.value)} /></Field></div><div className="flex gap-2"><Button disabled={busy}>{busy ? 'Saving…' : 'Record payment'}</Button><Button type="button" onClick={onClose}>Cancel</Button></div></form>;
}
