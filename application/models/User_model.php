<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Users, plus the auth tables tied to a user (login attempts, remember-me tokens, password resets).
 */
class User_model extends MY_Model
{
    protected $table = 'users';

    /**
     * User with role key/name, looked up by username OR e-mail (case-insensitive).
     *
     * @return array<string, mixed>|null
     */
    public function find_by_login(string $identifier): ?array
    {
        $identifier = trim($identifier);
        $row = $this->db
            ->select('u.*, r.role_key, r.role_name')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->group_start()
                ->where('u.username', $identifier)
                ->or_where('u.email', $identifier)
            ->group_end()
            ->limit(1)
            ->get()->row_array();
        return $row ?: NULL;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_with_role(int $id): ?array
    {
        $row = $this->db
            ->select('u.*, r.role_key, r.role_name')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->where('u.id', $id)
            ->get()->row_array();
        return $row ?: NULL;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list_all(): array
    {
        return $this->db
            ->select('u.id, u.full_name, u.username, u.email, u.mobile, u.status, u.last_login_at, u.locked_until, u.created_at, r.role_key, r.role_name')
            ->from('users u')
            ->join('roles r', 'r.id = u.role_id')
            ->order_by('r.id, u.full_name')
            ->get()->result_array();
    }

    /**
     * Permission keys granted to a role.
     *
     * @return string[]
     */
    public function permissions_for_role(int $role_id): array
    {
        $rows = $this->db
            ->select('p.perm_key')
            ->from('role_permissions rp')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('rp.role_id', $role_id)
            ->get()->result_array();
        return array_column($rows, 'perm_key');
    }

    public function count_active_super_admins(?int $exclude_id = NULL): int
    {
        $this->db->from('users u')->join('roles r', 'r.id = u.role_id')
            ->where('r.role_key', 'super_admin')->where('u.status', 'Active');
        if ($exclude_id !== NULL)
        {
            $this->db->where('u.id !=', $exclude_id);
        }
        return (int) $this->db->count_all_results();
    }

    /* ---------------- Login attempts ---------------- */

    public function record_attempt(string $identifier, string $ip, bool $success): void
    {
        $this->db->insert('login_attempts', array(
            'login_identifier' => mb_substr($identifier, 0, 150),
            'ip_address'       => $ip,
            'is_success'       => $success ? 1 : 0,
        ));
    }

    public function failed_attempts_since(string $field, string $value, string $since): int
    {
        return (int) $this->db->from('login_attempts')
            ->where($field, $value)
            ->where('is_success', 0)
            ->where('attempted_at >=', $since)
            ->count_all_results();
    }

    /**
     * Remove attempt history older than 30 days (called occasionally on login).
     */
    public function purge_old_attempts(): void
    {
        $this->db->where('attempted_at <', date('Y-m-d H:i:s', strtotime('-30 days')))->delete('login_attempts');
    }

    /* ---------------- Remember-me tokens ---------------- */

    public function store_remember_token(int $user_id, string $selector, string $validator_hash, string $expires_at, string $user_agent): void
    {
        $this->db->insert('user_remember_tokens', array(
            'user_id'        => $user_id,
            'selector'       => $selector,
            'validator_hash' => $validator_hash,
            'expires_at'     => $expires_at,
            'user_agent'     => mb_substr($user_agent, 0, 255),
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find_remember_token(string $selector): ?array
    {
        $row = $this->db->get_where('user_remember_tokens', array('selector' => $selector), 1)->row_array();
        return $row ?: NULL;
    }

    public function delete_remember_token(string $selector): void
    {
        $this->db->where('selector', $selector)->delete('user_remember_tokens');
    }

    public function delete_all_remember_tokens(int $user_id): void
    {
        $this->db->where('user_id', $user_id)->delete('user_remember_tokens');
    }

    public function purge_expired_remember_tokens(): void
    {
        $this->db->where('expires_at <', date('Y-m-d H:i:s'))->delete('user_remember_tokens');
    }

    /* ---------------- Password resets ---------------- */

    public function create_password_reset(int $user_id, string $token_hash, string $expires_at, string $ip): void
    {
        // Only the newest link stays valid
        $this->db->where('user_id', $user_id)->where('used_at IS NULL', NULL, FALSE)->update('password_resets', array('used_at' => date('Y-m-d H:i:s')));
        $this->db->insert('password_resets', array(
            'user_id'    => $user_id,
            'token_hash' => $token_hash,
            'expires_at' => $expires_at,
            'request_ip' => $ip,
        ));
    }

    public function recent_reset_requests(int $user_id, string $since): int
    {
        return (int) $this->db->from('password_resets')
            ->where('user_id', $user_id)
            ->where('created_at >=', $since)
            ->count_all_results();
    }

    /**
     * Valid (unused, unexpired) reset row for a token hash.
     *
     * @return array<string, mixed>|null
     */
    public function find_valid_reset(string $token_hash): ?array
    {
        $row = $this->db->from('password_resets')
            ->where('token_hash', $token_hash)
            ->where('used_at IS NULL', NULL, FALSE)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->limit(1)
            ->get()->row_array();
        return $row ?: NULL;
    }

    public function mark_reset_used(int $reset_id): void
    {
        $this->db->where('id', $reset_id)->update('password_resets', array('used_at' => date('Y-m-d H:i:s')));
    }
}
