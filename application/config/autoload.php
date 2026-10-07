<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| Auto-loader
| -------------------------------------------------------------------------
| Keep this list small: everything here is loaded on every request.
*/
$autoload['packages']  = array();
$autoload['libraries'] = array('database', 'session');
$autoload['drivers']   = array();
$autoload['helper']    = array('url', 'form', 'security', 'app', 'permission', 'ui');
$autoload['config']    = array('app');
$autoload['language']  = array();
$autoload['model']     = array('settings_model');
