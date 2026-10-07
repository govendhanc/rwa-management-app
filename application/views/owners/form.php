<?php defined('BASEPATH') OR exit('No direct script access allowed');
$is_new = $owner['id'] === NULL;
$fields = array('owner_name', 'co_owner_name', 'mobile', 'whatsapp_no', 'email', 'permanent_address', 'residential_address',
    'owner_type', 'joining_date', 'maintenance_start_date', 'status', 'remarks');
$f = array();
foreach ($fields as $field)
{
    $f[$field] = field_error($field);
}
$val = function (string $field) use ($owner) {
    return set_value($field, isset($owner[$field]) ? (string) $owner[$field] : '');
};
$cfg = $this->config;
$owner_occupancy = array('Owner Occupied', 'Vacant');

/**
 * One plot row of the Add Owner form. $index is an int for real rows or '__INDEX__' for the JS template.
 *
 * @param int|string           $index
 * @param array<string, mixed> $row   Posted values
 */
$plot_row = function ($index, array $row) use ($free_houses, $cfg, $owner_occupancy) {
    $name = function (string $key) use ($index) { return 'plots['.$index.']['.$key.']'; };
    $id = function (string $key) use ($index) { return 'plot_'.$index.'_'.$key; };
    $v = function (string $key) use ($row) { return isset($row[$key]) ? (string) $row[$key] : ''; };
    $err = function (string $key) use ($index, $name) {
        if ( ! is_int($index)) { return array('class' => '', 'message' => ''); }
        return field_error($name($key));
    };
    $mode = $v('mode') !== '' ? $v('mode') : (empty($free_houses) ? 'new' : 'existing');
    ob_start(); ?>
    <div class="border rounded-3 p-3 mb-3 js-plot-row" data-index="<?= e((string) $index) ?>">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div class="fw-semibold">Plot <span class="js-plot-number"><?= is_int($index) ? $index + 1 : '' ?></span></div>
            <div class="d-flex gap-2 align-items-center">
                <div class="btn-group btn-group-sm" role="group" aria-label="Plot type">
                    <input type="radio" class="btn-check js-plot-mode" name="<?= e($name('mode')) ?>" id="<?= e($id('mode_existing')) ?>" value="existing" <?= $mode === 'existing' ? 'checked' : '' ?> <?= empty($free_houses) ? 'disabled' : '' ?>>
                    <label class="btn btn-outline-primary" for="<?= e($id('mode_existing')) ?>">Existing plot</label>
                    <input type="radio" class="btn-check js-plot-mode" name="<?= e($name('mode')) ?>" id="<?= e($id('mode_new')) ?>" value="new" <?= $mode === 'new' ? 'checked' : '' ?>>
                    <label class="btn btn-outline-primary" for="<?= e($id('mode_new')) ?>">New plot</label>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger js-remove-plot" aria-label="Remove plot"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
        <div class="row g-2">
            <div class="col-md-8 js-mode-panel" data-mode="existing">
                <?php $e = $err('house_id'); ?>
                <label class="form-label" for="<?= e($id('house_id')) ?>">Unassigned plot <span class="required">*</span></label>
                <select class="form-select<?= $e['class'] ?>" id="<?= e($id('house_id')) ?>" name="<?= e($name('house_id')) ?>">
                    <?= select_options($free_houses, $v('house_id'), empty($free_houses) ? 'No unassigned plots' : 'Select plot') ?>
                </select>
                <?= $e['message'] ?>
            </div>
            <?php foreach (array('plot_no' => array('Plot Number', TRUE, 3), 'house_no' => array('House Number', FALSE, 3), 'block' => array('Block', FALSE, 2), 'street' => array('Street', FALSE, 4)) as $key => $meta): $e = $err($key); ?>
            <div class="col-md-<?= (int) $meta[2] ?> js-mode-panel" data-mode="new">
                <label class="form-label" for="<?= e($id($key)) ?>"><?= e($meta[0]) ?><?= $meta[1] ? ' <span class="required">*</span>' : '' ?></label>
                <input type="text" class="form-control<?= $e['class'] ?>" id="<?= e($id($key)) ?>" name="<?= e($name($key)) ?>" maxlength="<?= $key === 'street' ? 100 : 20 ?>" value="<?= e($v($key)) ?>">
                <?= $e['message'] ?>
            </div>
            <?php endforeach; ?>
            <div class="col-md-4 js-mode-panel" data-mode="new">
                <?php $e = $err('house_type'); ?>
                <label class="form-label" for="<?= e($id('house_type')) ?>">House Type</label>
                <select class="form-select<?= $e['class'] ?>" id="<?= e($id('house_type')) ?>" name="<?= e($name('house_type')) ?>">
                    <?= select_options((array) $cfg->item('house_types'), $v('house_type') !== '' ? $v('house_type') : 'Independent House') ?>
                </select>
                <?= $e['message'] ?>
            </div>
            <div class="col-md-4 js-mode-panel" data-mode="new">
                <?php $e = $err('built_status'); ?>
                <label class="form-label" for="<?= e($id('built_status')) ?>">Built Status</label>
                <select class="form-select js-built-status<?= $e['class'] ?>" id="<?= e($id('built_status')) ?>" name="<?= e($name('built_status')) ?>">
                    <?= select_options((array) $cfg->item('built_statuses'), $v('built_status') !== '' ? $v('built_status') : 'Built') ?>
                </select>
                <div class="form-text js-rate-hint"></div>
                <?= $e['message'] ?>
            </div>
            <div class="col-md-4">
                <?php $e = $err('occupancy_status'); ?>
                <label class="form-label" for="<?= e($id('occupancy_status')) ?>">Occupancy <span class="required">*</span></label>
                <select class="form-select<?= $e['class'] ?>" id="<?= e($id('occupancy_status')) ?>" name="<?= e($name('occupancy_status')) ?>">
                    <?= select_options($owner_occupancy, $v('occupancy_status') !== '' ? $v('occupancy_status') : 'Owner Occupied') ?>
                </select>
                <?= $e['message'] ?>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
};
?>
<?php if ( ! empty($form_error)): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= e($form_error) ?></div>
<?php endif; ?>
<?php if (validation_errors() !== ''): ?>
    <div class="alert alert-danger py-2"><i class="fa-solid fa-circle-exclamation me-2"></i>Please correct the highlighted fields.</div>
