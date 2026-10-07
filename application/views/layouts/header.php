<?php defined('BASEPATH') OR exit('No direct script access allowed');
$CI =& get_instance();
$app_title = association('short_name') !== '' ? association('short_name') : $CI->config->item('app_name');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="base-url" content="<?= e(base_url()) ?>">
    <meta name="csrf-name" content="<?= e($CI->security->get_csrf_token_name()) ?>">
    <meta name="csrf-hash" content="<?= e($CI->security->get_csrf_hash()) ?>">
    <meta name="csrf-cookie" content="<?= e($CI->config->item('cookie_prefix').$CI->config->item('csrf_cookie_name')) ?>">
    <meta name="currency-symbol" content="<?= e(currency_symbol()) ?>">
    <meta name="wa-mode" content="<?= e((string) sys_setting('whatsapp_mode', 'click_to_chat')) ?>">
    <title><?= e(($page_title !== '' ? $page_title.' | ' : '').$app_title) ?></title>
    <link rel="icon" href="<?= logo_url() !== '' ? e(logo_url()) : e(asset_url('img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('vendor/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('vendor/fontawesome/css/all.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('vendor/datatables/css/dataTables.bootstrap5.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('vendor/datatables/css/buttons.bootstrap5.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('vendor/datatables/css/responsive.bootstrap5.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
<?php foreach ($styles as $style): ?>
    <link rel="stylesheet" href="<?= e(asset_url($style)) ?>">
<?php endforeach; ?>
</head>
<body class="app-body">
<div class="app-shell">
