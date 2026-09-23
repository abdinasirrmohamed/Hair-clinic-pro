export function homeRoute(user) {
  if (user?.role === 'Pharmacy User') return '/pharmacy/dashboard';
  if (user?.role === 'Lab User') return '/laboratory';
  if (user?.role === 'Inventory Officer') return '/inventory';
  return '/dashboard';
}

export function pharmacyRedirect(user, pathname) {
  if (user?.role !== 'Pharmacy User') return null;
  if (pathname === '/pharmacy' || pathname.startsWith('/pharmacy/')) return null;
  if (['/profile', '/notifications'].includes(pathname)) return null;
  const legacyRoutes = {
    '/inventory': '/pharmacy/medicines',
    '/inventory/medicines': '/pharmacy/medicines',
    '/prescriptions': '/pharmacy/prescription-sales',
    '/reports': '/pharmacy/reports',
  };
  return legacyRoutes[pathname] || '/pharmacy/dashboard';
}
