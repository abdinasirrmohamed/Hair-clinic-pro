import JsBarcode from 'jsbarcode';
import { money } from './formatters';

export const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
function printDocument(title, css, body) {
  const popup = window.open('', '_blank', 'width=760,height=850');
  if (!popup) throw new Error('Allow popups to open the print preview.');
  popup.onload = () => popup.print();
  popup.document.write(`<!doctype html><html><head><title>${escapeHtml(title)}</title><style>${css}</style></head><body>${body}</body></html>`);
  popup.document.close();
  popup.focus();
  // Keep the preview open so a cancelled print can be retried.
}

export function printLabels(products) {
  const count = products.reduce((sum,p)=>sum+p.copies,0);
  if (!count || count > 500) throw new Error('Select between 1 and 500 labels per print job.');
  const labels = products.map(p => {
    if (!p.barcode || !Number.isInteger(p.copies) || p.copies < 1 || p.copies > 100) throw new Error('Each product needs a barcode and 1–100 copies.');
    const svg = document.createElementNS('http://www.w3.org/2000/svg','svg');
    try { JsBarcode(svg,String(p.barcode),{format:'CODE128',width:1.5,height:38,fontSize:11,margin:8,background:'#ffffff',lineColor:'#000000'}); }
    catch { throw new Error(`The barcode for ${p.medicine_name} cannot be encoded as CODE128.`); }
    return Array.from({length:p.copies},()=>`<article><strong>${escapeHtml(p.medicine_name)}</strong><span>${escapeHtml(money(p.unit_price))} · ${escapeHtml(p.batch_number || '')}</span>${svg.outerHTML}</article>`).join('');
  }).join('');
  printDocument('Pharmacy barcode labels','@page{size:50mm 30mm;margin:0}*{box-sizing:border-box}body{margin:0;font-family:Arial;color:#000;background:#fff}article{width:50mm;height:30mm;padding:2mm;overflow:hidden;break-after:page;text-align:center}article:last-child{break-after:auto}strong{font-size:10px;display:block;max-height:8mm;overflow:hidden}span{display:block;font-size:10px;margin:1mm 0}svg{width:46mm;height:15mm;display:block}',labels);
}

export function printThermal(receipt,width=80) {
  width = Number(width) === 58 ? 58 : 80;
  const row=(label,value)=>`<div class="row"><span>${escapeHtml(label)}</span><b>${escapeHtml(value)}</b></div>`;
  const items=(receipt.medicines||[]).map(i=>`<section><strong>${escapeHtml(i.medicine?.medicine_name||'Medicine')}</strong>${i.medicine?.batch_number?`<small>Batch ${escapeHtml(i.medicine.batch_number)}</small>`:''}${row(`${i.quantity} × ${money(i.unit_price)}`,money(i.subtotal))}${i.frequency?`<small>${escapeHtml(i.frequency)}</small>`:''}${i.instructions?`<small>${escapeHtml(i.instructions)}</small>`:''}</section>`).join('');
  printDocument('Pharmacy Receipt',`@page{size:${width}mm auto;margin:2mm}*{box-sizing:border-box}body{width:${width-4}mm;margin:0;padding:2mm;font:11px Arial;color:#000;background:#fff;overflow-wrap:anywhere}h1{font-size:17px;margin:0}header{text-align:center;border-bottom:1px dashed #000;padding-bottom:8px}p{margin:4px 0}.row{display:flex;justify-content:space-between;gap:8px;margin:5px 0}.row b{text-align:right}section{padding:7px 0;border-bottom:1px dashed #777;break-inside:avoid}small{display:block;font-size:10px;margin:3px 0}.totals{border-bottom:1px dashed #000;padding:8px 0}footer{text-align:center;margin-top:10px}`,`<header><h1>Hair Clinic Pro</h1><p>Pharmacy receipt</p><p>${escapeHtml(receipt.sale_number)}</p><p>${escapeHtml(receipt.created_at)}</p></header><p>Customer: ${escapeHtml(receipt.customer_name||receipt.patient?.full_name||'Walk-in')}</p><p>Cashier: ${escapeHtml(receipt.creator?.full_name||'—')}</p>${items}<div class="totals">${row('Subtotal',money(receipt.subtotal))}${row('Discount',money(receipt.discount_amount))}${row('Tax',money(receipt.tax_amount))}${row('TOTAL',money(receipt.total_amount))}${row('Paid',money(receipt.amount_paid))}${row('Balance',money(receipt.remaining_balance))}${row('Method',receipt.payment_method)}${row('Status',receipt.status||receipt.payment_status)}</div>${receipt.notes?`<p>${escapeHtml(receipt.notes)}</p>`:''}<footer>Thank you for your purchase.</footer>`);
}
