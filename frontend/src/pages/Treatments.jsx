import { useAuth } from '../context/AuthContext';
import CrudPage from '../components/crud/CrudPage';
import { modules } from '../modules';

export default function Treatments() {
  const { lookups, refresh } = useAuth();
  return (
    <CrudPage
      title="Treatments"
      subtitle="Treatment plans, progress, and operation records."
      config={modules.treatments}
      lookups={lookups}
      onDataChanged={refresh}
    />
  );
}
