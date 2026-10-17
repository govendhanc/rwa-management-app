<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Finance -> Opening Balance.
 *
 * @var array<string, mixed>|null           $active
 * @var array<string, mixed>                $position  Fund_model::position() (amounts in paise)
 * @var array<int, array<string, mixed>>    $history
 * @var array<string, int>                  $recorded  transactions already recorded up to the opening date
 * @var string                              $default_date
 * @var string                              $request_token
 */
$p = $position;
$rs = function (int $paise): string { return money(from_paise($paise)); };
$date = $active !== NULL ? (string) $active['opening_date'] : $default_date;
$pre_count = $recorded['payments'] + $recorded['other_incomes'] + $recorded['expenses'];

/** Opening balance form fields (record or correct). */
$fields = function (array $v) use ($rs) { ob_start(); ?>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label" for="<?= e($v['prefix']) ?>opening_date">Opening Balance Date <span class="required">*</span></label>
            <input type="date" class="form-control" id="<?= e($v['prefix']) ?>opening_date" name="opening_date" value="<?= e($v['opening_date']) ?>" required>
            <div class="form-text">Balances as at the end of this day.</div>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="<?= e($v['prefix']) ?>bank_amount">Opening Bank Balance (₹) <span class="required">*</span></label>
            <input type="text" inputmode="decimal" class="form-control text-end js-ob-amount" id="<?= e($v['prefix']) ?>bank_amount" name="bank_amount" value="<?= e($v['bank_amount']) ?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label" for="<?= e($v['prefix']) ?>cash_amount">Opening Cash Balance (₹) <span class="required">*</span></label>
            <input type="text" inputmode="decimal" class="form-control text-end js-ob-amount" id="<?= e($v['prefix']) ?>cash_amount" name="cash_amount" value="<?= e($v['cash_amount']) ?>" required>
            <div class="form-text">Enter 0 if there is no cash in hand.</div>
        </div>
        <div class="col-md-4">
            <div class="form-label">Total Opening Balance</div>
            <div class="fs-5 fw-bold js-ob-total">-</div>
        </div>
        <div class="col-md-8">
            <label class="form-label" for="<?= e($v['prefix']) ?>remarks">Remarks / Reference</label>
            <input type="text" class="form-control" id="<?= e($v['prefix']) ?>remarks" name="remarks" maxlength="500" value="<?= e($v['remarks']) ?>" placeholder="e.g. Bank statement 31-Oct-2026, cash counted by Treasurer">
        </div>
    </div>
<?php return ob_get_clean(); };
?>

<div class="alert alert-info small">
    <i class="fa-solid fa-circle-info me-1"></i>
    The opening balance is the association's <strong>verified money in hand</strong> (bank + cash) at the end of the opening date, before the portal's own collections start.
    It is <strong>brought forward</strong> - not maintenance collection or income - so no receipt is created and no owner payment is recorded.
    Payments, other income and expenses dated <strong>after</strong> that date are added to it; anything dated on or before it is treated as already included.
</div>

<?php if ($active === NULL): ?>
    <div class="card mb-3">
        <div class="card-header">Record opening balance</div>
        <div class="card-body">
            <?php if ($pre_count > 0): ?>
                <div class="alert alert-warning small">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    The portal already holds <?= (int) $recorded['payments'] ?> payment(s), <?= (int) $recorded['other_incomes'] ?> other income and <?= (int) $recorded['expenses'] ?> expense entr<?= $recorded['expenses'] === 1 ? 'y' : 'ies' ?>
                    dated on or before <?= e(fmt_date($date)) ?> (net <?= e($rs($recorded['net'])) ?>). Once the opening balance is saved, those are treated as part of it and are not added to the available funds again.
                    They stay in their receipts and period reports.
                </div>
            <?php endif; ?>
            <?= form_open('opening-balance/save', array('id' => 'obRecordForm', 'novalidate' => 'novalidate')) ?>
                <input type="hidden" name="request_token" value="<?= e($request_token) ?>">
                <?= $fields(array('prefix' => 'ob_', 'opening_date' => $default_date, 'bank_amount' => '', 'cash_amount' => '0', 'remarks' => '')) ?>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary" data-confirm="Save this opening balance? Check the amounts against the bank statement and cash count - later changes need a recorded correction." data-confirm-button="Save opening balance">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save opening balance
                    </button>
                </div>
            <?= form_close() ?>
        </div>
    </div>
