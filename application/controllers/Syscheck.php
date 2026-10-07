<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * System Check - verifies the server environment, database and folders.
 *
 * Available without login ONLY when APP_ENV=development. In any other
 * environment the page returns 404. Once authentication is in place a
 * Super Admin can still open it in production.
 *
 * URLs: /syscheck (also /system-check), /syscheck/preview (layout preview)
 */
class Syscheck extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();

        $allowed = ENVIRONMENT === 'development' || is_super_admin();
        if ( ! $allowed)
        {
            show_404();
        }
    }

    public function index(): void
    {
        $groups = array(
            'PHP'         => $this->check_php(),
            'Database'    => $this->check_database(),
            'Folders'     => $this->check_folders(),
            'Security'    => $this->check_security(),
            'Libraries'   => $this->check_libraries(),
        );

        $summary = array('ok' => 0, 'warning' => 0, 'error' => 0);
        foreach ($groups as $checks)
        {
            foreach ($checks as $check)
            {
                $summary[$check['status']]++;
            }
        }

        $this->render('syscheck/index', array(
            'page_title' => 'System Check',
            'groups'     => $groups,
            'summary'    => $summary,
            'auth_wide'  => TRUE,
        ), 'auth');
    }

    /**
     * Shows the authenticated layout (sidebar, navbar, cards, DataTable, toasts, confirm dialog)
     * with every menu item visible, so the UI shell can be reviewed before login exists.
     */
    public function preview(): void
    {
        if (ENVIRONMENT !== 'development')
        {
            show_404();
        }

        $owners = $this->db
            ->select('o.owner_code, o.owner_name, o.mobile, h.plot_no, h.house_no, h.occupancy_status, h.built_status, b.outstanding, o.status')
            ->from('owners o')
            ->join('houses h', 'h.owner_id = o.id', 'left')
            ->join('v_owner_balances b', 'b.owner_id = o.id', 'left')
            ->where('o.is_deleted', 0)
            ->order_by('o.id')
            ->get()->result_array();

        $totals = $this->db->query(
            "SELECT
                (SELECT COUNT(*) FROM owners WHERE is_deleted = 0 AND status = 'Active') AS active_owners,
                (SELECT COALESCE(SUM(balance_amount), 0) FROM maintenance WHERE record_status = 'Active') AS outstanding,
                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'Active') AS collected,
                (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE status = 'Active') AS expenses"
        )->row_array();

        $this->render('syscheck/preview', array(
            'page_title'       => 'Layout Preview',
            'breadcrumbs'      => array(array('label' => 'System Check', 'url' => 'syscheck'), array('label' => 'Layout Preview')),
            'menu_preview_all' => TRUE,
            'owners'           => $owners,
            'totals'           => $totals,
            'scripts'          => array('js/modules/syscheck-preview.js'),
        ));
    }

    /* ------------------------------------------------------------------ */

    /**
     * @return array<int, array{label: string, status: string, detail: string}>
     */
    private function check_php(): array
    {
        $checks = array();
        $min = (string) $this->config->item('min_php_version');
        $checks[] = $this->result(
            'PHP version '.PHP_VERSION,
            version_compare(PHP_VERSION, $min, '>=') ? 'ok' : 'error',
            'Minimum required: '.$min
        );

        foreach ((array) $this->config->item('required_php_extensions') as $ext)
        {
            $checks[] = $this->result('Extension: '.$ext, extension_loaded($ext) ? 'ok' : 'error', extension_loaded($ext) ? 'Loaded' : 'Required - enable it in php.ini');
        }

        foreach ((array) $this->config->item('recommended_php_extensions') as $ext => $why)
        {
            $checks[] = $this->result('Extension: '.$ext, extension_loaded($ext) ? 'ok' : 'warning', extension_loaded($ext) ? 'Loaded' : $why);
        }

        $checks[] = $this->result('Timezone', 'ok', date_default_timezone_get().' (now '.date('d-M-Y h:i A').')');
        $checks[] = $this->result('CodeIgniter', 'ok', 'Version '.CI_VERSION);
        $checks[] = $this->result('Environment', ENVIRONMENT === 'production' ? 'ok' : 'warning', 'APP_ENV='.ENVIRONMENT.(ENVIRONMENT === 'production' ? '' : ' - use production on live servers'));
        $checks[] = $this->result(
            'Upload limits',
            'ok',
            'upload_max_filesize='.ini_get('upload_max_filesize').', post_max_size='.ini_get('post_max_size')
        );

        return $checks;
    }

    /**
     * @return array<int, array{label: string, status: string, detail: string}>
     */
    private function check_database(): array
    {
        $checks = array();
        $version = (string) $this->db->version();
        $is_maria = stripos($version, 'mariadb') !== FALSE;
        $numeric = preg_replace('/^(\d+\.\d+\.\d+).*$/', '$1', $version);
        $ok_version = $is_maria ? version_compare($numeric, '10.4.0', '>=') : version_compare($numeric, '8.0.16', '>=');
        $checks[] = $this->result(
            ($is_maria ? 'MariaDB ' : 'MySQL ').$version,
            $ok_version ? 'ok' : 'error',
            $is_maria ? 'Minimum MariaDB 10.4' : 'Minimum MySQL 8.0.16 (CHECK constraints)'
        );

        $db_name = $this->db->database;
        $tables = (int) $this->db->query(
            "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE'",
            array($db_name)
        )->row()->c;
        $checks[] = $this->result('Tables in '.$db_name, $tables >= 30 ? 'ok' : 'error', $tables.' of 30 found'.($tables < 30 ? ' - import database/01_schema.sql' : ''));

        $views = (int) $this->db->query(
            'SELECT COUNT(*) AS c FROM information_schema.views WHERE table_schema = ?',
            array($db_name)
        )->row()->c;
        $checks[] = $this->result('Reporting views', $views >= 2 ? 'ok' : 'error', $views.' of 2 found');

        if ($tables >= 30)
        {
            $roles = $this->db->count_all('roles');
            $perms = $this->db->count_all('permissions');
            $admin = $this->db->where('role_id', 1)->where('status', 'Active')->count_all_results('users');
            $checks[] = $this->result('Master data', ($roles >= 4 && $perms > 0 && $admin > 0) ? 'ok' : 'error', $roles.' roles, '.$perms.' permissions, '.$admin.' active Super Admin(s)'.($admin === 0 ? ' - import database/02_seed_master.sql' : ''));

            $settings = $this->db->count_all('association_settings');
            $checks[] = $this->result('Association settings', $settings === 1 ? 'ok' : 'error', $settings === 1 ? association('association_name') : 'Missing - import database/02_seed_master.sql');

            $this->load->model('rate_model');
            foreach ($this->rate_model->rates_for_month() as $category => $rate)
            {
                $checks[] = $this->result('Maintenance rate: '.$category, $rate !== NULL ? 'ok' : 'warning', $rate !== NULL ? money($rate['amount']).' per plot per month' : 'Not set - add it under Maintenance > Maintenance Rates');
            }
        }

        $charset = $this->db->query('SELECT @@character_set_connection AS cs')->row()->cs;
        $checks[] = $this->result('Connection charset', $charset === 'utf8mb4' ? 'ok' : 'warning', (string) $charset);

        $mode = (string) $this->db->query('SELECT @@SESSION.sql_mode AS m')->row()->m;
        $checks[] = $this->result('Strict SQL mode', stripos($mode, 'STRICT_') !== FALSE ? 'ok' : 'warning', $mode !== '' ? $mode : '(empty)');

        $checks[] = $this->result('Session storage', 'ok', 'Database (ci_sessions) - session id '.substr(session_id(), 0, 8).'...');

        return $checks;
    }

    /**
     * @return array<int, array{label: string, status: string, detail: string}>
     */
    private function check_folders(): array
    {
        $checks = array();
        foreach ((array) $this->config->item('writable_paths') as $label => $path)
        {
            $exists = is_dir($path);
            $writable = $exists && is_really_writable($path);
            $checks[] = $this->result($label, $writable ? 'ok' : 'error', $writable ? 'Writable' : ($exists ? 'Not writable by the web server user' : 'Folder missing'));
        }
        $inside_public = strpos(realpath(STORAGEPATH) ?: STORAGEPATH, realpath(FCPATH) ?: FCPATH) === 0;
        $checks[] = $this->result('storage/ outside web root', $inside_public ? 'error' : 'ok', $inside_public ? 'storage is inside the public folder!' : 'Not reachable from the browser');
        return $checks;
    }

    /**
     * @return array<int, array{label: string, status: string, detail: string}>
     */
    private function check_security(): array
    {
        $checks = array();
        $key = (string) $this->config->item('encryption_key');
        $checks[] = $this->result('Encryption key', strlen($key) === 32 ? 'ok' : 'error', strlen($key) === 32 ? 'Configured' : 'Set APP_ENCRYPTION_KEY (64 hex chars) in .env');
        $checks[] = $this->result('.env file', is_file(ROOTPATH.'.env') ? 'ok' : 'warning', is_file(ROOTPATH.'.env') ? 'Found (outside web root)' : 'Not found - using server environment variables');
        $checks[] = $this->result('CSRF protection', $this->config->item('csrf_protection') ? 'ok' : 'error', $this->config->item('csrf_protection') ? 'Enabled' : 'Disabled');
        $checks[] = $this->result('HttpOnly cookies', $this->config->item('cookie_httponly') ? 'ok' : 'error', $this->config->item('cookie_httponly') ? 'Enabled' : 'Disabled');
        $https = is_https();
        $secure = (bool) $this->config->item('cookie_secure');
        $checks[] = $this->result('HTTPS', $https ? 'ok' : 'warning', $https ? 'Connection is secure'.($secure ? '' : ' - set APP_FORCE_HTTPS=true') : 'Not using HTTPS - required on a live server');
        $display = (string) ini_get('display_errors');
        $checks[] = $this->result('display_errors', (ENVIRONMENT !== 'production' || in_array(strtolower($display), array('0', 'off', ''), TRUE)) ? 'ok' : 'error', $display === '' ? 'off' : $display);
        $checks[] = $this->result('Base URL', 'ok', base_url());
        return $checks;
    }

    /**
     * @return array<int, array{label: string, status: string, detail: string}>
     */
    private function check_libraries(): array
    {
        $checks = array();
        $assets = array(
            'Bootstrap 5'   => 'vendor/bootstrap/js/bootstrap.bundle.min.js',
            'jQuery'        => 'vendor/jquery/jquery.min.js',
            'DataTables'    => 'vendor/datatables/js/jquery.dataTables.min.js',
            'Chart.js'      => 'vendor/chartjs/chart.umd.js',
            'Font Awesome'  => 'vendor/fontawesome/css/all.min.css',
            'JSZip / pdfmake (exports)' => 'vendor/pdfmake/pdfmake.min.js',
        );
        foreach ($assets as $label => $file)
        {
            $exists = is_file(FCPATH.'assets/'.$file);
            $checks[] = $this->result($label, $exists ? 'ok' : 'error', $exists ? 'public/assets/'.$file : 'Missing public/assets/'.$file);
        }
        $dompdf = is_file(APPPATH.'third_party/dompdf/autoload.inc.php');
        $checks[] = $this->result('Dompdf (PDF receipts)', $dompdf ? 'ok' : 'warning', $dompdf ? 'application/third_party/dompdf' : 'Installed in Step 7 (Receipts)');
        return $checks;
    }

    /**
     * @return array{label: string, status: string, detail: string}
     */
    private function result(string $label, string $status, string $detail): array
    {
        return array('label' => $label, 'status' => $status, 'detail' => $detail);
    }
}
