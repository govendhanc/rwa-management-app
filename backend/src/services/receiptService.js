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
  doc.roundedRect(42, 36, 510, 690, 8).stroke('#d8dee9');
  if (logoPath) doc.image(logoPath, 62, 54, { width: 70, height: 70, fit: [70, 70] });
  doc.fontSize(17).fillColor('#102a43').text(association.name, 145, 54, { width: 360, align: 'center' });
  doc.fontSize(9).fillColor('#52606d').text(association.registration_number || '', { align: 'center' });
  doc.fontSize(9).text(association.address, { align: 'center' });
  doc.fontSize(9).text(`Mobile: ${association.mobile || '-'}   Email: ${association.email || '-'}`, { align: 'center' });

  doc.moveTo(62, 144).lineTo(532, 144).stroke('#d8dee9');
  doc.fontSize(15).fillColor('#102a43').text('Maintenance Payment Receipt', 62, 166, { align: 'center' });
  doc.fontSize(10).fillColor('#52606d').text(`Receipt No. ${receipt.receipt_number}`, 62, 194, { align: 'center' });

  const rows = [
    ['Date', receipt.receipt_date],
    ['Plot Number', payment.plot_number],
    ['Owner Name', payment.owner_name],
    ['Amount Paid', `Rs. ${Number(payment.paid_amount || 0).toFixed(2)}`],
    ['Payment Mode', payment.payment_mode || '-'],
    ['Reference Number', payment.transaction_number || '-'],
    ['Remarks', payment.remarks || '-']
  ];
  let y = 244;
  rows.forEach(([label, value]) => {
    doc.fontSize(10).fillColor('#52606d').text(label, 82, y, { width: 150 });
    doc.fontSize(11).fillColor('#102a43').text(String(value || '-'), 240, y, { width: 250 });
    y += 34;
  });

  doc.roundedRect(82, y + 8, 220, 58, 6).fillAndStroke('#f5f7fa', '#e4e7eb');
  doc.fontSize(10).fillColor('#52606d').text('Balance After Payment', 102, y + 24);
  doc.fontSize(14).fillColor('#102a43').text(`Rs. ${Number(payment.balance || 0).toFixed(2)}`, 102, y + 42);
  doc.image(qrDataUrl, 410, y + 4, { width: 92 });

  doc.moveTo(360, 650).lineTo(512, 650).stroke('#9aa5b1');
  doc.fontSize(10).fillColor('#102a43').text('Authorized Signature', 382, 662);
  doc.fontSize(8).fillColor('#7b8794').text('This is a computer generated receipt. Reprint is valid with matching receipt number.', 62, 700, { align: 'center' });
  doc.end();

  return new Promise((resolve) => doc.on('end', () => resolve(Buffer.concat(chunks))));
}
