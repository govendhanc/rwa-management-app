<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Payments - read queries. All writes go through the Payment_engine library.
 */
class Payment_model extends MY_Model
{
    protected $table = 'payments';

    /**
     * @param array{from?: string, to?: string, mode?: string, status?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_payments(array $filters): array
    {
        $this->db
            ->select('p.*, o.owner_code, o.owner_name, r.id AS receipt_id, r.receipt_no, r.plot_no, r.period_label, u.full_name AS received_by_name')
            ->from('payments p')
            ->join('owners o', 'o.id = p.owner_id')
            ->join('receipts r', 'r.payment_id = p.id', 'left')
            ->join('users u', 'u.id = p.received_by', 'left');
        if ( ! empty($filters['from']))
        {
            $this->db->where('p.payment_date >=', $filters['from']);
        }
        if ( ! empty($filters['to']))
        {
            $this->db->where('p.payment_date <=', $filters['to']);
        }
        if ( ! empty($filters['mode']))
        {
            $this->db->where('p.payment_mode', $filters['mode']);
        }
        if ( ! empty($filters['status']))
        {
            $this->db->where('p.status', $filters['status']);
        }
        return $this->db->order_by('p.payment_date', 'DESC')->order_by('p.id', 'DESC')->get()->result_array();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_full(int $id): ?array
    {
        $row = $this->db
            ->select('p.*, o.owner_code, o.owner_name, o.mobile, o.whatsapp_no, r.id AS receipt_id, r.receipt_no,
                      ru.full_name AS received_by_name, cu.full_name AS cancelled_by_name')
            ->from('payments p')
            ->join('owners o', 'o.id = p.owner_id')
            ->join('receipts r', 'r.payment_id = p.id', 'left')
            ->join('users ru', 'ru.id = p.received_by', 'left')
            ->join('users cu', 'cu.id = p.cancelled_by', 'left')
            ->where('p.id', $id)
            ->get()->row_array();
        return $row ?: NULL;
    }

    /**
     * Allocation lines of a payment with the bill month and plot.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allocation_lines(int $payment_id): array
    {
        return $this->db
            ->select('pa.*, m.billing_year, m.billing_month, m.period_start, m.amount AS bill_amount, m.balance_amount, m.payment_status AS bill_status, h.plot_no, h.house_no')
            ->from('payment_allocations pa')
            ->join('maintenance m', 'm.id = pa.maintenance_id')
            ->join('houses h', 'h.id = m.house_id')
            ->where('pa.payment_id', $payment_id)
            ->order_by('m.period_start')
            ->order_by('CAST(h.plot_no AS UNSIGNED)', '', FALSE)
            ->order_by('h.plot_no')
            ->get()->result_array();
    }

    /**
     * Owner search for Collect Payment: plot no, house no, name, owner code or mobile.
     * Active owners, plus inactive owners who still owe money.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search_owners(string $term, int $limit = 15): array
    {
        $term = trim($term);
        if ($term === '')
        {
            return array();
        }
        $like = '%'.$this->db->escape_like_str($term).'%';
        $digits = preg_replace('/\D+/', '', $term);

        $sql = "SELECT o.id, o.owner_code, o.owner_name, o.mobile, o.status,
                       hh.plots, hh.house_nos,
                       COALESCE(b.outstanding, 0) AS outstanding, COALESCE(b.advance_credit, 0) AS advance_credit
                FROM owners o
                LEFT JOIN (SELECT owner_id,
                                  GROUP_CONCAT(plot_no ORDER BY CAST(plot_no AS UNSIGNED), plot_no SEPARATOR ', ') AS plots,
                                  GROUP_CONCAT(COALESCE(NULLIF(house_no, ''), '-') ORDER BY CAST(plot_no AS UNSIGNED), plot_no SEPARATOR ', ') AS house_nos
                           FROM houses WHERE owner_id IS NOT NULL GROUP BY owner_id) hh ON hh.owner_id = o.id
                LEFT JOIN v_owner_balances b ON b.owner_id = o.id
                WHERE o.is_deleted = 0
                  AND (o.status = 'Active' OR COALESCE(b.outstanding, 0) > 0)
                  AND (o.owner_name LIKE ? ESCAPE '!' OR o.owner_code LIKE ? ESCAPE '!'
                       OR EXISTS (SELECT 1 FROM houses h WHERE h.owner_id = o.id AND (h.plot_no = ? OR h.house_no LIKE ? ESCAPE '!'))"
             .($digits !== '' && strlen($digits) >= 4 ? " OR o.mobile LIKE ? OR o.whatsapp_no LIKE ?" : '')."
                  )
                ORDER BY (o.owner_name LIKE ? ESCAPE '!') DESC, o.owner_name
                LIMIT ".(int) $limit;

        $params = array($like, $like, $term, $like);
        if ($digits !== '' && strlen($digits) >= 4)
        {
            $params[] = '%'.$digits.'%';
            $params[] = '%'.$digits.'%';
        }
        $params[] = $this->db->escape_like_str($term).'%';
        return $this->db->query($sql, $params)->result_array();
    }

    /**
     * Owner summary for the payment form.
     *
     * @return array<string, mixed>|null
     */
    public function owner_summary(int $owner_id): ?array
    {
        $row = $this->db->query(
            "SELECT o.id, o.owner_code, o.owner_name, o.mobile, o.whatsapp_no, o.status,
                    COALESCE(b.outstanding, 0) AS outstanding, COALESCE(b.advance_credit, 0) AS advance_credit,
                    b.last_payment_date
             FROM owners o LEFT JOIN v_owner_balances b ON b.owner_id = o.id
             WHERE o.id = ? AND o.is_deleted = 0",
            array($owner_id)
        )->row_array();
        return $row ?: NULL;
    }

