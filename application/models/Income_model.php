<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Other income (interest, donations, hall rent, penalties ...).
 */
class Income_model extends MY_Model
{
    protected $table = 'other_incomes';

    /**
     * @param array{from?: string, to?: string, status?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function list_incomes(array $filters): array
    {
        $this->db->select('i.*, u.full_name AS created_by_name')->from('other_incomes i')->join('users u', 'u.id = i.created_by', 'left');
        if ( ! empty($filters['from']))
        {
            $this->db->where('i.income_date >=', $filters['from']);
        }
        if ( ! empty($filters['to']))
        {
            $this->db->where('i.income_date <=', $filters['to']);
        }
        if ( ! empty($filters['status']))
        {
            $this->db->where('i.status', $filters['status']);
        }
        return $this->db->order_by('i.income_date', 'DESC')->order_by('i.id', 'DESC')->get()->result_array();
    }

    /**
     * Sources already used, for the form's suggestion list.
     *
     * @return string[]
     */
    public function sources(): array
    {
        $rows = $this->db->query('SELECT DISTINCT source FROM other_incomes ORDER BY source')->result_array();
        $sources = array_column($rows, 'source');
        foreach (array('Interest', 'Donation', 'Hall Rent', 'Penalty', 'Other') as $default)
        {
            if ( ! in_array($default, $sources, TRUE))
            {
                $sources[] = $default;
            }
        }
        sort($sources);
        return $sources;
    }
}
