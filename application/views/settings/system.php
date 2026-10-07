<?php defined('BASEPATH') OR exit('No direct script access allowed');
$f = array();
foreach (array_keys($values) as $n)
{
    $f[$n] = field_error($n);
}
$val = function (string $k) use ($values) { return set_value($k, (string) $values[$k]); };
?>
<div class="card" style="max-width: 760px;">
    <div class="card-header">Security &amp; display</div>
    <div class="card-body">
        <?= form_open('settings/system', array('novalidate' => 'novalidate')) ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="session_timeout_minutes">Idle timeout (minutes)</label>
                <input type="number" class="form-control<?= $f['session_timeout_minutes']['class'] ?>" id="session_timeout_minutes" name="session_timeout_minutes" min="5" max="480" value="<?= e($val('session_timeout_minutes')) ?>">
                <?= $f['session_timeout_minutes']['message'] ?>
                <div class="form-text">Users are signed out after this much inactivity (5-480).</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="max_login_attempts">Failed logins before lockout</label>
                <input type="number" class="form-control<?= $f['max_login_attempts']['class'] ?>" id="max_login_attempts" name="max_login_attempts" min="3" max="20" value="<?= e($val('max_login_attempts')) ?>">
                <?= $f['max_login_attempts']['message'] ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="lockout_minutes">Lockout duration (minutes)</label>
                <input type="number" class="form-control<?= $f['lockout_minutes']['class'] ?>" id="lockout_minutes" name="lockout_minutes" min="1" max="1440" value="<?= e($val('lockout_minutes')) ?>">
                <?= $f['lockout_minutes']['message'] ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="date_format">Date format</label>
                <select class="form-select<?= $f['date_format']['class'] ?>" id="date_format" name="date_format">
                    <?php foreach ($date_formats as $fmt => $example): ?>
                        <option value="<?= e($fmt) ?>" <?= $val('date_format') === $fmt ? 'selected' : '' ?>><?= e($example) ?></option>
                    <?php endforeach; ?>
                </select>
                <?= $f['date_format']['message'] ?>
            </div>
        </div>
        <button type="submit" class="btn btn-primary mt-4"><i class="fa-solid fa-floppy-disk me-1"></i> Save</button>
        <?= form_close() ?>
    </div>
</div>
<p class="small text-muted mt-3">Database and e-mail settings are kept in the server's <code>.env</code> file and are not editable here. WhatsApp settings are under <a href="<?= e(site_url('whatsapp/settings')) ?>">WhatsApp &gt; Settings</a>.</p>
