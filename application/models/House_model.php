<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Houses / plots. A house has at most one current owner (houses.owner_id).
 */
class House_model extends MY_Model
{
    protected $table = 'houses';

    /**
     * All houses with owner name and code.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list_all(): array
    {
        return $this->db
            ->select("h.*, o.owner_code, o.owner_name, o.status AS owner_status, t.id AS tenant_id, t.tenant_name", FALSE)
            ->from('houses h')
            ->join('owners o', 'o.id = h.owner_id AND o.is_deleted = 0', 'left')
            ->join('tenants t', "t.house_id = h.id AND t.status = 'Active'", 'left')
            ->order_by('h.block, CAST(h.plot_no AS UNSIGNED), h.plot_no', '', FALSE)
            ->get()->result_array();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_with_owner(int $id): ?array
    {
        $row = $this->db
            ->select("h.*, o.owner_code, o.owner_name, t.id AS tenant_id", FALSE)
            ->from('houses h')
            ->join('owners o', 'o.id = h.owner_id', 'left')
            ->join('tenants t', "t.house_id = h.id AND t.status = 'Active'", 'left')
            ->where('h.id', $id)
            ->get()->row_array();
        return $row ?: NULL;
    }

    /**
     * Active houses without an owner, for assignment dropdowns.
     *
     * @return array<int, string> id => "Plot 12 - A-112 (Block A)"
     */
    public function unassigned_options(): array
    {
        $rows = $this->db->select('id, plot_no, house_no, block')
            ->from('houses')
            ->where('owner_id IS NULL', NULL, FALSE)
            ->where('status', 'Active')
            ->order_by('block, CAST(plot_no AS UNSIGNED), plot_no', '', FALSE)
            ->get()->result_array();
        $options = array();
        foreach ($rows as $r)
        {
            $options[(int) $r['id']] = $this->label($r);
        }
        return $options;
    }

    /**
     * Houses currently owned by an owner.
     *
     * @return array<int, array<string, mixed>>
     */
    public function for_owner(int $owner_id): array
    {
        return $this->db
            ->select("h.*, t.id AS tenant_id, t.tenant_name, t.mobile AS tenant_mobile", FALSE)
            ->from('houses h')
            ->join('tenants t', "t.house_id = h.id AND t.status = 'Active'", 'left')
            ->where('h.owner_id', $owner_id)
            ->order_by('h.block, CAST(h.plot_no AS UNSIGNED), h.plot_no', '', FALSE)
            ->get()->result_array();
    }

    /**
     * Current (active) tenant of a house, if any.
     *
     * @return array<string, mixed>|null
     */
    public function active_tenant(int $house_id): ?array
    {
        $row = $this->db->get_where('tenants', array('house_id' => $house_id, 'status' => 'Active'), 1)->row_array();
        return $row ?: NULL;
    }

    /**
     * Outstanding maintenance on this house that is billed to a given owner.
     */
    public function outstanding_for_owner(int $house_id, int $owner_id): string
    {
        return (string) $this->db->query(
            "SELECT COALESCE(SUM(balance_amount), 0) AS total FROM maintenance
             WHERE house_id = ? AND owner_id = ? AND record_status = 'Active'",
            array($house_id, $owner_id)
        )->row()->total;
    }

    public function count_bills(int $house_id): int
    {
        return (int) $this->db->where('house_id', $house_id)->count_all_results('maintenance');
    }

    /**
     * @param array<string, mixed> $row
     */
    public function label(array $row): string
    {
        $label = 'Plot '.$row['plot_no'];
        if ( ! empty($row['house_no']))
        {
            $label .= ' - '.$row['house_no'];
        }
        if ( ! empty($row['block']))
        {
            $label .= ' (Block '.$row['block'].')';
        }
        return $label;
    }

    /**
     * Is (block, house_no) already used by another house? Empty house_no never conflicts.
     */
    public function house_no_taken(string $block, ?string $house_no, ?int $exclude_id = NULL): bool
    {
        if ($house_no === NULL || $house_no === '')
        {
            return FALSE;
        }
        $this->db->from('houses')->where('block', $block)->where('house_no', $house_no);
        if ($exclude_id !== NULL)
        {
            $this->db->where('id !=', $exclude_id);
        }
        return $this->db->count_all_results() > 0;
    }
}
