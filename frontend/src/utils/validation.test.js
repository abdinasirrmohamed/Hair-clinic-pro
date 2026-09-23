import test from 'node:test';
import assert from 'node:assert/strict';
import { validateFields, passwordPattern } from './validation.js';
import { modules } from '../modules.js';

test('required patient fields reject empty and whitespace values', () => {
  const errors = validateFields(modules.patients.fields, { full_name: 'Patient', phone: '1', gender: 'Female', date_of_birth: '', address: '   ', assigned_doctor_id: null });
  assert.deepEqual(Object.keys(errors).sort(), ['address', 'assigned_doctor_id', 'date_of_birth']);
});

test('money rejects negative, excessive, non-finite and overly precise values', () => {
  const field = modules.finance.fields.find((item) => item.name === 'amount');
  for (const value of [-1, 0, 'Infinity', 'NaN', 100000000, '1.001']) assert.ok(validateFields([field], { amount: value }).amount);
  for (const value of [0.01, '12.50', 99999999.99]) assert.deepEqual(validateFields([field], { amount: value }), {});
});

test('stock quantities must be whole nonnegative numbers', () => {
  const field = modules.inventory.fields.find((item) => item.name === 'quantity');
  for (const value of [-1, 1.5, 2147483648]) assert.ok(validateFields([field], { quantity: value }).quantity);
  assert.deepEqual(validateFields([field], { quantity: 0 }), {});
});

test('dates reject impossible dates and future birth dates', () => {
  const field = { name: 'date', label: 'Date', type: 'date', max: '2026-09-23' };
  for (const date of ['2026-02-30', 'invalid', '2026-09-24']) assert.ok(validateFields([field], { date }).date);
  assert.deepEqual(validateFields([field], { date: '2024-02-29' }), {});
});

test('image validation checks type and size without rejecting an existing image', () => {
  const field = { name: 'photo', label: 'Photo', type: 'file', accept: 'image/*', maxBytes: 3 * 1024 * 1024 };
  assert.ok(validateFields([field], { photo: { type: 'text/html', size: 10 } }).photo);
  assert.ok(validateFields([field], { photo: { type: 'image/png', size: 4 * 1024 * 1024 } }).photo);
  assert.deepEqual(validateFields([field], { photo: 'doctors/existing.png' }), {});
});

test('password requirements match uppercase lowercase digits and supported symbols', () => {
  const pattern = new RegExp(`^${passwordPattern}$`);
  assert.ok(pattern.test('Example123!'));
  for (const password of ['short', 'example123!', 'EXAMPLE123!', 'Exampleabc!', 'Example1234']) assert.equal(pattern.test(password), false);
});
