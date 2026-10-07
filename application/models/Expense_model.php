<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Expenses and expense categories.
 */
class Expense_model extends MY_Model
{
    protected $table = 'expenses';

    /**
     * @param array{from?: string, to?: string, category_id?: int, status?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_expenses(array $filters): array
    {
        $this->db->select('e.*, c.name AS category_name, u.full_name AS created_by_name')
            ->from('expenses e')
            ->join('expense_categories c', 'c.id = e.category_id')
            ->join('users u', 'u.id = e.created_by', 'left');
        if ( ! empty($filters['from']))
        {
            $this->db->where('e.expense_date >=', $filters['from']);
        }
        if ( ! empty($filters['to']))
        {
            $this->db->where('e.expense_date <=', $filters['to']);
        }
        if ( ! empty($filters['category_id']))
        {
            $this->db->where('e.category_id', (int) $filters['category_id']);
        }
        if ( ! empty($filters['status']))
        {
            $this->db->where('e.status', $filters['status']);
        }
        return $this->db->order_by('e.expense_date', 'DESC')->order_by('e.id', 'DESC')->get()->result_array();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_full(int $id): ?array
    {
        $row = $this->db->select('e.*, c.name AS category_name, cu.full_name AS created_by_name, uu.full_name AS updated_by_name, xu.full_name AS cancelled_by_name')
            ->from('expenses e')
            ->join('expense_categories c', 'c.id = e.category_id')
            ->join('users cu', 'cu.id = e.created_by', 'left')
            ->join('users uu', 'uu.id = e.updated_by', 'left')
            ->join('users xu', 'xu.id = e.cancelled_by', 'left')
            ->where('e.id', $id)
            ->get()->row_array();
        return $row ?: NULL;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function categories(bool $active_only = FALSE): array
    {
        $this->db->select('c.*, (SELECT COUNT(*) FROM expenses e WHERE e.category_id = c.id) AS expense_count', FALSE)->from('expense_categories c');
        if ($active_only)
        {
            $this->db->where('c.status', 'Active');
        }
        return $this->db->order_by('c.name')->get()->result_array();
    }

    /**
     * id => name for dropdowns (active categories, plus $include_id even if inactive).
     *
     * @return array<int, string>
     */
    public function category_options(?int $include_id = NULL): array
    {
        $options = array();
        foreach ($this->categories() as $c)
        {
            if ($c['status'] === 'Active' || (int) $c['id'] === $include_id)
            {
                $options[(int) $c['id']] = $c['name'];
            }
        }
        return $options;
    }
}
