<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base controller for every ROWA Portal controller.
 *
 * - Sends security headers (works for both Apache and Nginx).
 * - Renders pages inside the shared layouts.
 * - Provides consistent JSON responses for AJAX endpoints.
 *
 * Authenticated controllers extend Auth_Controller (application/core/Auth_Controller.php),
 * which adds login, idle-timeout and permission checks on top of this class.
 */
#[\AllowDynamicProperties]
class MY_Controller extends CI_Controller
{
    /**
     * Data shared with every view rendered by this controller.
     *
     * @var array<string, mixed>
     */
    protected $data = array();

    public function __construct()
    {
        parent::__construct();
        $this->send_security_headers();

        $this->data['page_title']  = '';
        $this->data['breadcrumbs'] = array();
        $this->data['scripts']     = array();
        $this->data['styles']      = array();
    }

    /**
     * Every request is dispatched through here (CodeIgniter calls _remap
     * instead of the action). Applies CodeIgniter's own routing rules, plus:
     * required URL segments must be present and int parameters must be
     * digits - so "/receipts/view/abc" is a 404, not a PHP TypeError.
     *
     * @param array<int, string> $params
     * @return mixed
     */
    public function _remap(string $method, array $params = array())
    {
        if ( ! method_exists($this, $method))
        {
            show_404();
        }
        $action = new ReflectionMethod($this, $method);
        $base = array('CI_Controller', 'MY_Controller', 'Auth_Controller');
        if ( ! $action->isPublic() || $action->isStatic() || $action->isConstructor()
            || in_array($action->getDeclaringClass()->getName(), $base, TRUE)
            || count($params) < $action->getNumberOfRequiredParameters())
        {
            show_404();
        }
        foreach ($action->getParameters() as $i => $param)
        {
            if ( ! array_key_exists($i, $params))
            {
                break;
            }
            $type = $param->getType();
            if ($type instanceof ReflectionNamedType && $type->getName() === 'int')
            {
                if ( ! preg_match('/^\d{1,9}$/', (string) $params[$i]))
                {
                    show_404();
                }
                $params[$i] = (int) $params[$i];
            }
        }
        return $action->invokeArgs($this, $params);
    }

    /**
     * Render a page: layouts/<layout>_header -> view -> layouts/<layout>_footer.
     * Layout "main" also renders the sidebar and navbar.
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = array(), string $layout = 'main'): void
    {
        $data = array_merge($this->data, $data);

        if ($layout === 'main')
        {
            $this->config->load('menu', TRUE);
            $data['menu'] = visible_menu(
                (array) $this->config->item('sidebar_menu', 'menu'),
                ! empty($data['menu_preview_all'])
            );
            $this->load->view('layouts/header', $data);
            $this->load->view('layouts/sidebar', $data);
            $this->load->view('layouts/navbar', $data);
            $this->load->view($view, $data);
            $this->load->view('layouts/footer', $data);
            return;
        }

        $this->load->view('layouts/'.$layout.'_header', $data);
        $this->load->view($view, $data);
        $this->load->view('layouts/'.$layout.'_footer', $data);
    }

    /**
     * Send a JSON response and stop.
     *
     * @param array<string, mixed> $payload
     */
    protected function json(array $payload, int $status = 200): void
    {
        $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
            ->_display();
        exit;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function json_success(string $message, array $data = array()): void
    {
        $this->json(array('success' => TRUE, 'message' => $message, 'data' => $data));
    }

    /**
     * @param array<string, string> $errors Field => message
     */
    protected function json_error(string $message, int $status = 422, array $errors = array()): void
    {
        $this->json(array('success' => FALSE, 'message' => $message, 'errors' => $errors), $status);
    }

    /**
     * Reject non-AJAX access to AJAX-only endpoints.
     */
    protected function require_ajax(): void
    {
        if ( ! $this->input->is_ajax_request())
        {
            show_404();
        }
    }

    /**
     * Reject anything other than POST for state-changing actions (CSRF is verified by CI on POST).
     */
    protected function require_post(): void
    {
        if ($this->input->method() !== 'post')
        {
            if ($this->input->is_ajax_request())
            {
                $this->json_error('Invalid request method.', 405);
            }
            show_error('Invalid request method.', 405);
        }
    }

    /**
     * Collect form_validation errors as field => message for JSON responses.
     *
     * @param string[] $fields
     * @return array<string, string>
     */
    protected function validation_errors_array(array $fields): array
    {
        $errors = array();
        foreach ($fields as $field)
        {
            $message = form_error($field, '', '');
            if ($message !== '')
            {
                $errors[$field] = strip_tags($message);
            }
        }
        return $errors;
    }

    private function send_security_headers(): void
    {
        $nonce = csp_nonce();
        $csp = implode('; ', array(
            "default-src 'self'",
            "script-src 'self' 'nonce-".$nonce."'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
        ));

        $this->output->set_header('Content-Security-Policy: '.$csp);
        $this->output->set_header('X-Frame-Options: SAMEORIGIN');
        $this->output->set_header('X-Content-Type-Options: nosniff');
        $this->output->set_header('Referrer-Policy: strict-origin-when-cross-origin');
        $this->output->set_header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        if (is_https())
        {
            $this->output->set_header('Strict-Transport-Security: max-age=31536000');
        }
    }
}

// CodeIgniter only auto-loads MY_Controller.php from application/core, so load the
// authenticated base controller here.
require_once APPPATH.'core/Auth_Controller.php';
