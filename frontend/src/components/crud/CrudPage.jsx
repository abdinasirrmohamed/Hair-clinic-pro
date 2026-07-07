import { useCallback, useEffect, useMemo, useState } from 'react';
import api, { asRows } from '../../api';
import DataTable from '../ui/DataTable';
import Alert from '../ui/Alert';
import LoadingSpinner from '../ui/LoadingSpinner';
import CrudEditor from './CrudEditor';
import { Plus, RefreshCw, Search } from 'lucide-react';
import { money } from '../../utils/formatters';

export default function CrudPage({
  title, subtitle, config, lookups, onDataChanged, renderActions,
}) {
  const [rows,    setRows]    = useState([]);
  const [summary, setSummary] = useState(null);
  const [search,  setSearch]  = useState('');
  const [loading, setLoading] = useState(true);
  const [error,   setError]   = useState('');
  const [editor,  setEditor]  = useState(undefined);

  const load = useCallback(async (q = '') => {
    setLoading(true);
    setError('');
    try {
      const params = q ? `?search=${encodeURIComponent(q)}` : '';
      const { data } = await api.get(`${config.endpoint}${params}`);
      setRows(asRows(data, config.payloadKey));
      setSummary(data?.summary ?? null);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, [config.endpoint, config.payloadKey]);

  useEffect(() => { load(); }, [load]);

  const filtered = useMemo(() => {
    if (!search) return rows;
    const needle = search.toLowerCase();
    return rows.filter((row) =>
      config.columns.some((col) => {
        const val = col.split('.').reduce((v, k) => v?.[k], row);
        return String(val ?? '').toLowerCase().includes(needle);
      }),
    );
  }, [rows, search, config.columns]);

  const handleDelete = async (row) => {
    if (!window.confirm('Delete this record? This cannot be undone.')) return;
    setError('');
    try {
      await api.delete(`${config.endpoint}/${row.id}`);
      await load();
      onDataChanged?.();
    } catch (err) {
      setError(err.message);
    }
  };

  const handleSaved = async () => {
    setEditor(undefined);
    await load();
    onDataChanged?.();
  };

  /* Reusable inline button style */
  const btnStyle = {
    display: 'inline-flex', alignItems: 'center', gap: '.375rem',
    padding: '.5rem 1rem', borderRadius: '.625rem',
    background: '#22c55e', color: '#052e10',
    fontWeight: 600, fontSize: '.8125rem', cursor: 'pointer',
    border: 'none', whiteSpace: 'nowrap',
    transition: 'background .15s, transform .1s',
  };

  return (
    <div className="space-y-5 animate-fade-in">
      {/* Page header */}
      <div className="flex items-start justify-between gap-4">
        <div>
          <h1 className="text-xl font-bold" style={{ color: 'var(--clr-text)' }}>{title}</h1>
          {subtitle && (
            <p className="mt-1 text-xs" style={{ color: 'var(--clr-muted)' }}>{subtitle}</p>
          )}
        </div>
        <button
          onClick={() => setEditor(null)}
          style={btnStyle}
          onMouseEnter={(e) => { e.currentTarget.style.background = '#16a34a'; }}
          onMouseLeave={(e) => { e.currentTarget.style.background = '#22c55e'; }}
        >
          <Plus size={14} />
          Add New
        </button>
      </div>

      {/* Finance summary strip */}
      {summary && (
        <div className="grid grid-cols-3 gap-4">
          {[
            ['Revenue',    summary.total_revenue,  'var(--clr-accent-soft)', '#22c55e'],
            ['Expenses',   summary.total_expenses, 'rgba(248,113,113,.1)',   '#f87171'],
            ['Net Profit', summary.net_profit,     'rgba(34,197,94,.06)',    '#22c55e'],
          ].map(([label, val, bg, color]) => (
            <div key={label}
              className="p-4 rounded-xl border"
              style={{ background: bg, borderColor: `${color}30` }}
            >
              <p className="text-xs font-medium" style={{ color }}>{label}</p>
              <p className="text-2xl font-bold mt-1" style={{ color: 'var(--clr-text)' }}>{money(val)}</p>
            </div>
          ))}
        </div>
      )}

      {error && <Alert message={error} />}

      {/* Table card */}
      <div
        className="rounded-xl overflow-hidden"
        style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}
      >
        {/* Toolbar */}
        <div
          className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-4"
          style={{ borderBottom: '1px solid var(--clr-border)' }}
        >
          <div>
            <p className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>{title}</p>
            <p className="text-xs mt-0.5" style={{ color: 'var(--clr-muted)' }}>
              {filtered.length} record{filtered.length !== 1 ? 's' : ''}
            </p>
          </div>
          <div className="flex items-center gap-2">
            <div className="relative">
              <Search size={13} className="absolute left-3 top-1/2 -translate-y-1/2" style={{ color: 'var(--clr-muted)' }} />
              <input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Search…"
                className="pl-8 pr-3 py-2 rounded-lg text-sm outline-none w-48 transition-all"
                style={{
                  background: 'var(--clr-search-bg)',
                  border: '1px solid var(--clr-search-border)',
                  color: 'var(--clr-text)',
                }}
                onFocus={(e) => { e.target.style.borderColor = '#22c55e'; e.target.style.boxShadow = '0 0 0 3px rgba(34,197,94,.12)'; }}
                onBlur={(e) => { e.target.style.borderColor = 'var(--clr-search-border)'; e.target.style.boxShadow = 'none'; }}
              />
            </div>
            <button
              onClick={() => load(search)}
              title="Refresh"
              className="p-2 rounded-lg transition-colors"
              style={{ color: 'var(--clr-muted)', border: '1px solid var(--clr-border)' }}
              onMouseEnter={(e) => { e.currentTarget.style.color = '#22c55e'; e.currentTarget.style.borderColor = '#22c55e44'; }}
              onMouseLeave={(e) => { e.currentTarget.style.color = 'var(--clr-muted)'; e.currentTarget.style.borderColor = 'var(--clr-border)'; }}
            >
              <RefreshCw size={14} />
            </button>
          </div>
        </div>

        {loading ? <LoadingSpinner /> : (
          <DataTable
            columns={config.columns}
            labels={config.labels}
            rows={filtered}
            noEdit={config.noEdit}
            noDelete={config.noDelete}
            onEdit={(row) => setEditor(row)}
            onDelete={handleDelete}
            renderActions={renderActions}
          />
        )}
      </div>

      {editor !== undefined && (
        <CrudEditor
          config={config}
          record={editor}
          lookups={lookups}
          onClose={() => setEditor(undefined)}
          onSaved={handleSaved}
        />
      )}
    </div>
  );
}
