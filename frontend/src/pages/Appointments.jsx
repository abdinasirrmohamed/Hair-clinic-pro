import { useCallback, useEffect, useMemo, useState } from 'react';
import { CalendarCheck, Printer, RefreshCw, Save } from 'lucide-react';
import api, { asRows } from '../api';
import { useAuth } from '../context/AuthContext';
import Alert from '../components/ui/Alert';
import DataTable from '../components/ui/DataTable';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import ReceiptModal from '../components/ui/ReceiptModal';
import { money } from '../utils/formatters';

const initialForm = {
  patient_name: '',
  patient_phone: '',
  gender: 'Male',
  age: '',
  address: '',
  doctor_id: '',
  appointment_date: '',
  appointment_time: '',
  payment_method: 'Cash',
  payment_status: 'Paid',
  account_no: '',
};

const paymentMethods = ['Cash', 'Card', 'EVC Plus', 'Zaad', 'Sahal', 'Bank Transfer'];
const paymentStatuses = ['Paid', 'Partial', 'Outstanding'];
const mobileMethods = ['EVC Plus', 'Zaad', 'Sahal'];

export default function Appointments() {
  const { lookups, refresh } = useAuth();
  const [form, setForm] = useState(initialForm);
  const [rows, setRows] = useState([]);
  const [calendarRows, setCalendarRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [calendarLoading, setCalendarLoading] = useState(true);
  const [slots, setSlots] = useState([]);
  const [slotsLoading, setSlotsLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState({ text: '', type: 'info' });
  const [receipt, setReceipt] = useState(null);

  const doctors = lookups?.doctors ?? [];
  const selectedDoctor = useMemo(
    () => doctors.find((doctor) => String(doctor.id) === String(form.doctor_id)),
    [doctors, form.doctor_id],
  );

  const loadAppointments = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await api.get('/appointments');
      setRows(asRows(data));
    } catch (err) {
      setMessage({ text: err.message, type: 'danger' });
    } finally {
      setLoading(false);
    }
  }, []);

  const loadCalendar = useCallback(async () => {
    setCalendarLoading(true);
    try {
      const now = new Date();
      const from = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
      const to = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10);
      const { data } = await api.get('/appointments/calendar', { params: { from, to } });
      setCalendarRows(data);
    } catch (err) {
      setMessage({ text: err.message, type: 'danger' });
    } finally {
      setCalendarLoading(false);
    }
  }, []);

  useEffect(() => { loadAppointments(); }, [loadAppointments]);
  useEffect(() => { loadCalendar(); }, [loadCalendar]);

  useEffect(() => {
    if (!form.doctor_id || !form.appointment_date) {
      setSlots([]);
      setField('appointment_time', '');
      return;
    }

    let cancelled = false;
    setSlotsLoading(true);
    api.get('/appointments/available-slots', {
      params: { doctor_id: form.doctor_id, appointment_date: form.appointment_date },
    })
      .then(({ data }) => {
        if (cancelled) return;
        setSlots(data.slots ?? []);
        setForm((current) => {
          const stillAvailable = (data.slots ?? []).some((slot) => slot.time === current.appointment_time);
          return stillAvailable ? current : { ...current, appointment_time: '' };
        });
      })
      .catch((err) => {
        if (!cancelled) setMessage({ text: err.message, type: 'danger' });
      })
      .finally(() => {
        if (!cancelled) setSlotsLoading(false);
      });

    return () => { cancelled = true; };
  }, [form.doctor_id, form.appointment_date]);

  const setField = (name, value) => {
    setForm((current) => ({ ...current, [name]: value }));
  };

  const submit = async (event) => {
    event.preventDefault();
    setSaving(true);
    setMessage({ text: '', type: 'info' });

    try {
      const payload = {
        ...form,
        age: form.age === '' ? null : Number(form.age),
      };
      const { data } = await api.post('/appointments/book', payload);
      setMessage({ text: 'Appointment booked, patient registered, and payment recorded successfully.', type: 'success' });
      setReceipt(data.payment ?? null);
      setForm(initialForm);
      await loadAppointments();
      await loadCalendar();
      await refresh();
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
  const readonlyStyle = {
    ...inputStyle,
    background: 'var(--clr-hover)',
    color: 'var(--clr-muted)',
  };
  const labelClass = 'block text-[10px] font-bold uppercase tracking-widest mb-1.5';
  const needsAccount = mobileMethods.includes(form.payment_method) && form.payment_status === 'Paid';
  const groupedCalendar = calendarRows.reduce((groups, item) => {
    groups[item.date] = groups[item.date] ? [...groups[item.date], item] : [item];
    return groups;
  }, {});

  return (
    <div className="space-y-5 animate-fade-in">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold" style={{ color: 'var(--clr-text)' }}>Appointment Booking</h1>
          <p className="mt-1 text-xs" style={{ color: 'var(--clr-muted)' }}>
            Register a patient, book the appointment, and record the doctor fee in one clean step.
          </p>
        </div>
        <button
          onClick={loadAppointments}
          className="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold"
          style={{ color: 'var(--clr-muted)', border: '1px solid var(--clr-border)' }}
        >
          <RefreshCw size={13} />
          Refresh
        </button>
      </div>

      {message.text && <Alert message={message.text} variant={message.type} />}

      <form
        onSubmit={submit}
        className="rounded-xl overflow-hidden"
        style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}
      >
        <div className="px-5 py-4 flex items-center gap-2" style={{ borderBottom: '1px solid var(--clr-border)' }}>
          <CalendarCheck size={16} className="text-green-500" />
          <h2 className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>New Appointment</h2>
        </div>

        <div className="p-5 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          <label>
            <span className={labelClass} style={{ color: 'var(--clr-section)' }}>Patient Name</span>
            <input style={inputStyle} value={form.patient_name} onChange={(e) => setField('patient_name', e.target.value)} required />
          </label>
          <label>
            <span className={labelClass} style={{ color: 'var(--clr-section)' }}>Patient Phone</span>
            <input style={inputStyle} value={form.patient_phone} onChange={(e) => setField('patient_phone', e.target.value)} required />
          </label>
          <label>
            <span className={labelClass} style={{ color: 'var(--clr-section)' }}>Gender</span>
            <select style={inputStyle} value={form.gender} onChange={(e) => setField('gender', e.target.value)}>
              <option>Male</option>
              <option>Female</option>
              <option>Other</option>
            </select>
          </label>
          <label>
            <span className={labelClass} style={{ color: 'var(--clr-section)' }}>Age</span>
            <input type="number" min="0" max="120" style={inputStyle} value={form.age} onChange={(e) => setField('age', e.target.value)} />
          </label>
          <label className="md:col-span-2">
            <span className={labelClass} style={{ color: 'var(--clr-section)' }}>Address</span>
            <input style={inputStyle} value={form.address} onChange={(e) => setField('address', e.target.value)} />
          </label>
          <label>
            <span className={labelClass} style={{ color: 'var(--clr-section)' }}>Doctor</span>
            <select style={inputStyle} value={form.doctor_id} onChange={(e) => setField('doctor_id', e.target.value)} required>
              <option value="">Select doctor</option>
              {doctors.map((doctor) => (
                <option key={doctor.id} value={doctor.id}>{doctor.full_name}</option>
              ))}
            </select>
          </label>
          <label>
            <span className={labelClass} style={{ color: 'var(--clr-section)' }}>Doctor Phone</span>
            <input style={readonlyStyle} value={selectedDoctor?.phone ?? ''} readOnly placeholder="Auto-filled" />
          </label>
          <label>
            <span className={labelClass} style={{ color: 'var(--clr-section)' }}>Specialization</span>
            <input style={readonlyStyle} value={selectedDoctor?.specialization ?? ''} readOnly placeholder="Auto-filled" />
          </label>
          <label>
            <span className={labelClass} style={{ color: 'var(--clr-section)' }}>Doctor Fee</span>
            <input style={readonlyStyle} value={selectedDoctor ? money(selectedDoctor.consultation_fee ?? 0) : ''} readOnly placeholder="Auto-filled" />
          </label>
          <label>
            <span className={labelClass} style={{ color: 'var(--clr-section)' }}>Appointment Date</span>
            <input type="date" style={inputStyle} value={form.appointment_date} onChange={(e) => setField('appointment_date', e.target.value)} required />
          </label>
          <label>
            <span className={labelClass} style={{ color: 'var(--clr-section)' }}>Appointment Time</span>
            <select
              style={inputStyle}
              value={form.appointment_time}
              onChange={(e) => setField('appointment_time', e.target.value)}
              required
              disabled={!form.doctor_id || !form.appointment_date || slotsLoading}
            >
              <option value="">{slotsLoading ? 'Loading slots...' : 'Select available slot'}</option>
              {slots.map((slot) => (
                <option key={slot.time} value={slot.time}>{slot.label}</option>
              ))}
            </select>
          </label>
          <label>
            <span className={labelClass} style={{ color: 'var(--clr-section)' }}>Payment Method</span>
            <select style={inputStyle} value={form.payment_method} onChange={(e) => setField('payment_method', e.target.value)}>
              {paymentMethods.map((method) => <option key={method}>{method}</option>)}
            </select>
          </label>
          <label>
            <span className={labelClass} style={{ color: 'var(--clr-section)' }}>Payment Status</span>
            <select style={inputStyle} value={form.payment_status} onChange={(e) => setField('payment_status', e.target.value)}>
              {paymentStatuses.map((status) => <option key={status}>{status}</option>)}
            </select>
          </label>
          {needsAccount && (
            <label>
              <span className={labelClass} style={{ color: 'var(--clr-section)' }}>Mobile Account</span>
              <input
                style={inputStyle}
                value={form.account_no}
                onChange={(e) => setField('account_no', e.target.value)}
                placeholder="25261..."
                required
              />
            </label>
          )}
        </div>

        <div className="px-5 py-4 flex justify-end" style={{ borderTop: '1px solid var(--clr-border)' }}>
          <button
            type="submit"
            disabled={saving}
            className="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold disabled:opacity-60"
            style={{ background: '#22c55e', color: '#052e10', border: 'none' }}
          >
            <Save size={14} />
            {saving ? 'Saving...' : 'Save Appointment'}
          </button>
        </div>
      </form>

      <div className="rounded-xl overflow-hidden" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
        <div className="px-5 py-4 flex items-center justify-between gap-3" style={{ borderBottom: '1px solid var(--clr-border)' }}>
          <div>
            <p className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>Appointment Calendar</p>
            <p className="text-xs mt-0.5" style={{ color: 'var(--clr-muted)' }}>This month by day, doctor, patient, and payment state.</p>
          </div>
          <button onClick={loadCalendar} className="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-semibold" style={{ color: 'var(--clr-muted)', border: '1px solid var(--clr-border)' }}>
            <RefreshCw size={13} /> Refresh
          </button>
        </div>
        {calendarLoading ? (
          <LoadingSpinner text="Loading calendar..." />
        ) : (
          <div className="p-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
            {Object.keys(groupedCalendar).length === 0 && (
              <p className="text-sm p-4" style={{ color: 'var(--clr-muted)' }}>No appointments in this month.</p>
            )}
            {Object.entries(groupedCalendar).map(([date, items]) => (
              <div key={date} className="rounded-lg p-3" style={{ background: 'var(--clr-search-bg)', border: '1px solid var(--clr-border)' }}>
                <p className="text-xs font-bold text-green-500">{date}</p>
                <div className="mt-3 space-y-2">
                  {items.map((item) => (
                    <div key={item.id} className="rounded-md p-2" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
                      <div className="flex justify-between gap-3">
                        <span className="text-sm font-bold" style={{ color: 'var(--clr-text)' }}>{item.time}</span>
                        <span className="text-[11px] font-bold text-green-500">{item.status}</span>
                      </div>
                      <p className="text-xs mt-1" style={{ color: 'var(--clr-text)' }}>{item.patient}</p>
                      <p className="text-[11px]" style={{ color: 'var(--clr-muted)' }}>{item.doctor} - {item.payment_status ?? 'No payment'}</p>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      <div className="rounded-xl overflow-hidden" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
        <div className="px-5 py-4" style={{ borderBottom: '1px solid var(--clr-border)' }}>
          <p className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>Recent Appointments</p>
          <p className="text-xs mt-0.5" style={{ color: 'var(--clr-muted)' }}>{rows.length} records</p>
        </div>
        {loading ? (
          <LoadingSpinner />
        ) : (
          <DataTable
            columns={['patient.full_name', 'doctor.full_name', 'appointment_date', 'appointment_time', 'fee_at_booking', 'status']}
            labels={{
              'patient.full_name': 'Patient',
              'doctor.full_name': 'Doctor',
              appointment_date: 'Date',
              appointment_time: 'Time',
              fee_at_booking: 'Fee',
              status: 'Status',
            }}
            rows={rows}
            noEdit
            noDelete
            renderActions={(row) => row.payment ? (
              <button
                onClick={() => setReceipt(row.payment)}
                title="Print receipt"
                className="p-2 rounded-lg transition-colors"
                style={{ color: 'var(--clr-muted)' }}
                onMouseEnter={(e) => { e.currentTarget.style.color = '#22c55e'; e.currentTarget.style.background = 'var(--clr-accent-soft)'; }}
                onMouseLeave={(e) => { e.currentTarget.style.color = 'var(--clr-muted)'; e.currentTarget.style.background = 'transparent'; }}
              >
                <Printer size={14} />
              </button>
            ) : null}
          />
        )}
      </div>

      <div className="rounded-xl overflow-hidden" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
        <div className="px-5 py-4" style={{ borderBottom: '1px solid var(--clr-border)' }}>
          <p className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>Recently Registered Patients</p>
          <p className="text-xs mt-0.5" style={{ color: 'var(--clr-muted)' }}>Patients created or reused during booking appear from the database.</p>
        </div>
        <DataTable
          columns={['full_name', 'phone', 'gender', 'age', 'address']}
          labels={{ full_name: 'Patient', phone: 'Phone', gender: 'Gender', age: 'Age', address: 'Address' }}
          rows={(lookups?.patients ?? []).slice(0, 8)}
          noEdit
          noDelete
        />
      </div>

      {receipt && (
        <ReceiptModal
          type="payment"
          receipt={receipt}
          onClose={() => setReceipt(null)}
        />
      )}
    </div>
  );
}
