import express from 'express';
import { authorize } from '../middleware/auth.js';
import { audit } from '../middleware/audit.js';
import { query } from '../config/db.js';

const router = express.Router();

function buildReminder(row) {
  return `Dear Owner,\n\nYour maintenance fee for ${row.month_name} ${row.year} is pending.\n\nAmount: ₹${row.balance}\n\nPlease pay at the earliest.\n\nRegards,\nAssociation Treasurer`;
}

router.post('/reminders', authorize('Admin', 'Manager', 'Treasurer'), audit('Send Reminder', 'notifications'), async (req, res) => {
  const { month, year, channel = 'WhatsApp' } = req.body;
  const { rows } = await query(
    `SELECT p.plot_number, o.owner_name, o.mobile_number, o.email, m.balance,
            MONTHNAME(STR_TO_DATE(m.month, '%m')) AS month_name, m.year
     FROM maintenance m
     JOIN plots p ON p.id = m.plot_id
     JOIN owners o ON o.id = m.owner_id
     WHERE m.deleted_at IS NULL AND m.month = $1 AND m.year = $2 AND m.balance > 0`,
    [month, year]
  );
  const reminders = rows.map((row) => ({
    plot_number: row.plot_number,
    owner_name: row.owner_name,
    channel,
    to: channel === 'Email' ? row.email : row.mobile_number,
    message: buildReminder(row)
  }));

  res.json({
    queued: reminders.length,
    providerStatus: 'Provider integration placeholder',
    reminders
  });
});

export default router;
