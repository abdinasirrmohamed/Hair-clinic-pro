import StatusBadge, { isStatusColumn } from './StatusBadge';
import { displayValue, valueAt } from '../../utils/formatters';
import { Pencil, Trash2 } from 'lucide-react';

export default function DataTable({
  columns, labels, rows,
  noEdit, noDelete,
  onEdit, onDelete,
  renderActions,
}) {
  const hasActions = !noEdit || !noDelete || renderActions;

  return (
    <div className="overflow-x-auto">
      <table className="w-full border-collapse text-sm">
        <thead>
          <tr style={{ borderBottom: '1px solid var(--clr-border)' }}>
            {columns.map((col) => (
              <th
                key={col}
                className="px-4 py-3 text-left text-[10px] font-semibold uppercase tracking-widest whitespace-nowrap"
                style={{
                  color: 'var(--clr-section)',
                  background: 'var(--clr-card)',
                }}
              >
                {labels?.[col] ?? col.split('.').pop().replaceAll('_', ' ')}
              </th>
            ))}
            {hasActions && (
              <th
                className="px-4 py-3 text-right text-[10px] font-semibold uppercase tracking-widest"
                style={{ color: 'var(--clr-section)', background: 'var(--clr-card)' }}
              >
                Actions
              </th>
            )}
          </tr>
        </thead>
        <tbody>
          {rows.length === 0 ? (
            <tr>
              <td
                colSpan={columns.length + (hasActions ? 1 : 0)}
                className="px-4 py-16 text-center"
                style={{ color: 'var(--clr-section)' }}
              >
                <div className="flex flex-col items-center gap-2">
                  <span className="text-3xl opacity-40">📋</span>
                  <span className="text-xs font-medium">No records found</span>
                </div>
              </td>
            </tr>
          ) : (
            rows.map((row) => (
              <tr
                key={row.id}
                className="transition-colors group"
                style={{ borderBottom: '1px solid var(--clr-border)' }}
                onMouseEnter={(e) => { e.currentTarget.style.background = 'var(--clr-hover)'; }}
                onMouseLeave={(e) => { e.currentTarget.style.background = 'transparent'; }}
              >
                {columns.map((col) => {
                  const raw = valueAt(row, col);
                  const colKey = col.split('.').pop();
                  return (
                    <td
                      key={col}
                      className="px-4 py-3 whitespace-nowrap max-w-[200px] truncate text-sm"
                      style={{ color: 'var(--clr-text)' }}
                    >
                      {isStatusColumn(colKey) ? (
                        <StatusBadge value={raw} />
                      ) : (
                        displayValue(raw, colKey)
                      )}
                    </td>
                  );
                })}
                {hasActions && (
                  <td className="px-4 py-3 text-right">
                    <div className="flex items-center justify-end gap-1.5">
                      {renderActions ? (
                        renderActions(row)
                      ) : (
                        <>
                          {!noEdit && (
                            <button
                              onClick={() => onEdit?.(row)}
                              title="Edit"
                              className="p-2 rounded-lg transition-colors"
                              style={{ color: 'var(--clr-muted)' }}
                              onMouseEnter={(e) => { e.currentTarget.style.color = '#22c55e'; e.currentTarget.style.background = 'var(--clr-accent-soft)'; }}
                              onMouseLeave={(e) => { e.currentTarget.style.color = 'var(--clr-muted)'; e.currentTarget.style.background = 'transparent'; }}
                            >
                              <Pencil size={14} />
                            </button>
                          )}
                          {!noDelete && (
                            <button
                              onClick={() => onDelete?.(row)}
                              title="Delete"
                              className="p-2 rounded-lg transition-colors"
                              style={{ color: 'var(--clr-muted)' }}
                              onMouseEnter={(e) => { e.currentTarget.style.color = '#f87171'; e.currentTarget.style.background = 'rgba(248,113,113,0.08)'; }}
                              onMouseLeave={(e) => { e.currentTarget.style.color = 'var(--clr-muted)'; e.currentTarget.style.background = 'transparent'; }}
                            >
                              <Trash2 size={14} />
                            </button>
                          )}
                        </>
                      )}
                    </div>
                  </td>
                )}
              </tr>
            ))
          )}
        </tbody>
      </table>
    </div>
  );
}
