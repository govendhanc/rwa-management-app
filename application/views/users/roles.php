<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-actions">
    <div class="text-muted small">Tick the actions each role may perform. Super Admin always has full access.</div>
    <a href="<?= e(site_url('users')) ?>" class="btn btn-light"><i class="fa-solid fa-arrow-left me-1"></i> Back to Users</a>
</div>

<?= form_open('users/roles') ?>
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead>
                <tr>
                    <th>Permission</th>
                    <?php foreach ($roles as $role): ?>
                        <th class="text-center"><?= e($role['role_name']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($modules as $module => $permissions): ?>
                <tr class="table-light">
                    <td colspan="<?= count($roles) + 1 ?>" class="fw-semibold text-uppercase small"><?= e(ucfirst($module)) ?></td>
                </tr>
                <?php foreach ($permissions as $perm): ?>
                    <tr>
                        <td>
                            <div><?= e($perm['description']) ?></div>
                            <code class="small"><?= e($perm['perm_key']) ?></code>
                        </td>
                        <?php foreach ($roles as $role):
                            $is_super = $role['role_key'] === 'super_admin';
                            $checked = $is_super || in_array((int) $perm['id'], $grants[(int) $role['id']] ?? array(), TRUE);
                        ?>
                            <td class="text-center">
                                <input class="form-check-input" type="checkbox"
                                       name="grants[<?= (int) $role['id'] ?>][]" value="<?= (int) $perm['id'] ?>"
                                       aria-label="<?= e($role['role_name'].': '.$perm['perm_key']) ?>"
                                       <?= $checked ? 'checked' : '' ?> <?= $is_super ? 'disabled' : '' ?>>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Permissions</button>
</div>
<?= form_close() ?>
