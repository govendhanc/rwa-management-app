<?php defined('BASEPATH') OR exit('No direct script access allowed');
$total_outstanding = 0;
foreach ($owners as $o)
{
    $total_outstanding += to_paise_signed($o['outstanding']);
}
?>
<div class="page-actions">
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <label class="visually-hidden" for="filterStatus">Status</label>
        <select class="form-select form-select-sm w-auto js-filter" id="filterStatus" data-column="11">
            <option value="">All statuses</option>
            <option value="Active">Active</option>
            <option value="Inactive">Inactive</option>
        </select>
        <label class="visually-hidden" for="filterOccupancy">Occupancy</label>
        <select class="form-select form-select-sm w-auto js-filter" id="filterOccupancy" data-column="8" data-match="contains">
            <option value="">All occupancy</option>
            <?= select_options((array) $this->config->item('occupancy_statuses')) ?>
            <option value="No house">No house assigned</option>
        </select>
        <label class="visually-hidden" for="filterDues">Dues</label>
        <select class="form-select form-select-sm w-auto" id="filterDues">
            <option value="">All dues</option>
            <option value="due">With outstanding</option>
            <option value="clear">No outstanding</option>
        </select>
    </div>
    <div class="d-flex gap-2">
        <?php if (can('owners.import')): ?>
            <a href="<?= e(site_url('imports')) ?>" class="btn btn-outline-primary"><i class="fa-solid fa-file-import me-1"></i> Import</a>
        <?php endif; ?>
        <?php if (can('owners.create')): ?>
            <a href="<?= e(site_url('owners/create')) ?>" class="btn btn-primary"><i class="fa-solid fa-user-plus me-1"></i> Add Owner</a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover w-100" id="ownersTable">
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Owner ID</th>
                    <th>Plot No</th>
                    <th>House No</th>
                    <th>Owner Name</th>
                    <th>Mobile</th>
                    <th>WhatsApp</th>
                    <th>Email</th>
                    <th>Occupancy</th>
                    <th class="text-end">Maintenance</th>
                    <th class="text-end">Outstanding</th>
                    <th>Status</th>
                    <th class="no-export text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($owners as $i => $o):
                $due = to_paise_signed($o['outstanding']);
            ?>
                <tr data-due="<?= $due > 0 ? 'due' : 'clear' ?>">
                    <td><?= $i + 1 ?></td>
                    <td class="text-nowrap"><?= e($o['owner_code']) ?></td>
                    <td><?= $o['plots'] !== NULL ? e($o['plots']) : '<span class="text-muted">-</span>' ?></td>
                    <td><?= $o['house_nos'] !== NULL ? e($o['house_nos']) : '<span class="text-muted">-</span>' ?></td>
                    <td>
                        <a href="<?= e(site_url('owners/view/'.$o['id'])) ?>" class="fw-semibold text-decoration-none"><?= e($o['owner_name']) ?></a>
                        <?php if ($o['co_owner_name']): ?><div class="small text-muted">&amp; <?= e($o['co_owner_name']) ?></div><?php endif; ?>
                    </td>
                    <td class="text-nowrap"><?= e($o['mobile']) ?></td>
                    <td class="text-nowrap"><?= e($o['whatsapp_no']) ?></td>
                    <td><?= e($o['email']) ?></td>
                    <td><?= $o['occupancy'] !== NULL ? implode(' ', array_map('status_badge', explode(', ', $o['occupancy']))) : '<span class="text-muted small">No house</span>' ?></td>
                    <td class="text-end amount" data-order="<?= e($o['monthly_maintenance']) ?>"><?= e(money($o['monthly_maintenance'])) ?></td>
                    <td class="text-end amount<?= $due > 0 ? ' text-danger fw-semibold' : '' ?>" data-order="<?= e($o['outstanding']) ?>">
                        <?= e(money($o['outstanding'])) ?>
                        <?php if (to_paise_signed($o['advance_credit']) > 0): ?>
                            <div class="small text-success fw-normal">Credit <?= e(money($o['advance_credit'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= status_badge($o['status']) ?></td>
                    <td class="table-actions text-end">
                        <a href="<?= e(site_url('owners/view/'.$o['id'])) ?>" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="View"><i class="fa-solid fa-eye"></i></a>
                        <a href="<?= e(site_url('owners/view/'.$o['id'].'#payments')) ?>" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Payment history"><i class="fa-solid fa-clock-rotate-left"></i></a>
                        <?php if (can('payments.create')): ?>
                            <a href="<?= e(site_url('payments/collect?owner_id='.$o['id'])) ?>" class="btn btn-sm btn-outline-success" data-bs-toggle="tooltip" title="Collect payment &amp; generate receipt"><i class="fa-solid fa-indian-rupee-sign"></i></a>
                        <?php endif; ?>
                        <?php if (can('owners.edit')): ?>
                            <a href="<?= e(site_url('owners/edit/'.$o['id'])) ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="10" class="text-end">Total outstanding (all owners)</th>
                    <th class="text-end amount"><?= e(money(from_paise($total_outstanding))) ?></th>
                    <th colspan="2"></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
