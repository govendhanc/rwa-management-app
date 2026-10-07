<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php $this->load->view('receipts/_actions', array('receipt' => $receipt)); ?>
<div class="row g-3">
    <div class="col-xl-9">
        <div class="card">
            <div class="card-body receipt-screen"><?= $receipt_html ?></div>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="card">
            <div class="card-header">Links</div>
            <ul class="list-group list-group-flush">
                <?php if (can('payments.view')): ?>
                    <li class="list-group-item"><a href="<?= e(site_url('payments/view/'.$receipt['payment_id'])) ?>"><i class="fa-solid fa-indian-rupee-sign fa-fw me-1"></i>Payment details</a></li>
                <?php endif; ?>
                <li class="list-group-item"><a href="<?= e(site_url('owners/view/'.$receipt['owner_id'].'#payments')) ?>"><i class="fa-solid fa-user fa-fw me-1"></i>Owner payment history</a></li>
            </ul>
        </div>
    </div>
</div>
