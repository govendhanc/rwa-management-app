<?php defined('BASEPATH') OR exit('No direct script access allowed');
$r = $receipt;
?>
<div class="alert alert-success d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3">
        <i class="fa-solid fa-circle-check fa-2x"></i>
        <div>
            <div class="fw-bold fs-5">Payment recorded successfully.</div>
            <div>Receipt <strong><?= e($r['receipt_no']) ?></strong> &middot; <?= e($r['owner_name']) ?> &middot; <?= e(money($r['amount_received'])) ?> by <?= e($r['payment_mode']) ?>
                &middot; Balance <?= e(money($r['balance_after'])) ?><?= to_paise_signed($r['advance_amount']) > 0 ? ' &middot; Advance credit '.e(money($r['advance_amount'])) : '' ?></div>
        </div>
    </div>
    <a href="<?= e(site_url('payments/collect')) ?>" class="btn btn-outline-success"><i class="fa-solid fa-plus me-1"></i> Collect another payment</a>
</div>

<?php $this->load->view('receipts/_actions', array('receipt' => $r)); ?>

<div class="card">
    <div class="card-body receipt-screen"><?= $receipt_html ?></div>
</div>
