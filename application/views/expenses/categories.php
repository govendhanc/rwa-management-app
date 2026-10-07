<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="row g-3">
    <?php if (can('expenses.edit')): ?>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">Add category</div>
            <div class="card-body">
                <?= form_open('expenses/save-category') ?>
                    <div class="mb-3"><label class="form-label" for="cat_name">Name <span class="required">*</span></label><input type="text" class="form-control" id="cat_name" name="name" maxlength="60" required></div>
                    <div class="mb-3"><label class="form-label" for="cat_desc">Description</label><input type="text" class="form-control" id="cat_desc" name="description" maxlength="255"></div>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i> Add</button>
                <?= form_close() ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="<?= can('expenses.edit') ? 'col-xl-8' : 'col-12' ?>">
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Category</th><th>Description</th><th class="text-end">Expenses</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <?php if (can('expenses.edit')): ?>
                                <td colspan="2">
                                    <?= form_open('expenses/save-category', array('class' => 'd-flex gap-2')) ?>
                                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                        <input type="text" class="form-control form-control-sm" name="name" value="<?= e($c['name']) ?>" maxlength="60" aria-label="Name" required>
                                        <input type="text" class="form-control form-control-sm" name="description" value="<?= e($c['description']) ?>" maxlength="255" aria-label="Description">
                                        <button type="submit" class="btn btn-sm btn-outline-primary" data-no-loading>Save</button>
                                    <?= form_close() ?>
                                </td>
                            <?php else: ?>
                                <td><?= e($c['name']) ?></td><td><?= e($c['description']) ?></td>
                            <?php endif; ?>
                            <td class="text-end"><?= (int) $c['expense_count'] ?></td>
                            <td><?= status_badge($c['status']) ?></td>
                            <td class="text-end">
                                <?php if (can('expenses.edit')): ?>
                                    <?= form_open('expenses/toggle-category/'.$c['id'], array('class' => 'd-inline')) ?>
                                        <button type="submit" class="btn btn-sm <?= $c['status'] === 'Active' ? 'btn-outline-secondary' : 'btn-outline-success' ?>" data-no-loading><?= $c['status'] === 'Active' ? 'Deactivate' : 'Activate' ?></button>
                                    <?= form_close() ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="small text-muted mt-2">Categories are never deleted; deactivated categories stay on past expenses but are not offered for new ones.</p>
    </div>
</div>
