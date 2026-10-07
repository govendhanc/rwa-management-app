<?php defined('BASEPATH') OR exit('No direct script access allowed');
$badge = array('Imported' => 'success', 'Ready' => 'info', 'Failed' => 'danger', 'Duplicate' => 'warning');
?>
<?php if (is_array($result)): $r = $result; ?>
<div class="card mb-3 border-<?= $r['failed'] + $r['duplicate'] > 0 ? 'warning' : 'success' ?>">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><?= $r['dry_run'] ? 'Validation result (nothing saved)' : 'Import summary' ?></span>
        <a href="<?= e(site_url('imports/report/'.$r['batch_id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-file-csv me-1"></i> Download row-by-row report</a>
    </div>
    <div class="card-body">
        <div class="row g-3 text-center mb-3">
            <div class="col-6 col-md-3"><div class="border rounded-3 p-2"><div class="stat-label">Total Records</div><div class="stat-value"><?= (int) $r['total'] ?></div></div></div>
            <div class="col-6 col-md-3"><div class="border rounded-3 p-2"><div class="stat-label"><?= $r['dry_run'] ? 'Ready to import' : 'Imported' ?></div><div class="stat-value text-success"><?= (int) $r['imported'] ?></div></div></div>
            <div class="col-6 col-md-3"><div class="border rounded-3 p-2"><div class="stat-label">Failed</div><div class="stat-value text-danger"><?= (int) $r['failed'] ?></div></div></div>
            <div class="col-6 col-md-3"><div class="border rounded-3 p-2"><div class="stat-label">Duplicate</div><div class="stat-value text-warning"><?= (int) $r['duplicate'] ?></div></div></div>
        </div>
        <?php if ( ! $r['dry_run']): ?><p class="small text-muted"><?= (int) $r['owners_created'] ?> owner(s) created<?= $r['owners_extended'] ? ', '.(int) $r['owners_extended'].' existing owner(s) received additional plots' : '' ?>.</p><?php endif; ?>
        <div class="table-responsive" style="max-height: 360px;">
            <table class="table table-sm mb-0">
                <thead class="sticky-top"><tr><th>Row</th><th>Plot</th><th>Owner</th><th>Mobile</th><th>Result</th><th>Reason</th></tr></thead>
                <tbody>
                <?php foreach ($r['rows'] as $row): ?>
                    <tr><td><?= (int) $row['line'] ?></td><td><?= e($row['plot']) ?></td><td><?= e($row['owner']) ?></td><td><?= e($row['mobile']) ?></td>
                        <td><span class="badge text-bg-<?= e($badge[$row['status']] ?? 'secondary') ?>"><?= e($row['status']) ?></span></td><td class="small"><?= e($row['reason']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-5">
        <div class="card">
            <div class="card-header">Upload owners file</div>
            <div class="card-body">
                <?= form_open_multipart('imports/upload') ?>
                    <div class="mb-3">
                        <label class="form-label" for="file">CSV file <span class="required">*</span></label>
                        <input type="file" class="form-control" id="file" name="file" accept=".csv,text/csv" required>
                        <div class="form-text">From Excel: <em>File &gt; Save As &gt; CSV UTF-8 (Comma delimited)</em>. Max 2 MB, <?= Owner_import::MAX_ROWS ?> rows.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="maintenance_start">Bill imported owners from <span class="required">*</span></label>
                        <input type="month" class="form-control" id="maintenance_start" name="maintenance_start" value="<?= e(date('Y-m')) ?>" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="dry_run" name="dry_run" checked>
                        <label class="form-check-label" for="dry_run"><strong>Validate only</strong> (check the file first; nothing is saved)</label>
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-import me-1"></i> Upload</button>
                    <a href="<?= e(site_url('imports/template')) ?>" class="btn btn-link"><i class="fa-solid fa-download me-1"></i>Download template</a>
                <?= form_close() ?>
            </div>
        </div>
    </div>
    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-header">Rules</div>
            <div class="card-body small">
                <ul class="mb-0">
                    <li>Required columns: <strong>Plot No, Owner Name, Mobile</strong>. Optional: House No, Block, Street, Co-owner, WhatsApp, Email, Owner Type, Built Status.</li>
                    <li><strong>One row per plot.</strong> Rows with the same owner name and mobile become one owner with several plots.</li>
                    <li>A plot already in the portal, a plot repeated in the file, or a house number already used in the same block is reported as <strong>Duplicate</strong> and skipped.</li>
                    <li>If an owner with the same name and mobile already exists, the plots are added to that owner.</li>
                    <li>Built Status: <em>Built</em> (default) bills at the Constructed rate; <em>Not Built / Vacant</em> or <em>Under Construction</em> at the Vacant Plot rate. A "Maintenance Amount" column is ignored &mdash; amounts come from Maintenance Rates.</li>
                </ul>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Recent imports</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Date</th><th>File</th><th>Type</th><th class="text-end">Total</th><th class="text-end">Imported</th><th class="text-end">Failed</th><th class="text-end">Duplicate</th><th></th></tr></thead>
                    <tbody>
                    <?php if (empty($history)): ?><tr><td colspan="8" class="text-muted text-center py-3">No imports yet</td></tr><?php endif; ?>
                    <?php foreach ($history as $h): ?>
                        <tr><td class="text-nowrap"><?= e(fmt_datetime($h['created_at'])) ?></td><td><?= e($h['original_file_name']) ?><div class="small text-muted"><?= e($h['created_by_name']) ?></div></td><td><?= e($h['import_type']) ?></td>
                            <td class="text-end"><?= (int) $h['total_records'] ?></td><td class="text-end"><?= (int) $h['imported_count'] ?></td><td class="text-end"><?= (int) $h['failed_count'] ?></td><td class="text-end"><?= (int) $h['duplicate_count'] ?></td>
                            <td><?php if ($h['error_file_path']): ?><a href="<?= e(site_url('imports/report/'.$h['id'])) ?>" data-bs-toggle="tooltip" title="Download report"><i class="fa-solid fa-file-csv"></i></a><?php endif; ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
