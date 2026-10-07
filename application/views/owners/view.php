<?php defined('BASEPATH') OR exit('No direct script access allowed');
$o = $owner;
$detail = function (string $label, ?string $value) {
    return '<div class="col-sm-6 col-lg-4"><div class="small text-muted">'.e($label).'</div><div class="fw-semibold text-break">'
        .($value !== NULL && $value !== '' ? e($value) : '<span class="text-muted fw-normal">-</span>').'</div></div>';
};
?>
<div class="page-actions">
    <div class="d-flex align-items-center gap-3">
        <span class="avatar" style="width:48px;height:48px;font-size:1.2rem;"><?= e(mb_strtoupper(mb_substr($o['owner_name'], 0, 1))) ?></span>
        <div>
            <div class="h5 mb-0"><?= e($o['owner_name']) ?> <?= status_badge($o['status']) ?></div>
            <div class="text-muted small"><?= e($o['owner_code']) ?><?= $o['co_owner_name'] ? ' &middot; Co-owner: '.e($o['co_owner_name']) : '' ?></div>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php if (can('payments.create')): ?>
            <a href="<?= e(site_url('payments/collect?owner_id='.$o['id'])) ?>" class="btn btn-success"><i class="fa-solid fa-indian-rupee-sign me-1"></i> Collect Payment</a>
        <?php endif; ?>
        <?php if (can('owners.edit')): ?>
            <a href="<?= e(site_url('owners/edit/'.$o['id'])) ?>" class="btn btn-outline-primary"><i class="fa-solid fa-pen me-1"></i> Edit</a>
        <?php endif; ?>
        <?php if ($can_delete): ?>
            <button type="button" class="btn btn-outline-danger js-delete-owner" data-url="<?= e(site_url('owners/delete/'.$o['id'])) ?>" data-name="<?= e($o['owner_name']) ?>"><i class="fa-solid fa-trash me-1"></i> Delete</button>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card stat-card"><span class="stat-icon bg-primary-soft"><i class="fa-solid fa-file-invoice"></i></span><div><div class="stat-label">Total billed</div><div class="stat-value"><?= e(money($o['total_due'])) ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card"><span class="stat-icon bg-success-soft"><i class="fa-solid fa-circle-check"></i></span><div><div class="stat-label">Total paid</div><div class="stat-value"><?= e(money($o['total_paid'])) ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card"><span class="stat-icon bg-danger-soft"><i class="fa-solid fa-hourglass-half"></i></span><div><div class="stat-label">Outstanding</div><div class="stat-value"><?= e(money($o['outstanding'])) ?></div><?php if (to_paise_signed($o['total_waived']) > 0): ?><div class="small text-muted"><?= e(money($o['total_waived'])) ?> waived</div><?php endif; ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="card stat-card"><span class="stat-icon bg-secondary-soft"><i class="fa-solid fa-forward"></i></span><div><div class="stat-label">Advance credit</div><div class="stat-value"><?= e(money($o['advance_credit'])) ?></div><div class="small text-muted">Last paid: <?= $o['last_payment_date'] ? e(fmt_date($o['last_payment_date'])) : 'never' ?></div></div></div></div>
</div>

