<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Additional validation rules shared by all modules.
 *
 *   strong_password          min 8 chars, at least one letter and one digit, max 72 (bcrypt limit)
 *   valid_mobile             10-digit Indian mobile (spaces, +91 and dashes allowed)
 *   valid_date_ymd           strict Y-m-d calendar date
 *   valid_amount             non-negative amount with at most 2 decimals
 *   positive_amount          amount > 0 with at most 2 decimals
 *   unique_except[t.f.id]    value unique in table t, field f, ignoring row with primary key id
 *   unique_house_no[blk.id]  house number unique within the block posted in field "blk"
 *   exists_in[t.f]           value exists in table t, field f
 *   in_config_list[key]      value is one of config item array "key" (config/app.php)
 */
class MY_Form_validation extends CI_Form_validation
{
    /**
     * @param array<string, mixed> $rules
     */
    public function __construct($rules = array())
    {
        parent::__construct($rules);

        $this->set_message(array(
            'strong_password'  => 'The {field} must be 8-72 characters and contain at least one letter and one number.',
            'valid_mobile'     => 'The {field} must be a valid 10-digit mobile number.',
            'valid_date_ymd'   => 'The {field} must be a valid date.',
            'valid_amount'     => 'The {field} must be a valid amount (up to 2 decimals).',
            'positive_amount'  => 'The {field} must be greater than zero (up to 2 decimals).',
            'unique_except'    => 'This {field} is already in use.',
            'unique_house_no'  => 'This {field} already exists in the same block.',
            'exists_in'        => 'The selected {field} is not valid.',
            'in_config_list'   => 'The selected {field} is not valid.',
        ));
    }

    public function strong_password($value): bool
    {
        $value = (string) $value;
        return strlen($value) >= 8
            && strlen($value) <= 72
            && preg_match('/[A-Za-z]/', $value) === 1
            && preg_match('/\d/', $value) === 1;
    }

    public function valid_mobile($value): bool
    {
        if ($value === NULL || $value === '')
        {
            return TRUE;
        }
        $digits = preg_replace('/\D+/', '', (string) $value);
        if (strlen($digits) === 12 && strpos($digits, '91') === 0)
        {
            $digits = substr($digits, 2);
        }
        elseif (strlen($digits) === 11 && $digits[0] === '0')
        {
            $digits = substr($digits, 1);
        }
        return preg_match('/^[6-9]\d{9}$/', $digits) === 1;
    }

    public function valid_date_ymd($value): bool
    {
        if ($value === NULL || $value === '')
        {
            return TRUE;
        }
        return is_valid_date((string) $value);
    }

    public function valid_amount($value): bool
    {
        if ($value === NULL || $value === '')
        {
            return TRUE;
        }
        return to_paise((string) $value) !== NULL;
    }

    public function positive_amount($value): bool
    {
        $paise = to_paise((string) $value);
        return $paise !== NULL && $paise > 0;
    }

    public function unique_except($value, string $params): bool
    {
        $parts = explode('.', $params);
        if (count($parts) !== 3)
        {
            return FALSE;
        }
        list($table, $field, $ignore_id) = $parts;
        if ( ! isset($this->CI->db))
        {
            return FALSE;
        }
        $this->CI->db->from($table)->where($field, $value);
        if ($ignore_id !== '' && ctype_digit($ignore_id))
        {
            $this->CI->db->where('id !=', (int) $ignore_id);
        }
        return $this->CI->db->count_all_results() === 0;
    }

    /**
     * House number must be unique within its block.
     * Param: "<block POST field>.<id to ignore or empty>", e.g. unique_house_no[block.5]
     */
    public function unique_house_no($value, string $params): bool
    {
        if ($value === NULL || $value === '')
        {
            return TRUE;
        }
        $parts = explode('.', $params);
        $block = trim((string) $this->CI->input->post($parts[0]));
        $this->CI->db->from('houses')->where('block', $block)->where('house_no', $value);
        if (isset($parts[1]) && ctype_digit($parts[1]))
        {
            $this->CI->db->where('id !=', (int) $parts[1]);
        }
        return $this->CI->db->count_all_results() === 0;
    }

    public function exists_in($value, string $params): bool
    {
        if ($value === NULL || $value === '')
        {
            return TRUE;
        }
        $parts = explode('.', $params);
        if (count($parts) !== 2)
        {
            return FALSE;
        }
        return $this->CI->db->from($parts[0])->where($parts[1], $value)->count_all_results() > 0;
    }

    public function in_config_list($value, string $key): bool
    {
        if ($value === NULL || $value === '')
        {
            return TRUE;
        }
        $list = $this->CI->config->item($key);
        return is_array($list) && in_array((string) $value, $list, TRUE);
    }
}
