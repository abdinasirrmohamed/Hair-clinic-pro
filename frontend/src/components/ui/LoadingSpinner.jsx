export default function LoadingSpinner({ text = 'Loading…' }) {
  return (
    <div className="flex flex-col items-center justify-center py-16 gap-4 animate-fade-in">
      <div
        className="w-8 h-8 rounded-full animate-spin"
        style={{ border: '3px solid var(--clr-border)', borderTopColor: '#7c3aed' }}
      />
      <p className="text-xs font-medium" style={{ color: 'var(--clr-muted)' }}>{text}</p>
    </div>
  );
}

export function PageLoader({ text = 'Loading Hair Clinic Pro…' }) {
  return (
    <div
      className="fixed inset-0 flex flex-col items-center justify-center gap-6 z-50"
      style={{ background: 'var(--clr-body)' }}
    >
      <div className="flex items-center gap-3">
        <div className="w-10 h-10 rounded-xl bg-green-500 flex items-center justify-center">
          <span className="text-[#ffffff] font-bold text-sm">HC</span>
        </div>
        <span className="text-xl font-bold" style={{ color: 'var(--clr-text)' }}>
          Hair Clinic Pro
        </span>
      </div>
      <div
        className="w-7 h-7 rounded-full animate-spin"
        style={{ border: '3px solid var(--clr-border)', borderTopColor: '#7c3aed' }}
      />
      <p className="text-xs" style={{ color: 'var(--clr-muted)' }}>{text}</p>
    </div>
  );
}
