<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Maintenance bills (one row per plot per month) - read queries.
 * All writes go through the Maintenance_engine library.
 */
class Maintenance_model extends MY_Model
{
    protected $table = 'maintenance';

    /**
     * Bills for one billing month (or every open bill when $year is NULL).
     *
     * @param array{status?: string, block?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_bills(?int $year, ?int $month, array $filters = array()): array
    {
        $this->db
            ->select('m.*, h.plot_no, h.house_no, h.block, o.owner_code, o.owner_name, o.mobile')
            ->from('maintenance m')
            ->join('houses h', 'h.id = m.house_id')
            ->join('owners o', 'o.id = m.owner_id');

        if ($year !== NULL && $month !== NULL)
        {
            $this->db->where('m.billing_year', $year)->where('m.billing_month', $month);
        }
        else
        {
            $this->db->where('m.record_status', 'Active')->where('m.balance_amount >', 0);
        }
        if ( ! empty($filters['status']))
        {
            if ($filters['status'] === 'Cancelled')
            {
                $this->db->where('m.record_status', 'Cancelled');
            }
            elseif ($filters['status'] === 'Unpaid')
            {
                $this->db->where('m.record_status', 'Active')->where_in('m.payment_status', array('Pending', 'Partially Paid'));
            }
            else
            {
                $this->db->where('m.record_status', 'Active')->where('m.payment_status', $filters['status']);
            }
        }
        if (isset($filters['block']) && $filters['block'] !== '')
        {
            $this->db->where('h.block', $filters['block']);
        }

        return $this->db
            ->order_by('m.period_start', 'DESC')
            ->order_by('h.block')
            ->order_by('CAST(h.plot_no AS UNSIGNED)', '', FALSE)
            ->order_by('h.plot_no')
            ->get()->result_array();
    }

    /**
     * Totals for one billing month (active monthly bills).
     *
     * @return array<string, mixed>
     */
    public function period_summary(int $year, int $month): array
    {
        $row = $this->db->query(
            "SELECT COUNT(*) AS bills, COUNT(DISTINCT owner_id) AS owners,
                    COALESCE(SUM(amount), 0) AS billed, COALESCE(SUM(paid_amount), 0) AS collected,
                    COALESCE(SUM(waived_amount), 0) AS waived, COALESCE(SUM(balance_amount), 0) AS outstanding,
                    SUM(payment_status = 'Paid') AS paid_count, SUM(payment_status = 'Partially Paid') AS partial_count,
                    SUM(payment_status = 'Pending') AS pending_count, SUM(payment_status = 'Waived') AS waived_count
             FROM maintenance
             WHERE record_status = 'Active' AND charge_type = 'Monthly' AND billing_year = ? AND billing_month = ?",
            array($year, $month)
        )->row_array();
        $due = to_paise_signed($row['billed']) - to_paise_signed($row['waived']);
        $row['collection_pct'] = $due > 0 ? round(to_paise_signed($row['collected']) * 100 / $due, 2) : 0.0;
        $row['batch'] = $this->db->get_where('maintenance_batches', array('billing_year' => $year, 'billing_month' => $month), 1)->row_array() ?: NULL;
        return $row;
    }

    /**
     * Generated months, newest first: [{billing_year, billing_month, total_records, total_amount, ...}].
     *
     * @return array<int, array<string, mixed>>
     */
    public function batches(): array
    {
        return $this->db
            ->select('b.*, u.full_name AS generated_by_name')
            ->from('maintenance_batches b')
            ->join('users u', 'u.id = b.generated_by', 'left')
            ->order_by('b.billing_year', 'DESC')
            ->order_by('b.billing_month', 'DESC')
            ->get()->result_array();
    }