<?php else: ?>
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>Opening balance <strong><?= e($active['reference_no']) ?></strong> <?= status_badge('Active') ?></span>
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#obCorrect" aria-expanded="false" aria-controls="obCorrect">
                <i class="fa-solid fa-pen-to-square me-1"></i> Correct opening balance
            </button>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-6 col-lg-3"><div class="small text-muted">Opening Balance Date</div><div class="fw-semibold"><?= e(fmt_date($active['opening_date'])) ?></div></div>
                <div class="col-6 col-lg-3"><div class="small text-muted">Opening Bank Balance</div><div class="fw-semibold"><?= e(money($active['bank_amount'])) ?></div></div>
                <div class="col-6 col-lg-3"><div class="small text-muted">Opening Cash Balance</div><div class="fw-semibold"><?= e(money($active['cash_amount'])) ?></div></div>
                <div class="col-6 col-lg-3"><div class="small text-muted">Total Opening Balance</div><div class="fw-bold fs-5"><?= e(money($active['total_amount'])) ?></div></div>
                <div class="col-lg-6"><div class="small text-muted">Remarks / Reference</div><div><?= $active['remarks'] !== NULL ? e($active['remarks']) : '<span class="text-muted">-</span>' ?></div></div>
                <div class="col-6 col-lg-3"><div class="small text-muted">Entered By</div><div><?= e($active['created_by_name'] ?: '-') ?></div></div>
                <div class="col-6 col-lg-3"><div class="small text-muted">Entered Date and Time</div><div><?= e(fmt_datetime($active['created_at'])) ?></div></div>
            </div>

            <div class="collapse mt-3" id="obCorrect">
                <div class="border rounded-3 p-3 bg-light">
                    <div class="fw-semibold mb-1">Correct opening balance</div>
                    <p class="small text-muted">The corrected figures replace <?= e($active['reference_no']) ?> with a new reference. The original entry is kept as <em>Superseded</em>, and the change is written to the audit log.</p>
                    <?= form_open('opening-balance/correct', array('id' => 'obCorrectForm', 'novalidate' => 'novalidate')) ?>
                        <input type="hidden" name="request_token" value="<?= e($request_token) ?>">
                        <input type="hidden" name="active_id" value="<?= (int) $active['id'] ?>">
                        <?= $fields(array('prefix' => 'oc_', 'opening_date' => (string) $active['opening_date'], 'bank_amount' => (string) $active['bank_amount'], 'cash_amount' => (string) $active['cash_amount'], 'remarks' => (string) $active['remarks'])) ?>
                        <div class="mt-3">
                            <label class="form-label" for="correction_reason">Reason for correction <span class="required">*</span></label>
                            <input type="text" class="form-control" id="correction_reason" name="correction_reason" maxlength="255" required placeholder="e.g. Cash count corrected after Treasurer verification">
                        </div>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary" data-confirm="Replace the opening balance with these figures? The original entry is kept for the record." data-confirm-button="Save correction">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save correction
                            </button>
                        </div>
                    <?= form_close() ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header">Fund ledger <?= $p['has_opening'] ? '&middot; since '.e(fmt_date($p['since'])) : '&middot; all recorded transactions' ?></div>
            <div class="table-responsive">
                <table class="table mb-0" id="fundLedger">
                    <thead><tr><th></th><th class="text-end">Bank</th><th class="text-end">Cash</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        <tr><td>Opening balance<?= $p['has_opening'] ? ' ('.e(fmt_date($p['opening_row']['opening_date'])).')' : ' (not recorded)' ?></td>
                            <td class="text-end amount"><?= e($rs($p['opening']['bank'])) ?></td><td class="text-end amount"><?= e($rs($p['opening']['cash'])) ?></td><td class="text-end amount fw-semibold"><?= e($rs($p['opening']['total'])) ?></td></tr>
                        <tr><td>+ Maintenance collections</td>
                            <td class="text-end amount"><?= e($rs($p['collections']['bank'])) ?></td><td class="text-end amount"><?= e($rs($p['collections']['cash'])) ?></td><td class="text-end amount"><?= e($rs($p['collections']['total'])) ?></td></tr>
                        <tr><td>+ Other income</td>
                            <td class="text-end amount"><?= e($rs($p['other_income']['bank'])) ?></td><td class="text-end amount"><?= e($rs($p['other_income']['cash'])) ?></td><td class="text-end amount"><?= e($rs($p['other_income']['total'])) ?></td></tr>
                        <tr><td>&plusmn; Withdrawals / deposits (bank &harr; cash)</td>
                            <td class="text-end amount"><?= e($rs($p['transfers']['bank'])) ?></td><td class="text-end amount"><?= e($rs($p['transfers']['cash'])) ?></td><td class="text-end amount"><?= e($rs($p['transfers']['total'])) ?></td></tr>
                        <tr><td>&minus; Expenses</td>
                            <td class="text-end amount"><?= e($rs($p['expenses']['bank'])) ?></td><td class="text-end amount"><?= e($rs($p['expenses']['cash'])) ?></td><td class="text-end amount"><?= e($rs($p['expenses']['total'])) ?></td></tr>
                        <tr class="fw-bold border-top border-dark table-light"><td>Current available balance</td>
                            <td class="text-end amount"><?= e($rs($p['closing']['bank'])) ?></td><td class="text-end amount"><?= e($rs($p['closing']['cash'])) ?></td><td class="text-end amount fs-5"><?= e($rs($p['closing']['total'])) ?></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card-body small text-muted">
                Bank = every payment mode except Cash (UPI, bank transfer, cheque, NEFT, RTGS, other) - the association has one bank account. Cash = payment mode Cash.
                Cancelled entries are excluded. Unpaid maintenance (outstanding dues) is never part of available funds.
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header">Maintenance collections by month</div>
            <?php if ( ! $p['has_opening']): ?>
                <div class="card-body small text-muted">Shown once the opening balance is recorded.</div>
            <?php elseif (empty($p['by_month'])): ?>
                <div class="card-body small text-muted">No payments recorded after <?= e(fmt_date($p['opening_row']['opening_date'])) ?> yet.</div>
            <?php else: ?>
                <table class="table table-sm mb-0" id="collectionsByMonth">
                    <thead><tr><th>Month</th><th class="text-end">Payments</th><th class="text-end">Collected</th></tr></thead>
                    <tbody>
                        <?php foreach ($p['by_month'] as $mrow): ?>
                            <tr><td><?= e($mrow['label']) ?></td><td class="text-end"><?= (int) $mrow['count'] ?></td><td class="text-end amount"><?= e($rs($mrow['total'])) ?></td></tr>
                        <?php endforeach; ?>
                        <tr class="fw-bold border-top"><td>Total since <?= e(fmt_date($p['since'])) ?></td><td class="text-end"><?= (int) $p['collections']['count'] ?></td><td class="text-end amount"><?= e($rs($p['collections']['total'])) ?></td></tr>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ( ! empty($history)): ?>
