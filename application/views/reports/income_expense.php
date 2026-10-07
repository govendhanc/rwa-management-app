<?php defined('BASEPATH') OR exit('No direct script access allowed');
$r = $report;
$net = to_paise_signed($r['net']);
?>
<form method="get" action="<?= e(site_url('reports/income-expense')) ?>" class="card mb-3 no-print" data-ajax="false" id="ieForm">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <div><label class="form-label small mb-1" for="type">Period</label>
            <select class="form-select form-select-sm" id="type" name="type"><?= select_options(array('month' => 'Monthly', 'year' => 'Yearly (Jan-Dec)', 'fy' => 'Financial year (Apr-Mar)', 'custom' => 'Custom range'), $type) ?></select></div>
        <div class="js-ie" data-type="month"><label class="form-label small mb-1" for="month">Month</label><input type="month" class="form-control form-control-sm" id="month" name="month" value="<?= e($month) ?>"></div>
        <div class="js-ie" data-type="year"><label class="form-label small mb-1" for="year">Year</label><input type="number" class="form-control form-control-sm" id="year" name="year" value="<?= (int) $year ?>" min="2000" max="2100" style="width: 100px;"></div>
        <div class="js-ie" data-type="fy"><label class="form-label small mb-1" for="fy">FY starting April</label><input type="number" class="form-control form-control-sm" id="fy" name="fy" value="<?= (int) $fy ?>" min="2000" max="2100" style="width: 100px;"></div>
        <div class="js-ie" data-type="custom"><label class="form-label small mb-1" for="from">From</label><input type="date" class="form-control form-control-sm" id="from" name="from" value="<?= e($from) ?>"></div>
        <div class="js-ie" data-type="custom"><label class="form-label small mb-1" for="to">To</label><input type="date" class="form-control form-control-sm" id="to" name="to" value="<?= e($to) ?>"></div>
        <button type="submit" class="btn btn-sm btn-primary" data-no-loading>Show</button>
        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" id="printReport"><i class="fa-solid fa-print me-1"></i>Print</button>
    </div>
</form>

<div class="card" style="max-width: 900px;">
    <div class="card-header d-flex justify-content-between"><span>Income &amp; Expense Statement &middot; <?= e($label) ?></span><span class="small text-muted"><?= e(fmt_date($from)) ?> to <?= e(fmt_date($to)) ?></span></div>
    <div class="table-responsive">
        <table class="table mb-0 report-statement" id="ieTable">
            <tbody>
                <tr class="table-light"><th colspan="2">Income</th></tr>
                <tr><td class="ps-4">Total Maintenance Collection</td><td class="text-end amount"><?= e(money($r['collection'])) ?></td></tr>
                <?php foreach ($r['by_mode'] as $m): ?>
                    <tr class="small text-muted"><td class="ps-5">by <?= e($m['label']) ?> (<?= (int) $m['cnt'] ?>)</td><td class="text-end amount"><?= e(money($m['total'])) ?></td></tr>
                <?php endforeach; ?>
                <tr><td class="ps-4">+ Other Income</td><td class="text-end amount"><?= e(money($r['other_total'])) ?></td></tr>
                <?php foreach ($r['other'] as $o): ?>
                    <tr class="small text-muted"><td class="ps-5"><?= e($o['label']) ?> (<?= (int) $o['cnt'] ?>)</td><td class="text-end amount"><?= e(money($o['total'])) ?></td></tr>
                <?php endforeach; ?>
                <tr class="fw-bold border-top border-dark"><td>Total Income</td><td class="text-end amount"><?= e(money($r['income_total'])) ?></td></tr>

                <tr class="table-light"><th colspan="2">Less: Expenses</th></tr>
                <?php if (empty($r['expenses'])): ?><tr><td class="ps-4 text-muted">No expenses</td><td class="text-end amount"><?= e(money('0')) ?></td></tr><?php endif; ?>
                <?php foreach ($r['expenses'] as $x): ?>
                    <tr><td class="ps-4"><?= e($x['label']) ?> <span class="small text-muted">(<?= (int) $x['cnt'] ?>)</span></td><td class="text-end amount"><?= e(money($x['total'])) ?></td></tr>
                <?php endforeach; ?>
                <tr class="fw-bold border-top border-dark"><td>Total Expenses</td><td class="text-end amount"><?= e(money($r['expense_total'])) ?></td></tr>

                <tr class="fw-bold <?= $net >= 0 ? 'table-success' : 'table-danger' ?>"><td><?= $net >= 0 ? 'Surplus' : 'Deficit' ?> for the period (Income &minus; Expenses)</td><td class="text-end amount"><?= e(money($r['net'])) ?></td></tr>
                <tr class="table-light"><th colspan="2">Fund position</th></tr>
                <tr><td class="ps-4">Opening Balance (before <?= e(fmt_date($from)) ?>)</td><td class="text-end amount"><?= e(money($r['opening'])) ?></td></tr>
                <tr><td class="ps-4"><?= $net >= 0 ? 'Add: Surplus' : 'Less: Deficit' ?></td><td class="text-end amount"><?= e(money($r['net'])) ?></td></tr>
                <tr class="fw-bold fs-5 border-top border-dark"><td>Closing Balance</td><td class="text-end amount"><?= e(money($r['closing'])) ?></td></tr>
            </tbody>
        </table>
    </div>
    <div class="card-body small text-muted">Cash basis: collections by payment date, other income and expenses by their dates. Cancelled entries are excluded.</div>
</div>
