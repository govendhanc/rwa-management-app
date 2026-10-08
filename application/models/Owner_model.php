<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Owners (members).
 *
 * Lifecycle: Active -> Inactive (deactivated: kept with all history, not billed) -> Active again.
 * Owners removed because they were entered by mistake (no financial history) have
 * is_deleted = 1 and are excluded everywhere.
 */
class Owner_model extends MY_Model
{
    protected $table = 'owners';

    /**
     * Owner list (Active or Inactive) with plots, balances and the count of billable plots per
     * rate category (constructed_plots / vacant_plots - active plots only).
     *
     * @return array<int, array<string, mixed>>
     */
    public function list_all(string $status = 'Active'): array
    {
        $this->load->model('rate_model');
        $category = $this->rate_model->category_sql('built_status');
        return $this->db->query(
            "SELECT o.id, o.owner_code, o.owner_name, o.co_owner_name, o.mobile, o.whatsapp_no, o.email,
                    o.owner_type, o.status, o.deactivated_at, o.deactivation_reason,
                    du.full_name AS deactivated_by_name,
                    hh.plots, hh.house_nos, hh.occupancy, hh.house_count,
                    COALESCE(hh.constructed_plots, 0) AS constructed_plots,
                    COALESCE(hh.vacant_plots, 0) AS vacant_plots,
                    COALESCE(b.outstanding, 0) AS outstanding,
                    COALESCE(b.advance_credit, 0) AS advance_credit
             FROM owners o
             LEFT JOIN (
                 SELECT owner_id,
                        GROUP_CONCAT(plot_no ORDER BY CAST(plot_no AS UNSIGNED), plot_no SEPARATOR ', ') AS plots,
                        GROUP_CONCAT(COALESCE(NULLIF(house_no, ''), '-') ORDER BY CAST(plot_no AS UNSIGNED), plot_no SEPARATOR ', ') AS house_nos,
                        GROUP_CONCAT(DISTINCT occupancy_status ORDER BY occupancy_status SEPARATOR ', ') AS occupancy,
                        COUNT(*) AS house_count,
                        SUM(status = 'Active' AND ($category) = 'Constructed') AS constructed_plots,
                        SUM(status = 'Active' AND ($category) = 'Vacant Plot') AS vacant_plots
                 FROM houses
                 WHERE owner_id IS NOT NULL
                 GROUP BY owner_id
             ) hh ON hh.owner_id = o.id
             LEFT JOIN v_owner_balances b ON b.owner_id = o.id
             LEFT JOIN users du ON du.id = o.deactivated_by
             WHERE o.is_deleted = 0 AND o.status = ?
             ORDER BY CAST(SUBSTRING_INDEX(COALESCE(hh.plots, '999999'), ',', 1) AS UNSIGNED), o.owner_name",
            array($status)
        )->result_array();
    }

    /**
     * @return array{Active: int, Inactive: int}
     */
    public function status_counts(): array
    {
        $counts = array('Active' => 0, 'Inactive' => 0);
        $rows = $this->db->select('status, COUNT(*) AS n')->where('is_deleted', 0)->group_by('status')->get('owners')->result_array();
        foreach ($rows as $r)
        {
            $counts[$r['status']] = (int) $r['n'];
        }
        return $counts;
    }

    /**
     * @return array<string, mixed>|null Owner (not deleted, Active or Inactive) with balance figures.
     */
    public function find_active_record(int $id): ?array
    {
        $row = $this->db->query(
            "SELECT o.*,
                    COALESCE(b.total_due, 0) AS total_due, COALESCE(b.total_paid, 0) AS total_paid,
                    COALESCE(b.total_waived, 0) AS total_waived, COALESCE(b.outstanding, 0) AS outstanding,
                    COALESCE(b.advance_credit, 0) AS advance_credit, COALESCE(b.net_payable, 0) AS net_payable,
                    b.last_payment_date,
                    cu.full_name AS created_by_name, uu.full_name AS updated_by_name, du.full_name AS deactivated_by_name
             FROM owners o
             LEFT JOIN v_owner_balances b ON b.owner_id = o.id
             LEFT JOIN users cu ON cu.id = o.created_by
             LEFT JOIN users uu ON uu.id = o.updated_by
             LEFT JOIN users du ON du.id = o.deactivated_by
             WHERE o.id = ? AND o.is_deleted = 0",
            array($id)
        )->row_array();
        return $row ?: NULL;
    }

    /**
     * Another owner (not deleted) with the same name and mobile number - the same person
     * entered twice. Their extra plots must be assigned to the existing owner instead.
     *
     * @return array<string, mixed>|null
     */
    public function find_duplicate(string $owner_name, string $mobile, int $exclude_id = 0): ?array
    {
        $row = $this->db->select('id, owner_code, owner_name, status')
            ->from('owners')
            ->where('is_deleted', 0)
            ->where('mobile', $mobile)
            ->where('LOWER(TRIM(owner_name)) =', mb_strtolower(trim($owner_name)))
            ->where('id !=', $exclude_id)
            ->limit(1)
            ->get()->row_array();
        return $row ?: NULL;
    }

    /**
     * Active owners for dropdowns: id => "OWN00001 - Name".
     *
     * @return array<int, string>
     */
    public function active_options(): array
    {
        $rows = $this->db->select('id, owner_code, owner_name')
            ->from('owners')
            ->where('is_deleted', 0)
            ->where('status', 'Active')
            ->order_by('owner_name')
            ->get()->result_array();
        $options = array();
        foreach ($rows as $r)
        {
            $options[(int) $r['id']] = $r['owner_code'].' - '.$r['owner_name'];
        }
        return $options;
    }

    /**
     * Maintenance charges for an owner, newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function maintenance_history(int $owner_id): array
    {
        return $this->db
            ->select('m.*, h.plot_no, h.house_no')
            ->from('maintenance m')
            ->join('houses h', 'h.id = m.house_id')
            ->where('m.owner_id', $owner_id)
            ->order_by('m.period_start', 'DESC')
            ->order_by('h.plot_no')
            ->get()->result_array();
    }

    /**
     * Payments with receipt numbers and the months each payment covered, newest first.
     *
     * @param int $limit 0 = all
     * @return array<int, array<string, mixed>>
     */
    public function payment_history(int $owner_id, int $limit = 0): array
    {
        return $this->db->query(
            "SELECT p.*, r.id AS receipt_id, r.receipt_no, r.period_label,
                    u.full_name AS received_by_name
             FROM payments p
             LEFT JOIN receipts r ON r.payment_id = p.id
             LEFT JOIN users u ON u.id = p.received_by
             WHERE p.owner_id = ?
             ORDER BY p.payment_date DESC, p.id DESC"
            .($limit > 0 ? ' LIMIT '.(int) $limit : ''),
            array($owner_id)
        )->result_array();
    }

    /**
     * True when the owner has any maintenance or payment row (then they can only be deactivated).
     */
    public function has_financial_history(int $owner_id): bool
    {
        $bills = $this->db->where('owner_id', $owner_id)->count_all_results('maintenance');
        if ($bills > 0)
        {
            return TRUE;
        }
        return $this->db->where('owner_id', $owner_id)->count_all_results('payments') > 0;
    }
}
