<?php
/**
 * ROWA Portal - Front controller.
 *
 * The web server document root must point to this "public" directory.
 * application/, system/, storage/ and .env live one level up and are
 * therefore never directly reachable from the browser.
 *
 * Based on the CodeIgniter 3.1.13 front controller.
 */

/*
 *---------------------------------------------------------------
 * PROJECT ROOT AND ENVIRONMENT FILE
 *---------------------------------------------------------------
 */
define('ROOTPATH', dirname(__DIR__).DIRECTORY_SEPARATOR);
define('STORAGEPATH', ROOTPATH.'storage'.DIRECTORY_SEPARATOR);

require_once ROOTPATH.'application/third_party/Env.php';

// Do not advertise the PHP version (expose_php may be on in php.ini)
header_remove('X-Powered-By');
Env::load(ROOTPATH.'.env');

/*
 *---------------------------------------------------------------
 * APPLICATION ENVIRONMENT: development | testing | production
 *---------------------------------------------------------------
 */
$app_env = (string) env('APP_ENV', 'production');
define('ENVIRONMENT', in_array($app_env, array('development', 'testing', 'production'), TRUE) ? $app_env : 'production');

date_default_timezone_set((string) env('APP_TIMEZONE', 'Asia/Kolkata'));

/*
 *---------------------------------------------------------------
 * ERROR REPORTING
 *---------------------------------------------------------------
 * CodeIgniter 3.1.13 creates dynamic properties inside system/ classes,
 * which PHP 8.2+ reports as E_DEPRECATED. These are notices only (not errors),
 * so E_DEPRECATED is excluded in every environment. All other notices,
 * warnings and errors are shown in development.
 */
switch (ENVIRONMENT)
{
    case 'development':
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        ini_set('display_errors', '1');
        break;

    case 'testing':
    case 'production':
        ini_set('display_errors', '0');
        error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
        break;
}

/*
 *---------------------------------------------------------------
 * PATHS
 *---------------------------------------------------------------
 */
$system_path        = ROOTPATH.'system';
$application_folder = ROOTPATH.'application';
$view_folder        = '';

if (defined('STDIN'))
{
    chdir(__DIR__);
}

if (($_temp = realpath($system_path)) !== FALSE)
{
    $system_path = $_temp.DIRECTORY_SEPARATOR;
}
else
{
    $system_path = strtr(rtrim($system_path, '/\\'), '/\\', DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
}

if ( ! is_dir($system_path))
{
    header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
    echo 'System folder not found. Check $system_path in public/index.php.';
    exit(3);
}

define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));
define('BASEPATH', $system_path);
define('FCPATH', __DIR__.DIRECTORY_SEPARATOR);
define('SYSDIR', basename(BASEPATH));

if (($_temp = realpath($application_folder)) !== FALSE && is_dir($_temp))
{
    $application_folder = $_temp;
}
else
{
    header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
    echo 'Application folder not found. Check $application_folder in public/index.php.';
    exit(3);
}

define('APPPATH', $application_folder.DIRECTORY_SEPARATOR);

if ( ! isset($view_folder[0]) && is_dir(APPPATH.'views'.DIRECTORY_SEPARATOR))
{
    $view_folder = APPPATH.'views';
}
elseif ( ! is_dir($view_folder))
{
    header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
    echo 'View folder not found. Check $view_folder in public/index.php.';
    exit(3);
}

define('VIEWPATH', $view_folder.DIRECTORY_SEPARATOR);

unset($_temp, $app_env);

/*
 *---------------------------------------------------------------
 * LOAD THE BOOTSTRAP FILE
 *---------------------------------------------------------------
 */
require_once BASEPATH.'core/CodeIgniter.php';
