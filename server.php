<?php
/**
 * Development router for PHP's built-in web server ONLY (not used by Apache/Nginx):
 *     php -S 127.0.0.1:8080 -t public server.php
 * Serves real files from /public directly and sends everything else to index.php.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== '/' && is_file(__DIR__.'/public'.$path))
{
    return FALSE;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__.'/public/index.php';
