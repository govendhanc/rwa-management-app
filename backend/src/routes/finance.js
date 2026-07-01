import express from 'express';
import { randomUUID } from 'crypto';
import { query } from '../config/db.js';
import { authorize } from '../middleware/auth.js';
import { audit } from '../middleware/audit.js';

const router = express.Router();

router.get('/expenses', async (_req, res) => {
  const { rows } = await query('SELECT * FROM expenses ORDER BY expense_date DESC LIMIT 500');
  res.json(rows);
});

router.post('/expenses', authorize('Admin', 'Treasurer'), audit('Expense Entry', 'expenses'), async (req, res) => {
  const p = req.body;
  const id = randomUUID();
  await query(
    `INSERT INTO expenses (id, expense_date, category, vendor_name, amount, payment_mode, invoice_number, bill_url, description, created_by)
     VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)`,
    [id, p.expense_date, p.category, p.vendor_name, p.amount, p.payment_mode, p.invoice_number, p.bill_url, p.description, req.user.id]
  );
  const { rows } = await query('SELECT * FROM expenses WHERE id=$1', [id]);
  res.status(201).json(rows[0]);
});

router.get('/income', async (_req, res) => {
  const { rows } = await query('SELECT * FROM income ORDER BY income_date DESC LIMIT 500');
  res.json(rows);
});

router.post('/income', authorize('Admin', 'Treasurer'), audit('Income Entry', 'income'), async (req, res) => {
  const p = req.body;
  const id = randomUUID();
  await query(
    `INSERT INTO income (id, income_date, source, amount, remarks, created_by)
     VALUES ($1,$2,$3,$4,$5,$6)`,
    [id, p.income_date, p.source, p.amount, p.remarks, req.user.id]
  );
  const { rows } = await query('SELECT * FROM income WHERE id=$1', [id]);
  res.status(201).json(rows[0]);
});

export default router;