<div class="card">
    <div class="card-header">Opening balance history</div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" id="obHistory">
            <thead><tr><th>Reference</th><th>Date</th><th class="text-end">Bank</th><th class="text-end">Cash</th><th class="text-end">Total</th><th>Status</th><th>Entered</th><th>Correction reason</th><th>Replaced</th></tr></thead>
            <tbody>
            <?php foreach ($history as $h): ?>
                <tr class="<?= $h['status'] !== 'Active' ? 'text-muted' : '' ?>">
                    <td class="text-nowrap fw-semibold"><?= e($h['reference_no']) ?></td>
                    <td class="text-nowrap"><?= e(fmt_date($h['opening_date'])) ?></td>
                    <td class="text-end amount"><?= e(money($h['bank_amount'])) ?></td>
                    <td class="text-end amount"><?= e(money($h['cash_amount'])) ?></td>
                    <td class="text-end amount"><?= e(money($h['total_amount'])) ?></td>
                    <td><?= status_badge($h['status']) ?></td>
                    <td class="small"><?= e($h['created_by_name'] ?: '-') ?><br><?= e(fmt_datetime($h['created_at'])) ?></td>
                    <td class="small"><?= $h['correction_reason'] !== NULL ? e($h['correction_reason']) : '-' ?></td>
                    <td class="small"><?= $h['superseded_at'] !== NULL ? e(fmt_datetime($h['superseded_at'])).($h['superseded_by_name'] ? ' by '.e($h['superseded_by_name']) : '') : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
