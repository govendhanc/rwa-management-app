<?php defined('BASEPATH') OR exit('No direct script access allowed');
$identifier = field_error('identifier');
?>
<p class="text-muted small">Enter your username or registered e-mail address. We will e-mail you a link to set a new password.</p>

<?php if (is_string($dev_reset_link) && $dev_reset_link !== ''): ?>
    <div class="alert alert-info small text-break">
        <strong>Development mode only:</strong> e-mail is written to the log instead of being sent.
        <a href="<?= e($dev_reset_link) ?>">Open the reset link</a>.
    </div>
<?php endif; ?>

<?= form_open('auth/forgot-password', array('novalidate' => 'novalidate')) ?>
    <div class="mb-3">
        <label for="identifier" class="form-label">Username or E-mail</label>
        <input type="text" class="form-control<?= $identifier['class'] ?>" id="identifier" name="identifier" required maxlength="150" autocomplete="username" autofocus>
        <?= $identifier['message'] ?>
    </div>
    <button type="submit" class="btn btn-primary w-100 py-2"><i class="fa-solid fa-paper-plane me-1"></i> Send Reset Link</button>
<?= form_close() ?>

<div class="text-center mt-3 small">
    <a href="<?= e(site_url('auth/login')) ?>"><i class="fa-solid fa-arrow-left me-1"></i>Back to sign in</a>
</div>
