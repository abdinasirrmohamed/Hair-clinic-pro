/* ─── Value utilities ─── */
export const valueAt = (row, path) =>
  path.split('.').reduce((v, k) => v?.[k], row);

/* ─── Display formatters ─── */
export const money = (v) => `$${Number(v || 0).toFixed(2)}`;

export const initials = (name = '') =>
  name.trim().split(/\s+/).map((p) => p[0]).slice(0, 2).join('').toUpperCase();

export function displayValue(value, column) {
  if (value == null || value === '') return '—';
  if (['amount', 'cost', 'unit_price', 'unit_cost', 'total_amount', 'fee_at_booking'].includes(column))
    return money(value);
  if (column.endsWith('_at'))
    return new Date(value).toLocaleDateString('en-US', {
      year: 'numeric', month: 'short', day: 'numeric',
    });
  if (column.endsWith('_date'))
    return new Date(value).toLocaleDateString('en-US', {
      year: 'numeric', month: 'short', day: 'numeric',
    });
  if (column.endsWith('_time'))
    return value.slice(0, 5); // "HH:MM"
  return String(value);
}

/* ─── Status badge class ─── */
export function badgeClass(value) {
  const key = String(value || '').toLowerCase().replace(/\s+/g, '-');
  return `badge-${key}`;
}

/* ─── Column label ─── */
export const columnLabel = (col) =>
  col.split('.').pop().replaceAll('_', ' ');
