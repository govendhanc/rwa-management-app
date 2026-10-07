<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Owner management: list, view (plots, tenants, maintenance, payments), create with any
 * number of plots, assign more plots, edit, soft delete.
 *
 * Monthly maintenance is not stored per owner: each plot is billed at the rate for its
 * category (Vacant Plot / Constructed) in force for the billing month (see Rates).
 */
class Owners extends Auth_Controller
{
    const MAX_PLOTS_PER_FORM = 20;

    protected $permission_map = array(
        'index'       => 'owners.view',
        'view'        => 'owners.view',
        'create'      => 'owners.create',
        'edit'        => 'owners.edit',
        'assign_plot' => 'owners.edit',
        'delete'      => 'owners.delete',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->model(array('owner_model', 'house_model', 'rate_model'));
        $this->load->library(array('form_validation', 'owner_service'));
    }

    public function index(): void
    {
        $rates = $this->rate_model->rates_for_month();
        $owners = $this->owner_model->list_all();
        foreach ($owners as &$o)
        {
            $o['monthly_maintenance'] = $this->monthly_amount((int) $o['constructed_plots'], (int) $o['vacant_plots'], $rates);
        }
        unset($o);

        $this->render('owners/index', array(
            'page_title'  => 'Owners',
            'breadcrumbs' => array(array('label' => 'Owners')),
            'owners'      => $owners,
            'scripts'     => array('js/modules/owners.js'),
        ));
    }

    public function view(int $id = 0): void
    {
        $owner = $this->owner_model->find_active_record($id);
        if ($owner === NULL)
        {
            show_404();
        }

        $rates = $this->rate_model->rates_for_month();
        $houses = $this->house_model->for_owner($id);
        $constructed = 0;
        $vacant = 0;
        foreach ($houses as &$h)
        {
            $h['plot_category'] = $this->rate_model->category_for($h['built_status']);
            $h['current_rate'] = $rates[$h['plot_category']]['amount'] ?? NULL;
            if ($h['status'] === 'Active')
            {
                $h['plot_category'] === 'Constructed' ? $constructed++ : $vacant++;
            }
        }
        unset($h);

        $this->render('owners/view', array(
            'page_title'   => $owner['owner_name'],
            'breadcrumbs'  => array(array('label' => 'Owners', 'url' => 'owners'), array('label' => $owner['owner_code'])),
            'owner'        => $owner,
            'houses'       => $houses,
            'monthly'      => $this->monthly_amount($constructed, $vacant, $rates),
            'free_houses'  => can('owners.edit') && $owner['status'] === 'Active' ? $this->house_model->unassigned_options() : array(),
            'maintenance'  => can('maintenance.view') ? $this->owner_model->maintenance_history($id) : array(),
            'payments'     => can('payments.view') ? $this->owner_model->payment_history($id) : array(),
            'tenants'      => can('tenants.view') ? $this->owner_tenants($id) : array(),
            'can_delete'   => can('owners.delete') && ! $this->owner_model->has_financial_history($id),
            'scripts'      => array('js/modules/owners.js'),
        ));
    }

