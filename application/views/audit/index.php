<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end" id="auditFilters">
        <div><label class="form-label small mb-1" for="af_from">From</label><input type="date" class="form-control form-control-sm" id="af_from" value="<?= e(date('Y-m-d', strtotime('-30 days'))) ?>"></div>
        <div><label class="form-label small mb-1" for="af_to">To</label><input type="date" class="form-control form-control-sm" id="af_to" value="<?= e(date('Y-m-d')) ?>"></div>
        <div><label class="form-label small mb-1" for="af_module">Module</label><select class="form-select form-select-sm" id="af_module"><option value="">All modules</option><?= select_options($modules) ?></select></div>
        <div><label class="form-label small mb-1" for="af_user">User</label><select class="form-select form-select-sm" id="af_user"><option value="">All users</option>
            <?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>"><?= e($u['full_name'].' ('.$u['username'].')') ?></option><?php endforeach; ?></select></div>
        <button type="button" class="btn btn-sm btn-primary" id="af_apply"><i class="fa-solid fa-filter me-1"></i>Apply</button>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <table class="table table-hover w-100" id="auditTable" data-url="<?= e(site_url('audit-logs/data')) ?>" data-detail-url="<?= e(site_url('audit-logs/detail/')) ?>">
            <thead><tr><th>#</th><th>Date / Time</th><th>User</th><th>Action</th><th>Module</th><th>Record</th><th>IP Address</th><th class="no-export"></th></tr></thead>
        </table>
        <p class="small text-muted mb-0 mt-2">The audit log is append-only: entries cannot be edited or deleted from the portal. Passwords and tokens are never recorded.</p>
    </div>
</div>

<div class="modal fade" id="auditModal" tabindex="-1" aria-labelledby="auditModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="auditModalTitle">Audit entry</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="small text-muted mb-2" id="auditMeta"></div>
                <table class="table table-sm"><thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead><tbody id="auditChanges"></tbody></table>
            </div>
        </div>
    </div>
</div>
