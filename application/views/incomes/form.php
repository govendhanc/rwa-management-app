<?php defined('BASEPATH') OR exit('No direct script access allowed');
$is_new = $income['id'] === NULL;
$f = array();
foreach (array('income_date', 'source', 'description', 'amount', 'payment_mode', 'reference', 'remarks') as $n)
{
    $f[$n] = field_error($n);
}
$val = function (string $k) use ($income) { return set_value($k, (string) $income[$k]); };
?>
<?php if ( ! empty($form_error)): ?><div class="alert alert-danger"><i class="fa-solid fa-circle-exclamation me-2"></i><?= e($form_error) ?></div><?php endif; ?>
<div class="card" style="max-width: 900px;">
    <div class="card-header"><?= $is_new ? 'New income entry (ID generated automatically)' : 'Income '.e($income['income_code']) ?></div>
    <div class="card-body">
        <?= form_open($is_new ? 'incomes/create' : 'incomes/edit/'.$income['id'], array('novalidate' => 'novalidate')) ?>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="income_date">Date <span class="required">*</span></label>
                <input type="date" class="form-control<?= $f['income_date']['class'] ?>" id="income_date" name="income_date" max="<?= e(date('Y-m-d')) ?>" value="<?= e($val('income_date')) ?>" required>
                <?= $f['income_date']['message'] ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="source">Source <span class="required">*</span></label>
                <input type="text" class="form-control<?= $f['source']['class'] ?>" id="source" name="source" list="sourceList" maxlength="80" value="<?= e($val('source')) ?>" required>
                <datalist id="sourceList"><?php foreach ($sources as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist>
                <?= $f['source']['message'] ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="amount">Amount <span class="required">*</span></label>
                <div class="input-group has-validation"><span class="input-group-text"><?= e(currency_symbol()) ?></span>
                    <input type="text" class="form-control<?= $f['amount']['class'] ?>" id="amount" name="amount" inputmode="decimal" value="<?= e($val('amount')) ?>" required>
                    <?= $f['amount']['message'] ?></div>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description</label>
                <input type="text" class="form-control<?= $f['description']['class'] ?>" id="description" name="description" maxlength="255" value="<?= e($val('description')) ?>">
                <?= $f['description']['message'] ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="payment_mode">Received By <span class="required">*</span></label>
                <select class="form-select" id="payment_mode" name="payment_mode"><?= select_options((array) $this->config->item('payment_modes'), $val('payment_mode')) ?></select>
            </div>
            <div class="col-md-8">
                <label class="form-label" for="reference">Reference</label>
                <input type="text" class="form-control<?= $f['reference']['class'] ?>" id="reference" name="reference" maxlength="100" value="<?= e($val('reference')) ?>">
                <?= $f['reference']['message'] ?>
            </div>
            <div class="col-12">
                <label class="form-label" for="remarks">Remarks</label>
                <textarea class="form-control<?= $f['remarks']['class'] ?>" id="remarks" name="remarks" rows="2" maxlength="500"><?= e($val('remarks')) ?></textarea>
                <?= $f['remarks']['message'] ?>
            </div>
        </div>
        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i> Save</button>
            <a href="<?= e(site_url('incomes')) ?>" class="btn btn-light">Cancel</a>
        </div>
        <?= form_close() ?>
    </div>
</div>