    public function create(): void
    {
        $plot_rows = $this->posted_plot_rows();

        if ($this->input->method() === 'post')
        {
            $this->set_owner_rules();
            foreach ($plot_rows as $i => $row)
            {
                $this->set_plot_row_rules($i, $row['mode']);
            }

            $valid = $this->form_validation->run();
            $duplicate_error = $valid ? $this->duplicate_plot_error($plot_rows) : NULL;

            if ($valid && $duplicate_error === NULL)
            {
                $result = $this->owner_service->create_owner($this->collect_owner_input(), $this->collect_plot_rows($plot_rows));
                if ($result['success'])
                {
                    $this->session->set_flashdata('success', $result['message']);
                    redirect('owners/view/'.$result['id']);
                }
                $this->data['form_error'] = $result['message'];
            }
            elseif ($duplicate_error !== NULL)
            {
                $this->data['form_error'] = $duplicate_error;
            }
        }

        $free_houses = $this->house_model->unassigned_options();
        if (empty($plot_rows) && $this->input->method() !== 'post')
        {
            // Start with one row: pick an existing plot when any are free, otherwise register a new one
            $plot_rows = array(array('mode' => empty($free_houses) ? 'new' : 'existing'));
        }

        $defaults = array(
            'id' => NULL, 'owner_code' => '', 'owner_name' => '', 'co_owner_name' => '', 'mobile' => '', 'whatsapp_no' => '',
            'email' => '', 'permanent_address' => '', 'residential_address' => '', 'owner_type' => 'Individual',
            'joining_date' => date('Y-m-d'), 'maintenance_start_date' => date('Y-m-01'), 'status' => 'Active', 'remarks' => '',
        );

        $this->render('owners/form', array(
            'page_title'  => 'Add Owner',
            'breadcrumbs' => array(array('label' => 'Owners', 'url' => 'owners'), array('label' => 'Add Owner')),
            'owner'       => $defaults,
            'plot_rows'   => $plot_rows,
            'free_houses' => $free_houses,
            'houses'      => array(),
            'rates'       => $this->rate_model->rates_for_month(),
            'scripts'     => array('js/modules/owner-form.js'),
        ));
    }

    public function edit(int $id = 0): void
    {
        $owner = $this->owner_model->find($id);
        if ($owner === NULL || (int) $owner['is_deleted'] === 1)
        {
            show_404();
        }

        if ($this->input->method() === 'post')
        {
            $this->set_owner_rules();
            if ($this->form_validation->run())
            {
                $result = $this->owner_service->update_owner($owner, $this->collect_owner_input());
                if ($result['success'])
                {
                    $this->session->set_flashdata('success', $result['message']);
                    redirect('owners/view/'.$id);
                }
                $this->data['form_error'] = $result['message'];
            }
        }

        $this->render('owners/form', array(
            'page_title'  => 'Edit Owner',
            'breadcrumbs' => array(array('label' => 'Owners', 'url' => 'owners'), array('label' => $owner['owner_code'], 'url' => 'owners/view/'.$id), array('label' => 'Edit')),
            'owner'       => $owner,
            'plot_rows'   => array(),
            'free_houses' => array(),
            'houses'      => $this->house_model->for_owner($id),
            'rates'       => $this->rate_model->rates_for_month(),
            'scripts'     => array('js/modules/owner-form.js'),
        ));
    }

    /**
     * POST from the owner profile: assign an existing unassigned plot.
     */
    public function assign_plot(int $id = 0): void
    {
        $this->require_post();
        $this->form_validation->set_rules('house_id', 'Plot', 'required|is_natural_no_zero|exists_in[houses.id]');
        $this->form_validation->set_rules('occupancy_status', 'Occupancy Status', 'required|in_list[Owner Occupied,Vacant]');
        if ( ! $this->form_validation->run())
        {
            $this->session->set_flashdata('error', strip_tags(validation_errors(' ', ' ')));
            redirect('owners/view/'.$id.'#houses');
        }

        $result = $this->owner_service->assign_plot($id, array(
            'mode'             => 'existing',
            'house_id'         => (int) $this->input->post('house_id'),
            'occupancy_status' => (string) $this->input->post('occupancy_status'),
        ));
        $this->session->set_flashdata($result['success'] ? 'success' : 'error', $result['message']);
        redirect('owners/view/'.$id.'#houses');
    }

    /**
     * AJAX POST: soft delete (only when there is no financial history).
     */
    public function delete(int $id = 0): void
    {
        $this->require_ajax();
        $this->require_post();
        $result = $this->owner_service->delete_owner($id);
        if ( ! $result['success'])
        {
            $this->json_error($result['message']);
        }
        $this->session->set_flashdata('success', $result['message']);
        $this->json_success($result['message'], array('redirect' => site_url('owners')));
    }

    /* ------------------------------------------------------------------ */

