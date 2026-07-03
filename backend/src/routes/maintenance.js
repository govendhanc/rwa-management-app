import express from 'express';
import { randomUUID } from 'crypto';
import { query } from '../config/db.js';
import { authorize } from '../middleware/auth.js';
import { audit } from '../middleware/audit.js';

const router = express.Router();
const FINANCE_ROLES = ['Admin', 'Manager', 'Treasurer'];

function statusFor(total, paid) {
  if (Number(paid) <= 0) return 'Unpaid';
  if (Number(paid) >= Number(total)) return 'Paid';
  return 'Partially Paid';
}

function paymentPayload(p) {
  const previousDue = Number(p.previous_due || 0);
  const lateFee = Number(p.late_fee || 0);
  const discount = Number(p.discount || 0);
  const monthlyAmount = Number(p.monthly_amount ?? p.amount ?? 0);
  const paidAmount = Number(p.paid_amount ?? p.amount ?? 0);
  const date = p.payment_date || p.date;
  const parsedDate = date ? new Date(date) : new Date();
  const month = Number(p.month || parsedDate.getMonth() + 1);
  const year = Number(p.year || parsedDate.getFullYear());
  const totalAmount = monthlyAmount + previousDue + lateFee - discount;
  const balance = Math.max(totalAmount - paidAmount, 0);
  return {
    ...p,
    month,
    year,
    monthlyAmount,
    previousDue,
    lateFee,
    discount,
    paidAmount,
    totalAmount,
    balance,
    paymentDate: date,
    paymentMode: p.payment_mode,
    transactionNumber: p.transaction_number || p.reference_number || '',
    status: statusFor(totalAmount, paidAmount)
  };
}

function validatePayment(p) {
  const required = ['plot_id', 'owner_id', 'paymentDate', 'paidAmount', 'paymentMode'];
  const missing = required.filter((field) => p[field] === undefined || p[field] === null || p[field] === '');
  if (missing.length) {
    const error = new Error(`Missing mandatory fields: ${missing.join(', ')}`);
    error.status = 400;
    throw error;
  }
  if (Number(p.paidAmount) < 0) {
    const error = new Error('Amount cannot be negative');
    error.status = 400;
    throw error;
  }
}

async function nextReceiptNumber(year) {
  const prefixResult = await query("SELECT JSON_UNQUOTE(value) AS prefix FROM settings WHERE `key`='receipt_prefix'");
  const prefix = prefixResult.rows[0]?.prefix || `RWA-${year}`;
  for (let attempt = 0; attempt < 5; attempt += 1) {
    const sequence = Date.now().toString().slice(-6) + (attempt || '');
    const receiptNumber = `${prefix}-${sequence}`;
    const existing = await query('SELECT id FROM maintenance WHERE receipt_number=$1', [receiptNumber]);
    if (!existing.rows.length) return receiptNumber;
  }
  return `${prefix}-${randomUUID().slice(0, 8).toUpperCase()}`;
}

async function writeAudit(req, action, originalRecord, changes = null) {
  await query(
    `INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, metadata)
     VALUES ($1, $2, 'maintenance', $3, $4, $5)`,
    [req.user.id, action, originalRecord.id, req.ip, JSON.stringify({
      deletedBy: action.includes('Delete') ? req.user.name : undefined,
      deletedDateTime: action.includes('Delete') ? new Date().toISOString() : undefined,
      originalRecord,
      changes
    })]
  );
}

router.get('/', async (req, res) => {
  const { month, year, status } = req.query;
  const params = [];
  const filters = ['m.deleted_at IS NULL'];
  if (month) { params.push(month); filters.push(`m.month = $${params.length}`); }
  if (year) { params.push(year); filters.push(`m.year = $${params.length}`); }
  if (status) { params.push(status); filters.push(`m.status = $${params.length}`); }
  const where = filters.length ? `WHERE ${filters.join(' AND ')}` : '';
  const { rows } = await query(
    `SELECT m.*, p.plot_number, o.owner_name, o.mobile_number
     FROM maintenance m
     JOIN plots p ON p.id = m.plot_id
     JOIN owners o ON o.id = m.owner_id
     ${where}
     ORDER BY m.year DESC, m.month DESC, p.plot_number`,
    params
  );
  res.json(rows);
});

