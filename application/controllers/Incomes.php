<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Other income (interest, donations, hall rent ...) - the "+ Other Income" line of the
 * Income & Expense report. Never deleted: cancelled with a reason.
 */
class Incomes extends Auth_Controller
{
    protected $permission_map = array(
        'index'  => 'incomes.view',
        'create' => 'incomes.create',
        'edit'   => 'incomes.edit',
        'cancel' => 'incomes.cancel',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('income_model', 'sequence_model'));
        $this->load->library('form_validation');
    }

    public function index(): void
    {
        $from = (string) $this->input->get('from');
        $to = (string) $this->input->get('to');
        $filters = array(
            'from'   => is_valid_date($from) ? $from : date('Y-m-01', strtotime('-11 months')),
            'to'     => is_valid_date($to) ? $to : date('Y-m-d'),
            'status' => in_array($this->input->get('status'), array('Active', 'Cancelled'), TRUE) ? (string) $this->input->get('status') : '',
        );
        $incomes = $this->income_model->list_incomes($filters);
        $total = 0;
        foreach ($incomes as $i)
        {
            $total += $i['status'] === 'Active' ? to_paise_signed($i['amount']) : 0;
        }
        $this->render('incomes/index', array(
            'page_title'  => 'Other Income',
            'breadcrumbs' => array(array('label' => 'Finance'), array('label' => 'Other Income')),
            'filters'     => $filters,
            'incomes'     => $incomes,
            'total'       => from_paise($total),
            'scripts'     => array('js/modules/finance.js'),
        ));
    }

    public function create(): void
    {
        $income = array('id' => NULL, 'income_code' => '', 'income_date' => date('Y-m-d'), 'source' => '', 'description' => '', 'amount' => '', 'payment_mode' => 'Bank Transfer', 'reference' => '', 'remarks' => '');
        if ($this->input->method() === 'post')
        {
            $this->set_rules();
            if ($this->form_validation->run() && ($error = $this->date_error()) === NULL)
            {
                $data = $this->collect_input();
                $this->db->trans_begin();
                try
                {
                    $year = (int) substr($data['income_date'], 0, 4);
                    $data['income_code'] = $this->sequence_model->formatted('INCOME', 'INC-'.$year.'-', 6, $year);
                    $data['status'] = 'Active';
                    $data['created_by'] = user_id();
                    $data['updated_by'] = user_id();
                    $id = $this->income_model->insert($data);
                    if ($id === 0 || $this->db->trans_status() === FALSE)
                    {
                        throw new RuntimeException('insert failed');
                    }
                    $this->audit->log('Income Added', 'incomes', $id, NULL, $data);
                    $this->db->trans_commit();
                    $this->session->set_flashdata('success', 'Income '.$data['income_code'].' of '.money($data['amount']).' recorded.');
                    redirect('incomes');
                }
                catch (Throwable $e)
                {
                    $this->db->trans_rollback();
                    log_message('error', 'Income save failed: '.$e->getMessage());
                    $this->data['form_error'] = 'The income could not be saved. Please try again.';
                }
            }
            elseif (isset($error))
            {
                $this->data['form_error'] = $error;
            }
        }
        $this->render_form($income, 'Add Other Income');
    }

    public function edit(int $id = 0): void
    {
        $income = $this->income_model->find($id);
        if ($income === NULL)
        {
            show_404();
        }
        if ($income['status'] !== 'Active')
        {
            $this->session->set_flashdata('error', 'A cancelled entry cannot be edited.');
            redirect('incomes');
        }
        if ($this->input->method() === 'post')
        {
            $this->set_rules();
            if ($this->form_validation->run() && ($error = $this->date_error()) === NULL)
            {
                $data = $this->collect_input() + array('updated_by' => user_id());
                $this->income_model->update($id, $data);
                $this->audit->log('Income Updated', 'incomes', $id, $income, $data);
                $this->session->set_flashdata('success', 'Income '.$income['income_code'].' updated.');
                redirect('incomes');
            }
            elseif (isset($error))
            {
                $this->data['form_error'] = $error;
            }
        }
        $this->render_form($income, 'Edit Income '.$income['income_code']);
    }

    public function cancel(int $id = 0): void
    {
        $this->require_post();
        $income = $this->income_model->find($id);
        $reason = trim((string) $this->input->post('reason', TRUE));
        if ($income === NULL)
        {
            show_404();
        }
        if ($income['status'] !== 'Active' || mb_strlen($reason) < 3)
        {
            $this->session->set_flashdata('error', $income['status'] !== 'Active' ? 'This entry is already cancelled.' : 'Please give a reason for cancelling.');
            redirect('incomes');
        }
        $this->income_model->update($id, array('status' => 'Cancelled', 'remarks' => mb_substr(trim(($income['remarks'] ? $income['remarks'].' | ' : '').'Cancelled: '.$reason), 0, 500), 'updated_by' => user_id()));
        $this->audit->log('Income Cancelled', 'incomes', $id, array('status' => 'Active'), array('status' => 'Cancelled', 'reason' => $reason));
        $this->session->set_flashdata('success', 'Income '.$income['income_code'].' cancelled.');
        redirect('incomes');
    }

    private function set_rules(): void
    {
        $v = $this->form_validation;
        $v->set_rules('income_date', 'Date', 'required|valid_date_ymd');
        $v->set_rules('source', 'Source', 'trim|required|max_length[80]');
        $v->set_rules('description', 'Description', 'trim|max_length[255]');
        $v->set_rules('amount', 'Amount', 'trim|required|positive_amount');
        $v->set_rules('payment_mode', 'Payment Mode', 'required|in_config_list[payment_modes]');
        $v->set_rules('reference', 'Reference', 'trim|max_length[100]');
        $v->set_rules('remarks', 'Remarks', 'trim|max_length[500]');
    }

    private function date_error(): ?string
    {
        return (string) $this->input->post('income_date') > date('Y-m-d') ? 'The date cannot be in the future.' : NULL;
    }

    /**
     * @return array<string, mixed>
     */
    private function collect_input(): array
    {
        $post = function (string $k): string { return trim((string) $this->input->post($k, TRUE)); };
        return array(
            'income_date'  => $post('income_date'),
            'source'       => $post('source'),
            'description'  => $post('description') !== '' ? $post('description') : NULL,
            'amount'       => from_paise((int) to_paise($post('amount'))),
            'payment_mode' => $post('payment_mode'),
            'reference'    => $post('reference') !== '' ? $post('reference') : NULL,
            'remarks'      => $post('remarks') !== '' ? $post('remarks') : NULL,
        );
    }

    /**
     * @param array<string, mixed> $income
     */
    private function render_form(array $income, string $title): void
    {
        $this->render('incomes/form', array(
            'page_title'  => $title,
            'breadcrumbs' => array(array('label' => 'Other Income', 'url' => 'incomes'), array('label' => $income['id'] === NULL ? 'Add' : $income['income_code'])),
            'income'      => $income,
            'sources'     => $this->income_model->sources(),
        ));
    }
}
