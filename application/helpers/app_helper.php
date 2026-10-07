<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * ROWA Portal - general helpers (escaping, money, dates, settings, assets).
 *
 * Money rule: amounts are handled as strings ("1234.50") from/to the database and
 * as integer paise for arithmetic. Floats are never used for financial math.
 */

if ( ! function_exists('e'))
{
    /**
     * HTML-escape any scalar for safe output in views.
     *
     * @param mixed $value
     */
    function e($value): string
    {
        if ($value === NULL || is_array($value) || is_object($value))
        {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if ( ! function_exists('csp_nonce'))
{
    /**
     * Per-request nonce for inline <script> tags allowed by the Content-Security-Policy.
     */
    function csp_nonce(): string
    {
        static $nonce = NULL;
        if ($nonce === NULL)
        {
            $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        }
        return $nonce;
    }
}

/* ------------------------------------------------------------------------
 * Money
 * --------------------------------------------------------------------- */

if ( ! function_exists('to_paise'))
{
    /**
     * Convert a decimal amount ("1,234.5", "300", 300.00) into integer paise.
     * Returns NULL when the value is not a valid non-negative amount with max 2 decimals.
     *
     * @param mixed $amount
     */
    function to_paise($amount): ?int
    {
        if (is_int($amount))
        {
            return $amount * 100;
        }
        $amount = str_replace(array(',', ' ', '₹'), '', trim((string) $amount));
        if ( ! preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $amount, $m))
        {
            return NULL;
        }
        $fraction = isset($m[2]) ? str_pad($m[2], 2, '0') : '00';
        return ((int) $m[1]) * 100 + (int) $fraction;
    }
}

if ( ! function_exists('from_paise'))
{
    /**
     * Integer paise -> "1234.50" (database DECIMAL string).
     */
    function from_paise(int $paise): string
    {
        $sign = $paise < 0 ? '-' : '';
        $paise = abs($paise);
        return $sign.intdiv($paise, 100).'.'.str_pad((string) ($paise % 100), 2, '0', STR_PAD_LEFT);
    }
}

if ( ! function_exists('money'))
{
    /**
     * Format an amount in Indian grouping: 123456.5 -> "₹1,23,456.50".
     *
     * @param mixed $amount
     */
    function money($amount, bool $with_symbol = TRUE): string
    {
        $paise = is_int($amount) ? $amount * 100 : to_paise_signed($amount);
        $negative = $paise < 0;
        $paise = abs($paise);

        $rupees = (string) intdiv($paise, 100);
        $decimals = str_pad((string) ($paise % 100), 2, '0', STR_PAD_LEFT);

        if (strlen($rupees) > 3)
        {
            $last3 = substr($rupees, -3);
            $rest  = substr($rupees, 0, -3);
            $rest  = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $rupees = $rest.','.$last3;
        }

        $symbol = $with_symbol ? currency_symbol() : '';
        return ($negative ? '-' : '').$symbol.$rupees.'.'.$decimals;
    }
}

if ( ! function_exists('to_paise_signed'))
{
    /**
     * Like to_paise() but accepts negative values and returns 0 for invalid input.
     * Intended for formatting values that already came from the database.
     *
     * @param mixed $amount
     */
    function to_paise_signed($amount): int
    {
        $str = trim((string) $amount);
        $negative = strpos($str, '-') === 0;
        $paise = to_paise(ltrim($str, '-'));
        if ($paise === NULL)
        {
            return 0;
        }
        return $negative ? -$paise : $paise;
    }
}

if ( ! function_exists('currency_symbol'))
{
    function currency_symbol(): string
    {
        $symbol = association('currency_symbol');
        return $symbol !== '' ? $symbol : '₹';
    }
}

/* ------------------------------------------------------------------------
 * Dates
 * --------------------------------------------------------------------- */

if ( ! function_exists('fmt_date'))
{
    /**
     * Format a Y-m-d / Y-m-d H:i:s value for display (default d-M-Y, e.g. 06-Oct-2026).
     */
    function fmt_date(?string $value, ?string $format = NULL): string
    {
        if ($value === NULL || $value === '' || strpos($value, '0000-00-00') === 0)
        {
            return '';
        }
        $ts = strtotime($value);
        if ($ts === FALSE)
        {
            return '';
        }
        if ($format === NULL)
        {
            $format = sys_setting('date_format', 'd-M-Y');
        }
        return date($format, $ts);
    }
}

if ( ! function_exists('fmt_datetime'))
{
    function fmt_datetime(?string $value): string
    {
        return fmt_date($value, sys_setting('date_format', 'd-M-Y').' h:i A');
    }
}

if ( ! function_exists('is_valid_date'))
{
    /**
     * Strict Y-m-d validation.
     */
    function is_valid_date(?string $value): bool
    {
        if ($value === NULL || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value))
        {
            return FALSE;
        }
        $d = DateTime::createFromFormat('Y-m-d', $value);
        return $d !== FALSE && $d->format('Y-m-d') === $value;
    }
}

if ( ! function_exists('month_name'))
{
    function month_name(int $month, bool $short = FALSE): string
    {
        if ($month < 1 || $month > 12)
        {
            return '';
        }
        return date($short ? 'M' : 'F', mktime(0, 0, 0, $month, 1, 2000));
    }
}

if ( ! function_exists('period_label'))
{
    /**
     * 2026, 10 -> "October 2026"
     */
    function period_label(int $year, int $month, bool $short = FALSE): string
    {
        return month_name($month, $short).' '.$year;
    }
}

/* ------------------------------------------------------------------------
 * Settings
 * --------------------------------------------------------------------- */

if ( ! function_exists('association'))
{
    /**
     * Read one field of the association_settings row (e.g. association('association_name')).
     */
    function association(string $field): string
    {
        $CI =& get_instance();
        $row = $CI->settings_model->association();
        return isset($row[$field]) ? (string) $row[$field] : '';
    }
}

if ( ! function_exists('sys_setting'))
{
    /**
     * Read one system_settings value with a default.
     */
    function sys_setting(string $key, ?string $default = NULL): ?string
    {
        $CI =& get_instance();
        return $CI->settings_model->get($key, $default);
    }
}

if ( ! function_exists('logo_url'))
{
    /**
     * Public URL of the uploaded association logo, or '' when none.
     */
    function logo_url(): string
    {
        $path = association('logo_path');
        if ($path === '' || ! is_file(FCPATH.$path))
        {
            return '';
        }
        return base_url($path).'?v='.filemtime(FCPATH.$path);
    }
}

/* ------------------------------------------------------------------------
 * Requests, assets, phone numbers
 * --------------------------------------------------------------------- */

if ( ! function_exists('asset_url'))
{
    /**
     * URL of a file under public/assets with a cache-busting version.
     */
    function asset_url(string $path): string
    {
        $path = ltrim($path, '/');
        $file = FCPATH.'assets'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
        $version = is_file($file) ? (string) filemtime($file) : '1';
        return base_url('assets/'.$path).'?v='.$version;
    }
}

if ( ! function_exists('client_ip'))
{
    function client_ip(): string
    {
        $CI =& get_instance();
        return (string) $CI->input->ip_address();
    }
}

if ( ! function_exists('normalize_mobile'))
{
    /**
     * Keep digits only and return the last 10 digits (Indian mobile), or '' if too short.
     */
    function normalize_mobile(?string $number): string
    {
        $digits = preg_replace('/\D+/', '', (string) $number);
        if (strlen($digits) < 10)
        {
            return '';
        }
        return substr($digits, -10);
    }
}

if ( ! function_exists('whatsapp_number'))
{
    /**
     * International WhatsApp number without "+" (e.g. 919876543210), or '' when invalid.
     */
    function whatsapp_number(?string $number): string
    {
        $mobile = normalize_mobile($number);
        if ($mobile === '')
        {
            return '';
        }
        $country = preg_replace('/\D+/', '', (string) sys_setting('whatsapp_country_code', '91'));
        return $country.$mobile;
    }
}

if ( ! function_exists('amount_in_words'))
{
    /**
     * Indian-style amount in words: 125050.50 -> "Rupees One Lakh Twenty Five Thousand Fifty and Fifty Paise Only".
     *
     * @param mixed $amount
     */
    function amount_in_words($amount): string
    {
        $paise_total = abs(to_paise_signed($amount));
        $rupees = intdiv($paise_total, 100);
        $paise = $paise_total % 100;

        $words = rupee_words($rupees);
        $text = 'Rupees '.($words !== '' ? $words : 'Zero');
        if ($paise > 0)
        {
            $text .= ' and '.rupee_words($paise).' Paise';
        }
        return $text.' Only';
    }
}

if ( ! function_exists('rupee_words'))
{
    /**
     * Whole number to words using the Indian system (Crore, Lakh, Thousand, Hundred).
     */
    function rupee_words(int $n): string
    {
        static $ones = array('', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen');
        static $tens = array('', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety');

        $two = function (int $x) use ($ones, $tens): string {
            if ($x < 20)
            {
                return $ones[$x];
            }
            return trim($tens[intdiv($x, 10)].' '.$ones[$x % 10]);
        };

        if ($n === 0)
        {
            return '';
        }
        $parts = array();
        foreach (array(10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand', 100 => 'Hundred') as $unit => $name)
        {
            if ($n >= $unit)
            {
                $count = intdiv($n, $unit);
                $parts[] = ($unit === 10000000 ? rupee_words($count) : $two($count)).' '.$name;
                $n %= $unit;
            }
        }
        if ($n > 0)
        {
            $parts[] = $two($n);
        }
        return implode(' ', $parts);
    }
}

if ( ! function_exists('months_covered_label'))
{
    /**
     * Label for the billing months a payment covered.
     * ['2026-10-01'] -> "October 2026"; consecutive -> "August 2026 - October 2026"; gaps -> "Aug 2026, Oct 2026".
     *
     * @param string[] $period_starts Y-m-01 dates
     */
    function months_covered_label(array $period_starts): string
    {
        $months = array_values(array_unique($period_starts));
        sort($months);
        if (empty($months))
        {
            return '';
        }
        if (count($months) === 1)
        {
            return date('F Y', strtotime($months[0]));
        }
        $consecutive = TRUE;
        for ($i = 1; $i < count($months); $i++)
        {
            if (date('Y-m-01', strtotime($months[$i - 1].' +1 month')) !== $months[$i])
            {
                $consecutive = FALSE;
                break;
            }
        }
        if ($consecutive)
        {
            return date('F Y', strtotime($months[0])).' - '.date('F Y', strtotime(end($months)));
        }
        return implode(', ', array_map(function ($m) { return date('M Y', strtotime($m)); }, $months));
    }
}