    /**
     * Open bills of an owner, oldest first (the order payments are applied in).
     *
     * @return array<int, array<string, mixed>>
     */
    public function open_bills(int $owner_id): array
    {
        return $this->db
            ->select('m.id, m.billing_year, m.billing_month, m.period_start, m.due_date, m.amount, m.paid_amount, m.waived_amount, m.balance_amount, m.payment_status, m.charge_type, h.plot_no, h.house_no')
            ->from('maintenance m')
            ->join('houses h', 'h.id = m.house_id')
            ->where('m.owner_id', $owner_id)
            ->where('m.record_status', 'Active')
            ->where('m.balance_amount >', 0)
            ->order_by('m.period_start')
            ->order_by('m.id')
            ->get()->result_array();
    }

    /**
     * Owners holding unused advance credit.
     *
     * @return array<int, array<string, mixed>>
     */
    public function advances(): array
    {
        return $this->db->query(
            "SELECT b.owner_id, b.owner_code, b.owner_name, b.mobile, b.advance_credit, b.outstanding,
                    (SELECT GROUP_CONCAT(plot_no ORDER BY CAST(plot_no AS UNSIGNED) SEPARATOR ', ') FROM houses h WHERE h.owner_id = b.owner_id) AS plots,
                    (SELECT MAX(p.payment_date) FROM payments p WHERE p.owner_id = b.owner_id AND p.status = 'Active' AND p.unallocated_amount > 0) AS credit_since
             FROM v_owner_balances b
             WHERE b.advance_credit > 0
             ORDER BY b.advance_credit DESC, b.owner_name"
        )->result_array();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_by_token(string $token): ?array
    {
        $row = $this->db->get_where('payments', array('request_token' => $token), 1)->row_array();
        return $row ?: NULL;
    }

    /**
     * An active payment with the same mode + reference (duplicate entry guard).
     *
     * @return array<string, mixed>|null
     */
    public function active_with_reference(string $mode, string $reference): ?array
    {
        $row = $this->db
            ->select('p.id, p.payment_date, p.amount, o.owner_name, r.receipt_no')
            ->from('payments p')
            ->join('owners o', 'o.id = p.owner_id')
            ->join('receipts r', 'r.payment_id = p.id', 'left')
            ->where('p.status', 'Active')
            ->where('p.payment_mode', $mode)
            ->where('p.transaction_ref', $reference)
            ->limit(1)
            ->get()->row_array();
        return $row ?: NULL;
    }
}
