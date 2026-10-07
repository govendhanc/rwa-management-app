<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Monthly maintenance: list, generate (preview + generate), bill detail,
 * waiver/discount, cancel/restore, and pending reminders (WhatsApp Click-to-Chat).
 */
class Maintenance extends Auth_Controller
{
    protected $permission_map = array(
        'index'              => 'maintenance.view',
        'view'               => 'maintenance.view',
        'generate'           => 'maintenance.generate',
        'preview'            => 'maintenance.generate',
        'waive'              => 'maintenance.waive',
        'reverse_adjustment' => 'maintenance.waive',
        'cancel'             => 'maintenance.waive',
        'restore'            => 'maintenance.waive',
        'reminders'          => 'whatsapp.send',
        'reminder_link'      => 'whatsapp.send',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('maintenance_model', 'rate_model'));
        $this->load->library(array('form_validation', 'maintenance_engine'));
    }

    /* ==================================================================
     * List
     * ================================================================ */

    public function index(): void
    {
        $batches = $this->maintenance_model->batches();
        $period = (string) $this->input->get('period');
        if ($period === '')
        {
            $period = ! empty($batches) ? sprintf('%04d-%02d', $batches[0]['billing_year'], $batches[0]['billing_month']) : date('Y-m');
        }

        $filters = array(
            'status' => (string) $this->input->get('status'),
            'block'  => (string) $this->input->get('block'),
        );
        if ( ! in_array($filters['status'], array('', 'Unpaid', 'Pending', 'Partially Paid', 'Paid', 'Waived', 'Cancelled'), TRUE))
        {
            $filters['status'] = '';
        }

        $open_mode = $period === 'open';
        $year = $month = NULL;
        if ( ! $open_mode)
        {
            list($year, $month) = $this->parse_period($period) ?? array((int) date('Y'), (int) date('n'));
            $period = sprintf('%04d-%02d', $year, $month);
        }

        $this->render('maintenance/index', array(
            'page_title'  => 'Maintenance',
            'breadcrumbs' => array(array('label' => 'Maintenance')),
            'period'      => $period,
            'open_mode'   => $open_mode,
            'year'        => $year,
            'month'       => $month,
            'filters'     => $filters,
            'batches'     => $batches,
            'blocks'      => $this->maintenance_model->blocks(),
            'bills'       => $this->maintenance_model->list_bills($year, $month, $filters),
            'summary'     => $open_mode ? NULL : $this->maintenance_model->period_summary($year, $month),
            'scripts'     => array('js/modules/maintenance.js'),
        ));
    }

    public function view(int $id = 0): void
    {
        $bill = $this->maintenance_model->find_full($id);
        if ($bill === NULL)
        {
            show_404();
        }
        $this->render('maintenance/view', array(
            'page_title'  => 'Bill #'.$bill['id'],
            'breadcrumbs' => array(
                array('label' => 'Maintenance', 'url' => 'maintenance?period='.sprintf('%04d-%02d', $bill['billing_year'], $bill['billing_month'])),
                array('label' => 'Plot '.$bill['plot_no'].' - '.period_label((int) $bill['billing_year'], (int) $bill['billing_month'], TRUE)),
            ),
            'bill'        => $bill,
            'allocations' => $this->maintenance_model->allocations($id),
            'adjustments' => $this->maintenance_model->adjustments($id),
            'scripts'     => array('js/modules/maintenance.js'),
        ));
    }

    /* ==================================================================
     * Generation
     * ================================================================ */

    public function generate(): void
    {
        if ($this->input->method() === 'post')
        {
            list($ok, $year, $month, $due_date, $error) = $this->validate_generation_input();
            if ( ! $ok)
            {
                $this->session->set_flashdata('error', $error);
                redirect('maintenance/generate');
            }
            $result = $this->maintenance_engine->generate($year, $month, $due_date);
            $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
            redirect($result['success'] ? 'maintenance?period='.sprintf('%04d-%02d', $year, $month) : 'maintenance/generate?period='.sprintf('%04d-%02d', $year, $month));
        }

        $suggested = $this->suggested_period();
        $requested = $this->parse_period((string) $this->input->get('period'));
        list($year, $month) = $requested ?? $suggested;
        $due_day = max(1, min(28, (int) association('default_due_day') ?: 10));

        $this->render('maintenance/generate', array(
            'page_title'  => 'Generate Monthly Maintenance',
            'breadcrumbs' => array(array('label' => 'Maintenance', 'url' => 'maintenance'), array('label' => 'Generate')),
            'period'      => sprintf('%04d-%02d', $year, $month),
            'max_period'  => date('Y-m', strtotime(date('Y-m-01').' +1 month')),
            'due_date'    => sprintf('%04d-%02d-%02d', $year, $month, $due_day),
            'due_day'     => $due_day,
            'batches'     => array_slice($this->maintenance_model->batches(), 0, 6),
            'scripts'     => array('js/modules/maintenance-generate.js'),
        ));
    }

    /**
     * AJAX POST: preview for a month (no writes).
     */
    public function preview(): void
    {
        $this->require_ajax();
        $this->require_post();
        list($ok, $year, $month, , $error) = $this->validate_generation_input(FALSE);
        if ( ! $ok)
        {
            $this->json_error($error);
        }
        $preview = $this->maintenance_engine->preview($year, $month);

        $rows = array();
        foreach ($preview['rows'] as $r)
        {
            $rows[] = array(
                'plot'     => 'Plot '.$r['plot_no'].($r['house_no'] ? ' - '.$r['house_no'] : ''),
                'block'    => $r['block'],
                'owner'    => $r['owner_name'] !== NULL ? $r['owner_code'].' - '.$r['owner_name'] : '-',
                'category' => $r['plot_category'],
                'amount'   => money($r['amount']),
                'action'   => $r['action'],
                'reason'   => $r['reason'],
            );
        }
        $by_category = array();
        foreach ($preview['totals']['by_category'] as $category => $c)
        {
            $by_category[] = array('category' => $category, 'plots' => $c['plots'], 'rate' => money($c['rate']), 'amount' => money($c['amount']));
        }

        $this->json_success('OK', array(
            'period_label'      => $preview['period_label'],
            'batch_exists'      => $preview['batch_exists'],
            'already_generated' => $preview['already_generated'],
            'errors'            => $preview['errors'],
            'plots'             => $preview['totals']['plots'],
            'owners'            => $preview['totals']['owners'],
            'amount'            => money($preview['totals']['amount']),
            'by_category'       => $by_category,
            'skipped'           => $preview['skipped'],
            'already_billed'    => $preview['already_billed'],
            'rows'              => $rows,
        ));
    }

    /* ==================================================================
     * Adjustments & cancellation (POST, redirect back to the bill)
     * ================================================================ */

    public function waive(int $id = 0): void
    {
        $this->require_post();
        $this->form_validation->set_rules('adjustment_type', 'Type', 'required|in_list[Waiver,Discount]');
        $this->form_validation->set_rules('amount', 'Amount', 'trim|required|positive_amount');
        $this->form_validation->set_rules('adjustment_date', 'Date', 'required|valid_date_ymd');
        $this->form_validation->set_rules('reason', 'Reason', 'trim|required|min_length[3]|max_length[255]');
        if ( ! $this->form_validation->run())
        {
            $this->session->set_flashdata('error', strip_tags(validation_errors(' ', ' ')));
            redirect('maintenance/view/'.$id);
        }
        $result = $this->maintenance_engine->waive(
            $id,
            (string) $this->input->post('adjustment_type'),
            (string) $this->input->post('amount'),
            (string) $this->input->post('adjustment_date'),
            trim((string) $this->input->post('reason', TRUE))
        );
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect('maintenance/view/'.$id);
    }

    public function reverse_adjustment(int $adjustment_id = 0): void
    {
        $this->require_post();
        $adj = $this->db->get_where('maintenance_adjustments', array('id' => $adjustment_id), 1)->row_array();
        if ( ! $adj)
        {
            show_404();
        }
        $reason = trim((string) $this->input->post('reason', TRUE));
        if (mb_strlen($reason) < 3)
        {
            $this->session->set_flashdata('error', 'Please give a reason for reversing the adjustment.');
            redirect('maintenance/view/'.$adj['maintenance_id']);
        }
        $result = $this->maintenance_engine->reverse_adjustment($adjustment_id, mb_substr($reason, 0, 255));
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect('maintenance/view/'.$adj['maintenance_id']);
    }

    public function cancel(int $id = 0): void
    {
        $this->require_post();
        $reason = trim((string) $this->input->post('reason', TRUE));
        if (mb_strlen($reason) < 3)
        {
            $this->session->set_flashdata('error', 'Please give a reason for cancelling the bill.');
            redirect('maintenance/view/'.$id);
        }
        $result = $this->maintenance_engine->cancel($id, mb_substr($reason, 0, 150));
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect('maintenance/view/'.$id);
    }

    public function restore(int $id = 0): void
    {
        $this->require_post();
        $result = $this->maintenance_engine->restore($id);
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect('maintenance/view/'.$id);
    }

    /* ==================================================================
     * Reminders
     * ================================================================ */

    public function reminders(): void
    {
        $batches = $this->maintenance_model->batches();
        $parsed = $this->parse_period((string) $this->input->get('period'));
        if ($parsed === NULL)
        {
            $parsed = ! empty($batches) ? array((int) $batches[0]['billing_year'], (int) $batches[0]['billing_month']) : array((int) date('Y'), (int) date('n'));
        }
        list($year, $month) = $parsed;

        $this->render('maintenance/reminders', array(
            'page_title'  => 'Pending Reminders',
            'breadcrumbs' => array(array('label' => 'Maintenance', 'url' => 'maintenance'), array('label' => 'Pending Reminders')),
            'period'      => sprintf('%04d-%02d', $year, $month),
            'year'        => $year,
            'month'       => $month,
            'batches'     => $batches,
            'summary'     => $this->maintenance_model->period_summary($year, $month),
            'owners'      => $this->maintenance_model->reminder_candidates($year, $month),
            'wa_mode'     => (string) sys_setting('whatsapp_mode', 'click_to_chat'),
            'scripts'     => array('js/modules/maintenance.js'),
        ));
    }

    /**
     * AJAX POST: prepare a WhatsApp reminder for one owner and return the Click-to-Chat URL.
     */
    public function reminder_link(int $owner_id = 0): void
    {
        $this->require_ajax();
        $this->require_post();
        $parsed = $this->parse_period((string) $this->input->post('period'));
        if ($parsed === NULL)
        {
            $this->json_error('Invalid month.');
        }
        list($year, $month) = $parsed;

        $candidate = NULL;
        foreach ($this->maintenance_model->reminder_candidates($year, $month) as $row)
        {
            if ((int) $row['owner_id'] === $owner_id)
            {
                $candidate = $row;
                break;
            }
        }
        if ($candidate === NULL)
        {
            $this->json_error('This owner has no pending maintenance for '.period_label($year, $month).'.');
        }

        $this->load->library('whatsapp_service');
        $result = $this->whatsapp_service->deliver('maintenance_reminder', (string) ($candidate['whatsapp_no'] ?: $candidate['mobile']), array(
            'OWNER_NAME' => $candidate['owner_name'],
            'PLOT_NO'    => $candidate['plots'],
            'HOUSE_NO'   => $candidate['house_nos'],
            'MONTH'      => month_name($month),
            'YEAR'       => (string) $year,
            'AMOUNT'     => money($candidate['month_due'], FALSE),
            'BALANCE'    => money($candidate['total_due'], FALSE),
            'DUE_DATE'   => fmt_date($candidate['due_date']),
        ), array('owner_id' => $owner_id, 'maintenance_id' => (int) $candidate['first_bill_id']));

        if ( ! $result['success'])
        {
            $this->json_error($result['message']);
        }
        $this->audit->log($result['mode'] === 'cloud_api' ? 'WhatsApp Reminder Sent' : 'WhatsApp Reminder Prepared', 'whatsapp', $owner_id, NULL, array('period' => sprintf('%04d-%02d', $year, $month), 'total_due' => $candidate['total_due']));
        $this->json_success($result['message'], array(
            'mode'       => $result['mode'],
            'url'        => $result['url'] ?? NULL,
            'text'       => $result['text'] ?? '',
            'sent_label' => fmt_datetime(date('Y-m-d H:i:s')),
        ));
    }

    /* ------------------------------------------------------------------ */

    /**
     * @return array{0: int, 1: int}|null
     */
    private function parse_period(string $period): ?array
    {
        if ( ! preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $period, $m))
        {
            return NULL;
        }
        return array((int) $m[1], (int) $m[2]);
    }

    /**
     * The month after the latest generated month (or this month), never later than next month.
     *
     * @return array{0: int, 1: int}
     */
    private function suggested_period(): array
    {
        $current = date('Y-m-01');
        $row = $this->db->query("SELECT MAX(CONCAT(billing_year, '-', LPAD(billing_month, 2, '0'), '-01')) AS last FROM maintenance_batches")->row_array();
        $candidate = $current;
        if ( ! empty($row['last']) && $row['last'] >= $current)
        {
            $candidate = date('Y-m-01', strtotime($row['last'].' +1 month'));
        }
        $max = date('Y-m-01', strtotime($current.' +1 month'));
        if ($candidate > $max)
        {
            $candidate = $max;
        }
        return array((int) substr($candidate, 0, 4), (int) substr($candidate, 5, 2));
    }

    /**
     * @return array{0: bool, 1: int, 2: int, 3: string, 4: string}
     */
    private function validate_generation_input(bool $need_due_date = TRUE): array
    {
        $parsed = $this->parse_period((string) $this->input->post('period'));
        if ($parsed === NULL)
        {
            return array(FALSE, 0, 0, '', 'Please choose a valid month.');
        }
        list($year, $month) = $parsed;
        $period_start = sprintf('%04d-%02d-01', $year, $month);
        $max = date('Y-m-01', strtotime(date('Y-m-01').' +1 month'));
        if ($period_start > $max)
        {
            return array(FALSE, $year, $month, '', 'Maintenance can be generated at most one month in advance ('.date('F Y', strtotime($max)).').');
        }

        $due_date = (string) $this->input->post('due_date');
        if ($need_due_date)
        {
            if ( ! is_valid_date($due_date))
            {
                return array(FALSE, $year, $month, '', 'Please enter a valid due date.');
            }
            if ($due_date < $period_start)
            {
                return array(FALSE, $year, $month, '', 'The due date cannot be before the start of '.period_label($year, $month).'.');
            }
            if ($due_date > date('Y-m-d', strtotime($period_start.' +3 months')))
            {
                return array(FALSE, $year, $month, '', 'The due date is too far after the billing month.');
            }
        }
        return array(TRUE, $year, $month, $due_date, '');
    }
}
