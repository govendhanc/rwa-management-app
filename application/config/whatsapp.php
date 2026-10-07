<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| WhatsApp integration
| -------------------------------------------------------------------------
| The active mode (click_to_chat | cloud_api), country code, phone number ID
| and API version are edited from WhatsApp > Settings and stored in system_settings.
|
| The Cloud API access token is a secret: it is read ONLY from .env
| (WHATSAPP_CLOUD_ACCESS_TOKEN) and is never stored in the database or shown
| in the UI.
|
| WHATSAPP_CLOUD_BASE_URL is optional - leave it blank in production. It exists so the
| integration can be tested against a local mock server or routed through a proxy.
*/

$config['whatsapp_click_to_chat_url']  = 'https://wa.me/';
$config['whatsapp_cloud_base_url']     = (string) env('WHATSAPP_CLOUD_BASE_URL', '') !== '' ? (string) env('WHATSAPP_CLOUD_BASE_URL') : 'https://graph.facebook.com/';
$config['whatsapp_cloud_access_token'] = (string) env('WHATSAPP_CLOUD_ACCESS_TOKEN', '');
$config['whatsapp_cloud_timeout']      = 20;
