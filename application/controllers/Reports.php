<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Reports: Outstanding, Monthly Collection, Owner Statement (with PDF), Income & Expense.
 * Tables export to Excel / CSV / PDF / Print through DataTables; the owner statement also has a server PDF.
 */
class Reports extends Auth_Controller
{
    protected $permission_map = array(
        'index'          => 'reports.view',
        'outstanding'    => 'reports.view',
        'collection'     => 'reports.view',
        'statement'      => 'reports.view',
        'statement_pdf'  => 'reports.view',
        'income_expense' => 'reports.financial',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('report_model', 'owner_model', 'rate_model'));
    }

    public function index(): void
    {
        redirect('reports/outstanding');
    }

    public function outstanding(): void
    {
        $period = (string) $this->input->get('period');
        if ( ! preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/', $period))
        {
            $period = date('Y-m');
        }
        $filters = array(
            'as_of'        => $period.'-01',
            'owner_id'     => (int) $this->input->get('owner_id'),
            'plot'         => trim((string) $this->input->get('plot', TRUE)),
            'owner_status' => in_array($this->input->get('owner_status'), array('Active', 'Inactive'), TRUE) ? (string) $this->input->get('owner_status') : '',
            'only_due'     => $this->input->get('only_due') !== '0',
        );

        $rates = $this->rate_model->rates_for_month();
        $rows = $this->report_model->outstanding($filters);
        $totals = array('total_due' => 0, 'total_waived' => 0, 'total_paid' => 0, 'outstanding' => 0, 'advance_credit' => 0);
        foreach ($rows as &$r)
        {
            $monthly = (int) $r['constructed_plots'] * to_paise_signed($rates['Constructed']['amount'] ?? '0')
                + (int) $r['vacant_plots'] * to_paise_signed($rates['Vacant Plot']['amount'] ?? '0');
            $r['monthly_maintenance'] = from_paise($monthly);
            foreach (array_keys($totals) as $k)
            {
                $totals[$k] += to_paise_signed($r[$k]);
            }
        }
        unset($r);

        $this->render('reports/outstanding', array(
            'page_title'  => 'Outstanding Report',
            'breadcrumbs' => array(array('label' => 'Reports'), array('label' => 'Outstanding')),
            'period'      => $period,
            'filters'     => $filters,
            'rows'        => $rows,
            'totals'      => array_map('from_paise', $totals),
            'owners'      => $this->owner_model->active_options(),
            'scripts'     => array('js/modules/reports.js'),
        ));
    }

    public function collection(): void
    {
        $years = $this->report_model->billing_years();
        $year = (int) $this->input->get('year');
        if ( ! in_array($year, $years, TRUE))
        {
            $year = $years[0];
        }
        $rows = $this->report_model->collection($year);
        $t = array('total_maintenance' => 0, 'total_collected' => 0, 'total_waived' => 0, 'total_outstanding' => 0);
        foreach ($rows as $r)
        {
            foreach (array_keys($t) as $k)
            {
                $t[$k] += to_paise_signed($r[$k]);
            }
        }
        $due = $t['total_maintenance'] - $t['total_waived'];
        $this->render('reports/collection', array(
            'page_title'  => 'Monthly Collection Report',
            'breadcrumbs' => array(array('label' => 'Reports'), array('label' => 'Monthly Collection')),
            'year'        => $year,
            'years'       => $years,
            'rows'        => $rows,
            'totals'      => array_map('from_paise', $t),
            'total_pct'   => $due > 0 ? round($t['total_collected'] * 100 / $due, 2) : 0,
            'scripts'     => array('js/modules/reports.js'),
        ));
    }

    public function statement(): void
    {
        list($owner, $from, $to) = $this->statement_params();
        $data = array(
            'page_title'  => 'Owner Statement',
            'breadcrumbs' => array(array('label' => 'Reports'), array('label' => 'Owner Statement')),
            'owners'      => $this->owner_model->active_options() + $this->inactive_owner_options(),
            'owner'       => $owner,
            'from'        => $from,
            'to'          => $to,
            'statement'   => $owner !== NULL ? $this->report_model->statement((int) $owner['id'], $from, $to) : NULL,
            'scripts'     => array('js/modules/reports.js'),
        );
        if ($owner !== NULL)
        {
            $this->load->library('pdf_renderer');
            $data['document'] = $this->load->view('reports/statement_document', array(
                'owner' => $owner, 'from' => $from, 'to' => $to, 'statement' => $data['statement'], 'for_pdf' => FALSE, 'logo' => $this->pdf_renderer->logo_data_uri(),
            ), TRUE);
        }
        $this->render('reports/statement', $data);
    }

    public function statement_pdf(): void
    {
        list($owner, $from, $to) = $this->statement_params();
        if ($owner === NULL)
        {
            show_404();
        }
        $this->load->library('pdf_renderer');
        $html = $this->load->view('reports/statement_document', array(
            'owner' => $owner, 'from' => $from, 'to' => $to,
            'statement' => $this->report_model->statement((int) $owner['id'], $from, $to),
            'for_pdf' => TRUE, 'logo' => $this->pdf_renderer->logo_data_uri(),
        ), TRUE);
        $bytes = $this->pdf_renderer->render($html, 'A4', 'portrait', 'Statement '.$owner['owner_code']);
        $this->audit->log('Owner Statement Downloaded', 'reports', $owner['id'], NULL, array('from' => $from, 'to' => $to));
        $this->pdf_renderer->send($bytes, 'Statement-'.$owner['owner_code'].'-'.$from.'-to-'.$to.'.pdf', $this->input->get('inline') === '1');
    }

    /**
     * Income & expense: ?type=month&month=YYYY-MM | type=year&year=YYYY | type=fy&fy=YYYY (Apr-Mar) | type=custom&from&to
     */
    public function income_expense(): void
    {
        $type = (string) $this->input->get('type');
        if ( ! in_array($type, array('month', 'year', 'fy', 'custom'), TRUE))
        {
            $type = 'month';
        }
        $month = preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/', (string) $this->input->get('month')) ? (string) $this->input->get('month') : date('Y-m');
        $year = (int) $this->input->get('year') ?: (int) date('Y');
        $fy_default = (int) date('n') >= 4 ? (int) date('Y') : (int) date('Y') - 1;
        $fy = (int) $this->input->get('fy') ?: $fy_default;
        $year = max(2000, min(2100, $year));
        $fy = max(2000, min(2100, $fy));

        switch ($type)
        {
            case 'year':
                $from = $year.'-01-01';
                $to = $year.'-12-31';
                $label = 'Year '.$year;
                break;
            case 'fy':
                $from = $fy.'-04-01';
                $to = ($fy + 1).'-03-31';
                $label = 'Financial Year '.$fy.'-'.substr((string) ($fy + 1), 2);
                break;
            case 'custom':
                $from = (string) $this->input->get('from');
                $to = (string) $this->input->get('to');
                if ( ! is_valid_date($from) || ! is_valid_date($to) || $from > $to)
                {
                    $from = date('Y-m-01');
                    $to = date('Y-m-d');
                }
                $label = fmt_date($from).' to '.fmt_date($to);
                break;
            default:
                $from = $month.'-01';
                $to = date('Y-m-t', strtotime($from));
                $label = date('F Y', strtotime($from));
        }

        $this->render('reports/income_expense', array(
            'page_title'  => 'Income & Expense',
            'breadcrumbs' => array(array('label' => 'Reports'), array('label' => 'Income & Expense')),
            'type'        => $type,
            'month'       => $month,
            'year'        => $year,
            'fy'          => $fy,
            'from'        => $from,
            'to'          => $to,
            'label'       => $label,
            'report'      => $this->report_model->income_expense($from, $to),
            'scripts'     => array('js/modules/reports.js'),
        ));
    }

    /* ------------------------------------------------------------------ */

    /**
     * Owner and date range for the statement. Default range: the owner's first bill month to today.
     *
     * @return array{0: array<string, mixed>|null, 1: string, 2: string}
     */
    private function statement_params(): array
    {
        $owner = NULL;
        $owner_id = (int) $this->input->get('owner_id');
        if ($owner_id > 0)
        {
            $owner = $this->owner_model->find_active_record($owner_id);
            if ($owner !== NULL)
            {
                $owner['plots'] = implode(', ', array_column($this->db->select('plot_no')->where('owner_id', $owner_id)->order_by('CAST(plot_no AS UNSIGNED)', '', FALSE)->get('houses')->result_array(), 'plot_no'));
            }
        }
        $from = (string) $this->input->get('from');
        $to = (string) $this->input->get('to');
        if ( ! is_valid_date($from))
        {
            $first = $owner !== NULL ? $this->db->query("SELECT MIN(period_start) AS d FROM maintenance WHERE owner_id = ? AND record_status = 'Active'", array($owner['id']))->row()->d : NULL;
            $from = $first ?: date('Y-04-01', strtotime((int) date('n') >= 4 ? 'now' : '-1 year'));
        }
        if ( ! is_valid_date($to))
        {
            $to = date('Y-m-d');
        }
        if ($from > $to)
        {
            list($from, $to) = array($to, $from);
        }
        return array($owner, $from, $to);
    }

    /**
     * Inactive owners (still selectable for statements).
     *
     * @return array<int, string>
     */
    private function inactive_owner_options(): array
    {
        $options = array();
        foreach ($this->db->select('id, owner_code, owner_name')->where('is_deleted', 0)->where('status', 'Inactive')->order_by('owner_name')->get('owners')->result_array() as $o)
        {
            $options[(int) $o['id']] = $o['owner_code'].' - '.$o['owner_name'].' (inactive)';
        }
        return $options;
    }
}
