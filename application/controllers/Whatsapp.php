<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * WhatsApp: message templates, message log, settings (mode / Cloud API) and test send.
 */
class Whatsapp extends Auth_Controller
{
    protected $permission_map = array(
        'index'     => 'whatsapp.templates',
        'templates' => 'whatsapp.templates',
        'edit'      => 'whatsapp.templates',
        'preview'   => 'whatsapp.templates',
        'log'       => 'whatsapp.send',
        'settings'  => 'settings.manage',
        'test_send' => 'settings.manage',
    );

    public function __construct()
    {
        parent::__construct();
        $this->load->model('whatsapp_model');
        $this->load->library(array('whatsapp_service', 'form_validation'));
    }

    public function index(): void
    {
        redirect('whatsapp/templates');
    }

    public function templates(): void
    {
        $this->render('whatsapp/templates', array(
            'page_title'  => 'WhatsApp Message Templates',
            'breadcrumbs' => array(array('label' => 'WhatsApp'), array('label' => 'Message Templates')),
            'templates'   => $this->whatsapp_model->templates(),
            'mode'        => $this->whatsapp_service->mode(),
            'scripts'     => array('js/modules/whatsapp.js'),
        ));
    }

    public function edit(int $id = 0): void
    {
        $template = $this->whatsapp_model->find_template($id);
        if ($template === NULL)
        {
            show_404();
        }

        if ($this->input->method() === 'post')
        {
            $v = $this->form_validation;
            $v->set_rules('template_name', 'Template Name', 'trim|required|max_length[100]');
            $v->set_rules('message_body', 'Message', 'trim|required|max_length['.Whatsapp_service::MAX_BODY_LENGTH.']');
            $v->set_rules('cloud_template_name', 'Cloud API template name', array('trim', 'max_length[100]', 'regex_match[/^[a-z0-9_]*$/]'), array('regex_match' => 'The Cloud API template name may contain only lowercase letters, digits and underscores.'));
            $v->set_rules('cloud_language', 'Language code', array('trim', 'required', 'regex_match[/^[a-z]{2}(_[A-Z]{2})?$/]'), array('regex_match' => 'Use a language code like en, en_US or ta.'));
            $body = (string) $this->input->post('message_body');
            $unknown = $this->whatsapp_service->unknown_placeholders($body);

            if ($v->run() && empty($unknown))
            {
                $data = array(
                    'template_name'       => trim((string) $this->input->post('template_name', TRUE)),
                    'message_body'        => str_replace("\r\n", "\n", trim($body)),
                    'cloud_template_name' => trim((string) $this->input->post('cloud_template_name')) !== '' ? trim((string) $this->input->post('cloud_template_name')) : NULL,
                    'cloud_language'      => trim((string) $this->input->post('cloud_language')),
                    'is_active'           => $this->input->post('is_active') === '1' ? 1 : 0,
                    'updated_by'          => user_id(),
                );
                $this->whatsapp_model->update_template($id, $data);
                $this->audit->log('WhatsApp Template Updated', 'whatsapp', $id, $template, $data);
                $this->session->set_flashdata('success', 'Template "'.$data['template_name'].'" saved.');
                redirect('whatsapp/templates');
            }
            if ( ! empty($unknown))
            {
                $this->data['form_error'] = 'Unknown placeholder(s): {'.implode('}, {', $unknown).'}. Use only the placeholders listed on the right.';
            }
        }

        $this->render('whatsapp/edit', array(
            'page_title'   => 'Edit Template',
            'breadcrumbs'  => array(array('label' => 'WhatsApp'), array('label' => 'Message Templates', 'url' => 'whatsapp/templates'), array('label' => $template['template_name'])),
            'template'     => $template,
            'placeholders' => Whatsapp_service::PLACEHOLDERS,
            'max_length'   => Whatsapp_service::MAX_BODY_LENGTH,
            'scripts'      => array('js/modules/whatsapp.js'),
        ));
    }

    /**
     * AJAX POST: render a template body with sample values.
     */
    public function preview(): void
    {
        $this->require_ajax();
        $this->require_post();
        $body = mb_substr((string) $this->input->post('message_body'), 0, 4000);
        $key = (string) $this->input->post('template_key');
        $this->json_success('OK', array(
            'text'    => $this->whatsapp_service->render($body, $this->whatsapp_service->sample_vars($key)),
            'unknown' => $this->whatsapp_service->unknown_placeholders($body),
            'length'  => mb_strlen($body),
        ));
    }

