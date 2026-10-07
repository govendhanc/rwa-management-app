<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<div class="card mx-auto text-center" style="max-width: 560px;">
    <div class="card-body py-5">
        <div class="stat-icon bg-danger-soft mx-auto mb-3"><i class="fa-solid fa-lock"></i></div>
        <h2 class="h4">Access denied</h2>
        <p class="text-muted mb-4">Your role does not have permission to open this page or perform this action.
            If you need access, please contact the Super Admin.</p>
        <a href="<?= e(site_url('dashboard')) ?>" class="btn btn-primary"><i class="fa-solid fa-gauge-high me-1"></i> Go to Dashboard</a>
    </div>
</div>
