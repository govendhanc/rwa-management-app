<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="row g-3">
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header">Create backup</div>
            <div class="card-body">
                <p class="small text-muted">Creates a compressed copy of the whole database (all owners, bills, payments, receipts, settings and logs). Files are stored outside the website and can only be downloaded here.</p>
                <p class="small">Method: <strong><?= e($method) ?></strong></p>
                <?= form_open('backup/create') ?>
                    <button type="submit" class="btn btn-primary w-100" data-loading-text="Creating backup..."><i class="fa-solid fa-database me-1"></i> Create backup now</button>
                <?= form_close() ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header">Restore (for the server administrator)</div>
            <div class="card-body small">
                <ol class="mb-0 ps-3">
                    <li>Download the backup (<code>.sql.gz</code>).</li>
                    <li>Unzip it to get the <code>.sql</code> file.</li>
                    <li>Import it into an empty database with phpMyAdmin (Import) or:<br><code>mysql -u USER -p DBNAME &lt; backup.sql</code></li>
                </ol>
                <p class="mt-2 mb-0 text-muted">Keep a copy off the server (e.g. monthly on a USB drive or cloud storage).</p>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">Backups</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>Created</th><th>File</th><th class="text-end">Size</th><th>Method</th><th>By</th><th></th></tr></thead>
                    <tbody>
                    <?php if (empty($backups)): ?><tr><td colspan="6" class="text-muted text-center py-4">No backups yet</td></tr><?php endif; ?>
                    <?php foreach ($backups as $b): ?>
                        <tr>
                            <td class="text-nowrap"><?= e(fmt_datetime($b['created_at'])) ?></td>
                            <td class="small text-break"><?= e($b['file_name']) ?><?= $b['exists'] ? '' : ' <span class="badge text-bg-warning">file missing</span>' ?></td>
                            <td class="text-end"><?= e($b['size_label']) ?></td>
                            <td><?= e($b['method']) ?></td>
                            <td><?= e($b['created_by_name']) ?></td>
                            <td class="text-end text-nowrap">
                                <?php if ($b['exists']): ?><a href="<?= e(site_url('backup/download/'.$b['id'])) ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-download"></i></a><?php endif; ?>
                                <?= form_open('backup/delete/'.$b['id'], array('class' => 'd-inline')) ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete this backup file permanently?" data-confirm-button="Delete"><i class="fa-solid fa-trash"></i></button>
                                <?= form_close() ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
