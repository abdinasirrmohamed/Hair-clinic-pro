import { useAuth } from '../context/AuthContext';
import CrudPage from '../components/crud/CrudPage';
import { modules } from '../modules';

export default function Finance() {
  const { lookups, refresh } = useAuth();
  return (
    <CrudPage
      title="Finance"
      subtitle="Expenses, revenue tracking, and profit reports."
      config={modules.finance}
      lookups={lookups}
      onDataChanged={refresh}
    />
  );
}
