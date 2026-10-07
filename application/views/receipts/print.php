<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Receipt <?= e($receipt['receipt_no']) ?></title>
    <style>
        @page { size: <?= $format === 'small' ? 'A5' : 'A4' ?> portrait; margin: <?= $format === 'small' ? '10mm' : '14mm' ?>; }
        body { margin: 0; background: #e5e7eb; }
        .sheet { background: #fff; margin: 16px auto; padding: 24px; max-width: <?= $format === 'small' ? '148mm' : '210mm' ?>; box-shadow: 0 4px 18px rgba(0,0,0,.12); }
        .toolbar { text-align: center; margin: 12px; font-family: system-ui, sans-serif; }
        .toolbar button { padding: 8px 18px; font-size: 14px; border: 0; border-radius: 6px; background: #1d4f91; color: #fff; cursor: pointer; }
        @media print { body { background: #fff; } .sheet { margin: 0; padding: 0; box-shadow: none; max-width: none; } .toolbar { display: none; } }
    </style>
</head>
<body>
<div class="toolbar"><button type="button" id="printBtn">Print</button></div>
<div class="sheet"><?= $receipt_html ?></div>
<script nonce="<?= e(csp_nonce()) ?>">
    document.getElementById('printBtn').addEventListener('click', function () { window.print(); });
    window.addEventListener('load', function () { window.setTimeout(function () { window.print(); }, 300); });
</script>
</body>
</html>
