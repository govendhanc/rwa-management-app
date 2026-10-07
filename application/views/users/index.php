<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-actions">
    <div class="text-muted small">Users who can sign in to the portal. Users are deactivated, never deleted.</div>
    <div class="d-flex gap-2">
        <?php if (is_super_admin()): ?>
            <a href="<?= e(site_url('users/roles')) ?>" class="btn btn-outline-primary"><i class="fa-solid fa-user-lock me-1"></i> Roles &amp; Permissions</a>
        <?php endif; ?>
        <a href="<?= e(site_url('users/create')) ?>" class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i> Add User</a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover w-100" id="usersTable">
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>E-mail</th>
                    <th>Mobile</th>
                    <th>Role</th>
                    <th>Last Login</th>
                    <th>Status</th>
                    <th class="no-export text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $i => $u):
                $locked = $u['locked_until'] !== NULL && strtotime($u['locked_until']) > time();
                $is_self = (int) $u['id'] === user_id();
                $can_touch = $u['role_key'] !== 'super_admin' || is_super_admin();
            ?>
                <tr data-id="<?= (int) $u['id'] ?>">
                    <td><?= $i + 1 ?></td>
                    <td class="fw-semibold"><?= e($u['full_name']) ?><?= $is_self ? ' <span class="badge text-bg-light">You</span>' : '' ?></td>
                    <td><?= e($u['username']) ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= e($u['mobile']) ?></td>
                    <td><?= e($u['role_name']) ?></td>
                    <td data-order="<?= e($u['last_login_at']) ?>"><?= $u['last_login_at'] ? e(fmt_datetime($u['last_login_at'])) : '<span class="text-muted">Never</span>' ?></td>
                    <td class="js-status">
                        <?= status_badge($u['status']) ?>
                        <?php if ($locked): ?><span class="badge rounded-pill text-bg-warning js-locked">Locked</span><?php endif; ?>
                    </td>
                    <td class="table-actions text-end">
                        <?php if ($can_touch): ?>
                            <a href="<?= e(site_url('users/edit/'.$u['id'])) ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit"><i class="fa-solid fa-pen"></i></a>
                            <?php if ($locked): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning js-unlock" data-url="<?= e(site_url('users/unlock/'.$u['id'])) ?>" data-bs-toggle="tooltip" title="Unlock"><i class="fa-solid fa-lock-open"></i></button>
                            <?php endif; ?>
                            <?php if ( ! $is_self): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary js-reset" data-url="<?= e(site_url('users/reset-password/'.$u['id'])) ?>" data-name="<?= e($u['username']) ?>" data-bs-toggle="tooltip" title="Reset password"><i class="fa-solid fa-key"></i></button>
                                <button type="button" class="btn btn-sm <?= $u['status'] === 'Active' ? 'btn-outline-danger' : 'btn-outline-success' ?> js-toggle" data-url="<?= e(site_url('users/toggle-status/'.$u['id'])) ?>" data-name="<?= e($u['username']) ?>" data-status="<?= e($u['status']) ?>" data-bs-toggle="tooltip" title="<?= $u['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>">
                                    <i class="fa-solid <?= $u['status'] === 'Active' ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Temporary password modal -->
<div class="modal fade" id="tempPasswordModal" tabindex="-1" aria-labelledby="tempPasswordTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tempPasswordTitle">Temporary password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Give this password to <strong id="tempPasswordUser"></strong> securely. They must change it at their next login.</p>
                <div class="input-group">
                    <input type="text" class="form-control font-monospace fs-5" id="tempPasswordValue" readonly>
                    <button class="btn btn-outline-primary" type="button" id="tempPasswordCopy"><i class="fa-regular fa-copy me-1"></i>Copy</button>
                </div>
                <div class="form-text">This password is shown only once.</div>
            </div>
        </div>
    </div>
</div>
