<?php defined('BASEPATH') OR exit('No direct script access allowed');
$f = array();
foreach (array('template_name', 'message_body', 'cloud_template_name', 'cloud_language') as $n)
{
    $f[$n] = field_error($n);
}
$val = function (string $k) use ($template) { return set_value($k, (string) $template[$k]); };
$descriptions = array(
    'OWNER_NAME' => 'Owner name', 'PLOT_NO' => 'Plot number(s)', 'HOUSE_NO' => 'House number(s)', 'MONTH' => 'Receipt: months paid with year; reminder: month name',
    'YEAR' => 'Year', 'AMOUNT' => 'Amount (paid / due this month)', 'PAYMENT_MODE' => 'Payment mode', 'RECEIPT_NO' => 'Receipt number',
    'BALANCE' => 'Receipt: balance after payment; reminder: total outstanding', 'ASSOCIATION_NAME' => 'Association name', 'PAYMENT_DATE' => 'Payment date', 'DUE_DATE' => 'Due date',
);
?>
<?php if ( ! empty($form_error)): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= e($form_error) ?></div>
<?php endif; ?>

<?= form_open('whatsapp/edit/'.$template['id'], array('novalidate' => 'novalidate', 'id' => 'templateForm')) ?>
<div class="row g-3">
    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-header">Message <code class="small ms-1"><?= e($template['template_key']) ?></code></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="template_name">Template Name <span class="required">*</span></label>
                    <input type="text" class="form-control<?= $f['template_name']['class'] ?>" id="template_name" name="template_name" maxlength="100" value="<?= e($val('template_name')) ?>">
                    <?= $f['template_name']['message'] ?>
                </div>
                <div class="mb-2">
                    <label class="form-label d-flex justify-content-between" for="message_body"><span>Message <span class="required">*</span></span><span class="small text-muted"><span id="bodyLength">0</span> / <?= (int) $max_length ?></span></label>
                    <textarea class="form-control font-monospace<?= $f['message_body']['class'] ?>" id="message_body" name="message_body" rows="14" maxlength="<?= (int) $max_length ?>" data-preview-url="<?= e(site_url('whatsapp/preview')) ?>" data-template-key="<?= e($template['template_key']) ?>"><?= e($val('message_body')) ?></textarea>
                    <?= $f['message_body']['message'] ?>
                    <div class="form-text">Use *bold* and _italic_ as in WhatsApp. Click a placeholder on the right to insert it at the cursor.</div>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" <?= set_checkbox('is_active', '1', (int) $template['is_active'] === 1) ?>>
                    <label class="form-check-label" for="is_active">Active (inactive templates cannot be sent)</label>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Cloud API (optional)</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label" for="cloud_template_name">Meta-approved template name</label>
                        <input type="text" class="form-control<?= $f['cloud_template_name']['class'] ?>" id="cloud_template_name" name="cloud_template_name" maxlength="100" value="<?= e($val('cloud_template_name')) ?>" placeholder="e.g. payment_received">
                        <?= $f['cloud_template_name']['message'] ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="cloud_language">Language code</label>
                        <input type="text" class="form-control<?= $f['cloud_language']['class'] ?>" id="cloud_language" name="cloud_language" maxlength="10" value="<?= e($val('cloud_language')) ?>">
                        <?= $f['cloud_language']['message'] ?>
                    </div>
                </div>
                <div class="form-text mt-2">
                    In Cloud API mode, if a template name is set the portal sends that approved template. The placeholders in the message above are passed as
                    body variables {{1}}, {{2}}, ... in the order they first appear: <span id="cloudOrder" class="fw-semibold"></span>.
                    The receipt PDF is sent as the template's document header. Leave blank to send a free-form message (delivered only within 24 hours of the owner's last message).
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Template</button>
            <a href="<?= e(site_url('whatsapp/templates')) ?>" class="btn btn-light">Cancel</a>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header">Live preview</div>
            <div class="card-body">
                <div class="wa-bubble" id="previewText"></div>
                <div class="text-danger small mt-2 d-none" id="previewUnknown"></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Placeholders</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($placeholders as $p): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-1">
                        <button type="button" class="btn btn-link btn-sm p-0 font-monospace js-insert" data-token="{<?= e($p) ?>}">{<?= e($p) ?>}</button>
                        <span class="small text-muted"><?= e($descriptions[$p] ?? '') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?= form_close() ?>
