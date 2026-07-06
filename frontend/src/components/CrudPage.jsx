import { useEffect, useMemo, useState } from 'react';
import { api, asRows } from '../api.js';

function valueAt(row, path) {
  return path.split('.').reduce((value, key) => value?.[key], row);
}

function display(value, column) {
  if (value == null || value === '') return '—';
  if (column === 'amount' || column === 'cost' || column === 'unit_price') return `$${Number(value).toFixed(2)}`;
  if (column.endsWith('_at')) return new Date(value).toLocaleString();
  return String(value);
}

function Input({ definition, value, onChange, lookups }) {
  if (definition.type === 'hidden') return null;
  const common = { name: definition.name, value: value ?? '', onChange };
  if (definition.type === 'textarea') return <textarea {...common} rows="3" />;
  if (definition.type === 'file') return <input name={definition.name} type="file" onChange={onChange} />;
  if (definition.type === 'select') return (
    <select {...common}><option value="">Select...</option>{definition.options.map((option) => <option key={option}>{option}</option>)}</select>
  );
  if (definition.type === 'lookup') {
    const rows = lookups[definition.lookup] || [];
    return (
      <select {...common}>
        <option value="">Select...</option>
        {rows.map((row) => <option value={row.id} key={row.id}>{row.full_name || row.medicine_name || row.treatment_name || `${row.appointment_date} (#${row.id})`}</option>)}
      </select>
    );
  }
  return <input {...common} type={definition.type || 'text'} step={definition.type === 'number' ? '0.01' : undefined} />;
}

function Editor({ config, record, lookups, onClose, onSaved }) {
  const editing = Boolean(record?.id);
  const [form, setForm] = useState({ ...(config.createDefaults || {}), ...(record || {}) });
  const [files, setFiles] = useState({});
  const [error, setError] = useState('');
  const [saving, setSaving] = useState(false);
  const fields = config.fields.filter((item) => !(item.editOnly && !editing));

  const change = (event) => {
    const { name, value, files: selected } = event.target;
    if (selected) setFiles((current) => ({ ...current, [name]: selected[0] }));
    else setForm((current) => ({ ...current, [name]: value }));
  };

  const submit = async (event) => {
    event.preventDefault();
    setSaving(true);
    setError('');
    try {
      const hasFiles = Object.keys(files).length > 0;
      let body;
      if (hasFiles) {
        body = new FormData();
        Object.entries(form).forEach(([key, value]) => {
          if (value != null && typeof value !== 'object') body.append(key, value);
        });
        Object.entries(files).forEach(([key, value]) => body.append(key, value));
        if (editing) body.append('_method', 'PUT');
      } else {
        body = JSON.stringify(form);
      }
      await api(editing ? `${config.endpoint}/${record.id}` : config.endpoint, {
        method: editing && !hasFiles ? 'PUT' : 'POST',
        body,
      });
      onSaved();
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="modal-backdrop" role="presentation" onMouseDown={onClose}>
      <section className="crud-modal" role="dialog" onMouseDown={(event) => event.stopPropagation()}>
        <div className="modal-title"><div><h2>{editing ? 'Edit Record' : 'Add New Record'}</h2><p>Complete the details below.</p></div><button type="button" onClick={onClose}>×</button></div>
        {error && <div className="alert alert-danger">{error}</div>}
        <form onSubmit={submit}>
          <div className="form-grid">
            {fields.map((definition) => (
              <label className={definition.type === 'textarea' ? 'wide-field' : ''} key={definition.name}>
                <span>{definition.label}</span>
                <Input definition={definition} value={form[definition.name]} onChange={change} lookups={lookups} />
              </label>
            ))}
          </div>
          <div className="modal-actions"><button type="button" className="btn btn-light" onClick={onClose}>Cancel</button><button className="btn btn-primary" disabled={saving}>{saving ? 'Saving...' : 'Save Record'}</button></div>
        </form>
      </section>
    </div>
  );
}

export function CrudPage({ route, config, lookups, onDataChanged }) {
  const [rows, setRows] = useState([]);
  const [summary, setSummary] = useState(null);
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [editor, setEditor] = useState(undefined);

  const load = async () => {
    setLoading(true);
    setError('');
    try {
      const result = await api(`${config.endpoint}?search=${encodeURIComponent(search)}`);
      setRows(asRows(config.payloadKey ? result[config.payloadKey] : result));
      setSummary(result?.summary || null);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [config.endpoint]);
  const filtered = useMemo(() => {
    const needle = search.toLowerCase();
    return rows.filter((row) => config.columns.some((column) => String(valueAt(row, column) ?? '').toLowerCase().includes(needle)));
  }, [rows, search, config]);

  const remove = async (row) => {
    if (!confirm('Are you sure you want to delete this record?')) return;
    try {
      await api(`${config.endpoint}/${row.id}`, { method: 'DELETE' });
      await load();
      onDataChanged?.();
    } catch (err) {
      setError(err.message);
    }
  };

  const saved = async () => {
    setEditor(undefined);
    await load();
    onDataChanged?.();
  };

  return (
    <>
      <div className="patient-head">
        <div><h1>{route.title}</h1><p>{route.subtitle}</p></div>
        <button className="add-patient-btn" type="button" onClick={() => setEditor(null)}><i className="bi bi-plus-lg" />Add New</button>
      </div>
      {summary && <div className="summary-strip"><span>Revenue <strong>${Number(summary.total_revenue || 0).toFixed(2)}</strong></span><span>Expenses <strong>${Number(summary.total_expenses || 0).toFixed(2)}</strong></span><span>Net Profit <strong>${Number(summary.net_profit || 0).toFixed(2)}</strong></span></div>}
      <section className="patient-management-card">
        <div className="list-toolbar">
          <div><h2>{route.title}</h2><p>{filtered.length} records</p></div>
          <form onSubmit={(event) => { event.preventDefault(); load(); }}><i className="bi bi-search" /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search records..." /></form>
        </div>
        {error && <div className="alert alert-danger">{error}</div>}
        {loading ? <div className="empty-state">Loading...</div> : (
          <div className="responsive-table">
            <table className="data-table">
              <thead><tr>{config.columns.map((column) => <th key={column}>{config.labels[column] || column}</th>)}<th>Actions</th></tr></thead>
              <tbody>
                {filtered.map((row) => (
                  <tr key={row.id}>
                    {config.columns.map((column) => <td key={column}>{column === 'status' || column === 'progress' || column === 'payment_status' ? <span className="status-pill active">{display(valueAt(row, column), column)}</span> : display(valueAt(row, column), column)}</td>)}
                    <td><div className="row-buttons">{!config.noEdit && <button onClick={() => setEditor(row)} title="Edit"><i className="bi bi-pencil-square" /></button>} {!config.noDelete && <button onClick={() => remove(row)} title="Delete"><i className="bi bi-trash" /></button>}</div></td>
                  </tr>
                ))}
                {!filtered.length && <tr><td colSpan={config.columns.length + 1}><div className="empty-state">No records found.</div></td></tr>}
              </tbody>
            </table>
          </div>
        )}
      </section>
      {editor !== undefined && <Editor config={config} record={editor} lookups={lookups} onClose={() => setEditor(undefined)} onSaved={saved} />}
    </>
  );
}
