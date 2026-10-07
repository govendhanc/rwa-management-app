<?php defined('BASEPATH') OR exit('No direct script access allowed');
$t = $tenant;
$detail = function (string $label, ?string $value, string $col = 'col-sm-6 col-lg-4') {
    return '<div class="'.$col.'"><div class="small text-muted">'.e($label).'</div><div class="fw-semibold text-break">'
        .($value !== NULL && $value !== '' ? e($value) : '<span class="text-muted fw-normal">-</span>').'</div></div>';
};
$id_display = NULL;
if ($t['id_proof_type'] !== NULL)
{
    $id_display = $t['id_proof_type'] === 'Aadhaar' ? 'XXXX XXXX '.$t['id_proof_number'] : (string) $t['id_proof_number'];
}
$today = date('Y-m-d');
$agreement_note = '';
if ($t['status'] === 'Active' && $t['agreement_end'] !== NULL)
{
    $days = (int) floor((strtotime($t['agreement_end']) - strtotime($today)) / 86400);
    $agreement_note = $days < 0 ? 'Expired '.abs($days).' day(s) ago' : ($days <= $warning_days ? 'Ends in '.$days.' day(s)' : '');
}
?>
<div class="page-actions">
    <div>
        <div class="h5 mb-0"><?= e($t['tenant_name']) ?>
            <span class="badge rounded-pill <?= $t['status'] === 'Active' ? 'bg-success' : 'bg-secondary' ?>"><?= e($t['status']) ?></span>
        </div>
        <div class="text-muted small"><?= e($t['tenant_code']) ?> &middot; Plot <?= e($t['plot_no']) ?><?= $t['house_no'] ? ' - '.e($t['house_no']) : '' ?> &middot;
            Owner: <a href="<?= e(site_url('owners/view/'.$t['owner_id'])) ?>"><?= e($t['owner_name']) ?></a></div>
    </div>
    <div class="d-flex gap-2">
        <?php if (can('tenants.manage')): ?>
            <a href="<?= e(site_url('tenants/edit/'.$t['id'])) ?>" class="btn btn-outline-primary"><i class="fa-solid fa-pen me-1"></i> Edit</a>
            <?php if ($t['status'] === 'Active'): ?>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#moveOutModal"><i class="fa-solid fa-person-walking-luggage me-1"></i> Record Move-out</button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($agreement_note !== ''): ?>
    <div class="alert <?= strpos($agreement_note, 'Expired') === 0 ? 'alert-danger' : 'alert-warning' ?> d-flex gap-2 align-items-center">
        <i class="fa-solid fa-file-signature"></i><div>Rental agreement: <strong><?= e($agreement_note) ?></strong> (<?= e(fmt_date($t['agreement_end'])) ?>).</div>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header">Tenant details</div>
            <div class="card-body">
                <div class="row g-3">
                    <?= $detail('Mobile', $t['mobile']) ?>
                    <?= $detail('WhatsApp', $t['whatsapp_no']) ?>
                    <?= $detail('E-mail', $t['email']) ?>
                    <?= $detail('Moved In', fmt_date($t['move_in_date'])) ?>
                    <?= $detail('Moved Out', fmt_date($t['move_out_date'])) ?>
                    <?= $detail('House Occupancy', $t['occupancy_status']) ?>
                    <?= $detail('Permanent Address', $t['permanent_address'], 'col-12') ?>
                    <?= $detail('Remarks', $t['remarks'], 'col-12') ?>
                    <?= $detail('Created', trim(fmt_datetime($t['created_at']).' '.($t['created_by_name'] ? 'by '.$t['created_by_name'] : ''))) ?>
                    <?= $detail('Last Updated', trim(fmt_datetime($t['updated_at']).' '.($t['updated_by_name'] ? 'by '.$t['updated_by_name'] : ''))) ?>
                </div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header">Emergency contact</div>
            <div class="card-body">
                <div class="row g-3">
                    <?= $detail('Name', $t['emergency_contact_name']) ?>
                    <?= $detail('Phone', $t['emergency_contact_phone']) ?>
                    <?= $detail('Relationship', $t['emergency_contact_relation']) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header">Rental agreement</div>
            <div class="card-body">
                <div class="row g-3">
                    <?= $detail('Start', fmt_date($t['agreement_start']), 'col-6') ?>
                    <?= $detail('End', fmt_date($t['agreement_end']), 'col-6') ?>
                    <div class="col-12">
                        <?php if ( ! empty($t['agreement_file'])): ?>
                            <a href="<?= e(site_url('tenants/document/'.$t['id'].'/agreement')) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-file-lines me-1"></i>View agreement</a>
                        <?php else: ?>
                            <span class="text-muted small">No agreement copy uploaded.</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between"><span>ID proof</span><span class="badge text-bg-warning"><i class="fa-solid fa-lock me-1"></i>Restricted</span></div>
            <div class="card-body">
                <?php if (can('tenants.id_proof')): ?>
                    <div class="row g-3">
                        <?= $detail('Type', $t['id_proof_type'], 'col-6') ?>
                        <?= $detail('Number', $id_display, 'col-6') ?>
                        <div class="col-12">
                            <?php if ( ! empty($t['id_proof_file'])): ?>
                                <a href="<?= e(site_url('tenants/document/'.$t['id'].'/id-proof')) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-id-card me-1"></i>View ID proof</a>
                            <?php else: ?>
                                <span class="text-muted small">No ID proof copy uploaded.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">ID proof details are visible only to Admin and Super Admin.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (can('tenants.manage') && $t['status'] === 'Active'): ?>
<div class="modal fade" id="moveOutModal" tabindex="-1" aria-labelledby="moveOutTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <?= form_open('tenants/move-out/'.$t['id']) ?>
            <div class="modal-header">
                <h5 class="modal-title" id="moveOutTitle">Record move-out</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label" for="move_out_date">Move-out date <span class="required">*</span></label>
                    <input type="date" class="form-control" id="move_out_date" name="move_out_date" required
                           min="<?= e($t['move_in_date']) ?>" max="<?= e($today) ?>" value="<?= e($today) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="occupancy_after">House after move-out <span class="required">*</span></label>
                    <select class="form-select" id="occupancy_after" name="occupancy_after" required>
                        <option value="Vacant">Vacant</option>
                        <option value="Owner Occupied">Owner Occupied (owner moves in)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="move_out_remarks">Remarks</label>
                    <input type="text" class="form-control" id="move_out_remarks" name="move_out_remarks" maxlength="200" placeholder="e.g. Agreement ended, keys returned">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Record Move-out</button>
            </div>
            <?= form_close() ?>
        </div>
    </div>
</div>
<?php endif; ?>
