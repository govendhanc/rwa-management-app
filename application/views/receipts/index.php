<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="page-actions">
    <form method="get" action="<?= e(site_url('receipts')) ?>" class="d-flex flex-wrap gap-2 align-items-center" data-ajax="false">
        <label class="small text-muted" for="from">From</label>
        <input type="date" class="form-control form-control-sm w-auto" id="from" name="from" value="<?= e($filters['from']) ?>">
        <label class="small text-muted" for="to">To</label>
        <input type="date" class="form-control form-control-sm w-auto" id="to" name="to" value="<?= e($filters['to']) ?>">
        <select class="form-select form-select-sm w-auto" name="status" aria-label="Status">
            <?= select_options(array('' => 'All receipts', 'Active' => 'Active', 'Cancelled' => 'Cancelled'), $filters['status']) ?>
        </select>
        <button type="submit" class="btn btn-sm btn-primary" data-no-loading><i class="fa-solid fa-filter me-1"></i>Apply</button>
    </form>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover w-100" id="receiptsTable">
            <thead>
                <tr>
                    <th>Receipt No</th>
                    <th>Date</th>
                    <th>Owner</th>
                    <th>Plot</th>
                    <th>Months</th>
                    <th class="text-end">Amount</th>
                    <th>Mode</th>
                    <th class="text-end">Balance after</th>
                    <th>Status</th>
                    <th class="no-export text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($receipts as $r): ?>
                <tr class="<?= $r['status'] !== 'Active' ? 'text-muted' : '' ?>">
                    <td class="text-nowrap fw-semibold"><a href="<?= e(site_url('receipts/view/'.$r['id'])) ?>"><?= e($r['receipt_no']) ?></a></td>
                    <td data-order="<?= e($r['receipt_date']) ?>"><?= e(fmt_date($r['receipt_date'])) ?></td>
                    <td><?= e($r['owner_name']) ?><div class="small text-muted"><?= e($r['owner_code']) ?></div></td>
                    <td><?= e($r['plot_no']) ?></td>
                    <td><?= e($r['period_label']) ?></td>
                    <td class="text-end amount"><?= e(money($r['amount_received'])) ?></td>
                    <td><?= e($r['payment_mode']) ?></td>
                    <td class="text-end amount"><?= e(money($r['balance_after'])) ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                    <td class="table-actions text-end text-nowrap">
                        <a href="<?= e(site_url('receipts/view/'.$r['id'])) ?>" class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="View"><i class="fa-solid fa-eye"></i></a>
                        <?php if (can('receipts.download')): ?>
                            <a href="<?= e(site_url('receipts/pdf/'.$r['id'].'/a4')) ?>" class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="Download PDF"><i class="fa-solid fa-file-pdf"></i></a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
