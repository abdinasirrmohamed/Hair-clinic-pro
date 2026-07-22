import { Printer, X } from 'lucide-react';
import { money } from '../../utils/formatters';
import BrandLogo from './BrandLogo';

const line = (label, value) => value !== undefined && value !== null && value !== ''
  ? `<tr><td>${label}</td><td>${value}</td></tr>`
  : '';

function paymentRows(receipt) {
  return [
    line('Receipt No', receipt.receipt?.receipt_number),
    line('Patient', receipt.patient?.full_name),
    line('Phone', receipt.patient?.phone),
    line('Amount', money(receipt.amount)),
    line('Method', receipt.payment_method),
    line('Status', receipt.payment_status),
    line('Reference', receipt.reference_number),
    line('Date', receipt.created_at),
  ].join('');
}

function pharmacyRows(receipt) {
  const itemRows = (receipt.medicines ?? []).map((item) => `
    <tr>
      <td>${item.medicine?.medicine_name ?? 'Medicine'}</td>
      <td>${item.quantity}</td>
      <td>${item.frequency ?? '-'}</td>
      <td>${item.instructions ?? '-'}</td>
      <td>${money(item.unit_price)}</td>
      <td>${money(item.subtotal)}</td>
    </tr>
  `).join('');

  return `
    ${line('Sale No', receipt.sale_number)}
    ${line('Customer', receipt.customer_name || receipt.patient?.full_name)}
    ${line('Prescription', receipt.prescription?.prescription_number)}
    ${line('Doctor', receipt.prescription?.doctor?.full_name)}
    ${line('Subtotal', money(receipt.subtotal))}
    ${line('Discount', money(receipt.discount_amount))}
    ${line('Tax', money(receipt.tax_amount))}
    ${line('Total', money(receipt.total_amount))}
    ${line('Amount Paid', money(receipt.amount_paid))}
    ${line('Remaining Balance', money(receipt.remaining_balance))}
    ${line('Method', receipt.payment_method)}
    ${line('Status', receipt.payment_status)}
    ${line('Date', receipt.created_at)}
    ${itemRows ? `<tr><td colspan="2"><table class="items"><thead><tr><th>Medicine</th><th>Qty</th><th>Frequency</th><th>Instructions</th><th>Price</th><th>Total</th></tr></thead><tbody>${itemRows}</tbody></table></td></tr>` : ''}
  `;
}

function printReceipt(type, receipt) {
  const rows = type === 'pharmacy' ? pharmacyRows(receipt) : paymentRows(receipt);
  const title = type === 'pharmacy' ? 'Pharmacy Receipt' : 'Payment Receipt';
  const popup = window.open('', 'receipt-print', 'width=720,height=820');
  if (!popup) return;

  popup.document.write(`
    <!doctype html>
    <html>
      <head>
        <title>${title}</title>
        <style>
          body { font-family: Arial, sans-serif; color: #111827; padding: 28px; }
          .receipt { max-width: 640px; margin: 0 auto; }
          .brand { display: flex; align-items: center; gap: 10px; }
          .brand-mark { display: grid; place-items: center; width: 36px; height: 36px; border-radius: 10px; background: linear-gradient(135deg, #7c3aed, #6d28d9); color: #fff; font-size: 12px; font-weight: 800; }
          h1 { margin: 0; font-size: 22px; }
          .muted { color: #6b7280; font-size: 12px; margin: 4px 0 22px; }
          table { width: 100%; border-collapse: collapse; }
          td, th { border-bottom: 1px solid #e5e7eb; padding: 10px 0; text-align: left; font-size: 13px; }
          td:last-child { text-align: right; font-weight: 700; }
          .items { margin-top: 12px; }
          .items td, .items th { padding: 8px; border: 1px solid #e5e7eb; }
          .items td:last-child { text-align: left; }
          .total { font-size: 18px; color: #6d28d9; }
          @media print { body { padding: 0; } }
        </style>
      </head>
      <body>
        <div class="receipt">
          <div class="brand"><span class="brand-mark">HC</span><h1>Hair Clinic Pro</h1></div>
          <p class="muted">${title}</p>
          <table>${rows}</table>
        </div>
        <script>window.onload = () => { window.print(); window.close(); };</script>
      </body>
    </html>
  `);
  popup.document.close();
}

