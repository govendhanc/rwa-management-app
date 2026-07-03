import express from 'express';
import { query } from '../config/db.js';
import { createReceiptPdf } from '../services/receiptService.js';

const router = express.Router();

router.get('/:receiptNumber/pdf', async (req, res) => {
  const receiptResult = await query('SELECT * FROM receipts WHERE receipt_number = $1', [req.params.receiptNumber]);
  const receipt = receiptResult.rows[0];
  if (!receipt) return res.status(404).json({ message: 'Receipt not found' });

  const paymentResult = await query(
    `SELECT m.*, p.plot_number, o.owner_name
     FROM maintenance m
     JOIN plots p ON p.id = m.plot_id
     JOIN owners o ON o.id = m.owner_id
     WHERE m.id = $1`,
    [receipt.maintenance_id]
  );
  const associationResult = await query('SELECT * FROM association LIMIT 1');
  const buffer = await createReceiptPdf({
    association: associationResult.rows[0],
    receipt,
    payment: paymentResult.rows[0]
  });
  await query('UPDATE receipts SET printed_at=NOW() WHERE id=$1', [receipt.id]);
  res.setHeader('Content-Type', 'application/pdf');
  res.setHeader('Content-Disposition', `inline; filename="${receipt.receipt_number}.pdf"`);
  res.send(buffer);
});

export default router;
