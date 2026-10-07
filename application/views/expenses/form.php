<?php defined('BASEPATH') OR exit('No direct script access allowed');
$is_new = $expense['id'] === NULL;
$f = array();
foreach (array('expense_date', 'category_id', 'description', 'amount', 'payment_mode', 'vendor', 'bill_number', 'remarks') as $n)
{
    $f[$n] = field_error($n);
}
$val = function (string $k) use ($expense) { return set_value($k, (string) $expense[$k]); };
?>
<?php if ( ! empty($form_error)): ?><div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= e($form_error) ?></div><?php endif; ?>
<div class="card" style="max-width: 900px;">
    <div class="card-header"><?= $is_new ? 'New expense (ID generated automatically)' : 'Expense '.e($expense['expense_code']) ?></div>
    <div class="card-body">
        <?= form_open_multipart($is_new ? 'expenses/create' : 'expenses/edit/'.$expense['id'], array('novalidate' => 'novalidate')) ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="expense_date">Expense Date <span class="required">*</span></label>
                <input type="date" class="form-control<?= $f['expense_date']['class'] ?>" id="expense_date" name="expense_date" max="<?= e(date('Y-m-d')) ?>" value="<?= e($val('expense_date')) ?>" required>
                <?= $f['expense_date']['message'] ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="category_id">Category <span class="required">*</span></label>
                <select class="form-select<?= $f['category_id']['class'] ?>" id="category_id" name="category_id" required>
                    <?= select_options($categories, $val('category_id'), 'Select category') ?>
                </select>
                <?= $f['category_id']['message'] ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="amount">Amount <span class="required">*</span></label>
                <div class="input-group has-validation"><span class="input-group-text"><?= e(currency_symbol()) ?></span>
                    <input type="text" class="form-control<?= $f['amount']['class'] ?>" id="amount" name="amount" inputmode="decimal" value="<?= e($val('amount')) ?>" required>
                    <?= $f['amount']['message'] ?></div>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description <span class="required">*</span></label>
                <input type="text" class="form-control<?= $f['description']['class'] ?>" id="description" name="description" maxlength="255" value="<?= e($val('description')) ?>" required>
                <?= $f['description']['message'] ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="payment_mode">Payment Mode <span class="required">*</span></label>
                <select class="form-select<?= $f['payment_mode']['class'] ?>" id="payment_mode" name="payment_mode"><?= select_options((array) $this->config->item('payment_modes'), $val('payment_mode')) ?></select>
                <?= $f['payment_mode']['message'] ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="vendor">Vendor / Paid To</label>
                <input type="text" class="form-control<?= $f['vendor']['class'] ?>" id="vendor" name="vendor" maxlength="120" value="<?= e($val('vendor')) ?>">
                <?= $f['vendor']['message'] ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="bill_number">Bill / Invoice No</label>
                <input type="text" class="form-control<?= $f['bill_number']['class'] ?>" id="bill_number" name="bill_number" maxlength="60" value="<?= e($val('bill_number')) ?>">
                <?= $f['bill_number']['message'] ?>
            </div>
            <div class="col-12">
                <label class="form-label" for="remarks">Remarks</label>
                <textarea class="form-control<?= $f['remarks']['class'] ?>" id="remarks" name="remarks" rows="2" maxlength="500"><?= e($val('remarks')) ?></textarea>
                <?= $f['remarks']['message'] ?>
            </div>
            <div class="col-md-8">
                <label class="form-label" for="attachment">Bill / invoice attachment</label>
                <input type="file" class="form-control" id="attachment" name="attachment" accept=".pdf,.jpg,.jpeg,.png">
                <div class="form-text">PDF, JPG or PNG, max <?= e((string) $max_mb) ?> MB. Stored securely (not publicly accessible).</div>
                <?php if ( ! empty($expense['attachment_path'])): ?>
                    <div class="mt-2 small">Current: <a href="<?= e(site_url('expenses/attachment/'.$expense['id'])) ?>" target="_blank" rel="noopener"><i class="fa-solid fa-paperclip me-1"></i><?= e($expense['attachment_original_name']) ?></a>
                        <div class="form-check d-inline-block ms-3"><input class="form-check-input" type="checkbox" value="1" id="remove_attachment" name="remove_attachment"><label class="form-check-label" for="remove_attachment">Remove</label></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save Expense</button>
            <a href="<?= e(site_url('expenses')) ?>" class="btn btn-light">Cancel</a>
        </div>
        <?= form_close() ?>
    </div>
</div>
