import { badgeClass } from '../../utils/formatters';

const STATUS_COLS = new Set([
  'status', 'progress', 'payment_status', 'result', 'movement_type',
]);

export function isStatusColumn(column) {
  return STATUS_COLS.has(column.split('.').pop());
}

export default function StatusBadge({ value }) {
  const cls = badgeClass(value);
  return (
    <span
      className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize ${cls}`}
    >
      {value ?? '—'}
    </span>
  );
}
