<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tenant records for rented houses (records only: bills, receipts and reminders go to the owner).
 *
 * Permissions:
 *   tenants.view      list / view contact, agreement dates, agreement document
 *   tenants.manage    add, edit, record move-out
 *   tenants.id_proof  see and edit ID proof details and the ID proof document
 */
class Tenants extends Auth_Controller
{
    protected $permission_map = array(
        'index'    => 'tenants.view',
        'view'     => 'tenants.view',
        'document' => 'tenants.view',
        'create'   => 'tenants.manage',
        'edit'     => 'tenants.manage',
        'move_out' => 'tenants.manage',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('tenant_model', 'house_model'));
        $this->load->library(array('form_validation', 'tenant_service', 'secure_upload'));
    }

    public function index(): void
    {
        $this->render('tenants/index', array(
            'page_title'   => 'Tenants',
            'breadcrumbs'  => array(array('label' => 'Tenants')),
            'tenants'      => $this->tenant_model->list_all(),
            'warning_days' => (int) $this->config->item('agreement_expiry_warning_days'),
            'scripts'      => array('js/modules/tenants.js'),
        ));
    }

    public function view(int $id = 0): void
    {
        $tenant = $this->tenant_model->find_full($id);
        if ($tenant === NULL)
        {
            show_404();
        }
        $this->render('tenants/view', array(
            'page_title'   => $tenant['tenant_name'],
            'breadcrumbs'  => array(array('label' => 'Tenants', 'url' => 'tenants'), array('label' => $tenant['tenant_code'])),
            'tenant'       => $tenant,
            'warning_days' => (int) $this->config->item('agreement_expiry_warning_days'),
            'scripts'      => array('js/modules/tenants.js'),
        ));
    }

    public function create(): void
    {
        $house_options = $this->tenant_model->rentable_house_options();
        $tenant = $this->blank_tenant();
        $tenant['house_id'] = (string) $this->input->get('house_id');

        if ($this->input->method() === 'post')
        {
            $this->set_rules(TRUE);
            if ($this->form_validation->run() && ($error = $this->date_order_error()) === NULL)
            {
                $data = $this->collect_input();
                $data['house_id'] = (int) $this->input->post('house_id');
                $files = $this->store_files($data);
                if ($files['success'])
                {
                    $result = $this->tenant_service->move_in($data);
                    if ($result['success'])
                    {
                        $this->session->set_flashdata('success', $result['message']);
                        redirect('tenants/view/'.$result['id']);
                    }
                    $this->discard_new_files($data);
                    $this->data['form_error'] = $result['message'];
                }
                else
                {
                    $this->data['form_error'] = $files['message'];
                }
            }
            elseif (isset($error))
            {
                $this->data['form_error'] = $error;
            }
        }

        $this->render('tenants/form', array(
            'page_title'    => 'Add Tenant',
            'breadcrumbs'   => array(array('label' => 'Tenants', 'url' => 'tenants'), array('label' => 'Add Tenant')),
            'tenant'        => $tenant,
            'house_options' => $house_options,
            'scripts'       => array('js/modules/tenants.js'),
        ));
    }

    public function edit(int $id = 0): void
    {
        $tenant = $this->tenant_model->find_full($id);
        if ($tenant === NULL)
        {
            show_404();
        }

        if ($this->input->method() === 'post')
        {
            $this->set_rules(FALSE);
            if ($this->form_validation->run() && ($error = $this->date_order_error()) === NULL)
            {
                $data = $this->collect_input();
                $files = $this->store_files($data);
                if ($files['success'])
                {
                    $old = $this->tenant_model->find($id);
                    $result = $this->tenant_service->update($old, $data);
                    if ($result['success'])
                    {
                        // Replace documents only after the new ones are saved
                        foreach (array('id_proof_file', 'agreement_file') as $col)
                        {
                            if (isset($data[$col]) && $old[$col] && $old[$col] !== $data[$col])
                            {
                                $this->secure_upload->delete($old[$col]);
                            }
                        }
                        $this->session->set_flashdata('success', $result['message']);
                        redirect('tenants/view/'.$id);
                    }
                    $this->discard_new_files($data);
                    $this->data['form_error'] = $result['message'];
                }
                else
                {
                    $this->data['form_error'] = $files['message'];
                }
            }
            elseif (isset($error))
            {
                $this->data['form_error'] = $error;
            }
        }

        $this->render('tenants/form', array(
            'page_title'    => 'Edit Tenant',
            'breadcrumbs'   => array(array('label' => 'Tenants', 'url' => 'tenants'), array('label' => $tenant['tenant_code'], 'url' => 'tenants/view/'.$id), array('label' => 'Edit')),
            'tenant'        => $tenant,
            'house_options' => array(),
            'scripts'       => array('js/modules/tenants.js'),
        ));
    }

    /**
     * POST: record move-out.
     */
    public function move_out(int $id = 0): void
    {
        $this->require_post();
        $this->form_validation->set_rules('move_out_date', 'Move-out Date', 'required|valid_date_ymd');
        $this->form_validation->set_rules('occupancy_after', 'House after move-out', 'required|in_list[Owner Occupied,Vacant]');
        $this->form_validation->set_rules('move_out_remarks', 'Remarks', 'trim|max_length[200]');
        if ( ! $this->form_validation->run())
        {
            $this->session->set_flashdata('error', strip_tags(validation_errors(' ', ' ')));
            redirect('tenants/view/'.$id);
        }
        $result = $this->tenant_service->move_out(
            $id,
            (string) $this->input->post('move_out_date'),
            (string) $this->input->post('occupancy_after'),
            trim((string) $this->input->post('move_out_remarks', TRUE))
        );
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect('tenants/view/'.$id);
    }

    /**
     * Stream a tenant document. $type: 'agreement' (tenants.view) or 'id-proof' (tenants.id_proof).
     */
    public function document(int $id = 0, string $type = ''): void
    {
        $tenant = $this->tenant_model->find($id);
        if ($tenant === NULL)
        {
            show_404();
        }
        if ($type === 'id-proof' || $type === 'id_proof')
        {
            $this->require_permission('tenants.id_proof');
            $this->audit->log('Tenant ID Proof Viewed', 'tenants', $id);
            $this->secure_upload->send($tenant['id_proof_file'], (string) ($tenant['id_proof_file_name'] ?: 'id-proof'));
        }
        if ($type === 'agreement')
        {
            $this->secure_upload->send($tenant['agreement_file'], (string) ($tenant['agreement_file_name'] ?: 'agreement'));
        }
        show_404();
    }

    /* ------------------------------------------------------------------ */

    /**
     * @return array<string, mixed>
     */
    private function blank_tenant(): array
    {
        return array(
            'id' => NULL, 'tenant_code' => '', 'house_id' => '', 'tenant_name' => '', 'mobile' => '', 'whatsapp_no' => '', 'email' => '',
            'permanent_address' => '', 'emergency_contact_name' => '', 'emergency_contact_phone' => '', 'emergency_contact_relation' => '',
            'id_proof_type' => '', 'id_proof_number' => '', 'id_proof_file' => NULL, 'id_proof_file_name' => NULL,
            'agreement_start' => '', 'agreement_end' => '', 'agreement_file' => NULL, 'agreement_file_name' => NULL,
            'move_in_date' => date('Y-m-d'), 'status' => 'Active', 'remarks' => '',
        );
    }

    private function set_rules(bool $is_new): void
    {
        $v = $this->form_validation;
        if ($is_new)
        {
            $v->set_rules('house_id', 'House / Plot', 'required|is_natural_no_zero|exists_in[houses.id]');
        }
        $v->set_rules('tenant_name', 'Tenant Name', 'trim|required|min_length[2]|max_length[120]');
        $v->set_rules('mobile', 'Mobile Number', 'trim|required|valid_mobile');
        $v->set_rules('whatsapp_no', 'WhatsApp Number', 'trim|valid_mobile');
        $v->set_rules('email', 'E-mail', 'trim|valid_email|max_length[150]');
        $v->set_rules('permanent_address', 'Permanent Address', 'trim|max_length[255]');
        $v->set_rules('emergency_contact_name', 'Emergency Contact Name', 'trim|max_length[120]');
        $v->set_rules('emergency_contact_phone', 'Emergency Contact Phone', 'trim|valid_mobile');
        $v->set_rules('emergency_contact_relation', 'Relationship', 'trim|max_length[50]');
        $v->set_rules('agreement_start', 'Agreement Start', 'trim|valid_date_ymd');
        $v->set_rules('agreement_end', 'Agreement End', 'trim|valid_date_ymd');
        $v->set_rules('move_in_date', 'Move-in Date', 'trim|required|valid_date_ymd');
        $v->set_rules('remarks', 'Remarks', 'trim|max_length[500]');

        if (can('tenants.id_proof'))
        {
            $type = (string) $this->input->post('id_proof_type');
            $v->set_rules('id_proof_type', 'ID Proof Type', 'in_config_list[id_proof_types]');
            if ($type === 'Aadhaar')
            {
                $v->set_rules('id_proof_number', 'Aadhaar (last 4 digits)', 'trim|required|regex_match[/^\d{4}$/]',
                    array('regex_match' => 'For Aadhaar enter only the LAST 4 digits.'));
            }
            elseif ($type !== '')
            {
                $v->set_rules('id_proof_number', 'ID Proof Number', 'trim|required|regex_match[/^[A-Za-z0-9 \/-]{4,30}$/]',
                    array('regex_match' => 'The ID number may contain letters, digits, space, / and - (4-30 characters).'));
            }
        }
    }

    /**
     * Cross-field date rules not expressible as single-field rules.
     */
    private function date_order_error(): ?string
    {
        $start = (string) $this->input->post('agreement_start');
        $end = (string) $this->input->post('agreement_end');
        if ($start !== '' && $end !== '' && $end < $start)
        {
            return 'The agreement end date cannot be before its start date.';
        }
        $move_in = (string) $this->input->post('move_in_date');
        if ($move_in > date('Y-m-d', strtotime('+60 days')))
        {
            return 'The move-in date cannot be more than 60 days in the future.';
        }
        return NULL;
    }

    /**
     * @return array<string, mixed>
     */
    private function collect_input(): array
    {
        $post = function (string $key): string {
            return trim((string) $this->input->post($key, TRUE));
        };
        $nullable = function (string $key) use ($post): ?string {
            return $post($key) !== '' ? $post($key) : NULL;
        };
        $mobile = normalize_mobile($post('mobile'));
        $whatsapp = normalize_mobile($post('whatsapp_no'));
        $emergency = normalize_mobile($post('emergency_contact_phone'));

        $data = array(
            'tenant_name'                => $post('tenant_name'),
            'mobile'                     => $mobile,
            'whatsapp_no'                => $whatsapp !== '' ? $whatsapp : $mobile,
            'email'                      => $post('email') !== '' ? strtolower($post('email')) : NULL,
            'permanent_address'          => $nullable('permanent_address'),
            'emergency_contact_name'     => $nullable('emergency_contact_name'),
            'emergency_contact_phone'    => $emergency !== '' ? $emergency : NULL,
            'emergency_contact_relation' => $nullable('emergency_contact_relation'),
            'agreement_start'            => $nullable('agreement_start'),
            'agreement_end'              => $nullable('agreement_end'),
            'move_in_date'               => $post('move_in_date'),
            'remarks'                    => $nullable('remarks'),
        );

        if (can('tenants.id_proof'))
        {
            $type = $post('id_proof_type');
            $data['id_proof_type'] = $type !== '' ? $type : NULL;
            $data['id_proof_number'] = $type !== '' ? strtoupper($post('id_proof_number')) : NULL;
        }
        return $data;
    }

    /**
     * Save uploaded documents and add their paths to $data.
     *
     * @param array<string, mixed> $data
     * @return array{success: bool, message: string}
     */
    private function store_files(array &$data): array
    {
        $types = (string) $this->config->item('upload_tenant_types');
        $max = (int) $this->config->item('upload_tenant_max_kb');

        $uploads = array('agreement_file' => 'Rental agreement');
        if (can('tenants.id_proof'))
        {
            $uploads['id_proof_file'] = 'ID proof';
        }

        foreach ($uploads as $field => $label)
        {
            $result = $this->secure_upload->store($field, 'tenants', $types, $max);
            if ( ! $result['success'])
            {
                $this->discard_new_files($data);
                return array('success' => FALSE, 'message' => $label.': '.$result['message']);
            }
            if (isset($result['path']))
            {
                $data[$field] = $result['path'];
                $data[$field.'_name'] = $result['original_name'];
            }
        }
        return array('success' => TRUE, 'message' => '');
    }

    /**
     * Remove files uploaded in this request when the save fails.
     *
     * @param array<string, mixed> $data
     */
    private function discard_new_files(array $data): void
    {
        foreach (array('id_proof_file', 'agreement_file') as $col)
        {
            if ( ! empty($data[$col]))
            {
                $this->secure_upload->delete($data[$col]);
            }
        }
    }
}
