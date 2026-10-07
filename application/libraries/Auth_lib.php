<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Authentication service.
 *
 * - Login by username or e-mail with password_verify() (bcrypt via password_hash()).
 * - Brute-force protection: per-account lockout + per-identifier and per-IP throttling.
 * - Session fixation protection (session id regenerated on login/logout).
 * - Idle timeout (system_settings.session_timeout_minutes).
 * - "Remember me" using split selector/validator tokens (only a SHA-256 hash is stored).
 * - Change password, forgot/reset password with single-use, expiring tokens.
 */
class Auth_lib
{
    const REMEMBER_COOKIE = 'rowa_remember';
    const REMEMBER_DAYS   = 30;
    const RESET_MINUTES   = 60;
    const RESET_PER_HOUR  = 3;

    /** Valid bcrypt hash used to keep response time equal when the user does not exist. */
    const DUMMY_HASH = '$2y$10$yttj1qX6HSq4Lnp7P6.1/.fhCzoCySW9KaDqYgywTm0LHu7aUh.sS';

    /** @var CI_Controller */
    private $CI;

    /** @var bool */
    private $timed_out = FALSE;

    /** @var bool */
    private $checked = FALSE;

    /** @var bool */
    private $check_result = FALSE;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('user_model');
        $this->CI->load->library('audit');
    }

    /* ==================================================================
     * Login / logout
     * ================================================================ */

    /**
     * @return array{success: bool, message: string}
     */
    public function attempt(string $identifier, string $password, bool $remember): array
    {
        $identifier = trim($identifier);
        $ip = client_ip();
        $max = $this->CI->settings_model->get_int('max_login_attempts', (int) $this->CI->config->item('default_max_login_attempts'));
        $lockout = $this->CI->settings_model->get_int('lockout_minutes', (int) $this->CI->config->item('default_lockout_minutes'));
        $since = date('Y-m-d H:i:s', time() - $lockout * 60);
        $locked_message = 'Too many failed login attempts. Please try again after '.$lockout.' minutes.';

        if (mt_rand(1, 50) === 1)
        {
            $this->CI->user_model->purge_old_attempts();
            $this->CI->user_model->purge_expired_remember_tokens();
        }

        // Throttle by IP (higher limit: several users may share one office/home IP) and by identifier
        if ($this->CI->user_model->failed_attempts_since('ip_address', $ip, $since) >= $max * 4
            || $this->CI->user_model->failed_attempts_since('login_identifier', $identifier, $since) >= $max)
        {
            return array('success' => FALSE, 'message' => $locked_message);
        }

        $user = $this->CI->user_model->find_by_login($identifier);

        if ($user !== NULL && $user['locked_until'] !== NULL && strtotime($user['locked_until']) > time())
        {
            $this->CI->user_model->record_attempt($identifier, $ip, FALSE);
            $minutes = (int) ceil((strtotime($user['locked_until']) - time()) / 60);
            return array('success' => FALSE, 'message' => 'This account is temporarily locked. Please try again after '.$minutes.' minute(s).');
        }

        $password_ok = password_verify($password, $user !== NULL ? $user['password_hash'] : self::DUMMY_HASH) && $user !== NULL;

        if ( ! $password_ok)
        {
            $this->CI->user_model->record_attempt($identifier, $ip, FALSE);
            if ($user !== NULL)
            {
                $failed = (int) $user['failed_attempts'] + 1;
                $update = array('failed_attempts' => min($failed, 255));
                if ($failed >= $max)
                {
                    $update['locked_until'] = date('Y-m-d H:i:s', time() + $lockout * 60);
                    $update['failed_attempts'] = 0;
                }
                $this->CI->user_model->update((int) $user['id'], $update);
                $this->CI->audit->log('Login Failed', 'auth', $user['id'], NULL, array('reason' => 'Wrong password', 'locked' => isset($update['locked_until'])), $this->actor($user));
                if (isset($update['locked_until']))
                {
                    return array('success' => FALSE, 'message' => $locked_message);
                }
            }
            return array('success' => FALSE, 'message' => 'Invalid username/e-mail or password.');
        }

        if ($user['status'] !== 'Active')
        {
            $this->CI->user_model->record_attempt($identifier, $ip, FALSE);
            $this->CI->audit->log('Login Failed', 'auth', $user['id'], NULL, array('reason' => 'Inactive account'), $this->actor($user));
            return array('success' => FALSE, 'message' => 'Your account is inactive. Please contact the administrator.');
        }

        $this->CI->user_model->record_attempt($identifier, $ip, TRUE);

        $update = array(
            'failed_attempts' => 0,
            'locked_until'    => NULL,
            'last_login_at'   => date('Y-m-d H:i:s'),
            'last_login_ip'   => $ip,
        );
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT))
        {
            $update['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $this->CI->user_model->update((int) $user['id'], $update);

        $this->start_session($user);
        if ($remember)
        {
            $this->issue_remember_token((int) $user['id']);
        }

        $this->CI->audit->log('Login Success', 'auth', $user['id'], NULL, array('remember_me' => $remember));
        return array('success' => TRUE, 'message' => 'Welcome, '.$user['full_name'].'!');
    }

    public function logout(bool $write_audit = TRUE): void
    {
        $user = current_user();

        $cookie = (string) $this->CI->input->cookie(self::REMEMBER_COOKIE);
        if ($cookie !== '' && strpos($cookie, ':') !== FALSE)
        {
            list($selector) = explode(':', $cookie, 2);
            if (preg_match('/^[a-f0-9]{24}$/', $selector))
            {
                $this->CI->user_model->delete_remember_token($selector);
            }
        }
        $this->clear_remember_cookie();

        if ($write_audit && $user !== NULL)
        {
            $this->CI->audit->log('Logout', 'auth', $user['id']);
        }

        $this->CI->session->unset_userdata(array('auth_user', 'auth_permissions', 'auth_last_activity'));
        $this->CI->session->sess_regenerate(TRUE);
        $this->checked = FALSE;
        $this->check_result = FALSE;
    }

    /**
     * Validate the current request's authentication. Called once per request by Auth_Controller.
     * Restores "remember me" logins, enforces idle timeout and re-reads the user and
     * permissions from the database so deactivation or role changes apply immediately.
     */
    public function check(): bool
    {
        if ($this->checked)
        {
            return $this->check_result;
        }
        $this->checked = TRUE;
        $this->check_result = FALSE;

        $session_user = current_user();

        if ($session_user === NULL)
        {
            $user = $this->restore_from_cookie();
            if ($user === NULL)
            {
                return FALSE;
            }
            $this->start_session($user);
            $this->CI->user_model->update((int) $user['id'], array('last_login_at' => date('Y-m-d H:i:s'), 'last_login_ip' => client_ip()));
            $this->CI->audit->log('Login Success', 'auth', $user['id'], NULL, array('method' => 'Remember me'));
            $session_user = current_user();
        }
        else
        {
            $timeout = $this->CI->settings_model->get_int('session_timeout_minutes', (int) $this->CI->config->item('default_session_timeout_minutes'));
            $last = (int) $this->CI->session->userdata('auth_last_activity');
            if ($timeout > 0 && $last > 0 && (time() - $last) > $timeout * 60)
            {
                $this->timed_out = TRUE;
                $this->CI->audit->log('Session Timeout', 'auth', $session_user['id']);
                // Keep remember-me logins alive: a valid cookie re-authenticates silently
                $this->CI->session->unset_userdata(array('auth_user', 'auth_permissions', 'auth_last_activity'));
                $this->checked = FALSE;
                $remembered = $this->restore_from_cookie();
                if ($remembered === NULL)
                {
                    $this->checked = TRUE;
                    return FALSE;
                }
                $this->timed_out = FALSE;
                $this->checked = TRUE;
                $this->start_session($remembered);
                $session_user = current_user();
            }
        }

        $fresh = $this->CI->user_model->find_with_role((int) $session_user['id']);
        if ($fresh === NULL || $fresh['status'] !== 'Active' || ($fresh['locked_until'] !== NULL && strtotime($fresh['locked_until']) > time()))
        {
            $this->logout(FALSE);
            return FALSE;
        }

        $this->store_session_user($fresh);
        $this->CI->session->set_userdata('auth_last_activity', time());
        $this->check_result = TRUE;
        return TRUE;
    }

    public function timed_out(): bool
    {
        return $this->timed_out;
    }

    /**
     * @param array<string, mixed> $user Row with role_key and role_name
     */
    private function start_session(array $user): void
    {
        $this->CI->session->sess_regenerate(TRUE);
        $this->store_session_user($user);
        $this->CI->session->set_userdata('auth_last_activity', time());
    }

    /**
     * @param array<string, mixed> $user
     */
    private function store_session_user(array $user): void
    {
        $this->CI->session->set_userdata(array(
            'auth_user' => array(
                'id'                   => (int) $user['id'],
                'username'             => $user['username'],
                'full_name'            => $user['full_name'],
                'email'                => $user['email'],
                'role_id'              => (int) $user['role_id'],
                'role_key'             => $user['role_key'],
                'role_name'            => $user['role_name'],
                'must_change_password' => (int) $user['must_change_password'],
            ),
            'auth_permissions' => $this->CI->user_model->permissions_for_role((int) $user['role_id']),
        ));
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function actor(array $user): array
    {
        return array('id' => $user['id'], 'username' => $user['username']);
    }

    /* ==================================================================
     * Remember me
     * ================================================================ */

    private function issue_remember_token(int $user_id): void
    {
        $selector  = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $expires   = time() + self::REMEMBER_DAYS * 86400;

        $this->CI->user_model->store_remember_token(
            $user_id,
            $selector,
            hash('sha256', $validator),
            date('Y-m-d H:i:s', $expires),
            (string) $this->CI->input->user_agent()
        );

        $this->CI->input->set_cookie(array(
            'name'     => self::REMEMBER_COOKIE,
            'value'    => $selector.':'.$validator,
            'expire'   => self::REMEMBER_DAYS * 86400,
            'path'     => '/',
            'secure'   => (bool) $this->CI->config->item('cookie_secure'),
            'httponly' => TRUE,
            'samesite' => 'Lax',
        ));
    }

    private function clear_remember_cookie(): void
    {
        $this->CI->input->set_cookie(array(
            'name'     => self::REMEMBER_COOKIE,
            'value'    => '',
            'expire'   => '',
            'path'     => '/',
            'secure'   => (bool) $this->CI->config->item('cookie_secure'),
            'httponly' => TRUE,
            'samesite' => 'Lax',
        ));
    }

    /**
     * @return array<string, mixed>|null User row (with role) when the cookie is valid. Token is rotated.
     */
    private function restore_from_cookie(): ?array
    {
        $cookie = (string) $this->CI->input->cookie(self::REMEMBER_COOKIE);
        if ($cookie === '' || ! preg_match('/^([a-f0-9]{24}):([a-f0-9]{64})$/', $cookie, $m))
        {
            if ($cookie !== '')
            {
                $this->clear_remember_cookie();
            }
            return NULL;
        }

        $token = $this->CI->user_model->find_remember_token($m[1]);
        if ($token === NULL || strtotime($token['expires_at']) < time())
        {
            $this->clear_remember_cookie();
            return NULL;
        }

        if ( ! hash_equals($token['validator_hash'], hash('sha256', $m[2])))
        {
            // Selector known but validator wrong: possible stolen cookie - revoke all of this user's tokens
            $this->CI->user_model->delete_all_remember_tokens((int) $token['user_id']);
            $this->clear_remember_cookie();
            log_message('error', 'Remember-me validator mismatch for user '.$token['user_id'].' from '.client_ip());
            return NULL;
        }

        $this->CI->user_model->delete_remember_token($m[1]);

        $user = $this->CI->user_model->find_with_role((int) $token['user_id']);
        if ($user === NULL || $user['status'] !== 'Active' || ($user['locked_until'] !== NULL && strtotime($user['locked_until']) > time()))
        {
            $this->clear_remember_cookie();
            return NULL;
        }

        $this->issue_remember_token((int) $user['id']);
        return $user;
    }

    /* ==================================================================
     * Passwords
     * ================================================================ */

    public function hash_password(string $plain): string
    {
        return password_hash($plain, PASSWORD_DEFAULT);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function change_password(int $user_id, string $current, string $new): array
    {
        $user = $this->CI->user_model->find($user_id);
        if ($user === NULL || ! password_verify($current, $user['password_hash']))
        {
            return array('success' => FALSE, 'message' => 'The current password is incorrect.');
        }
        if (password_verify($new, $user['password_hash']))
        {
            return array('success' => FALSE, 'message' => 'The new password must be different from the current password.');
        }

        $this->CI->user_model->update($user_id, array(
            'password_hash'        => $this->hash_password($new),
            'password_changed_at'  => date('Y-m-d H:i:s'),
            'must_change_password' => 0,
            'updated_by'           => $user_id,
        ));
        // Sign out other devices
        $this->CI->user_model->delete_all_remember_tokens($user_id);
        $this->clear_remember_cookie();
        $this->CI->session->sess_regenerate(TRUE);

        $this->CI->audit->log('Password Changed', 'auth', $user_id);
        return array('success' => TRUE, 'message' => 'Your password has been changed.');
    }

    /**
     * Always behaves the same whether or not the account exists (no user enumeration).
     *
     * @return array{dev_link: string|null}
     */
    public function request_password_reset(string $identifier): array
    {
        $result = array('dev_link' => NULL);
        $user = $this->CI->user_model->find_by_login($identifier);
        if ($user === NULL || $user['status'] !== 'Active')
        {
            return $result;
        }

        $since = date('Y-m-d H:i:s', time() - 3600);
        if ($this->CI->user_model->recent_reset_requests((int) $user['id'], $since) >= self::RESET_PER_HOUR)
        {
            $this->CI->audit->log('Password Reset Throttled', 'auth', $user['id'], NULL, NULL, $this->actor($user));
            return $result;
        }

        $token = bin2hex(random_bytes(32));
        $this->CI->user_model->create_password_reset(
            (int) $user['id'],
            hash('sha256', $token),
            date('Y-m-d H:i:s', time() + self::RESET_MINUTES * 60),
            client_ip()
        );

        $link = site_url('auth/reset-password/'.$token);
        $sent = $this->send_reset_email($user, $link);
        $this->CI->audit->log('Password Reset Requested', 'auth', $user['id'], NULL, array('email_sent' => $sent), $this->actor($user));

        if (ENVIRONMENT === 'development')
        {
            $result['dev_link'] = $link;
        }
        return $result;
    }

    /**
     * @return array<string, mixed>|null Reset row when the token is valid
     */
    public function find_reset(string $token): ?array
    {
        if ( ! preg_match('/^[a-f0-9]{64}$/', $token))
        {
            return NULL;
        }
        return $this->CI->user_model->find_valid_reset(hash('sha256', $token));
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function reset_password(string $token, string $new): array
    {
        $reset = $this->find_reset($token);
        if ($reset === NULL)
        {
            return array('success' => FALSE, 'message' => 'This password reset link is invalid or has expired.');
        }
        $user_id = (int) $reset['user_id'];

        $this->CI->db->trans_start();
        $this->CI->user_model->update($user_id, array(
            'password_hash'        => $this->hash_password($new),
            'password_changed_at'  => date('Y-m-d H:i:s'),
            'must_change_password' => 0,
            'failed_attempts'      => 0,
            'locked_until'         => NULL,
        ));
        $this->CI->user_model->mark_reset_used((int) $reset['id']);
        $this->CI->user_model->delete_all_remember_tokens($user_id);
        $this->CI->db->trans_complete();

        if ( ! $this->CI->db->trans_status())
        {
            return array('success' => FALSE, 'message' => 'The password could not be reset. Please try again.');
        }

        $user = $this->CI->user_model->find($user_id);
        $this->CI->audit->log('Password Reset Completed', 'auth', $user_id, NULL, NULL, $this->actor($user));
        return array('success' => TRUE, 'message' => 'Your password has been reset. You can now log in.');
    }

    /**
     * @param array<string, mixed> $user
     */
    private function send_reset_email(array $user, string $link): bool
    {
        $association = association('association_name');
        $subject = 'Password reset - '.$association;
        $body = "Dear {$user['full_name']},\n\n"
              ."We received a request to reset your password for the {$association} portal.\n\n"
              ."Open this link within ".self::RESET_MINUTES." minutes to choose a new password:\n{$link}\n\n"
              ."If you did not request this, you can ignore this e-mail; your password will not change.\n\n"
              ."Association Management";

        $protocol = (string) env('MAIL_PROTOCOL', 'mail');
        if ($protocol === 'log')
        {
            log_message('error', 'MAIL (log mode) to '.$user['email'].' | '.$subject.' | '.$link);
            return TRUE;
        }

        $this->CI->load->library('email');
        $this->CI->email->initialize(array(
            'protocol'    => $protocol,
            'smtp_host'   => (string) env('MAIL_HOST', ''),
            'smtp_port'   => (int) env('MAIL_PORT', 587),
            'smtp_user'   => (string) env('MAIL_USERNAME', ''),
            'smtp_pass'   => (string) env('MAIL_PASSWORD', ''),
            'smtp_crypto' => (string) env('MAIL_CRYPTO', ''),
            'smtp_timeout'=> 15,
            'mailtype'    => 'text',
            'charset'     => 'utf-8',
            'newline'     => "\r\n",
            'crlf'        => "\r\n",
        ));
        $this->CI->email->from((string) env('MAIL_FROM_ADDRESS', 'no-reply@example.com'), (string) env('MAIL_FROM_NAME', $association));
        $this->CI->email->to($user['email']);
        $this->CI->email->subject($subject);
        $this->CI->email->message($body);

        $sent = @$this->CI->email->send(FALSE);
        if ( ! $sent)
        {
            log_message('error', 'Password reset e-mail failed for user '.$user['id'].': '.strip_tags($this->CI->email->print_debugger(array('headers'))));
        }
        return (bool) $sent;
    }
}
