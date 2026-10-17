<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Finance -> Bank & Cash: current bank / cash balances, the fund ledger (every money movement
 * with running balances) and bank <-> cash transfers (withdrawals and deposits).
 *
 * Money received and spent is recorded in the existing modules (Payments, Other Income, Expenses);
 * this page only adds the transfers between the bank account and cash in hand.
 */
class Funds extends Auth_Controller
{
    protected $permission_map = array(
        'index'           => 'transfers.view',
        'transfer'        => 'transfers.manage',
        'cancel_transfer' => 'transfers.manage',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('fund_model', 'transfer_model'));
        $this->load->library('form_validation');
    }

    public function index(): void
    {
        $ob = $this->fund_model->active_opening();
        $default_from = $ob !== NULL ? date('Y-m-d', strtotime($ob['opening_date'].' +1 day')) : date('Y-04-01', strtotime((int) date('n') >= 4 ? 'now' : '-1 year'));
        $from = (string) $this->input->get('from');
        $to = (string) $this->input->get('to');
        $from = is_valid_date($from) ? $from : $default_from;
        $to = is_valid_date($to) ? $to : $this->fund_model->last_entry_date();

        $this->render('funds/index', array(
            'page_title'    => 'Bank & Cash',
            'breadcrumbs'   => array(array('label' => 'Finance'), array('label' => 'Bank & Cash')),
            'position'      => $this->fund_model->position(),
            'ledger'        => $this->fund_model->ledger($from, $to),
            'transfers'     => $this->transfer_model->list_all(),
            'directions'    => Transfer_model::DIRECTIONS,
            'opening'       => $ob,
            'request_token' => bin2hex(random_bytes(16)),
            'scripts'       => array('js/modules/finance.js'),
        ));
    }

    /**
     * POST: record a withdrawal (bank to cash) or deposit (cash to bank).
     */
    public function transfer(): void
    {
        $this->require_post();
        $v = $this->form_validation;
        $v->set_rules('transfer_date', 'Date', 'trim|required|valid_date_ymd');
        $v->set_rules('direction', 'Type', 'required|in_list['.implode(',', array_keys(Transfer_model::DIRECTIONS)).']');
        $v->set_rules('amount', 'Amount', 'trim|required|positive_amount', array('positive_amount' => 'The amount must be greater than zero (up to 2 decimals).'));
        $v->set_rules('description', 'Description', 'trim|required|max_length[255]');
        $v->set_rules('reference', 'Transaction Reference', 'trim|max_length[100]');
        $v->set_rules('remarks', 'Remarks', 'trim|max_length[500]');
        $v->set_rules('request_token', 'Form token', array('required', 'regex_match[/^[a-f0-9]{32}$/]'));
        if ( ! $v->run())
        {
            $this->session->set_flashdata('error', strip_tags(validation_errors(' ', ' ')));
            redirect('funds');
        }

        $date = (string) $this->input->post('transfer_date');
        $error = $this->date_error($date);
        if ($error !== NULL)
        {
            $this->session->set_flashdata('error', $error);
            redirect('funds');
        }

        $post = function (string $key): ?string {
            $value = trim((string) $this->input->post($key, TRUE));
            return $value !== '' ? $value : NULL;
        };
        $result = $this->transfer_model->record(array(
            'transfer_date' => $date,
            'direction'     => (string) $this->input->post('direction'),
            'amount'        => from_paise((int) to_paise((string) $this->input->post('amount'))),
            'description'   => (string) $post('description'),
            'reference'     => $post('reference'),
            'remarks'       => $post('remarks'),
            'request_token' => (string) $this->input->post('request_token'),
        ));
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect('funds');
    }

    /**
     * POST: cancel a transfer with a reason (kept on record, excluded from balances).
     */
    public function cancel_transfer(int $id = 0): void
    {
        $this->require_post();
        $reason = trim((string) $this->input->post('reason', TRUE));
        if (mb_strlen($reason) < 3)
        {
            $this->session->set_flashdata('error', 'Please give a reason for cancelling the transfer.');
            redirect('funds');
        }
        $result = $this->transfer_model->cancel($id, $reason);
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect('funds');
    }

    /* ------------------------------------------------------------------ */

    private function date_error(string $date): ?string
    {
        if ($date > date('Y-m-d'))
        {
            return 'The transfer date cannot be in the future.';
        }
        $ob = $this->fund_model->active_opening();
        if ($ob !== NULL && $date <= $ob['opening_date'])
        {
            return 'The transfer date must be after the opening balance date ('.fmt_date($ob['opening_date']).') - earlier movements are already inside the verified opening balance.';
        }
        return NULL;
    }
}
