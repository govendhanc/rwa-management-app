<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Finance -> Opening Balance: record the association's verified bank + cash balance brought
 * forward (once), correct it through an audited replacement, and show the fund ledger.
 *
 * No receipt, payment or maintenance record is created for the opening balance.
 */
class Opening_balance extends Auth_Controller
{
    const DEFAULT_DATE = '2026-10-31';

    protected $permission_map = array(
        'index'   => 'finance.opening_balance',
        'save'    => 'finance.opening_balance',
        'correct' => 'finance.opening_balance',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->model('fund_model');
        $this->load->library('form_validation');
    }

    public function index(): void
    {
        $position = $this->fund_model->position();
        $active = $position['opening_row'];

        $this->render('opening_balance/index', array(
            'page_title'    => 'Opening Balance',
            'breadcrumbs'   => array(array('label' => 'Finance'), array('label' => 'Opening Balance')),
            'active'        => $active,
            'position'      => $position,
            'history'       => $this->fund_model->history(),
            'recorded'      => $this->fund_model->recorded_up_to($active !== NULL ? (string) $active['opening_date'] : self::DEFAULT_DATE),
            'default_date'  => self::DEFAULT_DATE,
            'request_token' => bin2hex(random_bytes(16)),
            'scripts'       => array('js/modules/opening-balance.js'),
        ));
    }

    /**
     * POST: record the first opening balance.
     */
    public function save(): void
    {
        $this->require_post();
        $this->set_rules(FALSE);
        if ( ! $this->form_validation->run())
        {
            $this->session->set_flashdata('error', strip_tags(validation_errors(' ', ' ')));
            redirect('opening-balance');
        }
        $result = $this->fund_model->record($this->collect());
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect('opening-balance');
    }

    /**
     * POST: replace the active opening balance with a corrected one (the original is kept).
     */
    public function correct(): void
    {
        $this->require_post();
        $this->set_rules(TRUE);
        if ( ! $this->form_validation->run())
        {
            $this->session->set_flashdata('error', strip_tags(validation_errors(' ', ' ')));
            redirect('opening-balance');
        }
        $result = $this->fund_model->correct(
            (int) $this->input->post('active_id'),
            $this->collect(),
            trim((string) $this->input->post('correction_reason', TRUE))
        );
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect('opening-balance');
    }

    /* ------------------------------------------------------------------ */

    private function set_rules(bool $correction): void
    {
        $v = $this->form_validation;
        $v->set_rules('opening_date', 'Opening Balance Date', 'trim|required|valid_date_ymd');
        $v->set_rules('bank_amount', 'Opening Bank Balance', 'trim|required|valid_amount', array('valid_amount' => 'Enter the bank balance as an amount of 0 or more (up to 2 decimals).'));
        $v->set_rules('cash_amount', 'Opening Cash Balance', 'trim|required|valid_amount', array('valid_amount' => 'Enter the cash balance as an amount of 0 or more (up to 2 decimals).'));
        $v->set_rules('remarks', 'Remarks / Reference', 'trim|max_length[500]');
        $v->set_rules('request_token', 'Form token', array('required', 'regex_match[/^[a-f0-9]{32}$/]'));
        if ($correction)
        {
            $v->set_rules('active_id', 'Opening balance', 'required|is_natural_no_zero');
            $v->set_rules('correction_reason', 'Reason for correction', 'trim|required|min_length[5]|max_length[255]');
        }
    }

    /**
     * @return array{opening_date: string, bank_amount: string, cash_amount: string, remarks: ?string, request_token: string}
     */
    private function collect(): array
    {
        $remarks = trim((string) $this->input->post('remarks', TRUE));
        return array(
            'opening_date'  => (string) $this->input->post('opening_date'),
            'bank_amount'   => from_paise((int) to_paise((string) $this->input->post('bank_amount'))),
            'cash_amount'   => from_paise((int) to_paise((string) $this->input->post('cash_amount'))),
            'remarks'       => $remarks !== '' ? $remarks : NULL,
            'request_token' => (string) $this->input->post('request_token'),
        );
    }
}
