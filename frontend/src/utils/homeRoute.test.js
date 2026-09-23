import test from 'node:test';
import assert from 'node:assert/strict';
import { homeRoute, pharmacyRedirect } from './homeRoute.js';

const pharmacyUser = { role: 'Pharmacy User' };

test('pharmacy users land directly in the pharmacy workspace', () => {
  assert.equal(homeRoute(pharmacyUser), '/pharmacy/dashboard');
  for (const path of ['/', '/dashboard', '/patients', '/unknown']) {
    assert.equal(pharmacyRedirect(pharmacyUser, path), '/pharmacy/dashboard');
  }
});

test('old pharmacy links go to the corresponding pharmacy screen', () => {
  assert.equal(pharmacyRedirect(pharmacyUser, '/inventory'), '/pharmacy/medicines');
  assert.equal(pharmacyRedirect(pharmacyUser, '/prescriptions'), '/pharmacy/prescription-sales');
  assert.equal(pharmacyRedirect(pharmacyUser, '/reports'), '/pharmacy/reports');
});

test('pharmacy routes and account pages do not redirect in a loop', () => {
  for (const path of ['/pharmacy', '/pharmacy/dashboard', '/pharmacy/pos-sales', '/profile', '/notifications']) {
    assert.equal(pharmacyRedirect(pharmacyUser, path), null);
  }
});

test('other roles keep their own workspaces', () => {
  assert.equal(homeRoute({ role: 'Administrator' }), '/dashboard');
  assert.equal(homeRoute({ role: 'Lab User' }), '/laboratory');
  assert.equal(homeRoute({ role: 'Inventory Officer' }), '/inventory');
  assert.equal(pharmacyRedirect({ role: 'Administrator' }, '/dashboard'), null);
});
