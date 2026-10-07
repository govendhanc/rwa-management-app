<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Settings > Association (details shown on receipts/reports, logo, bank/UPI, receipt prefix)
 * and Settings > System (session/lockout/date format). Super Admin only (settings.manage).
 */
class Settings extends Auth_Controller
{
    const DATE_FORMATS = array('d-M-Y' => '07-Oct-2026', 'd/m/Y' => '07/10/2026', 'd-m-Y' => '07-10-2026', 'd M Y' => '07 Oct 2026', 'Y-m-d' => '2026-10-07');

    protected $permission_map = array(
        'index'  => 'settings.manage',
        'system' => 'settings.manage',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->library('form_validation');
    }

    public function index(): void
    {
        $current = $this->settings_model->association();

        if ($this->input->method() === 'post')
        {
            $v = $this->form_validation;
            $v->set_rules('association_name', 'Association Name', 'trim|required|min_length[3]|max_length[200]');
            $v->set_rules('short_name', 'Short Name', 'trim|max_length[60]');
            $v->set_rules('registration_no', 'Registration Number', 'trim|max_length[60]');
            $v->set_rules('registration_label', 'Registration label', 'trim|required|max_length[30]');
            $v->set_rules('address', 'Address', 'trim|max_length[255]');
            $v->set_rules('city', 'City', 'trim|max_length[80]');
            $v->set_rules('district', 'District', 'trim|max_length[80]');
            $v->set_rules('state', 'State', 'trim|max_length[80]');
            $v->set_rules('pincode', 'PIN Code', 'trim|regex_match[/^\d{6}$/]', array('regex_match' => 'The PIN Code must be 6 digits.'));
            $v->set_rules('mobile', 'Mobile', 'trim|valid_mobile');
            $v->set_rules('email', 'Email', 'trim|valid_email|max_length[150]');
            $v->set_rules('website', 'Website', 'trim|valid_url|max_length[150]');
            $v->set_rules('bank_name', 'Bank Name', 'trim|max_length[100]');
            $v->set_rules('bank_account_no', 'Bank Account', 'trim|regex_match[/^\d{6,20}$/]', array('regex_match' => 'The bank account number must be 6-20 digits.'));
            $v->set_rules('bank_ifsc', 'IFSC', 'trim|regex_match[/^[A-Za-z]{4}0[A-Za-z0-9]{6}$/]', array('regex_match' => 'The IFSC must look like ABCD0123456.'));
            $v->set_rules('bank_branch', 'Branch', 'trim|max_length[100]');
            $v->set_rules('upi_id', 'UPI ID', 'trim|regex_match[/^[A-Za-z0-9.\-_]{2,64}@[A-Za-z]{2,64}$/]', array('regex_match' => 'The UPI ID must look like name@bank.'));
            $v->set_rules('default_due_day', 'Default due day', 'required|is_natural_no_zero|less_than_equal_to[28]');
            $v->set_rules('receipt_prefix', 'Receipt Prefix', 'trim|required|regex_match[/^[A-Z]{2,6}$/]', array('regex_match' => 'The receipt prefix must be 2-6 capital letters, e.g. REC.'));
            $v->set_rules('receipt_footer_note', 'Receipt footer', 'trim|max_length[255]');

            if ($v->run())
            {
                $data = array();
                foreach (array('association_name', 'short_name', 'registration_no', 'address', 'city', 'district', 'state', 'pincode', 'mobile', 'email', 'website',
                    'bank_name', 'bank_account_no', 'bank_ifsc', 'bank_branch', 'upi_id', 'receipt_prefix', 'receipt_footer_note') as $k)
                {
                    $value = trim((string) $this->input->post($k, TRUE));
                    $data[$k] = $value !== '' ? $value : NULL;
                }
                $data['association_name'] = (string) $data['association_name'];
                $data['receipt_prefix'] = (string) $data['receipt_prefix'];
                $data['mobile'] = $data['mobile'] !== NULL ? normalize_mobile($data['mobile']) : NULL;
                $data['email'] = $data['email'] !== NULL ? strtolower($data['email']) : NULL;
                $data['bank_ifsc'] = $data['bank_ifsc'] !== NULL ? strtoupper($data['bank_ifsc']) : NULL;
                $data['default_due_day'] = (int) $this->input->post('default_due_day');

                $logo = $this->handle_logo($current);
                if ($logo['error'] !== NULL)
                {
                    $this->data['form_error'] = $logo['error'];
                }
                else
                {
                    if ($logo['path'] !== FALSE)
                    {
                        $data['logo_path'] = $logo['path'];
                    }
                    $this->settings_model->update_association($data, (int) user_id());
                    $label = trim((string) $this->input->post('registration_label', TRUE));
                    $old_label = $this->settings_model->get('registration_label');
                    $this->settings_model->set('registration_label', $label);
                    $this->audit->log('Settings Updated', 'settings', 'association', $current + array('registration_label' => $old_label), $data + array('registration_label' => $label));
                    if ($logo['path'] !== FALSE && ! empty($current['logo_path']) && $current['logo_path'] !== $logo['path'])
                    {
                        $this->delete_logo_file($current['logo_path']);
                    }
                    $this->session->set_flashdata('success', 'Association settings saved. Receipts, reports and messages use the new details.');
                    redirect('settings');
                }
            }
        }

        $this->render('settings/association', array(
            'page_title'  => 'Association Settings',
            'breadcrumbs' => array(array('label' => 'Settings'), array('label' => 'Association')),
            's'           => $current,
            'reg_label'   => $this->settings_model->get('registration_label', 'Reg. No'),
            'gd_loaded'   => extension_loaded('gd'),
        ));
    }

