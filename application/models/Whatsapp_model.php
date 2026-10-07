<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * WhatsApp message templates and the message log.
 */
class Whatsapp_model extends CI_Model
{
    /**
     * Active template by key.
     *
     * @return array<string, mixed>|null
     */
    public function template(string $key): ?array
    {
        $row = $this->db->get_where('whatsapp_templates', array('template_key' => $key, 'is_active' => 1), 1)->row_array();
        return $row ?: NULL;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function templates(): array
    {
        return $this->db->select('t.*, u.full_name AS updated_by_name')
            ->from('whatsapp_templates t')
            ->join('users u', 'u.id = t.updated_by', 'left')
            ->order_by('t.id')
            ->get()->result_array();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_template(int $id): ?array
    {
        $row = $this->db->get_where('whatsapp_templates', array('id' => $id), 1)->row_array();
        return $row ?: NULL;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update_template(int $id, array $data): bool
    {
        return (bool) $this->db->where('id', $id)->update('whatsapp_templates', $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function log_message(array $data): int
    {
        $this->db->insert('whatsapp_messages', $data);
        return (int) $this->db->insert_id();
    }

    /**
     * @param array{from?: string, to?: string, template?: string, status?: string, channel?: string} $filters
     * @return array<int, array<string, mixed>>
     */
    public function messages(array $filters): array
    {
        $this->db
            ->select('w.*, o.owner_code, o.owner_name, r.receipt_no, u.full_name AS sent_by_name')
            ->from('whatsapp_messages w')
            ->join('owners o', 'o.id = w.owner_id')
            ->join('receipts r', 'r.id = w.receipt_id', 'left')
            ->join('users u', 'u.id = w.created_by', 'left');
        if ( ! empty($filters['from']))
        {
            $this->db->where('w.created_at >=', $filters['from'].' 00:00:00');
        }
        if ( ! empty($filters['to']))
        {
            $this->db->where('w.created_at <=', $filters['to'].' 23:59:59');
        }
        foreach (array('template' => 'w.template_key', 'status' => 'w.status', 'channel' => 'w.channel') as $key => $column)
        {
            if ( ! empty($filters[$key]))
            {
                $this->db->where($column, $filters[$key]);
            }
        }
        return $this->db->order_by('w.created_at', 'DESC')->order_by('w.id', 'DESC')->get()->result_array();
    }
}
