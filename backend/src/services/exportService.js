import ExcelJS from 'exceljs';
import PDFDocument from 'pdfkit';

export async function rowsToExcel(rows, sheetName = 'Report') {
  const workbook = new ExcelJS.Workbook();
  const worksheet = workbook.addWorksheet(sheetName);
  if (rows.length) {
    worksheet.columns = Object.keys(rows[0]).map((key) => ({
      header: key.replaceAll('_', ' ').toUpperCase(),
      key,
      width: Math.max(14, key.length + 4)
    }));
    worksheet.addRows(rows);
    worksheet.getRow(1).font = { bold: true };
  }
  return workbook.xlsx.writeBuffer();
}

export function rowsToPdf(rows, title = 'Report') {
  const doc = new PDFDocument({ margin: 36, size: 'A4' });
  const chunks = [];
  doc.on('data', (chunk) => chunks.push(chunk));
  doc.fontSize(16).text(title, { underline: true });
  doc.moveDown();
  rows.slice(0, 200).forEach((row, index) => {
    doc.fontSize(10).text(`${index + 1}. ${JSON.stringify(row)}`);
    doc.moveDown(0.25);
  });
  doc.end();
  return new Promise((resolve) => doc.on('end', () => resolve(Buffer.concat(chunks))));
}

export function rowsToCsv(rows) {
  if (!rows.length) return '';
  const headers = Object.keys(rows[0]);
  const escape = (value) => `"${String(value ?? '').replaceAll('"', '""')}"`;
  return [
    headers.map(escape).join(','),
    ...rows.map((row) => headers.map((header) => escape(row[header])).join(','))
  ].join('\n');
}
