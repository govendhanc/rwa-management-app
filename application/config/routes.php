<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI routing
| -------------------------------------------------------------------------
| Standard CodeIgniter routing (controller/method/params) is used for all
| modules. Only friendly aliases are declared here.
*/
$route['default_controller']   = 'dashboard';
$route['404_override']         = '';
$route['translate_uri_dashes'] = TRUE;

$route['system-check'] = 'syscheck/index';
