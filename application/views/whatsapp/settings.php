<?php defined('BASEPATH') OR exit('No direct script access allowed');
$s = $settings;
$f = array();
foreach (array_keys($s) as $n)
{
    $f[$n] = field_error($n);
}
?>
<div class="row g-3">
    <div class="col-xl-7">
        <?= form_open('whatsapp/settings', array('novalidate' => 'novalidate')) ?>
        <div class="card mb-3">
            <div class="card-header">Sending mode</div>
            <div class="card-body">
                <div class="form-check mb-3">
                    <input class="form-check-input" type="radio" name="whatsapp_mode" id="modeC2C" value="click_to_chat" <?= set_radio('whatsapp_mode', 'click_to_chat', $s['whatsapp_mode'] === 'click_to_chat') ?>>
                    <label class="form-check-label" for="modeC2C"><strong>Click-to-Chat</strong> (recommended to start)<br>
                        <span class="small text-muted">No setup or cost. The portal opens WhatsApp (app or web) with the message typed; the user taps Send. PDFs must be attached manually.</span></label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="whatsapp_mode" id="modeCloud" value="cloud_api" <?= set_radio('whatsapp_mode', 'cloud_api', $s['whatsapp_mode'] === 'cloud_api') ?>>
                    <label class="form-check-label" for="modeCloud"><strong>WhatsApp Business Cloud API</strong><br>
                        <span class="small text-muted">Sends directly from the server with the receipt PDF attached, and enables one-click bulk reminders. Needs a Meta WhatsApp Business account, a registered phone number and approved message templates; Meta charges per conversation.</span></label>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Numbers &amp; Cloud API</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="whatsapp_country_code">Country code <span class="required">*</span></label>
                        <div class="input-group has-validation"><span class="input-group-text">+</span>
                            <input type="text" class="form-control<?= $f['whatsapp_country_code']['class'] ?>" id="whatsapp_country_code" name="whatsapp_country_code" maxlength="4" value="<?= e(set_value('whatsapp_country_code', (string) $s['whatsapp_country_code'])) ?>">
                            <?= $f['whatsapp_country_code']['message'] ?></div>
                        <div class="form-text">Added to 10-digit mobile numbers.</div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="whatsapp_cloud_phone_number_id">Phone number ID</label>
                        <input type="text" class="form-control<?= $f['whatsapp_cloud_phone_number_id']['class'] ?>" id="whatsapp_cloud_phone_number_id" name="whatsapp_cloud_phone_number_id" maxlength="30" value="<?= e(set_value('whatsapp_cloud_phone_number_id', (string) $s['whatsapp_cloud_phone_number_id'])) ?>">
                        <?= $f['whatsapp_cloud_phone_number_id']['message'] ?>
                        <div class="form-text">From Meta &gt; WhatsApp &gt; API Setup.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="whatsapp_cloud_api_version">API version</label>
                        <input type="text" class="form-control<?= $f['whatsapp_cloud_api_version']['class'] ?>" id="whatsapp_cloud_api_version" name="whatsapp_cloud_api_version" maxlength="6" value="<?= e(set_value('whatsapp_cloud_api_version', (string) $s['whatsapp_cloud_api_version'])) ?>">
                        <?= $f['whatsapp_cloud_api_version']['message'] ?>
                    </div>
                </div>
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Settings</button>
        <?= form_close() ?>
    </div>

    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header">Cloud API status</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span>Access token (.env)</span><?= $token_set ? '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Configured</span>' : '<span class="text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>Not set</span>' ?></li>
                <li class="list-group-item d-flex justify-content-between"><span>PHP curl extension</span><?= $curl_loaded ? '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Loaded</span>' : '<span class="text-danger"><i class="fa-solid fa-circle-xmark me-1"></i>Missing</span>' ?></li>
                <li class="list-group-item d-flex justify-content-between"><span>Phone number ID</span><?= $s['whatsapp_cloud_phone_number_id'] !== '' ? '<span class="text-success"><i class="fa-solid fa-circle-check me-1"></i>Set</span>' : '<span class="text-warning"><i class="fa-solid fa-triangle-exclamation me-1"></i>Not set</span>' ?></li>
                <li class="list-group-item"><?= $cloud_ready ? '<span class="text-success fw-semibold">Ready to send.</span>' : '<span class="text-muted">'.e($cloud_reason).'</span>' ?></li>
            </ul>
            <div class="card-body small text-muted">
                The access token is a secret and is never stored in the database or shown here. Set it on the server in the <code>.env</code> file:
                <code class="d-block mt-1">WHATSAPP_CLOUD_ACCESS_TOKEN=...</code>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Send a test message (Cloud API)</div>
            <div class="card-body">
                <div class="input-group">
                    <input type="tel" class="form-control" id="testNumber" placeholder="10-digit mobile" maxlength="15" <?= $cloud_ready ? '' : 'disabled' ?>>
                    <button type="button" class="btn btn-outline-primary" id="testSendBtn" data-url="<?= e(site_url('whatsapp/test-send')) ?>" <?= $cloud_ready ? '' : 'disabled' ?>>Send test</button>
                </div>
                <div class="form-text">Free-form messages are delivered only if that number messaged your business number in the last 24 hours.</div>
            </div>
        </div>
    </div>
</div>
