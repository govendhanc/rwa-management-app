<?php defined('BASEPATH') OR exit('No direct script access allowed');
$t = $totals;
?>
<form method="get" action="<?= e(site_url('reports/outstanding')) ?>" class="card mb-3 no-print" data-ajax="false">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <div><label class="form-label small mb-1" for="period">Bills up to</label><input type="month" class="form-control form-control-sm" id="period" name="period" value="<?= e($period) ?>"></div>
        <div><label class="form-label small mb-1" for="owner_id">Owner</label><select class="form-select form-select-sm" id="owner_id" name="owner_id"><?= select_options($owners, (string) $filters['owner_id'], 'All owners') ?></select></div>
        <div><label class="form-label small mb-1" for="plot">Plot / House No</label><input type="text" class="form-control form-control-sm" id="plot" name="plot" value="<?= e($filters['plot']) ?>" maxlength="20" style="width: 110px;"></div>
        <div><label class="form-label small mb-1" for="owner_status">Owner status</label><select class="form-select form-select-sm" id="owner_status" name="owner_status"><?= select_options(array('' => 'All', 'Active' => 'Active', 'Inactive' => 'Inactive'), $filters['owner_status']) ?></select></div>
        <div><label class="form-label small mb-1" for="only_due">Show</label><select class="form-select form-select-sm" id="only_due" name="only_due"><?= select_options(array('1' => 'Only owners with dues', '0' => 'All owners'), $filters['only_due'] ? '1' : '0') ?></select></div>
        <button type="submit" class="btn btn-sm btn-primary" data-no-loading><i class="fa-solid fa-filter me-1"></i>Apply</button>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="card stat-card"><span class="stat-icon bg-primary-soft"><i class="fa-solid fa-file-invoice"></i></span><div><div class="stat-label">Total billed</div><div class="stat-value"><?= e(money($t['total_due'])) ?></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card stat-card"><span class="stat-icon bg-success-soft"><i class="fa-solid fa-circle-check"></i></span><div><div class="stat-label">Total paid</div><div class="stat-value"><?= e(money($t['total_paid'])) ?></div><?php if (to_paise_signed($t['total_waived']) > 0): ?><div class="small text-muted"><?= e(money($t['total_waived'])) ?> waived</div><?php endif; ?></div></div></div>
    <div class="col-6 col-xl-3"><div class="card stat-card"><span class="stat-icon bg-danger-soft"><i class="fa-solid fa-hourglass-half"></i></span><div><div class="stat-label">Outstanding</div><div class="stat-value"><?= e(money($t['outstanding'])) ?></div><div class="small text-muted"><?= count($rows) ?> owner(s)</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card stat-card"><span class="stat-icon bg-secondary-soft"><i class="fa-solid fa-forward"></i></span><div><div class="stat-label">Advance credit held</div><div class="stat-value"><?= e(money($t['advance_credit'])) ?></div></div></div></div>
</div>

<div class="card">
    <div class="card-header">Outstanding maintenance &middot; bills up to <?= e(date('F Y', strtotime($filters['as_of']))) ?></div>
    <div class="card-body">
        <table class="table table-hover w-100 report-table" id="outstandingTable" data-title="Outstanding Report - bills up to <?= e(date('F Y', strtotime($filters['as_of']))) ?>">
            <thead>
                <tr><th>Plot No</th><th>House No</th><th>Owner</th><th>Mobile</th><th class="text-end">Monthly Maintenance</th><th class="text-end">Total Due</th><th class="text-end">Total Paid</th><th class="text-end">Waived</th><th class="text-end">Outstanding</th><th>Oldest Unpaid</th><th>Last Payment</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['plots'] ?? '-') ?></td>
                    <td><?= e($r['house_nos'] ?? '-') ?></td>
                    <td><a href="<?= e(site_url('owners/view/'.$r['owner_id'])) ?>" class="text-decoration-none"><?= e($r['owner_name']) ?></a><div class="small text-muted"><?= e($r['owner_code']) ?><?= $r['status'] !== 'Active' ? ' &middot; inactive' : '' ?></div></td>
                    <td class="text-nowrap"><?= e($r['mobile']) ?></td>
                    <td class="text-end amount"><?= e(money($r['monthly_maintenance'])) ?></td>
                    <td class="text-end amount"><?= e(money($r['total_due'])) ?></td>
                    <td class="text-end amount"><?= e(money($r['total_paid'])) ?></td>
                    <td class="text-end amount"><?= e(money($r['total_waived'])) ?></td>
                    <td class="text-end amount fw-bold<?= to_paise_signed($r['outstanding']) > 0 ? ' text-danger' : '' ?>" data-order="<?= e($r['outstanding']) ?>"><?= e(money($r['outstanding'])) ?>
                        <?php if (to_paise_signed($r['advance_credit']) > 0): ?><div class="small text-success fw-normal">credit <?= e(money($r['advance_credit'])) ?></div><?php endif; ?></td>
                    <td data-order="<?= e((string) $r['oldest_due']) ?>"><?= $r['oldest_due'] ? e(date('M Y', strtotime($r['oldest_due']))) : '' ?></td>
                    <td data-order="<?= e((string) $r['last_payment_date']) ?>"><?= e(fmt_date($r['last_payment_date'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><th colspan="5" class="text-end">Total</th><th class="text-end amount"><?= e(money($t['total_due'])) ?></th><th class="text-end amount"><?= e(money($t['total_paid'])) ?></th><th class="text-end amount"><?= e(money($t['total_waived'])) ?></th><th class="text-end amount"><?= e(money($t['outstanding'])) ?></th><th colspan="2"></th></tr>
            </tfoot>
        </table>
    </div>
</div>
