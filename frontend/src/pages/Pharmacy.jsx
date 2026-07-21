import { useEffect, useMemo, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import api, { asRows } from '../api';
import Alert from '../components/ui/Alert';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import ReceiptModal from '../components/ui/ReceiptModal';
import { money } from '../utils/formatters';
import {
  BarChart2, Boxes, CalendarDays, Download, FileText,
  History, Minus, PackagePlus, Pill, Plus, Printer, RefreshCw,
  RotateCcw, Search, ShoppingCart, Trash2, X,
} from 'lucide-react';

const tabs = [
  ['dashboard', 'Dashboard', BarChart2],
  ['medicines', 'Medicines', Pill],
  ['pos', 'POS Sales', ShoppingCart],
  ['prescriptions', 'Prescription Sales', FileText],
  ['history', 'Sales History', History],
  ['reports', 'Reports', CalendarDays],
];

const slugToSection = {
  dashboard: 'dashboard',
  medicines: 'medicines',
  'pos-sales': 'pos',
  'prescription-sales': 'prescriptions',
  'sales-history': 'history',
  reports: 'reports',
};

const sectionToSlug = Object.fromEntries(Object.entries(slugToSection).map(([slug, section]) => [section, slug]));

const emptyMedicine = {
  medicine_name: '', generic_name: '', brand: '', category: '', supplier: '',
  batch_number: '', barcode: '', buying_price: '', unit_price: '',
  quantity: 0, reorder_level: 10, expiry_date: '', description: '',
};

const isExpired = (medicine) => medicine.expiry_date && new Date(medicine.expiry_date) < new Date(new Date().toDateString());
const isLow = (medicine) => Number(medicine.quantity) <= Number(medicine.reorder_level ?? 0);

function StatCard({ label, value, tone = 'green' }) {
  const color = tone === 'red' ? '#f87171' : tone === 'amber' ? '#f59e0b' : '#7c3aed';
  return (
    <div className="rounded-xl p-4" style={{ background: 'var(--clr-card)', border: `1px solid ${color}33` }}>
      <p className="text-xs font-semibold" style={{ color }}>{label}</p>
      <p className="mt-2 text-2xl font-bold" style={{ color: 'var(--clr-text)' }}>{value}</p>
    </div>
  );
}

function SearchBox({ value, onChange, placeholder }) {
  return (
    <div className="relative">
      <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--clr-muted)' }} />
      <input
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={placeholder}
        className="w-full rounded-lg py-2 pl-9 pr-3 text-sm outline-none"
        style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)', color: 'var(--clr-text)' }}
      />
    </div>
  );
}

