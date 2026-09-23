import { useEffect, useState } from 'react';
import api from '../api';
import { useAuth } from '../context/AuthContext';
import Alert from '../components/ui/Alert';
import { allRows, Button, Field, GridTable, control } from './PharmacyOperations';
import { money } from '../utils/formatters';

const style = { background: 'var(--clr-card)', color: 'var(--clr-text)', border: '1px solid var(--clr-border)' };
const Input = props => <input className={control} style={style} {...props} />;
function Panel({ children }) { return <section className="rounded-xl p-5 space-y-4" style={style}>{children}</section>; }

export function Stocktakes({ medicines }) {
  const { user } = useAuth();
  const [rows, setRows] = useState([]), [selected, setSelected] = useState(null);
  const [ids, setIds] = useState([]), [search, setSearch] = useState(''), [notes, setNotes] = useState('');
  const [busy, setBusy] = useState(false), [error, setError] = useState(''), [message, setMessage] = useState('');
  const load = async () => setRows(await allRows('/pharmacy/stocktakes'));
  useEffect(() => { load().catch(e => setError(e.message)); }, []);
  const act = async action => {
    setBusy(true); setError(''); setMessage('');
    try { await action(); await load(); } catch (e) { setError(e.message); } finally { setBusy(false); }
  };
  const save = async () => {
    const { data } = await api.put(`/pharmacy/stocktakes/${selected.id}`, { items: selected.items.map(i => ({ id: i.id, counted_quantity: i.counted_quantity === '' ? null : i.counted_quantity, reason: i.reason })) });
    setSelected(data);
  };
  const change = (id, key, value) => setSelected(s => ({ ...s, items: s.items.map(i => i.id === id ? { ...i, [key]: value } : i) }));
  const visible = medicines.filter(m => `${m.medicine_name} ${m.batch_number || ''}`.toLowerCase().includes(search.toLowerCase()));
  return <div className="space-y-4">
    {error && <Alert message={error} />}{message && <Alert variant="success" message={message} />}
    {!selected ? <Panel>
      <h2 className="text-lg font-bold">Start a stocktake</h2>
      <p>Choose batches, count the physical stock, and explain any difference. An administrator approves adjustments. If stock moves during counting, a recount is required.</p>
      <Field label="Notes"><Input value={notes} onChange={e => setNotes(e.target.value)} /></Field>
      <Input aria-label="Search batches to count" placeholder="Search medicine or batch" value={search} onChange={e => setSearch(e.target.value)} />
      <div className="flex gap-3"><Button onClick={() => setIds(visible.slice(0, 1000).map(m => m.id))}>Select visible batches</Button><Button onClick={() => setIds([])}>Clear selection</Button></div>
      <div className="max-h-64 overflow-auto grid sm:grid-cols-2 gap-2">{visible.map(m => <label key={m.id} className="flex items-center gap-3 rounded-lg p-2" style={style}><input type="checkbox" checked={ids.includes(m.id)} onChange={e => setIds(e.target.checked ? [...ids, m.id] : ids.filter(id => id !== m.id))} /><span>{m.medicine_name} · {m.batch_number || 'No batch'}</span></label>)}</div>
      <Button disabled={busy || !ids.length} onClick={() => act(async () => { const { data } = await api.post('/pharmacy/stocktakes', { medicine_ids: ids, notes }); setSelected(data); })}>Start count ({ids.length} batches)</Button>
    </Panel> : <Panel>
      <div className="flex flex-wrap justify-between gap-3"><h2 className="text-lg font-bold">{selected.number} · {selected.status}</h2><Button disabled={busy} onClick={() => setSelected(null)}>Back to stocktakes</Button></div>
      {selected.notes && <p>{selected.notes}</p>}
      {selected.approved_at && <p>Approved {selected.approved_at} · Administrator #{selected.approved_by}</p>}
      <GridTable columns={['Medicine / batch', 'System quantity', 'Counted', 'Difference', 'Reason']} rows={selected.items.map(i => [
        `${i.medicine_name} · ${i.batch_number || '—'}`, i.expected_quantity,
        selected.status === 'Draft' ? <Input key={i.id} aria-label={`Count ${i.medicine_name} ${i.batch_number}`} type="number" min="0" max="1000000" step="1" value={i.counted_quantity ?? ''} onChange={e => change(i.id, 'counted_quantity', e.target.value)} /> : i.counted_quantity,
        i.counted_quantity === null || i.counted_quantity === '' ? '—' : Number(i.counted_quantity) - i.expected_quantity,
        selected.status === 'Draft' ? <Input key={i.id} aria-label={`Reason ${i.medicine_name} ${i.batch_number}`} maxLength={255} value={i.reason || ''} onChange={e => change(i.id, 'reason', e.target.value)} /> : i.reason,
      ])} />
      <div className="flex flex-wrap gap-3">
        {selected.status === 'Draft' && <><Button disabled={busy} onClick={() => act(async () => { await save(); setMessage('Counts saved. Stock has not changed.'); })}>Save counts</Button><Button disabled={busy} onClick={() => act(async () => { await save(); const { data } = await api.post(`/pharmacy/stocktakes/${selected.id}/submit`); setSelected(data); setMessage('Submitted for administrator approval.'); })}>Submit for approval</Button></>}
        {selected.status === 'Submitted' && user?.role === 'Administrator' && <Button disabled={busy} onClick={() => { if (window.confirm('Apply these counted quantities to inventory?')) act(async () => { const { data } = await api.post(`/pharmacy/stocktakes/${selected.id}/approve`); setSelected(data); setMessage('Stock adjusted and movements recorded.'); }); }}>Approve & adjust stock</Button>}
        {selected.status !== 'Approved' && <Button disabled={busy} onClick={() => { if (window.confirm('Discard these counts and take a fresh stock snapshot?')) act(async () => { const { data } = await api.post(`/pharmacy/stocktakes/${selected.id}/recount`); setSelected(data); }); }}>Start recount</Button>}
      </div>
    </Panel>}
    <GridTable columns={['Stocktake', 'Status', 'Created by', 'Created', 'Action']} rows={rows.map(r => [r.number, r.status, r.creator_name, r.created_at, <Button key={r.id} disabled={busy} onClick={() => act(async () => setSelected((await api.get(`/pharmacy/stocktakes/${r.id}`)).data))}>Open</Button>])} />
  </div>;
}

export function ProfitReport() {
  const today = new Date().toLocaleDateString('en-CA');
  const [range, setRange] = useState({ from: `${today.slice(0, 7)}-01`, to: today });
  const [data, setData] = useState(null), [error, setError] = useState(''), [busy, setBusy] = useState(false);
  const load = async () => { setBusy(true); setError(''); try { setData((await api.get('/pharmacy/profit', { params: range })).data); } catch (e) { setError(e.message); } finally { setBusy(false); } };
  useEffect(() => { load(); }, []);
  const exportCsv = () => {
    const cells = [['Medicine', 'Batch', 'Quantity', 'Net revenue', 'Known cost', 'Gross profit', 'Missing cost lines'], ...data.items.map(i => [i.medicine_name, i.batch_number, i.quantity, i.net_revenue, i.cost, i.gross_profit ?? 'Unknown', i.missing_cost_lines])];
    const blob = new Blob([cells.map(row => row.map(v => `"${String(v ?? '').replace(/^[=+@-]/, "'$&").replaceAll('"', '""')}"`).join(',')).join('\n')], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = `pharmacy-profit-${data.from}-${data.to}.csv`; a.click(); URL.revokeObjectURL(url);
  };
  return <div className="space-y-4">{error && <Alert message={error} />}<Panel><h2 className="text-lg font-bold">Pharmacy gross profit</h2><p>Sales revenue after discounts, excluding tax, minus batch buying costs saved at checkout. Returned sales are excluded. Operating expenses are not deducted.</p><form className="flex flex-wrap items-end gap-3" onSubmit={e => { e.preventDefault(); load(); }}><Field label="From"><Input required type="date" value={range.from} max={range.to} onChange={e => setRange({ ...range, from: e.target.value })} /></Field><Field label="To"><Input required type="date" value={range.to} min={range.from} onChange={e => setRange({ ...range, to: e.target.value })} /></Field><Button disabled={busy}>{busy ? 'Loading...' : 'Apply dates'}</Button>{data && <Button type="button" onClick={exportCsv}>Export CSV</Button>}</form></Panel>
      {data && <><div className="grid sm:grid-cols-2 xl:grid-cols-4 gap-3">{[['Net revenue', money(data.net_revenue)], ['Known buying costs', money(data.known_cost)], ['Gross profit', data.gross_profit === null ? 'Incomplete cost history' : money(data.gross_profit)], ['Gross margin', data.margin_percent === null ? '—' : `${data.margin_percent}%`]].map(([label, value]) => <Panel key={label}><p className="text-sm">{label}</p><strong className="text-xl">{value}</strong></Panel>)}</div>
      {data.missing_cost_lines > 0 && <Alert message={`${data.missing_cost_lines} older sale line(s) have no saved buying cost (${money(data.revenue_without_cost)} revenue). Profit for known-cost lines only: ${money(data.known_cost_profit)}. Historical costs have not been guessed.`} />}
      <p className="text-sm">{data.sales} sales · {data.returned_sales_excluded} returned sales excluded · {data.from} to {data.to}</p>
      <GridTable columns={['Medicine', 'Batch', 'Sold', 'Net revenue', 'Known cost', 'Gross profit']} rows={data.items.map(i => [i.medicine_name, i.batch_number, i.quantity, money(i.net_revenue), money(i.cost), i.gross_profit === null ? 'Missing historical cost' : money(i.gross_profit)])} /></>}
    </div>;
}

export function DatabaseBackups() {
  const { user } = useAuth();
  const [data, setData] = useState(null), [error, setError] = useState(''), [message, setMessage] = useState(''), [busy, setBusy] = useState(false), [verified, setVerified] = useState(null);
  const load = async () => setData((await api.get('/backups')).data);
  useEffect(() => { if (user?.role === 'Administrator') load().catch(e => setError(e.message)); }, [user?.role]);
  const act = async fn => { setBusy(true); setError(''); setMessage(''); try { await fn(); } catch (e) { setError(e.message); } finally { setBusy(false); } };
  if (user?.role !== 'Administrator') return <p>Database backups are available to administrators.</p>;
  const alive = data?.scheduler_last_seen && Date.now() - new Date(data.scheduler_last_seen).getTime() < 180000;
  return <div className="space-y-4">{error && <Alert message={error} />}{message && <Alert message={message} variant="success" />}<Panel><h2 className="text-lg font-bold">Database backups & recovery</h2><p>Encrypted database records. Keep a separate copy of uploaded files, application code and the original APP_KEY; the key is required to decrypt backups.</p><p>Daily backup: 02:00 Africa/Mogadishu · Scheduler: <strong>{alive ? 'Active' : 'Not recently detected'}</strong></p><p className="text-xs">Backups stay on this computer. Download copies to separate storage. Restoring replaces current database records and requires maintenance mode from the server terminal.</p><div className="flex gap-3"><Button disabled={busy} onClick={() => act(async () => { const { data: b } = await api.post('/backups'); await load(); setMessage(`Backup created: ${b.name} (${b.rows} database rows).`); })}>{busy ? 'Working...' : 'Create backup now'}</Button><Button disabled={busy} onClick={() => act(load)}>Refresh</Button></div></Panel>
    {verified && <Panel><h3 className="font-bold">Backup validated · {verified.rows} rows / {verified.tables} tables</h3><p>Recovery steps: stop background workers, validate the backup, put the app into maintenance mode, restore, then bring it online. A safety backup of current records is created automatically before replacement.</p><pre className="overflow-x-auto text-xs p-3 rounded-lg" style={style}>{`php artisan clinic:restore ${verified.name} --verify\nphp artisan down\nphp artisan clinic:restore ${verified.name} --confirm=RESTORE\nphp artisan up`}</pre><p className="text-xs">Run from the backend folder. If restore fails, keep maintenance mode on and review the error before bringing the app online.</p></Panel>}
    {data && <GridTable columns={['Backup', 'Created', 'Size', 'Actions']} rows={data.backups.map(b => [b.name, b.created_at, `${(b.bytes / 1024).toFixed(1)} KB`, <div key={b.name} className="flex gap-2"><Button disabled={busy} onClick={() => act(async () => { setVerified((await api.post('/backups/verify', { name: b.name })).data); })}>Validate / recovery steps</Button><Button disabled={busy} onClick={() => act(async () => { const res = await api.get('/backups/download', { params: { name: b.name }, responseType: 'blob' }); const url = URL.createObjectURL(res.data); const a = document.createElement('a'); a.href = url; a.download = b.name; a.click(); URL.revokeObjectURL(url); })}>Download</Button></div>])} />}
  </div>;
}
