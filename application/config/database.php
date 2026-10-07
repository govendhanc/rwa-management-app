<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Database connection - values come from .env, never hard-coded here.
| Works with MySQL 8.0.16+ and MariaDB 10.4+.
| -------------------------------------------------------------------------
*/
$active_group  = 'default';
$query_builder = TRUE;

$db['default'] = array(
    'dsn'          => '',
    'hostname'     => (string) env('DB_HOST', 'localhost'),
    'port'         => (int) env('DB_PORT', 3306),
    'username'     => (string) env('DB_USERNAME', ''),
    'password'     => (string) env('DB_PASSWORD', ''),
    'database'     => (string) env('DB_DATABASE', ''),
    'dbdriver'     => 'mysqli',
    'dbprefix'     => '',
    'pconnect'     => FALSE,
    // Raw SQL errors are only displayed in development
    'db_debug'     => (ENVIRONMENT === 'development'),
    'cache_on'     => FALSE,
    'cachedir'     => '',
    'char_set'     => 'utf8mb4',
    'dbcollat'     => 'utf8mb4_unicode_ci',
    'swap_pre'     => '',
    'encrypt'      => FALSE,
    'compress'     => FALSE,
    // STRICT_ALL_TABLES: invalid data raises an error instead of being silently truncated
    'stricton'     => TRUE,
    'failover'     => array(),
    'save_queries' => (ENVIRONMENT === 'development'),
);
