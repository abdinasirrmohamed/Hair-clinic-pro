export const moduleRoutes = [
  { path: '/dashboard', module: 'dashboard', title: 'Dashboard', subtitle: 'Full system overview, activity, and management controls.', icon: 'bi-grid' },
  { path: '/patients', module: 'patients', title: 'Patient Management', subtitle: 'Manage records, medical history, and scheduled sessions.', icon: 'bi-person' },
  { path: '/appointments', module: 'appointments', title: 'Appointments', subtitle: 'Scheduling, approvals, reminders, and follow-up actions.', icon: 'bi-calendar3' },
  { path: '/doctors', module: 'doctors', title: 'Doctors', subtitle: 'Clinical team profiles, schedules, and availability.', icon: 'bi-person-badge' },
  { path: '/doctor-appointments', module: 'doctor_appointments', title: 'My Appointments', subtitle: 'Doctor appointment approvals and daily worklist.', icon: 'bi-calendar-check' },
  { path: '/payments', module: 'payments', title: 'Payments', subtitle: 'Patient payments, receipts, and balances.', icon: 'bi-credit-card' },
  { path: '/finance', module: 'finance', title: 'Finance', subtitle: 'Expenses, revenue tracking, and profit reports.', icon: 'bi-cash-coin' },
  { path: '/treatments', module: 'treatments', title: 'Treatments', subtitle: 'Treatment plans, progress, and operation records.', icon: 'bi-scissors' },
  { path: '/followups', module: 'followups', title: 'Follow-Ups', subtitle: 'Post-treatment reviews and care schedule.', icon: 'bi-clipboard2-check' },
  { path: '/prescriptions', module: 'prescriptions', title: 'Prescriptions', subtitle: 'Doctor prescriptions and dispensing status.', icon: 'bi-prescription2' },
  { path: '/inventory', module: 'inventory', title: 'Inventory', subtitle: 'Stock levels, purchases, suppliers, and movement history.', icon: 'bi-archive' },
  { path: '/pharmacy', module: 'pharmacy', title: 'Pharmacy', subtitle: 'Medicine sales, prescriptions, returns, and receipts.', icon: 'bi-capsule' },
  { path: '/reports', module: 'reports', title: 'Reports', subtitle: 'Daily, weekly, monthly, and role-based analytics.', icon: 'bi-graph-up' },
  { path: '/audit-logs', module: 'audit_logs', title: 'Audit Logs', subtitle: 'System activity and accountability trail.', icon: 'bi-shield-lock' },
  { path: '/users', module: 'users', title: 'Users', subtitle: 'Staff accounts, roles, profiles, and access control.', icon: 'bi-people' },
  { path: '/profile', module: 'profile', title: 'My Profile', subtitle: 'Manage your account details and password.', icon: 'bi-person-circle' },
];

export function routeForPath(pathname) {
  const clean = pathname.replace(/\/+$/, '') || '/dashboard';
  return moduleRoutes.find((route) => route.path === clean) || moduleRoutes[0];
}

export function navigate(path) {
  if (window.location.pathname !== path) history.pushState({}, '', path);
  window.dispatchEvent(new PopStateEvent('popstate'));
}
