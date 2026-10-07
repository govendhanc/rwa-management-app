<?php defined('BASEPATH') OR exit('No direct script access allowed');
$total = 0;
foreach ($owners as $o)
{
    $total += to_paise_signed($o['advance_credit']);
}
?>
<div class="alert alert-info small"><i class="fa-solid fa-circle-info me-2"></i>Advance credit is money received beyond the dues. It is applied automatically, oldest first, whenever a new month is generated.
    If an owner has unpaid bills and credit at the same time (for example after a waiver was reversed), use <strong>Apply now</strong>.</div>

<div class="card">
    <div class="card-header d-flex justify-content-between"><span><?= count($owners) ?> owner(s) with credit</span><span>Total credit held: <strong><?= e(money(from_paise($total))) ?></strong></span></div>
    <div class="card-body">
        <table class="table table-hover w-100" id="advancesTable">
            <thead><tr><th>Owner</th><th>Plots</th><th>Mobile</th><th class="text-end">Credit</th><th class="text-end">Unpaid bills</th><th>Credit since</th><th class="no-export text-end"></th></tr></thead>
            <tbody>
            <?php foreach ($owners as $o): ?>
                <tr>
                    <td><a href="<?= e(site_url('owners/view/'.$o['owner_id'].'#payments')) ?>" class="fw-semibold text-decoration-none"><?= e($o['owner_name']) ?></a><div class="small text-muted"><?= e($o['owner_code']) ?></div></td>
                    <td><?= e($o['plots']) ?></td>
                    <td><?= e($o['mobile']) ?></td>
                    <td class="text-end amount fw-semibold text-success"><?= e(money($o['advance_credit'])) ?></td>
                    <td class="text-end amount"><?= e(money($o['outstanding'])) ?></td>
                    <td><?= e(fmt_date($o['credit_since'])) ?></td>
                    <td class="text-end">
                        <?php if (can('payments.create') && to_paise_signed($o['outstanding']) > 0): ?>
                            <?= form_open('payments/apply-credit/'.$o['owner_id'], array('class' => 'd-inline')) ?>
                                <button type="submit" class="btn btn-sm btn-outline-primary">Apply now</button>
                            <?= form_close() ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
