<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ROWA Portal - UI helpers (badges, active menu state, flash messages, forms).
 */

if ( ! function_exists('status_badge'))
{
    /**
     * Bootstrap badge for any status value used in the portal.
     */
    function status_badge(?string $status): string
    {
        $map = array(
            'Active'          => 'success',
            'Paid'            => 'success',
            'Sent'            => 'success',
            'Partially Paid'  => 'warning',
            'Pending'         => 'danger',
            'Prepared'        => 'info',
            'Waived'          => 'secondary',
            'Inactive'        => 'secondary',
            'Cancelled'       => 'dark',
            'Reversed'        => 'dark',
            'Moved Out'       => 'secondary',
            'Scheduled'       => 'info',
            'Current'         => 'success',
            'Superseded'      => 'secondary',
            'Failed'          => 'danger',
            'Owner Occupied'  => 'primary',
            'Tenant Occupied' => 'info',
            'Vacant'          => 'secondary',
        );
        $status = (string) $status;
        $class = $map[$status] ?? 'light';
        $text  = in_array($class, array('warning', 'info', 'light'), TRUE) ? ' text-dark' : '';
        return '<span class="badge rounded-pill bg-'.$class.$text.'">'.e($status).'</span>';
    }
}

if ( ! function_exists('menu_is_active'))
{
    /**
     * Is a menu URL the current page (or a parent of it)?
     */
    function menu_is_active(string $url): bool
    {
        $CI =& get_instance();
        $current = trim($CI->uri->uri_string(), '/');
        $url = trim($url, '/');
        if ($url === '')
        {
            return $current === '';
        }
        if ($current === $url)
        {
            return TRUE;
        }
        // "owners" is active for "owners/view/5" but not when a more specific sibling item matches
        return strpos($current, $url.'/') === 0 && ! in_array($current, menu_exact_urls(), TRUE);
    }
}

if ( ! function_exists('menu_exact_urls'))
{
    /**
     * All leaf URLs in the menu (used so "payments" does not light up on "payments/collect").
     *
     * @return string[]
     */
    function menu_exact_urls(): array
    {
        static $urls = NULL;
        if ($urls === NULL)
        {
            $urls = array();
            $CI =& get_instance();
            $CI->config->load('menu', TRUE);
            $menu = (array) $CI->config->item('sidebar_menu', 'menu');
            array_walk_recursive($menu, function ($value, $key) use (&$urls) {
                if ($key === 'url')
                {
                    $urls[] = trim((string) $value, '/');
                }
            });
        }
        return $urls;
    }
}

if ( ! function_exists('menu_group_is_active'))
{
    /**
     * @param array<int, array<string, mixed>> $children
     */
    function menu_group_is_active(array $children): bool
    {
        foreach ($children as $child)
        {
            if (isset($child['url']) && menu_is_active($child['url']))
            {
                return TRUE;
            }
        }
        return FALSE;
    }
}

if ( ! function_exists('flash_messages_json'))
{
    /**
     * Session flash messages as JSON for app.js toasts:
     * [{"type":"success","message":"Owner saved"}]
     */
    function flash_messages_json(): string
    {
        $CI =& get_instance();
        $messages = array();
        foreach (array('success', 'error', 'warning', 'info') as $type)
        {
            $value = $CI->session->flashdata($type);
            if (is_string($value) && $value !== '')
            {
                $messages[] = array('type' => $type, 'message' => $value);
            }
        }
        return json_encode($messages, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    }
}

if ( ! function_exists('field_error'))
{
    /**
     * Bootstrap "is-invalid" class + message for a form field after server-side validation.
     *
     * @return array{class: string, message: string}
     */
    function field_error(string $field): array
    {
        $message = form_error($field, '', '');
        return array(
            'class'   => $message !== '' ? ' is-invalid' : '',
            'message' => $message !== '' ? '<div class="invalid-feedback">'.e(strip_tags($message)).'</div>' : '',
        );
    }
}

if ( ! function_exists('select_options'))
{
    /**
     * <option> list. $options may be a list (value = label) or an associative array.
     *
     * @param array<int|string, string> $options
     * @param mixed $selected
     */
    function select_options(array $options, $selected = NULL, ?string $placeholder = NULL): string
    {
        $html = $placeholder !== NULL ? '<option value="">'.e($placeholder).'</option>' : '';
        $is_list = array_keys($options) === range(0, count($options) - 1);
        foreach ($options as $value => $label)
        {
            $value = $is_list ? $label : $value;
            $sel = ((string) $value === (string) $selected) ? ' selected' : '';
            $html .= '<option value="'.e($value).'"'.$sel.'>'.e($label).'</option>';
        }
        return $html;
    }
}
