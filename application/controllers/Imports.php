<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Owner import from CSV: upload, validate-only or import, summary, error report download, template.
 */
class Imports extends Auth_Controller
{
    protected $permission_map = array(
        'index'    => 'owners.import',
        'upload'   => 'owners.import',
        'template' => 'owners.import',
        'report'   => 'owners.import',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->library(array('secure_upload', 'owner_import'));
    }

    public function index(): void
    {
        $this->render('imports/index', array(
            'page_title'  => 'Import Owners',
            'breadcrumbs' => array(array('label' => 'Owners', 'url' => 'owners'), array('label' => 'Import')),
            'history'     => $this->db->select('b.*, u.full_name AS created_by_name')->from('import_batches b')->join('users u', 'u.id = b.created_by', 'left')
                                 ->order_by('b.id', 'DESC')->limit(20)->get()->result_array(),
            'result'      => $this->session->flashdata('import_result'),
        ));
    }

    public function upload(): void
    {
        $this->require_post();
        $start = (string) $this->input->post('maintenance_start');
        if ( ! preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/', $start))
        {
            $this->session->set_flashdata('error', 'Please choose the month from which imported owners are billed.');
            redirect('imports');
        }
        $dry_run = $this->input->post('dry_run') === '1';

        $stored = $this->secure_upload->store('file', 'imports', (string) $this->config->item('upload_import_types'), (int) $this->config->item('upload_import_max_kb'));
        if ( ! $stored['success'] || ! isset($stored['path']))
        {
            $this->session->set_flashdata('error', $stored['success'] ? 'Please choose a CSV file.' : $stored['message']);
            redirect('imports');
        }
        $file = STORAGEPATH.str_replace('/', DIRECTORY_SEPARATOR, $stored['path']);

        $result = $this->owner_import->run($file, $start.'-01', $dry_run);
        // The uploaded file contains personal data: keep only the result report
        $this->secure_upload->delete($stored['path']);

        if ( ! $result['success'])
        {
            $this->session->set_flashdata('error', $result['message']);
            redirect('imports');
        }

        $this->db->insert('import_batches', array(
            'import_type'        => $dry_run ? 'owners (validation)' : 'owners',
            'original_file_name' => mb_substr((string) $stored['original_name'], 0, 255),
            'total_records'      => $result['total'],
            'imported_count'     => $result['imported'],
            'failed_count'       => $result['failed'],
            'duplicate_count'    => $result['duplicate'],
            'created_by'         => user_id(),
        ));
        $batch_id = (int) $this->db->insert_id();
        $report = $this->owner_import->write_report($result['rows'], $result['headers'], $batch_id);
        $this->db->where('id', $batch_id)->update('import_batches', array('error_file_path' => $report));

        if ( ! $dry_run)
        {
            $this->audit->log('Owners Imported', 'owners', 'import-'.$batch_id, NULL, array(
                'file' => $stored['original_name'], 'total' => $result['total'], 'imported' => $result['imported'],
                'failed' => $result['failed'], 'duplicate' => $result['duplicate'], 'owners_created' => $result['owners_created'],
            ));
        }

        $preview = array();
        foreach ($result['rows'] as $row)
        {
            if ($row['status'] !== 'Imported' || count($preview) < 50)
            {
                $preview[] = array('line' => $row['line'], 'status' => $row['status'], 'reason' => $row['reason'],
                    'plot' => $row['data']['plot_no'], 'owner' => $row['data']['owner_name'], 'mobile' => $row['data']['mobile']);
            }
            if (count($preview) >= 200)
            {
                break;
            }
        }
        $this->session->set_flashdata('import_result', array(
            'dry_run'         => $dry_run,
            'batch_id'        => $batch_id,
            'total'           => $result['total'],
            'imported'        => $result['imported'],
            'failed'          => $result['failed'],
            'duplicate'       => $result['duplicate'],
            'owners_created'  => $result['owners_created'],
            'owners_extended' => $result['owners_extended'],
            'rows'            => $preview,
        ));
        $this->session->set_flashdata($result['failed'] + $result['duplicate'] > 0 ? 'warning' : 'success', $result['message']);
        redirect('imports');
    }

    /**
     * CSV template with example rows.
     */
    public function template(): void
    {
        $lines = array(
            array('Plot No', 'House No', 'Block', 'Street', 'Owner Name', 'Co-owner', 'Mobile', 'WhatsApp', 'Email', 'Owner Type', 'Built Status'),
            array('101', 'C-101', 'C', '3rd Cross Street', 'Sample Owner One', '', '9000000901', '9000000901', 'owner.one@example.com', 'Individual', 'Built'),
            array('102', '', 'C', '3rd Cross Street', 'Sample Owner One', '', '9000000901', '9000000901', 'owner.one@example.com', 'Individual', 'Not Built'),
            array('103', 'C-103', 'C', '3rd Cross Street', 'Sample Owner Two', 'Spouse Name', '9000000902', '', '', 'Joint', 'Built'),
        );
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="owner-import-template.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        foreach ($lines as $line)
        {
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }

    /**
     * Download the row-by-row result report of an import.
     */
    public function report(int $id = 0): void
    {
        $batch = $this->db->get_where('import_batches', array('id' => $id))->row_array();
        $path = $batch['error_file_path'] ?? '';
        if ( ! $batch || ! preg_match('#^imports/errors/import-\d+-[a-f0-9]{12}\.csv$#', (string) $path) || ! is_file(STORAGEPATH.$path))
        {
            show_404();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="import-'.$id.'-result.csv"');
        header('X-Content-Type-Options: nosniff');
        readfile(STORAGEPATH.str_replace('/', DIRECTORY_SEPARATOR, $path));
        exit;
    }
}
