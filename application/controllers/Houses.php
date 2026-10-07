<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * House / plot management. A house has one current owner; transferring a house
 * keeps old bills with the previous owner.
 */
class Houses extends Auth_Controller
{
    protected $permission_map = array(
        'index'         => 'houses.view',
        'create'        => 'houses.create',
        'edit'          => 'houses.edit',
        'toggle_status' => 'houses.delete',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('house_model', 'owner_model', 'rate_model'));
        $this->load->library(array('form_validation', 'owner_service'));
    }

    public function index(): void
    {
        $houses = $this->house_model->list_all();
        foreach ($houses as &$h)
        {
            $h['plot_category'] = $this->rate_model->category_for($h['built_status']);
        }
        unset($h);
        $this->render('houses/index', array(
            'page_title'  => 'Houses / Plots',
            'breadcrumbs' => array(array('label' => 'Houses / Plots')),
            'houses'      => $houses,
            'blocks'      => array_values(array_unique(array_filter(array_column($houses, 'block'), 'strlen'))),
            'scripts'     => array('js/modules/houses.js'),
        ));
    }

    public function create(): void
    {
        $house = array(
            'id' => NULL, 'plot_no' => '', 'house_no' => '', 'block' => '', 'street' => '', 'house_type' => 'Independent House',
            'built_status' => 'Built', 'occupancy_status' => 'Vacant', 'owner_id' => (string) $this->input->get('owner_id'),
            'area_sqft' => '', 'uds_sqft' => '', 'remarks' => '', 'status' => 'Active',
        );

        if ($this->input->method() === 'post')
        {
            $this->set_rules(NULL, FALSE);
            if ($this->form_validation->run())
            {
                $data = $this->collect_input(FALSE);
                $result = $this->owner_service->create_house($data);
                if ($result['success'])
                {
                    $this->session->set_flashdata('success', $result['message']);
                    $back_to_owner = (int) $this->input->post('return_owner');
                    redirect($back_to_owner > 0 && $back_to_owner === (int) $data['owner_id'] ? 'owners/view/'.$back_to_owner.'#houses' : 'houses');
                }
                $this->data['form_error'] = $result['message'];
            }
        }

        $this->render_form($house, 'Add House / Plot');
    }

    public function edit(int $id = 0): void
    {
        $house = $this->house_model->find_with_owner($id);
        if ($house === NULL)
        {
            show_404();
        }

        $has_tenant = $house['tenant_id'] !== NULL;
        if ($this->input->method() === 'post')
        {
            $this->set_rules($id, $has_tenant);
            if ($this->form_validation->run())
            {
                $result = $this->owner_service->update_house($id, $this->collect_input($has_tenant));
                if ($result['success'])
                {
                    $this->session->set_flashdata(strpos($result['message'], 'Note:') !== FALSE ? 'warning' : 'success', $result['message']);
                    redirect('houses');
                }
                $this->data['form_error'] = $result['message'];
            }
        }

        $this->render_form($house, 'Edit Plot '.$house['plot_no']);
    }

    /**
     * AJAX POST: activate / deactivate a house (inactive houses are not billed).
     */
    public function toggle_status(int $id = 0): void
    {
        $this->require_ajax();
        $this->require_post();
        $house = $this->house_model->find($id);
        if ($house === NULL)
        {
            $this->json_error('House not found.', 404);
        }
        $status = $house['status'] === 'Active' ? 'Inactive' : 'Active';
        if ($status === 'Inactive' && $this->house_model->active_tenant($id) !== NULL)
        {
            $this->json_error('Plot '.$house['plot_no'].' has a tenant living there. Record the tenant\'s move-out before deactivating the plot.');
        }
        $this->house_model->update($id, array('status' => $status, 'updated_by' => user_id()));
        $this->audit->log('House Status Changed', 'houses', $id, array('status' => $house['status']), array('status' => $status));
        $this->json_success('Plot '.$house['plot_no'].' is now '.$status.'.'.($status === 'Inactive' ? ' It will not be billed.' : ''), array('status' => $status));
    }

    /* ------------------------------------------------------------------ */

    private function set_rules(?int $id, bool $has_tenant): void
    {
        $ignore = $id !== NULL ? (string) $id : '';
        $v = $this->form_validation;
        $v->set_rules('plot_no', 'Plot Number', 'trim|required|max_length[20]|unique_except[houses.plot_no.'.$ignore.']');
        $v->set_rules('block', 'Block', 'trim|max_length[20]');
        $v->set_rules('house_no', 'House Number', 'trim|max_length[20]|unique_house_no[block.'.$ignore.']');
        $v->set_rules('street', 'Street', 'trim|max_length[100]');
        $v->set_rules('house_type', 'House Type', 'required|in_config_list[house_types]');
        $v->set_rules('built_status', 'Built Status', 'required|in_config_list[built_statuses]');
        // "Tenant Occupied" is set by the Tenants module only (when a tenant moves in)
        if ( ! $has_tenant)
        {
            $v->set_rules('occupancy_status', 'Occupancy Status', 'required|in_list[Owner Occupied,Vacant]');
        }
        $v->set_rules('owner_id', 'Owner', ($has_tenant ? 'required|' : '').'is_natural_no_zero|exists_in[owners.id]', array('required' => 'This plot has an active tenant, so it must have an owner. Record the tenant\'s move-out first.'));
        $v->set_rules('area_sqft', 'Area (sq.ft)', 'trim|valid_amount');
        $v->set_rules('uds_sqft', 'UDS (sq.ft)', 'trim|valid_amount');
        $v->set_rules('remarks', 'Remarks', 'trim|max_length[500]');
        $v->set_rules('status', 'Status', 'required|in_list[Active,Inactive]');
    }

    /**
     * @return array<string, mixed>
     */
    private function collect_input(bool $has_tenant): array
    {
        $post = function (string $key): string {
            return trim((string) $this->input->post($key, TRUE));
        };
        $owner_id = (int) $post('owner_id');
        $area = to_paise($post('area_sqft'));
        $uds = to_paise($post('uds_sqft'));
        return array(
            'plot_no'          => $post('plot_no'),
            'house_no'         => $post('house_no') !== '' ? $post('house_no') : NULL,
            'block'            => $post('block'),
            'street'           => $post('street') !== '' ? $post('street') : NULL,
            'house_type'       => $post('house_type'),
            'built_status'     => $post('built_status'),
            'occupancy_status' => $has_tenant ? 'Tenant Occupied' : ($owner_id > 0 ? $post('occupancy_status') : 'Vacant'),
            'owner_id'         => $owner_id > 0 ? $owner_id : NULL,
            'area_sqft'        => $area !== NULL && $post('area_sqft') !== '' ? from_paise($area) : NULL,
            'uds_sqft'         => $uds !== NULL && $post('uds_sqft') !== '' ? from_paise($uds) : NULL,
            'remarks'          => $post('remarks') !== '' ? $post('remarks') : NULL,
            'status'           => $post('status'),
        );
    }

    /**
     * @param array<string, mixed> $house
     */
    private function render_form(array $house, string $title): void
    {
        $owners = $this->owner_model->active_options();
        // Keep the current owner selectable even if they were deactivated later
        if ( ! empty($house['owner_id']) && ! isset($owners[(int) $house['owner_id']]) && ! empty($house['owner_name']))
        {
            $owners[(int) $house['owner_id']] = $house['owner_code'].' - '.$house['owner_name'].' (inactive)';
        }

        $this->render('houses/form', array(
            'page_title'  => $title,
            'breadcrumbs' => array(array('label' => 'Houses / Plots', 'url' => 'houses'), array('label' => $house['id'] === NULL ? 'Add' : 'Plot '.$house['plot_no'])),
            'house'       => $house,
            'owners'      => $owners,
            'bill_count'  => $house['id'] !== NULL ? $this->house_model->count_bills((int) $house['id']) : 0,
            'tenant'      => $house['id'] !== NULL ? $this->house_model->active_tenant((int) $house['id']) : NULL,
            'rates'       => $this->rate_model->rates_for_month(),
            'scripts'     => array('js/modules/houses.js'),
        ));
    }
}
