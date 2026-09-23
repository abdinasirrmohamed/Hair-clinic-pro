export function validateFields(fields, values) {
  const errors = {};
  for (const field of fields) {
    if (field.readOnly || field.disabled) continue;
    const raw = values[field.name];
    const value = typeof raw === 'string' ? raw.trim() : raw;
    const blank = value === '' || value == null;
    if (blank) {
      if (field.required) errors[field.name] = `${field.label} is required.`;
      continue;
    }
    if (field.type === 'file') {
      if (typeof value !== 'object') continue;
      if (field.maxBytes && value.size > field.maxBytes) errors[field.name] = `${field.label} must be smaller than ${field.maxBytes / 1024 / 1024} MB.`;
      if (field.accept?.includes('image') && !['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp'].includes(value.type)) errors[field.name] = `${field.label} must be a valid image.`;
      continue;
    }
    if (field.maxLength && String(value).length > field.maxLength) errors[field.name] = `${field.label} must not exceed ${field.maxLength} characters.`;
    if (field.type === 'number') {
      const number = Number(value);
      if (!Number.isFinite(number)) errors[field.name] = `${field.label} must be a valid number.`;
      else if (field.step === 1 && !Number.isInteger(number)) errors[field.name] = `${field.label} must be a whole number.`;
      else if (field.min != null && number < field.min) errors[field.name] = `${field.label} must be at least ${field.min}.`;
      else if (field.max != null && number > field.max) errors[field.name] = `${field.label} must not exceed ${field.max}.`;
      else if (field.step === 0.01 && !/^-?\d+(\.\d{1,2})?$/.test(String(value))) errors[field.name] = `${field.label} must have at most two decimal places.`;
    }
    if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(value))) errors[field.name] = 'Enter a valid email address.';
    if (field.options && !field.options.includes(value)) errors[field.name] = `Select a valid ${field.label.toLowerCase()}.`;
    if (field.type === 'date') {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(value) || Number.isNaN(Date.parse(value)) || new Date(value).toISOString().slice(0, 10) !== value) errors[field.name] = `${field.label} must be a valid date.`;
      else if (field.min && value < field.min) errors[field.name] = `${field.label} must be on or after ${field.min}.`;
      else if (field.max && value > field.max) errors[field.name] = `${field.label} must be on or before ${field.max}.`;
    }
  }
  return errors;
}

export const passwordPattern = '(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[@$!%*#?&]).{8,}';
export const passwordHelp = 'Use at least 8 characters, including uppercase, lowercase, a number and one of @$!%*#?&.';
