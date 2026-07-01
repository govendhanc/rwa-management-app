import express from 'express';
import { query } from '../config/db.js';
import { rowsToCsv, rowsToExcel, rowsToPdf } from '../services/exportService.js';

const router = express.Router();

const reportQueries = {
  'pending-list': `SELECT p.plot_number, o.owner_name, o.mobile_number, m.month, m.year, m.balance
    FROM maintenance m JOIN plots p ON p.id=m.plot_id JOIN owners o ON o.id=m.owner_id
    WHERE m.balance > 0 ORDER BY m.year DESC, m.month DESC, p.plot_number`,
  'paid-list': `SELECT p.plot_number, o.owner_name, m.month, m.year, m.paid_amount, m.receipt_number
    FROM maintenance m JOIN plots p ON p.id=m.plot_id JOIN owners o ON o.id=m.owner_id
    WHERE m.status='Paid' ORDER BY m.payment_date DESC`,
  'expense-report': `SELECT expense_date, category, vendor_name, amount, payment_mode, invoice_number FROM expenses ORDER BY expense_date DESC`,
  'income-report': `SELECT income_date, source, amount, remarks FROM income ORDER BY income_date DESC`,
  'cash-book': `SELECT payment_date AS date, 'Maintenance' AS source, paid_amount AS credit, 0 AS debit FROM maintenance WHERE paid_amount > 0
    UNION ALL SELECT income_date, source, amount, 0 FROM income
    UNION ALL SELECT expense_date, category, 0, amount FROM expenses
    ORDER BY date DESC`,
  'outstanding-aging': `SELECT p.plot_number, o.owner_name, SUM(m.balance) AS outstanding,
    SUM(CASE WHEN m.balance > 0 THEN 1 ELSE 0 END) AS pending_months
    FROM maintenance m JOIN plots p ON p.id=m.plot_id JOIN owners o ON o.id=m.owner_id
    WHERE m.balance > 0 GROUP BY p.plot_number, o.owner_name ORDER BY outstanding DESC`
};

router.get('/:type', async (req, res) => {
  const sql = reportQueries[req.params.type];
  if (!sql) return res.status(404).json({ message: 'Unknown report type' });
  const { rows } = await query(sql);
  res.json(rows);
});

router.get('/:type/export/:format', async (req, res) => {
  const sql = reportQueries[req.params.type];
  if (!sql) return res.status(404).json({ message: 'Unknown report type' });
  const { rows } = await query(sql);
  if (req.params.format === 'excel') {
    const buffer = await rowsToExcel(rows, req.params.type);
    res.setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    res.setHeader('Content-Disposition', `attachment; filename="${req.params.type}.xlsx"`);
    return res.send(buffer);
  }
  if (req.params.format === 'pdf') {
    const buffer = await rowsToPdf(rows, req.params.type);
    res.setHeader('Content-Type', 'application/pdf');
    res.setHeader('Content-Disposition', `attachment; filename="${req.params.type}.pdf"`);
    return res.send(buffer);
  }
  if (req.params.format === 'csv') {
    const csv = rowsToCsv(rows);
    res.setHeader('Content-Type', 'text/csv');
    res.setHeader('Content-Disposition', `attachment; filename="${req.params.type}.csv"`);
    return res.send(csv);
  }
  res.status(400).json({ message: 'Use excel, pdf, or csv' });
});

export default router;
