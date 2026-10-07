<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tenants of rented houses (records only - billing always stays with the owner).
 */
class Tenant_model extends MY_Model
{
    protected $table = 'tenants';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list_all(): array
    {
        return $this->db
            ->select('t.*, h.plot_no, h.house_no, h.block, o.owner_code, o.owner_name')
            ->from('tenants t')
            ->join('houses h', 'h.id = t.house_id')
            ->join('owners o', 'o.id = t.owner_id', 'left')
            ->order_by("FIELD(t.status, 'Active', 'Moved Out')", '', FALSE)
            ->order_by('t.move_in_date', 'DESC')
            ->get()->result_array();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_full(int $id): ?array
    {
        $row = $this->db
            ->select('t.*, h.plot_no, h.house_no, h.block, h.street, h.occupancy_status, o.owner_code, o.owner_name, o.mobile AS owner_mobile,
                      cu.full_name AS created_by_name, uu.full_name AS updated_by_name')
            ->from('tenants t')
            ->join('houses h', 'h.id = t.house_id')
            ->join('owners o', 'o.id = t.owner_id', 'left')
            ->join('users cu', 'cu.id = t.created_by', 'left')
            ->join('users uu', 'uu.id = t.updated_by', 'left')
            ->where('t.id', $id)
            ->get()->row_array();
        return $row ?: NULL;
    }

    /**
     * Houses that can receive a new tenant: active, owned by an active owner, no active tenant.
     *
     * @return array<int, string> id => label with owner name
     */
    public function rentable_house_options(): array
    {
        $rows = $this->db
            ->select('h.id, h.plot_no, h.house_no, h.block, o.owner_name')
            ->from('houses h')
            ->join('owners o', 'o.id = h.owner_id')
            ->join('tenants t', "t.house_id = h.id AND t.status = 'Active'", 'left')
            ->where('h.status', 'Active')
            ->where('o.status', 'Active')
            ->where('o.is_deleted', 0)
            ->where('t.id IS NULL', NULL, FALSE)
            ->order_by('h.block, CAST(h.plot_no AS UNSIGNED), h.plot_no', '', FALSE)
            ->get()->result_array();
        $options = array();
        foreach ($rows as $r)
        {
            $label = 'Plot '.$r['plot_no'].($r['house_no'] ? ' - '.$r['house_no'] : '').' (Owner: '.$r['owner_name'].')';
            $options[(int) $r['id']] = $label;
        }
        return $options;
    }

    /**
     * Active tenants whose agreement ends within $days days (or has already ended).
     */
    public function count_agreements_due(int $days): int
    {
        return (int) $this->db->from('tenants')
            ->where('status', 'Active')
            ->where('agreement_end IS NOT NULL', NULL, FALSE)
            ->where('agreement_end <=', date('Y-m-d', strtotime('+'.$days.' days')))
            ->count_all_results();
    }
}
