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
import Reports            from './pages/Reports';
import AuditLogs          from './pages/AuditLogs';
import Profile            from './pages/Profile';

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
  if (bootstrap) return <Navigate to="/dashboard" replace />;
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
        <Route path="pharmacy"           element={<Pharmacy />} />
        <Route path="reports"            element={<Reports />} />
        <Route path="audit-logs"         element={<AuditLogs />} />
        <Route path="profile"            element={<Profile />} />
      </Route>

      {/* Catch-all */}
      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  );
}
