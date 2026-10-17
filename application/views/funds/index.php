<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Finance -> Bank & Cash. Amounts from Fund_model are in paise.
 *
 * @var array<string, mixed>              $position
 * @var array<string, mixed>              $ledger
 * @var array<int, array<string, mixed>>  $transfers
 * @var array<string, string>             $directions
 * @var array<string, mixed>|null         $opening
 * @var string                            $request_token
 */
$p = $position;
$l = $ledger;
$rs = function (int $paise): string { return money(from_paise($paise)); };
$cell = function (int $paise) use ($rs): string { return $paise !== 0 ? e($rs($paise)) : ''; };
$min_date = $opening !== NULL ? date('Y-m-d', strtotime($opening['opening_date'].' +1 day')) : '';
?>
<div class="row g-3 mb-3">
    <div class="col-12 col-md-4"><div class="card stat-card"><span class="stat-icon bg-primary-soft"><i class="fa-solid fa-building-columns"></i></span><div><div class="stat-label">Bank balance</div><div class="stat-value" id="fundBank"><?= e($rs($p['closing']['bank'])) ?></div></div></div></div>
    <div class="col-12 col-md-4"><div class="card stat-card"><span class="stat-icon bg-success-soft"><i class="fa-solid fa-money-bills"></i></span><div><div class="stat-label">Cash balance</div><div class="stat-value" id="fundCash"><?= e($rs($p['closing']['cash'])) ?></div></div></div></div>
    <div class="col-12 col-md-4"><div class="card stat-card"><span class="stat-icon bg-secondary-soft"><i class="fa-solid fa-scale-balanced"></i></span><div><div class="stat-label">Current available balance</div><div class="stat-value" id="fundTotal"><?= e($rs($p['closing']['total'])) ?></div>
        <div class="small text-muted"><?= $opening !== NULL ? 'Includes opening balance of '.e(fmt_date($opening['opening_date'])) : 'Opening balance not recorded' ?></div></div></div></div>
</div>

<div class="alert alert-info small">
    <i class="fa-solid fa-circle-info me-1"></i>
    Money received is recorded under <strong>Payments</strong> (maintenance) and <strong>Other Income</strong> (corpus, hall rent, interest...); money spent under <strong>Expenses</strong>.
    The payment mode decides the account: <strong>Cash</strong> = cash in hand, any other mode = the association's bank account.
    Use a <strong>withdrawal</strong> when cash is drawn from the bank and a <strong>deposit</strong> when cash is paid into the bank - these move money between the two and never change the total,
    so spending the withdrawn cash is recorded once, as an expense.
</div>

<?php if (can('transfers.manage')): ?>
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Record withdrawal / deposit</span>
        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#transferForm" aria-expanded="false" aria-controls="transferForm"><i class="fa-solid fa-right-left me-1"></i> New transfer</button>
    </div>
    <div class="collapse" id="transferForm">
        <div class="card-body">
            <?= form_open('funds/transfer', array('id' => 'fundTransferForm', 'novalidate' => 'novalidate')) ?>
                <input type="hidden" name="request_token" value="<?= e($request_token) ?>">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="transfer_date">Date <span class="required">*</span></label>
                        <input type="date" class="form-control" id="transfer_date" name="transfer_date" value="<?= e(max(date('Y-m-d'), $min_date)) ?>" max="<?= e(date('Y-m-d')) ?>"<?= $min_date !== '' ? ' min="'.e($min_date).'"' : '' ?> required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="direction">Type <span class="required">*</span></label>
                        <select class="form-select" id="direction" name="direction" required><?= select_options($directions, 'Bank to Cash') ?></select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="transfer_amount">Amount (₹) <span class="required">*</span></label>
                        <input type="text" inputmode="decimal" class="form-control text-end" id="transfer_amount" name="amount" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="transfer_description">Description <span class="required">*</span></label>
                        <input type="text" class="form-control" id="transfer_description" name="description" maxlength="255" required placeholder="e.g. Cash withdrawn for petty expenses">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="transfer_reference">Transaction Reference</label>
                        <input type="text" class="form-control" id="transfer_reference" name="reference" maxlength="100" placeholder="Cheque / slip no.">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="transfer_remarks">Remarks</label>
                        <input type="text" class="form-control" id="transfer_remarks" name="remarks" maxlength="500">
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary" data-confirm="Record this transfer? It moves money between the bank account and cash in hand; the total balance does not change." data-confirm-button="Record transfer" data-confirm-variant="primary">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Record transfer
                    </button>
                </div>
            <?= form_close() ?>
        </div>
    </div>
</div>
<?php endif; ?>

<form method="get" action="<?= e(site_url('funds')) ?>" class="card mb-3" data-ajax="false">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <div><label class="form-label small mb-1" for="from">From</label><input type="date" class="form-control form-control-sm" id="from" name="from" value="<?= e($l['from']) ?>"></div>
        <div><label class="form-label small mb-1" for="to">To</label><input type="date" class="form-control form-control-sm" id="to" name="to" value="<?= e($l['to']) ?>"></div>
        <button type="submit" class="btn btn-sm btn-primary" data-no-loading>Show</button>
        <?php if ($l['clamped']): ?><span class="small text-muted">Starts on <?= e(fmt_date($l['from'])) ?>: earlier entries are inside the opening balance.</span><?php endif; ?>
    </div>
</form>

