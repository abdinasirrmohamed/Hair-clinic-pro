import { useEffect, useMemo, useState } from 'react';
import { api, asRows } from '../api.js';
import { CrudPage } from './CrudPage.jsx';
import { modules } from '../modules.js';

const money = (value) => `$${Number(value || 0).toFixed(2)}`;

export function Dashboard({ bootstrap }) {
  const [metrics, setMetrics] = useState({});
  useEffect(() => { api('/dashboard').then(setMetrics).catch(() => {}); }, []);
  const cards = [
    ['Total Users', metrics.total_users, 'bi-people'],
    ['Total Doctors', metrics.total_doctors, 'bi-person-badge'],
    ['Total Patients', metrics.total_patients ?? metrics.assigned_patients, 'bi-person-vcard'],
    ['Appointments', metrics.total_appointments ?? metrics.today_appointments ?? metrics.today_consultations, 'bi-calendar3'],
    ['Medicines', metrics.total_medicines, 'bi-capsule'],
    ['Low Stock', metrics.low_stock_items, 'bi-exclamation-triangle'],
    ["Today's Revenue", money(metrics.revenue_today), 'bi-cash-stack'],
    ['Sales Today', metrics.pharmacy_sales_today, 'bi-receipt'],
  ].filter((item) => item[1] !== undefined);
  return (
    <>
      <div className="overview-head"><div><h1>{bootstrap.user.role} Dashboard</h1><p>Welcome back, {bootstrap.user.full_name}. Full system overview and management controls.</p></div><div className="date-pill"><i className="bi bi-calendar3" />{new Date().toLocaleDateString()}</div></div>
      <div className="metric-grid">{cards.map(([label, value, icon]) => <article className="metric-card" key={label}><div className="metric-top"><span className="metric-icon blue"><i className={`bi ${icon}`} /></span><span className="metric-chip neutral">Live</span></div><p>{label}</p><strong>{value ?? 0}</strong></article>)}</div>
      <div className="dashboard-grid">
        <section className="recent-panel"><div className="panel-title"><h2>Recent Patients</h2></div>{bootstrap.lookups.patients.slice(0, 7).map((patient) => <div className="simple-row" key={patient.id}><span className="initials">{patient.full_name.split(' ').map((part) => part[0]).slice(0, 2).join('')}</span><div><strong>{patient.full_name}</strong><small>{patient.phone}</small></div></div>)}</section>
        <aside className="appointments-panel"><div className="panel-title"><h2>System Access</h2></div><p className="dashboard-note">Your {bootstrap.user.role} account can access {bootstrap.permissions.length} modules. Menus and API requests are both protected by the same role permissions.</p></aside>
      </div>
    </>
  );
}

export function Reports({ route }) {
  const [period, setPeriod] = useState('monthly');
  const [data, setData] = useState(null);
  const [error, setError] = useState('');
  useEffect(() => { api(`/reports?period=${period}`).then(setData).catch((err) => setError(err.message)); }, [period]);
  const entries = data ? Object.entries(data.summary) : [];
  return (
    <>
      <div className="patient-head"><div><h1>{route.title}</h1><p>{route.subtitle}</p></div><select className="period-select" value={period} onChange={(e) => setPeriod(e.target.value)}><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></div>
      {error && <div className="alert alert-danger">{error}</div>}
      <div className="metric-grid">{entries.map(([label, value]) => <article className="metric-card" key={label}><p>{label.replaceAll('_', ' ')}</p><strong>{label.includes('revenue') || label.includes('expenses') || label.includes('profit') ? money(value) : value}</strong></article>)}</div>
      <section className="patient-management-card react-module-card"><div className="panel-title"><h2>Appointment Status</h2></div><div className="report-bars">{(data?.appointments_by_status || []).map((row) => <div key={row.status}><span>{row.status}</span><div><i style={{ width: `${Math.min(100, row.total * 10)}%` }} /></div><strong>{row.total}</strong></div>)}</div></section>
    </>
  );
}

export function AuditLogs({ route }) {
  const [rows, setRows] = useState([]);
  useEffect(() => { api('/audit-logs').then((data) => setRows(asRows(data))).catch(() => {}); }, []);
  return <ReadOnlyTable route={route} rows={rows} columns={['created_at', 'user_name', 'user_role', 'module_name', 'action', 'ip_address']} />;
}

