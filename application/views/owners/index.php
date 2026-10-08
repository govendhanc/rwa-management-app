<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Owner list - Active owners (owners) or Inactive owners (owners/inactive).
 *
 * @var array<int, array<string, mixed>> $owners
 * @var string                           $list_status Active | Inactive
 * @var array{Active: int, Inactive: int} $counts
 */
$is_active_list = $list_status === 'Active';
$total_outstanding = 0;
foreach ($owners as $o)
{
    $total_outstanding += to_paise_signed($o['outstanding']);
}
?>
<div class="page-actions">
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <ul class="nav nav-pills">
            <li class="nav-item"><a class="nav-link py-1<?= $is_active_list ? ' active' : '' ?>" href="<?= e(site_url('owners')) ?>">Active <span class="badge <?= $is_active_list ? 'text-bg-light' : 'text-bg-secondary' ?>"><?= (int) $counts['Active'] ?></span></a></li>
            <li class="nav-item"><a class="nav-link py-1<?= $is_active_list ? '' : ' active' ?>" href="<?= e(site_url('owners/inactive')) ?>">Inactive <span class="badge <?= $is_active_list ? 'text-bg-secondary' : 'text-bg-light' ?>"><?= (int) $counts['Inactive'] ?></span></a></li>
        </ul>
        <label class="visually-hidden" for="filterOccupancy">Occupancy</label>
        <select class="form-select form-select-sm w-auto js-filter" id="filterOccupancy" data-column="6" data-match="contains">
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

<?php if ( ! $is_active_list): ?>
    <div class="alert alert-secondary py-2 small">
        <i class="fa-solid fa-circle-info me-1"></i>
        Inactive owners are not billed. Their bills, payments, receipts and statements are kept, and any outstanding balance can still be collected.
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <table class="table table-hover w-100" id="ownersTable" data-title="<?= e($is_active_list ? 'Owner List' : 'Inactive Owners') ?>">
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Owner ID</th>
                    <th>Plot No</th>
                    <th>House No</th>
                    <th>Owner Name</th>
                    <th>Mobile</th>
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
                    <td><?= $o['occupancy'] !== NULL ? implode(' ', array_map('status_badge', explode(', ', $o['occupancy']))) : '<span class="text-muted small">No house</span>' ?></td>
                    <td class="text-end amount" data-order="<?= $is_active_list ? e($o['monthly_maintenance']) : '0' ?>">
                        <?php if ($is_active_list): ?>
                            <?= e(money($o['monthly_maintenance'])) ?>
                        <?php else: ?>
                            <span class="text-muted small">Not billed</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end amount<?= $due > 0 ? ' text-danger fw-semibold' : '' ?>" data-order="<?= e($o['outstanding']) ?>">
                        <?= e(money($o['outstanding'])) ?>
                        <?php if (to_paise_signed($o['advance_credit']) > 0): ?>
                            <div class="small text-success fw-normal">Credit <?= e(money($o['advance_credit'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= status_badge($o['status']) ?>
                        <?php if ( ! $is_active_list && $o['deactivated_at']): ?>
                            <div class="small text-muted text-nowrap">since <?= e(fmt_date($o['deactivated_at'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="table-actions text-end text-nowrap">
                        <a href="<?= e(site_url('owners/view/'.$o['id'])) ?>" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="View" aria-label="View"><i class="fa-solid fa-eye"></i></a>
                        <?php if (can('owners.edit')): ?>
                            <a href="<?= e(site_url('owners/edit/'.$o['id'])) ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Edit" aria-label="Edit"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if (can('payments.view')): ?>
                            <a href="<?= e(site_url('owners/view/'.$o['id'].'#payments')) ?>" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Payment history" aria-label="Payment history"><i class="fa-solid fa-clock-rotate-left"></i></a>
                        <?php endif; ?>
                        <?php if (can('reports.view')): ?>
                            <a href="<?= e(site_url('reports/statement?owner_id='.$o['id'])) ?>" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Statement" aria-label="Statement"><i class="fa-solid fa-file-lines"></i></a>
                        <?php endif; ?>
                        <?php if (can('payments.create') && ($is_active_list || $due > 0)): ?>
                            <a href="<?= e(site_url('payments/collect?owner_id='.$o['id'])) ?>" class="btn btn-sm btn-outline-success" data-bs-toggle="tooltip" title="Collect payment &amp; generate receipt" aria-label="Collect payment"><i class="fa-solid fa-indian-rupee-sign"></i></a>
                        <?php endif; ?>
                        <?php if (can('owners.deactivate')): ?>
                            <?php $this->load->view('owners/_status_button', array('o' => $o, 'compact' => TRUE)); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="8" class="text-end">Total outstanding (<?= e(strtolower($list_status)) ?> owners)</th>
                    <th class="text-end amount"><?= e(money(from_paise($total_outstanding))) ?></th>
                    <th colspan="2"></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php if (can('owners.deactivate')): ?>
    <?php $this->load->view('owners/_status_modal'); ?>
<?php endif; ?>