<div class="card mb-3">
    <div class="card-header">Fund ledger &middot; <?= e(fmt_date($l['from'])) ?> to <?= e(fmt_date($l['to'])) ?></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" id="fundLedgerTable">
            <thead>
                <tr><th>Date</th><th>Type</th><th>Reference</th><th>Particulars</th><th>Mode</th>
                    <th class="text-end">Bank in</th><th class="text-end">Bank out</th><th class="text-end">Cash in</th><th class="text-end">Cash out</th>
                    <th class="text-end">Bank balance</th><th class="text-end">Cash balance</th><th class="text-end">Total</th></tr>
            </thead>
            <tbody>
                <tr class="table-light fw-semibold" data-row="opening"><td><?= e(fmt_date(date('Y-m-d', strtotime($l['from'].' -1 day')))) ?></td><td colspan="8">Balance brought forward<?= $opening !== NULL && $l['from'] === $min_date ? ' (opening balance '.e($opening['reference_no']).')' : '' ?></td>
                    <td class="text-end amount"><?= e($rs($l['opening']['bank'])) ?></td><td class="text-end amount"><?= e($rs($l['opening']['cash'])) ?></td><td class="text-end amount"><?= e($rs($l['opening']['total'])) ?></td></tr>
                <?php if (empty($l['rows'])): ?>
                    <tr><td colspan="12" class="text-muted">No money movements in this period.</td></tr>
                <?php endif; ?>
                <?php foreach ($l['rows'] as $row): ?>
                    <tr data-kind="<?= e($row['kind']) ?>">
                        <td class="text-nowrap"><?= e(fmt_date($row['d'])) ?></td>
                        <td class="text-nowrap"><?= e($row['type']) ?></td>
                        <td class="text-nowrap"><?= e($row['ref']) ?></td>
                        <td><?= e($row['particulars']) ?></td>
                        <td><?= e($row['mode'] ?? '-') ?></td>
                        <td class="text-end amount"><?= $cell($row['bank_in']) ?></td>
                        <td class="text-end amount"><?= $cell($row['bank_out']) ?></td>
                        <td class="text-end amount"><?= $cell($row['cash_in']) ?></td>
                        <td class="text-end amount"><?= $cell($row['cash_out']) ?></td>
                        <td class="text-end amount"><?= e($rs($row['bank_balance'])) ?></td>
                        <td class="text-end amount"><?= e($rs($row['cash_balance'])) ?></td>
                        <td class="text-end amount fw-semibold"><?= e($rs($row['balance'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="table-light fw-bold border-top border-dark" data-row="closing"><td><?= e(fmt_date($l['to'])) ?></td><td colspan="4">Closing balance (<?= count($l['rows']) ?> entr<?= count($l['rows']) === 1 ? 'y' : 'ies' ?>)</td>
                    <td class="text-end amount"><?= e($rs($l['totals']['bank_in'])) ?></td><td class="text-end amount"><?= e($rs($l['totals']['bank_out'])) ?></td>
                    <td class="text-end amount"><?= e($rs($l['totals']['cash_in'])) ?></td><td class="text-end amount"><?= e($rs($l['totals']['cash_out'])) ?></td>
                    <td class="text-end amount"><?= e($rs($l['closing']['bank'])) ?></td><td class="text-end amount"><?= e($rs($l['closing']['cash'])) ?></td><td class="text-end amount"><?= e($rs($l['closing']['total'])) ?></td></tr>
            </tbody>
        </table>
    </div>
    <div class="card-body small text-muted">Only recorded money movements are listed; cancelled entries are left out. Outstanding maintenance dues are not money in hand and are never included.</div>
</div>

<div class="card">
    <div class="card-header">Withdrawals and deposits</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" id="transfersTable">
            <thead><tr><th>Date</th><th>Reference</th><th>Type</th><th>Description</th><th class="text-end">Amount</th><th>Txn ref.</th><th>Entered by</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($transfers)): ?><tr><td colspan="9" class="text-muted">No transfers recorded.</td></tr><?php endif; ?>
            <?php foreach ($transfers as $t): ?>
                <tr class="<?= $t['status'] !== 'Active' ? 'text-muted' : '' ?>">
                    <td class="text-nowrap"><?= e(fmt_date($t['transfer_date'])) ?></td>
                    <td class="text-nowrap fw-semibold"><?= e($t['transfer_code']) ?></td>
                    <td class="text-nowrap"><?= e($directions[$t['direction']]) ?></td>
                    <td><?= e($t['description']) ?><?php if ($t['remarks']): ?><div class="small text-muted"><?= e($t['remarks']) ?></div><?php endif; ?></td>
                    <td class="text-end amount"><?= e(money($t['amount'])) ?></td>
                    <td><?= e($t['reference'] ?? '') ?></td>
                    <td class="small"><?= e($t['created_by_name'] ?: '-') ?><br><?= e(fmt_datetime($t['created_at'])) ?></td>
                    <td><?= status_badge($t['status']) ?><?php if ($t['status'] !== 'Active'): ?><div class="small"><?= e((string) $t['cancel_reason']) ?></div><?php endif; ?></td>
                    <td class="text-end">
                        <?php if ($t['status'] === 'Active' && can('transfers.manage')): ?>
                            <button type="button" class="btn btn-sm btn-outline-danger js-cancel-entry" data-url="<?= e(site_url('funds/cancel-transfer/'.$t['id'])) ?>" data-label="<?= e($t['transfer_code'].' ('.money($t['amount']).')') ?>" title="Cancel"><i class="fa-solid fa-ban"></i></button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?= form_open('', array('id' => 'cancelEntryForm', 'class' => 'd-none')) ?><input type="hidden" name="reason" id="cancelEntryReason"><?= form_close() ?>
