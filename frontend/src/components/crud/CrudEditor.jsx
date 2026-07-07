import { useState } from 'react';
import api from '../../api';
import Modal from '../ui/Modal';
import FormField from '../ui/FormField';
import Alert from '../ui/Alert';
import { Save } from 'lucide-react';

export default function CrudEditor({ config, record, lookups, onClose, onSaved }) {
  const editing = Boolean(record?.id);
  const [form,   setForm]   = useState({ ...(config.createDefaults ?? {}), ...(record ?? {}) });
  const [files,  setFiles]  = useState({});
  const [error,  setError]  = useState('');
  const [saving, setSaving] = useState(false);

  const fields = config.fields.filter(
    (f) => !(f.editOnly && !editing) && f.type !== 'hidden',
  );

  const handleChange = (e) => {
    const { name, value, files: selected } = e.target;
    if (selected && selected[0]) {
      setFiles((prev) => ({ ...prev, [name]: selected[0] }));
    } else {
      setForm((prev) => ({ ...prev, [name]: value }));
    }
  };

  const submit = async (e) => {
    e.preventDefault();
    setSaving(true);
    setError('');
    try {
      const hasFiles = Object.keys(files).length > 0;
      let body;
      if (hasFiles) {
        body = new FormData();
        Object.entries(form).forEach(([k, v]) => {
          if (v != null && typeof v !== 'object') body.append(k, v);
        });
        Object.entries(files).forEach(([k, v]) => body.append(k, v));
        if (editing) body.append('_method', 'PUT');
        await api.post(
          editing ? `${config.endpoint}/${record.id}` : config.endpoint,
          body,
          { headers: { 'Content-Type': 'multipart/form-data' } },
        );
      } else {
        if (editing) {
          await api.put(`${config.endpoint}/${record.id}`, form);
        } else {
          await api.post(config.endpoint, form);
        }
      }
      onSaved();
    } catch (err) {
      setError(err.message);
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
      {error && <div className="mb-4"><Alert message={error} /></div>}

      <form onSubmit={submit}>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {fields.map((def) => (
            <FormField
              key={def.name}
              definition={def}
              value={form[def.name]}
              onChange={handleChange}
              lookups={lookups}
            />
          ))}
        </div>

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
            style={{ background: '#22c55e', color: '#052e10', border: 'none' }}
            onMouseEnter={(e) => { if (!saving) e.currentTarget.style.background = '#16a34a'; }}
            onMouseLeave={(e) => { e.currentTarget.style.background = '#22c55e'; }}
          >
            <Save size={14} />
            {saving ? 'Saving…' : 'Save Record'}
          </button>
        </div>
      </form>
    </Modal>
  );
}
