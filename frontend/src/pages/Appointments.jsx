import { useCallback, useEffect, useMemo, useState } from 'react';
import { CalendarCheck, Plus, Printer, RefreshCw, Save, Search } from 'lucide-react';
import api, { asRows } from '../api';
import { useAuth } from '../context/AuthContext';
import Alert from '../components/ui/Alert';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import Modal from '../components/ui/Modal';
import ReceiptModal from '../components/ui/ReceiptModal';
import StatusBadge from '../components/ui/StatusBadge';
import { initials, money } from '../utils/formatters';

const initialForm = {
  patient_id: '',
  patient_name: '',
  patient_phone: '',
  gender: 'Male',
  age: '',
  address: '',
  doctor_id: '',
  appointment_date: '',
  appointment_time: '',
  payment_method: 'Cash',
  payment_status: 'Full Paid',
  paid_amount: '',
  account_no: '',
  payment_notes: '',
};

const paymentMethods = ['Cash', 'Card', 'EVC Plus', 'Zaad', 'Sahal', 'Bank Transfer'];
const paymentStatuses = ['Full Paid', 'Partial Paid'];
const mobileMethods = ['EVC Plus', 'Zaad', 'Sahal'];

function ReceptionStat({ value, label, tone = '#2563eb' }) {
  return (
    <div className="min-w-[135px]">
      <p className="text-2xl font-semibold leading-none" style={{ color: tone }}>{value}</p>
      <p className="mt-2 text-[11px]" style={{ color: '#8a94a6' }}>{label}</p>
    </div>
  );
}

