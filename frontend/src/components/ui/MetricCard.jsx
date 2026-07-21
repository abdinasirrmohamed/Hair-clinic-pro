import { TrendingUp } from 'lucide-react';

/* All cards use the same green accent — single unified color */
export default function MetricCard({ label, value, icon: Icon, index = 0 }) {
  return (
    <div
      className="rounded-xl p-5 border transition-all hover:shadow-md animate-slide-up"
      style={{
        background: 'var(--clr-card)',
        borderColor: 'var(--clr-border)',
        boxShadow: '0 1px 3px rgba(0,0,0,0.08)',
      }}
    >
      <div className="flex items-start justify-between">
        {/* Green icon badge */}
        <div
          className="w-10 h-10 rounded-lg flex items-center justify-center"
          style={{ background: 'var(--clr-accent-soft)', border: '1px solid rgba(124,58,237,.2)' }}
        >
          {Icon ? (
            <Icon size={18} className="text-violet-600" />
          ) : (
            <TrendingUp size={18} className="text-violet-600" />
          )}
        </div>

        {/* Live dot */}
        <span
          className="flex items-center gap-1 text-[10px] font-semibold px-2 py-0.5 rounded-full"
          style={{ background: 'var(--clr-accent-soft)', color: '#7c3aed' }}
        >
          <span className="w-1 h-1 rounded-full bg-green-500 animate-pulse" />
          Live
        </span>
      </div>

      <div className="mt-4">
        <p className="text-xs font-medium" style={{ color: 'var(--clr-muted)' }}>{label}</p>
        <p className="mt-1 text-3xl font-bold tracking-tight" style={{ color: 'var(--clr-text)' }}>
          {value ?? 0}
        </p>
      </div>
    </div>
  );
}
