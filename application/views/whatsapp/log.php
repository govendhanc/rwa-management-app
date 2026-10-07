<?php defined('BASEPATH') OR exit('No direct script access allowed');
$labels = array('payment_receipt' => 'Payment receipt', 'maintenance_reminder' => 'Maintenance reminder');
?>
<div class="page-actions">
    <form method="get" action="<?= e(site_url('whatsapp/log')) ?>" class="d-flex flex-wrap gap-2 align-items-center" data-ajax="false">
        <label class="small text-muted" for="from">From</label>
        <input type="date" class="form-control form-control-sm w-auto" id="from" name="from" value="<?= e($filters['from']) ?>">
        <label class="small text-muted" for="to">To</label>
        <input type="date" class="form-control form-control-sm w-auto" id="to" name="to" value="<?= e($filters['to']) ?>">
        <select class="form-select form-select-sm w-auto" name="template" aria-label="Message type">
            <?= select_options(array('' => 'All messages') + $labels, $filters['template']) ?>
        </select>
        <select class="form-select form-select-sm w-auto" name="status" aria-label="Status">
            <?= select_options(array('' => 'All statuses', 'Prepared' => 'Prepared (Click-to-Chat)', 'Sent' => 'Sent (Cloud API)', 'Failed' => 'Failed'), $filters['status']) ?>
        </select>
        <button type="submit" class="btn btn-sm btn-primary" data-no-loading><i class="fa-solid fa-filter me-1"></i>Apply</button>
    </form>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover w-100" id="waLogTable">
            <thead>
                <tr><th>Date</th><th>Owner</th><th>Number</th><th>Type</th><th>Receipt</th><th>Channel</th><th>Status</th><th>By</th><th>Message</th></tr>
            </thead>
            <tbody>
            <?php foreach ($messages as $m): ?>
                <tr>
                    <td data-order="<?= e($m['created_at']) ?>" class="text-nowrap"><?= e(fmt_datetime($m['created_at'])) ?></td>
                    <td><a href="<?= e(site_url('owners/view/'.$m['owner_id'])) ?>" class="text-decoration-none"><?= e($m['owner_name']) ?></a><div class="small text-muted"><?= e($m['owner_code']) ?></div></td>
                    <td class="text-nowrap">+<?= e($m['recipient_number']) ?></td>
                    <td><?= e($labels[$m['template_key']] ?? $m['template_key']) ?></td>
                    <td><?= $m['receipt_id'] ? '<a href="'.e(site_url('receipts/view/'.$m['receipt_id'])).'">'.e($m['receipt_no']).'</a>' : '' ?></td>
                    <td><?= $m['channel'] === 'cloud_api' ? 'Cloud API' : 'Click-to-Chat' ?></td>
                    <td><?= status_badge($m['status']) ?><?php if ($m['error_message']): ?><div class="small text-danger"><?= e($m['error_message']) ?></div><?php endif; ?></td>
                    <td><?= e($m['sent_by_name']) ?></td>
                    <td><button type="button" class="btn btn-sm btn-outline-secondary js-show-message" data-message="<?= e($m['message_body']) ?>"><i class="fa-regular fa-message"></i></button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="messageModal" tabindex="-1" aria-labelledby="messageModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="messageModalTitle">Message</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><div class="wa-bubble" id="messageModalBody"></div></div>
        </div>
    </div>
</div>