export default function ReceiptModal({ type = 'payment', receipt, onClose }) {
  if (!receipt) return null;

  const isPharmacy = type === 'pharmacy';
  const title = isPharmacy ? 'Pharmacy Receipt' : 'Payment Receipt';
  const number = isPharmacy ? receipt.sale_number : receipt.receipt?.receipt_number;
  const total = isPharmacy ? receipt.total_amount : receipt.amount;
  const customer = isPharmacy
    ? (receipt.customer_name || receipt.patient?.full_name || 'Walk-in customer')
    : receipt.patient?.full_name;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4" style={{ background: 'rgba(2,6,23,.72)' }}>
      <div className="w-full max-w-lg rounded-xl overflow-hidden" style={{ background: 'var(--clr-card)', border: '1px solid var(--clr-border)' }}>
        <div className="flex items-center justify-between px-5 py-4" style={{ borderBottom: '1px solid var(--clr-border)' }}>
          <div className="flex items-center gap-3">
            <BrandLogo size="sm" />
            <div>
            <h2 className="text-sm font-bold" style={{ color: 'var(--clr-text)' }}>{title}</h2>
            <p className="text-xs mt-1 font-mono" style={{ color: 'var(--clr-muted)' }}>{number}</p>
            </div>
          </div>
          <button onClick={onClose} className="p-2 rounded-lg" style={{ color: 'var(--clr-muted)' }}>
            <X size={16} />
          </button>
        </div>

        <div className="p-5 space-y-3">
          {[
            ['Customer', customer],
            ['Payment Method', receipt.payment_method],
            ['Payment Status', receipt.payment_status],
            ['Prescription', receipt.prescription?.prescription_number],
            ['Doctor', receipt.prescription?.doctor?.full_name],
            ['Amount Paid', isPharmacy ? money(receipt.amount_paid) : money(receipt.paid_amount)],
            ['Remaining Balance', isPharmacy ? money(receipt.remaining_balance) : money(receipt.remaining_amount)],
            ['Reference', receipt.reference_number],
            ['Date', receipt.created_at],
          ].filter(([, value]) => value).map(([label, value]) => (
            <div key={label} className="flex justify-between gap-4 text-sm">
              <span style={{ color: 'var(--clr-muted)' }}>{label}</span>
              <span className="font-semibold text-right" style={{ color: 'var(--clr-text)' }}>{value}</span>
            </div>
          ))}

          {isPharmacy && (
            <div className="pt-2 space-y-2">
              {(receipt.medicines ?? []).map((item) => (
                <div key={item.id} className="flex justify-between gap-3 text-sm">
                  <span style={{ color: 'var(--clr-text)' }}>{item.medicine?.medicine_name}<small className="block" style={{ color: 'var(--clr-muted)' }}>{item.frequency} · {item.instructions}</small></span>
                  <span className="font-semibold text-violet-600">{item.quantity} x {money(item.unit_price)}</span>
                </div>
              ))}
            </div>
          )}

          <div className="flex justify-between items-center pt-4 mt-4" style={{ borderTop: '1px solid var(--clr-border)' }}>
            <span className="text-sm font-semibold" style={{ color: 'var(--clr-muted)' }}>Total</span>
            <span className="text-2xl font-bold text-violet-600">{money(total)}</span>
          </div>
        </div>

        <div className="flex justify-end gap-2 px-5 py-4" style={{ borderTop: '1px solid var(--clr-border)' }}>
          <button
            onClick={() => printReceipt(type, receipt)}
            className="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold"
            style={{ background: '#7c3aed', color: '#ffffff' }}
          >
            <Printer size={15} />
            Print
          </button>
        </div>
      </div>
    </div>
  );
}
