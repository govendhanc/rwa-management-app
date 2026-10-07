<?php defined('BASEPATH') OR exit('No direct script access allowed');
$is_new = $user['id'] === NULL;
$f = array();
foreach (array('full_name', 'username', 'email', 'mobile', 'role_id', 'status', 'password', 'password_confirm') as $field)
{
    $f[$field] = field_error($field);
}
$action = $is_new ? 'users/create' : 'users/edit/'.$user['id'];
?>
<div class="card" style="max-width: 860px;">
    <div class="card-header"><?= $is_new ? 'New user' : 'Edit '.e($user['username']) ?></div>
    <div class="card-body">
        <?= form_open($action, array('novalidate' => 'novalidate', 'autocomplete' => 'off')) ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="full_name">Name <span class="required">*</span></label>
                <input type="text" class="form-control<?= $f['full_name']['class'] ?>" id="full_name" name="full_name" maxlength="100" required
                       value="<?= e(set_value('full_name', $user['full_name'])) ?>">
                <?= $f['full_name']['message'] ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="username">Username <span class="required">*</span></label>
                <input type="text" class="form-control<?= $f['username']['class'] ?>" id="username" name="username" maxlength="50" required
                       pattern="[A-Za-z0-9_\-]{3,50}" value="<?= e(set_value('username', $user['username'])) ?>">
                <div class="form-text">3-50 letters, numbers, dash or underscore.</div>
                <?= $f['username']['message'] ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">E-mail <span class="required">*</span></label>
                <input type="email" class="form-control<?= $f['email']['class'] ?>" id="email" name="email" maxlength="150" required
                       value="<?= e(set_value('email', $user['email'])) ?>">
                <?= $f['email']['message'] ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="mobile">Mobile</label>
                <input type="tel" class="form-control<?= $f['mobile']['class'] ?>" id="mobile" name="mobile" maxlength="15"
                       value="<?= e(set_value('mobile', (string) $user['mobile'])) ?>">
                <?= $f['mobile']['message'] ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="role_id">Role <span class="required">*</span></label>
                <select class="form-select<?= $f['role_id']['class'] ?>" id="role_id" name="role_id" required<?= $is_self ? ' disabled' : '' ?>>
                    <?= select_options($roles, set_value('role_id', (string) $user['role_id']), 'Select role') ?>
                </select>
                <?php if ($is_self): ?>
                    <input type="hidden" name="role_id" value="<?= (int) $user['role_id'] ?>">
                    <div class="form-text">You cannot change your own role.</div>
                <?php endif; ?>
                <?= $f['role_id']['message'] ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="status">Status <span class="required">*</span></label>
                <select class="form-select<?= $f['status']['class'] ?>" id="status" name="status" required<?= $is_self ? ' disabled' : '' ?>>
                    <?= select_options(array('Active', 'Inactive'), set_value('status', $user['status'])) ?>
                </select>
                <?php if ($is_self): ?>
                    <input type="hidden" name="status" value="Active">
                <?php endif; ?>
                <?= $f['status']['message'] ?>
            </div>

            <div class="col-12"><hr class="my-1"></div>
            <div class="col-12">
                <div class="section-title mb-0"><?= $is_new ? 'Password' : 'Set a new password (leave blank to keep the current one)' ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="password">Password<?= $is_new ? ' <span class="required">*</span>' : '' ?></label>
                <div class="input-group has-validation">
                    <input type="password" class="form-control<?= $f['password']['class'] ?>" id="password" name="password" maxlength="72" autocomplete="new-password"<?= $is_new ? ' required' : '' ?>>
                    <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                    <?= $f['password']['message'] ?>
                </div>
                <div class="form-text">At least 8 characters, with at least one letter and one number.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="password_confirm">Confirm Password<?= $is_new ? ' <span class="required">*</span>' : '' ?></label>
                <input type="password" class="form-control<?= $f['password_confirm']['class'] ?>" id="password_confirm" name="password_confirm" maxlength="72" autocomplete="new-password">
                <?= $f['password_confirm']['message'] ?>
            </div>
            <?php if ( ! $is_self): ?>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="1" id="must_change_password" name="must_change_password"
                        <?= set_checkbox('must_change_password', '1', (int) $user['must_change_password'] === 1) ?>>
                    <label class="form-check-label" for="must_change_password">Require the user to change this password at next login</label>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save User</button>
            <a href="<?= e(site_url('users')) ?>" class="btn btn-light">Cancel</a>
        </div>
        <?= form_close() ?>
    </div>
</div>