<div class="card">
    <div class="card-header p-0 border-0">
        <ul class="nav nav-tabs px-3 pt-2" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabDetails" type="button" role="tab">Details</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabHouses" type="button" role="tab" id="housesTabBtn">Plots <span class="badge text-bg-light"><?= count($houses) ?></span></button></li>
            <?php if (can('tenants.view')): ?>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabTenants" type="button" role="tab">Tenants <span class="badge text-bg-light"><?= count($tenants) ?></span></button></li>
            <?php endif; ?>
            <?php if (can('maintenance.view')): ?>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabMaintenance" type="button" role="tab">Maintenance <span class="badge text-bg-light"><?= count($maintenance) ?></span></button></li>
            <?php endif; ?>
            <?php if (can('payments.view')): ?>
                <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPayments" type="button" role="tab" id="paymentsTabBtn">Payment History <span class="badge text-bg-light"><?= count($payments) ?></span></button></li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="card-body tab-content">
        <div class="tab-pane fade show active" id="tabDetails" role="tabpanel">
            <div class="row g-3">
                <?= $detail('Mobile', $o['mobile']) ?>
                <?= $detail('WhatsApp', $o['whatsapp_no']) ?>
                <?= $detail('E-mail', $o['email']) ?>
                <?= $detail('Owner Type', $o['owner_type']) ?>
                <?= $detail('Date of Joining', fmt_date($o['joining_date'])) ?>
                <?= $detail('Monthly Maintenance (all plots)', money($monthly).' at current rates') ?>
                <?= $detail('Maintenance Start Date', fmt_date($o['maintenance_start_date'])) ?>
                <?= $detail('Permanent Address', $o['permanent_address']) ?>
                <?= $detail('Residential Address', $o['residential_address']) ?>
                <?= $detail('Remarks', $o['remarks']) ?>
                <?= $detail('Created', trim(fmt_datetime($o['created_at']).' '.($o['created_by_name'] ? 'by '.$o['created_by_name'] : ''))) ?>
                <?= $detail('Last Updated', trim(fmt_datetime($o['updated_at']).' '.($o['updated_by_name'] ? 'by '.$o['updated_by_name'] : ''))) ?>
            </div>
        </div>

        <div class="tab-pane fade" id="tabHouses" role="tabpanel">
            <?php if (empty($houses)): ?>
                <p class="text-muted">No plot assigned. Owners without a plot are not billed.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Plot No</th><th>House No</th><th>Block</th><th>Type</th><th>Built</th><th>Rate category</th><th class="text-end">Current rate</th><th>Occupancy</th><th>Tenant</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($houses as $h): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($h['plot_no']) ?></td>
                            <td><?= e($h['house_no']) ?></td>
                            <td><?= e($h['block']) ?></td>
                            <td><?= e($h['house_type']) ?></td>
                            <td><?= e($h['built_status']) ?></td>
                            <td><?= e($h['plot_category']) ?></td>
                            <td class="text-end amount"><?= $h['current_rate'] !== NULL ? e(money($h['current_rate'])) : '<span class="text-danger">Not set</span>' ?></td>
                            <td><?= status_badge($h['occupancy_status']) ?></td>
                            <td>
                                <?php if ($h['tenant_id'] !== NULL && can('tenants.view')): ?>
                                    <a href="<?= e(site_url('tenants/view/'.$h['tenant_id'])) ?>"><?= e($h['tenant_name']) ?></a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= status_badge($h['status']) ?></td>
                            <td class="text-end text-nowrap">
                                <?php if (can('tenants.manage') && $h['tenant_id'] === NULL && $h['status'] === 'Active'): ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(site_url('tenants/create?house_id='.$h['id'])) ?>" data-bs-toggle="tooltip" title="Add tenant"><i class="fa-solid fa-user-plus"></i></a>
                                <?php endif; ?>
                                <?php if (can('houses.edit')): ?><a class="btn btn-sm btn-outline-primary" href="<?= e(site_url('houses/edit/'.$h['id'])) ?>">Edit</a><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if (can('owners.edit') && $o['status'] === 'Active'): ?>
            <div class="border rounded-3 p-3 mt-2">
                <div class="fw-semibold mb-2">Assign another plot</div>
                <?php if ( ! empty($free_houses)): ?>
                    <?= form_open('owners/assign-plot/'.$o['id'], array('class' => 'row g-2 align-items-end')) ?>
                        <div class="col-md-5">
                            <label class="form-label" for="assign_house_id">Unassigned plot</label>
                            <select class="form-select" id="assign_house_id" name="house_id" required>
                                <?= select_options($free_houses, NULL, 'Select plot') ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="assign_occupancy">Occupancy</label>
                            <select class="form-select" id="assign_occupancy" name="occupancy_status">
                                <?= select_options(array('Owner Occupied', 'Vacant'), 'Vacant') ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-link me-1"></i>Assign</button>
                        </div>
                    <?= form_close() ?>
                <?php else: ?>
                    <p class="small text-muted mb-2">There are no unassigned plots.</p>
                <?php endif; ?>
                <?php if (can('houses.create')): ?>
                    <a href="<?= e(site_url('houses/create?owner_id='.$o['id'])) ?>" class="small d-inline-block mt-2"><i class="fa-solid fa-plus me-1"></i>Register a new plot for this owner</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php if (can('tenants.view')): ?>
        <div class="tab-pane fade" id="tabTenants" role="tabpanel">
            <?php if (empty($tenants)): ?>
                <p class="text-muted mb-0">No tenants recorded for this owner's plots.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Tenant</th><th>Plot</th><th>Mobile</th><th>Moved In</th><th>Moved Out</th><th>Agreement Ends</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($tenants as $t): ?>
                        <tr>
                            <td><a href="<?= e(site_url('tenants/view/'.$t['id'])) ?>"><?= e($t['tenant_name']) ?></a><div class="small text-muted"><?= e($t['tenant_code']) ?></div></td>
                            <td><?= e($t['plot_no']) ?><?= $t['house_no'] ? ' &middot; '.e($t['house_no']) : '' ?></td>
                            <td><?= e($t['mobile']) ?></td>
                            <td><?= e(fmt_date($t['move_in_date'])) ?></td>
                            <td><?= e(fmt_date($t['move_out_date'])) ?></td>
                            <td><?= e(fmt_date($t['agreement_end'])) ?></td>
                            <td><?= status_badge($t['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (can('maintenance.view')): ?>
        <div class="tab-pane fade" id="tabMaintenance" role="tabpanel">
            <table class="table table-sm table-hover w-100" id="ownerMaintenanceTable">
                <thead><tr><th>Month</th><th>Plot</th><th>Type</th><th>Due Date</th><th class="text-end">Amount</th><th class="text-end">Paid</th><th class="text-end">Waived</th><th class="text-end">Balance</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($maintenance as $m): ?>
                    <tr class="<?= $m['record_status'] === 'Cancelled' ? 'text-muted text-decoration-line-through' : '' ?>">
                        <td data-order="<?= e($m['period_start']) ?>"><?= e(period_label((int) $m['billing_year'], (int) $m['billing_month'])) ?></td>
                        <td><?= e($m['plot_no']) ?></td>
                        <td><?= e($m['charge_type']) ?></td>
                        <td data-order="<?= e($m['due_date']) ?>"><?= e(fmt_date($m['due_date'])) ?></td>
                        <td class="text-end amount"><?= e(money($m['amount'])) ?></td>
                        <td class="text-end amount"><?= e(money($m['paid_amount'])) ?></td>
                        <td class="text-end amount"><?= e(money($m['waived_amount'])) ?></td>
                        <td class="text-end amount fw-semibold"><?= e(money($m['balance_amount'])) ?></td>
                        <td><?= status_badge($m['record_status'] === 'Cancelled' ? 'Cancelled' : $m['payment_status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (can('payments.view')): ?>
        <div class="tab-pane fade" id="tabPayments" role="tabpanel">
            <table class="table table-sm table-hover w-100" id="ownerPaymentsTable">
                <thead><tr><th>Date</th><th>Receipt</th><th>Months</th><th class="text-end">Amount</th><th>Mode</th><th>Reference</th><th>Received By</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($payments as $p): ?>
                    <tr class="<?= $p['status'] !== 'Active' ? 'text-muted' : '' ?>">
                        <td data-order="<?= e($p['payment_date']) ?>"><?= e(fmt_date($p['payment_date'])) ?></td>
                        <td class="text-nowrap">
                            <?php if ($p['receipt_id'] && can('receipts.view')): ?>
                                <a href="<?= e(site_url('receipts/view/'.$p['receipt_id'])) ?>"><?= e($p['receipt_no']) ?></a>
                                <?php if (can('receipts.download')): ?><a href="<?= e(site_url('receipts/pdf/'.$p['receipt_id'].'/a4')) ?>" class="ms-1" data-bs-toggle="tooltip" title="Download PDF"><i class="fa-solid fa-file-pdf"></i></a><?php endif; ?>
                            <?php else: ?>
                                <?= e($p['receipt_no']) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= e($p['period_label']) ?></td>
                        <td class="text-end amount">
                            <?= e(money($p['amount'])) ?>
                            <?php if ((int) $p['is_advance'] === 1 && $p['status'] === 'Active' && to_paise_signed($p['unallocated_amount']) > 0): ?>
                                <div class="small text-success"><?= e(money($p['unallocated_amount'])) ?> credit left</div>
                            <?php endif; ?>
                        </td>
                        <td><?= e($p['payment_mode']) ?></td>
                        <td><?= e($p['transaction_ref']) ?></td>
                        <td><?= e($p['received_by_name']) ?></td>
                        <td><?= status_badge($p['status']) ?><?php if ($p['status'] !== 'Active' && $p['cancel_reason']): ?><div class="small"><?= e($p['cancel_reason']) ?></div><?php endif; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
