<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-actions">
    <form method="get" action="<?= e(site_url('payments')) ?>" class="d-flex flex-wrap gap-2 align-items-center" data-ajax="false">
        <label class="small text-muted" for="from">From</label>
        <input type="date" class="form-control form-control-sm w-auto" id="from" name="from" value="<?= e($filters['from']) ?>">
        <label class="small text-muted" for="to">To</label>
        <input type="date" class="form-control form-control-sm w-auto" id="to" name="to" value="<?= e($filters['to']) ?>">
        <select class="form-select form-select-sm w-auto" name="mode" aria-label="Payment mode">
            <option value="">All modes</option>
            <?= select_options((array) $this->config->item('payment_modes'), $filters['mode']) ?>
        </select>
        <select class="form-select form-select-sm w-auto" name="status" aria-label="Status">
            <?= select_options(array('' => 'All statuses', 'Active' => 'Active', 'Cancelled' => 'Cancelled', 'Reversed' => 'Reversed'), $filters['status']) ?>
        </select>
        <button type="submit" class="btn btn-sm btn-primary" data-no-loading><i class="fa-solid fa-filter me-1"></i>Apply</button>
    </form>
    <?php if (can('payments.create')): ?>
        <a href="<?= e(site_url('payments/collect')) ?>" class="btn btn-success"><i class="fa-solid fa-indian-rupee-sign me-1"></i> Collect Payment</a>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span><?= count($payments) ?> payment(s), <?= e(fmt_date($filters['from'])) ?> to <?= e(fmt_date($filters['to'])) ?></span>
        <span>Active total: <strong class="amount"><?= e(money($total)) ?></strong></span>
    </div>
    <div class="card-body">
        <table class="table table-hover w-100" id="paymentsTable">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Receipt</th>
                    <th>Owner</th>
                    <th>Plot</th>
                    <th>Months</th>
                    <th class="text-end">Amount</th>
                    <th>Mode</th>
                    <th>Reference</th>
                    <th>Received By</th>
                    <th>Status</th>
                    <th class="no-export text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($payments as $p): ?>
                <tr class="<?= $p['status'] !== 'Active' ? 'text-muted' : '' ?>">
                    <td data-order="<?= e($p['payment_date'].sprintf('%08d', $p['id'])) ?>"><?= e(fmt_date($p['payment_date'])) ?></td>
                    <td class="text-nowrap"><?= $p['receipt_id'] ? '<a href="'.e(site_url('receipts/view/'.$p['receipt_id'])).'">'.e($p['receipt_no']).'</a>' : '' ?></td>
                    <td><a href="<?= e(site_url('owners/view/'.$p['owner_id'])) ?>" class="text-decoration-none"><?= e($p['owner_name']) ?></a><div class="small text-muted"><?= e($p['owner_code']) ?></div></td>
                    <td><?= e($p['plot_no']) ?></td>
                    <td><?= e($p['period_label']) ?></td>
                    <td class="text-end amount fw-semibold"><?= e(money($p['amount'])) ?></td>
                    <td><?= e($p['payment_mode']) ?></td>
                    <td><?= e($p['transaction_ref']) ?></td>
                    <td><?= e($p['received_by_name']) ?></td>
                    <td><?= status_badge($p['status']) ?></td>
                    <td class="table-actions text-end"><a href="<?= e(site_url('payments/view/'.$p['id'])) ?>" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="Details"><i class="fa-solid fa-eye"></i></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