function exportCsv(name, rows) {
  const csv = rows.map((row) => row.map((cell) => `"${String(cell ?? '').replaceAll('"', '""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = name;
  link.click();
  URL.revokeObjectURL(link.href);
}

function printBarcode(medicine) {
  const popup = window.open('', 'barcode-print', 'width=420,height=360');
  if (!popup) return;
  popup.document.write(`
    <!doctype html><html><head><title>Barcode</title>
    <style>body{font-family:Arial,sans-serif;padding:24px;text-align:center}.code{font-size:28px;letter-spacing:4px;border:2px dashed #111;padding:18px;margin:18px 0}.name{font-weight:700}</style>
    </head><body><p class="name">${medicine.medicine_name}</p><div class="code">${medicine.barcode || medicine.id}</div><p>${money(medicine.unit_price)}</p><script>window.print();window.close();</script></body></html>
  `);
  popup.document.close();
}

function MedicineForm({ initial, onClose, onSaved }) {
  const [form, setForm] = useState({ ...emptyMedicine, ...(initial ?? {}) });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const set = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));

  const save = async (event) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    try {
      const payload = {
        medicine_name: form.medicine_name,
        generic_name: form.generic_name,
        brand: form.brand,
        category: form.category || form.brand || 'General',
        supplier: form.supplier,
        batch_number: form.batch_number,
        barcode: form.barcode,
        buying_price: Number(form.buying_price || 0),
        quantity: Number(form.quantity),
        reorder_level: Number(form.reorder_level),
        unit_price: Number(form.unit_price || form.selling_price || 0),
        expiry_date: form.expiry_date,
        description: form.description,
      };
      const body = new FormData();
      Object.entries(payload).forEach(([key, value]) => body.append(key, value ?? ''));
      if (form.image instanceof File) body.append('image', form.image);
      if (initial?.id) {
        body.append('_method', 'PUT');
        await api.post(`/medicines/${initial.id}`, body, { headers: { 'Content-Type': 'multipart/form-data' } });
      } else {
        await api.post('/medicines', body, { headers: { 'Content-Type': 'multipart/form-data' } });
      }
      onSaved();
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const fields = [
    ['medicine_name', 'Medicine Name', true],
    ['generic_name', 'Generic Name'],
    ['brand', 'Brand'],
    ['category', 'Category', true],
    ['supplier', 'Supplier', true],
    ['batch_number', 'Batch Number'],
    ['barcode', 'Barcode'],
    ['buying_price', 'Buying Price'],
    ['unit_price', 'Selling Price', true],
    ['quantity', 'Quantity', true, 'number'],
    ['reorder_level', 'Minimum Stock', true, 'number'],
    ['expiry_date', 'Expiry Date', true, 'date'],
  ];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4" style={{ background: 'rgba(2,6,23,.72)' }}>
      <form onSubmit={save} className="w-full max-w-3xl rounded-xl" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
        <div className="flex items-center justify-between px-5 py-4" style={{ borderBottom: '1px solid var(--clr-border)' }}>
          <h2 className="text-sm font-bold" style={{ color: 'var(--clr-text)' }}>{initial?.id ? 'Edit Medicine' : 'Add Medicine'}</h2>
          <button type="button" onClick={onClose} className="p-2 rounded-lg" style={{ color: 'var(--clr-muted)' }}><X size={16} /></button>
        </div>
        <div className="p-5 space-y-4">
          {error && <Alert message={error} />}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            {fields.map(([key, label, required, type = 'text']) => (
              <label key={key} className="space-y-1">
                <span className="text-xs font-semibold" style={{ color: 'var(--clr-muted)' }}>{label}</span>
                <input
                  required={required}
                  type={type}
                  value={form[key] ?? ''}
                  onChange={(e) => set(key, e.target.value)}
                  className="w-full rounded-lg px-3 py-2 text-sm outline-none"
                  style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)', color: 'var(--clr-text)' }}
                />
              </label>
            ))}
            <label className="space-y-1">
              <span className="text-xs font-semibold" style={{ color: 'var(--clr-muted)' }}>Image Upload</span>
              <input
                type="file"
                accept="image/*"
                onChange={(e) => set('image', e.target.files?.[0] ?? null)}
                className="w-full rounded-lg px-3 py-2 text-sm outline-none"
                style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)', color: 'var(--clr-text)' }}
              />
            </label>
          </div>
          <textarea
            value={form.description ?? ''}
            onChange={(e) => set('description', e.target.value)}
            placeholder="Description"
            rows={3}
            className="w-full rounded-lg px-3 py-2 text-sm outline-none"
            style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)', color: 'var(--clr-text)' }}
          />
          <div className="flex justify-end gap-2">
            <button type="button" onClick={onClose} className="px-4 py-2 rounded-lg text-sm font-semibold" style={{ color: 'var(--clr-muted)' }}>Cancel</button>
            <button disabled={saving} className="px-4 py-2 rounded-lg text-sm font-bold" style={{ background: '#7c3aed', color: '#ffffff' }}>
              {saving ? 'Saving...' : 'Save Medicine'}
            </button>
          </div>
        </div>
      </form>
    </div>
  );
}

function ReturnModal({ sale, onClose, onReturned }) {
  const [reason, setReason] = useState('');
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);

  const submit = async () => {
    if (!reason.trim()) return setError('Return reason is required.');
    setSaving(true);
    setError('');
    try {
      await api.post(`/pharmacy/sales/${sale.id}/return`, { return_reason: reason });
      onReturned();
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4" style={{ background: 'rgba(2,6,23,.72)' }}>
      <div className="w-full max-w-md rounded-xl" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
        <div className="flex items-center justify-between px-5 py-4" style={{ borderBottom: '1px solid var(--clr-border)' }}>
          <h2 className="text-sm font-bold" style={{ color: 'var(--clr-text)' }}>Refund / Return Sale</h2>
          <button onClick={onClose} className="p-2 rounded-lg" style={{ color: 'var(--clr-muted)' }}><X size={16} /></button>
        </div>
        <div className="p-5 space-y-4">
          {error && <Alert message={error} />}
          <p className="text-sm" style={{ color: 'var(--clr-muted)' }}>
            Invoice <strong style={{ color: 'var(--clr-text)' }}>{sale.sale_number}</strong> will be marked returned and stock will be restored.
          </p>
          <textarea
            rows={4}
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder="Refund / return reason"
            className="w-full rounded-lg px-3 py-2 text-sm outline-none"
            style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)', color: 'var(--clr-text)' }}
          />
          <div className="flex justify-end gap-2">
            <button onClick={onClose} className="px-4 py-2 rounded-lg text-sm font-semibold" style={{ color: 'var(--clr-muted)' }}>Cancel</button>
            <button onClick={submit} disabled={saving} className="px-4 py-2 rounded-lg text-sm font-bold" style={{ background: '#f87171', color: '#450a0a' }}>
              {saving ? 'Processing...' : 'Confirm Return'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}

export default function Pharmacy() {
  const navigate = useNavigate();
  const { section } = useParams();
  const { lookups, refresh } = useAuth();
  const active = slugToSection[section] ?? 'dashboard';
  const goSection = (target) => navigate(`/pharmacy/${sectionToSlug[target] ?? 'dashboard'}`);
  const [medicines, setMedicines] = useState([]);
  const [sales, setSales] = useState([]);
  const [prescriptions, setPrescriptions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [receipt, setReceipt] = useState(null);
  const [returnSale, setReturnSale] = useState(null);
  const [medicineForm, setMedicineForm] = useState(undefined);

  const [cart, setCart] = useState([]);
  const [search, setSearch] = useState('');
  const [barcode, setBarcode] = useState('');
  const [notes, setNotes] = useState('');
  const [saving, setSaving] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState('Cash');
  const [accountNo, setAccountNo] = useState('');
  const [discountType, setDiscountType] = useState('None');
  const [discountValue, setDiscountValue] = useState(0);
  const [taxPercent, setTaxPercent] = useState(0);
  const [customerName, setCustomerName] = useState('');
  const [patientId, setPatientId] = useState('');
  const [prescriptionContext, setPrescriptionContext] = useState(null);
  const [selectedPrescription, setSelectedPrescription] = useState(null);

  const [medicineFilter, setMedicineFilter] = useState('');
  const [medicineStatus, setMedicineStatus] = useState('all');
  const [salesFilter, setSalesFilter] = useState('');
  const [paymentFilter, setPaymentFilter] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      const [medRes, salesRes, rxRes] = await Promise.all([
        api.get('/medicines?per_page=100'),
        api.get('/pharmacy/sales?per_page=100'),
        api.get('/prescriptions?per_page=100'),
      ]);
      setMedicines(asRows(medRes.data));
      setSales(asRows(salesRes.data));
      setPrescriptions(asRows(rxRes.data));
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const today = new Date().toISOString().slice(0, 10);
  const lowStock = useMemo(() => medicines.filter(isLow), [medicines]);
  const expired = useMemo(() => medicines.filter(isExpired), [medicines]);
  const todaySales = useMemo(() => sales.filter((s) => String(s.created_at ?? '').slice(0, 10) === today), [sales, today]);
  const pendingRx = prescriptions.filter((p) => ['Pending', 'Partially Dispensed'].includes(p.status));
  const completedRx = prescriptions.filter((p) => ['Dispensed', 'Completed'].includes(p.status));

  const subtotal = cart.reduce((sum, item) => sum + Number(item.unit_price) * Number(item.quantity), 0);
  const discountAmount = discountType === 'Fixed'
    ? Math.min(Number(discountValue || 0), subtotal)
    : discountType === 'Percentage'
      ? subtotal * (Math.min(Number(discountValue || 0), 100) / 100)
      : 0;
  const taxAmount = (subtotal - discountAmount) * (Number(taxPercent || 0) / 100);
  const total = subtotal - discountAmount + taxAmount;
  const isMobileMethod = ['EVC Plus', 'Zaad', 'Sahal'].includes(paymentMethod);

  const filteredMedicines = medicines.filter((medicine) => {
    const q = medicineFilter.toLowerCase();
    const matches = !q || [medicine.medicine_name, medicine.generic_name, medicine.category, medicine.batch_number, medicine.barcode, medicine.supplier]
      .some((value) => String(value ?? '').toLowerCase().includes(q));
    const statusMatch = medicineStatus === 'all'
      || (medicineStatus === 'low' && isLow(medicine))
      || (medicineStatus === 'expired' && isExpired(medicine))
      || (medicineStatus === 'active' && !isLow(medicine) && !isExpired(medicine));
    return matches && statusMatch;
  });

  const filteredSales = sales.filter((sale) => {
    const q = salesFilter.toLowerCase();
    const matches = !q || [sale.sale_number, sale.customer_name, sale.patient?.full_name, sale.prescription?.prescription_number]
      .some((value) => String(value ?? '').toLowerCase().includes(q));
    const payment = !paymentFilter || sale.payment_method === paymentFilter;
    const after = !dateFrom || String(sale.created_at ?? '').slice(0, 10) >= dateFrom;
    const before = !dateTo || String(sale.created_at ?? '').slice(0, 10) <= dateTo;
    return matches && payment && after && before;
  });

  const addToCart = (medicine, qty = 1) => {
    setCart((prev) => {
      const idx = prev.findIndex((item) => item.medicine_id === medicine.id);
      if (idx >= 0) {
        const next = [...prev];
        next[idx] = { ...next[idx], quantity: Math.max(1, Number(next[idx].quantity) + qty) };
        return next;
      }
      return [...prev, {
        medicine_id: medicine.id,
        medicine_name: medicine.medicine_name,
        barcode: medicine.barcode,
        unit_price: Number(medicine.unit_price) || 0,
        quantity: qty,
        stock: Number(medicine.quantity) || 0,
      }];
    });
  };

  const runBarcodeSearch = () => {
    const found = medicines.find((medicine) => String(medicine.barcode ?? '').toLowerCase() === barcode.toLowerCase());
    if (found) {
      addToCart(found);
      setBarcode('');
    } else {
      setError('Medicine barcode was not found.');
    }
  };

  const suggestions = search
    ? medicines.filter((medicine) => [medicine.medicine_name, medicine.generic_name, medicine.barcode]
      .some((value) => String(value ?? '').toLowerCase().includes(search.toLowerCase()))).slice(0, 8)
    : medicines.slice(0, 8);

  const checkout = async () => {
    if (!cart.length) return setError('Cart is empty.');
    setSaving(true);
    setError('');
    setMessage('');
    try {
      const { data } = await api.post('/pharmacy/sales', {
        patient_id: patientId ? Number(patientId) : null,
        prescription_id: prescriptionContext?.id ?? null,
        customer_name: customerName,
        payment_method: paymentMethod,
        account_no: isMobileMethod ? accountNo : '',
        discount_type: discountType,
        discount_value: Number(discountValue || 0),
        tax_percent: Number(taxPercent || 0),
        medicines: cart.map(({ medicine_id, quantity, prescription_medicine_id }) => ({ medicine_id, quantity, prescription_medicine_id })),
        notes,
      });
      const loaded = await api.get(`/pharmacy/sales/${data.id}`);
      setReceipt(loaded.data);
      setCart([]);
      setNotes('');
      setPaymentMethod('Cash');
      setAccountNo('');
      setDiscountType('None');
      setDiscountValue(0);
      setTaxPercent(0);
      setCustomerName('');
      setPatientId('');
      setPrescriptionContext(null);
      setMessage('Sale completed, stock reduced, and invoice generated.');
      await load();
      refresh();
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  const viewPrescription = async (prescription) => {
    setError('');
    setMessage('');
    try {
      const { data } = await api.get(`/prescriptions/${prescription.id}`);
      setSelectedPrescription(data);
    } catch (err) {
      setError(err.message);
    }
  };

  const proceedPrescriptionPayment = (prescription = selectedPrescription) => {
    if (!prescription) return;
    setPrescriptionContext(prescription);
    setPatientId(String(prescription.patient_id ?? ''));
    setCustomerName(prescription.patient?.full_name ?? '');
    setCart((prescription.medicines ?? []).map((item) => ({
      medicine_id: item.medicine_id,
      prescription_medicine_id: item.id,
      medicine_name: item.medicine?.medicine_name ?? 'Medicine',
      barcode: item.medicine?.barcode,
      unit_price: Number(item.medicine?.unit_price) || 0,
      quantity: Math.max(0, Number(item.quantity) - Number(item.dispensed_quantity || 0)),
      stock: Number(item.medicine?.quantity) || 0,
      frequency: item.frequency,
      instructions: item.instructions,
    })).filter((item) => item.quantity > 0));
    goSection('pos');
  };

  const selectPosPatient = (value) => {
    setPatientId(value);
    setPrescriptionContext(null);
    setCart([]);
    const patient = (lookups?.patients ?? []).find((item) => String(item.id) === String(value));
    setCustomerName(patient?.full_name ?? '');
    if (!value) return;

    const prescription = pendingRx.find((item) => String(item.patient_id) === String(value));
    if (prescription) {
      proceedPrescriptionPayment(prescription);
      setMessage(`Loaded ${prescription.medicines?.length ?? 0} prescribed medicine(s) for ${patient?.full_name ?? 'the selected patient'}.`);
    } else {
      setMessage('No pending prescription was found for the selected patient.');
    }
  };

  const deleteMedicine = async (medicine) => {
    if (!window.confirm(`Delete ${medicine.medicine_name}?`)) return;
    await api.delete(`/medicines/${medicine.id}`);
    await load();
    refresh();
  };

  const renderDashboard = () => {
    const dailyRevenue = todaySales.reduce((sum, sale) => sum + Number(sale.total_amount), 0);
    const recentDispensed = prescriptions.filter((p) => ['Dispensed', 'Completed'].includes(p.status)).slice(0, 5);
    const bars = [
      ['Daily Sales', todaySales.length],
      ['Weekly Sales', sales.filter((s) => new Date(s.created_at) >= new Date(Date.now() - 7 * 86400000)).length],
      ['Monthly Revenue', Math.round(sales.filter((s) => new Date(s.created_at) >= new Date(Date.now() - 30 * 86400000)).reduce((sum, s) => sum + Number(s.total_amount), 0))],
    ];
    const max = Math.max(...bars.map(([, value]) => value), 1);

    return (
      <div className="space-y-5">
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard label="Today's Sales" value={todaySales.length} />
          <StatCard label="Today's Revenue" value={money(dailyRevenue)} />
          <StatCard label="Total Medicines" value={medicines.length} />
          <StatCard label="Low Stock Medicines" value={lowStock.length} tone="amber" />
          <StatCard label="Expired Medicines" value={expired.length} tone="red" />
          <StatCard label="Pending Prescriptions" value={pendingRx.length} tone="amber" />
          <StatCard label="Completed Prescriptions" value={completedRx.length} />
        </div>

        {(lowStock.length > 0 || expired.length > 0) && (
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {lowStock.length > 0 && <Alert message={`${lowStock.length} medicines are low stock.`} variant="warning" />}
            {expired.length > 0 && <Alert message={`${expired.length} medicines are expired.`} />}
          </div>
        )}

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
          {bars.map(([label, value]) => (
            <div key={label} className="rounded-xl p-4" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
              <p className="text-xs font-semibold" style={{ color: 'var(--clr-muted)' }}>{label}</p>
              <div className="mt-4 h-3 rounded-full overflow-hidden" style={{ background: 'var(--clr-hover)' }}>
                <div className="h-full rounded-full" style={{ width: `${Math.max(8, (value / max) * 100)}%`, background: '#7c3aed' }} />
              </div>
              <p className="mt-2 text-lg font-bold" style={{ color: 'var(--clr-text)' }}>{label.includes('Revenue') ? money(value) : value}</p>
            </div>
          ))}
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <ListCard title="Recent Sales" rows={sales.slice(0, 6).map((s) => [s.sale_number, money(s.total_amount)])} />
          <ListCard title="Recently Dispensed Prescriptions" rows={recentDispensed.map((p) => [p.prescription_number, p.patient?.full_name])} />
        </div>

        <div className="flex flex-wrap gap-2">
          <ActionButton onClick={() => goSection('pos')} icon={ShoppingCart} label="New Sale" />
          <ActionButton onClick={() => goSection('prescriptions')} icon={FileText} label="Open Prescription" />
          <ActionButton onClick={() => setMedicineForm(null)} icon={PackagePlus} label="Add Medicine" />
        </div>
      </div>
    );
  };

  const renderMedicines = () => (
    <div className="space-y-4">
      <div className="flex flex-col lg:flex-row gap-3 lg:items-center justify-between">
        <div className="flex-1"><SearchBox value={medicineFilter} onChange={setMedicineFilter} placeholder="Search medicines, barcode, batch, supplier..." /></div>
        <select value={medicineStatus} onChange={(e) => setMedicineStatus(e.target.value)} className="rounded-lg px-3 py-2 text-sm" style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)', color: 'var(--clr-text)' }}>
          <option value="all">All Status</option>
          <option value="active">Active</option>
          <option value="low">Low Stock</option>
          <option value="expired">Expired</option>
        </select>
        <ActionButton onClick={() => setMedicineForm(null)} icon={Plus} label="Add Medicine" />
        <ActionButton onClick={() => exportCsv('medicines.csv', [['Medicine', 'Generic', 'Category', 'Barcode', 'Qty', 'Price', 'Expiry'], ...filteredMedicines.map((m) => [m.medicine_name, m.generic_name, m.category, m.barcode, m.quantity, m.unit_price, m.expiry_date])])} icon={Download} label="Export" muted />
      </div>
      <Table
        columns={['Image', 'Medicine', 'Generic', 'Brand', 'Category', 'Batch', 'Barcode', 'Buying', 'Selling', 'Qty', 'Minimum', 'Expiry', 'Supplier', 'Status', 'Actions']}
        rows={filteredMedicines.map((medicine) => [
          medicine.image_path ? <img key="image" src={`/storage/${medicine.image_path}`} alt="" className="w-9 h-9 rounded-lg object-cover" /> : <div key="image" className="w-9 h-9 rounded-lg flex items-center justify-center" style={{ background: 'var(--clr-hover)' }}><Pill size={15} /></div>,
          medicine.medicine_name,
          medicine.generic_name || '-',
          medicine.brand || '-',
          medicine.category,
          medicine.batch_number || '-',
          medicine.barcode || '-',
          money(medicine.buying_price || 0),
          money(medicine.unit_price),
          medicine.quantity,
          medicine.reorder_level,
          medicine.expiry_date,
          medicine.supplier,
          <Status key="status" tone={isExpired(medicine) ? 'red' : isLow(medicine) ? 'amber' : 'green'} label={isExpired(medicine) ? 'Expired' : isLow(medicine) ? 'Low Stock' : 'Active'} />,
          <div key="actions" className="flex justify-end gap-1">
            <IconButton title="Print Barcode" icon={Printer} onClick={() => printBarcode(medicine)} />
            <IconButton title="Edit" icon={PackagePlus} onClick={() => setMedicineForm(medicine)} />
            <IconButton title="Delete" icon={Trash2} danger onClick={() => deleteMedicine(medicine)} />
          </div>,
        ])}
      />
    </div>
  );

  const renderPos = () => (
    <div className="grid grid-cols-1 xl:grid-cols-12 gap-5 items-start">
      <div className="xl:col-span-5 space-y-4">
        {prescriptionContext && (
          <Alert variant="success" message={`Prescription ${prescriptionContext.prescription_number} loaded for ${prescriptionContext.patient?.full_name}.`} />
        )}
        <Card title="Search Medicine">
          <div className="space-y-3">
            <SearchBox value={search} onChange={setSearch} placeholder="Search by name or generic name..." />
            <div className="flex gap-2">
              <input
                value={barcode}
                onChange={(e) => setBarcode(e.target.value)}
                onKeyDown={(e) => { if (e.key === 'Enter') runBarcodeSearch(); }}
                placeholder="Scan or enter barcode"
                className="flex-1 rounded-lg px-3 py-2 text-sm outline-none"
                style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)', color: 'var(--clr-text)' }}
              />
              <ActionButton onClick={runBarcodeSearch} icon={Search} label="Find" muted />
            </div>
            <div className="max-h-[28rem] overflow-y-auto">
              {suggestions.map((medicine) => (
                <button key={medicine.id} onClick={() => addToCart(medicine)} className="w-full flex items-center justify-between px-3 py-3 rounded-lg text-left hover:opacity-90" style={{ borderBottom: '1px solid var(--clr-border)' }}>
                  <div>
                    <p className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>{medicine.medicine_name}</p>
                    <p className="text-xs" style={{ color: isExpired(medicine) || isLow(medicine) ? '#f59e0b' : 'var(--clr-muted)' }}>
                      {medicine.barcode || 'No barcode'} - {medicine.quantity} in stock
                    </p>
                  </div>
                  <span className="text-sm font-bold text-violet-600">{money(medicine.unit_price)}</span>
                </button>
              ))}
            </div>
          </div>
        </Card>
      </div>

      <div className="xl:col-span-7">
        <Card title={prescriptionContext ? 'Prescription Sale Lines' : 'Walk-in Sale Lines'}>
          <div className="space-y-3">
            {cart.length === 0 ? (
              <EmptyState text="No sale lines added." />
            ) : (
              <div className="overflow-x-auto rounded-xl" style={{ border: '1px solid var(--clr-border)' }}>
                <div
                  className="grid grid-cols-[minmax(180px,2fr)_80px_100px_150px_100px_44px] gap-3 px-3 py-2 text-[10px] font-bold uppercase tracking-widest"
                  style={{ color: 'var(--clr-section)', background: 'var(--clr-search-bg)', borderBottom: '1px solid var(--clr-border)' }}
                >
                  <span>Medicine</span>
                  <span>Stock</span>
                  <span>Price</span>
                  <span>Quantity</span>
                  <span className="text-right">Subtotal</span>
                  <span />
                </div>
                {cart.map((item) => (
                  <div
                    key={item.medicine_id}
                    className="grid grid-cols-[minmax(180px,2fr)_80px_100px_150px_100px_44px] gap-3 items-center px-3 py-2"
                    style={{ borderBottom: '1px solid var(--clr-border)' }}
                  >
                    <div className="min-w-0">
                      <p className="text-sm font-semibold truncate" style={{ color: 'var(--clr-text)' }}>{item.medicine_name}</p>
                      {item.barcode && <p className="text-[11px] font-mono" style={{ color: 'var(--clr-muted)' }}>{item.barcode}</p>}
                      {item.frequency && <p className="text-[11px]" style={{ color: 'var(--clr-muted)' }}>Frequency: {item.frequency}</p>}
                      {item.instructions && <p className="text-[11px]" style={{ color: 'var(--clr-muted)' }}>{item.instructions}</p>}
                    </div>
                    <span className="text-sm" style={{ color: item.quantity > item.stock ? '#f87171' : 'var(--clr-muted)' }}>{item.stock}</span>
                    <span className="text-sm font-semibold text-violet-600">{money(item.unit_price)}</span>
                    <div className="flex items-center gap-1">
                      <IconButton icon={Minus} title="Decrease" onClick={() => setCart((prev) => prev.map((i) => i.medicine_id === item.medicine_id ? { ...i, quantity: Math.max(1, i.quantity - 1) } : i))} />
                      <input
                        type="number"
                        min="1"
                        value={item.quantity}
                        onChange={(e) => setCart((prev) => prev.map((i) => i.medicine_id === item.medicine_id ? { ...i, quantity: Math.max(1, Number(e.target.value)) } : i))}
                        className="w-16 rounded-lg px-2 py-1 text-center text-sm"
                        style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)', color: 'var(--clr-text)' }}
                      />
                      <IconButton icon={Plus} title="Increase" onClick={() => setCart((prev) => prev.map((i) => i.medicine_id === item.medicine_id ? { ...i, quantity: i.quantity + 1 } : i))} />
                    </div>
                    <span className="text-right text-sm font-bold" style={{ color: 'var(--clr-text)' }}>{money(item.unit_price * item.quantity)}</span>
                    <div className="text-right"><IconButton icon={Trash2} danger title="Remove line" onClick={() => setCart((prev) => prev.filter((i) => i.medicine_id !== item.medicine_id))} /></div>
                  </div>
                ))}
              </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
              <input value={customerName} onChange={(e) => setCustomerName(e.target.value)} placeholder="Customer name" className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()} />
              <select value={patientId} onChange={(e) => selectPosPatient(e.target.value)} className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()}>
                <option value="">Walk-in / no patient</option>
                {(lookups?.patients ?? []).map((p) => <option key={p.id} value={p.id}>{p.full_name} - {p.phone}</option>)}
              </select>
              {patientId && (
                <select
                  value={prescriptionContext?.id ?? ''}
                  onChange={async (e) => {
                    const prescription = pendingRx.find((item) => String(item.id) === e.target.value);
                    if (prescription) {
                      const { data } = await api.get(`/prescriptions/${prescription.id}`);
                      setSelectedPrescription(data);
                      proceedPrescriptionPayment(data);
                    }
                  }}
                  className="rounded-lg px-3 py-2 text-sm outline-none"
                  style={inputStyle()}
                >
                  <option value="">Load patient prescription</option>
                  {pendingRx.filter((item) => String(item.patient_id) === String(patientId)).map((item) => (
                    <option key={item.id} value={item.id}>{item.prescription_number} · {item.medicines_count ?? item.medicines?.length ?? 0} medicines</option>
                  ))}
                </select>
              )}
              <select value={paymentMethod} onChange={(e) => setPaymentMethod(e.target.value)} className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()}>
                {['Cash', 'Card', 'EVC Plus', 'Zaad', 'Sahal', 'Bank Transfer', 'Mixed Payment'].map((method) => <option key={method}>{method}</option>)}
              </select>
              {isMobileMethod && <input value={accountNo} onChange={(e) => setAccountNo(e.target.value)} placeholder="Mobile account number" className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()} />}
              <select value={discountType} onChange={(e) => setDiscountType(e.target.value)} className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()}>
                {['None', 'Fixed', 'Percentage'].map((type) => <option key={type}>{type}</option>)}
              </select>
              <input value={discountValue} onChange={(e) => setDiscountValue(e.target.value)} type="number" placeholder="Discount" className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()} />
              <input value={taxPercent} onChange={(e) => setTaxPercent(e.target.value)} type="number" placeholder="Tax %" className="rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()} />
            </div>
            <textarea value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Notes" rows={2} className="w-full rounded-lg px-3 py-2 text-sm outline-none" style={inputStyle()} />
            <div className="rounded-xl p-4 space-y-2" style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)' }}>
              <SummaryLine label="Subtotal" value={money(subtotal)} />
              <SummaryLine label="Discount" value={money(discountAmount)} />
              <SummaryLine label="Tax" value={money(taxAmount)} />
              <SummaryLine label="Grand Total" value={money(total)} strong />
            </div>
            <div className="flex flex-wrap justify-end gap-2">
              <ActionButton onClick={() => setCart([])} icon={X} label="Clear Lines" muted />
              <ActionButton onClick={() => setMessage('Sale held locally. Keep this tab open to continue.')} icon={History} label="Hold Sale" muted />
              <ActionButton onClick={checkout} icon={FileText} label={saving ? 'Processing...' : 'Complete Sale'} />
            </div>
          </div>
        </Card>
      </div>
    </div>
  );

  const renderPrescriptions = () => {
    const query = salesFilter.toLowerCase();
    const visiblePrescriptions = pendingRx.filter((p) => !query || [
      p.prescription_number,
      p.patient?.full_name,
      p.patient?.phone,
      p.doctor?.full_name,
    ].some((value) => String(value ?? '').toLowerCase().includes(query)));
    const selectedMedicines = selectedPrescription?.medicines ?? [];
    const selectedTotal = selectedMedicines.reduce((sum, item) => (
      sum + (Number(item.medicine?.unit_price) || 0) * (Number(item.quantity) || 1)
    ), 0);

    return (
      <div className="grid grid-cols-1 xl:grid-cols-12 gap-5 items-start">
        <div className="xl:col-span-7 space-y-4">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            <SearchBox value={salesFilter} onChange={setSalesFilter} placeholder="Search patient or prescription..." />
          </div>
          <Table
            columns={['Prescription', 'Patient', 'Doctor', 'Date', 'Items', 'Status', 'Action']}
            rows={visiblePrescriptions.map((p) => [
              <span key="rx" className="font-mono text-xs text-violet-600">{p.prescription_number}</span>,
              <div key="patient">
                <p className="font-semibold">{p.patient?.full_name || '-'}</p>
                <p className="text-[11px]" style={{ color: 'var(--clr-muted)' }}>{p.patient?.phone || ''}</p>
              </div>,
              p.doctor?.full_name || '-',
              p.prescription_date || '-',
              `${p.medicines_count ?? p.medicines?.length ?? 0} item(s)`,
              <Status key="status" label={p.status} tone="amber" />,
              <ActionButton key="action" onClick={() => viewPrescription(p)} icon={FileText} label="View Medicines" muted />,
            ])}
          />
        </div>

        <div className="xl:col-span-5">
          <Card title="Prescribed Medicines">
            {!selectedPrescription ? (
              <EmptyState text="Click a prescription to view prescribed medicines." />
            ) : (
              <div className="space-y-4">
                <div className="flex items-start justify-between gap-4">
                  <div>
                    <p className="font-mono text-xs text-violet-600">{selectedPrescription.prescription_number}</p>
                    <h3 className="text-sm font-bold" style={{ color: 'var(--clr-text)' }}>{selectedPrescription.patient?.full_name || '-'}</h3>
                    <p className="text-xs" style={{ color: 'var(--clr-muted)' }}>
                      {selectedPrescription.doctor?.full_name || '-'} - {selectedPrescription.prescription_date || '-'}
                    </p>
                  </div>
                  <Status label={selectedPrescription.status} tone="amber" />
                </div>

                {selectedPrescription.instructions && (
                  <div className="rounded-lg p-3 text-xs" style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)', color: 'var(--clr-muted)' }}>
                    {selectedPrescription.instructions}
                  </div>
                )}

                <div className="space-y-2">
                  {selectedMedicines.length === 0 ? <EmptyState text="No medicines on this prescription." /> : selectedMedicines.map((item) => {
                    const qty = Number(item.quantity) || 1;
                    const stock = Number(item.medicine?.quantity) || 0;
                    const price = Number(item.medicine?.unit_price) || 0;
                    const unavailable = !item.medicine || stock < qty;
                    return (
                      <div key={item.id ?? item.medicine_id} className="rounded-lg p-3" style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)' }}>
                        <div className="flex items-start justify-between gap-3">
                          <div className="min-w-0">
                            <p className="text-sm font-bold truncate" style={{ color: 'var(--clr-text)' }}>{item.medicine?.medicine_name || 'Medicine not found'}</p>
                            <p className="text-[11px]" style={{ color: unavailable ? '#f87171' : 'var(--clr-muted)' }}>
                              Qty {qty} - Stock {stock} {unavailable ? '- unavailable or low stock' : ''}
                            </p>
                            {item.instructions && <p className="mt-1 text-xs" style={{ color: 'var(--clr-muted)' }}>{item.instructions}</p>}
                            {item.frequency && <p className="mt-1 text-xs" style={{ color: 'var(--clr-muted)' }}>Frequency: {item.frequency}</p>}
                          </div>
                          <div className="text-right">
                            <p className="text-sm font-bold text-violet-600">{money(price * qty)}</p>
                            <p className="text-[11px]" style={{ color: 'var(--clr-muted)' }}>{money(price)} each</p>
                          </div>
                        </div>
                      </div>
                    );
                  })}
                </div>

                <div className="rounded-xl p-4 space-y-2" style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)' }}>
                  <SummaryLine label="Medicine Items" value={selectedMedicines.length} />
                  <SummaryLine label="Estimated Total" value={money(selectedTotal)} strong />
                </div>

                <div className="flex justify-end">
                  <ActionButton onClick={() => proceedPrescriptionPayment()} icon={ShoppingCart} label="Proceed to Payment" />
                </div>
              </div>
            )}
          </Card>
        </div>
      </div>
    );
  };

  const renderHistory = () => (
    <div className="space-y-4">
      <div className="grid grid-cols-1 md:grid-cols-5 gap-3">
        <SearchBox value={salesFilter} onChange={setSalesFilter} placeholder="Search invoice, customer, prescription..." />
        <input type="date" value={dateFrom} onChange={(e) => setDateFrom(e.target.value)} className="rounded-lg px-3 py-2 text-sm" style={inputStyle()} />
        <input type="date" value={dateTo} onChange={(e) => setDateTo(e.target.value)} className="rounded-lg px-3 py-2 text-sm" style={inputStyle()} />
        <select value={paymentFilter} onChange={(e) => setPaymentFilter(e.target.value)} className="rounded-lg px-3 py-2 text-sm" style={inputStyle()}>
          <option value="">All Payments</option>
          {['Cash', 'Card', 'EVC Plus', 'Zaad', 'Sahal', 'Bank Transfer', 'Mixed Payment'].map((method) => <option key={method}>{method}</option>)}
        </select>
        <ActionButton onClick={() => exportCsv('pharmacy-sales.csv', [['Invoice', 'Date', 'Customer', 'Type', 'Payment', 'Cashier', 'Total', 'Status'], ...filteredSales.map((s) => [s.sale_number, s.created_at, s.customer_name || s.patient?.full_name, s.prescription_id ? 'Prescription' : 'Walk-In', s.payment_method, s.creator?.full_name, s.total_amount, s.status])])} icon={Download} label="Export" muted />
      </div>
      <Table
        columns={['Invoice', 'Date', 'Customer', 'Type', 'Payment', 'Cashier', 'Total', 'Status', 'Actions']}
        rows={filteredSales.map((sale) => [
          sale.sale_number,
          sale.created_at,
          sale.customer_name || sale.patient?.full_name || 'Walk-in',
          sale.prescription_id ? 'Prescription' : 'Walk-In',
          sale.payment_method,
          sale.creator?.full_name || '-',
          money(sale.total_amount),
          <Status key="status" label={sale.status} tone={sale.status === 'Returned' ? 'red' : 'green'} />,
          <div key="actions" className="flex justify-end gap-1">
            <IconButton icon={FileText} title="View / Print" onClick={async () => setReceipt((await api.get(`/pharmacy/sales/${sale.id}`)).data)} />
            <IconButton icon={RotateCcw} title="Refund" danger={sale.status !== 'Returned'} onClick={() => sale.status !== 'Returned' && setReturnSale(sale)} />
          </div>,
        ])}
      />
    </div>
  );

  const renderReports = () => {
    const topSelling = medicines.map((medicine) => {
      const qty = sales.reduce((sum, sale) => sum + Number((sale.medicines ?? []).find?.((m) => m.medicine_id === medicine.id)?.quantity ?? 0), 0);
      return [medicine.medicine_name, qty];
    }).sort((a, b) => b[1] - a[1]).slice(0, 5);
    const revenue = filteredSales.reduce((sum, sale) => sum + Number(sale.total_amount), 0);
    const prescriptionSales = filteredSales.filter((sale) => sale.prescription_id).length;
    const walkInSales = filteredSales.filter((sale) => !sale.prescription_id).length;

    return (
      <div className="space-y-5">
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard label="Filtered Sales" value={filteredSales.length} />
          <StatCard label="Revenue" value={money(revenue)} />
          <StatCard label="Prescription Sales" value={prescriptionSales} />
          <StatCard label="Walk-in Sales" value={walkInSales} />
          <StatCard label="Low Stock" value={lowStock.length} tone="amber" />
          <StatCard label="Expired Medicines" value={expired.length} tone="red" />
        </div>
        <div className="flex flex-wrap gap-2">
          <ActionButton onClick={() => exportCsv('pharmacy-report.csv', [['Metric', 'Value'], ['Sales', filteredSales.length], ['Revenue', revenue], ['Low Stock', lowStock.length], ['Expired', expired.length]])} icon={Download} label="Export CSV" muted />
          <ActionButton onClick={() => window.print()} icon={Printer} label="Print" muted />
        </div>
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <ListCard title="Top Selling Medicines" rows={topSelling} />
          <ListCard title="Low Stock Report" rows={lowStock.slice(0, 8).map((m) => [m.medicine_name, `${m.quantity} left`])} />
          <ListCard title="Expired Medicines" rows={expired.slice(0, 8).map((m) => [m.medicine_name, m.expiry_date])} />
          <ListCard title="Refund / Reconciliation" rows={sales.filter((s) => s.status === 'Returned').slice(0, 8).map((s) => [s.sale_number, money(s.total_amount)])} />
        </div>
      </div>
    );
  };

  const content = {
    dashboard: renderDashboard,
    medicines: renderMedicines,
    pos: renderPos,
    prescriptions: renderPrescriptions,
    history: renderHistory,
    reports: renderReports,
  }[active];

  return (
    <div className="animate-fade-in">
      <main className="space-y-5 min-w-0">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-3">
          <div>
            <h1 className="text-xl font-bold" style={{ color: 'var(--clr-text)' }}>{tabs.find(([key]) => key === active)?.[1] ?? 'Pharmacy'}</h1>
            <p className="text-xs mt-1" style={{ color: 'var(--clr-muted)' }}>Pharmacy operations, sales, stock, prescriptions, and reports.</p>
          </div>
          <ActionButton onClick={load} icon={RefreshCw} label="Refresh" muted />
        </div>
        {error && <Alert message={error} />}
        {message && <Alert message={message} variant="success" />}
        {loading ? <LoadingSpinner /> : content()}
      </main>

      {receipt && <ReceiptModal type="pharmacy" receipt={receipt} onClose={() => setReceipt(null)} />}
      {returnSale && <ReturnModal sale={returnSale} onClose={() => setReturnSale(null)} onReturned={async () => { setReturnSale(null); setMessage('Sale returned and stock restored.'); await load(); }} />}
      {medicineForm !== undefined && <MedicineForm initial={medicineForm} onClose={() => setMedicineForm(undefined)} onSaved={async () => { setMedicineForm(undefined); setMessage('Medicine saved.'); await load(); refresh(); }} />}
    </div>
  );
}

function inputStyle() {
  return { background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)', color: 'var(--clr-text)' };
}

function Card({ title, children }) {
  return (
    <section className="rounded-xl overflow-hidden" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
      <div className="px-4 py-3.5" style={{ borderBottom: '1px solid var(--clr-border)' }}>
        <h2 className="text-sm font-bold" style={{ color: 'var(--clr-text)' }}>{title}</h2>
      </div>
      <div className="p-4">{children}</div>
    </section>
  );
}

function ActionButton({ onClick, icon: Icon, label, muted = false }) {
  return (
    <button onClick={onClick} className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold transition-colors" style={{ background: muted ? 'var(--clr-search-bg)' : '#7c3aed', color: muted ? 'var(--clr-text)' : '#ffffff', border: muted ? '1px solid var(--clr-border)' : 'none' }}>
      <Icon size={15} />
      {label}
    </button>
  );
}

function IconButton({ icon: Icon, title, onClick, danger = false }) {
  return (
    <button onClick={onClick} title={title} className="p-2 rounded-lg transition-colors" style={{ color: danger ? '#f87171' : 'var(--clr-muted)' }}>
      <Icon size={14} />
    </button>
  );
}

function Status({ label, tone }) {
  const color = tone === 'red' ? '#f87171' : tone === 'amber' ? '#f59e0b' : '#7c3aed';
  return <span className="inline-flex px-2 py-1 rounded-full text-[11px] font-bold" style={{ background: `${color}18`, color }}>{label}</span>;
}

function Table({ columns, rows }) {
  return (
    <div className="overflow-x-auto rounded-xl" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
      <table className="w-full text-sm">
        <thead className="sticky top-0">
          <tr style={{ borderBottom: '1px solid var(--clr-border)' }}>
            {columns.map((column) => <th key={column} className="px-4 py-3 text-left text-[10px] font-bold uppercase tracking-widest whitespace-nowrap" style={{ color: 'var(--clr-section)', background: 'var(--clr-card)' }}>{column}</th>)}
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 ? (
            <tr><td colSpan={columns.length}><EmptyState text="No records found." /></td></tr>
          ) : rows.map((row, index) => (
            <tr key={index} style={{ borderBottom: '1px solid var(--clr-border)' }}>
              {row.map((cell, cellIndex) => <td key={cellIndex} className="px-4 py-3 whitespace-nowrap" style={{ color: 'var(--clr-text)' }}>{cell}</td>)}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function ListCard({ title, rows }) {
  return (
    <Card title={title}>
      {rows.length === 0 ? <EmptyState text="No data yet." /> : (
        <div className="space-y-2">
          {rows.map(([label, value], index) => (
            <div key={`${label}-${index}`} className="flex justify-between gap-4 text-sm py-2" style={{ borderBottom: '1px solid var(--clr-border)' }}>
              <span style={{ color: 'var(--clr-text)' }}>{label}</span>
              <span className="font-semibold text-right" style={{ color: 'var(--clr-muted)' }}>{value}</span>
            </div>
          ))}
        </div>
      )}
    </Card>
  );
}

function SummaryLine({ label, value, strong = false }) {
  return (
    <div className="flex justify-between text-sm">
      <span style={{ color: 'var(--clr-muted)' }}>{label}</span>
      <span className={strong ? 'text-xl font-bold text-violet-600' : 'font-semibold'} style={strong ? undefined : { color: 'var(--clr-text)' }}>{value}</span>
    </div>
  );
}

function EmptyState({ text }) {
  return (
    <div className="flex flex-col items-center justify-center gap-2 py-10 text-center">
      <Boxes size={24} style={{ color: 'var(--clr-section)' }} />
      <p className="text-xs font-semibold" style={{ color: 'var(--clr-muted)' }}>{text}</p>
    </div>
  );
}
