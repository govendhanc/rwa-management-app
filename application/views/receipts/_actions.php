<?php defined('BASEPATH') OR exit('No direct script access allowed');
/** Receipt action bar: View / Download PDF / Print / Share WhatsApp. @var array<string, mixed> $receipt */
$r = $receipt;
$active = $r['status'] === 'Active';
?>
<div class="card mb-3 no-print">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center">
        <a href="<?= e(site_url('receipts/view/'.$r['id'])) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-eye me-1"></i> View Receipt</a>
        <?php if (can('receipts.download')): ?>
            <div class="btn-group">
                <a href="<?= e(site_url('receipts/pdf/'.$r['id'].'/a4')) ?>" class="btn btn-primary js-download-pdf"><i class="fa-solid fa-file-pdf me-1"></i> Download PDF</a>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false"><span class="visually-hidden">More formats</span></button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="<?= e(site_url('receipts/pdf/'.$r['id'].'/a4')) ?>">A4 PDF</a></li>
                    <li><a class="dropdown-item" href="<?= e(site_url('receipts/pdf/'.$r['id'].'/small')) ?>">Small (A5) PDF</a></li>
                    <li><a class="dropdown-item" href="<?= e(site_url('receipts/pdf/'.$r['id'].'/a4?inline=1')) ?>" target="_blank" rel="noopener">Open PDF in new tab</a></li>
                </ul>
            </div>
            <div class="btn-group">
                <a href="<?= e(site_url('receipts/print/'.$r['id'].'/a4')) ?>" class="btn btn-outline-primary" target="_blank" rel="noopener"><i class="fa-solid fa-print me-1"></i> Print</a>
                <button type="button" class="btn btn-outline-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false"><span class="visually-hidden">Print formats</span></button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="<?= e(site_url('receipts/print/'.$r['id'].'/a4')) ?>" target="_blank" rel="noopener">Print A4</a></li>
                    <li><a class="dropdown-item" href="<?= e(site_url('receipts/print/'.$r['id'].'/small')) ?>" target="_blank" rel="noopener">Print small (A5)</a></li>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($active && can('whatsapp.send')): ?>
            <button type="button" class="btn btn-whatsapp js-share-receipt" data-url="<?= e(site_url('receipts/whatsapp/'.$r['id'])) ?>"
                    data-pdf="<?= can('receipts.download') ? e(site_url('receipts/pdf/'.$r['id'].'/a4')) : '' ?>">
                <i class="fa-brands fa-whatsapp me-1"></i> Share on WhatsApp
            </button>
        <?php endif; ?>
        <?php if ( ! $active): ?>
            <span class="badge text-bg-danger fs-6"><i class="fa-solid fa-ban me-1"></i>Cancelled receipt</span>
        <?php endif; ?>
        <?php if ($active && can('whatsapp.send')): ?>
            <div class="small text-muted w-100 mt-1">
                <i class="fa-solid fa-circle-info me-1"></i>
                <?php if (sys_setting('whatsapp_mode', 'click_to_chat') === 'cloud_api'): ?>
                    <em>Share on WhatsApp</em> sends the message with the receipt PDF attached directly to the owner (WhatsApp Cloud API).
                <?php else: ?>
                    WhatsApp Click-to-Chat cannot attach files: <em>Share on WhatsApp</em> downloads the PDF and opens WhatsApp with the message &mdash; attach the downloaded PDF in the chat, then send.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
