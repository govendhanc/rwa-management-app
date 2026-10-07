-- =====================================================================================
--  ROWA Portal
--  File    : database/04_verify.sql
--  Purpose : Financial integrity checks. Safe (read-only). Run any time, e.g. after
--            each development step or before month-end closing.
--            Every "CHECK ..." query must return issue_count = 0.
-- =====================================================================================

-- 1. maintenance.paid_amount must equal the sum of its ACTIVE allocations
SELECT 'CHECK 1: maintenance.paid_amount = SUM(active allocations)' AS check_name, COUNT(*) AS issue_count
FROM maintenance m
LEFT JOIN (
    SELECT maintenance_id, SUM(amount) AS alloc
    FROM payment_allocations WHERE status = 'Active' GROUP BY maintenance_id
) a ON a.maintenance_id = m.id
WHERE m.paid_amount <> COALESCE(a.alloc, 0);

-- 2. Active payment allocated_amount must equal the sum of its ACTIVE allocations
SELECT 'CHECK 2: payments.allocated_amount = SUM(active allocations)' AS check_name, COUNT(*) AS issue_count
FROM payments p
LEFT JOIN (
    SELECT payment_id, SUM(amount) AS alloc
    FROM payment_allocations WHERE status = 'Active' GROUP BY payment_id
) a ON a.payment_id = p.id
WHERE p.status = 'Active' AND p.allocated_amount <> COALESCE(a.alloc, 0);

-- 3. Cancelled / reversed payments must not have ACTIVE allocations
SELECT 'CHECK 3: no active allocations on cancelled payments' AS check_name, COUNT(*) AS issue_count
FROM payment_allocations pa
JOIN payments p ON p.id = pa.payment_id
WHERE p.status <> 'Active' AND pa.status = 'Active';

-- 4. payment_status must agree with the amounts
SELECT 'CHECK 4: maintenance.payment_status matches amounts' AS check_name, COUNT(*) AS issue_count
FROM maintenance
WHERE record_status = 'Active'
  AND payment_status <> CASE
        WHEN waived_amount >= amount AND amount > 0       THEN 'Waived'
        WHEN balance_amount = 0                           THEN 'Paid'
        WHEN paid_amount > 0 OR waived_amount > 0         THEN 'Partially Paid'
        ELSE 'Pending'
      END;

-- 5. Allocation owner must match the charge owner
SELECT 'CHECK 5: allocation owner = maintenance owner' AS check_name, COUNT(*) AS issue_count
FROM payment_allocations pa
JOIN payments p    ON p.id = pa.payment_id
JOIN maintenance m ON m.id = pa.maintenance_id
WHERE p.owner_id <> m.owner_id;

-- 6. Every payment has exactly one receipt; receipt status follows payment status
SELECT 'CHECK 6: one receipt per payment, status in sync' AS check_name, COUNT(*) AS issue_count
FROM payments p
LEFT JOIN receipts r ON r.payment_id = p.id
WHERE r.id IS NULL
   OR (p.status = 'Active' AND r.status <> 'Active')
   OR (p.status <> 'Active' AND r.status <> 'Cancelled')
   OR r.amount_received <> p.amount;

-- 7. waived_amount must equal the sum of ACTIVE adjustments
SELECT 'CHECK 7: maintenance.waived_amount = SUM(active adjustments)' AS check_name, COUNT(*) AS issue_count
FROM maintenance m
LEFT JOIN (
    SELECT maintenance_id, SUM(amount) AS adj
    FROM maintenance_adjustments WHERE status = 'Active' GROUP BY maintenance_id
) a ON a.maintenance_id = m.id
WHERE m.waived_amount <> COALESCE(a.adj, 0);

-- 8. Batch totals must equal their records
SELECT 'CHECK 8: batch totals = SUM(records)' AS check_name, COUNT(*) AS issue_count
FROM maintenance_batches b
LEFT JOIN (
    SELECT batch_id, COUNT(*) AS cnt, SUM(amount) AS total
    FROM maintenance WHERE record_status = 'Active' GROUP BY batch_id
) m ON m.batch_id = b.id
WHERE b.total_records <> COALESCE(m.cnt, 0) OR b.total_amount <> COALESCE(m.total, 0);

