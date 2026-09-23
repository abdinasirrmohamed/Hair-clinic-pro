import { useState } from 'react';
import api from '../../api';
import Modal from '../ui/Modal';
import FormField from '../ui/FormField';
import Alert from '../ui/Alert';
import { Save } from 'lucide-react';
import { validateFields } from '../../utils/validation';

const ageFromDateOfBirth = (dateOfBirth) => {
  if (!dateOfBirth) return '';
  const birthDate = new Date(`${dateOfBirth}T00:00:00`);
  if (Number.isNaN(birthDate.getTime())) return '';
  const today = new Date();
  let age = today.getFullYear() - birthDate.getFullYear();
  const birthdayPassed = today.getMonth() > birthDate.getMonth()
    || (today.getMonth() === birthDate.getMonth() && today.getDate() >= birthDate.getDate());
  if (!birthdayPassed) age -= 1;
  return age >= 0 ? age : '';
};

export default function CrudEditor({ config, record, lookups, onClose, onSaved }) {
  const editing = Boolean(record?.id);
  const [form,   setForm]   = useState({ ...(config.createDefaults ?? {}), ...(record ?? {}) });
  const [files,  setFiles]  = useState({});
  const [error,  setError]  = useState('');
  const [saving, setSaving] = useState(false);
  const [fieldErrors, setFieldErrors] = useState({});

  const fields = config.fields.filter(
    (f) => !(f.editOnly && !editing) && f.type !== 'hidden',
  ).map((field) => field.name === 'account_no' && config.endpoint === '/payments'
    ? { ...field, required: ['EVC Plus', 'Zaad', 'Sahal'].includes(form.payment_method) } : field);
  const filteredLookups = config.endpoint === '/payments' ? {
    ...lookups,
    appointments: (lookups?.appointments ?? []).filter((row) => String(row.patient_id) === String(form.patient_id)),
  } : lookups;

  const handleChange = (e) => {
    const { name, value, files: selected } = e.target;
    setFieldErrors((prev) => ({ ...prev, [name]: undefined }));
    if (selected) {
      setFiles((prev) => {
        const next = { ...prev };
        if (selected[0]) next[name] = selected[0];
        else delete next[name];
        return next;
      });
    } else {
      setForm((prev) => {
        const next = { ...prev, [name]: value };
        if (name === 'patient_id' && config.endpoint === '/payments') next.appointment_id = '';
        if (name === 'date_of_birth' && config.endpoint === '/patients') {
          next.age = ageFromDateOfBirth(value);
        }
        return next;
      });
    }
  };

  const submit = async (e) => {
    e.preventDefault();
    const errors = validateFields(fields, { ...form, ...files });
    if (config.endpoint === '/payments' && ['EVC Plus', 'Zaad', 'Sahal'].includes(form.payment_method) && form.payment_status !== 'Paid') {
      errors.payment_status = 'Mobile wallet payments must be fully paid.';
    }
    setFieldErrors(errors);
    if (Object.keys(errors).length) {
      setError(Object.values(errors).join(' '));
      return;
    }
    setSaving(true);
    setError('');
    try {
      const hasFiles = Object.keys(files).length > 0;
      let body;
      let response;
      if (hasFiles) {
        body = new FormData();
        Object.entries(form).forEach(([k, v]) => {
          if (v != null && typeof v !== 'object') body.append(k, v);
        });
        Object.entries(files).forEach(([k, v]) => body.append(k, v));
        if (editing) body.append('_method', 'PUT');
        response = await api.post(
          editing ? `${config.endpoint}/${record.id}` : config.endpoint,
          body,
          { headers: { 'Content-Type': 'multipart/form-data' } },
        );
      } else {
        if (editing) {
          response = await api.put(`${config.endpoint}/${record.id}`, form);
        } else {
          response = await api.post(config.endpoint, form);
        }
      }
      onSaved(editing ? 'Record updated successfully.' : 'Record created successfully.', response?.data, !editing);
    } catch (err) {
      setError(err.message);
      setFieldErrors(Object.fromEntries(Object.entries(err.errors ?? {}).map(([key, messages]) => [key, messages.join(' ')])));
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      title={editing ? 'Edit Record' : 'Add New Record'}
      subtitle="Fill in the details and click Save to continue."
      onClose={onClose}
    >
      <form onSubmit={submit}>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {fields.map((def) => (
            <FormField
              key={def.name}
              definition={def}
              value={form[def.name]}
              onChange={handleChange}
              lookups={filteredLookups}
              error={fieldErrors[def.name]}
            />
          ))}
        </div>

        {error && (
          <div className="mt-4" role="alert" aria-live="assertive">
            <Alert message={error} />
          </div>
        )}

        <div
          className="flex justify-end gap-3 mt-5 pt-4"
          style={{ borderTop: '1px solid var(--clr-border)' }}
        >
          <button
            type="button"
            onClick={onClose}
            className="px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors"
            style={{
              background: 'var(--clr-hover)',
              color: 'var(--clr-muted)',
              border: '1px solid var(--clr-border)',
            }}
            onMouseEnter={(e) => { e.currentTarget.style.color = 'var(--clr-text)'; }}
            onMouseLeave={(e) => { e.currentTarget.style.color = 'var(--clr-muted)'; }}
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={saving}
            className="flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold transition-all disabled:opacity-50 disabled:cursor-not-allowed"
            style={{ background: '#7c3aed', color: '#ffffff', border: 'none' }}
            onMouseEnter={(e) => { if (!saving) e.currentTarget.style.background = '#6d28d9'; }}
            onMouseLeave={(e) => { e.currentTarget.style.background = '#7c3aed'; }}
          >
            <Save size={14} />
            {saving ? 'Saving…' : 'Save Record'}
          </button>
        </div>
      </form>
    </Modal>
  );
}