    /**
     * Monthly total for an owner = constructed plots x Constructed rate + vacant plots x Vacant Plot rate.
     *
     * @param array<string, array<string, mixed>|null> $rates
     */
    private function monthly_amount(int $constructed, int $vacant, array $rates): string
    {
        $paise = 0;
        if ($constructed > 0 && isset($rates['Constructed']))
        {
            $paise += $constructed * to_paise_signed($rates['Constructed']['amount']);
        }
        if ($vacant > 0 && isset($rates['Vacant Plot']))
        {
            $paise += $vacant * to_paise_signed($rates['Vacant Plot']['amount']);
        }
        return from_paise($paise);
    }

    /**
     * Plot rows posted from the Add Owner form, re-indexed 0..n-1.
     *
     * @return array<int, array<string, mixed>>
     */
    private function posted_plot_rows(): array
    {
        $posted = $this->input->post('plots');
        if ( ! is_array($posted))
        {
            return array();
        }
        $rows = array();
        foreach (array_slice($posted, 0, self::MAX_PLOTS_PER_FORM, TRUE) as $key => $row)
        {
            if (is_array($row) && isset($row['mode']) && in_array($row['mode'], array('existing', 'new'), TRUE))
            {
                $rows[(int) $key] = $row;
            }
        }
        return $rows;
    }

    private function set_plot_row_rules(int $i, string $mode): void
    {
        $v = $this->form_validation;
        $p = 'plots['.$i.']';
        $n = ' (plot row '.($i + 1).')';
        $v->set_rules($p.'[mode]', 'Plot type'.$n, 'required|in_list[existing,new]');
        $v->set_rules($p.'[occupancy_status]', 'Occupancy'.$n, 'required|in_list[Owner Occupied,Vacant]');
        if ($mode === 'existing')
        {
            $v->set_rules($p.'[house_id]', 'Plot'.$n, 'required|is_natural_no_zero|exists_in[houses.id]');
            return;
        }
        $v->set_rules($p.'[plot_no]', 'Plot Number'.$n, 'trim|required|max_length[20]|unique_except[houses.plot_no.]');
        $v->set_rules($p.'[block]', 'Block'.$n, 'trim|max_length[20]');
        $v->set_rules($p.'[house_no]', 'House Number'.$n, 'trim|max_length[20]|unique_house_no['.$p.'[block].]');
        $v->set_rules($p.'[street]', 'Street'.$n, 'trim|max_length[100]');
        $v->set_rules($p.'[house_type]', 'House Type'.$n, 'required|in_config_list[house_types]');
        $v->set_rules($p.'[built_status]', 'Built Status'.$n, 'required|in_config_list[built_statuses]');
    }

