<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Dashboard: owner, maintenance and financial statistics with a month filter.
 */
class Dashboard extends Auth_Controller
{
    protected $permission_map = array('index' => 'dashboard.view');

    public function __construct()
    {
        parent::__construct();
        $this->load->model('dashboard_model');
    }

    public function index(): void
    {
        list($year, $month) = $this->selected_period();

        $trend = $this->dashboard_model->trend($year, $month, 12);

        $this->render('dashboard/index', array(
            'page_title'   => 'Dashboard',
            'year'         => $year,
            'month'        => $month,
            'period'       => sprintf('%04d-%02d', $year, $month),
            'period_label' => period_label($year, $month),
            'owners'       => $this->dashboard_model->owner_stats(),
            'maintenance'  => $this->dashboard_model->month_stats($year, $month),
            'finance'      => $this->dashboard_model->financial_totals(),
            'trend'        => $trend,
            'recent'       => can('payments.view') ? $this->dashboard_model->recent_payments(6) : array(),
            'top_dues'     => can('reports.view') ? $this->dashboard_model->top_outstanding(6) : array(),
            'scripts'      => array('vendor/chartjs/chart.umd.js', 'js/modules/dashboard.js'),
        ));
    }

    /**
     * ?period=YYYY-MM (defaults to the current month; future months are not allowed).
     *
     * @return array{0: int, 1: int}
     */
    private function selected_period(): array
    {
        $period = (string) $this->input->get('period');
        if (preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $period, $m))
        {
            $year = (int) $m[1];
            $month = (int) $m[2];
            if (sprintf('%04d-%02d', $year, $month) <= date('Y-m', strtotime('+1 month')))
            {
                return array($year, $month);
            }
        }
        return array((int) date('Y'), (int) date('n'));
    }
}
