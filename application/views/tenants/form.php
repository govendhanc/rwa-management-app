<?php defined('BASEPATH') OR exit('No direct script access allowed');
$is_new = $tenant['id'] === NULL;
$names = array('house_id', 'tenant_name', 'mobile', 'whatsapp_no', 'email', 'permanent_address', 'emergency_contact_name',
    'emergency_contact_phone', 'emergency_contact_relation', 'id_proof_type', 'id_proof_number', 'agreement_start',
    'agreement_end', 'move_in_date', 'remarks');
$f = array();
foreach ($names as $n)
{
    $f[$n] = field_error($n);
}
$val = function (string $field) use ($tenant) {
    return set_value($field, isset($tenant[$field]) ? (string) $tenant[$field] : '');
};
$input = function (string $name, string $label, string $type = 'text', array $attrs = array(), bool $required = FALSE) use ($f, $val) {
    $extra = '';
    foreach ($attrs as $k => $v)
    {
        $extra .= ' '.$k.'="'.e($v).'"';
    }
    return '<label class="form-label" for="'.e($name).'">'.e($label).($required ? ' <span class="required">*</span>' : '').'</label>'
        .'<input type="'.e($type).'" class="form-control'.$f[$name]['class'].'" id="'.e($name).'" name="'.e($name).'" value="'.e($val($name)).'"'.$extra.($required ? ' required' : '').'>'
        .$f[$name]['message'];
};
$max_mb = round((int) $this->config->item('upload_tenant_max_kb') / 1024, 1);
?>
<?php if ( ! empty($form_error)): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= e($form_error) ?></div>
<?php endif; ?>
<?php if (validation_errors() !== ''): ?>
    <div class="alert alert-danger py-2"><i class="fa-solid fa-circle-exclamation me-2"></i>Please correct the highlighted fields.</div>
<?php endif; ?>

<?= form_open_multipart($is_new ? 'tenants/create' : 'tenants/edit/'.$tenant['id'], array('novalidate' => 'novalidate')) ?>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header">Tenant &amp; house</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="house_id">House / Plot <span class="required">*</span></label>
                        <?php if ($is_new): ?>
                            <select class="form-select<?= $f['house_id']['class'] ?>" id="house_id" name="house_id" required>
                                <?= select_options($house_options, $val('house_id'), empty($house_options) ? 'No houses available (all let out or without owner)' : 'Select house') ?>
                            </select>
                            <div class="form-text">Only active plots that have an owner and no current tenant are listed. Maintenance stays billed to the owner.</div>
                            <?= $f['house_id']['message'] ?>
                        <?php else: ?>
                            <input type="text" class="form-control" id="house_id" value="Plot <?= e($tenant['plot_no']) ?><?= $tenant['house_no'] ? ' - '.e($tenant['house_no']) : '' ?> (Owner: <?= e($tenant['owner_name']) ?>)" disabled>
                            <div class="form-text">To move a tenant to another house, record a move-out and add a new tenancy.</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6"><?= $input('tenant_name', 'Tenant Name', 'text', array('maxlength' => '120'), TRUE) ?></div>
                    <div class="col-md-6"><?= $input('email', 'E-mail', 'email', array('maxlength' => '150')) ?></div>
                    <div class="col-md-6"><?= $input('mobile', 'Mobile Number', 'tel', array('maxlength' => '15', 'inputmode' => 'tel'), TRUE) ?></div>
                    <div class="col-md-6"><?= $input('whatsapp_no', 'WhatsApp Number', 'tel', array('maxlength' => '15', 'inputmode' => 'tel', 'placeholder' => 'Same as mobile if blank')) ?></div>
                    <div class="col-12">
                        <label class="form-label" for="permanent_address">Permanent Address</label>
                        <textarea class="form-control<?= $f['permanent_address']['class'] ?>" id="permanent_address" name="permanent_address" rows="2" maxlength="255"><?= e($val('permanent_address')) ?></textarea>
                        <?= $f['permanent_address']['message'] ?>
                    </div>
                    <div class="col-md-4"><?= $input('move_in_date', 'Move-in Date', 'date', array(), TRUE) ?></div>
                    <div class="col-12">
                        <label class="form-label" for="remarks">Remarks</label>
                        <textarea class="form-control<?= $f['remarks']['class'] ?>" id="remarks" name="remarks" rows="2" maxlength="500"><?= e($val('remarks')) ?></textarea>
                        <?= $f['remarks']['message'] ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">Emergency contact</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-5"><?= $input('emergency_contact_name', 'Name', 'text', array('maxlength' => '120')) ?></div>
                    <div class="col-md-4"><?= $input('emergency_contact_phone', 'Phone', 'tel', array('maxlength' => '15', 'inputmode' => 'tel')) ?></div>
                    <div class="col-md-3"><?= $input('emergency_contact_relation', 'Relationship', 'text', array('maxlength' => '50', 'placeholder' => 'e.g. Father')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header">Rental agreement</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6"><?= $input('agreement_start', 'Start', 'date') ?></div>
                    <div class="col-6"><?= $input('agreement_end', 'End', 'date') ?></div>
                    <div class="col-12">
                        <label class="form-label" for="agreement_file">Agreement copy</label>
                        <input type="file" class="form-control" id="agreement_file" name="agreement_file" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text">PDF, JPG or PNG, max <?= e((string) $max_mb) ?> MB.
                            <?php if ( ! empty($tenant['agreement_file'])): ?>Current: <a href="<?= e(site_url('tenants/document/'.$tenant['id'].'/agreement')) ?>" target="_blank" rel="noopener"><?= e($tenant['agreement_file_name']) ?></a> (choose a file to replace)<?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (can('tenants.id_proof')): ?>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between"><span>ID proof</span><span class="badge text-bg-warning"><i class="fa-solid fa-lock me-1"></i>Restricted</span></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label" for="id_proof_type">ID Proof Type</label>
                        <select class="form-select<?= $f['id_proof_type']['class'] ?>" id="id_proof_type" name="id_proof_type">
                            <?= select_options((array) $this->config->item('id_proof_types'), $val('id_proof_type'), '- None -') ?>
                        </select>
                        <?= $f['id_proof_type']['message'] ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="id_proof_number" id="idNumberLabel">ID Number</label>
                        <input type="text" class="form-control<?= $f['id_proof_number']['class'] ?>" id="id_proof_number" name="id_proof_number" maxlength="30" value="<?= e($val('id_proof_number')) ?>" autocomplete="off">
                        <div class="form-text" id="idNumberHint">For Aadhaar, enter only the last 4 digits &mdash; the full number is never stored.</div>
                        <?= $f['id_proof_number']['message'] ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="id_proof_file">ID proof copy</label>
                        <input type="file" class="form-control" id="id_proof_file" name="id_proof_file" accept=".pdf,.jpg,.jpeg,.png">
                        <div class="form-text">Optional. Stored outside the website and visible only to Admin / Super Admin.
                            <?php if ( ! empty($tenant['id_proof_file'])): ?>Current: <a href="<?= e(site_url('tenants/document/'.$tenant['id'].'/id-proof')) ?>" target="_blank" rel="noopener"><?= e($tenant['id_proof_file_name']) ?></a><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> <?= $is_new ? 'Save Tenant (Move In)' : 'Save Changes' ?></button>
    <a href="<?= e(site_url($is_new ? 'tenants' : 'tenants/view/'.$tenant['id'])) ?>" class="btn btn-light">Cancel</a>
</div>
<?= form_close() ?>
