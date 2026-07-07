import { useAuth } from '../context/AuthContext';
import CrudPage from '../components/crud/CrudPage';
import { modules } from '../modules';

export default function Users() {
  const { lookups, refresh } = useAuth();
  return (
    <CrudPage
      title="Users"
      subtitle="Staff accounts, roles, profiles, and access control."
      config={modules.users}
      lookups={lookups}
      onDataChanged={refresh}
    />
  );
}
