<?php defined('BASEPATH') OR exit('No direct script access allowed');
$m = $maintenance;
$owners_billed = (int) $m['paid_owners'] + (int) $m['partial_owners'] + (int) $m['pending_owners'] + (int) $m['waived_owners'];
$status_rows = array(
    array('key' => 'paid',    'label' => 'Paid',           'count' => (int) $m['paid_owners'],    'icon' => 'fa-circle-check'),
    array('key' => 'partial', 'label' => 'Partially paid', 'count' => (int) $m['partial_owners'], 'icon' => 'fa-circle-half-stroke'),
    array('key' => 'pending', 'label' => 'Pending',        'count' => (int) $m['pending_owners'], 'icon' => 'fa-clock'),
    array('key' => 'waived',  'label' => 'Waived',         'count' => (int) $m['waived_owners'],  'icon' => 'fa-circle-minus'),
);
$chart_payload = array(
    'period' => $period_label,
    'trend'  => $trend,
);

/**
 * Small stat tile.
 */
$tile = function (string $label, string $value, string $icon, string $tone, string $sub = '') {
    return '<div class="card stat-card"><span class="stat-icon bg-'.$tone.'-soft"><i class="'.e($icon).'"></i></span>'
        .'<div class="min-w-0"><div class="stat-label">'.e($label).'</div><div class="stat-value">'.e($value).'</div>'
        .($sub !== '' ? '<div class="small text-muted">'.e($sub).'</div>' : '').'</div></div>';
};
?>
<div class="page-actions">
    <div>
        <div class="fw-semibold">Welcome back, <?= e(current_user()['full_name']) ?></div>
        <div class="text-muted small">Showing maintenance for <strong><?= e($period_label) ?></strong> &middot; financial totals to date</div>
    </div>
    <form method="get" action="<?= e(site_url('dashboard')) ?>" class="d-flex gap-2 align-items-center" data-ajax="false">
        <label for="period" class="visually-hidden">Month</label>
        <input type="month" class="form-control" id="period" name="period" value="<?= e($period) ?>" max="<?= e(date('Y-m', strtotime('+1 month'))) ?>" required>
        <button type="submit" class="btn btn-primary text-nowrap" data-no-loading><i class="fa-solid fa-filter me-1"></i>Apply</button>
        <?php if ($period !== date('Y-m')): ?>
            <a href="<?= e(site_url('dashboard')) ?>" class="btn btn-light text-nowrap">This month</a>
        <?php endif; ?>
    </form>
</div>

<!-- Owners & houses -->
<div class="section-title">Owners &amp; houses</div>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl"><?= $tile('Total owners', (string) $owners['total_owners'], 'fa-solid fa-users', 'primary') ?></div>
    <div class="col-6 col-md-4 col-xl"><?= $tile('Active owners', (string) $owners['active_owners'], 'fa-solid fa-user-check', 'success') ?></div>
    <div class="col-6 col-md-4 col-xl"><?= $tile('Total houses / plots', (string) $owners['total_houses'], 'fa-solid fa-house', 'info') ?></div>
    <div class="col-6 col-md-6 col-xl"><?= $tile('Occupied houses', (string) $owners['occupied_houses'], 'fa-solid fa-house-user', 'primary') ?></div>
    <div class="col-12 col-md-6 col-xl"><?= $tile('Vacant houses', (string) $owners['vacant_houses'], 'fa-solid fa-house-circle-xmark', 'secondary') ?></div>
</div>

