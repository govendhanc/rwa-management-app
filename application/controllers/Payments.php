<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Payment collection, payment list/detail, cancellation and advance credits.
 */
class Payments extends Auth_Controller
{
    protected $permission_map = array(
        'index'        => 'payments.view',
        'view'         => 'payments.view',
        'advances'     => 'payments.view',
        'collect'      => 'payments.create',
        'search'       => 'payments.create',
        'owner_dues'   => 'payments.create',
        'store'        => 'payments.create',
        'success'      => 'payments.view',
        'apply_credit' => 'payments.create',
        'cancel'       => 'payments.cancel',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->model('payment_model');
        $this->load->library(array('form_validation', 'payment_engine'));
    }

    public function index(): void
    {
        $from = (string) $this->input->get('from');
        $to = (string) $this->input->get('to');
        $filters = array(
            'from'   => is_valid_date($from) ? $from : date('Y-m-01', strtotime('-2 months')),
            'to'     => is_valid_date($to) ? $to : date('Y-m-d'),
            'mode'   => in_array($this->input->get('mode'), (array) $this->config->item('payment_modes'), TRUE) ? (string) $this->input->get('mode') : '',
            'status' => in_array($this->input->get('status'), array('Active', 'Cancelled', 'Reversed'), TRUE) ? (string) $this->input->get('status') : '',
        );
        $payments = $this->payment_model->list_payments($filters);
        $total = 0;
        foreach ($payments as $p)
        {
            if ($p['status'] === 'Active')
            {
                $total += to_paise_signed($p['amount']);
            }
        }

        $this->render('payments/index', array(
            'page_title'  => 'Payments',
            'breadcrumbs' => array(array('label' => 'Payments')),
            'filters'     => $filters,
            'payments'    => $payments,
            'total'       => from_paise($total),
            'scripts'     => array('js/modules/payments.js'),
        ));
    }

    public function view(int $id = 0): void
    {
        $payment = $this->payment_model->find_full($id);
        if ($payment === NULL)
        {
            show_404();
        }
        $this->render('payments/view', array(
            'page_title'  => 'Payment #'.$id,
            'breadcrumbs' => array(array('label' => 'Payments', 'url' => 'payments'), array('label' => $payment['receipt_no'] ?: '#'.$id)),
            'payment'     => $payment,
            'lines'       => $this->payment_model->allocation_lines($id),
            'scripts'     => array('js/modules/payments.js'),
        ));
    }

    /**
     * Collect Payment screen. ?owner_id= preselects an owner (from the owner list/profile).
     */
    public function collect(): void
    {
        $owner = NULL;
        $owner_id = (int) $this->input->get('owner_id');
        if ($owner_id > 0)
        {
            $owner = $this->payment_model->owner_summary($owner_id);
        }
        $this->render('payments/collect', array(
            'page_title'    => 'Collect Payment',
            'breadcrumbs'   => array(array('label' => 'Payments', 'url' => 'payments'), array('label' => 'Collect Payment')),
            'owner'         => $owner,
            'request_token' => bin2hex(random_bytes(16)),
            'modes'         => (array) $this->config->item('payment_modes'),
            'scripts'       => array('js/modules/payment-collect.js'),
        ));
    }

    /**
     * AJAX GET: owner search by plot no, house no, name, owner ID or mobile.
     */
    public function search(): void
    {
        $this->require_ajax();
        $term = trim((string) $this->input->get('q', TRUE));
        $results = array();
        foreach ($this->payment_model->search_owners(mb_substr($term, 0, 60)) as $r)
        {
            $results[] = array(
                'id'          => (int) $r['id'],
                'owner_code'  => $r['owner_code'],
                'owner_name'  => $r['owner_name'],
                'mobile'      => $r['mobile'],
                'plots'       => $r['plots'],
                'house_nos'   => $r['house_nos'],
                'status'      => $r['status'],
                'outstanding' => money($r['outstanding']),
                'credit'      => to_paise_signed($r['advance_credit']) > 0 ? money($r['advance_credit']) : '',
            );
        }
        $this->json_success('OK', array('results' => $results));
    }

