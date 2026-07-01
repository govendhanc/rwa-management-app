import express from 'express';
import { query } from '../config/db.js';
import { authorize } from '../middleware/auth.js';
import { audit } from '../middleware/audit.js';

const router = express.Router();

router.get('/', async (_req, res) => {
  const [association, settings] = await Promise.all([
    query('SELECT * FROM association LIMIT 1'),
    query('SELECT `key`, value FROM settings ORDER BY `key`')
  ]);
  res.json({ association: association.rows[0], settings: settings.rows });
});

router.put('/association', authorize('Admin'), audit('Update Association', 'association'), async (req, res) => {
  const p = req.body;
  await query(
    `UPDATE association SET name=$1, address=$2, registration_number=$3, mobile=$4, email=$5,
      bank_name=$6, bank_account_number=$7, bank_ifsc=$8, bank_branch=$9, financial_year_start_month=$10,
      monthly_maintenance_amount=$11, late_fee_amount=$12, gst_percent=$13, logo_url=$14, updated_at=NOW()
     WHERE id=$15`,
    [p.name, p.address, p.registration_number, p.mobile, p.email, p.bank_name, p.bank_account_number, p.bank_ifsc, p.bank_branch,
      p.financial_year_start_month, p.monthly_maintenance_amount, p.late_fee_amount, p.gst_percent, p.logo_url, p.id]
  );
  const { rows } = await query('SELECT * FROM association WHERE id=$1', [p.id]);
  res.json(rows[0]);
});

export default router;