<!-- Maintenance for the selected month -->
<div class="section-title">Maintenance &middot; <?= e($period_label) ?></div>
<?php if ( ! $m['generated']): ?>
    <div class="alert alert-warning d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <div><i class="fa-solid fa-triangle-exclamation me-2"></i>Maintenance for <?= e($period_label) ?> has not been generated yet.</div>
        <?php if (can('maintenance.generate')): ?>
            <a href="<?= e(site_url('maintenance/generate')) ?>" class="btn btn-sm btn-warning">Generate now</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-xl-3"><?= $tile('Maintenance billed', money($m['billed']), 'fa-solid fa-file-invoice', 'primary', (int) $m['records'].' bill(s)'.(to_paise_signed($m['waived']) > 0 ? ', '.money($m['waived']).' waived' : '')) ?></div>
    <div class="col-12 col-sm-6 col-xl-3"><?= $tile('Collected', money($m['collected']), 'fa-solid fa-circle-check', 'success', 'Collection '.number_format((float) $m['collection_pct'], 2).'%') ?></div>
    <div class="col-12 col-sm-6 col-xl-3"><?= $tile('Outstanding', money($m['outstanding']), 'fa-solid fa-hourglass-half', 'danger', 'For '.$period_label) ?></div>
    <div class="col-12 col-sm-6 col-xl-3"><?= $tile('Received this month', money($m['received_in_month']), 'fa-solid fa-indian-rupee-sign', 'info', 'All payments dated '.month_name($month, TRUE).' '.$year) ?></div>
</div>

<!-- Financial position -->
<div class="section-title">Financial position &middot; to date</div>
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-6 col-lg-4"><?= $tile('Total collection', money($finance['total_collection']), 'fa-solid fa-sack-dollar', 'success') ?></div>
    <div class="col-12 col-sm-6 col-lg-4"><?= $tile('Other income', money($finance['other_income']), 'fa-solid fa-hand-holding-dollar', 'info') ?></div>
    <div class="col-12 col-sm-6 col-lg-4"><?= $tile('Total expenses', money($finance['total_expenses']), 'fa-solid fa-money-bill-wave', 'warning') ?></div>
    <div class="col-12 col-sm-6 col-lg-4"><?= $tile('Current balance', money($finance['current_balance']), 'fa-solid fa-scale-balanced', 'primary') ?></div>
    <div class="col-12 col-sm-6 col-lg-4"><?= $tile('Total outstanding', money($finance['total_outstanding']), 'fa-solid fa-triangle-exclamation', 'danger', 'All months') ?></div>
    <div class="col-12 col-sm-6 col-lg-4"><?= $tile('Advance credit', money($finance['advance_credit']), 'fa-solid fa-forward', 'secondary', 'Held for future months') ?></div>
</div>