-- 9. Document sequences must be >= the highest number issued
SELECT 'CHECK 9: receipt sequence >= highest receipt number' AS check_name, COUNT(*) AS issue_count
FROM (
    SELECT CAST(SUBSTRING_INDEX(receipt_no, '-', 2) AS CHAR) AS pfx,
           CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(receipt_no, '-', 2), '-', -1) AS UNSIGNED) AS yr,
           MAX(CAST(SUBSTRING_INDEX(receipt_no, '-', -1) AS UNSIGNED)) AS max_no
    FROM receipts GROUP BY pfx, yr
) r
LEFT JOIN document_sequences s ON s.seq_type = 'RECEIPT' AND s.seq_year = r.yr
WHERE s.id IS NULL OR s.last_number < r.max_no;

-- 10. A house must not be billed twice for the same month (also enforced by a UNIQUE key)
SELECT 'CHECK 10: no duplicate maintenance per house/month' AS check_name, COUNT(*) AS issue_count
FROM (
    SELECT house_id, billing_year, billing_month, charge_type
    FROM maintenance
    GROUP BY house_id, billing_year, billing_month, charge_type
    HAVING COUNT(*) > 1
) d;

-- 11. Monthly bills carry the rate they were billed at, and the amount matches that rate
SELECT 'CHECK 11: bill amount = applied rate amount' AS check_name, COUNT(*) AS issue_count
FROM maintenance m
LEFT JOIN maintenance_rates r ON r.id = m.rate_id
WHERE m.charge_type = 'Monthly'
  AND (r.id IS NULL OR r.amount <> m.amount OR r.plot_category <> m.plot_category OR r.effective_from > m.period_start);

-- 12. The applied rate is the one in force for that billing month (no newer rate of the same category was skipped)
SELECT 'CHECK 12: bill used the rate in force for its month' AS check_name, COUNT(*) AS issue_count
FROM maintenance m
JOIN maintenance_rates r ON r.id = m.rate_id
WHERE EXISTS (
    SELECT 1 FROM maintenance_rates r2
    WHERE r2.plot_category = r.plot_category
      AND r2.effective_from > r.effective_from
      AND r2.effective_from <= m.period_start
);

-- 13. A house with an active tenant is marked Tenant Occupied
SELECT 'CHECK 13: active tenant => house Tenant Occupied' AS check_name, COUNT(*) AS issue_count
FROM tenants t
JOIN houses h ON h.id = t.house_id
WHERE t.status = 'Active' AND h.occupancy_status <> 'Tenant Occupied';

-- -------------------------------------------------------------------------------------
-- Summary figures (compare with the dashboard)
-- -------------------------------------------------------------------------------------
SELECT
    (SELECT COUNT(*) FROM owners WHERE is_deleted = 0)                                   AS total_owners,
    (SELECT COUNT(*) FROM owners WHERE is_deleted = 0 AND status = 'Active')             AS active_owners,
    (SELECT COUNT(*) FROM houses WHERE status = 'Active')                                AS total_houses,
    (SELECT COUNT(*) FROM houses WHERE status = 'Active' AND occupancy_status = 'Vacant') AS vacant_houses,
    (SELECT SUM(balance_amount) FROM maintenance WHERE record_status = 'Active')         AS total_outstanding,
    (SELECT SUM(unallocated_amount) FROM payments WHERE status = 'Active')               AS total_advance_credit,
    (SELECT SUM(amount) FROM payments WHERE status = 'Active')                           AS total_collection,
    (SELECT SUM(amount) FROM other_incomes WHERE status = 'Active')                      AS other_income,
    (SELECT SUM(amount) FROM expenses WHERE status = 'Active')                           AS total_expenses,
    (SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'Active')
      + (SELECT COALESCE(SUM(amount),0) FROM other_incomes WHERE status = 'Active')
      - (SELECT COALESCE(SUM(amount),0) FROM expenses WHERE status = 'Active')           AS current_balance;

SELECT * FROM v_monthly_collection ORDER BY billing_year, billing_month;

SELECT owner_code, owner_name, total_due, total_paid, total_waived, outstanding, advance_credit, net_payable, last_payment_date
FROM v_owner_balances ORDER BY owner_id;
