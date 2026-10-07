<?php defined('BASEPATH') OR exit('No direct script access allowed');
$counts = array('total' => count($houses), 'assigned' => 0, 'vacant' => 0, 'inactive' => 0);
foreach ($houses as $h)
{
    $counts['assigned'] += $h['owner_id'] !== NULL ? 1 : 0;
    $counts['vacant'] += $h['occupancy_status'] === 'Vacant' ? 1 : 0;
    $counts['inactive'] += $h['status'] !== 'Active' ? 1 : 0;
}
?>
<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card stat-card"><span class="stat-icon bg-primary-soft"><i class="fa-solid fa-house"></i></span><div><div class="stat-label">Total plots</div><div class="stat-value"><?= $counts['total'] ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card"><span class="stat-icon bg-success-soft"><i class="fa-solid fa-user-check"></i></span><div><div class="stat-label">With owner</div><div class="stat-value"><?= $counts['assigned'] ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card"><span class="stat-icon bg-secondary-soft"><i class="fa-solid fa-house-circle-xmark"></i></span><div><div class="stat-label">Vacant</div><div class="stat-value"><?= $counts['vacant'] ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card"><span class="stat-icon bg-warning-soft"><i class="fa-solid fa-ban"></i></span><div><div class="stat-label">Inactive</div><div class="stat-value"><?= $counts['inactive'] ?></div></div></div></div>
</div>

<div class="page-actions">
    <div class="d-flex flex-wrap gap-2">
        <select class="form-select form-select-sm w-auto js-filter" data-column="3" aria-label="Block">
            <option value="">All blocks</option>
            <?= select_options($blocks) ?>
        </select>
        <select class="form-select form-select-sm w-auto js-filter" data-column="7" aria-label="Occupancy">
            <option value="">All occupancy</option>
            <?= select_options((array) $this->config->item('occupancy_statuses')) ?>
        </select>
        <select class="form-select form-select-sm w-auto js-filter" data-column="10" aria-label="Rate category">
            <option value="">All rate categories</option>
            <?= select_options((array) $this->config->item('plot_categories')) ?>
        </select>
        <select class="form-select form-select-sm w-auto js-filter" data-column="11" aria-label="Status">
            <option value="">All statuses</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
        </select>
    </div>
    <?php if (can('houses.create')): ?>
        <a href="<?= e(site_url('houses/create')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i> Add House / Plot</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover w-100" id="housesTable">
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Plot No</th>
                    <th>House No</th>
                    <th>Block</th>
                    <th>Street</th>
                    <th>House Type</th>
                    <th>Built Status</th>
                    <th>Occupancy</th>
                    <th>Owner</th>
                    <th>Tenant</th>
                    <th>Rate Category</th>
                    <th>Status</th>
                    <th class="no-export text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($houses as $i => $h): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td class="fw-semibold" data-order="<?= e(str_pad($h['plot_no'], 10, '0', STR_PAD_LEFT)) ?>"><?= e($h['plot_no']) ?></td>
                    <td><?= e($h['house_no']) ?></td>
                    <td><?= e($h['block']) ?></td>
                    <td><?= e($h['street']) ?></td>
                    <td><?= e($h['house_type']) ?></td>
                    <td><?= e($h['built_status']) ?></td>
                    <td><?= status_badge($h['occupancy_status']) ?></td>
                    <td>
                        <?php if ($h['owner_id'] !== NULL && $h['owner_name'] !== NULL): ?>
                            <a href="<?= e(site_url('owners/view/'.$h['owner_id'])) ?>" class="text-decoration-none"><?= e($h['owner_name']) ?></a>
                            <div class="small text-muted"><?= e($h['owner_code']) ?><?= $h['owner_status'] === 'Inactive' ? ' &middot; inactive' : '' ?></div>
                        <?php else: ?>
                            <span class="text-muted">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($h['tenant_id'] !== NULL): ?>
                            <?php if (can('tenants.view')): ?><a href="<?= e(site_url('tenants/view/'.$h['tenant_id'])) ?>" class="text-decoration-none"><?= e($h['tenant_name']) ?></a><?php else: ?><?= e($h['tenant_name']) ?><?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($h['plot_category']) ?></td>
                    <td><?= status_badge($h['status']) ?></td>
                    <td class="table-actions text-end">
                        <?php if (can('houses.edit')): ?>
                            <a href="<?= e(site_url('houses/edit/'.$h['id'])) ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit / assign owner"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (can('houses.delete')): ?>
                            <button type="button" class="btn btn-sm <?= $h['status'] === 'Active' ? 'btn-outline-danger' : 'btn-outline-success' ?> js-toggle-house"
                                    data-url="<?= e(site_url('houses/toggle-status/'.$h['id'])) ?>" data-plot="<?= e($h['plot_no']) ?>" data-status="<?= e($h['status']) ?>"
                                    data-bs-toggle="tooltip" title="<?= $h['status'] === 'Active' ? 'Deactivate' : 'Activate' ?>">
                                <i class="fa-solid <?= $h['status'] === 'Active' ? 'fa-ban' : 'fa-check' ?>"></i>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
