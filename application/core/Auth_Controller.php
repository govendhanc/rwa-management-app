<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base controller for every page that requires a logged-in user.
 *
 * On each request:
 *   1. Validates the session (remember-me restore, idle timeout, user still active).
 *   2. Forces a password change when must_change_password is set.
 *   3. Provides require_permission() for per-action RBAC checks.
 *
 * Every controller action MUST call $this->require_permission('<module>.<action>')
 * (or declare $this->permission_map) before doing any work.
 */
#[\AllowDynamicProperties]
class Auth_Controller extends MY_Controller
{
    /**
     * Optional method => permission map checked automatically before the action runs.
     * Example: protected $permission_map = array('index' => 'owners.view', 'store' => 'owners.create');
     *
     * @var array<string, string>
     */
    protected $permission_map = array();

    public function __construct()
    {
        parent::__construct();
        $this->load->library('auth_lib');
        $this->load->model('notification_model');

        if ( ! $this->auth_lib->check())
        {
            $this->reject_unauthenticated();
        }

        $user = current_user();
        if ((int) $user['must_change_password'] === 1)
        {
            if ($this->input->is_ajax_request())
            {
                $this->json(array('success' => FALSE, 'message' => 'Please change your password first.', 'redirect' => site_url('auth/change-password')), 403);
            }
            $this->session->set_flashdata('warning', 'Please set a new password before continuing.');
            redirect('auth/change-password');
        }

        $method = $this->router->fetch_method();
        if (isset($this->permission_map[$method]))
        {
            $this->require_permission($this->permission_map[$method]);
        }

        $this->data['notification_count'] = $this->notification_model->unread_count((int) $user['id']);
    }

    /**
     * Stop with 403 unless the user holds the permission.
     */
    protected function require_permission(string $permission): void
    {
        if ( ! can($permission))
        {
            $this->deny($permission);
        }
    }

    /**
     * @param string[] $permissions
     */
    protected function require_any_permission(array $permissions): void
    {
        if ( ! can_any($permissions))
        {
            $this->deny(implode(' / ', $permissions));
        }
    }

    protected function deny(string $permission = ''): void
    {
        log_message('error', 'Access denied: user '.user_id().' lacks '.$permission.' for '.$this->uri->uri_string());

        if ($this->input->is_ajax_request())
        {
            $this->json(array('success' => FALSE, 'message' => 'You do not have permission to perform this action.'), 403);
        }

        $this->output->set_status_header(403);
        $this->render('errors/forbidden', array('page_title' => 'Access Denied'));
        $this->output->_display();
        exit;
    }

    private function reject_unauthenticated(): void
    {
        $timed_out = $this->auth_lib->timed_out();
        $login_url = site_url('auth/login'.($timed_out ? '?timeout=1' : ''));

        if ($this->input->is_ajax_request())
        {
            $this->json(array(
                'success'  => FALSE,
                'message'  => $timed_out ? 'Your session has expired due to inactivity. Please log in again.' : 'Please log in to continue.',
                'redirect' => $login_url,
            ), 401);
        }

        // Remember where the user was going (GET pages only, internal paths only)
        if ($this->input->method() === 'get')
        {
            $this->session->set_userdata('auth_return_to', $this->uri->uri_string());
        }
        redirect($login_url);
    }
}
