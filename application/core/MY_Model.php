<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base model with common single-table operations built on Query Builder.
 *
 * Child models set $table (and optionally $primary_key). Business rules and
 * multi-table transactions belong in libraries (services), not here.
 */
#[\AllowDynamicProperties]
class MY_Model extends CI_Model
{
    /** @var string */
    protected $table = '';

    /** @var string */
    protected $primary_key = 'id';

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $row = $this->db->get_where($this->table, array($this->primary_key => $id), 1)->row_array();
        return $row ?: NULL;
    }

    /**
     * Same as find() but locks the row until the current transaction ends.
     *
     * @return array<string, mixed>|null
     */
    public function find_for_update(int $id): ?array
    {
        $sql = 'SELECT * FROM '.$this->db->protect_identifiers($this->table, TRUE)
             .' WHERE '.$this->db->protect_identifiers($this->primary_key).' = ? LIMIT 1 FOR UPDATE';
        $row = $this->db->query($sql, array($id))->row_array();
        return $row ?: NULL;
    }

    /**
     * @param array<string, mixed> $where
     * @return array<int, array<string, mixed>>
     */
    public function get_all(array $where = array(), string $order_by = '', int $limit = 0): array
    {
        if ( ! empty($where))
        {
            $this->db->where($where);
        }
        if ($order_by !== '')
        {
            $this->db->order_by($order_by);
        }
        if ($limit > 0)
        {
            $this->db->limit($limit);
        }
        return $this->db->get($this->table)->result_array();
    }

    /**
     * @param array<string, mixed> $data
     * @return int New primary key, or 0 on failure.
     */
    public function insert(array $data): int
    {
        if ( ! $this->db->insert($this->table, $data))
        {
            return 0;
        }
        return (int) $this->db->insert_id();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        return (bool) $this->db->where($this->primary_key, $id)->update($this->table, $data);
    }

    /**
     * True if a row exists with $field = $value (optionally excluding one id - used for "unique" checks on edit).
     *
     * @param mixed $value
     */
    public function exists(string $field, $value, ?int $exclude_id = NULL): bool
    {
        $this->db->from($this->table)->where($field, $value);
        if ($exclude_id !== NULL)
        {
            $this->db->where($this->primary_key.' !=', $exclude_id);
        }
        return $this->db->count_all_results() > 0;
    }

    /**
     * @param array<string, mixed> $where
     */
    public function count_where(array $where = array()): int
    {
        $this->db->from($this->table);
        if ( ! empty($where))
        {
            $this->db->where($where);
        }
        return (int) $this->db->count_all_results();
    }
}
