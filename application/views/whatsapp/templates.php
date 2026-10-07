<?php defined('BASEPATH') OR exit('No direct script access allowed');
$CI =& get_instance();
?>
<div class="alert alert-info small d-flex gap-2">
    <i class="fa-brands fa-whatsapp fs-5"></i>
    <div>Current mode: <strong><?= $mode === 'cloud_api' ? 'WhatsApp Cloud API (sent from the server)' : 'Click-to-Chat (opens WhatsApp with the message typed)' ?></strong>.
        <?php if (can('settings.manage')): ?><a href="<?= e(site_url('whatsapp/settings')) ?>">Change in WhatsApp Settings</a>.<?php endif; ?></div>
</div>

<div class="row g-3">
<?php foreach ($templates as $t): ?>
    <div class="col-xl-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><?= e($t['template_name']) ?> <code class="small ms-1"><?= e($t['template_key']) ?></code></span>
                <span><?= (int) $t['is_active'] === 1 ? status_badge('Active') : status_badge('Inactive') ?></span>
            </div>
            <div class="card-body">
                <div class="small text-muted mb-1">Preview with sample values</div>
                <div class="wa-bubble"><?= e($CI->whatsapp_service->render($t['message_body'], $CI->whatsapp_service->sample_vars($t['template_key']))) ?></div>
                <div class="small text-muted mt-2">
                    Cloud API template: <?= $t['cloud_template_name'] ? '<code>'.e($t['cloud_template_name']).'</code> ('.e($t['cloud_language']).')' : '<em>none - free-form message</em>' ?>
                    &middot; Updated <?= e(fmt_datetime($t['updated_at'])) ?><?= $t['updated_by_name'] ? ' by '.e($t['updated_by_name']) : '' ?>
                </div>
            </div>
            <div class="card-footer bg-transparent">
                <a href="<?= e(site_url('whatsapp/edit/'.$t['id'])) ?>" class="btn btn-sm btn-primary"><i class="fa-solid fa-pen me-1"></i> Edit template</a>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>
