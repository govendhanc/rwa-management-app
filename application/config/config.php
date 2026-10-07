<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| ROWA Portal - Core CodeIgniter configuration
| -------------------------------------------------------------------------
| Environment-specific values come from .env (see .env.example).
| Business configuration (association name, logo, receipt prefix ...) is
| stored in the database and edited from Settings, never here.
*/

$config['base_url'] = rtrim((string) env('APP_URL', 'http://localhost/'), '/').'/';

// URLs without index.php (public/.htaccess or Nginx try_files rewrites to index.php)
$config['index_page'] = '';

$config['uri_protocol'] = 'REQUEST_URI';
$config['url_suffix']   = '';
$config['language']     = 'english';
$config['charset']      = 'UTF-8';

$config['enable_hooks']      = FALSE;
$config['subclass_prefix']   = 'MY_';
$config['composer_autoload'] = FALSE;

$config['permitted_uri_chars']  = 'a-z 0-9~%.:_\-';
$config['enable_query_strings'] = FALSE;
$config['controller_trigger']   = 'c';
$config['function_trigger']     = 'm';
$config['directory_trigger']    = 'd';
$config['allow_get_array']      = TRUE;

/*
| Logging - files are written outside the web root.
*/
$config['log_threshold']        = (int) env('APP_LOG_THRESHOLD', 1);
$config['log_path']             = STORAGEPATH.'logs'.DIRECTORY_SEPARATOR;
$config['log_file_extension']   = 'log';
$config['log_file_permissions'] = 0640;
$config['log_date_format']      = 'Y-m-d H:i:s';

$config['error_views_path'] = '';

$config['cache_path']                   = STORAGEPATH.'cache'.DIRECTORY_SEPARATOR;
$config['cache_query_string']           = FALSE;

/*
| Encryption key (used by the Encryption library if ever needed).
| 64 hex chars in .env -> 32 raw bytes.
*/
$_rowa_key = (string) env('APP_ENCRYPTION_KEY', '');
$config['encryption_key'] = (strlen($_rowa_key) === 64 && ctype_xdigit($_rowa_key)) ? hex2bin($_rowa_key) : '';
unset($_rowa_key);

/*
| Sessions - stored in the database (ci_sessions table).
| Absolute lifetime 8 hours; idle timeout is enforced by Auth_Controller
| using system_settings.session_timeout_minutes.
*/
$config['sess_driver']             = 'database';
$config['sess_cookie_name']        = 'rowa_session';
$config['sess_samesite']           = 'Lax';
$config['sess_expiration']         = 28800;
$config['sess_save_path']          = 'ci_sessions';
$config['sess_match_ip']           = FALSE;
$config['sess_time_to_update']     = 300;
$config['sess_regenerate_destroy'] = TRUE;

/*
| Cookies
*/
$config['cookie_prefix']   = '';
$config['cookie_domain']   = '';
$config['cookie_path']     = '/';
$config['cookie_secure']   = (bool) env('APP_FORCE_HTTPS', FALSE);
$config['cookie_httponly'] = TRUE;
$config['cookie_samesite'] = 'Lax';

$config['standardize_newlines'] = FALSE;

/*
| XSS: we escape all output with e() / html_escape() instead of the
| deprecated global input filter.
*/
$config['global_xss_filtering'] = FALSE;

/*
| CSRF protection - every POST (forms and AJAX) must carry the token.
| Token is not regenerated per request so parallel AJAX calls keep working;
| it is still bound to a cookie and rotated when the cookie expires.
*/
$config['csrf_protection']   = TRUE;
$config['csrf_token_name']   = 'rowa_csrf';
$config['csrf_cookie_name']  = 'rowa_csrf_cookie';
$config['csrf_expire']       = 7200;
$config['csrf_regenerate']   = FALSE;
$config['csrf_exclude_uris'] = array();

$config['compress_output'] = FALSE;
$config['time_reference']  = 'local';
$config['rewrite_short_tags'] = FALSE;

$_rowa_proxies = trim((string) env('APP_PROXY_IPS', ''));
$config['proxy_ips'] = $_rowa_proxies;
unset($_rowa_proxies);
