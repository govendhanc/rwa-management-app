import express from 'express';
import { randomUUID } from 'crypto';
import { query } from '../config/db.js';
import { authorize } from '../middleware/auth.js';
import { audit } from '../middleware/audit.js';

const router = express.Router();
const FINANCE_ROLES = ['Admin', 'Manager', 'Treasurer'];

function requireFields(payload, fields) {
  const missing = fields.filter((field) => payload[field] === undefined || payload[field] === null || payload[field] === '');
  if (missing.length) {
    const error = new Error(`Missing mandatory fields: ${missing.join(', ')}`);
    error.status = 400;
    throw error;
  }
}

async function writeAudit(req, action, entityType, originalRecord, changes = null) {
  await query(
    `INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, metadata)
     VALUES ($1, $2, $3, $4, $5, $6)`,
    [req.user.id, action, entityType, originalRecord.id, req.ip, JSON.stringify({
      deletedBy: action.includes('Delete') ? req.user.name : undefined,
      deletedDateTime: action.includes('Delete') ? new Date().toISOString() : undefined,
      originalRecord,
      changes
    })]
  );
}

router.get('/expenses', async (_req, res) => {
  const { rows } = await query('SELECT * FROM expenses WHERE deleted_at IS NULL ORDER BY expense_date DESC LIMIT 500');
  res.json(rows);
});

router.post('/expenses', authorize(...FINANCE_ROLES), audit('Expense Entry', 'expenses'), async (req, res) => {
  const p = req.body;
  requireFields(p, ['expense_date', 'category', 'amount', 'payment_mode']);
  if (Number(p.amount) < 0) return res.status(400).json({ message: 'Amount cannot be negative' });
  const id = randomUUID();
  await query(
    `INSERT INTO expenses (id, expense_date, category, vendor_name, amount, payment_mode, invoice_number, bill_url, description, created_by)
     VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10)`,
    [id, p.expense_date, p.category, p.vendor_name, p.amount, p.payment_mode, p.invoice_number, p.bill_url, p.description, req.user.id]
  );
  const { rows } = await query('SELECT * FROM expenses WHERE id=$1', [id]);
  res.status(201).json(rows[0]);
});

router.put('/expenses/:id', authorize(...FINANCE_ROLES), async (req, res) => {
  const current = await query('SELECT * FROM expenses WHERE id=$1 AND deleted_at IS NULL', [req.params.id]);
  const original = current.rows[0];
  if (!original) return res.status(404).json({ message: 'Expense not found' });
  const p = { ...original, ...req.body };
  requireFields(p, ['expense_date', 'category', 'amount', 'payment_mode']);
  if (Number(p.amount) < 0) return res.status(400).json({ message: 'Amount cannot be negative' });
  await query(
    `UPDATE expenses SET expense_date=$1, category=$2, vendor_name=$3, amount=$4,
      payment_mode=$5, invoice_number=$6, bill_url=$7, description=$8,
      updated_by=$9, updated_at=NOW()
     WHERE id=$10`,
    [p.expense_date, p.category, p.vendor_name, p.amount, p.payment_mode, p.invoice_number,
      p.bill_url, p.description, req.user.id, req.params.id]
  );
  await writeAudit(req, 'Edit Expense', 'expenses', original, req.body);
  const { rows } = await query('SELECT * FROM expenses WHERE id=$1', [req.params.id]);
  res.json(rows[0]);
});

router.delete('/expenses/:id', authorize(...FINANCE_ROLES), async (req, res) => {
  const current = await query('SELECT * FROM expenses WHERE id=$1 AND deleted_at IS NULL', [req.params.id]);
  const original = current.rows[0];
  if (!original) return res.status(404).json({ message: 'Expense not found' });
  await query('UPDATE expenses SET deleted_at=NOW(), deleted_by=$1, updated_by=$1 WHERE id=$2', [req.user.id, req.params.id]);
  await writeAudit(req, 'Delete Expense', 'expenses', original);
  res.status(204).send();
});

router.get('/income', async (_req, res) => {
  const { rows } = await query('SELECT * FROM income WHERE deleted_at IS NULL ORDER BY income_date DESC LIMIT 500');
  res.json(rows);
});

router.post('/income', authorize(...FINANCE_ROLES), audit('Income Entry', 'income'), async (req, res) => {
  const p = req.body;
  requireFields(p, ['income_date', 'source', 'amount']);
  if (Number(p.amount) < 0) return res.status(400).json({ message: 'Amount cannot be negative' });
  const id = randomUUID();
  await query(
    `INSERT INTO income (id, income_date, source, amount, remarks, created_by)
     VALUES ($1,$2,$3,$4,$5,$6)`,
    [id, p.income_date, p.source, p.amount, p.remarks, req.user.id]
  );
  const { rows } = await query('SELECT * FROM income WHERE id=$1', [id]);
  res.status(201).json(rows[0]);
});

router.put('/income/:id', authorize(...FINANCE_ROLES), async (req, res) => {
  const current = await query('SELECT * FROM income WHERE id=$1 AND deleted_at IS NULL', [req.params.id]);
  const original = current.rows[0];
  if (!original) return res.status(404).json({ message: 'Income not found' });
  const p = { ...original, ...req.body };
  requireFields(p, ['income_date', 'source', 'amount']);
  if (Number(p.amount) < 0) return res.status(400).json({ message: 'Amount cannot be negative' });
  await query(
    `UPDATE income SET income_date=$1, source=$2, amount=$3, remarks=$4,
      updated_by=$5, updated_at=NOW()
     WHERE id=$6`,
    [p.income_date, p.source, p.amount, p.remarks, req.user.id, req.params.id]
  );
  await writeAudit(req, 'Edit Income', 'income', original, req.body);
  const { rows } = await query('SELECT * FROM income WHERE id=$1', [req.params.id]);
  res.json(rows[0]);
});

router.delete('/income/:id', authorize(...FINANCE_ROLES), async (req, res) => {
  const current = await query('SELECT * FROM income WHERE id=$1 AND deleted_at IS NULL', [req.params.id]);
  const original = current.rows[0];
  if (!original) return res.status(404).json({ message: 'Income not found' });
  await query('UPDATE income SET deleted_at=NOW(), deleted_by=$1, updated_by=$1 WHERE id=$2', [req.user.id, req.params.id]);
  await writeAudit(req, 'Delete Income', 'income', original);
  res.status(204).send();
});

export default router;
