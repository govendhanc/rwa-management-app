import QRCode from 'qrcode';
import PDFDocument from 'pdfkit';
import fs from 'fs';
import path from 'path';

function resolveLogoPath(logoUrl) {
  if (!logoUrl) return null;
  const normalized = logoUrl.replace(/^\/+/, '');
  const logoPath = path.resolve(process.cwd(), 'public', normalized);
  return fs.existsSync(logoPath) ? logoPath : null;
}

export async function createReceiptPdf({ association, receipt, payment }) {
  const qrPayload = `Receipt:${receipt.receipt_number}|Plot:${payment.plot_number}|Amount:${payment.paid_amount}`;
  const qrDataUrl = await QRCode.toDataURL(qrPayload);
  const doc = new PDFDocument({ size: 'A4', margin: 48 });
  const chunks = [];
  doc.on('data', (chunk) => chunks.push(chunk));

  const logoPath = resolveLogoPath(association.logo_url);
  if (logoPath) doc.image(logoPath, 48, 42, { width: 72, height: 72, fit: [72, 72] });
  doc.fontSize(16).text(association.name, 130, 42, { align: 'center' });
  doc.fontSize(10).text(association.registration_number || '', { align: 'center' });
  doc.fontSize(10).text(association.address, { align: 'center' });
  doc.fontSize(10).text(`Mobile: ${association.mobile || '-'}   Email: ${association.email || '-'}`, { align: 'center' });
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
