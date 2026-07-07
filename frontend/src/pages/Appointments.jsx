import { useAuth } from '../context/AuthContext';
import CrudPage from '../components/crud/CrudPage';
import { modules } from '../modules';

export default function Appointments() {
  const { lookups, refresh } = useAuth();
  return (
    <CrudPage
      title="Appointments"
      subtitle="Scheduling, approvals, reminders, and follow-up actions."
      config={modules.appointments}
      lookups={lookups}
      onDataChanged={refresh}
    />
  );
}
