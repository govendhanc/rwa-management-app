<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Read-only aggregates for the dashboard. All amounts are returned as DECIMAL strings.
 */
class Dashboard_model extends CI_Model
{
    /**
     * @return array<string, int>
     */
    public function owner_stats(): array
    {
        $row = $this->db->query(
            "SELECT
                (SELECT COUNT(*) FROM owners WHERE is_deleted = 0) AS total_owners,
                (SELECT COUNT(*) FROM owners WHERE is_deleted = 0 AND status = 'Active') AS active_owners,
                (SELECT COUNT(*) FROM houses WHERE status = 'Active') AS total_houses,
                (SELECT COUNT(*) FROM houses WHERE status = 'Active' AND occupancy_status = 'Vacant') AS vacant_houses,
                (SELECT COUNT(*) FROM houses WHERE status = 'Active' AND occupancy_status <> 'Vacant') AS occupied_houses"
        )->row_array();
        return array_map('intval', $row);
    }

    /**
     * Maintenance figures for one billing month.
     *
     * @return array<string, mixed>
     */
    public function month_stats(int $year, int $month): array
    {
        $row = $this->db->query(
            "SELECT
                COUNT(*)                                 AS records,
                COALESCE(SUM(amount), 0)                 AS billed,
                COALESCE(SUM(waived_amount), 0)          AS waived,
                COALESCE(SUM(paid_amount), 0)            AS collected,
                COALESCE(SUM(balance_amount), 0)         AS outstanding,
                COUNT(DISTINCT CASE WHEN payment_status = 'Paid' THEN owner_id END)           AS paid_owners,
                COUNT(DISTINCT CASE WHEN payment_status = 'Partially Paid' THEN owner_id END) AS partial_owners,
                COUNT(DISTINCT CASE WHEN payment_status = 'Pending' THEN owner_id END)        AS pending_owners,
                COUNT(DISTINCT CASE WHEN payment_status = 'Waived' THEN owner_id END)         AS waived_owners
             FROM maintenance
             WHERE record_status = 'Active' AND charge_type = 'Monthly'
               AND billing_year = ? AND billing_month = ?",
            array($year, $month)
        )->row_array();

        $row['generated'] = $this->db->where(array('billing_year' => $year, 'billing_month' => $month))
            ->count_all_results('maintenance_batches') > 0;

        $due = to_paise_signed($row['billed']) - to_paise_signed($row['waived']);
        $row['collection_pct'] = $due > 0 ? round(to_paise_signed($row['collected']) * 100 / $due, 2) : 0.0;

        $row['received_in_month'] = $this->db->query(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM payments
             WHERE status = 'Active' AND payment_date BETWEEN ? AND ?",
            $this->month_range($year, $month)
        )->row()->total;

        return $row;
    }

    /**
     * All-time financial position.
     *
     * @return array<string, string>
     */
    public function financial_totals(): array
    {
        $row = $this->db->query(
            "SELECT
                (SELECT COALESCE(SUM(balance_amount), 0) FROM maintenance WHERE record_status = 'Active') AS total_outstanding,
                (SELECT COALESCE(SUM(unallocated_amount), 0) FROM payments WHERE status = 'Active') AS advance_credit,
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'Active') AS total_collection,
                (SELECT COALESCE(SUM(amount), 0) FROM other_incomes WHERE status = 'Active') AS other_income,
                (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'Active') AS total_expenses"
        )->row_array();

