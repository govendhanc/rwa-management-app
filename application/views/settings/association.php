<?php defined('BASEPATH') OR exit('No direct script access allowed');
$fields = array('association_name', 'short_name', 'registration_no', 'registration_label', 'address', 'city', 'district', 'state', 'pincode', 'mobile', 'email', 'website',
    'bank_name', 'bank_account_no', 'bank_ifsc', 'bank_branch', 'upi_id', 'default_due_day', 'receipt_prefix', 'receipt_footer_note');
$f = array();
foreach ($fields as $n)
{
    $f[$n] = field_error($n);
}
$val = function (string $k) use ($s, $reg_label) { return set_value($k, $k === 'registration_label' ? (string) $reg_label : (string) ($s[$k] ?? '')); };
$input = function (string $name, string $label, int $col, array $attrs = array(), bool $required = FALSE) use ($f, $val) {
    $extra = '';
    foreach ($attrs as $k => $v) { $extra .= ' '.$k.'="'.e($v).'"'; }
    return '<div class="col-md-'.$col.'"><label class="form-label" for="'.$name.'">'.e($label).($required ? ' <span class="required">*</span>' : '').'</label>'
        .'<input type="text" class="form-control'.$f[$name]['class'].'" id="'.$name.'" name="'.$name.'" value="'.e($val($name)).'"'.$extra.'>'.$f[$name]['message'].'</div>';
};
$logo = logo_url();
?>
<?php if ( ! empty($form_error)): ?><div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= e($form_error) ?></div><?php endif; ?>
<?php if (validation_errors() !== ''): ?><div class="alert alert-danger py-2">Please correct the highlighted fields.</div><?php endif; ?>

<?= form_open_multipart('settings', array('novalidate' => 'novalidate')) ?>
<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-header">Association</div>
            <div class="card-body"><div class="row g-3">
                <?= $input('association_name', 'Association Name', 12, array('maxlength' => '200'), TRUE) ?>
                <?= $input('short_name', 'Short Name (sidebar, signature)', 4, array('maxlength' => '60')) ?>
                <?= $input('registration_label', 'Registration label', 3, array('maxlength' => '30', 'placeholder' => 'Reg. No'), TRUE) ?>
                <?= $input('registration_no', 'Registration Number', 5, array('maxlength' => '60')) ?>
                <?= $input('address', 'Address', 12, array('maxlength' => '255')) ?>
                <?= $input('city', 'City', 3, array('maxlength' => '80')) ?>
                <?= $input('district', 'District', 3, array('maxlength' => '80')) ?>
                <?= $input('state', 'State', 3, array('maxlength' => '80')) ?>
                <?= $input('pincode', 'PIN Code', 3, array('maxlength' => '6', 'inputmode' => 'numeric')) ?>
                <?= $input('mobile', 'Mobile', 4, array('maxlength' => '15', 'inputmode' => 'tel')) ?>
                <?= $input('email', 'Email', 4, array('maxlength' => '150')) ?>
                <?= $input('website', 'Website', 4, array('maxlength' => '150', 'placeholder' => 'https://')) ?>
            </div></div>
        </div>
        <div class="card mb-3">
            <div class="card-header">Bank &amp; UPI (printed on receipts when filled)</div>
            <div class="card-body"><div class="row g-3">
                <?= $input('bank_name', 'Bank Name', 6, array('maxlength' => '100')) ?>
                <?= $input('bank_branch', 'Branch', 6, array('maxlength' => '100')) ?>
                <?= $input('bank_account_no', 'Account Number', 4, array('maxlength' => '20', 'inputmode' => 'numeric')) ?>
                <?= $input('bank_ifsc', 'IFSC', 4, array('maxlength' => '11')) ?>
                <?= $input('upi_id', 'UPI ID', 4, array('maxlength' => '100', 'placeholder' => 'name@bank')) ?>
            </div></div>
        </div>
        <div class="card mb-3">
            <div class="card-header">Maintenance &amp; receipts</div>
            <div class="card-body"><div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="default_due_day">Default due day of month <span class="required">*</span></label>
                    <input type="number" class="form-control<?= $f['default_due_day']['class'] ?>" id="default_due_day" name="default_due_day" min="1" max="28" value="<?= e($val('default_due_day')) ?>">
                    <?= $f['default_due_day']['message'] ?>
                </div>
                <?= $input('receipt_prefix', 'Receipt Prefix', 4, array('maxlength' => '6'), TRUE) ?>
                <div class="col-md-4 small text-muted pt-md-4">Next receipt looks like <strong><?= e(($s['receipt_prefix'] ?? 'REC').'-'.date('Y').'-000123') ?></strong>. Numbering continues when the prefix changes.</div>
                <?= $input('receipt_footer_note', 'Receipt footer note', 12, array('maxlength' => '255')) ?>
            </div></div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header">Logo</div>
            <div class="card-body text-center">
                <?php if ($logo !== ''): ?>
                    <img src="<?= e($logo) ?>" alt="Current logo" class="img-fluid mb-3" style="max-height: 140px;">
                    <div class="form-check text-start mb-3"><input class="form-check-input" type="checkbox" value="1" id="remove_logo" name="remove_logo"><label class="form-check-label" for="remove_logo">Remove logo</label></div>
                <?php else: ?>
                    <p class="text-muted">No logo uploaded.</p>
                <?php endif; ?>
                <input type="file" class="form-control" id="logo" name="logo" accept=".jpg,.jpeg,.png" aria-label="Upload new logo">
                <div class="form-text text-start">JPG or PNG, max <?= (int) $this->config->item('upload_logo_max_kb') ?> KB. Shown in the sidebar, login page, receipts and statements.
                    <?php if ( ! $gd_loaded): ?><br><strong>Tip:</strong> use JPG &mdash; the server's PHP GD extension is not enabled, so PNG logos cannot be drawn in PDFs.<?php endif; ?></div>
            </div>
        </div>
    </div>
</div>
<button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Settings</button>
<?= form_close() ?>