    /**
     * Same plot chosen twice, the same new plot number twice, or the same block + house number twice.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function duplicate_plot_error(array $rows): ?string
    {
        $existing = array();
        $plot_nos = array();
        $house_nos = array();
        foreach ($rows as $i => $row)
        {
            $line = 'Plot row '.($i + 1);
            if ($row['mode'] === 'existing')
            {
                $id = (int) $row['house_id'];
                if (isset($existing[$id]))
                {
                    return $line.' selects the same plot as row '.($existing[$id] + 1).'.';
                }
                $existing[$id] = $i;
                continue;
            }
            $plot = strtolower(trim((string) $row['plot_no']));
            if (isset($plot_nos[$plot]))
            {
                return $line.' repeats plot number "'.trim((string) $row['plot_no']).'" from row '.($plot_nos[$plot] + 1).'.';
            }
            $plot_nos[$plot] = $i;
            $house = trim((string) ($row['house_no'] ?? ''));
            if ($house !== '')
            {
                $key = strtolower(trim((string) ($row['block'] ?? '')).'|'.$house);
                if (isset($house_nos[$key]))
                {
                    return $line.' repeats house number "'.$house.'" in the same block as row '.($house_nos[$key] + 1).'.';
                }
                $house_nos[$key] = $i;
            }
        }
        return NULL;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function collect_plot_rows(array $rows): array
    {
        $plots = array();
        foreach ($rows as $row)
        {
            $clean = function (string $key) use ($row): string {
                return trim(strip_tags((string) ($row[$key] ?? '')));
            };
            if ($row['mode'] === 'existing')
            {
                $plots[] = array('mode' => 'existing', 'house_id' => (int) $row['house_id'], 'occupancy_status' => $clean('occupancy_status'));
                continue;
            }
            $plots[] = array(
                'mode'             => 'new',
                'occupancy_status' => $clean('occupancy_status'),
                'house'            => array(
                    'plot_no'      => $clean('plot_no'),
                    'house_no'     => $clean('house_no') !== '' ? $clean('house_no') : NULL,
                    'block'        => $clean('block'),
                    'street'       => $clean('street') !== '' ? $clean('street') : NULL,
                    'house_type'   => $clean('house_type'),
                    'built_status' => $clean('built_status'),
                ),
            );
        }
        return $plots;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function owner_tenants(int $owner_id): array
    {
        return $this->db
            ->select('t.id, t.tenant_code, t.tenant_name, t.mobile, t.move_in_date, t.move_out_date, t.agreement_end, t.status, h.plot_no, h.house_no')
            ->from('tenants t')
            ->join('houses h', 'h.id = t.house_id')
            ->where('t.owner_id', $owner_id)
            ->order_by('t.status', 'ASC')
            ->order_by('t.move_in_date', 'DESC')
            ->get()->result_array();
    }

    private function set_owner_rules(): void
    {
        $v = $this->form_validation;
        $v->set_rules('owner_name', 'Owner Name', 'trim|required|min_length[2]|max_length[120]');
        $v->set_rules('co_owner_name', 'Co-owner Name', 'trim|max_length[120]');
        $v->set_rules('mobile', 'Mobile Number', 'trim|required|valid_mobile');
        $v->set_rules('whatsapp_no', 'WhatsApp Number', 'trim|valid_mobile');
        $v->set_rules('email', 'E-mail', 'trim|valid_email|max_length[150]');
        $v->set_rules('permanent_address', 'Permanent Address', 'trim|max_length[255]');
        $v->set_rules('residential_address', 'Residential Address', 'trim|max_length[255]');
        $v->set_rules('owner_type', 'Owner Type', 'required|in_config_list[owner_types]');
        $v->set_rules('joining_date', 'Joining Date', 'trim|valid_date_ymd');
        $v->set_rules('maintenance_start_date', 'Maintenance Start Date', 'trim|valid_date_ymd');
        $v->set_rules('status', 'Status', 'required|in_list[Active,Inactive]');
        $v->set_rules('remarks', 'Remarks', 'trim|max_length[500]');
    }

    /**
     * @return array<string, mixed>
     */
    private function collect_owner_input(): array
    {
        $post = function (string $key): string {
            return trim((string) $this->input->post($key, TRUE));
        };
        $mobile = normalize_mobile($post('mobile'));
        $whatsapp = normalize_mobile($post('whatsapp_no'));
        $email = strtolower($post('email'));

        return array(
            'owner_name'             => $post('owner_name'),
            'co_owner_name'          => $post('co_owner_name') !== '' ? $post('co_owner_name') : NULL,
            'mobile'                 => $mobile,
            'whatsapp_no'            => $whatsapp !== '' ? $whatsapp : $mobile,
            'email'                  => $email !== '' ? $email : NULL,
            'permanent_address'      => $post('permanent_address') !== '' ? $post('permanent_address') : NULL,
            'residential_address'    => $post('residential_address') !== '' ? $post('residential_address') : NULL,
            'owner_type'             => $post('owner_type'),
            'joining_date'           => $post('joining_date') !== '' ? $post('joining_date') : NULL,
            'maintenance_start_date' => $post('maintenance_start_date') !== '' ? $post('maintenance_start_date') : NULL,
            'status'                 => $post('status'),
            'remarks'                => $post('remarks') !== '' ? $post('remarks') : NULL,
        );
    }
}
