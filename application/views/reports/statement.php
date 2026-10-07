<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<form method="get" action="<?= e(site_url('reports/statement')) ?>" class="card mb-3 no-print" data-ajax="false">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <div style="min-width: 260px;"><label class="form-label small mb-1" for="owner_id">Owner</label>
            <select class="form-select form-select-sm" id="owner_id" name="owner_id" required><?= select_options($owners, $owner !== NULL ? (string) $owner['id'] : '', 'Select owner') ?></select></div>
        <div><label class="form-label small mb-1" for="from">From</label><input type="date" class="form-control form-control-sm" id="from" name="from" value="<?= e($from) ?>"></div>
        <div><label class="form-label small mb-1" for="to">To</label><input type="date" class="form-control form-control-sm" id="to" name="to" value="<?= e($to) ?>"></div>
        <button type="submit" class="btn btn-sm btn-primary" data-no-loading><i class="fa-solid fa-file-lines me-1"></i>Show statement</button>
    </div>
</form>

<?php if ($owner === NULL): ?>
    <div class="card"><div class="card-body text-muted text-center py-5">Select an owner to see their statement: opening balance, maintenance charges, payments, adjustments, advance and closing balance.</div></div>
<?php else:
    $q = http_build_query(array('owner_id' => $owner['id'], 'from' => $from, 'to' => $to));
?>
    <div class="d-flex flex-wrap gap-2 mb-3 no-print">
        <a href="<?= e(site_url('reports/statement-pdf?'.$q)) ?>" class="btn btn-primary"><i class="fa-solid fa-file-pdf me-1"></i> Download PDF</a>
        <a href="<?= e(site_url('reports/statement-pdf?'.$q.'&inline=1')) ?>" class="btn btn-outline-primary" target="_blank" rel="noopener"><i class="fa-solid fa-print me-1"></i> Print (PDF)</a>
        <button type="button" class="btn btn-outline-success" id="statementExcel"><i class="fa-solid fa-file-excel me-1"></i> Excel</button>
    </div>
    <div class="card"><div class="card-body receipt-screen"><?= $document ?></div></div>
<?php endif; ?>
