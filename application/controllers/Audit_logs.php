<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Audit log viewer (read-only). The table is append-only; there is no edit or delete route.
 * Server-side paging keeps it fast as the log grows.
 */
class Audit_logs extends Auth_Controller
{
    protected $permission_map = array(
        'index'  => 'audit.view',
        'data'   => 'audit.view',
        'detail' => 'audit.view',
    );

    public function index(): void
    {
        $this->render('audit/index', array(
            'page_title'  => 'Audit Logs',
            'breadcrumbs' => array(array('label' => 'Administration'), array('label' => 'Audit Logs')),
            'modules'     => array_column($this->db->query('SELECT DISTINCT module FROM audit_logs ORDER BY module')->result_array(), 'module'),
            'users'       => $this->db->select('id, full_name, username')->order_by('full_name')->get('users')->result_array(),
            'scripts'     => array('js/modules/audit.js'),
        ));
    }

    /**
     * AJAX GET: DataTables server-side endpoint.
     */
    public function data(): void
    {
        $this->require_ajax();
        $draw = (int) $this->input->get('draw');
        $start = max(0, (int) $this->input->get('start'));
        $length = (int) $this->input->get('length');
        $length = $length > 0 ? min($length, 200) : 25;
        $search = trim((string) ($this->input->get('search')['value'] ?? ''));
        $order = $this->input->get('order');
        $dir = isset($order[0]['dir']) && $order[0]['dir'] === 'asc' ? 'ASC' : 'DESC';

        $apply_filters = function () use ($search) {
            $from = (string) $this->input->get('from');
            $to = (string) $this->input->get('to');
            if (is_valid_date($from))
            {
                $this->db->where('a.created_at >=', $from.' 00:00:00');
            }
            if (is_valid_date($to))
            {
                $this->db->where('a.created_at <=', $to.' 23:59:59');
            }
            $module = (string) $this->input->get('module');
            if ($module !== '' && preg_match('/^[a-z_]{1,40}$/', $module))
            {
                $this->db->where('a.module', $module);
            }
            $user = (int) $this->input->get('user_id');
            if ($user > 0)
            {
                $this->db->where('a.user_id', $user);
            }
            if ($search !== '')
            {
                $this->db->group_start()
                    ->like('a.action', $search)
                    ->or_like('a.module', $search)
                    ->or_like('a.username', $search)
                    ->or_like('a.record_id', $search)
                    ->or_like('a.ip_address', $search)
                    ->group_end();
            }
        };

        $total = (int) $this->db->count_all('audit_logs');
        $this->db->from('audit_logs a');
        $apply_filters();
        $filtered = (int) $this->db->count_all_results();

        $this->db->select('a.id, a.created_at, a.username, a.action, a.module, a.record_id, a.ip_address, (a.old_value IS NOT NULL OR a.new_value IS NOT NULL) AS has_detail', FALSE)
            ->from('audit_logs a');
        $apply_filters();
        $rows = $this->db->order_by('a.id', $dir)->limit($length, $start)->get()->result_array();

        $data = array();
        foreach ($rows as $r)
        {
            $data[] = array(
                'id'         => (int) $r['id'],
                'created_at' => fmt_datetime($r['created_at']),
                'user'       => (string) ($r['username'] ?? 'system'),
                'action'     => $r['action'],
                'module'     => $r['module'],
                'record_id'  => (string) $r['record_id'],
                'ip'         => (string) $r['ip_address'],
                'has_detail' => (int) $r['has_detail'] === 1,
            );
        }
        $this->output->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode(array('draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $data), JSON_UNESCAPED_UNICODE))
            ->_display();
        exit;
    }

    /**
     * AJAX GET: old/new values of one entry.
     */
    public function detail(int $id = 0): void
    {
        $this->require_ajax();
        $row = $this->db->get_where('audit_logs', array('id' => $id))->row_array();
        if ( ! $row)
        {
            $this->json_error('Entry not found.', 404);
        }
        $old = $row['old_value'] !== NULL ? json_decode($row['old_value'], TRUE) : NULL;
        $new = $row['new_value'] !== NULL ? json_decode($row['new_value'], TRUE) : NULL;
        $keys = array_values(array_unique(array_merge(array_keys((array) $old), array_keys((array) $new))));
        $changes = array();
        foreach ($keys as $k)
        {
            $changes[] = array(
                'field' => (string) $k,
                'old'   => is_array($old) && array_key_exists($k, $old) ? (is_scalar($old[$k]) || $old[$k] === NULL ? (string) $old[$k] : json_encode($old[$k], JSON_UNESCAPED_UNICODE)) : '',
                'new'   => is_array($new) && array_key_exists($k, $new) ? (is_scalar($new[$k]) || $new[$k] === NULL ? (string) $new[$k] : json_encode($new[$k], JSON_UNESCAPED_UNICODE)) : '',
            );
        }
        $this->json_success('OK', array(
            'action'     => $row['action'],
            'module'     => $row['module'],
            'record_id'  => $row['record_id'],
            'user'       => $row['username'],
            'created_at' => fmt_datetime($row['created_at']),
            'ip'         => $row['ip_address'],
            'user_agent' => $row['user_agent'],
            'changes'    => $changes,
        ));
    }
}
