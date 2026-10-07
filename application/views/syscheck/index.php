<?php defined('BASEPATH') OR exit('No direct script access allowed');
$icons = array(
    'ok'      => '<i class="fa-solid fa-circle-check text-success"></i>',
    'warning' => '<i class="fa-solid fa-triangle-exclamation text-warning"></i>',
    'error'   => '<i class="fa-solid fa-circle-xmark text-danger"></i>',
);
?>
<div class="d-flex flex-wrap gap-2 justify-content-center mb-4">
    <span class="badge rounded-pill text-bg-success fs-6"><?= (int) $summary['ok'] ?> passed</span>
    <span class="badge rounded-pill text-bg-warning fs-6"><?= (int) $summary['warning'] ?> warnings</span>
    <span class="badge rounded-pill text-bg-danger fs-6"><?= (int) $summary['error'] ?> errors</span>
</div>

<?php if ($summary['error'] === 0): ?>
    <div class="alert alert-success d-flex align-items-center gap-2">
        <i class="fa-solid fa-circle-check"></i>
        <div>The server meets all requirements. Warnings are recommendations.</div>
    </div>
<?php else: ?>
    <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="fa-solid fa-circle-xmark"></i>
        <div>Fix the items marked in red before using the portal.</div>
    </div>
<?php endif; ?>

<div class="row g-3">
<?php foreach ($groups as $group => $checks): ?>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><?= e($group) ?></div>
            <ul class="list-group list-group-flush check-list">
            <?php foreach ($checks as $check): ?>
                <li class="list-group-item">
                    <span class="check-icon"><?= $icons[$check['status']] ?></span>
                    <div class="min-w-0">
                        <div class="fw-semibold"><?= e($check['label']) ?></div>
                        <div class="small text-muted text-break"><?= e($check['detail']) ?></div>
                    </div>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endforeach; ?>
</div>

<?php if (ENVIRONMENT === 'development'): ?>
<div class="text-center mt-4">
    <a href="<?= e(site_url('syscheck/preview')) ?>" class="btn btn-primary">
        <i class="fa-solid fa-table-columns me-1"></i> Preview application layout
    </a>
</div>
<?php endif; ?>