    /**
     * @return array<string, mixed>|null Bill with plot, owner and batch details.
     */
    public function find_full(int $id): ?array
    {
        $row = $this->db
            ->select('m.*, h.plot_no, h.house_no, h.block, h.street, o.owner_code, o.owner_name, o.mobile, o.whatsapp_no,
                      r.amount AS rate_amount, r.effective_from AS rate_effective_from, u.full_name AS created_by_name')
            ->from('maintenance m')
            ->join('houses h', 'h.id = m.house_id')
            ->join('owners o', 'o.id = m.owner_id')
            ->join('maintenance_rates r', 'r.id = m.rate_id', 'left')
            ->join('users u', 'u.id = m.created_by', 'left')
            ->where('m.id', $id)
            ->get()->row_array();
        return $row ?: NULL;
    }

    /**
     * Payment allocations against a bill, with receipt numbers.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allocations(int $maintenance_id): array
    {
        return $this->db
            ->select('pa.*, p.payment_date, p.payment_mode, p.transaction_ref, p.status AS payment_status, r.id AS receipt_id, r.receipt_no')
            ->from('payment_allocations pa')
            ->join('payments p', 'p.id = pa.payment_id')
            ->join('receipts r', 'r.payment_id = p.id', 'left')
            ->where('pa.maintenance_id', $maintenance_id)
            ->order_by('pa.allocated_at')
            ->get()->result_array();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function adjustments(int $maintenance_id): array
    {
        return $this->db
            ->select('a.*, u.full_name AS created_by_name')
            ->from('maintenance_adjustments a')
            ->join('users u', 'u.id = a.created_by', 'left')
            ->where('a.maintenance_id', $maintenance_id)
            ->order_by('a.created_at')
            ->get()->result_array();
    }

    /**
     * Owners with unpaid or partly paid bills for a month, with this month's due,
     * their total outstanding (all months), plots and the last reminder sent.
     *
     * @return array<int, array<string, mixed>>
     */
    public function reminder_candidates(int $year, int $month): array
    {
        return $this->db->query(
            "SELECT o.id AS owner_id, o.owner_code, o.owner_name, o.mobile, o.whatsapp_no,
                    GROUP_CONCAT(DISTINCT h.plot_no ORDER BY CAST(h.plot_no AS UNSIGNED), h.plot_no SEPARATOR ', ') AS plots,
                    GROUP_CONCAT(DISTINCT COALESCE(NULLIF(h.house_no, ''), '-') ORDER BY CAST(h.plot_no AS UNSIGNED), h.plot_no SEPARATOR ', ') AS house_nos,
                    SUM(m.balance_amount) AS month_due,
                    MIN(m.id) AS first_bill_id,
                    MIN(m.due_date) AS due_date,
                    MAX(m.payment_status = 'Partially Paid') AS has_partial,
                    (SELECT COALESCE(SUM(m2.balance_amount), 0) FROM maintenance m2
                      WHERE m2.owner_id = o.id AND m2.record_status = 'Active') AS total_due,
                    (SELECT MAX(w.created_at) FROM whatsapp_messages w
                      WHERE w.owner_id = o.id AND w.template_key = 'maintenance_reminder'
                        AND w.maintenance_id IN (SELECT m3.id FROM maintenance m3 WHERE m3.owner_id = o.id AND m3.billing_year = ? AND m3.billing_month = ?)) AS last_reminded_at
             FROM maintenance m
             JOIN owners o ON o.id = m.owner_id
             JOIN houses h ON h.id = m.house_id
             WHERE m.record_status = 'Active' AND m.billing_year = ? AND m.billing_month = ?
               AND m.payment_status IN ('Pending', 'Partially Paid')
             GROUP BY o.id, o.owner_code, o.owner_name, o.mobile, o.whatsapp_no
             ORDER BY MIN(CAST(h.plot_no AS UNSIGNED)), o.owner_name",
            array($year, $month, $year, $month)
        )->result_array();
    }

    /**
     * Distinct blocks for the filter dropdown.
     *
     * @return string[]
     */
    public function blocks(): array
    {
        $rows = $this->db->query("SELECT DISTINCT block FROM houses WHERE block <> '' ORDER BY block")->result_array();
        return array_column($rows, 'block');
    }
}
