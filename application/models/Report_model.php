<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Report queries (read-only). Amounts are DECIMAL strings; arithmetic is done in paise.
 */
class Report_model extends CI_Model
{
    /**
     * Owner-wise outstanding for bills up to (and including) the month of $as_of.
     *
     * @param array{as_of: string, owner_id?: int, plot?: string, owner_status?: string, only_due?: bool} $f
     * @return array<int, array<string, mixed>>
     */
    public function outstanding(array $f): array
    {
        $this->load->model('rate_model');
        $category = $this->rate_model->category_sql('built_status');
        $period_end = date('Y-m-01', strtotime($f['as_of']));

        $sql = "SELECT o.id AS owner_id, o.owner_code, o.owner_name, o.mobile, o.status,
                       hh.plots, hh.house_nos,
                       COALESCE(hh.constructed_plots, 0) AS constructed_plots, COALESCE(hh.vacant_plots, 0) AS vacant_plots,
                       COALESCE(m.billed, 0) AS total_due, COALESCE(m.waived, 0) AS total_waived,
                       COALESCE(m.paid, 0) AS total_paid, COALESCE(m.outstanding, 0) AS outstanding,
                       COALESCE(m.open_bills, 0) AS open_bills, m.oldest_due,
                       lp.last_payment_date, COALESCE(cr.credit, 0) AS advance_credit
                FROM owners o
                LEFT JOIN (SELECT owner_id,
                                  GROUP_CONCAT(plot_no ORDER BY CAST(plot_no AS UNSIGNED), plot_no SEPARATOR ', ') AS plots,
                                  GROUP_CONCAT(COALESCE(NULLIF(house_no, ''), '-') ORDER BY CAST(plot_no AS UNSIGNED), plot_no SEPARATOR ', ') AS house_nos,
                                  SUM(status = 'Active' AND ($category) = 'Constructed') AS constructed_plots,
                                  SUM(status = 'Active' AND ($category) = 'Vacant Plot') AS vacant_plots
                           FROM houses WHERE owner_id IS NOT NULL GROUP BY owner_id) hh ON hh.owner_id = o.id
                LEFT JOIN (SELECT owner_id, SUM(amount) AS billed, SUM(waived_amount) AS waived, SUM(paid_amount) AS paid,
                                  SUM(balance_amount) AS outstanding, SUM(balance_amount > 0) AS open_bills,
                                  MIN(CASE WHEN balance_amount > 0 THEN period_start END) AS oldest_due
                           FROM maintenance WHERE record_status = 'Active' AND period_start <= ? GROUP BY owner_id) m ON m.owner_id = o.id
                LEFT JOIN (SELECT owner_id, MAX(payment_date) AS last_payment_date FROM payments WHERE status = 'Active' GROUP BY owner_id) lp ON lp.owner_id = o.id
                LEFT JOIN (SELECT owner_id, SUM(unallocated_amount) AS credit FROM payments WHERE status = 'Active' GROUP BY owner_id) cr ON cr.owner_id = o.id
                WHERE o.is_deleted = 0";
        $params = array($period_end);

        if ( ! empty($f['owner_id']))
        {
            $sql .= ' AND o.id = ?';
            $params[] = (int) $f['owner_id'];
        }
        if (isset($f['plot']) && $f['plot'] !== '')
        {
            $sql .= ' AND EXISTS (SELECT 1 FROM houses h2 WHERE h2.owner_id = o.id AND (h2.plot_no = ? OR h2.house_no = ?))';
            $params[] = $f['plot'];
            $params[] = $f['plot'];
        }
        if ( ! empty($f['owner_status']))
        {
            $sql .= ' AND o.status = ?';
            $params[] = $f['owner_status'];
        }
        if ( ! empty($f['only_due']))
        {
            $sql .= ' AND COALESCE(m.outstanding, 0) > 0';
        }
        $sql .= ' ORDER BY COALESCE(m.outstanding, 0) DESC, CAST(SUBSTRING_INDEX(COALESCE(hh.plots, \'999999\'), \',\', 1) AS UNSIGNED), o.owner_name';
        return $this->db->query($sql, $params)->result_array();
    }

