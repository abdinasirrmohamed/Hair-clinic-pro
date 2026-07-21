import PasswordInput from './PasswordInput';

export default function FormField({ definition, value, onChange, lookups }) {
  if (definition.type === 'hidden') return null;

  const inputStyle = {
    width: '100%',
    padding: '.625rem .75rem',
    borderRadius: '.625rem',
    border: '1px solid var(--clr-border)',
    background: 'var(--clr-search-bg)',
    color: 'var(--clr-text)',
    fontSize: '.875rem',
    outline: 'none',
    fontFamily: 'inherit',
    transition: 'border-color .15s, box-shadow .15s',
  };

  const focusStyle = {
    borderColor: '#7c3aed',
    boxShadow: '0 0 0 3px rgba(124,58,237,.15)',
  };

  const addFocus = (e) => Object.assign(e.target.style, focusStyle);
  const remFocus = (e) => {
    e.target.style.borderColor = 'var(--clr-border)';
    e.target.style.boxShadow = 'none';
  };

  const common = {
    name: definition.name,
    id: `field-${definition.name}`,
    style: inputStyle,
    onFocus: addFocus,
    onBlur: remFocus,
    required: definition.required,
    disabled: definition.disabled,
    readOnly: definition.readOnly,
  };

  let input;

  if (definition.type === 'textarea') {
    input = (
      <textarea
        {...common}
        value={value ?? ''}
        onChange={onChange}
        rows={3}
      />
    );
  } else if (definition.type === 'file') {
    input = (
      <input
        type="file"
        name={definition.name}
        id={`field-${definition.name}`}
        onChange={onChange}
        style={inputStyle}
        onFocus={addFocus}
        onBlur={remFocus}
      />
    );
  } else if (definition.type === 'select') {
    input = (
      <select {...common} value={value ?? ''} onChange={onChange}>
        <option value="">Select…</option>
        {(definition.options ?? []).map((opt) => (
          <option key={opt} value={opt}>{opt}</option>
        ))}
      </select>
    );
  } else if (definition.type === 'lookup') {
    const lookupRows = lookups?.[definition.lookup] ?? [];
    input = (
      <select {...common} value={value ?? ''} onChange={onChange}>
        <option value="">Select…</option>
        {lookupRows.map((row) => (
          <option key={row.id} value={row.id}>
            {row.full_name ?? row.medicine_name ?? row.treatment_name ?? `#${row.id} — ${row.appointment_date ?? ''}`}
          </option>
        ))}
      </select>
    );
  } else if (definition.type === 'password') {
    input = <PasswordInput {...common} value={value ?? ''} onChange={onChange} />;
  } else {
    input = (
      <input
        {...common}
        type={definition.type ?? 'text'}
        value={value ?? ''}
        onChange={onChange}
        step={definition.step ?? (definition.type === 'number' ? '0.01' : undefined)}
        min={definition.min}
        max={definition.max}
      />
    );
  }

  return (
    <div className={definition.type === 'textarea' ? 'col-span-2' : ''}>
      <label
        htmlFor={`field-${definition.name}`}
        className="block text-[10px] font-semibold uppercase tracking-widest mb-1.5"
        style={{ color: 'var(--clr-section)' }}
      >
        {definition.label}
      </label>
      {input}
    </div>
  );
}
