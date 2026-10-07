<?php defined('BASEPATH') OR exit('No direct script access allowed');
$today = date('Y-m-d');
$s = $summary;
?>
<div class="page-actions">
    <form method="get" action="<?= e(site_url('maintenance')) ?>" class="d-flex flex-wrap gap-2 align-items-center" data-ajax="false">
        <label class="visually-hidden" for="period">Billing month</label>
        <select class="form-select form-select-sm w-auto" id="period" name="period">
            <option value="open" <?= $open_mode ? 'selected' : '' ?>>All unpaid bills (any month)</option>
            <?php foreach ($batches as $b): $p = sprintf('%04d-%02d', $b['billing_year'], $b['billing_month']); ?>
                <option value="<?= e($p) ?>" <?= $p === $period ? 'selected' : '' ?>><?= e(period_label((int) $b['billing_year'], (int) $b['billing_month'])) ?></option>
            <?php endforeach; ?>
            <?php if ( ! $open_mode && ! in_array($period, array_map(function ($b) { return sprintf('%04d-%02d', $b['billing_year'], $b['billing_month']); }, $batches), TRUE)): ?>
                <option value="<?= e($period) ?>" selected><?= e(period_label((int) $year, (int) $month)) ?> (not generated)</option>
            <?php endif; ?>
        </select>
        <label class="visually-hidden" for="status">Status</label>
        <select class="form-select form-select-sm w-auto" id="status" name="status">
            <?= select_options(array('' => 'All statuses', 'Unpaid' => 'Unpaid (pending + partial)', 'Pending' => 'Pending', 'Partially Paid' => 'Partially Paid', 'Paid' => 'Paid', 'Waived' => 'Waived', 'Cancelled' => 'Cancelled'), $filters['status']) ?>
        </select>
        <label class="visually-hidden" for="block">Block</label>
        <select class="form-select form-select-sm w-auto" id="block" name="block">
            <option value="">All blocks</option>
            <?= select_options($blocks, $filters['block']) ?>
        </select>
        <button type="submit" class="btn btn-sm btn-primary" data-no-loading><i class="fa-solid fa-filter me-1"></i>Apply</button>
    </form>
    <div class="d-flex gap-2">
        <?php if (can('whatsapp.send') && ! $open_mode): ?>
            <a href="<?= e(site_url('maintenance/reminders?period='.$period)) ?>" class="btn btn-whatsapp"><i class="fa-brands fa-whatsapp me-1"></i> Reminders</a>
        <?php endif; ?>
        <?php if (can('maintenance.generate')): ?>
            <a href="<?= e(site_url('maintenance/generate')) ?>" class="btn btn-primary"><i class="fa-solid fa-file-circle-plus me-1"></i> Generate Monthly</a>
        <?php endif; ?>
    </div>
</div>

<?php if ( ! $open_mode && $s['batch'] === NULL): ?>
    <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <div><i class="fa-solid fa-triangle-exclamation me-2"></i>Maintenance for <?= e(period_label((int) $year, (int) $month)) ?> has not been generated yet.</div>
        <?php if (can('maintenance.generate')): ?><a class="btn btn-sm btn-warning" href="<?= e(site_url('maintenance/generate?period='.$period)) ?>">Generate now</a><?php endif; ?>
    </div>
<?php endif; ?>

