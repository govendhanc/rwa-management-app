<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Maintenance rate history by plot category ("Vacant Plot" / "Constructed").
 *
 * The rate for a billing month is the row with the latest effective_from that is
 * on or before the first day of that month. Rates are never edited once used by a
 * bill, so past bills always keep the amount they were issued at.
 */
class Rate_model extends MY_Model
{
    protected $table = 'maintenance_rates';

    /**
     * Category for a plot's built status (config: plot_category_by_built_status).
     */
    public function category_for(string $built_status): string
    {
        $map = (array) $this->config->item('plot_category_by_built_status');
        return $map[$built_status] ?? 'Vacant Plot';
    }

    /**
     * SQL CASE expression mapping a built_status column to its category (same rules as category_for()).
     */
    public function category_sql(string $column): string
    {
        $sql = 'CASE '.$column;
        foreach ((array) $this->config->item('plot_category_by_built_status') as $status => $category)
        {
            $sql .= ' WHEN '.$this->db->escape($status).' THEN '.$this->db->escape($category);
        }
        return $sql." ELSE 'Vacant Plot' END";
    }

    /**
     * Rate row in force for a category in the month containing $date (Y-m-d).
     *
     * @return array<string, mixed>|null
     */
    public function rate_for(string $category, string $date): ?array
    {
        $month_start = date('Y-m-01', strtotime($date));
        $row = $this->db->from($this->table)
            ->where('plot_category', $category)
            ->where('effective_from <=', $month_start)
            ->order_by('effective_from', 'DESC')
            ->limit(1)
            ->get()->row_array();
        return $row ?: NULL;
    }

    /**
     * Rates in force for every category in a given month (default: this month).
     *
     * @return array<string, array<string, mixed>|null> category => rate row or NULL
     */
    public function rates_for_month(?string $date = NULL): array
    {
        $date = $date ?? date('Y-m-d');
        $rates = array();
        foreach ((array) $this->config->item('plot_categories') as $category)
        {
            $rates[$category] = $this->rate_for($category, $date);
        }
        return $rates;
    }

    /**
     * Full history with a computed state per row:
     *  Scheduled  - starts in a future month
     *  Current    - the rate in force this month
     *  Superseded - replaced by a later rate
     * plus bill_count (bills issued at this rate).
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(): array
    {
        $rows = $this->db->query(
            "SELECT r.*, u.full_name AS created_by_name,
                    (SELECT COUNT(*) FROM maintenance m WHERE m.rate_id = r.id) AS bill_count
             FROM maintenance_rates r
             LEFT JOIN users u ON u.id = r.created_by
             ORDER BY r.plot_category, r.effective_from DESC"
        )->result_array();

        $current = $this->rates_for_month();
        $this_month = date('Y-m-01');
        foreach ($rows as &$row)
        {
            if ($row['effective_from'] > $this_month)
            {
                $row['state'] = 'Scheduled';
            }
            elseif (isset($current[$row['plot_category']]) && (int) $current[$row['plot_category']]['id'] === (int) $row['id'])
            {
                $row['state'] = 'Current';
            }
            else
            {
                $row['state'] = 'Superseded';
            }
        }
        unset($row);
        return $rows;
    }

    /**
     * First day of the latest month that already has generated bills, or NULL.
     */
    public function last_billed_month(): ?string
    {
        $row = $this->db->query("SELECT MAX(period_start) AS last_month FROM maintenance WHERE charge_type = 'Monthly'")->row_array();
        return $row['last_month'] ?? NULL;
    }

    public function exists_for(string $category, string $effective_from): bool
    {
        return $this->db->where(array('plot_category' => $category, 'effective_from' => $effective_from))
            ->count_all_results($this->table) > 0;
    }

    public function bill_count(int $rate_id): int
    {
        return (int) $this->db->where('rate_id', $rate_id)->count_all_results('maintenance');
    }
}