export default function Appointments() {
  const { lookups, refresh } = useAuth();
  const [form, setForm] = useState(initialForm);
  const [rows, setRows] = useState([]);
  const [calendarRows, setCalendarRows] = useState([]);
  const [loading, setLoading] = useState(true);
  const [calendarLoading, setCalendarLoading] = useState(true);
  const [slots, setSlots] = useState([]);
  const [workingHours, setWorkingHours] = useState(null);
  const [doctorSchedules, setDoctorSchedules] = useState([]);
  const [slotsLoading, setSlotsLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [bookingOpen, setBookingOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [message, setMessage] = useState({ text: '', type: 'info' });
  const [bookingError, setBookingError] = useState('');
  const [paymentFeedback, setPaymentFeedback] = useState({ text: '', type: 'info' });
  const [bookingComplete, setBookingComplete] = useState(false);
  const [completedPayment, setCompletedPayment] = useState(null);
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
    if (!form.doctor_id) {
      setDoctorSchedules([]);
      return;
    }
    api.get(`/appointments/doctors/${form.doctor_id}/schedules`)
      .then(({ data }) => setDoctorSchedules((data.schedules ?? []).filter((row) => row.is_working)))
      .catch((err) => setBookingError(err.message));
  }, [form.doctor_id]);

  useEffect(() => {
    if (!form.doctor_id || !form.appointment_date) {
      setSlots([]);
      setWorkingHours(null);
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
        setWorkingHours(data.working_hours ?? null);
        setForm((current) => {
          const stillAvailable = (data.slots ?? []).some((slot) => slot.time === current.appointment_time);
          return stillAvailable ? current : { ...current, appointment_time: '' };
        });
      })
      .catch((err) => {
        if (!cancelled) setBookingError(err.message);
      })
      .finally(() => {
        if (!cancelled) setSlotsLoading(false);
      });

    return () => { cancelled = true; };
  }, [form.doctor_id, form.appointment_date]);

  const setField = (name, value) => {
    setForm((current) => {
      if (name === 'patient_id') {
        const patient = (lookups?.patients ?? []).find((item) => String(item.id) === String(value));
        return {
          ...current,
          patient_id: value,
          patient_name: patient?.full_name ?? '',
          patient_phone: patient?.phone ?? '',
          gender: patient?.gender ?? 'Male',
          age: patient?.age ?? '',
          address: patient?.address ?? '',
        };
      }
      return { ...current, [name]: value };
    });
  };

  const submit = async (event) => {
    event.preventDefault();
    setSaving(true);
    setBookingError('');
    setBookingComplete(false);
    setPaymentFeedback({
      text: needsAccount
        ? 'Payment request sent. Waiting for the customer to approve it on their phone...'
        : 'Recording appointment and payment...',
      type: 'info',
    });

    try {
      const payload = {
        ...form,
        age: form.age === '' ? null : Number(form.age),
      };
      const { data } = await api.post('/appointments/book', payload);
      setMessage({ text: 'Appointment booked, patient registered, and payment recorded successfully.', type: 'success' });
      setPaymentFeedback({
        text: needsAccount
          ? 'Payment approved successfully. The appointment and payment have been recorded.'
          : 'Appointment and payment recorded successfully.',
        type: 'success',
      });
      setBookingComplete(true);
      setCompletedPayment(data.payment ?? null);
      await loadAppointments();
      await loadCalendar();
      await refresh();
    } catch (err) {
      setBookingError(err.message);
      setPaymentFeedback({ text: err.message, type: 'danger' });
    } finally {
      setSaving(false);
    }
  };

  const closeBooking = () => {
    if (bookingComplete && completedPayment) {
      setReceipt(completedPayment);
    }
    setBookingOpen(false);
    setBookingError('');
    setPaymentFeedback({ text: '', type: 'info' });
    setBookingComplete(false);
    setCompletedPayment(null);
    setForm(initialForm);
  };

  const todayKey = new Date().toISOString().slice(0, 10);
  const stats = useMemo(() => {
    const today = rows.filter((row) => row.appointment_date === todayKey).length;
    const pending = rows.filter((row) => row.status === 'Pending').length;
    const completed = rows.filter((row) => row.status === 'Completed').length;
    const paid = rows.filter((row) => row.payment?.payment_status === 'Full Paid').length;
    return { total: rows.length, today, pending, completed, paid };
  }, [rows, todayKey]);

  const filteredRows = useMemo(() => {
    const text = query.trim().toLowerCase();
    if (!text) return rows;
    return rows.filter((row) => [
      row.patient?.full_name,
      row.patient?.phone,
      row.doctor?.full_name,
      row.status,
      row.payment?.payment_status,
    ].some((value) => String(value ?? '').toLowerCase().includes(text)));
  }, [query, rows]);

  const upcomingCards = calendarRows.slice(0, 6);
  const needsAccount = mobileMethods.includes(form.payment_method);
  const isPartial = form.payment_status === 'Partial Paid';
  const appointmentTotal = Number(selectedDoctor?.consultation_fee ?? 0);
  const appointmentPaid = isPartial ? Number(form.paid_amount || 0) : appointmentTotal;
  const remainingBalance = Math.max(0, appointmentTotal - appointmentPaid);
  const availableDates = useMemo(() => {
    const workingDays = new Set(doctorSchedules.map((row) => row.day_of_week));
    return Array.from({ length: 60 }, (_, offset) => {
      const date = new Date();
      date.setDate(date.getDate() + offset);
      const value = date.toISOString().slice(0, 10);
      return { value, label: date.toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' }), day: date.toLocaleDateString('en-US', { weekday: 'long' }) };
    }).filter((item) => workingDays.has(item.day));
  }, [doctorSchedules]);

  const fieldClass = 'w-full rounded-lg border border-[#edf1f7] bg-white px-3 py-2.5 text-sm text-[#1f2937] outline-none focus:border-[#7aa7ff]';
  const labelClass = 'block text-[10px] font-semibold uppercase tracking-widest text-[#8993a4] mb-1.5';

  return (
    <div className="min-h-full animate-fade-in">
      <div className="rounded-none lg:rounded-sm bg-white px-4 py-5 sm:px-7 sm:py-7 shadow-[0_18px_60px_rgba(114,105,160,0.08)]">
        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-xl font-semibold tracking-wide text-[#101828]">Reception Appointments</h1>
            <p className="mt-1 text-xs text-[#8a94a6]">Book patients, collect consultation payments, and print receipts.</p>
          </div>
          <div className="flex items-center gap-3">
            <div className="hidden sm:flex h-9 w-9 items-center justify-center rounded-full border border-[#eef2f8] text-[#8a94a6]">
              <Search size={15} />
            </div>
            <button
              onClick={() => {
                setBookingError('');
                setPaymentFeedback({ text: '', type: 'info' });
                setBookingComplete(false);
                setCompletedPayment(null);
                setBookingOpen(true);
              }}
              className="inline-flex items-center gap-2 rounded-full border border-[#b7cdf8] bg-white px-4 py-2 text-xs font-semibold text-[#3b73d9] shadow-[0_8px_22px_rgba(65,111,190,0.12)]"
            >
              <Plus size={14} />
              Add Appointment
            </button>
          </div>
        </div>

        {message.text && <div className="mt-5"><Alert message={message.text} variant={message.type} /></div>}

        <div className="mt-7 rounded-sm bg-white px-4 py-5 shadow-[0_18px_45px_rgba(15,23,42,0.055)]">
          <div className="grid grid-cols-2 gap-5 md:grid-cols-5">
            <ReceptionStat value={stats.total} label="Total Appointments" tone="var(--clr-accent)" />
            <ReceptionStat value={stats.today} label="Today" tone="#14a59a" />
            <ReceptionStat value={stats.pending} label="Pending" tone="#c34b7a" />
            <ReceptionStat value={stats.completed} label="Completed" tone="#aa2f2f" />
            <ReceptionStat value={stats.paid} label="Paid Bookings" tone="#4f7fd8" />
          </div>
        </div>

        <div className="mt-7 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <h2 className="text-sm font-semibold text-[#1f2937]">Appointment List</h2>
            <p className="mt-1 text-[11px] text-[#8a94a6]">{filteredRows.length} records shown</p>
          </div>
          <div className="flex items-center gap-2 rounded-full border border-[#eef2f8] bg-white px-3 py-2">
            <Search size={14} className="text-[#9aa5b5]" />
            <input
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              placeholder="Search patient or doctor"
              className="w-52 bg-transparent text-xs text-[#111827] outline-none placeholder:text-[#a7b0bf]"
            />
          </div>
        </div>

        <div className="mt-4 overflow-x-auto">
          {loading ? (
            <LoadingSpinner text="Loading appointments..." />
          ) : (
            <table className="w-full min-w-[860px] border-collapse text-left">
              <thead>
                <tr className="border-y border-[#edf1f7] text-[11px] font-semibold text-[#5f6b7a]">
                  <th className="px-4 py-3">Patient Name</th>
                  <th className="px-4 py-3">Doctor</th>
                  <th className="px-4 py-3">Appointment Date</th>
                  <th className="px-4 py-3">Time</th>
                  <th className="px-4 py-3">Total</th>
                  <th className="px-4 py-3">Paid</th>
                  <th className="px-4 py-3">Remaining</th>
                  <th className="px-4 py-3">Payment</th>
                  <th className="px-4 py-3">Appointment</th>
                  <th className="px-4 py-3 text-right">Receipt</th>
                </tr>
              </thead>
              <tbody>
                {filteredRows.map((row) => (
                  <tr key={row.id} className="border-b border-[#f1f4f8] text-xs text-[#344054] hover:bg-[#fbfcff]">
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-2.5">
                        <span className="grid h-6 w-6 place-items-center rounded-full bg-[#edf5ff] text-[9px] font-bold text-[#4c7fd1]">
                          {initials(row.patient?.full_name)}
                        </span>
                        <div>
                          <p className="font-medium text-[#1f2937]">{row.patient?.full_name ?? '-'}</p>
                          <p className="text-[10px] text-[#98a2b3]">{row.patient?.phone ?? ''}</p>
                        </div>
                      </div>
                    </td>
                    <td className="px-4 py-3">{row.doctor?.full_name ?? '-'}</td>
                    <td className="px-4 py-3">{row.appointment_date}</td>
                    <td className="px-4 py-3">{String(row.appointment_time ?? '').slice(0, 5)}</td>
                    <td className="px-4 py-3">{money(row.payment?.total_amount ?? row.fee_at_booking)}</td>
                    <td className="px-4 py-3">{money(row.payment?.paid_amount ?? 0)}</td>
                    <td className="px-4 py-3 font-semibold" style={{ color: Number(row.payment?.remaining_amount) > 0 ? '#d97706' : '#16a34a' }}>{money(row.payment?.remaining_amount ?? 0)}</td>
                    <td className="px-4 py-3"><StatusBadge value={row.payment?.payment_status ?? 'Outstanding'} /></td>
                    <td className="px-4 py-3"><StatusBadge value={row.status} /></td>
                    <td className="px-4 py-3 text-right">
                      {row.payment ? (
                        <button
                          onClick={() => setReceipt(row.payment)}
                          title="Print receipt"
                          className="inline-grid h-8 w-8 place-items-center rounded-full border border-[#e6edf7] text-[#4f7fd8] hover:bg-[#eef5ff]"
                        >
                          <Printer size={14} />
                        </button>
                      ) : (
                        <span className="text-[#a7b0bf]">-</span>
                      )}
                    </td>
                  </tr>
                ))}
                {filteredRows.length === 0 && (
                  <tr>
                    <td colSpan="10" className="px-4 py-10 text-center text-sm text-[#8a94a6]">
                      No appointments found.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          )}
        </div>

        <div className="mt-7">
          <div className="mb-3 flex items-center justify-between">
            <div>
              <h2 className="text-sm font-semibold text-[#1f2937]">This Month Calendar</h2>
              <p className="mt-1 text-[11px] text-[#8a94a6]">Upcoming appointment snapshots.</p>
            </div>
            <button onClick={loadCalendar} className="inline-flex items-center gap-2 rounded-full border border-[#eef2f8] px-3 py-2 text-xs font-semibold text-[#667085]">
              <RefreshCw size={13} />
              Refresh
            </button>
          </div>
          {calendarLoading ? (
            <LoadingSpinner text="Loading calendar..." />
          ) : (
            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
              {upcomingCards.map((item) => (
                <div key={item.id} className="rounded-lg border border-[#edf1f7] bg-[#fbfcff] p-3">
                  <div className="flex items-center justify-between gap-3">
                    <p className="text-xs font-semibold" style={{ color: 'var(--clr-accent)' }}>{item.date} at {item.time}</p>
                    <StatusBadge value={item.payment_status ?? 'Outstanding'} />
                  </div>
                  <p className="mt-2 text-sm font-semibold text-[#1f2937]">{item.patient}</p>
                  <p className="mt-0.5 text-xs text-[#8a94a6]">{item.doctor}</p>
                </div>
              ))}
              {upcomingCards.length === 0 && (
                <p className="text-sm text-[#8a94a6]">No appointments in this month.</p>
              )}
            </div>
          )}
        </div>
      </div>

      {bookingOpen && (
        <Modal
          title="New Appointment"
          subtitle="Register patient, choose an available doctor slot, and record payment."
          onClose={closeBooking}
          size="xl"
        >
          <form onSubmit={submit}>
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
              <label>
                <span className={labelClass}>Patient</span>
                <select className={fieldClass} value={form.patient_id} onChange={(e) => setField('patient_id', e.target.value)} required>
                  <option value="">Select patient</option>
                  {(lookups?.patients ?? []).map((patient) => <option key={patient.id} value={patient.id}>{patient.full_name} · {patient.phone}</option>)}
                </select>
              </label>
              <label>
                <span className={labelClass}>Patient Phone</span>
                <input className={`${fieldClass} bg-[#f8fafc]`} value={form.patient_phone} readOnly />
              </label>
              <label>
                <span className={labelClass}>Gender</span>
                <select className={fieldClass} value={form.gender} disabled>
                  <option>Male</option>
                  <option>Female</option>
                </select>
              </label>
              <label>
                <span className={labelClass}>Age</span>
                <input type="number" min="0" max="120" className={`${fieldClass} bg-[#f8fafc]`} value={form.age} readOnly />
              </label>
              <label className="md:col-span-2">
                <span className={labelClass}>Address</span>
                <input className={`${fieldClass} bg-[#f8fafc]`} value={form.address} readOnly />
              </label>
              <label>
                <span className={labelClass}>Doctor</span>
                <select className={fieldClass} value={form.doctor_id} onChange={(e) => setField('doctor_id', e.target.value)} required>
                  <option value="">Select doctor</option>
                  {doctors.map((doctor) => (
                    <option key={doctor.id} value={doctor.id}>{doctor.full_name}</option>
                  ))}
                </select>
              </label>
              <label>
                <span className={labelClass}>Doctor Phone</span>
                <input className={`${fieldClass} bg-[#f8fafc] text-[#7a8494]`} value={selectedDoctor?.phone ?? ''} readOnly placeholder="Auto-filled" />
              </label>
              <label>
                <span className={labelClass}>Doctor Fee</span>
                <input className={`${fieldClass} bg-[#f8fafc] text-[#7a8494]`} value={selectedDoctor ? money(selectedDoctor.consultation_fee ?? 0) : ''} readOnly placeholder="Auto-filled" />
              </label>
              <label>
                <span className={labelClass}>Appointment Date</span>
                <select className={fieldClass} value={form.appointment_date} onChange={(e) => setField('appointment_date', e.target.value)} required disabled={!form.doctor_id}>
                  <option value="">Select working date</option>
                  {availableDates.map((date) => <option key={date.value} value={date.value}>{date.label}</option>)}
                </select>
              </label>
              <label>
                <span className={labelClass}>Appointment Time</span>
                <select
                  className={fieldClass}
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
                <span className={labelClass}>Payment Method</span>
                <select className={fieldClass} value={form.payment_method} onChange={(e) => setField('payment_method', e.target.value)}>
                  {paymentMethods.map((method) => <option key={method}>{method}</option>)}
                </select>
              </label>
              <label>
                <span className={labelClass}>Payment Status</span>
                <select className={fieldClass} value={form.payment_status} onChange={(e) => setField('payment_status', e.target.value)}>
                  {paymentStatuses.map((status) => <option key={status}>{status}</option>)}
                </select>
              </label>
              {isPartial && (
                <label>
                  <span className={labelClass}>Amount Paid</span>
                  <input type="number" min="0.01" max={appointmentTotal} step="0.01" className={fieldClass} value={form.paid_amount} onChange={(e) => setField('paid_amount', e.target.value)} required />
                </label>
              )}
              <label>
                <span className={labelClass}>Remaining Balance</span>
                <input className={`${fieldClass} bg-[#f8fafc]`} value={money(remainingBalance)} readOnly />
              </label>
              {needsAccount && (
                <label>
                  <span className={labelClass}>Mobile Account</span>
                  <input className={fieldClass} value={form.account_no} onChange={(e) => setField('account_no', e.target.value)} placeholder="25261..." required />
                </label>
              )}
              <label className="md:col-span-2 xl:col-span-3">
                <span className={labelClass}>Payment Message / Description</span>
                <textarea rows={2} className={fieldClass} value={form.payment_notes} onChange={(e) => setField('payment_notes', e.target.value)} placeholder="Optional payment note..." />
              </label>
            </div>

            {paymentFeedback.text && (
              <div className="mt-4" role="status" aria-live="assertive">
                <Alert message={paymentFeedback.text} variant={paymentFeedback.type} />
              </div>
            )}

            {Array.isArray(workingHours) && workingHours.length > 0 && (
              <div className="mt-4 rounded-lg border border-[#edf1f7] bg-[#fbfcff] p-3 text-xs text-[#667085]">
                {workingHours.map((hours) => (
                  <span key={hours.shift} className="mr-4">
                    <strong>{hours.shift}:</strong> {hours.start}-{hours.end} · {hours.available} available
                  </span>
                ))}
              </div>
            )}

            <div className="mt-6 flex justify-end gap-3">
              {bookingComplete ? (
                <button
                  type="button"
                  onClick={closeBooking}
                  className="inline-flex items-center gap-2 rounded-full bg-[#6d28d9] px-5 py-2.5 text-sm font-semibold text-white"
                >
                  Done
                </button>
              ) : (
                <button
                  type="submit"
                  disabled={saving}
                  className="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-60"
                  style={{ background: 'var(--clr-accent)' }}
                >
                  <Save size={14} />
                  {saving ? 'Waiting for payment...' : 'Save Appointment'}
                </button>
              )}
            </div>
          </form>
        </Modal>
      )}

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