export function DoctorAppointments({ route }) {
  const [rows, setRows] = useState([]);
  const [error, setError] = useState('');
  const load = () => api('/doctor/appointments').then((data) => setRows(asRows(data))).catch((err) => setError(err.message));
  useEffect(load, []);
  const change = async (id, status) => { try { await api(`/doctor/appointments/${id}/status`, { method: 'PATCH', body: JSON.stringify({ status }) }); load(); } catch (err) { setError(err.message); } };
  return <ReadOnlyTable route={route} rows={rows} error={error} columns={['appointment_date', 'appointment_time', 'patient.full_name', 'reason', 'status']} renderActions={(row) => <select value={row.status} onChange={(e) => change(row.id, e.target.value)}><option>Pending</option><option>Approved</option><option>Rejected</option><option>Completed</option></select>} />;
}

export function Prescriptions({ route, lookups, refresh }) {
  const [rows, setRows] = useState([]);
  const [form, setForm] = useState({ patient_id: '', doctor_id: '', medicine_id: '', quantity: 1, instructions: '' });
  const [error, setError] = useState('');
  const load = () => api('/prescriptions').then((data) => setRows(asRows(data))).catch((err) => setError(err.message));
  useEffect(load, []);
  const submit = async (e) => {
    e.preventDefault(); setError('');
    try { await api('/prescriptions', { method: 'POST', body: JSON.stringify({ patient_id: form.patient_id, doctor_id: form.doctor_id, medicines: [{ medicine_id: form.medicine_id, quantity: form.quantity, instructions: form.instructions }] }) }); load(); refresh(); }
    catch (err) { setError(err.message); }
  };
  return (
    <>
      <div className="patient-head"><div><h1>{route.title}</h1><p>{route.subtitle}</p></div></div>
      {error && <div className="alert alert-danger">{error}</div>}
      <section className="split-workspace"><form className="quick-form" onSubmit={submit}><h2>New Prescription</h2><Select label="Patient" value={form.patient_id} rows={lookups.patients} text="full_name" onChange={(v) => setForm({ ...form, patient_id: v })} /><Select label="Doctor" value={form.doctor_id} rows={lookups.doctors} text="full_name" onChange={(v) => setForm({ ...form, doctor_id: v })} /><Select label="Medicine" value={form.medicine_id} rows={lookups.medicines} text="medicine_name" onChange={(v) => setForm({ ...form, medicine_id: v })} /><label><span>Quantity</span><input type="number" min="1" value={form.quantity} onChange={(e) => setForm({ ...form, quantity: e.target.value })} /></label><label><span>Instructions</span><textarea value={form.instructions} onChange={(e) => setForm({ ...form, instructions: e.target.value })} /></label><button className="btn btn-primary">Create Prescription</button></form><ReadOnlyTable compact route={route} rows={rows} columns={['patient.full_name', 'doctor.full_name', 'status', 'created_at']} /></section>
    </>
  );
}

