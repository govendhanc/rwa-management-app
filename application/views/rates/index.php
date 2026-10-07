<?php defined('BASEPATH') OR exit('No direct script access allowed');
$icons = array('Vacant Plot' => 'fa-solid fa-mountain-sun', 'Constructed' => 'fa-solid fa-house-chimney');
$state_badge = array('Current' => 'success', 'Scheduled' => 'info', 'Superseded' => 'secondary');
?>
<div class="row g-3 mb-3">
    <?php foreach ($current as $category => $rate): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card stat-card">
            <span class="stat-icon bg-primary-soft"><i class="<?= e($icons[$category] ?? 'fa-solid fa-tag') ?>"></i></span>
            <div>
                <div class="stat-label"><?= e($category) ?> &middot; current rate</div>
                <?php if ($rate !== NULL): ?>
                    <div class="stat-value"><?= e(money($rate['amount'])) ?> <small class="text-muted fs-6 fw-normal">/ plot / month</small></div>
                    <div class="small text-muted">Since <?= e(date('F Y', strtotime($rate['effective_from']))) ?> &middot; <?= (int) $plot_counts[$category] ?> billable plot(s)</div>
                <?php else: ?>
                    <div class="stat-value text-danger">Not set</div>
                    <div class="small text-danger">Bills cannot be generated for these plots until a rate is set.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-body small text-muted">
                <i class="fa-solid fa-circle-info me-1"></i>
                Plots marked <strong>Built</strong> pay the Constructed rate; <strong>Not Built</strong> and <strong>Under Construction</strong> plots pay the Vacant Plot rate.
                A rate change applies from its month onward; bills already generated keep their amount.
                <?php if ($last_billed !== NULL): ?>
                    <div class="mt-2">Bills are generated up to <strong><?= e(date('F Y', strtotime($last_billed))) ?></strong>.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <?php if (can('maintenance.rates')): ?>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">Change rate</div>
            <div class="card-body">
                <?= form_open('rates/add', array('novalidate' => 'novalidate')) ?>
                    <div class="mb-3">
                        <label class="form-label" for="plot_category">Applies to <span class="required">*</span></label>
                        <select class="form-select" id="plot_category" name="plot_category" required>
                            <option value="Both">All plots (Vacant Plot and Constructed)</option>
                            <?= select_options((array) $this->config->item('plot_categories')) ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="amount">Monthly amount per plot <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><?= e(currency_symbol()) ?></span>
                            <input type="text" class="form-control" id="amount" name="amount" inputmode="decimal" required placeholder="300.00">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="effective_month">Effective from <span class="required">*</span></label>
                        <input type="month" class="form-control" id="effective_month" name="effective_month" required
                               min="<?= e(substr($earliest_month, 0, 7)) ?>" value="<?= e(substr($earliest_month, 0, 7)) ?>">
                        <div class="form-text">Earliest allowed: <?= e(date('F Y', strtotime($earliest_month))) ?> (the first month without bills).</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="remarks">Remarks / resolution reference</label>
                        <input type="text" class="form-control" id="remarks" name="remarks" maxlength="255" placeholder="e.g. AGM resolution dated 15-Dec-2026">
                    </div>
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-floppy-disk me-1"></i> Save Rate</button>
                <?= form_close() ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?= can('maintenance.rates') ? 'col-xl-8' : 'col-12' ?>">
        <div class="card">
            <div class="card-header">Rate history</div>
            <div class="card-body">
                <table class="table table-hover w-100" id="ratesTable">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Effective From</th>
                            <th class="text-end">Amount</th>
                            <th>State</th>
                            <th class="text-end">Bills</th>
                            <th>Remarks</th>
                            <th>Added By</th>
                            <th class="no-export text-end"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $r):
                        $deletable = (int) $r['bill_count'] === 0 && ($last_billed === NULL || $r['effective_from'] > $last_billed);
                    ?>
                        <tr>
                            <td><?= e($r['plot_category']) ?></td>
                            <td data-order="<?= e($r['effective_from']) ?>"><?= e(date('M Y', strtotime($r['effective_from']))) ?></td>
                            <td class="text-end amount fw-semibold"><?= e(money($r['amount'])) ?></td>
                            <td><span class="badge rounded-pill text-bg-<?= e($state_badge[$r['state']]) ?>"><?= e($r['state']) ?></span></td>
                            <td class="text-end"><?= (int) $r['bill_count'] ?></td>
                            <td><?= e($r['remarks']) ?></td>
                            <td><?= e($r['created_by_name']) ?><div class="small text-muted"><?= e(fmt_date($r['created_at'])) ?></div></td>
                            <td class="text-end">
                                <?php if (can('maintenance.rates') && $deletable): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger js-delete-rate"
                                            data-url="<?= e(site_url('rates/delete/'.$r['id'])) ?>"
                                            data-label="<?= e($r['plot_category'].' '.money($r['amount']).' from '.date('F Y', strtotime($r['effective_from']))) ?>"
                                            data-bs-toggle="tooltip" title="Delete (not used yet)"><i class="fa-solid fa-trash"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