    public function log(): void
    {
        $from = (string) $this->input->get('from');
        $to = (string) $this->input->get('to');
        $filters = array(
            'from'     => is_valid_date($from) ? $from : date('Y-m-d', strtotime('-30 days')),
            'to'       => is_valid_date($to) ? $to : date('Y-m-d'),
            'template' => in_array($this->input->get('template'), array('payment_receipt', 'maintenance_reminder'), TRUE) ? (string) $this->input->get('template') : '',
            'status'   => in_array($this->input->get('status'), array('Prepared', 'Sent', 'Failed'), TRUE) ? (string) $this->input->get('status') : '',
            'channel'  => in_array($this->input->get('channel'), array('click_to_chat', 'cloud_api'), TRUE) ? (string) $this->input->get('channel') : '',
        );
        $this->render('whatsapp/log', array(
            'page_title'  => 'WhatsApp Message Log',
            'breadcrumbs' => array(array('label' => 'WhatsApp'), array('label' => 'Message Log')),
            'filters'     => $filters,
            'messages'    => $this->whatsapp_model->messages($filters),
            'scripts'     => array('js/modules/whatsapp.js'),
        ));
    }

    public function settings(): void
    {
        if ($this->input->method() === 'post')
        {
            $v = $this->form_validation;
            $v->set_rules('whatsapp_mode', 'Mode', 'required|in_list[click_to_chat,cloud_api]');
            $v->set_rules('whatsapp_country_code', 'Country code', 'trim|required|regex_match[/^\d{1,4}$/]', array('regex_match' => 'Enter the country code as digits only, e.g. 91.'));
            $v->set_rules('whatsapp_cloud_phone_number_id', 'Phone number ID', 'trim|max_length[30]|regex_match[/^\d*$/]', array('regex_match' => 'The phone number ID contains digits only.'));
            $v->set_rules('whatsapp_cloud_api_version', 'API version', 'trim|required|regex_match[/^v\d{1,2}\.\d$/]', array('regex_match' => 'Use a version like v21.0.'));

            if ($v->run())
            {
                $old = array();
                $new = array();
                foreach (array('whatsapp_mode', 'whatsapp_country_code', 'whatsapp_cloud_phone_number_id', 'whatsapp_cloud_api_version') as $key)
                {
                    $old[$key] = $this->settings_model->get($key);
                    $new[$key] = trim((string) $this->input->post($key));
                    $this->settings_model->set($key, $new[$key]);
                }
                $this->audit->log('WhatsApp Settings Updated', 'settings', 'whatsapp', $old, $new);

                $message = 'WhatsApp settings saved.';
                if ($new['whatsapp_mode'] === 'cloud_api')
                {
                    list($ready, $reason) = $this->whatsapp_service->cloud_ready();
                    if ( ! $ready)
                    {
                        $this->session->set_flashdata('warning', $message.' Cloud API mode is selected but not ready: '.$reason.' Messages will fail until this is fixed.');
                        redirect('whatsapp/settings');
                    }
                }
                $this->session->set_flashdata('success', $message);
                redirect('whatsapp/settings');
            }
        }

        list($ready, $reason) = $this->whatsapp_service->cloud_ready();
        $this->render('whatsapp/settings', array(
            'page_title'    => 'WhatsApp Settings',
            'breadcrumbs'   => array(array('label' => 'WhatsApp'), array('label' => 'Settings')),
            'settings'      => array(
                'whatsapp_mode'                  => $this->whatsapp_service->mode(),
                'whatsapp_country_code'          => $this->settings_model->get('whatsapp_country_code', '91'),
                'whatsapp_cloud_phone_number_id' => $this->settings_model->get('whatsapp_cloud_phone_number_id', ''),
                'whatsapp_cloud_api_version'     => $this->settings_model->get('whatsapp_cloud_api_version', 'v21.0'),
            ),
            'token_set'     => (string) $this->config->item('whatsapp_cloud_access_token', 'whatsapp') !== '',
            'curl_loaded'   => extension_loaded('curl'),
            'cloud_ready'   => $ready,
            'cloud_reason'  => $reason,
            'scripts'       => array('js/modules/whatsapp.js'),
        ));
    }

    /**
     * AJAX POST: send a test text message through the Cloud API.
     */
    public function test_send(): void
    {
        $this->require_ajax();
        $this->require_post();
        $number = (string) $this->input->post('number');
        try
        {
            $id = $this->whatsapp_service->send_test($number, 'Test message from '.association('association_name').' portal ('.fmt_datetime(date('Y-m-d H:i:s')).').');
        }
        catch (Throwable $e)
        {
            $this->json_error('Test failed: '.$e->getMessage());
        }
        $this->audit->log('WhatsApp Test Sent', 'settings', 'whatsapp', NULL, array('to' => whatsapp_number($number), 'message_id' => $id));
        $this->json_success('Test message accepted by WhatsApp (message id '.$id.'). Note: free-form messages are delivered only if the number has messaged your business in the last 24 hours.');
    }
}
