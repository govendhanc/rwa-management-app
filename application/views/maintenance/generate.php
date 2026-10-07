<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="row g-3">
    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header">Billing month</div>
            <div class="card-body">
                <?= form_open('maintenance/generate', array('id' => 'generateForm', 'data-ajax' => 'true', 'novalidate' => 'novalidate')) ?>
                    <div class="mb-3">
                        <label class="form-label" for="period">Month <span class="required">*</span></label>
                        <input type="month" class="form-control" id="period" name="period" value="<?= e($period) ?>" max="<?= e($max_period) ?>" required data-due-day="<?= (int) $due_day ?>">
                        <div class="form-text">Up to one month in advance. Past months can be generated for catch-up billing.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="due_date">Due Date <span class="required">*</span></label>
                        <input type="date" class="form-control" id="due_date" name="due_date" value="<?= e($due_date) ?>" required>
                    </div>
                    <div class="small text-muted mb-3">
                        The amount for each plot comes from <a href="<?= e(site_url('rates')) ?>">Maintenance Rates</a> (Vacant Plot / Constructed) in force for the month.
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-primary" id="previewBtn"><i class="fa-solid fa-magnifying-glass me-1"></i> Preview</button>
                        <button type="submit" class="btn btn-primary" id="generateBtn" disabled><i class="fa-solid fa-file-circle-plus me-1"></i> <span id="generateLabel">Generate Maintenance</span></button>
                    </div>
                <?= form_close() ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Recently generated</div>
            <ul class="list-group list-group-flush">
                <?php if (empty($batches)): ?>
                    <li class="list-group-item text-muted small">No months generated yet.</li>
                <?php endif; ?>
                <?php foreach ($batches as $b): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <a href="<?= e(site_url('maintenance?period='.sprintf('%04d-%02d', $b['billing_year'], $b['billing_month']))) ?>"><?= e(period_label((int) $b['billing_year'], (int) $b['billing_month'])) ?></a>
                        <span class="small text-muted"><?= (int) $b['total_records'] ?> bills &middot; <?= e(money($b['total_amount'])) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">Preview</div>
            <div class="card-body" id="previewArea">
                <p class="text-muted mb-0" id="previewHint">Choose a month and click <strong>Preview</strong> to see who will be billed before generating.</p>
                <div class="d-none" id="previewResult">
                    <div id="previewAlerts"></div>
                    <div class="row g-3 mb-3">
                        <div class="col-sm-4"><div class="border rounded-3 p-3"><div class="stat-label">Plots to bill</div><div class="stat-value" id="pvPlots">0</div></div></div>
                        <div class="col-sm-4"><div class="border rounded-3 p-3"><div class="stat-label">Owners</div><div class="stat-value" id="pvOwners">0</div></div></div>
                        <div class="col-sm-4"><div class="border rounded-3 p-3"><div class="stat-label">Total maintenance</div><div class="stat-value" id="pvAmount">0</div></div></div>
                    </div>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Category</th><th class="text-end">Plots</th><th class="text-end">Rate</th><th class="text-end">Amount</th></tr></thead>
                            <tbody id="pvCategories"></tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                        <span class="small text-muted">Show:</span>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Filter preview rows">
                            <button type="button" class="btn btn-outline-secondary active" data-show="all">All</button>
                            <button type="button" class="btn btn-outline-secondary" data-show="bill">Will bill</button>
                            <button type="button" class="btn btn-outline-secondary" data-show="skip">Skipped <span class="badge text-bg-light" id="pvSkipped">0</span></button>
                            <button type="button" class="btn btn-outline-secondary" data-show="billed">Already billed <span class="badge text-bg-light" id="pvBilled">0</span></button>
                        </div>
                    </div>
                    <div class="table-responsive" style="max-height: 420px;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="sticky-top"><tr><th>Plot</th><th>Block</th><th>Owner</th><th>Category</th><th class="text-end">Amount</th><th>Result</th></tr></thead>
                            <tbody id="pvRows"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
