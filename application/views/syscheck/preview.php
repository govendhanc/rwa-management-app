<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="alert alert-info d-flex align-items-center gap-2">
    <i class="fa-solid fa-circle-info"></i>
    <div>This page previews the shared layout using the demo data. Menu links go live module by module in the next steps.</div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <span class="stat-icon bg-primary-soft"><i class="fa-solid fa-users"></i></span>
            <div><div class="stat-label">Active Owners</div><div class="stat-value"><?= (int) $totals['active_owners'] ?></div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <span class="stat-icon bg-success-soft"><i class="fa-solid fa-indian-rupee-sign"></i></span>
            <div><div class="stat-label">Total Collected</div><div class="stat-value amount"><?= e(money($totals['collected'])) ?></div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <span class="stat-icon bg-danger-soft"><i class="fa-solid fa-hourglass-half"></i></span>
            <div><div class="stat-label">Outstanding</div><div class="stat-value amount"><?= e(money($totals['outstanding'])) ?></div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card">
            <span class="stat-icon bg-warning-soft"><i class="fa-solid fa-money-bill-wave"></i></span>
            <div><div class="stat-label">Expenses</div><div class="stat-value amount"><?= e(money($totals['expenses'])) ?></div></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Owners (demo data)</span>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" id="previewToast"><i class="fa-regular fa-bell me-1"></i>Toast</button>
            <button type="button" class="btn btn-sm btn-outline-danger" id="previewConfirm"><i class="fa-solid fa-ban me-1"></i>Confirm dialog</button>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-striped table-hover w-100" id="previewTable">
            <thead>
                <tr>
                    <th>S.No</th>
                    <th>Owner ID</th>
                    <th>Plot</th>
                    <th>House</th>
                    <th>Owner Name</th>
                    <th>Mobile</th>
                    <th>Occupancy</th>
                    <th>Built Status</th>
                    <th class="text-end">Outstanding</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($owners as $i => $owner): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= e($owner['owner_code']) ?></td>
                    <td><?= e($owner['plot_no']) ?></td>
                    <td><?= e($owner['house_no']) ?></td>
                    <td><?= e($owner['owner_name']) ?></td>
                    <td><?= e($owner['mobile']) ?></td>
                    <td><?= status_badge($owner['occupancy_status']) ?></td>
                    <td><?= e($owner['built_status']) ?></td>
                    <td class="text-end amount"><?= e(money($owner['outstanding'])) ?></td>
                    <td><?= status_badge($owner['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
