<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Roles and their permission grants.
 */
class Role_model extends MY_Model
{
    protected $table = 'roles';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all_roles(): array
    {
        return $this->db->order_by('id')->get('roles')->result_array();
    }

    /**
     * id => role_name for <select> lists.
     *
     * @return array<int, string>
     */
    public function options(): array
    {
        $options = array();
        foreach ($this->all_roles() as $role)
        {
            $options[(int) $role['id']] = $role['role_name'];
        }
        return $options;
    }

    /**
     * Permissions grouped by module, ordered for display.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function permissions_by_module(): array
    {
        $grouped = array();
        foreach ($this->db->order_by('id')->get('permissions')->result_array() as $perm)
        {
            $grouped[$perm['module']][] = $perm;
        }
        return $grouped;
    }

    /**
     * role_id => [permission_id, ...]
     *
     * @return array<int, int[]>
     */
    public function grant_map(): array
    {
        $map = array();
        foreach ($this->db->get('role_permissions')->result_array() as $row)
        {
            $map[(int) $row['role_id']][] = (int) $row['permission_id'];
        }
        return $map;
    }

    /**
     * Replace a role's grants. Caller wraps this in a transaction.
     *
     * @param int[] $permission_ids
     */
    public function replace_grants(int $role_id, array $permission_ids): void
    {
        $this->db->where('role_id', $role_id)->delete('role_permissions');
        $rows = array();
        foreach (array_unique($permission_ids) as $pid)
        {
            $rows[] = array('role_id' => $role_id, 'permission_id' => (int) $pid);
        }
        if ( ! empty($rows))
        {
            $this->db->insert_batch('role_permissions', $rows);
        }
    }

    /**
     * @return int[]
     */
    public function valid_permission_ids(): array
    {
        return array_map('intval', array_column($this->db->select('id')->get('permissions')->result_array(), 'id'));
    }
}
