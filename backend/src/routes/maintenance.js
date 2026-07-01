import express from 'express';
import { randomUUID } from 'crypto';
import { query } from '../config/db.js';
import { authorize } from '../middleware/auth.js';
import { audit } from '../middleware/audit.js';

const router = express.Router();

function statusFor(total, paid) {
  if (Number(paid) <= 0) return 'Unpaid';
  if (Number(paid) >= Number(total)) return 'Paid';
  return 'Partially Paid';
}

router.get('/', async (req, res) => {
  const { month, year, status } = req.query;
  const params = [];
  const filters = [];
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

router.post('/', authorize('Admin', 'Treasurer'), audit('Payment Entry', 'maintenance'), async (req, res) => {
  const p = req.body;
  const previousDue = Number(p.previous_due || 0);
  const lateFee = Number(p.late_fee || 0);
  const discount = Number(p.discount || 0);
  const monthlyAmount = Number(p.monthly_amount || 0);
  const paidAmount = Number(p.paid_amount || 0);
  const totalAmount = monthlyAmount + previousDue + lateFee - discount;
  const balance = Math.max(totalAmount - paidAmount, 0);
  const status = statusFor(totalAmount, paidAmount);
  const receiptNumber = p.receipt_number || `RWA-${p.year}-${Date.now().toString().slice(-6)}`;
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
      updated_at = NOW()
    `,
    [id, p.plot_id, p.owner_id, p.month, p.year, monthlyAmount, previousDue, lateFee, discount, totalAmount,
      paidAmount, balance, p.payment_date, p.payment_mode, p.transaction_number, receiptNumber, status, p.remarks, req.user.id]
  );
  const { rows } = await query(
    'SELECT * FROM maintenance WHERE plot_id=$1 AND month=$2 AND year=$3',
    [p.plot_id, p.month, p.year]
  );
  await query(
    `INSERT IGNORE INTO receipts (maintenance_id, receipt_number, receipt_date, qr_payload, upi_qr_payload)
     VALUES ($1, $2, CURRENT_DATE, $3, $4)
    `,
    [rows[0].id, receiptNumber, `Receipt ${receiptNumber}`, p.upi_qr_payload || null]
  );
  res.status(201).json(rows[0]);
});

router.post('/generate-month', authorize('Admin', 'Treasurer'), audit('Generate Month', 'maintenance'), async (req, res) => {
  const { month, year } = req.body;
  const assoc = await query('SELECT monthly_maintenance_amount, late_fee_amount FROM association LIMIT 1');
  const amount = Number(assoc.rows[0]?.monthly_maintenance_amount || 0);
  const result = await query(
    `INSERT IGNORE INTO maintenance (id, plot_id, owner_id, month, year, monthly_amount, previous_due, late_fee, total_amount, balance, status)
     SELECT UUID(), p.id, o.id, $1, $2, $3,
       COALESCE((SELECT balance FROM maintenance pm WHERE pm.plot_id = p.id ORDER BY year DESC, month DESC LIMIT 1), 0),
       0,
       $3 + COALESCE((SELECT balance FROM maintenance pm WHERE pm.plot_id = p.id ORDER BY year DESC, month DESC LIMIT 1), 0),
       $3 + COALESCE((SELECT balance FROM maintenance pm WHERE pm.plot_id = p.id ORDER BY year DESC, month DESC LIMIT 1), 0),
       'Unpaid'
     FROM plots p JOIN owners o ON o.plot_id = p.id`,
    [month, year, amount]
  );
  res.json({ generated: result.rowCount });
});

export default router;
