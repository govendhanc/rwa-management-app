<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-actions">
    <form method="get" action="<?= e(site_url('incomes')) ?>" class="d-flex flex-wrap gap-2 align-items-center" data-ajax="false">
        <label class="small text-muted" for="from">From</label>
        <input type="date" class="form-control form-control-sm w-auto" id="from" name="from" value="<?= e($filters['from']) ?>">
        <label class="small text-muted" for="to">To</label>
        <input type="date" class="form-control form-control-sm w-auto" id="to" name="to" value="<?= e($filters['to']) ?>">
        <select class="form-select form-select-sm w-auto" name="status" aria-label="Status"><?= select_options(array('' => 'All statuses', 'Active' => 'Active', 'Cancelled' => 'Cancelled'), $filters['status']) ?></select>
        <button type="submit" class="btn btn-sm btn-primary" data-no-loading><i class="fa-solid fa-filter me-1"></i>Apply</button>
    </form>
    <?php if (can('incomes.create')): ?>
        <a href="<?= e(site_url('incomes/create')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i> Add Income</a>
    <?php endif; ?>
</div>
<div class="card">
    <div class="card-header d-flex justify-content-between"><span>Other income</span><span>Total: <strong class="amount"><?= e(money($total)) ?></strong></span></div>
    <div class="card-body">
        <table class="table table-hover w-100" id="incomesTable">
            <thead><tr><th>Date</th><th>Income ID</th><th>Source</th><th>Description</th><th class="text-end">Amount</th><th>Mode</th><th>Reference</th><th>Status</th><th>Created By</th><th class="no-export text-end"></th></tr></thead>
            <tbody>
            <?php foreach ($incomes as $i): ?>
                <tr class="<?= $i['status'] !== 'Active' ? 'text-muted' : '' ?>">
                    <td data-order="<?= e($i['income_date']) ?>"><?= e(fmt_date($i['income_date'])) ?></td>
                    <td class="text-nowrap"><?= e($i['income_code']) ?></td>
                    <td><?= e($i['source']) ?></td>
                    <td><?= e($i['description']) ?><?php if ($i['remarks']): ?><div class="small text-muted"><?= e($i['remarks']) ?></div><?php endif; ?></td>
                    <td class="text-end amount fw-semibold"><?= e(money($i['amount'])) ?></td>
                    <td><?= e($i['payment_mode']) ?></td>
                    <td><?= e($i['reference']) ?></td>
                    <td><?= status_badge($i['status']) ?></td>
                    <td><?= e($i['created_by_name']) ?></td>
                    <td class="table-actions text-end text-nowrap">
                        <?php if ($i['status'] === 'Active' && can('incomes.edit')): ?><a href="<?= e(site_url('incomes/edit/'.$i['id'])) ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit"><i class="fa-solid fa-pen"></i></a><?php endif; ?>
                        <?php if ($i['status'] === 'Active' && can('incomes.cancel')): ?><button type="button" class="btn btn-sm btn-outline-danger js-cancel-entry" data-url="<?= e(site_url('incomes/cancel/'.$i['id'])) ?>" data-label="<?= e($i['income_code'].' ('.money($i['amount']).')') ?>" data-bs-toggle="tooltip" title="Cancel"><i class="fa-solid fa-ban"></i></button><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= form_open('', array('id' => 'cancelEntryForm', 'class' => 'd-none')) ?><input type="hidden" name="reason" id="cancelEntryReason"><?= form_close() ?>
