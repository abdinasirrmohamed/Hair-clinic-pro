import { useAuth } from '../context/AuthContext';
import { useState } from 'react';
import api from '../api';
import CrudPage from '../components/crud/CrudPage';
import ReceiptModal from '../components/ui/ReceiptModal';
import { modules } from '../modules';
import { ReceiptText } from 'lucide-react';

export default function Payments() {
  const { lookups, refresh } = useAuth();
  const [receipt, setReceipt] = useState(null);

  const openReceipt = async (payment) => {
    const { data } = await api.get(`/payments/${payment.id}`);
    setReceipt(data);
  };

  return (
    <>
      <CrudPage
        title="Payments"
        subtitle="Patient payments, receipts, and balances."
        config={modules.payments}
        lookups={lookups}
        onDataChanged={refresh}
        renderActions={(row) => (
          <button
            onClick={() => openReceipt(row)}
            title="Receipt"
            className="p-2 rounded-lg transition-colors"
            style={{ color: 'var(--clr-muted)' }}
            onMouseEnter={(e) => { e.currentTarget.style.color = '#22c55e'; e.currentTarget.style.background = 'var(--clr-accent-soft)'; }}
            onMouseLeave={(e) => { e.currentTarget.style.color = 'var(--clr-muted)'; e.currentTarget.style.background = 'transparent'; }}
          >
            <ReceiptText size={14} />
          </button>
        )}
      />
      <ReceiptModal receipt={receipt} onClose={() => setReceipt(null)} />
    </>
  );
}
