import { useAuth } from '../context/AuthContext';
import { useEffect, useState } from 'react';
import api, { asRows } from '../api';
import CrudPage from '../components/crud/CrudPage';
import ReceiptModal from '../components/ui/ReceiptModal';
import { modules } from '../modules';
import { History, ReceiptText, RefreshCw } from 'lucide-react';

export default function Payments() {
  const { lookups, refresh } = useAuth();
  const [receipt, setReceipt] = useState(null);
  const [gatewayLogs, setGatewayLogs] = useState([]);
  const [logsError, setLogsError] = useState('');

  const loadGatewayLogs = async () => {
    setLogsError('');
    try {
      const { data } = await api.get('/payments/gateway-logs');
      setGatewayLogs(asRows(data));
    } catch (err) {
      setLogsError(err.message);
    }
  };

  useEffect(() => { loadGatewayLogs(); }, []);

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
            onMouseEnter={(e) => { e.currentTarget.style.color = '#7c3aed'; e.currentTarget.style.background = 'var(--clr-accent-soft)'; }}
            onMouseLeave={(e) => { e.currentTarget.style.color = 'var(--clr-muted)'; e.currentTarget.style.background = 'transparent'; }}
          >
            <ReceiptText size={14} />
          </button>
        )}
      />
      <section className="rounded-xl overflow-hidden mt-5" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
        <div className="px-5 py-4 flex items-center justify-between" style={{ borderBottom: '1px solid var(--clr-border)' }}>
          <div className="flex items-center gap-2">
            <History size={16} className="text-violet-600" />
            <div>
              <h2 className="text-sm font-semibold" style={{ color: 'var(--clr-text)' }}>WaafiPay Gateway History</h2>
              <p className="text-xs" style={{ color: 'var(--clr-muted)' }}>Successful and failed mobile payment attempts.</p>
            </div>
          </div>
          <button onClick={loadGatewayLogs} title="Refresh logs" className="p-2 rounded-lg" style={{ color: 'var(--clr-muted)', border: '1px solid var(--clr-border)' }}>
            <RefreshCw size={14} />
          </button>
        </div>
        {logsError && <p className="px-5 py-3 text-sm text-red-400">{logsError}</p>}
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr style={{ borderBottom: '1px solid var(--clr-border)' }}>
                {['Date', 'Reference', 'Account', 'Amount', 'Status', 'Code', 'Message', 'User'].map((heading) => (
                  <th key={heading} className="px-4 py-3 text-left text-[10px] uppercase tracking-widest whitespace-nowrap" style={{ color: 'var(--clr-section)' }}>{heading}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {gatewayLogs.map((row) => (
                <tr key={row.id} style={{ borderBottom: '1px solid var(--clr-border)' }}>
                  <td className="px-4 py-3 whitespace-nowrap" style={{ color: 'var(--clr-muted)' }}>{new Date(row.created_at).toLocaleString()}</td>
                  <td className="px-4 py-3 font-mono text-xs" style={{ color: 'var(--clr-text)' }}>{row.reference_id}</td>
                  <td className="px-4 py-3" style={{ color: 'var(--clr-muted)' }}>{row.account_masked}</td>
                  <td className="px-4 py-3" style={{ color: 'var(--clr-text)' }}>${row.amount}</td>
                  <td className="px-4 py-3"><span style={{ color: row.status === 'Successful' ? '#7c3aed' : '#f87171' }}>{row.status}</span></td>
                  <td className="px-4 py-3 font-mono text-xs" style={{ color: 'var(--clr-muted)' }}>{row.response_code || '—'}</td>
                  <td className="px-4 py-3 max-w-xs" style={{ color: 'var(--clr-muted)' }}>{row.message}</td>
                  <td className="px-4 py-3 whitespace-nowrap" style={{ color: 'var(--clr-muted)' }}>{row.creator?.full_name || 'System'}</td>
                </tr>
              ))}
              {!gatewayLogs.length && !logsError && (
                <tr><td colSpan="8" className="px-5 py-8 text-center text-sm" style={{ color: 'var(--clr-muted)' }}>No gateway attempts recorded yet.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </section>
      <ReceiptModal receipt={receipt} onClose={() => setReceipt(null)} />
    </>
  );
}
