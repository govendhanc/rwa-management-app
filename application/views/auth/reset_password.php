<?php defined('BASEPATH') OR exit('No direct script access allowed');
$new = field_error('new_password');
$confirm = field_error('confirm_password');
?>
<?php if ( ! $valid): ?>
    <div class="alert alert-danger d-flex gap-2 align-items-center">
        <i class="fa-solid fa-link-slash"></i>
        <div>This password reset link is invalid, has already been used, or has expired.</div>
    </div>
    <a href="<?= e(site_url('auth/forgot-password')) ?>" class="btn btn-primary w-100">Request a new link</a>
<?php else: ?>
    <?php if ($error !== NULL): ?>
        <div class="alert alert-danger py-2"><?= e($error) ?></div>
    <?php endif; ?>

    <?= form_open('auth/reset-password/'.$token, array('novalidate' => 'novalidate')) ?>
        <div class="mb-3">
            <label for="new_password" class="form-label">New Password <span class="required">*</span></label>
            <div class="input-group has-validation">
                <input type="password" class="form-control<?= $new['class'] ?>" id="new_password" name="new_password" required minlength="8" maxlength="72" autocomplete="new-password" autofocus>
                <button class="btn btn-outline-secondary" type="button" data-toggle-password="#new_password" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                <?= $new['message'] ?>
            </div>
            <div class="form-text">At least 8 characters, with at least one letter and one number.</div>
        </div>
        <div class="mb-4">
            <label for="confirm_password" class="form-label">Confirm New Password <span class="required">*</span></label>
            <input type="password" class="form-control<?= $confirm['class'] ?>" id="confirm_password" name="confirm_password" required maxlength="72" autocomplete="new-password">
            <?= $confirm['message'] ?>
        </div>
        <button type="submit" class="btn btn-primary w-100 py-2"><i class="fa-solid fa-check me-1"></i> Set New Password</button>
    <?= form_close() ?>
<?php endif; ?>

<div class="text-center mt-3 small">
    <a href="<?= e(site_url('auth/login')) ?>"><i class="fa-solid fa-arrow-left me-1"></i>Back to sign in</a>
</div>