export function Pharmacy({ route, lookups, refresh }) {
  const [sales, setSales] = useState([]);
  const [cart, setCart] = useState([]);
  const [form, setForm] = useState({ customer_name: '', payment_method: 'Cash', discount_type: 'None', discount_value: 0, tax_percent: 0, account_no: '' });
  const [error, setError] = useState('');
  const load = () => api('/pharmacy/sales').then((data) => setSales(asRows(data))).catch((err) => setError(err.message));
  useEffect(load, []);
  const add = (id) => { if (id) setCart((current) => [...current, { medicine_id: Number(id), quantity: 1 }]); };
  const total = useMemo(() => cart.reduce((sum, item) => sum + Number(lookups.medicines.find((m) => m.id === item.medicine_id)?.unit_price || 0) * item.quantity, 0), [cart, lookups]);
  const submit = async (e) => {
    e.preventDefault(); setError('');
    try { await api('/pharmacy/sales', { method: 'POST', body: JSON.stringify({ ...form, medicines: cart }) }); setCart([]); load(); refresh(); }
    catch (err) { setError(err.message); }
  };
  return (
    <>
      <div className="patient-head"><div><h1>{route.title}</h1><p>{route.subtitle}</p></div></div>
      {error && <div className="alert alert-danger">{error}</div>}
      <section className="split-workspace pharmacy-workspace">
        <form className="quick-form" onSubmit={submit}><h2>New Pharmacy Sale</h2><label><span>Customer Name</span><input value={form.customer_name} onChange={(e) => setForm({ ...form, customer_name: e.target.value })} /></label><label><span>Add Medicine</span><select value="" onChange={(e) => add(e.target.value)}><option value="">Select medicine...</option>{lookups.medicines.filter((m) => m.quantity > 0).map((m) => <option value={m.id} key={m.id}>{m.medicine_name} — {m.quantity} in stock</option>)}</select></label><div className="cart-lines">{cart.map((item, index) => { const med = lookups.medicines.find((m) => m.id === item.medicine_id); return <div key={`${item.medicine_id}-${index}`}><span>{med?.medicine_name}</span><input type="number" min="1" max={med?.quantity} value={item.quantity} onChange={(e) => setCart(cart.map((line, i) => i === index ? { ...line, quantity: Number(e.target.value) } : line))} /><button type="button" onClick={() => setCart(cart.filter((_, i) => i !== index))}>×</button></div>; })}</div><label><span>Payment Method</span><select value={form.payment_method} onChange={(e) => setForm({ ...form, payment_method: e.target.value })}><option>Cash</option><option>EVC Plus</option><option>Sahal</option><option>Bank Transfer</option></select></label>{['EVC Plus', 'Sahal'].includes(form.payment_method) && <label><span>Mobile Account</span><input value={form.account_no} onChange={(e) => setForm({ ...form, account_no: e.target.value })} /></label>}<div className="sale-total"><span>Total</span><strong>{money(total)}</strong></div><button className="btn btn-primary" disabled={!cart.length}>Complete Sale</button></form>
        <ReadOnlyTable compact route={{ ...route, title: 'Recent Sales' }} rows={sales} columns={['sale_number', 'customer_name', 'total_amount', 'payment_method', 'status', 'created_at']} />
      </section>
    </>
  );
}

export function Inventory({ route, lookups, refresh }) {
  const [tab, setTab] = useState('medicines');
  const [movement, setMovement] = useState({ movement_type: 'stock-in', medicine_id: '', quantity: 1, unit_cost: 0, department: '', purpose: '' });
  const [rows, setRows] = useState([]);
  const [message, setMessage] = useState('');
  const loadMovements = () => api('/inventory/movements').then((data) => setRows(asRows(data))).catch((err) => setMessage(err.message));
  useEffect(() => { if (tab === 'movements') loadMovements(); }, [tab]);
  const submit = async (event) => {
    event.preventDefault(); setMessage('');
    const endpoint = movement.movement_type === 'stock-in' ? '/inventory/stock-in' : '/inventory/stock-out';
    const payload = movement.movement_type === 'stock-in'
      ? { medicine_id: movement.medicine_id, quantity: movement.quantity, unit_cost: movement.unit_cost }
      : { medicine_id: movement.medicine_id, quantity: movement.quantity, department: movement.department, purpose: movement.purpose };
    try { await api(endpoint, { method: 'POST', body: JSON.stringify(payload) }); setMessage('Stock movement recorded successfully.'); refresh(); loadMovements(); }
    catch (err) { setMessage(err.message); }
  };
  return (
    <>
      <div className="module-tabs"><button className={tab === 'medicines' ? 'active' : ''} onClick={() => setTab('medicines')}>Medicines</button><button className={tab === 'movements' ? 'active' : ''} onClick={() => setTab('movements')}>Stock Movements</button><button className={tab === 'suppliers' ? 'active' : ''} onClick={() => setTab('suppliers')}>Suppliers</button></div>
      {tab === 'medicines' && <CrudPage route={route} config={modules.inventory} lookups={lookups} onDataChanged={refresh} />}
      {tab === 'suppliers' && <CrudPage route={{ ...route, title: 'Suppliers', subtitle: 'Manage medicine and equipment suppliers.' }} config={modules.suppliers} lookups={lookups} />}
      {tab === 'movements' && <><div className="patient-head"><div><h1>Stock Movements</h1><p>Receive stock, issue supplies, and review inventory history.</p></div></div><section className="split-workspace"><form className="quick-form" onSubmit={submit}><h2>Record Movement</h2>{message && <div className="alert alert-info">{message}</div>}<label><span>Movement Type</span><select value={movement.movement_type} onChange={(e) => setMovement({ ...movement, movement_type: e.target.value })}><option value="stock-in">Stock In</option><option value="stock-out">Stock Out</option></select></label><Select label="Medicine" value={movement.medicine_id} rows={lookups.medicines} text="medicine_name" onChange={(value) => setMovement({ ...movement, medicine_id: value })} /><label><span>Quantity</span><input type="number" min="1" value={movement.quantity} onChange={(e) => setMovement({ ...movement, quantity: e.target.value })} /></label>{movement.movement_type === 'stock-in' ? <label><span>Unit Cost</span><input type="number" min="0" step=".01" value={movement.unit_cost} onChange={(e) => setMovement({ ...movement, unit_cost: e.target.value })} /></label> : <><label><span>Department</span><input value={movement.department} onChange={(e) => setMovement({ ...movement, department: e.target.value })} /></label><label><span>Purpose</span><input value={movement.purpose} onChange={(e) => setMovement({ ...movement, purpose: e.target.value })} /></label></>}<button className="btn btn-primary">Save Movement</button></form><ReadOnlyTable compact route={{ ...route, title: 'Movement History' }} rows={rows} columns={['transaction_number', 'medicine.medicine_name', 'movement_type', 'quantity', 'unit_cost', 'created_at']} /></section></>}
    </>
  );
}

