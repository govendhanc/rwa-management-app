<?php defined('BASEPATH') OR exit('No direct script access allowed');
$p = $payment;
$active = $p['status'] === 'Active';
$detail = function (string $label, string $value) {
    return '<div class="col-sm-6 col-lg-4"><div class="small text-muted">'.e($label).'</div><div class="fw-semibold text-break">'.($value !== '' ? e($value) : '<span class="text-muted fw-normal">-</span>').'</div></div>';
};
?>
<div class="page-actions">
    <div>
        <div class="h5 mb-0"><?= e(money($p['amount'])) ?> from <a href="<?= e(site_url('owners/view/'.$p['owner_id'])) ?>"><?= e($p['owner_name']) ?></a> <?= status_badge($p['status']) ?></div>
        <div class="text-muted small"><?= e($p['receipt_no']) ?> &middot; <?= e(fmt_date($p['payment_date'])) ?> &middot; <?= e($p['payment_mode']) ?></div>
    </div>
    <div class="d-flex gap-2">
        <?php if ($p['receipt_id']): ?>
            <a href="<?= e(site_url('receipts/view/'.$p['receipt_id'])) ?>" class="btn btn-outline-primary"><i class="fa-solid fa-receipt me-1"></i> Receipt</a>
        <?php endif; ?>
        <?php if ($active && can('payments.cancel')): ?>
            <button type="button" class="btn btn-outline-danger js-cancel-payment" data-url="<?= e(site_url('payments/cancel/'.$p['id'])) ?>" data-receipt="<?= e($p['receipt_no']) ?>">
                <i class="fa-solid fa-ban me-1"></i> Cancel Payment
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if ( ! $active): ?>
    <div class="alert alert-danger"><i class="fa-solid fa-ban me-2"></i>This payment was <strong><?= e(strtolower($p['status'])) ?></strong> on <?= e(fmt_datetime($p['cancelled_at'])) ?><?= $p['cancelled_by_name'] ? ' by '.e($p['cancelled_by_name']) : '' ?>: <?= e($p['cancel_reason']) ?>. The original record is kept for audit.</div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header">Payment</div>
    <div class="card-body">
        <div class="row g-3">
            <?= $detail('Owner', $p['owner_code'].' - '.$p['owner_name']) ?>
            <?= $detail('Payment date', fmt_date($p['payment_date'])) ?>
            <?= $detail('Amount', money($p['amount'])) ?>
            <?= $detail('Applied to bills', money($p['allocated_amount'])) ?>
            <?= $detail('Advance credit remaining', $active ? money($p['unallocated_amount']) : '-') ?>
            <?= $detail('Mode / reference', $p['payment_mode'].($p['transaction_ref'] ? ' - '.$p['transaction_ref'] : '')) ?>
            <?= $detail('Received by', (string) $p['received_by_name']) ?>
            <?= $detail('Recorded at', fmt_datetime($p['created_at'])) ?>
            <?= $detail('Remarks', (string) $p['remarks']) ?>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">Bills paid by this payment</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr><th>Month</th><th>Plot</th><th>Source</th><th class="text-end">Applied</th><th>Applied on</th><th>Bill status now</th><th>Line</th></tr></thead>
            <tbody>
            <?php if (empty($lines)): ?><tr><td colspan="7" class="text-muted text-center py-3">Not applied to any bill (advance credit)</td></tr><?php endif; ?>
            <?php foreach ($lines as $l): ?>
                <tr class="<?= $l['status'] !== 'Active' ? 'text-muted' : '' ?>">
                    <td><a href="<?= e(site_url('maintenance/view/'.$l['maintenance_id'])) ?>"><?= e(period_label((int) $l['billing_year'], (int) $l['billing_month'])) ?></a></td>
                    <td>Plot <?= e($l['plot_no']) ?><?= $l['house_no'] ? ' - '.e($l['house_no']) : '' ?></td>
                    <td><?= e($l['allocation_source']) ?></td>
                    <td class="text-end amount"><?= e(money($l['amount'])) ?></td>
                    <td><?= e(fmt_date(substr($l['allocated_at'], 0, 10))) ?></td>
                    <td><?= status_badge($l['bill_status']) ?></td>
                    <td><?= status_badge($l['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= form_open('', array('id' => 'cancelPaymentForm', 'class' => 'd-none')) ?>
    <input type="hidden" name="reason" id="cancelPaymentReason">
<?= form_close() ?>
