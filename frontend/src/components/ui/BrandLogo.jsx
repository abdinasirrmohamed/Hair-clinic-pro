import { Activity } from 'lucide-react';

export default function BrandLogo({ size = 'md', showName = false, subtitle = '' }) {
  const sizes = { sm: { box: 32, radius: 8, icon: 16 }, md: { box: 40, radius: 12, icon: 20 }, lg: { box: 48, radius: 14, icon: 24 } };
  const current = sizes[size] ?? sizes.md;
  return (
    <div className="flex items-center gap-3">
      <div className="grid shrink-0 place-items-center text-white shadow-sm" style={{ width: current.box, height: current.box, borderRadius: current.radius, background: 'linear-gradient(135deg, var(--clr-accent), var(--clr-accent-hover))', boxShadow: '0 8px 20px var(--clr-accent-soft)' }} aria-hidden="true"><Activity size={current.icon} /></div>
      {showName && <div className="min-w-0"><p className="truncate font-bold" style={{ color: 'var(--clr-text)' }}>Hair Clinic Pro</p>{subtitle && <p className="text-xs" style={{ color: 'var(--clr-muted)' }}>{subtitle}</p>}</div>}
    </div>
  );
}
