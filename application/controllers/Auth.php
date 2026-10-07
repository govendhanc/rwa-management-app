<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Public authentication pages: login, logout, forgot/reset password, and the
 * change-password page (which requires login but must work while a password
 * change is being forced, so it does not extend Auth_Controller).
 */
class Auth extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library(array('auth_lib', 'form_validation'));
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate');
    }

    public function index(): void
    {
        redirect('auth/login');
    }

    public function login(): void
    {
        if ($this->auth_lib->check())
        {
            redirect('dashboard');
        }

        $error = NULL;

        if ($this->input->method() === 'post')
        {
            $this->form_validation->set_rules('identifier', 'Username or E-mail', 'trim|required|max_length[150]');
            $this->form_validation->set_rules('password', 'Password', 'required|max_length[72]');

            if ($this->form_validation->run())
            {
                $result = $this->auth_lib->attempt(
                    (string) $this->input->post('identifier', TRUE),
                    (string) $this->input->post('password'),
                    $this->input->post('remember') === '1'
                );

                if ($result['success'])
                {
                    $this->session->set_flashdata('success', $result['message']);
                    redirect($this->pull_return_to());
                }
                $error = $result['message'];
            }
            else
            {
                $error = 'Please enter your username/e-mail and password.';
            }
        }

        $this->render('auth/login', array(
            'page_title' => 'Sign in to your account',
            'error'      => $error,
            'timed_out'  => $this->input->get('timeout') === '1',
            'identifier' => (string) $this->input->post('identifier', TRUE),
        ), 'auth');
    }

    public function logout(): void
    {
        if ($this->input->method() !== 'post')
        {
            redirect('dashboard');
        }
        $this->auth_lib->logout();
        $this->session->set_flashdata('success', 'You have been logged out.');
        redirect('auth/login');
    }

    public function change_password(): void
    {
        if ( ! $this->auth_lib->check())
        {
            redirect('auth/login');
        }

        $user = current_user();
        $error = NULL;

        if ($this->input->method() === 'post')
        {
            $this->form_validation->set_rules('current_password', 'Current Password', 'required|max_length[72]');
            $this->form_validation->set_rules('new_password', 'New Password', 'required|strong_password');
            $this->form_validation->set_rules('confirm_password', 'Confirm Password', 'required|matches[new_password]', array('matches' => 'The passwords do not match.'));

            if ($this->form_validation->run())
            {
                $result = $this->auth_lib->change_password(
                    (int) $user['id'],
                    (string) $this->input->post('current_password'),
                    (string) $this->input->post('new_password')
                );
                if ($result['success'])
                {
                    $this->session->set_flashdata('success', $result['message']);
                    redirect('dashboard');
                }
                $error = $result['message'];
            }
        }

        $this->render('auth/change_password', array(
            'page_title' => 'Change Password',
            'forced'     => (int) $user['must_change_password'] === 1,
            'error'      => $error,
        ), 'auth');
    }

    public function forgot_password(): void
    {
        if ($this->input->method() === 'post')
        {
            $this->form_validation->set_rules('identifier', 'Username or E-mail', 'trim|required|max_length[150]');
            if ($this->form_validation->run())
            {
                $result = $this->auth_lib->request_password_reset((string) $this->input->post('identifier', TRUE));
                $this->session->set_flashdata('success', 'If an active account matches, a password reset link has been sent to its registered e-mail address. The link is valid for '.Auth_lib::RESET_MINUTES.' minutes.');
                if ($result['dev_link'] !== NULL)
                {
                    $this->session->set_flashdata('dev_reset_link', $result['dev_link']);
                }
                redirect('auth/forgot-password');
            }
        }

        $this->render('auth/forgot_password', array(
            'page_title'     => 'Forgot Password',
            'dev_reset_link' => $this->session->flashdata('dev_reset_link'),
        ), 'auth');
    }

    public function reset_password(string $token = ''): void
    {
        $reset = $this->auth_lib->find_reset($token);
        $error = NULL;

        if ($reset !== NULL && $this->input->method() === 'post')
        {
            $this->form_validation->set_rules('new_password', 'New Password', 'required|strong_password');
            $this->form_validation->set_rules('confirm_password', 'Confirm Password', 'required|matches[new_password]', array('matches' => 'The passwords do not match.'));

            if ($this->form_validation->run())
            {
                $result = $this->auth_lib->reset_password($token, (string) $this->input->post('new_password'));
                if ($result['success'])
                {
                    $this->session->set_flashdata('success', $result['message']);
                    redirect('auth/login');
                }
                $error = $result['message'];
            }
        }

        $this->render('auth/reset_password', array(
            'page_title' => 'Reset Password',
            'valid'      => $reset !== NULL,
            'token'      => $token,
            'error'      => $error,
        ), 'auth');
    }

    /**
     * Internal return URL saved before the login redirect (never an external URL).
     */
    private function pull_return_to(): string
    {
        $path = (string) $this->session->userdata('auth_return_to');
        $this->session->unset_userdata('auth_return_to');
        if ($path === '' || preg_match('#^[a-z0-9/_\-]+$#i', $path) !== 1 || strpos($path, 'auth') === 0)
        {
            return 'dashboard';
        }
        return $path;
    }
}
