import { useAuth } from '../context/AuthContext';
import CrudPage from '../components/crud/CrudPage';
import { modules } from '../modules';

export default function Payments() {
  const { lookups, refresh } = useAuth();
  return (
    <CrudPage
      title="Payments"
      subtitle="Patient payments, receipts, and balances."
      config={modules.payments}
      lookups={lookups}
      onDataChanged={refresh}
    />
  );
}
