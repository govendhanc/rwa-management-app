<?php defined('BASEPATH') OR exit('No direct script access allowed');
$CI =& get_instance();
$logo = logo_url();
$wide = ! empty($auth_wide);
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
    <title><?= e(($page_title !== '' ? $page_title.' | ' : '').association('association_name')) ?></title>
    <link rel="icon" href="<?= $logo !== '' ? e($logo) : e(asset_url('img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('vendor/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('vendor/fontawesome/css/all.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
</head>
<body class="auth-body">
<div class="auth-wrapper">
    <div class="auth-card<?= $wide ? ' auth-card-wide' : '' ?>">
        <div class="auth-brand">
            <?php if ($logo !== ''): ?>
                <img src="<?= e($logo) ?>" alt="" class="auth-logo">
            <?php else: ?>
                <span class="auth-logo auth-logo-icon"><i class="fa-solid fa-city"></i></span>
            <?php endif; ?>
            <h1><?= e(association('association_name')) ?></h1>
            <p class="text-muted mb-0"><?= e($page_title) ?></p>
        </div>
