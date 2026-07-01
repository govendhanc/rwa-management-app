import QRCode from 'qrcode';
import PDFDocument from 'pdfkit';

export async function createReceiptPdf({ association, receipt, payment }) {
  const qrPayload = `Receipt:${receipt.receipt_number}|Plot:${payment.plot_number}|Amount:${payment.paid_amount}`;
  const qrDataUrl = await QRCode.toDataURL(qrPayload);
  const doc = new PDFDocument({ size: 'A4', margin: 48 });
  const chunks = [];
  doc.on('data', (chunk) => chunks.push(chunk));

  doc.fontSize(18).text(association.name, { align: 'center' });
  doc.fontSize(10).text(association.address, { align: 'center' });
  doc.moveDown();
  doc.fontSize(14).text('Maintenance Receipt', { align: 'center', underline: true });
  doc.moveDown();
  doc.fontSize(11).text(`Receipt Number: ${receipt.receipt_number}`);
  doc.text(`Date: ${receipt.receipt_date}`);
  doc.text(`Plot Number: ${payment.plot_number}`);
  doc.text(`Owner Name: ${payment.owner_name}`);
  doc.text(`Payment Mode: ${payment.payment_mode}`);
  doc.text(`Transaction Number: ${payment.transaction_number || '-'}`);
  doc.text(`Amount Paid: Rs. ${payment.paid_amount}`);
  doc.text(`Balance: Rs. ${payment.balance}`);
  doc.moveDown();
  doc.image(qrDataUrl, 48, 270, { width: 110 });
  doc.text('Treasurer Signature', 360, 360);
  doc.end();

  return new Promise((resolve) => doc.on('end', () => resolve(Buffer.concat(chunks))));
}
