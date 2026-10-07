<?php defined('BASEPATH') OR exit('No direct script access allowed');
$s = $summary;
?>
<div class="page-actions">
    <form method="get" action="<?= e(site_url('maintenance/reminders')) ?>" class="d-flex gap-2 align-items-center" data-ajax="false">
        <label class="visually-hidden" for="period">Billing month</label>
        <select class="form-select form-select-sm w-auto" id="period" name="period">
            <?php foreach ($batches as $b): $p = sprintf('%04d-%02d', $b['billing_year'], $b['billing_month']); ?>
                <option value="<?= e($p) ?>" <?= $p === $period ? 'selected' : '' ?>><?= e(period_label((int) $b['billing_year'], (int) $b['billing_month'])) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-primary" data-no-loading>Show</button>
    </form>
    <a href="<?= e(site_url('maintenance?period='.$period.'&status=Unpaid')) ?>" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-list me-1"></i> Unpaid bills</a>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-4"><div class="card stat-card"><span class="stat-icon bg-success-soft"><i class="fa-solid fa-circle-check"></i></span><div><div class="stat-label">Paid</div><div class="stat-value"><?= (int) $s['paid_count'] ?></div></div></div></div>
    <div class="col-sm-4"><div class="card stat-card"><span class="stat-icon bg-warning-soft"><i class="fa-solid fa-circle-half-stroke"></i></span><div><div class="stat-label">Partial</div><div class="stat-value"><?= (int) $s['partial_count'] ?></div></div></div></div>
    <div class="col-sm-4"><div class="card stat-card"><span class="stat-icon bg-danger-soft"><i class="fa-solid fa-clock"></i></span><div><div class="stat-label">Pending</div><div class="stat-value"><?= (int) $s['pending_count'] ?></div></div></div></div>
</div>

<div class="alert alert-info small d-flex gap-2">
    <i class="fa-brands fa-whatsapp fs-5"></i>
    <div><strong>How it works:</strong>
        <?= $wa_mode === 'cloud_api'
            ? 'clicking <em>Send</em> sends the reminder straight from the server through the WhatsApp Cloud API.'
            : 'clicking <em>Send</em> opens WhatsApp (app or web) with the reminder already typed for that owner &mdash; you then tap Send in WhatsApp.' ?>
        Each message is logged. Counts above are bills (plots) for <?= e(period_label($year, $month)) ?>; the list below groups them by owner.</div>
</div>

<?php if ( ! empty($owners)): ?>
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center">
        <?php if ($wa_mode === 'cloud_api'): ?>
            <button type="button" class="btn btn-whatsapp" id="bulkSendBtn" data-count="<?= count($owners) ?>"><i class="fa-solid fa-paper-plane me-1"></i> Send to all <?= count($owners) ?> owners</button>
            <span class="small text-muted">Cloud API mode: messages are sent one after another from the server.</span>
        <?php else: ?>
            <button type="button" class="btn btn-whatsapp" id="guidedSendBtn"><i class="fa-brands fa-whatsapp me-1"></i> <span id="guidedLabel">Start guided sending (<?= count($owners) ?> owners)</span></button>
            <span class="small text-muted">Click-to-Chat mode: each click opens the next owner's chat; send it in WhatsApp, come back and click again.</span>
        <?php endif; ?>
        <div class="flex-grow-1 d-none" id="bulkProgressWrap" style="min-width: 200px;">
            <div class="progress bulk-progress"><div class="progress-bar bg-success" id="bulkProgress" style="width: 0%"></div></div>
            <div class="small text-muted mt-1" id="bulkStatus"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">Owners with pending maintenance for <?= e(period_label($year, $month)) ?> (<?= count($owners) ?>)</div>
    <div class="card-body">
        <table class="table table-hover w-100" id="remindersTable">
            <thead>
                <tr>
                    <th>Owner</th>
                    <th>Plots</th>
                    <th>WhatsApp</th>
                    <th class="text-end">Due this month</th>
                    <th class="text-end">Total outstanding</th>
                    <th>Last reminder</th>
                    <th class="no-export text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($owners as $o): $number = whatsapp_number($o['whatsapp_no'] ?: $o['mobile']); ?>
                <tr>
                    <td><a href="<?= e(site_url('owners/view/'.$o['owner_id'])) ?>" class="fw-semibold text-decoration-none"><?= e($o['owner_name']) ?></a>
                        <div class="small text-muted"><?= e($o['owner_code']) ?><?= (int) $o['has_partial'] === 1 ? ' &middot; partly paid' : '' ?></div></td>
                    <td><?= e($o['plots']) ?></td>
                    <td class="text-nowrap"><?= $number !== '' ? e('+'.$number) : '<span class="text-danger">No valid number</span>' ?></td>
                    <td class="text-end amount"><?= e(money($o['month_due'])) ?></td>
                    <td class="text-end amount fw-semibold"><?= e(money($o['total_due'])) ?></td>
                    <td class="js-last-sent text-nowrap"><?= $o['last_reminded_at'] ? e(fmt_datetime($o['last_reminded_at'])) : '<span class="text-muted">Not sent</span>' ?></td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-whatsapp js-send-reminder" <?= $number === '' ? 'disabled' : '' ?>
                                data-url="<?= e(site_url('maintenance/reminder-link/'.$o['owner_id'])) ?>" data-period="<?= e($period) ?>" data-name="<?= e($o['owner_name']) ?>">
                            <i class="fa-brands fa-whatsapp me-1"></i> Send
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
