<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * WhatsApp messaging layer with two interchangeable modes (Settings > WhatsApp):
 *
 *  click_to_chat (default)
 *      Builds https://wa.me/<number>?text=<message>; the user's browser opens WhatsApp with the
 *      message typed and the user taps Send. Files cannot be attached this way.
 *
 *  cloud_api
 *      Sends through the WhatsApp Business Cloud API (graph.facebook.com) from the server.
 *      - If the local template has a Meta-approved "cloud_template_name", a template message is sent
 *        (placeholders become body parameters in order of appearance; a PDF becomes the document header).
 *      - Otherwise a free-form text / document message is sent. Meta only delivers free-form messages
 *        inside the 24-hour customer-service window, so approved templates are recommended.
 *      The access token comes ONLY from .env (WHATSAPP_CLOUD_ACCESS_TOKEN).
 *
 * Every message (prepared, sent or failed) is written to whatsapp_messages.
 */
class Whatsapp_service
{
    const PLACEHOLDERS = array('OWNER_NAME', 'PLOT_NO', 'HOUSE_NO', 'MONTH', 'YEAR', 'AMOUNT', 'PAYMENT_MODE',
        'RECEIPT_NO', 'BALANCE', 'ASSOCIATION_NAME', 'PAYMENT_DATE', 'DUE_DATE');

    const MAX_BODY_LENGTH = 1024;