export function Profile({ route, user, refresh }) {
  const [form, setForm] = useState({ full_name: user.full_name, old_password: '', password: '' });
  const [message, setMessage] = useState('');
  const submit = async (e) => { e.preventDefault(); try { await api('/users/profile', { method: 'PUT', body: JSON.stringify(form) }); setMessage('Profile updated successfully.'); refresh(); } catch (err) { setMessage(err.message); } };
  return <><div className="patient-head"><div><h1>{route.title}</h1><p>{route.subtitle}</p></div></div><form className="quick-form profile-form" onSubmit={submit}>{message && <div className="alert alert-info">{message}</div>}<label><span>Full Name</span><input value={form.full_name} onChange={(e) => setForm({ ...form, full_name: e.target.value })} /></label><label><span>Current Password</span><input type="password" value={form.old_password} onChange={(e) => setForm({ ...form, old_password: e.target.value })} /></label><label><span>New Password</span><input type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} /></label><button className="btn btn-primary">Save Changes</button></form></>;
}

function Select({ label, value, rows, text, onChange }) {
  return <label><span>{label}</span><select value={value} onChange={(e) => onChange(e.target.value)} required><option value="">Select...</option>{rows.map((row) => <option value={row.id} key={row.id}>{row[text]}</option>)}</select></label>;
}

function ReadOnlyTable({ route, rows, columns, renderActions, error, compact = false }) {
  const at = (row, path) => path.split('.').reduce((value, key) => value?.[key], row);
  const table = <section className="patient-management-card"><div className="list-toolbar"><div><h2>{route.title}</h2><p>{rows.length} records</p></div></div>{error && <div className="alert alert-danger">{error}</div>}<div className="responsive-table"><table className="data-table"><thead><tr>{columns.map((column) => <th key={column}>{column.split('.').pop().replaceAll('_', ' ')}</th>)}{renderActions && <th>Actions</th>}</tr></thead><tbody>{rows.map((row) => <tr key={row.id}>{columns.map((column) => <td key={column}>{column.includes('amount') ? money(at(row, column)) : String(at(row, column) ?? '—')}</td>)}{renderActions && <td>{renderActions(row)}</td>}</tr>)}</tbody></table></div></section>;
  return compact ? table : <><div className="patient-head"><div><h1>{route.title}</h1><p>{route.subtitle}</p></div></div>{table}</>;
}
