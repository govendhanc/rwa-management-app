import express from 'express';
import { query } from '../config/db.js';

const router = express.Router();

router.get('/', async (_req, res) => {
  const [totals, trend, expenses, paidPending, incomeExpense] = await Promise.all([
    query(`SELECT
      (SELECT COUNT(*) FROM plots) AS total_plots,
      (SELECT COUNT(*) FROM owners) AS total_owners,
      COALESCE((SELECT SUM(paid_amount) FROM maintenance WHERE deleted_at IS NULL AND month = MONTH(CURRENT_DATE) AND year = YEAR(CURRENT_DATE)), 0) AS paid_this_month,
      COALESCE((SELECT SUM(balance) FROM maintenance WHERE deleted_at IS NULL AND month = MONTH(CURRENT_DATE) AND year = YEAR(CURRENT_DATE)), 0) AS pending_this_month,
      COALESCE((SELECT SUM(paid_amount) FROM maintenance WHERE deleted_at IS NULL), 0) + COALESCE((SELECT SUM(amount) FROM income WHERE deleted_at IS NULL), 0) AS total_collection,
      COALESCE((SELECT SUM(amount) FROM expenses WHERE deleted_at IS NULL), 0) AS total_expenses,
      COALESCE((SELECT SUM(balance) FROM maintenance WHERE deleted_at IS NULL), 0) AS total_outstanding`),
    query(`SELECT year, month, SUM(paid_amount) AS collection
      FROM maintenance WHERE deleted_at IS NULL GROUP BY year, month ORDER BY year, month LIMIT 12`),
    query(`SELECT category, SUM(amount) AS amount FROM expenses WHERE deleted_at IS NULL GROUP BY category ORDER BY amount DESC`),
    query(`SELECT status, COUNT(*) AS count FROM maintenance WHERE deleted_at IS NULL GROUP BY status`),
    query(`SELECT label, SUM(amount) AS amount FROM (
      SELECT 'Income' AS label, amount FROM income WHERE deleted_at IS NULL
      UNION ALL
      SELECT 'Collection' AS label, paid_amount AS amount FROM maintenance WHERE deleted_at IS NULL
      UNION ALL
      SELECT 'Expense' AS label, amount * -1 AS amount FROM expenses WHERE deleted_at IS NULL
    ) t GROUP BY label`)
  ]);

  const total = totals.rows[0];
  const availableBalance = Number(total.total_collection) - Number(total.total_expenses);
  res.json({
    cards: { ...total, available_balance: availableBalance },
    charts: {
      monthlyCollectionTrend: trend.rows,
      expenseByCategory: expenses.rows,
      paidVsPending: paidPending.rows,
      incomeVsExpense: incomeExpense.rows
    }
  });
});

export default router;