    public function system(): void
    {
        $keys = array('session_timeout_minutes', 'max_login_attempts', 'lockout_minutes', 'date_format');
        if ($this->input->method() === 'post')
        {
            $v = $this->form_validation;
            $v->set_rules('session_timeout_minutes', 'Idle timeout', 'required|is_natural_no_zero|greater_than_equal_to[5]|less_than_equal_to[480]');
            $v->set_rules('max_login_attempts', 'Failed attempts before lockout', 'required|is_natural_no_zero|greater_than_equal_to[3]|less_than_equal_to[20]');
            $v->set_rules('lockout_minutes', 'Lockout duration', 'required|is_natural_no_zero|less_than_equal_to[1440]');
            $v->set_rules('date_format', 'Date format', 'required|in_list['.implode(',', array_keys(self::DATE_FORMATS)).']');
            if ($v->run())
            {
                $old = array();
                $new = array();
                foreach ($keys as $k)
                {
                    $old[$k] = $this->settings_model->get($k);
                    $new[$k] = (string) $this->input->post($k);
                    $this->settings_model->set($k, $new[$k]);
                }
                $this->audit->log('Settings Updated', 'settings', 'system', $old, $new);
                $this->session->set_flashdata('success', 'System settings saved.');
                redirect('settings/system');
            }
        }
        $values = array();
        foreach ($keys as $k)
        {
            $values[$k] = $this->settings_model->get($k);
        }
        $this->render('settings/system', array(
            'page_title'   => 'System Settings',
            'breadcrumbs'  => array(array('label' => 'Settings'), array('label' => 'System')),
            'values'       => $values,
            'date_formats' => self::DATE_FORMATS,
        ));
    }

    /* ------------------------------------------------------------------ */

    /**
     * Process the optional logo upload / removal.
     *
     * @param array<string, mixed> $current
     * @return array{path: string|false|null, error: string|null} path FALSE = unchanged, NULL = removed
     */
    private function handle_logo(array $current): array
    {
        if ($this->input->post('remove_logo') === '1')
        {
            return array('path' => NULL, 'error' => NULL);
        }
        if ( ! isset($_FILES['logo']) || (int) $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE)
        {
            return array('path' => FALSE, 'error' => NULL);
        }
        $file = $_FILES['logo'];
        $max = (int) $this->config->item('upload_logo_max_kb');
        if ((int) $file['error'] !== UPLOAD_ERR_OK || ! is_uploaded_file($file['tmp_name']))
        {
            return array('path' => FALSE, 'error' => 'The logo could not be uploaded (max '.$max.' KB).');
        }
        if ((int) $file['size'] > $max * 1024)
        {
            return array('path' => FALSE, 'error' => 'The logo is too large (max '.$max.' KB).');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        $ext = array('image/jpeg' => 'jpg', 'image/png' => 'png')[$mime] ?? NULL;
        $info = @getimagesize($file['tmp_name']);
        if ($ext === NULL || $info === FALSE || $info[0] < 32 || $info[1] < 32 || $info[0] > 4000 || $info[1] > 4000)
        {
            return array('path' => FALSE, 'error' => 'The logo must be a JPG or PNG image between 32 and 4000 pixels.');
        }
        $name = 'logo-'.bin2hex(random_bytes(6)).'.'.$ext;
        $target = FCPATH.'uploads'.DIRECTORY_SEPARATOR.'branding'.DIRECTORY_SEPARATOR.$name;
        if ( ! move_uploaded_file($file['tmp_name'], $target))
        {
            return array('path' => FALSE, 'error' => 'The logo could not be saved (check folder permissions).');
        }
        return array('path' => 'uploads/branding/'.$name, 'error' => NULL);
    }

    private function delete_logo_file(string $relative): void
    {
        if (preg_match('#^uploads/branding/[A-Za-z0-9._-]+\.(jpe?g|png)$#', $relative))
        {
            $file = FCPATH.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (is_file($file))
            {
                @unlink($file);
            }
        }
    }
}
