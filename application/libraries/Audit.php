<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Audit trail writer (append-only audit_logs table).
 *
 * Usage:
 *   $this->audit->log('Owner Updated', 'owners', $id, $old_row, $new_row);
 *
 * - Secrets (password hashes, tokens) are stripped before saving.
 * - For updates only the fields that actually changed are stored.
 * - A failure to write the audit row never breaks the user's action; it is logged instead.
 */
class Audit
{
    /** @var CI_Controller */
    private $CI;

    /** @var string[] */
    private $sensitive_keys = array(
        'password', 'password_hash', 'password_confirm', 'new_password', 'current_password',
        'token', 'token_hash', 'validator', 'validator_hash', 'selector', 'rowa_csrf',
    );

    public function __construct()
    {
        $this->CI =& get_instance();
    }

    /**
     * @param array<string, mixed>|null $old
     * @param array<string, mixed>|null $new
     */
    public function log(string $action, string $module, $record_id = NULL, ?array $old = NULL, ?array $new = NULL, ?array $actor = NULL): void
    {
        $old = $old !== NULL ? $this->scrub($old) : NULL;
        $new = $new !== NULL ? $this->scrub($new) : NULL;

        if ($old !== NULL && $new !== NULL)
        {
            list($old, $new) = $this->diff($old, $new);
        }

        $user = $actor ?? current_user();

        $row = array(
            'user_id'    => $user !== NULL ? (int) $user['id'] : NULL,
            'username'   => $user !== NULL ? (string) $user['username'] : NULL,
            'action'     => mb_substr($action, 0, 60),
            'module'     => mb_substr($module, 0, 40),
            'record_id'  => $record_id !== NULL ? mb_substr((string) $record_id, 0, 40) : NULL,
            'old_value'  => $old !== NULL ? json_encode($old, JSON_UNESCAPED_UNICODE) : NULL,
            'new_value'  => $new !== NULL ? json_encode($new, JSON_UNESCAPED_UNICODE) : NULL,
            'ip_address' => client_ip(),
            'user_agent' => mb_substr((string) $this->CI->input->user_agent(), 0, 255),
        );

        $db_debug = $this->CI->db->db_debug;
        $this->CI->db->db_debug = FALSE;
        $ok = $this->CI->db->insert('audit_logs', $row);
        $this->CI->db->db_debug = $db_debug;

        if ( ! $ok)
        {
            $error = $this->CI->db->error();
            log_message('error', 'Audit log write failed: '.$error['message'].' | '.json_encode($row));
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function scrub(array $data): array
    {
        foreach ($data as $key => $value)
        {
            if (in_array(strtolower((string) $key), $this->sensitive_keys, TRUE))
            {
                unset($data[$key]);
                continue;
            }
            if (is_array($value))
            {
                $data[$key] = $this->scrub($value);
            }
        }
        // Bookkeeping columns: the actor and time are already stored on the audit row itself
        unset($data['created_at'], $data['updated_at'], $data['created_by'], $data['updated_by']);
        return $data;
    }

    /**
     * Keep only keys whose value changed (compared as strings so "300.00" == 300.00 from forms).
     *
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function diff(array $old, array $new): array
    {
        $old_changed = array();
        $new_changed = array();
        foreach ($new as $key => $value)
        {
            $before = array_key_exists($key, $old) ? $old[$key] : NULL;
            if ($this->normalize($before) !== $this->normalize($value))
            {
                $old_changed[$key] = $before;
                $new_changed[$key] = $value;
            }
        }
        return array($old_changed, $new_changed);
    }

    /**
     * @param mixed $value
     */
    private function normalize($value): string
    {
        if (is_array($value))
        {
            return json_encode($value);
        }
        $value = trim((string) $value);
        if (is_numeric($value))
        {
            return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
        }
        return $value;
    }
}