router.post('/', authorize(...FINANCE_ROLES), audit('Payment Entry', 'maintenance'), async (req, res) => {
  const p = paymentPayload(req.body);
  validatePayment(p);
  const receiptNumber = p.receipt_number || await nextReceiptNumber(p.year);
  const duplicate = await query('SELECT id FROM maintenance WHERE receipt_number=$1', [receiptNumber]);
  if (duplicate.rows.length) return res.status(409).json({ message: 'Receipt number already exists' });
  const id = randomUUID();

  await query(
    `INSERT INTO maintenance (
      id, plot_id, owner_id, month, year, monthly_amount, previous_due, late_fee, discount,
      total_amount, paid_amount, balance, payment_date, payment_mode, transaction_number,
      receipt_number, status, remarks, created_by
    ) VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,$15,$16,$17,$18,$19)
    ON DUPLICATE KEY UPDATE
      monthly_amount = VALUES(monthly_amount),
      previous_due = VALUES(previous_due),
      late_fee = VALUES(late_fee),
      discount = VALUES(discount),
      total_amount = VALUES(total_amount),
      paid_amount = VALUES(paid_amount),
      balance = VALUES(balance),
      payment_date = VALUES(payment_date),
      payment_mode = VALUES(payment_mode),
      transaction_number = VALUES(transaction_number),
      receipt_number = COALESCE(receipt_number, VALUES(receipt_number)),
      status = VALUES(status),
      remarks = VALUES(remarks),
      updated_by = VALUES(created_by),
      deleted_at = NULL,
      deleted_by = NULL,
      updated_at = NOW()
    `,
    [id, p.plot_id, p.owner_id, p.month, p.year, p.monthlyAmount, p.previousDue, p.lateFee, p.discount, p.totalAmount,
      p.paidAmount, p.balance, p.paymentDate, p.paymentMode, p.transactionNumber, receiptNumber, p.status, p.remarks, req.user.id]
  );
  const { rows } = await query(
    'SELECT * FROM maintenance WHERE plot_id=$1 AND month=$2 AND year=$3',
    [p.plot_id, p.month, p.year]
  );
  await query(
    `INSERT IGNORE INTO receipts (maintenance_id, receipt_number, receipt_date, qr_payload, upi_qr_payload)
     VALUES ($1, $2, COALESCE($3, CURRENT_DATE), $4, $5)
    `,
    [rows[0].id, receiptNumber, p.paymentDate, `Receipt ${receiptNumber}`, p.upi_qr_payload || null]
  );
  res.status(201).json(rows[0]);
});

router.put('/:id', authorize(...FINANCE_ROLES), async (req, res) => {
  const current = await query('SELECT * FROM maintenance WHERE id=$1 AND deleted_at IS NULL', [req.params.id]);
  const original = current.rows[0];
  if (!original) return res.status(404).json({ message: 'Maintenance record not found' });

  const p = paymentPayload({ ...original, ...req.body });
  validatePayment(p);
  if (p.receipt_number && p.receipt_number !== original.receipt_number) {
    const duplicate = await query('SELECT id FROM maintenance WHERE receipt_number=$1 AND id<>$2', [p.receipt_number, req.params.id]);
    if (duplicate.rows.length) return res.status(409).json({ message: 'Receipt number already exists' });
  }

  await query(
    `UPDATE maintenance SET plot_id=$1, owner_id=$2, month=$3, year=$4, monthly_amount=$5,
      previous_due=$6, late_fee=$7, discount=$8, total_amount=$9, paid_amount=$10,
      balance=$11, payment_date=$12, payment_mode=$13, transaction_number=$14,
      receipt_number=$15, status=$16, remarks=$17, updated_by=$18, updated_at=NOW()
     WHERE id=$19`,
    [p.plot_id, p.owner_id, p.month, p.year, p.monthlyAmount, p.previousDue, p.lateFee, p.discount,
      p.totalAmount, p.paidAmount, p.balance, p.paymentDate, p.paymentMode, p.transactionNumber,
      p.receipt_number || original.receipt_number, p.status, p.remarks, req.user.id, req.params.id]
  );
  await query(
    `UPDATE receipts SET receipt_number=$1, receipt_date=COALESCE($2, receipt_date), qr_payload=$3
     WHERE maintenance_id=$4`,
    [p.receipt_number || original.receipt_number, p.paymentDate, `Receipt ${p.receipt_number || original.receipt_number}`, req.params.id]
  );
  await writeAudit(req, 'Edit Maintenance', original, req.body);
  const { rows } = await query('SELECT * FROM maintenance WHERE id=$1', [req.params.id]);
  res.json(rows[0]);
});

router.delete('/:id', authorize(...FINANCE_ROLES), async (req, res) => {
  const current = await query('SELECT * FROM maintenance WHERE id=$1 AND deleted_at IS NULL', [req.params.id]);
  const original = current.rows[0];
  if (!original) return res.status(404).json({ message: 'Maintenance record not found' });
  await query('UPDATE maintenance SET deleted_at=NOW(), deleted_by=$1, updated_by=$1 WHERE id=$2', [req.user.id, req.params.id]);
  await writeAudit(req, 'Delete Maintenance', original);
  res.status(204).send();
});

router.post('/generate-month', authorize(...FINANCE_ROLES), audit('Generate Month', 'maintenance'), async (req, res) => {
  const { month, year } = req.body;
  const assoc = await query('SELECT monthly_maintenance_amount, late_fee_amount FROM association LIMIT 1');
  const amount = Number(assoc.rows[0]?.monthly_maintenance_amount || 0);
  const result = await query(
    `INSERT IGNORE INTO maintenance (id, plot_id, owner_id, month, year, monthly_amount, previous_due, late_fee, total_amount, balance, status)
     SELECT UUID(), p.id, o.id, $1, $2, $3,
       COALESCE((SELECT balance FROM maintenance pm WHERE pm.plot_id = p.id AND pm.deleted_at IS NULL ORDER BY year DESC, month DESC LIMIT 1), 0),
       0,
       $3 + COALESCE((SELECT balance FROM maintenance pm WHERE pm.plot_id = p.id AND pm.deleted_at IS NULL ORDER BY year DESC, month DESC LIMIT 1), 0),
       $3 + COALESCE((SELECT balance FROM maintenance pm WHERE pm.plot_id = p.id AND pm.deleted_at IS NULL ORDER BY year DESC, month DESC LIMIT 1), 0),
       'Unpaid'
     FROM plots p JOIN owners o ON o.id = p.owner_id`,
    [month, year, amount]
  );
  res.json({ generated: result.rowCount });
});

export default router;
