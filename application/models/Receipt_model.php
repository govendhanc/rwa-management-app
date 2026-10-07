<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Receipts (one per payment). Receipt rows are snapshots and never edited,
 * except for status (Cancelled) and the cached PDF path.
 */
class Receipt_model extends MY_Model
{
    protected $table = 'receipts';

    /**
     * @param array{from?: string, to?: string, status?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_receipts(array $filters): array
    {
        $this->db->select('r.*, o.owner_code')->from('receipts r')->join('owners o', 'o.id = r.owner_id');
        if ( ! empty($filters['from']))
        {
            $this->db->where('r.receipt_date >=', $filters['from']);
        }
        if ( ! empty($filters['to']))
        {
            $this->db->where('r.receipt_date <=', $filters['to']);
        }
        if ( ! empty($filters['status']))
        {
            $this->db->where('r.status', $filters['status']);
        }
        return $this->db->order_by('r.receipt_date', 'DESC')->order_by('r.id', 'DESC')->get()->result_array();
    }

    /**
     * Receipt with payment, owner contact and cancellation details.
     *
     * @return array<string, mixed>|null
     */
    public function find_full(int $id): ?array
    {
        $row = $this->db
            ->select('r.*, p.payment_date, p.remarks AS payment_remarks, p.cancel_reason, p.status AS payment_status, p.is_advance,
                      o.owner_code, o.whatsapp_no, o.mobile AS owner_mobile')
            ->from('receipts r')
            ->join('payments p', 'p.id = r.payment_id')
            ->join('owners o', 'o.id = r.owner_id')
            ->where('r.id', $id)
            ->get()->row_array();
        return $row ?: NULL;
    }

    /**
     * Lines printed on the receipt: month, plot, bill amount and amount applied.
     * Reversed lines (cancelled payments) are included so a cancelled receipt still shows what it covered.
     *
     * @return array<int, array<string, mixed>>
     */
    public function lines(int $payment_id): array
    {
        return $this->db
            ->select('pa.amount, pa.allocation_source, pa.status, m.billing_year, m.billing_month, m.period_start, m.amount AS bill_amount, h.plot_no, h.house_no')
            ->from('payment_allocations pa')
            ->join('maintenance m', 'm.id = pa.maintenance_id')
            ->join('houses h', 'h.id = m.house_id')
            ->where('pa.payment_id', $payment_id)
            ->where('pa.allocation_source', 'Payment')
            ->order_by('m.period_start')
            ->order_by('CAST(h.plot_no AS UNSIGNED)', '', FALSE)
            ->order_by('h.plot_no')
            ->get()->result_array();
    }
}
