<?php defined('BASEPATH') OR exit('No direct script access allowed');
$is_new = $house['id'] === NULL;
$f = array();
foreach (array('plot_no', 'house_no', 'block', 'street', 'house_type', 'built_status', 'occupancy_status', 'owner_id', 'area_sqft', 'uds_sqft', 'remarks', 'status') as $field)
{
    $f[$field] = field_error($field);
}
$val = function (string $field) use ($house) {
    return set_value($field, isset($house[$field]) ? (string) $house[$field] : '');
};
$cfg = $this->config;
?>
<?php if ( ! empty($form_error)): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= e($form_error) ?></div>
<?php endif; ?>

<?= form_open($is_new ? 'houses/create' : 'houses/edit/'.$house['id'], array('novalidate' => 'novalidate')) ?>
<?php if ($is_new && ! empty($house['owner_id'])): ?>
    <input type="hidden" name="return_owner" value="<?= (int) $house['owner_id'] ?>">
<?php endif; ?>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">Plot details</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label" for="plot_no">Plot Number <span class="required">*</span></label>
                        <input type="text" class="form-control<?= $f['plot_no']['class'] ?>" id="plot_no" name="plot_no" maxlength="20" required value="<?= e($val('plot_no')) ?>">
                        <?= $f['plot_no']['message'] ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="house_no">House Number</label>
                        <input type="text" class="form-control<?= $f['house_no']['class'] ?>" id="house_no" name="house_no" maxlength="20" value="<?= e($val('house_no')) ?>">
                        <?= $f['house_no']['message'] ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="block">Block</label>
                        <input type="text" class="form-control<?= $f['block']['class'] ?>" id="block" name="block" maxlength="20" value="<?= e($val('block')) ?>">
                        <?= $f['block']['message'] ?>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="street">Street</label>
                        <input type="text" class="form-control<?= $f['street']['class'] ?>" id="street" name="street" maxlength="100" value="<?= e($val('street')) ?>">
                        <?= $f['street']['message'] ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="status">Status <span class="required">*</span></label>
                        <select class="form-select<?= $f['status']['class'] ?>" id="status" name="status">
                            <?= select_options(array('Active', 'Inactive'), $val('status')) ?>
                        </select>
                        <?= $f['status']['message'] ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="house_type">House Type <span class="required">*</span></label>
                        <select class="form-select<?= $f['house_type']['class'] ?>" id="house_type" name="house_type">
                            <?= select_options((array) $cfg->item('house_types'), $val('house_type')) ?>
                        </select>
                        <?= $f['house_type']['message'] ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="built_status">Built Status <span class="required">*</span></label>
                        <select class="form-select<?= $f['built_status']['class'] ?>" id="built_status" name="built_status"
                                data-rate-constructed="<?= e(isset($rates['Constructed']) ? money($rates['Constructed']['amount']) : '') ?>"
                                data-rate-vacant="<?= e(isset($rates['Vacant Plot']) ? money($rates['Vacant Plot']['amount']) : '') ?>">
                            <?= select_options((array) $cfg->item('built_statuses'), $val('built_status')) ?>
                        </select>
                        <div class="form-text" id="rateHint"></div>
                        <?= $f['built_status']['message'] ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="area_sqft">Area (sq.ft)</label>
                        <input type="text" class="form-control<?= $f['area_sqft']['class'] ?>" id="area_sqft" name="area_sqft" inputmode="decimal" value="<?= e($val('area_sqft')) ?>">
                        <?= $f['area_sqft']['message'] ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="uds_sqft">UDS (sq.ft)</label>
                        <input type="text" class="form-control<?= $f['uds_sqft']['class'] ?>" id="uds_sqft" name="uds_sqft" inputmode="decimal" value="<?= e($val('uds_sqft')) ?>">
                        <?= $f['uds_sqft']['message'] ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="remarks">Remarks</label>
                        <textarea class="form-control<?= $f['remarks']['class'] ?>" id="remarks" name="remarks" rows="2" maxlength="500"><?= e($val('remarks')) ?></textarea>
                        <?= $f['remarks']['message'] ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">Owner &amp; occupancy</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="owner_id">Current Owner</label>
                    <select class="form-select<?= $f['owner_id']['class'] ?>" id="owner_id" name="owner_id">
                        <?= select_options($owners, $val('owner_id'), '- Unassigned -') ?>
                    </select>
                    <?= $f['owner_id']['message'] ?>
                    <?php if ( ! $is_new && $bill_count > 0): ?>
                        <div class="form-text"><i class="fa-solid fa-circle-info me-1"></i>This plot has <?= (int) $bill_count ?> bill(s). Changing the owner keeps past bills with the previous owner; new bills go to the new owner.</div>
                    <?php endif; ?>
                </div>
                <div>
                    <label class="form-label" for="occupancy_status">Occupancy Status <span class="required">*</span></label>
                    <?php if ($tenant !== NULL): ?>
                        <input type="text" class="form-control" id="occupancy_status" value="Tenant Occupied" disabled>
                        <div class="form-text">
                            Current tenant:
                            <?php if (can('tenants.view')): ?><a href="<?= e(site_url('tenants/view/'.$tenant['id'])) ?>"><?= e($tenant['tenant_name']) ?></a><?php else: ?><?= e($tenant['tenant_name']) ?><?php endif; ?>
                            since <?= e(fmt_date($tenant['move_in_date'])) ?>. Occupancy changes when the move-out is recorded.
                        </div>
                    <?php else: ?>
                        <select class="form-select<?= $f['occupancy_status']['class'] ?>" id="occupancy_status" name="occupancy_status">
                            <?= select_options(array('Owner Occupied', 'Vacant'), $val('occupancy_status') === 'Tenant Occupied' ? 'Owner Occupied' : $val('occupancy_status')) ?>
                        </select>
                        <div class="form-text">An unassigned plot is always saved as Vacant. To let the house to a tenant, use <strong>Tenants &rarr; Add Tenant</strong>.</div>
                        <?= $f['occupancy_status']['message'] ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="d-flex gap-2 mt-3">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save</button>
    <a href="<?= e(site_url('houses')) ?>" class="btn btn-light">Cancel</a>
</div>
<?= form_close() ?>
