<?php defined('BASEPATH') OR exit('No direct script access allowed');
$current = field_error('current_password');
$new = field_error('new_password');
$confirm = field_error('confirm_password');
?>
<?php if ($forced): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-center py-2">
        <i class="fa-solid fa-shield-halved"></i>
        <div>For security, please replace the temporary password before using the portal.</div>
    </div>
<?php endif; ?>

<?php if ($error !== NULL): ?>
    <div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<?= form_open('auth/change-password', array('novalidate' => 'novalidate')) ?>
    <div class="mb-3">
        <label for="current_password" class="form-label">Current Password <span class="required">*</span></label>
        <div class="input-group has-validation">
            <input type="password" class="form-control<?= $current['class'] ?>" id="current_password" name="current_password" required maxlength="72" autocomplete="current-password">
            <button class="btn btn-outline-secondary" type="button" data-toggle-password="#current_password" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
            <?= $current['message'] ?>
        </div>
    </div>
    <div class="mb-3">
        <label for="new_password" class="form-label">New Password <span class="required">*</span></label>
        <div class="input-group has-validation">
            <input type="password" class="form-control<?= $new['class'] ?>" id="new_password" name="new_password" required minlength="8" maxlength="72" autocomplete="new-password">
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
    <button type="submit" class="btn btn-primary w-100 py-2"><i class="fa-solid fa-key me-1"></i> Change Password</button>
<?= form_close() ?>

<div class="d-flex justify-content-between mt-3 small">
    <?php if ( ! $forced): ?>
        <a href="<?= e(site_url('dashboard')) ?>"><i class="fa-solid fa-arrow-left me-1"></i>Back to dashboard</a>
    <?php else: ?>
        <span></span>
    <?php endif; ?>
    <?= form_open('auth/logout', array('class' => 'd-inline')) ?>
        <button type="submit" class="btn btn-link btn-sm p-0 text-danger" data-no-loading>Logout</button>
    <?= form_close() ?>
</div>