<?php endif; ?>

<?= form_open($is_new ? 'owners/create' : 'owners/edit/'.$owner['id'], array('novalidate' => 'novalidate', 'id' => 'ownerForm')) ?>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between">
                <span>Owner details</span>
                <span class="text-muted fw-normal small"><?= $is_new ? 'Owner ID is generated automatically' : 'Owner ID: <strong>'.e($owner['owner_code']).'</strong>' ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="owner_name">Owner Name <span class="required">*</span></label>
                        <input type="text" class="form-control<?= $f['owner_name']['class'] ?>" id="owner_name" name="owner_name" maxlength="120" required value="<?= e($val('owner_name')) ?>">
                        <?= $f['owner_name']['message'] ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="co_owner_name">Co-owner Name</label>
                        <input type="text" class="form-control<?= $f['co_owner_name']['class'] ?>" id="co_owner_name" name="co_owner_name" maxlength="120" value="<?= e($val('co_owner_name')) ?>">
                        <?= $f['co_owner_name']['message'] ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="mobile">Mobile Number <span class="required">*</span></label>
                        <input type="tel" class="form-control<?= $f['mobile']['class'] ?>" id="mobile" name="mobile" maxlength="15" required value="<?= e($val('mobile')) ?>" inputmode="tel">
                        <?= $f['mobile']['message'] ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="whatsapp_no">WhatsApp Number</label>
                        <input type="tel" class="form-control<?= $f['whatsapp_no']['class'] ?>" id="whatsapp_no" name="whatsapp_no" maxlength="15" value="<?= e($val('whatsapp_no')) ?>" inputmode="tel">
                        <div class="form-check form-text mt-1">
                            <input class="form-check-input" type="checkbox" id="sameAsMobile">
                            <label class="form-check-label" for="sameAsMobile">Same as mobile</label>
                        </div>
                        <?= $f['whatsapp_no']['message'] ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="email">E-mail</label>
                        <input type="email" class="form-control<?= $f['email']['class'] ?>" id="email" name="email" maxlength="150" value="<?= e($val('email')) ?>">
                        <?= $f['email']['message'] ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="permanent_address">Permanent Address</label>
                        <textarea class="form-control<?= $f['permanent_address']['class'] ?>" id="permanent_address" name="permanent_address" rows="2" maxlength="255"><?= e($val('permanent_address')) ?></textarea>
                        <?= $f['permanent_address']['message'] ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="residential_address">Residential Address</label>
                        <textarea class="form-control<?= $f['residential_address']['class'] ?>" id="residential_address" name="residential_address" rows="2" maxlength="255"><?= e($val('residential_address')) ?></textarea>
                        <?= $f['residential_address']['message'] ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="owner_type">Owner Type <span class="required">*</span></label>
                        <select class="form-select<?= $f['owner_type']['class'] ?>" id="owner_type" name="owner_type" required>
                            <?= select_options((array) $cfg->item('owner_types'), $val('owner_type')) ?>
                        </select>
                        <?= $f['owner_type']['message'] ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="joining_date">Date of Joining</label>
                        <input type="date" class="form-control<?= $f['joining_date']['class'] ?>" id="joining_date" name="joining_date" value="<?= e($val('joining_date')) ?>">
                        <?= $f['joining_date']['message'] ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="status">Status <span class="required">*</span></label>
                        <select class="form-select<?= $f['status']['class'] ?>" id="status" name="status" required>
                            <?= select_options(array('Active', 'Inactive'), $val('status')) ?>
                        </select>
                        <?= $f['status']['message'] ?>
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
        <div class="card mb-3">
            <div class="card-header">Maintenance</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label" for="maintenance_start_date">Maintenance Start Date</label>
                    <input type="date" class="form-control<?= $f['maintenance_start_date']['class'] ?>" id="maintenance_start_date" name="maintenance_start_date" value="<?= e($val('maintenance_start_date')) ?>">
                    <div class="form-text">No bills are generated for months before this date.</div>
                    <?= $f['maintenance_start_date']['message'] ?>
                </div>
                <div class="small text-muted mb-2">Each plot is billed every month at the current rate for its type:</div>
                <ul class="list-unstyled small mb-0" id="currentRates"
                    data-constructed="<?= e(isset($rates['Constructed']) ? money($rates['Constructed']['amount']) : '') ?>"
                    data-vacant="<?= e(isset($rates['Vacant Plot']) ? money($rates['Vacant Plot']['amount']) : '') ?>">
                    <?php foreach ($rates as $category => $rate): ?>
                        <li class="d-flex justify-content-between border-bottom py-1">
                            <span><?= e($category) ?></span>
                            <strong><?= $rate !== NULL ? e(money($rate['amount'])) : '<span class="text-danger">Not set</span>' ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (can('maintenance.view')): ?>
                    <a href="<?= e(site_url('rates')) ?>" class="small d-inline-block mt-2">View rate history</a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ( ! $is_new): ?>
        <div class="card mb-3">
            <div class="card-header">Plots (<?= count($houses) ?>)</div>
            <div class="card-body">
                <?php if (empty($houses)): ?>
                    <p class="text-muted small mb-2">No plot assigned. Owners without a plot are not billed.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush mb-2">
                    <?php foreach ($houses as $h): ?>
                        <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                            <span><strong>Plot <?= e($h['plot_no']) ?></strong><?= $h['house_no'] ? ' &middot; '.e($h['house_no']) : '' ?><br><?= status_badge($h['occupancy_status']) ?></span>
                            <?php if (can('houses.edit')): ?>
                                <a href="<?= e(site_url('houses/edit/'.$h['id'])) ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <a href="<?= e(site_url('owners/view/'.$owner['id'].'#houses')) ?>" class="small">Assign more plots from the owner profile</a>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($is_new): ?>
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Plots owned <span class="text-muted fw-normal small">(add one row per plot)</span></span>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addPlotRow"><i class="fa-solid fa-plus me-1"></i>Add another plot</button>
            </div>
            <div class="card-body">
                <div id="plotRows" data-max="<?= (int) Owners::MAX_PLOTS_PER_FORM ?>">
                    <?php foreach ($plot_rows as $i => $row): ?>
                        <?= $plot_row((int) $i, $row) ?>
                    <?php endforeach; ?>
                </div>
                <p class="text-muted small mb-0 js-no-plots<?= empty($plot_rows) ? '' : ' d-none' ?>">
                    No plots added. The owner can be saved now and plots assigned later; owners without a plot are not billed.
                </p>
                <template id="plotRowTemplate"><?= $plot_row('__INDEX__', array()) ?></template>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Owner</button>
    <a href="<?= e(site_url($is_new ? 'owners' : 'owners/view/'.$owner['id'])) ?>" class="btn btn-light">Cancel</a>
</div>
<?= form_close() ?>
