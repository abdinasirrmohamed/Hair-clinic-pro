import { X } from 'lucide-react';

export default function Modal({ title, subtitle, onClose, children, size = 'md' }) {
  const widths = { sm: 'max-w-md', md: 'max-w-2xl', lg: 'max-w-4xl', xl: 'max-w-6xl' };

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center p-4 animate-fade-in"
      style={{ background: 'rgba(5,46,16,0.6)', backdropFilter: 'blur(6px)' }}
      onMouseDown={onClose}
    >
      <div
        className={`relative w-full ${widths[size]} max-h-[90vh] flex flex-col rounded-2xl shadow-2xl animate-scale-in overflow-hidden`}
        style={{
          background: 'var(--clr-card)',
          border: '1px solid var(--clr-border)',
          boxShadow: '0 25px 60px rgba(0,0,0,0.4)',
        }}
        onMouseDown={(e) => e.stopPropagation()}
      >
        {/* Header */}
        <div
          className="flex items-start justify-between gap-4 px-6 py-5"
          style={{ borderBottom: '1px solid var(--clr-border)' }}
        >
          <div>
            <h2 className="text-base font-bold" style={{ color: 'var(--clr-text)' }}>{title}</h2>
            {subtitle && (
              <p className="mt-0.5 text-xs" style={{ color: 'var(--clr-muted)' }}>{subtitle}</p>
            )}
          </div>
          <button
            type="button"
            onClick={onClose}
            className="shrink-0 p-1.5 rounded-lg transition-colors"
            style={{ color: 'var(--clr-muted)' }}
            onMouseEnter={(e) => { e.currentTarget.style.background = 'var(--clr-hover)'; e.currentTarget.style.color = 'var(--clr-text)'; }}
            onMouseLeave={(e) => { e.currentTarget.style.background = 'transparent'; e.currentTarget.style.color = 'var(--clr-muted)'; }}
          >
            <X size={16} />
          </button>
        </div>

        {/* Body */}
        <div className="flex-1 overflow-y-auto px-6 py-5">{children}</div>
      </div>
    </div>
  );
}
