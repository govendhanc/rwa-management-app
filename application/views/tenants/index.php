<?php defined('BASEPATH') OR exit('No direct script access allowed');
$today = date('Y-m-d');
$soon = date('Y-m-d', strtotime('+'.$warning_days.' days'));
$active = 0;
$due = 0;
foreach ($tenants as $t)
{
    if ($t['status'] === 'Active')
    {
        $active++;
        if ($t['agreement_end'] !== NULL && $t['agreement_end'] <= $soon)
        {
            $due++;
        }
    }
}
?>
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><span class="stat-icon bg-info-soft"><i class="fa-solid fa-people-roof"></i></span><div><div class="stat-label">Current tenants</div><div class="stat-value"><?= $active ?></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card stat-card"><span class="stat-icon bg-warning-soft"><i class="fa-solid fa-file-signature"></i></span><div><div class="stat-label">Agreements due / expired</div><div class="stat-value"><?= $due ?></div><div class="small text-muted">Ending within <?= (int) $warning_days ?> days</div></div></div></div>
</div>

<div class="page-actions">
    <div class="d-flex flex-wrap gap-2">
        <select class="form-select form-select-sm w-auto js-filter" data-column="8" aria-label="Status">
            <option value="">All tenants</option>
            <option value="Active" selected>Current tenants</option>
            <option value="Moved Out">Moved out</option>
        </select>
        <select class="form-select form-select-sm w-auto" id="filterAgreement" aria-label="Agreement">
            <option value="">All agreements</option>
            <option value="due">Due / expired</option>
        </select>
    </div>
    <?php if (can('tenants.manage')): ?>
        <a href="<?= e(site_url('tenants/create')) ?>" class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i> Add Tenant</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover w-100" id="tenantsTable">
            <thead>
                <tr>
                    <th>Tenant ID</th>
                    <th>Tenant Name</th>
                    <th>Mobile</th>
                    <th>Plot / House</th>
                    <th>Owner</th>
                    <th>Moved In</th>
                    <th>Agreement Ends</th>
                    <th>Moved Out</th>
                    <th>Status</th>
                    <th class="no-export text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tenants as $t):
                $agreement_due = $t['status'] === 'Active' && $t['agreement_end'] !== NULL && $t['agreement_end'] <= $soon;
            ?>
                <tr data-agreement="<?= $agreement_due ? 'due' : '' ?>">
                    <td class="text-nowrap"><?= e($t['tenant_code']) ?></td>
                    <td><a href="<?= e(site_url('tenants/view/'.$t['id'])) ?>" class="fw-semibold text-decoration-none"><?= e($t['tenant_name']) ?></a></td>
                    <td class="text-nowrap"><?= e($t['mobile']) ?></td>
                    <td>Plot <?= e($t['plot_no']) ?><?= $t['house_no'] ? ' &middot; '.e($t['house_no']) : '' ?></td>
                    <td><?= e($t['owner_name']) ?><div class="small text-muted"><?= e($t['owner_code']) ?></div></td>
                    <td data-order="<?= e($t['move_in_date']) ?>"><?= e(fmt_date($t['move_in_date'])) ?></td>
                    <td data-order="<?= e((string) $t['agreement_end']) ?>">
                        <?= e(fmt_date($t['agreement_end'])) ?>
                        <?php if ($agreement_due): ?>
                            <span class="badge rounded-pill <?= $t['agreement_end'] < $today ? 'text-bg-danger' : 'text-bg-warning' ?>"><?= $t['agreement_end'] < $today ? 'Expired' : 'Due soon' ?></span>
                        <?php endif; ?>
                    </td>
                    <td data-order="<?= e((string) $t['move_out_date']) ?>"><?= e(fmt_date($t['move_out_date'])) ?></td>
                    <td><?= status_badge($t['status']) ?></td>
                    <td class="table-actions text-end">
                        <a href="<?= e(site_url('tenants/view/'.$t['id'])) ?>" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="View"><i class="fa-solid fa-eye"></i></a>
                        <?php if (can('tenants.manage')): ?>
                            <a href="<?= e(site_url('tenants/edit/'.$t['id'])) ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
