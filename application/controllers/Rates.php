<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Maintenance rates by plot category, with effective-from history.
 * Viewing needs maintenance.view; adding/deleting needs maintenance.rates.
 */
class Rates extends Auth_Controller
{
    protected $permission_map = array(
        'index'  => 'maintenance.view',
        'add'    => 'maintenance.rates',
        'delete' => 'maintenance.rates',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->model('rate_model');
        $this->load->library('form_validation');
    }

    public function index(): void
    {
        $last_billed = $this->rate_model->last_billed_month();
        $this->render('rates/index', array(
            'page_title'     => 'Maintenance Rates',
            'breadcrumbs'    => array(array('label' => 'Maintenance'), array('label' => 'Maintenance Rates')),
            'current'        => $this->rate_model->rates_for_month(),
            'history'        => $this->rate_model->history(),
            'last_billed'    => $last_billed,
            'earliest_month' => $this->earliest_allowed_month($last_billed),
            'plot_counts'    => $this->plot_counts(),
            'scripts'        => array('js/modules/rates.js'),
        ));
    }

    /**
     * POST: add a rate for one or both categories from a given month.
     */
    public function add(): void
    {
        $this->require_post();

        $this->form_validation->set_rules('plot_category', 'Plot Category', 'required|in_list[Both,Vacant Plot,Constructed]');
        $this->form_validation->set_rules('amount', 'Monthly Amount', 'trim|required|valid_amount');
        // Rules as an array: the regex contains "|", which would break a pipe-delimited rule string
        $this->form_validation->set_rules('effective_month', 'Effective From', array('trim', 'required', 'regex_match[/^20\d{2}-(0[1-9]|1[0-2])$/]'), array('regex_match' => 'Please choose a valid month.'));
        $this->form_validation->set_rules('remarks', 'Remarks', 'trim|max_length[255]');

        if ( ! $this->form_validation->run())
        {
            $this->session->set_flashdata('error', strip_tags(validation_errors(' ', ' ')));
            redirect('rates');
        }

        $category = (string) $this->input->post('plot_category');
        $categories = $category === 'Both' ? (array) $this->config->item('plot_categories') : array($category);
        $effective_from = $this->input->post('effective_month').'-01';
        $amount = from_paise((int) to_paise((string) $this->input->post('amount')));
        $remarks = trim((string) $this->input->post('remarks', TRUE));

        $earliest = $this->earliest_allowed_month($this->rate_model->last_billed_month());
        if ($effective_from < $earliest)
        {
            $this->session->set_flashdata('error', 'Bills up to '.date('F Y', strtotime($earliest.' -1 month')).' are already generated. A new rate can start from '.date('F Y', strtotime($earliest)).' or later.');
            redirect('rates');
        }

        foreach ($categories as $cat)
        {
            if ($this->rate_model->exists_for($cat, $effective_from))
            {
                $this->session->set_flashdata('error', 'A '.$cat.' rate starting '.date('F Y', strtotime($effective_from)).' already exists. Delete it first to change it.');
                redirect('rates');
            }
        }

        $this->db->trans_start();
        $ids = array();
        foreach ($categories as $cat)
        {
            $row = array(
                'plot_category'  => $cat,
                'amount'         => $amount,
                'effective_from' => $effective_from,
                'remarks'        => $remarks !== '' ? $remarks : NULL,
                'created_by'     => user_id(),
            );
            $id = $this->rate_model->insert($row);
            $ids[] = $id;
            $this->audit->log('Maintenance Rate Added', 'maintenance', $id, NULL, $row);
        }
        $this->db->trans_complete();

        if ( ! $this->db->trans_status())
        {
            $this->session->set_flashdata('error', 'The rate could not be saved.');
            redirect('rates');
        }

        $this->session->set_flashdata('success', implode(' and ', $categories).' rate set to '.money($amount).' per plot per month from '.date('F Y', strtotime($effective_from)).'.');
        redirect('rates');
    }

    /**
     * AJAX POST: delete a rate that has not been used by any bill and starts after the last billed month.
     */
    public function delete(int $id = 0): void
    {
        $this->require_ajax();
        $this->require_post();

        $rate = $this->rate_model->find($id);
        if ($rate === NULL)
        {
            $this->json_error('Rate not found.', 404);
        }
        if ($this->rate_model->bill_count($id) > 0)
        {
            $this->json_error('This rate has already been used for bills and cannot be deleted. Add a new rate from a later month instead.');
        }
        $others = $this->db->where('plot_category', $rate['plot_category'])->where('id !=', $id)->count_all_results('maintenance_rates');
        if ($others === 0)
        {
            $this->json_error('At least one '.$rate['plot_category'].' rate must remain.');
        }

        $this->rate_model->db->where('id', $id)->delete('maintenance_rates');
        $this->audit->log('Maintenance Rate Deleted', 'maintenance', $id, $rate, NULL);
        $this->json_success($rate['plot_category'].' rate from '.date('F Y', strtotime($rate['effective_from'])).' deleted.');
    }

    /* ------------------------------------------------------------------ */

    /**
     * New rates may start only in a month that has no bills yet.
     */
    private function earliest_allowed_month(?string $last_billed): string
    {
        if ($last_billed === NULL)
        {
            return date('Y-m-01');
        }
        return date('Y-m-01', strtotime($last_billed.' +1 month'));
    }

    /**
     * Active, owned plots per category (what the next generation would bill).
     *
     * @return array<string, int>
     */
    private function plot_counts(): array
    {
        $rows = $this->db->query(
            'SELECT '.$this->rate_model->category_sql('h.built_status').' AS category, COUNT(*) AS plots
             FROM houses h JOIN owners o ON o.id = h.owner_id
             WHERE h.status = \'Active\' AND o.status = \'Active\' AND o.is_deleted = 0
             GROUP BY category'
        )->result_array();
        $counts = array_fill_keys((array) $this->config->item('plot_categories'), 0);
        foreach ($rows as $r)
        {
            $counts[$r['category']] = (int) $r['plots'];
        }
        return $counts;
    }
}