    /** @var CI_Controller */
    private $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('whatsapp_model');
        $this->CI->config->load('whatsapp', TRUE);
    }

    /* ==================================================================
     * Templates
     * ================================================================ */

    /**
     * Replace {PLACEHOLDER} tokens. Unknown tokens are left as typed so template mistakes are visible.
     *
     * @param array<string, string> $vars Keys without braces
     */
    public function render(string $body, array $vars): string
    {
        $vars['ASSOCIATION_NAME'] = $vars['ASSOCIATION_NAME'] ?? association('association_name');
        $replace = array();
        foreach ($vars as $key => $value)
        {
            $replace['{'.strtoupper($key).'}'] = (string) $value;
        }
        return strtr($body, $replace);
    }

    /**
     * @param array<string, string> $vars
     */
    public function render_template(string $template_key, array $vars): ?string
    {
        $template = $this->CI->whatsapp_model->template($template_key);
        return $template !== NULL ? $this->render($template['message_body'], $vars) : NULL;
    }

    /**
     * Placeholders used in a body, in order of first appearance.
     *
     * @return string[]
     */
    public function placeholders_in(string $body): array
    {
        preg_match_all('/\{([A-Z_]+)\}/', $body, $m);
        return array_values(array_unique($m[1]));
    }

    /**
     * Placeholders in a body that the system does not know.
     *
     * @return string[]
     */
    public function unknown_placeholders(string $body): array
    {
        return array_values(array_diff($this->placeholders_in($body), self::PLACEHOLDERS));
    }

    /**
     * Example values for previews, matching what each message type really receives:
     *  payment_receipt      {MONTH} = months paid incl. year ("October 2026"), {BALANCE} = balance after payment
     *  maintenance_reminder {MONTH} = month name ("October") used with {YEAR}, {BALANCE} = total outstanding
     *
     * @return array<string, string>
     */
    public function sample_vars(string $template_key = 'payment_receipt'): array
    {
        $vars = array(
            'OWNER_NAME'   => 'Mr. Kumar',
            'PLOT_NO'      => '12',
            'HOUSE_NO'     => 'A-112',
            'MONTH'        => date('F Y'),
            'YEAR'         => date('Y'),
            'AMOUNT'       => '300.00',
            'PAYMENT_MODE' => 'UPI',
            'RECEIPT_NO'   => 'REC-'.date('Y').'-000125',
            'BALANCE'      => '0.00',
            'PAYMENT_DATE' => fmt_date(date('Y-m-d')),
            'DUE_DATE'     => fmt_date(date('Y-m-10')),
        );
        if ($template_key === 'maintenance_reminder')
        {
            $vars['MONTH'] = date('F');
            $vars['BALANCE'] = '600.00';
            $vars['RECEIPT_NO'] = '';
            $vars['PAYMENT_MODE'] = '';
        }
        return $vars;
    }

    /* ==================================================================
     * Mode & delivery
     * ================================================================ */

    public function mode(): string
    {
        $mode = (string) sys_setting('whatsapp_mode', 'click_to_chat');
        return in_array($mode, array('click_to_chat', 'cloud_api'), TRUE) ? $mode : 'click_to_chat';
    }

    /**
     * Is Cloud API mode fully configured? Returns array(bool ready, string reason).
     *
     * @return array{0: bool, 1: string}
     */
    public function cloud_ready(): array
    {
        if ( ! extension_loaded('curl'))
        {
            return array(FALSE, 'The PHP curl extension is not enabled on the server.');
        }
        if ((string) $this->CI->config->item('whatsapp_cloud_access_token', 'whatsapp') === '')
        {
            return array(FALSE, 'WHATSAPP_CLOUD_ACCESS_TOKEN is not set in the .env file.');
        }
        if ((string) sys_setting('whatsapp_cloud_phone_number_id', '') === '')
        {
            return array(FALSE, 'The WhatsApp phone number ID is not set (Settings > WhatsApp).');
        }
        return array(TRUE, '');
    }

    public function click_to_chat_url(string $number, string $message): string
    {
        $base = (string) $this->CI->config->item('whatsapp_click_to_chat_url', 'whatsapp');
        return rtrim($base, '/').'/'.preg_replace('/\D+/', '', $number).'?text='.rawurlencode($message);
    }

    /**
     * Send (Cloud API) or prepare (Click-to-Chat) a templated message, and log it.
     *
     * @param array<string, string> $vars
     * @param array{owner_id: int, receipt_id?: int|null, maintenance_id?: int|null} $context
     * @param array{path: string, filename: string}|null $document PDF to attach (Cloud API only)
     * @return array{success: bool, message: string, mode: string, url?: string, text?: string, provider_message_id?: string}
     */
    public function deliver(string $template_key, string $raw_number, array $vars, array $context, ?array $document = NULL): array
    {
        $mode = $this->mode();
        $number = whatsapp_number($raw_number);
        if ($number === '')
        {
            return array('success' => FALSE, 'mode' => $mode, 'message' => 'This owner has no valid WhatsApp/mobile number.');
        }
        $template = $this->CI->whatsapp_model->template($template_key);
        if ($template === NULL)
        {
            return array('success' => FALSE, 'mode' => $mode, 'message' => 'The WhatsApp template "'.$template_key.'" is missing or inactive.');
        }
        $text = $this->render($template['message_body'], $vars);

        $log = array(
            'owner_id'         => (int) $context['owner_id'],
            'receipt_id'       => $context['receipt_id'] ?? NULL,
            'maintenance_id'   => $context['maintenance_id'] ?? NULL,
            'template_key'     => $template_key,
            'recipient_number' => $number,
            'message_body'     => $text,
            'channel'          => $mode,
            'created_by'       => user_id(),
        );

        if ($mode === 'click_to_chat')
        {
            $this->CI->whatsapp_model->log_message($log + array('status' => 'Prepared'));
            return array('success' => TRUE, 'mode' => $mode, 'message' => 'WhatsApp message prepared.', 'url' => $this->click_to_chat_url($number, $text), 'text' => $text);
        }

        list($ready, $reason) = $this->cloud_ready();
        if ( ! $ready)
        {
            $this->CI->whatsapp_model->log_message($log + array('status' => 'Failed', 'error_message' => mb_substr($reason, 0, 500)));
            return array('success' => FALSE, 'mode' => $mode, 'message' => 'WhatsApp Cloud API is not configured: '.$reason);
        }

        try
        {
            $message_id = $this->send_cloud($number, $template, $vars, $text, $document);
            $this->CI->whatsapp_model->log_message($log + array('status' => 'Sent', 'provider_message_id' => mb_substr($message_id, 0, 100)));
            return array('success' => TRUE, 'mode' => $mode, 'message' => 'Message sent via WhatsApp'.($document !== NULL ? ' with the receipt PDF attached' : '').'.', 'provider_message_id' => $message_id, 'text' => $text);
        }
        catch (Throwable $e)
        {
            $this->CI->whatsapp_model->log_message($log + array('status' => 'Failed', 'error_message' => mb_substr($e->getMessage(), 0, 500)));
            log_message('error', 'WhatsApp Cloud API send failed: '.$e->getMessage());
            return array('success' => FALSE, 'mode' => $mode, 'message' => 'WhatsApp could not send the message: '.$e->getMessage());
        }
    }

    /**
     * Cloud API: plain test message (only delivered inside the 24-hour window).
     */
    public function send_test(string $raw_number, string $text): string
    {
        list($ready, $reason) = $this->cloud_ready();
        if ( ! $ready)
        {
            throw new RuntimeException($reason);
        }
        $number = whatsapp_number($raw_number);
        if ($number === '')
        {
            throw new RuntimeException('Please enter a valid 10-digit mobile number.');
        }
        return $this->post_message(array('to' => $number, 'type' => 'text', 'text' => array('preview_url' => FALSE, 'body' => $text)));
    }

    /* ==================================================================
     * Cloud API internals
     * ================================================================ */

    /**
     * @param array<string, mixed>                       $template
     * @param array<string, string>                      $vars
     * @param array{path: string, filename: string}|null $document
     */
    private function send_cloud(string $number, array $template, array $vars, string $text, ?array $document): string
    {
        $media_id = $document !== NULL ? $this->upload_media($document['path'], 'application/pdf') : NULL;

        if ( ! empty($template['cloud_template_name']))
        {
            $vars['ASSOCIATION_NAME'] = $vars['ASSOCIATION_NAME'] ?? association('association_name');
            $parameters = array();
            foreach ($this->placeholders_in($template['message_body']) as $name)
            {
                $parameters[] = array('type' => 'text', 'text' => (string) ($vars[$name] ?? ''));
            }
            $components = array();
            if ($media_id !== NULL)
            {
                $components[] = array('type' => 'header', 'parameters' => array(array(
                    'type' => 'document', 'document' => array('id' => $media_id, 'filename' => $document['filename']),
                )));
            }
            if ( ! empty($parameters))
            {
                $components[] = array('type' => 'body', 'parameters' => $parameters);
            }
            return $this->post_message(array(
                'to'       => $number,
                'type'     => 'template',
                'template' => array(
                    'name'       => $template['cloud_template_name'],
                    'language'   => array('code' => $template['cloud_language'] ?: 'en'),
                    'components' => $components,
                ),
            ));
        }

        if ($media_id !== NULL)
        {
            return $this->post_message(array(
                'to'       => $number,
                'type'     => 'document',
                'document' => array('id' => $media_id, 'filename' => $document['filename'], 'caption' => mb_substr($text, 0, 1024)),
            ));
        }
        return $this->post_message(array('to' => $number, 'type' => 'text', 'text' => array('preview_url' => FALSE, 'body' => $text)));
    }

    /**
     * POST /{phone_number_id}/messages. Returns the WhatsApp message id.
     *
     * @param array<string, mixed> $payload
     */
    private function post_message(array $payload): string
    {
        $payload = array('messaging_product' => 'whatsapp', 'recipient_type' => 'individual') + $payload;
        $response = $this->request('messages', json_encode($payload, JSON_UNESCAPED_UNICODE), array('Content-Type: application/json'));
        if (empty($response['messages'][0]['id']))
        {
            throw new RuntimeException('Unexpected response from WhatsApp (no message id).');
        }
        return (string) $response['messages'][0]['id'];
    }

    /**
     * POST /{phone_number_id}/media (multipart). Returns the media id.
     */
    private function upload_media(string $path, string $mime): string
    {
        if ( ! is_file($path))
        {
            throw new RuntimeException('The file to attach was not found.');
        }
        $response = $this->request('media', array(
            'messaging_product' => 'whatsapp',
            'type'              => $mime,
            'file'              => new CURLFile($path, $mime, basename($path)),
        ));
        if (empty($response['id']))
        {
            throw new RuntimeException('Unexpected response from WhatsApp media upload (no media id).');
        }
        return (string) $response['id'];
    }

    /**
     * @param string|array<string, mixed> $body
     * @param string[]                    $headers
     * @return array<string, mixed>
     */
    private function request(string $endpoint, $body, array $headers = array()): array
    {
        $base = rtrim((string) $this->CI->config->item('whatsapp_cloud_base_url', 'whatsapp'), '/');
        $version = preg_replace('/[^A-Za-z0-9.]/', '', (string) sys_setting('whatsapp_cloud_api_version', 'v21.0'));
        $phone_id = preg_replace('/\D+/', '', (string) sys_setting('whatsapp_cloud_phone_number_id', ''));
        $url = $base.'/'.$version.'/'.$phone_id.'/'.$endpoint;

        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => TRUE,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_TIMEOUT        => (int) $this->CI->config->item('whatsapp_cloud_timeout', 'whatsapp'),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => TRUE,
            CURLOPT_HTTPHEADER     => array_merge(array('Authorization: Bearer '.$this->CI->config->item('whatsapp_cloud_access_token', 'whatsapp')), $headers),
        ));
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === FALSE)
        {
            throw new RuntimeException('Could not reach WhatsApp ('.$error.').');
        }
        $data = json_decode((string) $raw, TRUE);
        if ($status < 200 || $status >= 300)
        {
            $detail = is_array($data) && isset($data['error']['message']) ? $data['error']['message'] : 'HTTP '.$status;
            throw new RuntimeException($detail);
        }
        return is_array($data) ? $data : array();
    }
}
