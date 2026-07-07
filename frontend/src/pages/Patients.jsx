import { useAuth } from '../context/AuthContext';
import CrudPage from '../components/crud/CrudPage';
import { modules } from '../modules';

export default function Patients() {
  const { lookups, refresh } = useAuth();
  return (
    <CrudPage
      title="Patient Management"
      subtitle="Manage records, medical history, and scheduled sessions."
      config={modules.patients}
      lookups={lookups}
      onDataChanged={refresh}
    />
  );
}
