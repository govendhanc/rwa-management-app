<?php defined('BASEPATH') OR exit('No direct script access allowed');
$t = $totals;
?>
<form method="get" action="<?= e(site_url('reports/collection')) ?>" class="d-flex gap-2 align-items-center mb-3 no-print" data-ajax="false">
    <label class="small text-muted" for="year">Year</label>
    <select class="form-select form-select-sm w-auto" id="year" name="year"><?php foreach ($years as $y): ?><option value="<?= $y ?>" <?= $y === $year ? 'selected' : '' ?>><?= $y ?></option><?php endforeach; ?></select>
    <button type="submit" class="btn btn-sm btn-primary" data-no-loading>Show</button>
</form>

<?php if (empty($rows)): ?>
    <div class="alert alert-info">No maintenance has been generated for <?= (int) $year ?> yet.</div>
<?php else: ?>
<div class="row g-3 mb-3">
    <?php foreach ($rows as $r):
        $pct = (float) $r['collection_pct'];
    ?>
    <div class="col-sm-6 col-xl-3">
        <div class="card h-100"><div class="card-body">
            <div class="fw-semibold mb-1"><?= e(period_label((int) $r['billing_year'], (int) $r['billing_month'])) ?></div>
            <div class="small text-muted">Owners: <?= (int) $r['total_owners'] ?> &middot; Plots: <?= (int) $r['total_records'] ?></div>
            <div class="small">Maintenance Generated: <strong><?= e(money($r['total_maintenance'])) ?></strong></div>
            <div class="small">Collected: <strong><?= e(money($r['total_collected'])) ?></strong></div>
            <div class="small">Outstanding: <strong><?= e(money($r['total_outstanding'])) ?></strong></div>
            <div class="progress mt-2" style="height: 8px;" role="progressbar" aria-label="Collection" aria-valuenow="<?= e((string) $pct) ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: <?= e((string) min(100, $pct)) ?>%"></div></div>
            <div class="small mt-1">Collection: <strong><?= e(number_format($pct, 2)) ?>%</strong></div>
        </div></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">Monthly collection &middot; <?= (int) $year ?></div>
    <div class="card-body">
        <table class="table table-hover w-100 report-table" id="collectionTable" data-title="Monthly Collection Report <?= (int) $year ?>">
            <thead><tr><th>Month</th><th class="text-end">Total Owners</th><th class="text-end">Plots Billed</th><th class="text-end">Total Maintenance</th><th class="text-end">Waived</th><th class="text-end">Total Collected</th><th class="text-end">Total Outstanding</th><th class="text-end">Collection %</th><th class="text-end">Paid / Partial / Pending</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td data-order="<?= (int) $r['billing_month'] ?>"><a href="<?= e(site_url('maintenance?period='.sprintf('%04d-%02d', $r['billing_year'], $r['billing_month']))) ?>"><?= e(period_label((int) $r['billing_year'], (int) $r['billing_month'])) ?></a></td>
                    <td class="text-end"><?= (int) $r['total_owners'] ?></td>
                    <td class="text-end"><?= (int) $r['total_records'] ?></td>
                    <td class="text-end amount"><?= e(money($r['total_maintenance'])) ?></td>
                    <td class="text-end amount"><?= e(money($r['total_waived'])) ?></td>
                    <td class="text-end amount"><?= e(money($r['total_collected'])) ?></td>
                    <td class="text-end amount fw-semibold"><?= e(money($r['total_outstanding'])) ?></td>
                    <td class="text-end"><?= e(number_format((float) $r['collection_pct'], 2)) ?>%</td>
                    <td class="text-end"><?= (int) $r['paid_count'] ?> / <?= (int) $r['partial_count'] ?> / <?= (int) $r['pending_count'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th>Total</th><th></th><th></th><th class="text-end amount"><?= e(money($t['total_maintenance'])) ?></th><th class="text-end amount"><?= e(money($t['total_waived'])) ?></th><th class="text-end amount"><?= e(money($t['total_collected'])) ?></th><th class="text-end amount"><?= e(money($t['total_outstanding'])) ?></th><th class="text-end"><?= e(number_format((float) $total_pct, 2)) ?>%</th><th></th></tr></tfoot>
        </table>
        <p class="small text-muted mb-0 mt-2">Collection % = collected &divide; (maintenance &minus; waived), for bills of that month. Payments for older months are counted in the month they were billed.</p>
    </div>
</div>
