<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-actions">
    <form method="get" action="<?= e(site_url('expenses')) ?>" class="d-flex flex-wrap gap-2 align-items-center" data-ajax="false">
        <label class="small text-muted" for="from">From</label>
        <input type="date" class="form-control form-control-sm w-auto" id="from" name="from" value="<?= e($filters['from']) ?>">
        <label class="small text-muted" for="to">To</label>
        <input type="date" class="form-control form-control-sm w-auto" id="to" name="to" value="<?= e($filters['to']) ?>">
        <select class="form-select form-select-sm w-auto" name="category_id" aria-label="Category">
            <?= select_options(array('' => 'All categories') + $categories, (string) $filters['category_id']) ?>
        </select>
        <select class="form-select form-select-sm w-auto" name="status" aria-label="Status">
            <?= select_options(array('' => 'All statuses', 'Active' => 'Active', 'Cancelled' => 'Cancelled'), $filters['status']) ?>
        </select>
        <button type="submit" class="btn btn-sm btn-primary" data-no-loading><i class="fa-solid fa-filter me-1"></i>Apply</button>
    </form>
    <div class="d-flex gap-2">
        <a href="<?= e(site_url('expenses/categories')) ?>" class="btn btn-outline-primary"><i class="fa-solid fa-tags me-1"></i> Categories</a>
        <?php if (can('expenses.create')): ?>
            <a href="<?= e(site_url('expenses/create')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i> Add Expense</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card stat-card h-100"><span class="stat-icon bg-warning-soft"><i class="fa-solid fa-money-bill-wave"></i></span>
            <div><div class="stat-label">Total expenses</div><div class="stat-value"><?= e(money($total)) ?></div><div class="small text-muted"><?= e(fmt_date($filters['from'])) ?> to <?= e(fmt_date($filters['to'])) ?></div></div></div>
    </div>
    <div class="col-md-8">
        <div class="card h-100"><div class="card-body py-2">
            <div class="stat-label mb-1">By category</div>
            <?php if (empty($by_category)): ?><span class="text-muted small">No expenses in this period.</span><?php endif; ?>
            <div class="d-flex flex-wrap gap-2">
            <?php foreach ($by_category as $name => $amount): ?>
                <span class="badge rounded-pill text-bg-light border fw-normal fs-6"><?= e($name) ?> <strong class="ms-1"><?= e(money($amount)) ?></strong></span>
            <?php endforeach; ?>
            </div>
        </div></div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover w-100" id="expensesTable">
            <thead>
                <tr><th>Date</th><th>Expense ID</th><th>Category</th><th>Description</th><th>Vendor</th><th>Bill No</th><th class="text-end">Amount</th><th>Mode</th><th>Bill</th><th>Status</th><th>Created By</th><th class="no-export text-end"></th></tr>
            </thead>
            <tbody>
            <?php foreach ($expenses as $x): ?>
                <tr class="<?= $x['status'] !== 'Active' ? 'text-muted' : '' ?>">
                    <td data-order="<?= e($x['expense_date']) ?>"><?= e(fmt_date($x['expense_date'])) ?></td>
                    <td class="text-nowrap"><?= e($x['expense_code']) ?></td>
                    <td><?= e($x['category_name']) ?></td>
                    <td><?= e($x['description']) ?><?php if ($x['remarks']): ?><div class="small text-muted"><?= e($x['remarks']) ?></div><?php endif; ?>
                        <?php if ($x['status'] !== 'Active'): ?><div class="small text-danger">Cancelled: <?= e($x['cancel_reason']) ?></div><?php endif; ?></td>
                    <td><?= e($x['vendor']) ?></td>
                    <td><?= e($x['bill_number']) ?></td>
                    <td class="text-end amount fw-semibold"><?= e(money($x['amount'])) ?></td>
                    <td><?= e($x['payment_mode']) ?></td>
                    <td><?php if ($x['attachment_path']): ?><a href="<?= e(site_url('expenses/attachment/'.$x['id'])) ?>" target="_blank" rel="noopener" data-bs-toggle="tooltip" title="<?= e($x['attachment_original_name']) ?>"><i class="fa-solid fa-paperclip"></i></a><?php endif; ?></td>
                    <td><?= status_badge($x['status']) ?></td>
                    <td><?= e($x['created_by_name']) ?></td>
                    <td class="table-actions text-end text-nowrap">
                        <?php if ($x['status'] === 'Active' && can('expenses.edit')): ?>
                            <a href="<?= e(site_url('expenses/edit/'.$x['id'])) ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if ($x['status'] === 'Active' && can('expenses.cancel')): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger js-cancel-entry" data-url="<?= e(site_url('expenses/cancel/'.$x['id'])) ?>" data-label="<?= e($x['expense_code'].' ('.money($x['amount']).')') ?>" data-bs-toggle="tooltip" title="Cancel"><i class="fa-solid fa-ban"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= form_open('', array('id' => 'cancelEntryForm', 'class' => 'd-none')) ?><input type="hidden" name="reason" id="cancelEntryReason"><?= form_close() ?>