    /**
     * AJAX GET: an owner's open bills and balances for the payment form.
     */
    public function owner_dues(int $owner_id = 0): void
    {
        $this->require_ajax();
        $owner = $this->payment_model->owner_summary($owner_id);
        if ($owner === NULL)
        {
            $this->json_error('Owner not found.', 404);
        }
        $this->load->model('house_model');
        $houses = $this->house_model->for_owner($owner_id);
        $today = date('Y-m-d');
        $current_month = date('Y-m-01');
        $current = 0;
        $previous = 0;
        $bills = array();
        foreach ($this->payment_model->open_bills($owner_id) as $b)
        {
            $balance = to_paise_signed($b['balance_amount']);
            if ($b['period_start'] >= $current_month)
            {
                $current += $balance;
            }
            else
            {
                $previous += $balance;
            }
            $bills[] = array(
                'id'      => (int) $b['id'],
                'month'   => period_label((int) $b['billing_year'], (int) $b['billing_month']),
                'plot'    => 'Plot '.$b['plot_no'].($b['house_no'] ? ' - '.$b['house_no'] : ''),
                'amount'  => money($b['amount']),
                'paid'    => money(from_paise(to_paise_signed($b['paid_amount']) + to_paise_signed($b['waived_amount']))),
                'balance' => money($b['balance_amount']),
                'balance_value' => from_paise($balance),
                'status'  => $b['payment_status'],
                'overdue' => $b['due_date'] < $today,
            );
        }
        $credit = to_paise_signed($owner['advance_credit']);
        $this->json_success('OK', array(
            'owner' => array(
                'id'         => (int) $owner['id'],
                'owner_code' => $owner['owner_code'],
                'owner_name' => $owner['owner_name'],
                'mobile'     => $owner['mobile'],
                'status'     => $owner['status'],
                'plots'      => implode(', ', array_map(function ($h) { return $h['plot_no']; }, $houses)),
                'house_nos'  => implode(', ', array_map(function ($h) { return $h['house_no'] ?: '-'; }, $houses)),
                'last_payment' => $owner['last_payment_date'] ? fmt_date($owner['last_payment_date']) : 'Never',
            ),
            'bills'                => $bills,
            'previous_outstanding' => money(from_paise($previous)),
            'current_amount'       => money(from_paise($current)),
            'total_payable'        => money(from_paise($previous + $current)),
            'total_payable_value'  => from_paise($previous + $current),
            'credit'               => $credit > 0 ? money(from_paise($credit)) : '',
        ));
    }