    /**
     * Month-wise collection for a year (monthly bills only).
     *
     * @return array<int, array<string, mixed>>
     */
    public function collection(int $year): array
    {
        return $this->db->query(
            'SELECT * FROM v_monthly_collection WHERE billing_year = ? ORDER BY billing_month',
            array($year)
        )->result_array();
    }

    /**
     * @return int[] Years that have generated maintenance (newest first), always including this year.
     */
    public function billing_years(): array
    {
        $years = array_map('intval', array_column($this->db->query('SELECT DISTINCT billing_year FROM maintenance_batches ORDER BY billing_year DESC')->result_array(), 'billing_year'));
        if ( ! in_array((int) date('Y'), $years, TRUE))
        {
            array_unshift($years, (int) date('Y'));
        }
        rsort($years);
        return $years;
    }

    /**
     * Owner statement (ledger) between two dates.
     * Debit = maintenance charges (dated on the first of the billing month).
     * Credit = payments (payment date) and waivers/discounts (adjustment date).
     * Cancelled payments, cancelled bills and reversed adjustments are excluded.
     * A negative balance is advance credit.
     *
     * @return array{opening: string, entries: array<int, array<string, mixed>>, total_charges: string, total_payments: string,
     *               total_adjustments: string, closing: string}
     */
    public function statement(int $owner_id, string $from, string $to): array
    {
        $before = function (string $sql) use ($owner_id, $from): int {
            return to_paise_signed($this->db->query($sql, array($owner_id, $from))->row()->total);
        };
        $opening = $before("SELECT COALESCE(SUM(amount), 0) AS total FROM maintenance WHERE owner_id = ? AND record_status = 'Active' AND period_start < ?")
            - $before("SELECT COALESCE(SUM(a.amount), 0) AS total FROM maintenance_adjustments a JOIN maintenance m ON m.id = a.maintenance_id
                       WHERE m.owner_id = ? AND m.record_status = 'Active' AND a.status = 'Active' AND a.adjustment_date < ?")
            - $before("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE owner_id = ? AND status = 'Active' AND payment_date < ?");

        $entries = array();
        foreach ($this->db->query(
            "SELECT m.id, m.period_start AS entry_date, m.billing_year, m.billing_month, m.amount, m.charge_type, h.plot_no, h.house_no
             FROM maintenance m JOIN houses h ON h.id = m.house_id
             WHERE m.owner_id = ? AND m.record_status = 'Active' AND m.period_start BETWEEN ? AND ?",
            array($owner_id, $from, $to))->result_array() as $r)
        {
            $entries[] = array('date' => $r['entry_date'], 'order' => 1, 'type' => 'Charge', 'ref' => 'Bill #'.$r['id'],
                'description' => ($r['charge_type'] === 'Monthly' ? 'Maintenance ' : $r['charge_type'].' ').period_label((int) $r['billing_year'], (int) $r['billing_month']).' - Plot '.$r['plot_no'],
                'debit' => to_paise_signed($r['amount']), 'credit' => 0);
        }
        foreach ($this->db->query(
            "SELECT a.id, a.adjustment_date, a.adjustment_type, a.amount, a.reason, m.billing_year, m.billing_month, h.plot_no
             FROM maintenance_adjustments a JOIN maintenance m ON m.id = a.maintenance_id JOIN houses h ON h.id = m.house_id
             WHERE m.owner_id = ? AND m.record_status = 'Active' AND a.status = 'Active' AND a.adjustment_date BETWEEN ? AND ?",
            array($owner_id, $from, $to))->result_array() as $r)
        {
            $entries[] = array('date' => $r['adjustment_date'], 'order' => 3, 'type' => 'Adjustment', 'ref' => $r['adjustment_type'],
                'description' => $r['adjustment_type'].' - '.period_label((int) $r['billing_year'], (int) $r['billing_month']).', Plot '.$r['plot_no'].' ('.$r['reason'].')',
                'debit' => 0, 'credit' => to_paise_signed($r['amount']));
        }
        foreach ($this->db->query(
            "SELECT p.id, p.payment_date, p.amount, p.payment_mode, p.transaction_ref, p.unallocated_amount, r.receipt_no, r.period_label
             FROM payments p LEFT JOIN receipts r ON r.payment_id = p.id
             WHERE p.owner_id = ? AND p.status = 'Active' AND p.payment_date BETWEEN ? AND ?",
            array($owner_id, $from, $to))->result_array() as $r)
        {
            $entries[] = array('date' => $r['payment_date'], 'order' => 2, 'type' => 'Payment', 'ref' => (string) $r['receipt_no'],
                'description' => 'Payment by '.$r['payment_mode'].($r['transaction_ref'] ? ' ('.$r['transaction_ref'].')' : '').($r['period_label'] ? ' - '.$r['period_label'] : ''),
                'debit' => 0, 'credit' => to_paise_signed($r['amount']));
        }

        usort($entries, function ($a, $b) {
            return array($a['date'], $a['order']) <=> array($b['date'], $b['order']);
        });

        $balance = $opening;
        $charges = $payments = $adjustments = 0;
        foreach ($entries as &$e)
        {
            $balance += $e['debit'] - $e['credit'];
            $e['balance'] = from_paise($balance);
            if ($e['type'] === 'Charge') { $charges += $e['debit']; }
            if ($e['type'] === 'Payment') { $payments += $e['credit']; }
            if ($e['type'] === 'Adjustment') { $adjustments += $e['credit']; }
            $e['debit'] = $e['debit'] > 0 ? from_paise($e['debit']) : '';
            $e['credit'] = $e['credit'] > 0 ? from_paise($e['credit']) : '';
        }
        unset($e);

        return array(
            'opening'           => from_paise($opening),
            'entries'           => $entries,
            'total_charges'     => from_paise($charges),
            'total_payments'    => from_paise($payments),
            'total_adjustments' => from_paise($adjustments),
            'closing'           => from_paise($balance),
        );
    }

    /**
     * Income & expense summary for a date range, with the cash position before it.
     *
     * @return array<string, mixed>
     */
    public function income_expense(string $from, string $to): array
    {
        $sum = function (string $sql, array $params): int {
            return to_paise_signed($this->db->query($sql, $params)->row()->total);
        };

        $collection = $sum("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE status = 'Active' AND payment_date BETWEEN ? AND ?", array($from, $to));
        $by_mode = $this->db->query(
            "SELECT payment_mode AS label, SUM(amount) AS total, COUNT(*) AS cnt FROM payments
             WHERE status = 'Active' AND payment_date BETWEEN ? AND ? GROUP BY payment_mode ORDER BY total DESC",
            array($from, $to))->result_array();
        $other = $this->db->query(
            "SELECT source AS label, SUM(amount) AS total, COUNT(*) AS cnt FROM other_incomes
             WHERE status = 'Active' AND income_date BETWEEN ? AND ? GROUP BY source ORDER BY total DESC",
            array($from, $to))->result_array();
        $expenses = $this->db->query(
            "SELECT c.name AS label, SUM(e.amount) AS total, COUNT(*) AS cnt FROM expenses e JOIN expense_categories c ON c.id = e.category_id
             WHERE e.status = 'Active' AND e.expense_date BETWEEN ? AND ? GROUP BY c.name ORDER BY total DESC",
            array($from, $to))->result_array();

        $other_total = 0;
        foreach ($other as $o) { $other_total += to_paise_signed($o['total']); }
        $expense_total = 0;
        foreach ($expenses as $x) { $expense_total += to_paise_signed($x['total']); }

        $opening = $sum("SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE status = 'Active' AND payment_date < ?", array($from))
            + $sum("SELECT COALESCE(SUM(amount), 0) AS total FROM other_incomes WHERE status = 'Active' AND income_date < ?", array($from))
            - $sum("SELECT COALESCE(SUM(amount), 0) AS total FROM expenses WHERE status = 'Active' AND expense_date < ?", array($from));

        $income_total = $collection + $other_total;
        $net = $income_total - $expense_total;
        return array(
            'collection'     => from_paise($collection),
            'by_mode'        => $by_mode,
            'other'          => $other,
            'other_total'    => from_paise($other_total),
            'income_total'   => from_paise($income_total),
            'expenses'       => $expenses,
            'expense_total'  => from_paise($expense_total),
            'net'            => from_paise($net),
            'opening'        => from_paise($opening),
            'closing'        => from_paise($opening + $net),
        );
    }
}
