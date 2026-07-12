import { Navigate, Route, Routes } from 'react-router-dom';
import { useAuth } from './context/AuthContext';
import Shell from './components/layout/Shell';
import { PageLoader } from './components/ui/LoadingSpinner';

/* Pages */
import Login              from './pages/Login';
import Dashboard          from './pages/Dashboard';
import Patients           from './pages/Patients';
import Doctors            from './pages/Doctors';
import Users              from './pages/Users';
import Appointments       from './pages/Appointments';
import DoctorAppointments from './pages/DoctorAppointments';
import Treatments         from './pages/Treatments';
import Followups          from './pages/Followups';
import Payments           from './pages/Payments';
import Finance            from './pages/Finance';
import Prescriptions      from './pages/Prescriptions';
import Inventory          from './pages/Inventory';
import Pharmacy           from './pages/Pharmacy';
import Laboratory         from './pages/Laboratory';
import Reports            from './pages/Reports';
import AuditLogs          from './pages/AuditLogs';
import Profile            from './pages/Profile';
import Notifications      from './pages/Notifications';
import Settings           from './pages/Settings';

/* ── Guard: redirect to /login if not authenticated ── */
function RequireAuth({ children }) {
  const { bootstrap, loading } = useAuth();
  if (loading) return <PageLoader />;
  if (!bootstrap) return <Navigate to="/login" replace />;
  return children;
}

/* ── Guard: redirect authenticated users away from /login ── */
function GuestOnly({ children }) {
  const { bootstrap, loading } = useAuth();
  if (loading) return <PageLoader />;
  if (bootstrap) {
    const redirect = bootstrap.user?.role === 'Pharmacy User'
      ? '/pharmacy/dashboard'
      : bootstrap.user?.role === 'Lab User'
        ? '/laboratory'
        : '/dashboard';
    return <Navigate to={redirect} replace />;
  }
  return children;
}

export default function App() {
  return (
    <Routes>
      {/* Public */}
      <Route
        path="/login"
        element={<GuestOnly><Login /></GuestOnly>}
      />

      {/* Protected — all inside the Shell layout */}
      <Route
        path="/"
        element={
          <RequireAuth>
            <Shell />
          </RequireAuth>
        }
      >
        <Route index element={<Navigate to="/dashboard" replace />} />
        <Route path="dashboard"          element={<Dashboard />} />
        <Route path="patients"           element={<Patients />} />
        <Route path="doctors"            element={<Doctors />} />
        <Route path="users"              element={<Users />} />
        <Route path="appointments"       element={<Appointments />} />
        <Route path="doctor-appointments"element={<DoctorAppointments />} />
        <Route path="treatments"         element={<Treatments />} />
        <Route path="followups"          element={<Followups />} />
        <Route path="payments"           element={<Payments />} />
        <Route path="finance"            element={<Finance />} />
        <Route path="prescriptions"      element={<Prescriptions />} />
        <Route path="inventory"          element={<Inventory />} />
        <Route path="pharmacy"           element={<Navigate to="/pharmacy/dashboard" replace />} />
        <Route path="pharmacy/:section"  element={<Pharmacy />} />
        <Route path="laboratory"         element={<Laboratory />} />
        <Route path="reports"            element={<Reports />} />
        <Route path="audit-logs"         element={<AuditLogs />} />
        <Route path="notifications"      element={<Notifications />} />
        <Route path="settings"           element={<Settings />} />
        <Route path="profile"            element={<Profile />} />
      </Route>

      {/* Catch-all */}
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  );
}
