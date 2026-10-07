<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="row g-3">
    <div class="col-xl-5">
        <div class="card mb-3">
            <div class="card-header">1. Find owner</div>
            <div class="card-body">
                <label class="form-label" for="ownerSearch">Search by plot no, house no, owner name, owner ID or mobile</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="search" class="form-control" id="ownerSearch" placeholder="e.g. 5, A-105, Elango, 98765..." autocomplete="off" data-url="<?= e(site_url('payments/search')) ?>">
                </div>
                <div class="list-group mt-2" id="searchResults"></div>
            </div>
        </div>

        <div class="card mb-3 d-none" id="ownerCard">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Owner</span>
                <a href="#" id="ownerProfileLink" class="small" target="_blank" rel="noopener">Open profile</a>
            </div>
            <div class="card-body">
                <div class="h5 mb-1" id="ownerName"></div>
                <div class="small text-muted mb-3" id="ownerMeta"></div>
                <table class="table table-sm mb-0">
                    <tr><td>Plot No</td><td class="text-end fw-semibold" id="ownerPlots"></td></tr>
                    <tr><td>House No</td><td class="text-end fw-semibold" id="ownerHouses"></td></tr>
                    <tr><td>Previous Outstanding</td><td class="text-end amount" id="dueOld"></td></tr>
                    <tr><td>Current Month Amount</td><td class="text-end amount" id="dueCurrent"></td></tr>
                    <tr class="table-light"><td class="fw-semibold">Total Payable</td><td class="text-end amount fw-bold fs-5" id="dueTotal"></td></tr>
                    <tr class="d-none" id="creditRow"><td>Advance credit held</td><td class="text-end amount text-success" id="dueCredit"></td></tr>
                    <tr><td>Last payment</td><td class="text-end" id="lastPayment"></td></tr>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-7">
        <div class="card mb-3 d-none" id="billsCard">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span>2. Unpaid bills <span class="text-muted fw-normal small">(oldest first)</span></span>
                <div class="btn-group btn-group-sm" role="group" aria-label="Apply payment to">
                    <input type="radio" class="btn-check" name="allocation_ui" id="allocAuto" value="auto" checked>
                    <label class="btn btn-outline-primary" for="allocAuto">Oldest first (automatic)</label>
                    <input type="radio" class="btn-check" name="allocation_ui" id="allocSelected" value="selected">
                    <label class="btn btn-outline-primary" for="allocSelected">Selected bills only</label>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead><tr><th style="width: 36px;"></th><th>Month</th><th>Plot</th><th class="text-end">Amount</th><th class="text-end">Paid / Waived</th><th class="text-end">Balance</th><th>Status</th></tr></thead>
                    <tbody id="billRows"></tbody>
                </table>
            </div>
        </div>

        <div class="card d-none" id="paymentCard">
            <div class="card-header">3. Payment details</div>
            <div class="card-body">
                <?= form_open('payments/store', array('id' => 'paymentForm', 'data-ajax' => 'true', 'novalidate' => 'novalidate')) ?>
                    <input type="hidden" name="owner_id" id="owner_id" value="">
                    <input type="hidden" name="request_token" value="<?= e($request_token) ?>">
                    <input type="hidden" name="allocation" id="allocation" value="auto">
                    <div id="selectedBillInputs"></div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="payment_date">Payment Date <span class="required">*</span></label>
                            <input type="date" class="form-control" id="payment_date" name="payment_date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="amount">Payment Amount <span class="required">*</span></label>
                            <div class="input-group has-validation">
                                <span class="input-group-text"><?= e(currency_symbol()) ?></span>
                                <input type="text" class="form-control fw-semibold" id="amount" name="amount" inputmode="decimal" required autocomplete="off">
                            </div>
                            <div class="form-text" id="amountHint"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="payment_mode">Payment Mode <span class="required">*</span></label>
                            <select class="form-select" id="payment_mode" name="payment_mode" required>
                                <?= select_options($modes, 'UPI') ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="transaction_ref" id="refLabel">Transaction Reference</label>
                            <input type="text" class="form-control" id="transaction_ref" name="transaction_ref" maxlength="100" autocomplete="off">
                            <div class="form-text" id="refHint"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="remarks">Remarks</label>
                            <input type="text" class="form-control" id="remarks" name="remarks" maxlength="500">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="is_advance" name="is_advance">
                                <label class="form-check-label" for="is_advance">
                                    <strong>Advance payment</strong> &mdash; keep any amount above the dues as credit; it is adjusted automatically against upcoming months.
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-light border mb-0 small" id="allocationPreview"></div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-success btn-lg" id="payBtn"><i class="fa-solid fa-indian-rupee-sign me-1"></i> Record Payment &amp; Generate Receipt</button>
                    </div>
                <?= form_close() ?>
            </div>
        </div>

        <div class="card" id="placeholderCard">
            <div class="card-body text-center text-muted py-5">
                <i class="fa-solid fa-magnifying-glass-dollar fa-2x mb-3 d-block"></i>
                Search and select an owner to see their dues and record a payment.
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="collectConfig"><?= json_encode(array(
    'duesUrl'      => site_url('payments/owner-dues/'),
    'ownerUrl'     => site_url('owners/view/'),
    'preselect'    => $owner !== NULL ? (int) $owner['id'] : 0,
    'needRef'      => Payment_engine::MODES_NEEDING_REFERENCE,
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
