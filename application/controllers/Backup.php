<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Settings > Database Backup (Super Admin): create, download, delete backups.
 */
class Backup extends Auth_Controller
{
    protected $permission_map = array(
        'index'    => 'backup.manage',
        'create'   => 'backup.manage',
        'download' => 'backup.manage',
        'delete'   => 'backup.manage',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->library('db_backup');
    }

    public function index(): void
    {
        $rows = $this->db->select('b.*, u.full_name AS created_by_name')->from('database_backups b')->join('users u', 'u.id = b.created_by', 'left')
            ->order_by('b.id', 'DESC')->get()->result_array();
        foreach ($rows as &$r)
        {
            $r['exists'] = $this->db_backup->path_of($r['file_name']) !== NULL;
            $r['size_label'] = $this->db_backup->human_size((int) $r['file_size']);
        }
        unset($r);
        $this->render('backup/index', array(
            'page_title'  => 'Database Backup',
            'breadcrumbs' => array(array('label' => 'Settings'), array('label' => 'Database Backup')),
            'backups'     => $rows,
            'method'      => (string) env('MYSQLDUMP_PATH', '') !== '' ? 'mysqldump (falls back to PHP if unavailable)' : 'PHP (built-in)',
        ));
    }

    public function create(): void
    {
        $this->require_post();
        $result = $this->db_backup->create();
        if ($result['success'])
        {
            $this->audit->log('Backup Created', 'backup', $result['file'], NULL, array('size' => $result['size'], 'method' => $result['method']));
        }
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect('backup');
    }

    public function download(int $id = 0): void
    {
        $row = $this->db->get_where('database_backups', array('id' => $id))->row_array();
        $path = $row ? $this->db_backup->path_of($row['file_name']) : NULL;
        if ($path === NULL)
        {
            show_404();
        }
        $this->audit->log('Backup Downloaded', 'backup', $row['file_name']);
        header('Content-Type: application/gzip');
        header('Content-Length: '.filesize($path));
        header('Content-Disposition: attachment; filename="'.$row['file_name'].'"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    public function delete(int $id = 0): void
    {
        $this->require_post();
        $row = $this->db->get_where('database_backups', array('id' => $id))->row_array();
        if ( ! $row)
        {
            show_404();
        }
        $path = $this->db_backup->path_of($row['file_name']);
        if ($path !== NULL)
        {
            @unlink($path);
        }
        $this->db->where('id', $id)->delete('database_backups');
        $this->audit->log('Backup Deleted', 'backup', $row['file_name']);
        $this->session->set_flashdata('success', 'Backup '.$row['file_name'].' deleted.');
        redirect('backup');
    }
}