    /**
     * POST: record the payment.
     */
    public function store(): void
    {
        $this->require_post();
        $v = $this->form_validation;
        $v->set_rules('owner_id', 'Owner', 'required|is_natural_no_zero|exists_in[owners.id]');
        $v->set_rules('payment_date', 'Payment Date', 'required|valid_date_ymd');
        $v->set_rules('amount', 'Payment Amount', 'trim|required|positive_amount');
        $v->set_rules('payment_mode', 'Payment Mode', 'required|in_config_list[payment_modes]');
        $v->set_rules('transaction_ref', 'Transaction Reference', 'trim|max_length[100]');
        $v->set_rules('remarks', 'Remarks', 'trim|max_length[500]');
        $v->set_rules('request_token', 'Form token', array('required', 'regex_match[/^[a-f0-9]{32}$/]'));

        $owner_id = (int) $this->input->post('owner_id');
        $back = 'payments/collect'.($owner_id > 0 ? '?owner_id='.$owner_id : '');

        if ( ! $v->run())
        {
            $this->respond_error(strip_tags(validation_errors(' ', ' ')), $back, $this->validation_errors_array(array('owner_id', 'payment_date', 'amount', 'payment_mode', 'transaction_ref', 'remarks')));
        }

        $date = (string) $this->input->post('payment_date');
        if ($date > date('Y-m-d'))
        {
            $this->respond_error('The payment date cannot be in the future.', $back, array('payment_date' => 'The payment date cannot be in the future.'));
        }
        if ($date < date('Y-m-d', strtotime('-1 year')))
        {
            $this->respond_error('The payment date is more than a year old. Please check the date.', $back, array('payment_date' => 'Too old.'));
        }
        $amount_paise = (int) to_paise((string) $this->input->post('amount'));
        if ($amount_paise > 100000000)
        {
            $this->respond_error('The amount looks too large. Please check it.', $back, array('amount' => 'Amount too large.'));
        }

        $bill_ids = $this->input->post('bill_ids');
        $bill_ids = is_array($bill_ids) ? array_values(array_filter(array_map('intval', $bill_ids))) : array();

        $result = $this->payment_engine->collect(array(
            'owner_id'        => $owner_id,
            'payment_date'    => $date,
            'amount'          => (string) $this->input->post('amount'),
            'payment_mode'    => (string) $this->input->post('payment_mode'),
            'transaction_ref' => trim((string) $this->input->post('transaction_ref', TRUE)),
            'remarks'         => trim((string) $this->input->post('remarks', TRUE)),
            'is_advance'      => $this->input->post('is_advance') === '1',
            'bill_ids'        => $this->input->post('allocation') === 'selected' ? $bill_ids : array(),
            'request_token'   => (string) $this->input->post('request_token'),
        ));

        if ( ! $result['success'])
        {
            $this->respond_error($result['message'], $back);
        }

        $this->session->set_flashdata(empty($result['duplicate']) ? 'success' : 'warning', $result['message']);
        $url = site_url('payments/success/'.$result['payment_id']);
        if ($this->input->is_ajax_request())
        {
            $this->json_success($result['message'], array('redirect' => $url, 'receipt_no' => $result['receipt_no']));
        }
        redirect($url);
    }

    /**
     * "Payment recorded successfully" page with receipt actions.
     */
    public function success(int $payment_id = 0): void
    {
        $payment = $this->payment_model->find_full($payment_id);
        if ($payment === NULL || empty($payment['receipt_id']))
        {
            show_404();
        }
        $this->load->model('receipt_model');
        $this->load->library('receipt_pdf');
        $receipt = $this->receipt_model->find_full((int) $payment['receipt_id']);
        $this->render('payments/success', array(
            'page_title'   => 'Payment Recorded',
            'breadcrumbs'  => array(array('label' => 'Payments', 'url' => 'payments'), array('label' => $payment['receipt_no'])),
            'payment'      => $payment,
            'receipt'      => $receipt,
            'receipt_html' => $this->receipt_pdf->html($receipt, 'a4'),
            'scripts'      => array('js/modules/receipts.js'),
        ));
    }

    /**
     * POST: cancel a payment (reason required). Nothing is deleted.
     */
    public function cancel(int $id = 0): void
    {
        $this->require_post();
        $reason = trim((string) $this->input->post('reason', TRUE));
        if (mb_strlen($reason) < 3)
        {
            $this->session->set_flashdata('error', 'Please give a reason for cancelling the payment.');
            redirect('payments/view/'.$id);
        }
        $result = $this->payment_engine->cancel($id, mb_substr($reason, 0, 255));
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect('payments/view/'.$id);
    }

    public function advances(): void
    {
        $this->render('payments/advances', array(
            'page_title'  => 'Advance Credits',
            'breadcrumbs' => array(array('label' => 'Payments', 'url' => 'payments'), array('label' => 'Advance Credits')),
            'owners'      => $this->payment_model->advances(),
            'scripts'     => array('js/modules/payments.js'),
        ));
    }

    /**
     * POST: apply an owner's advance credit to their open bills now.
     */
    public function apply_credit(int $owner_id = 0): void
    {
        $this->require_post();
        $result = $this->payment_engine->apply_credit($owner_id);
        $this->session->set_flashdata($result['success'] ? 'success' : 'warning', $result['message']);
        redirect('payments/advances');
    }

    /**
     * @param array<string, string> $errors
     */
    private function respond_error(string $message, string $back, array $errors = array()): void
    {
        if ($this->input->is_ajax_request())
        {
            $this->json_error($message, 422, $errors);
        }
        $this->session->set_flashdata('error', $message);
        redirect($back);
    }
}