        $balance = to_paise_signed($row['total_collection']) + to_paise_signed($row['other_income']) - to_paise_signed($row['total_expenses']);
        $row['current_balance'] = from_paise($balance);
        return $row;
    }

    /**
     * Month-by-month series for the N months ending at ($year, $month).
     *
     * @return array<int, array<string, mixed>>
     */
    public function trend(int $year, int $month, int $months = 12): array
    {
        $end = new DateTime(sprintf('%04d-%02d-01', $year, $month));
        $start = (clone $end)->modify('-'.($months - 1).' months');
        $from = $start->format('Y-m-01');
        $to = $end->format('Y-m-t');

        $series = array();
        $cursor = clone $start;
        for ($i = 0; $i < $months; $i++)
        {
            $key = $cursor->format('Y-m');
            $series[$key] = array(
                'key'         => $key,
                'label'       => $cursor->format('M Y'),
                'collections' => '0.00',
                'expenses'    => '0.00',
                'billed'      => '0.00',
                'collected'   => '0.00',
                'outstanding' => '0.00',
                'collection_pct' => NULL,
            );
            $cursor->modify('+1 month');
        }

        $rows = $this->db->query(
            "SELECT DATE_FORMAT(payment_date, '%Y-%m') AS ym, SUM(amount) AS total
             FROM payments WHERE status = 'Active' AND payment_date BETWEEN ? AND ?
             GROUP BY ym",
            array($from, $to)
        )->result_array();
        foreach ($rows as $r)
        {
            $series[$r['ym']]['collections'] = $r['total'];
        }

        $rows = $this->db->query(
            "SELECT DATE_FORMAT(expense_date, '%Y-%m') AS ym, SUM(amount) AS total
             FROM expenses WHERE status = 'Active' AND expense_date BETWEEN ? AND ?
             GROUP BY ym",
            array($from, $to)
        )->result_array();
        foreach ($rows as $r)
        {
            $series[$r['ym']]['expenses'] = $r['total'];
        }

        $rows = $this->db->query(
            "SELECT DATE_FORMAT(period_start, '%Y-%m') AS ym,
                    SUM(amount) AS billed, SUM(paid_amount) AS collected,
                    SUM(waived_amount) AS waived, SUM(balance_amount) AS outstanding
             FROM maintenance
             WHERE record_status = 'Active' AND charge_type = 'Monthly' AND period_start BETWEEN ? AND ?
             GROUP BY ym",
            array($from, $to)
        )->result_array();
        foreach ($rows as $r)
        {
            $series[$r['ym']]['billed'] = $r['billed'];
            $series[$r['ym']]['collected'] = $r['collected'];
            $series[$r['ym']]['outstanding'] = $r['outstanding'];
            $due = to_paise_signed($r['billed']) - to_paise_signed($r['waived']);
            $series[$r['ym']]['collection_pct'] = $due > 0 ? round(to_paise_signed($r['collected']) * 100 / $due, 2) : NULL;
        }

        return array_values($series);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent_payments(int $limit = 6): array
    {
        return $this->db
            ->select('p.id, p.payment_date, p.amount, p.payment_mode, o.owner_name, o.owner_code, r.id AS receipt_id, r.receipt_no')
            ->from('payments p')
            ->join('owners o', 'o.id = p.owner_id')
            ->join('receipts r', 'r.payment_id = p.id', 'left')
            ->where('p.status', 'Active')
            ->order_by('p.payment_date', 'DESC')
            ->order_by('p.id', 'DESC')
            ->limit($limit)
            ->get()->result_array();
    }

    /**
     * Owners with the highest outstanding dues.
     *
     * @return array<int, array<string, mixed>>
     */
    public function top_outstanding(int $limit = 6): array
    {
        return $this->db->query(
            "SELECT b.owner_id, b.owner_code, b.owner_name, b.mobile, b.outstanding,
                    GROUP_CONCAT(h.plot_no ORDER BY h.plot_no SEPARATOR ', ') AS plots
             FROM v_owner_balances b
             LEFT JOIN houses h ON h.owner_id = b.owner_id
             WHERE b.outstanding > 0
             GROUP BY b.owner_id, b.owner_code, b.owner_name, b.mobile, b.outstanding
             ORDER BY b.outstanding DESC, b.owner_name
             LIMIT ".(int) $limit
        )->result_array();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function month_range(int $year, int $month): array
    {
        $first = sprintf('%04d-%02d-01', $year, $month);
        return array($first, date('Y-m-t', strtotime($first)));
    }
}
