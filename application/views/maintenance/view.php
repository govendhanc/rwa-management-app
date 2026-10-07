<?php defined('BASEPATH') OR exit('No direct script access allowed');
$b = $bill;
$cancelled = $b['record_status'] === 'Cancelled';
$balance = to_paise_signed($b['balance_amount']);
$has_active_alloc = FALSE;
foreach ($allocations as $a)
{
    $has_active_alloc = $has_active_alloc || $a['status'] === 'Active';
}
$can_cancel = ! $cancelled && ! $has_active_alloc && to_paise_signed($b['paid_amount']) === 0 && to_paise_signed($b['waived_amount']) === 0;
$detail = function (string $label, string $value) {
    return '<div class="col-sm-6 col-lg-4"><div class="small text-muted">'.e($label).'</div><div class="fw-semibold">'.($value !== '' ? $value : '<span class="text-muted fw-normal">-</span>').'</div></div>';
};
?>
<div class="page-actions">
    <div>
        <div class="h5 mb-0"><?= e(period_label((int) $b['billing_year'], (int) $b['billing_month'])) ?> &middot; Plot <?= e($b['plot_no']) ?> <?= status_badge($cancelled ? 'Cancelled' : $b['payment_status']) ?></div>
        <div class="text-muted small"><a href="<?= e(site_url('owners/view/'.$b['owner_id'])) ?>"><?= e($b['owner_name']) ?></a> &middot; <?= e($b['owner_code']) ?> &middot; Bill #<?= (int) $b['id'] ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (can('maintenance.waive') && ! $cancelled && $balance > 0): ?>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#waiveModal"><i class="fa-solid fa-hand-holding-heart me-1"></i> Waive / Discount</button>
        <?php endif; ?>
        <?php if (can('maintenance.waive') && $can_cancel): ?>
            <button type="button" class="btn btn-outline-danger js-reason-post" data-url="<?= e(site_url('maintenance/cancel/'.$b['id'])) ?>"
                    data-title="Cancel this bill?" data-message="Use this only for a bill raised in error. The bill stays on record as Cancelled and no longer counts towards dues." data-button="Cancel bill">
                <i class="fa-solid fa-ban me-1"></i> Cancel Bill
            </button>
        <?php endif; ?>
        <?php if (can('maintenance.waive') && $cancelled): ?>
            <?= form_open('maintenance/restore/'.$b['id'], array('class' => 'd-inline')) ?>
                <button type="submit" class="btn btn-outline-success" data-confirm="Restore this bill? It will count towards the owner's dues again." data-confirm-variant="success" data-confirm-button="Restore"><i class="fa-solid fa-rotate-left me-1"></i> Restore Bill</button>
            <?= form_close() ?>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="card stat-card"><span class="stat-icon bg-primary-soft"><i class="fa-solid fa-file-invoice"></i></span><div><div class="stat-label">Amount</div><div class="stat-value"><?= e(money($b['amount'])) ?></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card stat-card"><span class="stat-icon bg-success-soft"><i class="fa-solid fa-circle-check"></i></span><div><div class="stat-label">Paid</div><div class="stat-value"><?= e(money($b['paid_amount'])) ?></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card stat-card"><span class="stat-icon bg-secondary-soft"><i class="fa-solid fa-circle-minus"></i></span><div><div class="stat-label">Waived / Discount</div><div class="stat-value"><?= e(money($b['waived_amount'])) ?></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card stat-card"><span class="stat-icon bg-danger-soft"><i class="fa-solid fa-hourglass-half"></i></span><div><div class="stat-label">Balance</div><div class="stat-value"><?= e(money($b['balance_amount'])) ?></div></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-header">Bill details</div>
    <div class="card-body">
        <div class="row g-3">
            <?= $detail('Plot / House', e('Plot '.$b['plot_no'].($b['house_no'] ? ' - '.$b['house_no'] : '').($b['block'] ? ' (Block '.$b['block'].')' : ''))) ?>
            <?= $detail('Rate category', e((string) $b['plot_category'])) ?>
            <?= $detail('Rate applied', $b['rate_amount'] !== NULL ? e(money($b['rate_amount']).' (in force from '.date('M Y', strtotime($b['rate_effective_from'])).')') : '') ?>
            <?= $detail('Due date', e(fmt_date($b['due_date'])).($balance > 0 && ! $cancelled && $b['due_date'] < date('Y-m-d') ? ' <span class="badge text-bg-danger">Overdue</span>' : '')) ?>
            <?= $detail('Charge type', e($b['charge_type'])) ?>
            <?= $detail('Generated', e(trim(fmt_datetime($b['generated_at']).' '.($b['created_by_name'] ? 'by '.$b['created_by_name'] : '')))) ?>
            <?php if ($b['description']): ?><?= $detail('Notes', e($b['description'])) ?><?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header">Payments against this bill</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Date</th><th>Receipt</th><th>Source</th><th>Mode</th><th class="text-end">Amount</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (empty($allocations)): ?><tr><td colspan="6" class="text-muted text-center py-3">No payments yet</td></tr><?php endif; ?>
                    <?php foreach ($allocations as $a): ?>
                        <tr class="<?= $a['status'] !== 'Active' ? 'text-muted' : '' ?>">
                            <td><?= e(fmt_date($a['allocation_source'] === 'Payment' ? $a['payment_date'] : substr($a['allocated_at'], 0, 10))) ?></td>
                            <td><?= e($a['receipt_no']) ?></td>
                            <td><?= e($a['allocation_source']) ?></td>
                            <td><?= e($a['payment_mode']) ?></td>
                            <td class="text-end amount"><?= e(money($a['amount'])) ?></td>
                            <td><?= status_badge($a['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header">Waivers &amp; discounts</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Date</th><th>Type</th><th class="text-end">Amount</th><th>Reason</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php if (empty($adjustments)): ?><tr><td colspan="6" class="text-muted text-center py-3">None</td></tr><?php endif; ?>
                    <?php foreach ($adjustments as $adj): ?>
                        <tr class="<?= $adj['status'] !== 'Active' ? 'text-muted' : '' ?>">
                            <td><?= e(fmt_date($adj['adjustment_date'])) ?></td>
                            <td><?= e($adj['adjustment_type']) ?></td>
                            <td class="text-end amount"><?= e(money($adj['amount'])) ?></td>
                            <td><?= e($adj['reason']) ?><div class="small text-muted"><?= e($adj['created_by_name']) ?></div></td>
                            <td><?= status_badge($adj['status']) ?></td>
                            <td class="text-end">
                                <?php if (can('maintenance.waive') && $adj['status'] === 'Active' && ! $cancelled): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger js-reason-post" data-url="<?= e(site_url('maintenance/reverse-adjustment/'.$adj['id'])) ?>"
                                            data-title="Reverse this <?= e(strtolower($adj['adjustment_type'])) ?>?" data-message="The <?= e(money($adj['amount'])) ?> will be added back to the bill balance." data-button="Reverse"
                                            data-bs-toggle="tooltip" title="Reverse"><i class="fa-solid fa-rotate-left"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (can('maintenance.waive') && ! $cancelled && $balance > 0): ?>
<div class="modal fade" id="waiveModal" tabindex="-1" aria-labelledby="waiveTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <?= form_open('maintenance/waive/'.$b['id']) ?>
            <div class="modal-header">
                <h5 class="modal-title" id="waiveTitle">Waive / discount</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="adjustment_type">Type</label>
                    <select class="form-select" id="adjustment_type" name="adjustment_type">
                        <option value="Waiver">Waiver (e.g. committee resolution)</option>
                        <option value="Discount">Discount</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="adj_amount">Amount <span class="required">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text"><?= e(currency_symbol()) ?></span>
                        <input type="text" class="form-control" id="adj_amount" name="amount" inputmode="decimal" required value="<?= e(from_paise($balance)) ?>">
                    </div>
                    <div class="form-text">Maximum: the current balance, <?= e(money($b['balance_amount'])) ?>.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="adjustment_date">Date</label>
                    <input type="date" class="form-control" id="adjustment_date" name="adjustment_date" value="<?= e(date('Y-m-d')) ?>" required>
                </div>
                <div>
                    <label class="form-label" for="adj_reason">Reason / resolution reference <span class="required">*</span></label>
                    <input type="text" class="form-control" id="adj_reason" name="reason" maxlength="255" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?= form_open('', array('id' => 'reasonPostForm', 'class' => 'd-none')) ?>
    <input type="hidden" name="reason" id="reasonPostValue">
<?= form_close() ?>
