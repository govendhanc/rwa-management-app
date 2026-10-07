<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/** @var string $heading */
/** @var string $message */
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo htmlspecialchars(strip_tags($heading), ENT_QUOTES, 'UTF-8'); ?></title>
<style>
    body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px;
           font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; background: #f3f6fa; color: #23324a; }
    .box { max-width: 560px; width: 100%; background: #fff; border: 1px solid #e3e9f1; border-radius: 16px; padding: 36px 32px; text-align: center;
           box-shadow: 0 12px 40px rgba(16, 38, 68, .08); }
    .icon { font-size: 44px; line-height: 1; }
    .code { color: #0f9d8a; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; font-size: 13px; margin-top: 14px; }
    h1 { font-size: 22px; margin: 8px 0 12px; }
    .msg { color: #5b6b82; font-size: 15px; line-height: 1.6; }
    .msg p { margin: 0 0 8px; }
    a.btn { display: inline-block; margin-top: 18px; padding: 10px 20px; border-radius: 10px; background: #1d4f91; color: #fff; text-decoration: none; font-weight: 600; }
    a.btn:hover { background: #163d70; }
</style>
</head>
<body>
<div class="box">
    <div class="icon">&#128269;</div>
    <div class="code">404</div>
    <h1><?php echo htmlspecialchars(strip_tags($heading), ENT_QUOTES, 'UTF-8'); ?></h1>
    <div class="msg"><?php echo $message; ?></div>
    <a class="btn" href="javascript:history.back()">Go back</a>
</div>
</body>
</html>
