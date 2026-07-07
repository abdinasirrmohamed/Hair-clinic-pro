import { useAuth } from '../context/AuthContext';
import CrudPage from '../components/crud/CrudPage';
import { modules } from '../modules';

export default function Followups() {
  const { lookups, refresh } = useAuth();
  return (
    <CrudPage
      title="Follow-Ups"
      subtitle="Post-treatment reviews and care schedule."
      config={modules.followups}
      lookups={lookups}
      onDataChanged={refresh}
    />
  );
}
