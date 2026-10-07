<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Association expenses with bill/invoice attachments, and expense categories.
 * Expenses are never deleted: they are cancelled with a reason.
 */
class Expenses extends Auth_Controller
{
    protected $permission_map = array(
        'index'           => 'expenses.view',
        'attachment'      => 'expenses.view',
        'create'          => 'expenses.create',
        'edit'            => 'expenses.edit',
        'cancel'          => 'expenses.cancel',
        'categories'      => 'expenses.view',
        'save_category'   => 'expenses.edit',
        'toggle_category' => 'expenses.edit',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('expense_model', 'sequence_model'));
        $this->load->library(array('form_validation', 'secure_upload'));
    }

    public function index(): void
    {
        $from = (string) $this->input->get('from');
        $to = (string) $this->input->get('to');
        $filters = array(
            'from'        => is_valid_date($from) ? $from : date('Y-m-01', strtotime('-2 months')),
            'to'          => is_valid_date($to) ? $to : date('Y-m-d'),
            'category_id' => (int) $this->input->get('category_id'),
            'status'      => in_array($this->input->get('status'), array('Active', 'Cancelled'), TRUE) ? (string) $this->input->get('status') : '',
        );
        $expenses = $this->expense_model->list_expenses($filters);
        $total = 0;
        $by_category = array();
        foreach ($expenses as $e)
        {
            if ($e['status'] === 'Active')
            {
                $paise = to_paise_signed($e['amount']);
                $total += $paise;
                $by_category[$e['category_name']] = ($by_category[$e['category_name']] ?? 0) + $paise;
            }
        }
        arsort($by_category);

        $this->render('expenses/index', array(
            'page_title'  => 'Expenses',
            'breadcrumbs' => array(array('label' => 'Finance'), array('label' => 'Expenses')),
            'filters'     => $filters,
            'expenses'    => $expenses,
            'total'       => from_paise($total),
            'by_category' => array_map('from_paise', $by_category),
            'categories'  => $this->expense_model->category_options(),
            'scripts'     => array('js/modules/finance.js'),
        ));
    }

    public function create(): void
    {
        $expense = array(
            'id' => NULL, 'expense_code' => '', 'expense_date' => date('Y-m-d'), 'category_id' => '', 'description' => '', 'amount' => '',
            'payment_mode' => 'Cash', 'vendor' => '', 'bill_number' => '', 'remarks' => '', 'attachment_path' => NULL, 'attachment_original_name' => NULL,
        );
        if ($this->input->method() === 'post')
        {
            $this->set_rules();
            if ($this->form_validation->run() && ($error = $this->date_error()) === NULL)
            {
                $data = $this->collect_input();
                $upload = $this->secure_upload->store('attachment', 'expenses', (string) $this->config->item('upload_expense_types'), (int) $this->config->item('upload_expense_max_kb'));
                if ( ! $upload['success'])
                {
                    $this->data['form_error'] = 'Attachment: '.$upload['message'];
                }
                else
                {
                    if (isset($upload['path']))
                    {
                        $data['attachment_path'] = $upload['path'];
                        $data['attachment_original_name'] = $upload['original_name'];
                    }
                    $result = $this->save_new($data);
                    if ($result['success'])
                    {
                        $this->session->set_flashdata('success', $result['message']);
                        redirect('expenses');
                    }
                    $this->secure_upload->delete($data['attachment_path'] ?? NULL);
                    $this->data['form_error'] = $result['message'];
                }
            }
            elseif (isset($error))
            {
                $this->data['form_error'] = $error;
            }
        }
        $this->render_form($expense, 'Add Expense');
    }

    public function edit(int $id = 0): void
    {
        $expense = $this->expense_model->find_full($id);
        if ($expense === NULL)
        {
            show_404();
        }
        if ($expense['status'] !== 'Active')
        {
            $this->session->set_flashdata('error', 'A cancelled expense cannot be edited.');
            redirect('expenses');
        }

        if ($this->input->method() === 'post')
        {
            $this->set_rules();
            if ($this->form_validation->run() && ($error = $this->date_error()) === NULL)
            {
                $data = $this->collect_input();
                $upload = $this->secure_upload->store('attachment', 'expenses', (string) $this->config->item('upload_expense_types'), (int) $this->config->item('upload_expense_max_kb'));
                if ( ! $upload['success'])
                {
                    $this->data['form_error'] = 'Attachment: '.$upload['message'];
                }
                else
                {
                    $old_file = NULL;
                    if (isset($upload['path']))
                    {
                        $data['attachment_path'] = $upload['path'];
                        $data['attachment_original_name'] = $upload['original_name'];
                        $old_file = $expense['attachment_path'];
                    }
                    elseif ($this->input->post('remove_attachment') === '1')
                    {
                        $data['attachment_path'] = NULL;
                        $data['attachment_original_name'] = NULL;
                        $old_file = $expense['attachment_path'];
                    }
                    $data['updated_by'] = user_id();
                    $this->expense_model->update($id, $data);
                    $this->secure_upload->delete($old_file);
                    $this->audit->log('Expense Updated', 'expenses', $id, $expense, $data);
                    $this->session->set_flashdata('success', 'Expense '.$expense['expense_code'].' updated.');
                    redirect('expenses');
                }
            }
            elseif (isset($error))
            {
                $this->data['form_error'] = $error;
            }
        }
        $this->render_form($expense, 'Edit Expense '.$expense['expense_code']);
    }

    /**
     * POST: cancel with a reason (the record and attachment are kept).
     */
    public function cancel(int $id = 0): void
    {
        $this->require_post();
        $expense = $this->expense_model->find($id);
        $reason = trim((string) $this->input->post('reason', TRUE));
        if ($expense === NULL)
        {
            show_404();
        }
        if ($expense['status'] !== 'Active')
        {
            $this->session->set_flashdata('error', 'This expense is already cancelled.');
            redirect('expenses');
        }
        if (mb_strlen($reason) < 3)
        {
            $this->session->set_flashdata('error', 'Please give a reason for cancelling the expense.');
            redirect('expenses');
        }
        $update = array('status' => 'Cancelled', 'cancel_reason' => mb_substr($reason, 0, 255), 'cancelled_by' => user_id(), 'cancelled_at' => date('Y-m-d H:i:s'));
        $this->expense_model->update($id, $update);
        $this->audit->log('Expense Cancelled', 'expenses', $id, array('status' => 'Active'), $update);
        $this->session->set_flashdata('success', 'Expense '.$expense['expense_code'].' cancelled.');
        redirect('expenses');
    }

    public function attachment(int $id = 0): void
    {
        $expense = $this->expense_model->find($id);
        if ($expense === NULL || empty($expense['attachment_path']))
        {
            show_404();
        }
        $this->secure_upload->send($expense['attachment_path'], (string) ($expense['attachment_original_name'] ?: $expense['expense_code']));
    }

    /* ---------------- Categories ---------------- */

    public function categories(): void
    {
        $this->render('expenses/categories', array(
            'page_title'  => 'Expense Categories',
            'breadcrumbs' => array(array('label' => 'Expenses', 'url' => 'expenses'), array('label' => 'Categories')),
            'categories'  => $this->expense_model->categories(),
        ));
    }

    /**
     * POST: add a category, or rename one (id given).
     */
    public function save_category(): void
    {
        $this->require_post();
        $id = (int) $this->input->post('id');
        $this->form_validation->set_rules('name', 'Category name', 'trim|required|min_length[2]|max_length[60]|unique_except[expense_categories.name.'.($id > 0 ? $id : '').']');
        $this->form_validation->set_rules('description', 'Description', 'trim|max_length[255]');
        if ( ! $this->form_validation->run())
        {
            $this->session->set_flashdata('error', strip_tags(validation_errors(' ', ' ')));
            redirect('expenses/categories');
        }
        $data = array('name' => trim((string) $this->input->post('name', TRUE)), 'description' => trim((string) $this->input->post('description', TRUE)) ?: NULL);
        if ($id > 0)
        {
            $old = $this->db->get_where('expense_categories', array('id' => $id))->row_array();
            if ( ! $old)
            {
                show_404();
            }
            $this->db->where('id', $id)->update('expense_categories', $data);
            $this->audit->log('Expense Category Updated', 'expenses', 'category-'.$id, $old, $data);
        }
        else
        {
            $this->db->insert('expense_categories', $data + array('status' => 'Active'));
            $this->audit->log('Expense Category Added', 'expenses', 'category-'.$this->db->insert_id(), NULL, $data);
        }
        $this->session->set_flashdata('success', 'Category "'.$data['name'].'" saved.');
        redirect('expenses/categories');
    }

    public function toggle_category(int $id = 0): void
    {
        $this->require_post();
        $cat = $this->db->get_where('expense_categories', array('id' => $id))->row_array();
        if ( ! $cat)
        {
            show_404();
        }
        $status = $cat['status'] === 'Active' ? 'Inactive' : 'Active';
        $this->db->where('id', $id)->update('expense_categories', array('status' => $status));
        $this->audit->log('Expense Category '.($status === 'Active' ? 'Activated' : 'Deactivated'), 'expenses', 'category-'.$id);
        $this->session->set_flashdata('success', 'Category "'.$cat['name'].'" is now '.$status.'.');
        redirect('expenses/categories');
    }

    /* ------------------------------------------------------------------ */

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, message: string}
     */
    private function save_new(array $data): array
    {
        $this->db->trans_begin();
        try
        {
            $year = (int) substr($data['expense_date'], 0, 4);
            $data['expense_code'] = $this->sequence_model->formatted('EXPENSE', 'EXP-'.$year.'-', 6, $year);
            $data['status'] = 'Active';
            $data['created_by'] = user_id();
            $data['updated_by'] = user_id();
            $id = $this->expense_model->insert($data);
            if ($id === 0 || $this->db->trans_status() === FALSE)
            {
                throw new RuntimeException('insert failed');
            }
            $this->audit->log('Expense Added', 'expenses', $id, NULL, $data);
            $this->db->trans_commit();
            return array('success' => TRUE, 'message' => 'Expense '.$data['expense_code'].' of '.money($data['amount']).' recorded.');
        }
        catch (Throwable $e)
        {
            $this->db->trans_rollback();
            log_message('error', 'Expense save failed: '.$e->getMessage().' '.json_encode($this->db->error()));
            return array('success' => FALSE, 'message' => 'The expense could not be saved. Please try again.');
        }
    }

    private function set_rules(): void
    {
        $v = $this->form_validation;
        $v->set_rules('expense_date', 'Expense Date', 'required|valid_date_ymd');
        $v->set_rules('category_id', 'Category', 'required|is_natural_no_zero|exists_in[expense_categories.id]');
        $v->set_rules('description', 'Description', 'trim|required|max_length[255]');
        $v->set_rules('amount', 'Amount', 'trim|required|positive_amount');
        $v->set_rules('payment_mode', 'Payment Mode', 'required|in_config_list[payment_modes]');
        $v->set_rules('vendor', 'Vendor', 'trim|max_length[120]');
        $v->set_rules('bill_number', 'Bill Number', 'trim|max_length[60]');
        $v->set_rules('remarks', 'Remarks', 'trim|max_length[500]');
    }

    private function date_error(): ?string
    {
        return (string) $this->input->post('expense_date') > date('Y-m-d') ? 'The expense date cannot be in the future.' : NULL;
    }

    /**
     * @return array<string, mixed>
     */
    private function collect_input(): array
    {
        $post = function (string $k): string { return trim((string) $this->input->post($k, TRUE)); };
        return array(
            'expense_date' => $post('expense_date'),
            'category_id'  => (int) $post('category_id'),
            'description'  => $post('description'),
            'amount'       => from_paise((int) to_paise($post('amount'))),
            'payment_mode' => $post('payment_mode'),
            'vendor'       => $post('vendor') !== '' ? $post('vendor') : NULL,
            'bill_number'  => $post('bill_number') !== '' ? $post('bill_number') : NULL,
            'remarks'      => $post('remarks') !== '' ? $post('remarks') : NULL,
        );
    }

    /**
     * @param array<string, mixed> $expense
     */
    private function render_form(array $expense, string $title): void
    {
        $this->render('expenses/form', array(
            'page_title'  => $title,
            'breadcrumbs' => array(array('label' => 'Expenses', 'url' => 'expenses'), array('label' => $expense['id'] === NULL ? 'Add' : $expense['expense_code'])),
            'expense'     => $expense,
            'categories'  => $this->expense_model->category_options($expense['id'] !== NULL ? (int) $expense['category_id'] : NULL),
            'max_mb'      => round((int) $this->config->item('upload_expense_max_kb') / 1024, 1),
        ));
    }
}
