import { AlertCircle, AlertTriangle, CheckCircle, Info } from 'lucide-react';

const VARIANTS = {
  danger:  { icon: AlertCircle,   color: '#f87171', bg: 'rgba(248,113,113,0.08)', border: 'rgba(248,113,113,0.2)'  },
  warning: { icon: AlertTriangle, color: '#fbbf24', bg: 'rgba(251,191,36,0.08)',  border: 'rgba(251,191,36,0.2)'  },
  success: { icon: CheckCircle,   color: '#22c55e', bg: 'rgba(34,197,94,0.08)',   border: 'rgba(34,197,94,0.2)'   },
  info:    { icon: Info,          color: '#22c55e', bg: 'rgba(34,197,94,0.06)',   border: 'rgba(34,197,94,0.15)'  },
};

export default function Alert({ message, variant = 'danger' }) {
  if (!message) return null;
  const { icon: Icon, color, bg, border } = VARIANTS[variant] ?? VARIANTS.danger;
  return (
    <div
      className="flex items-start gap-3 p-3.5 rounded-xl animate-fade-in"
      style={{ background: bg, border: `1px solid ${border}` }}
    >
      <Icon size={15} style={{ color, flexShrink: 0, marginTop: 1 }} />
      <p className="text-sm font-medium" style={{ color }}>{message}</p>
    </div>
  );
}
