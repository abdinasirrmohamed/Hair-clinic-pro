import { useAuth } from '../context/AuthContext';
import CrudPage from '../components/crud/CrudPage';
import { modules } from '../modules';

export default function Doctors() {
  const { lookups, refresh } = useAuth();
  return (
    <CrudPage
      title="Doctors"
      subtitle="Clinical team profiles, schedules, and availability."
      config={modules.doctors}
      lookups={lookups}
      onDataChanged={refresh}
    />
  );
}