<!-- Charts -->
<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card h-100 chart-card" data-chart="cashflow">
            <div class="card-header d-flex justify-content-between align-items-start gap-2">
                <div>
                    <div>Collections and expenses</div>
                    <div class="small text-muted fw-normal">Money received vs money spent, by month (last 12 months)</div>
                </div>
                <button type="button" class="btn btn-sm btn-light js-chart-table" aria-pressed="false"><i class="fa-solid fa-table me-1"></i>Table</button>
            </div>
            <div class="card-body">
                <div class="chart-box"><canvas id="chartCashflow" role="img" aria-label="Bar chart of monthly collections and expenses"></canvas></div>
                <div class="chart-table d-none"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <div>Payment status</div>
                <div class="small text-muted fw-normal">Owners billed for <?= e($period_label) ?></div>
            </div>
            <div class="card-body">
                <?php if ($owners_billed === 0): ?>
                    <p class="text-muted mb-0">No maintenance billed for this month.</p>
                <?php else: ?>
                    <div class="status-bar mb-3" role="img" aria-label="Payment status breakdown">
                        <?php foreach ($status_rows as $row): if ($row['count'] === 0) { continue; } ?>
                            <span class="status-seg status-<?= e($row['key']) ?>" style="flex-grow: <?= (int) $row['count'] ?>;" title="<?= e($row['label'].': '.$row['count']) ?>"></span>
                        <?php endforeach; ?>
                    </div>
                    <ul class="list-unstyled mb-0 status-legend">
                        <?php foreach ($status_rows as $row): ?>
                            <li class="d-flex align-items-center justify-content-between py-2 border-bottom">
                                <span><i class="fa-solid <?= e($row['icon']) ?> status-icon-<?= e($row['key']) ?> me-2"></i><?= e($row['label']) ?></span>
                                <span class="fw-semibold"><?= $row['count'] ?> <span class="text-muted fw-normal small">/ <?= $owners_billed ?></span></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (can('whatsapp.send') && ((int) $m['pending_owners'] + (int) $m['partial_owners']) > 0): ?>
                        <a href="<?= e(site_url('maintenance/reminders?period='.$period)) ?>" class="btn btn-sm btn-whatsapp mt-3 w-100">
                            <i class="fa-brands fa-whatsapp me-1"></i> Send reminders to <?= (int) $m['pending_owners'] + (int) $m['partial_owners'] ?> owner(s)
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100 chart-card" data-chart="billing">
            <div class="card-header d-flex justify-content-between align-items-start gap-2">
                <div>
                    <div>Maintenance billed vs collected</div>
                    <div class="small text-muted fw-normal">By billing month</div>
                </div>
                <button type="button" class="btn btn-sm btn-light js-chart-table" aria-pressed="false"><i class="fa-solid fa-table me-1"></i>Table</button>
            </div>
            <div class="card-body">
                <div class="chart-box"><canvas id="chartBilling" role="img" aria-label="Bar chart of maintenance billed and collected per billing month"></canvas></div>
                <div class="chart-table d-none"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100 chart-card" data-chart="percent">
            <div class="card-header d-flex justify-content-between align-items-start gap-2">
                <div>
                    <div>Collection percentage</div>
                    <div class="small text-muted fw-normal">Collected &divide; (billed &minus; waived), by billing month</div>
                </div>
                <button type="button" class="btn btn-sm btn-light js-chart-table" aria-pressed="false"><i class="fa-solid fa-table me-1"></i>Table</button>
            </div>
            <div class="card-body">
                <div class="chart-box"><canvas id="chartPercent" role="img" aria-label="Line chart of collection percentage per billing month"></canvas></div>
                <div class="chart-table d-none"></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-4">
        <div class="card h-100 chart-card" data-chart="outstanding">
            <div class="card-header d-flex justify-content-between align-items-start gap-2">
                <div>
                    <div>Outstanding maintenance</div>
                    <div class="small text-muted fw-normal">Unpaid balance remaining, by billing month</div>
                </div>
                <button type="button" class="btn btn-sm btn-light js-chart-table" aria-pressed="false"><i class="fa-solid fa-table me-1"></i>Table</button>
            </div>
            <div class="card-body">
                <div class="chart-box"><canvas id="chartOutstanding" role="img" aria-label="Bar chart of outstanding maintenance per billing month"></canvas></div>
                <div class="chart-table d-none"></div>
            </div>
        </div>
    </div>

    <?php if (can('reports.view')): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Highest outstanding</span>
                <a href="<?= e(site_url('reports/outstanding')) ?>" class="small">View report</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Owner</th><th>Plot</th><th class="text-end">Outstanding</th></tr></thead>
                    <tbody>
                    <?php if (empty($top_dues)): ?>
                        <tr><td colspan="3" class="text-muted text-center py-3">No outstanding dues</td></tr>
                    <?php endif; ?>
                    <?php foreach ($top_dues as $d): ?>
                        <tr>
                            <td><div class="fw-semibold"><?= e($d['owner_name']) ?></div><div class="small text-muted"><?= e($d['owner_code']) ?></div></td>
                            <td><?= e($d['plots']) ?></td>
                            <td class="text-end amount"><?= e(money($d['outstanding'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (can('payments.view')): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Recent payments</span>
                <a href="<?= e(site_url('payments')) ?>" class="small">View all</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Date</th><th>Owner</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                    <?php if (empty($recent)): ?>
                        <tr><td colspan="3" class="text-muted text-center py-3">No payments yet</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recent as $p): ?>
                        <tr>
                            <td class="text-nowrap"><?= e(fmt_date($p['payment_date'])) ?></td>
                            <td>
                                <div class="fw-semibold"><?= e($p['owner_name']) ?></div>
                                <div class="small text-muted"><?= e($p['receipt_no']) ?> &middot; <?= e($p['payment_mode']) ?></div>
                            </td>
                            <td class="text-end amount"><?= e(money($p['amount'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script type="application/json" id="dashboardData"><?= json_encode($chart_payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