<?php if ( ! $open_mode): ?>
<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><div class="card stat-card"><span class="stat-icon bg-primary-soft"><i class="fa-solid fa-file-invoice"></i></span><div><div class="stat-label">Billed &middot; <?= (int) $s['bills'] ?> plots</div><div class="stat-value"><?= e(money($s['billed'])) ?></div><div class="small text-muted"><?= (int) $s['owners'] ?> owners<?= to_paise_signed($s['waived']) > 0 ? ' &middot; '.e(money($s['waived'])).' waived' : '' ?></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card stat-card"><span class="stat-icon bg-success-soft"><i class="fa-solid fa-circle-check"></i></span><div><div class="stat-label">Collected</div><div class="stat-value"><?= e(money($s['collected'])) ?></div><div class="small text-muted"><?= e(number_format((float) $s['collection_pct'], 2)) ?>% collected</div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card stat-card"><span class="stat-icon bg-danger-soft"><i class="fa-solid fa-hourglass-half"></i></span><div><div class="stat-label">Outstanding</div><div class="stat-value"><?= e(money($s['outstanding'])) ?></div><div class="small text-muted"><?= $s['batch'] ? 'Due '.e(fmt_date($s['batch']['due_date'])) : '' ?></div></div></div></div>
    <div class="col-6 col-xl-3"><div class="card stat-card"><span class="stat-icon bg-info-soft"><i class="fa-solid fa-list-check"></i></span><div class="small">
        <div><i class="fa-solid fa-circle-check status-icon-paid me-1"></i>Paid <strong><?= (int) $s['paid_count'] ?></strong></div>
        <div><i class="fa-solid fa-circle-half-stroke status-icon-partial me-1"></i>Partial <strong><?= (int) $s['partial_count'] ?></strong></div>
        <div><i class="fa-solid fa-clock status-icon-pending me-1"></i>Pending <strong><?= (int) $s['pending_count'] ?></strong></div>
        <div><i class="fa-solid fa-circle-minus status-icon-waived me-1"></i>Waived <strong><?= (int) $s['waived_count'] ?></strong></div>
    </div></div></div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <table class="table table-hover w-100" id="maintenanceTable" data-title="Maintenance - <?= e($open_mode ? 'All unpaid bills' : period_label((int) $year, (int) $month)) ?>">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Plot</th>
                    <th>House</th>
                    <th>Owner</th>
                    <th>Category</th>
                    <th>Due Date</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Paid</th>
                    <th class="text-end">Waived</th>
                    <th class="text-end">Balance</th>
                    <th>Status</th>
                    <th class="no-export text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bills as $m):
                $cancelled = $m['record_status'] === 'Cancelled';
                $overdue = ! $cancelled && to_paise_signed($m['balance_amount']) > 0 && $m['due_date'] < $today;
            ?>
                <tr class="<?= $cancelled ? 'text-muted' : '' ?>">
                    <td data-order="<?= e($m['period_start']) ?>"><?= e(period_label((int) $m['billing_year'], (int) $m['billing_month'], TRUE)) ?></td>
                    <td class="fw-semibold" data-order="<?= e(str_pad($m['plot_no'], 10, '0', STR_PAD_LEFT)) ?>"><?= e($m['plot_no']) ?></td>
                    <td><?= e($m['house_no']) ?></td>
                    <td><a href="<?= e(site_url('owners/view/'.$m['owner_id'])) ?>" class="text-decoration-none"><?= e($m['owner_name']) ?></a><div class="small text-muted"><?= e($m['owner_code']) ?></div></td>
                    <td><?= e($m['plot_category'] ?? $m['charge_type']) ?></td>
                    <td data-order="<?= e($m['due_date']) ?>"><?= e(fmt_date($m['due_date'])) ?><?= $overdue ? ' <span class="badge text-bg-danger">Overdue</span>' : '' ?></td>
                    <td class="text-end amount"><?= e(money($m['amount'])) ?></td>
                    <td class="text-end amount"><?= e(money($m['paid_amount'])) ?></td>
                    <td class="text-end amount"><?= e(money($m['waived_amount'])) ?></td>
                    <td class="text-end amount fw-semibold"><?= e(money($m['balance_amount'])) ?></td>
                    <td><?= status_badge($cancelled ? 'Cancelled' : $m['payment_status']) ?></td>
                    <td class="table-actions text-end"><a href="<?= e(site_url('maintenance/view/'.$m['id'])) ?>" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="View bill"><i class="fa-solid fa-eye"></i></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
