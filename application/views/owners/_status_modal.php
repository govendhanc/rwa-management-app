<?php defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Deactivate / Reactivate confirmation. Filled by owners.js from the clicked button's data-* attributes;
 * every value is inserted with .text(), and the server re-checks everything on submit.
 */
?>
<div class="modal fade" id="ownerStatusModal" tabindex="-1" aria-labelledby="ownerStatusTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ownerStatusTitle">Deactivate owner?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="fw-semibold js-status-question mb-2">Are you sure you want to deactivate this owner?</p>
                <table class="table table-sm mb-3">
                    <tbody>
                        <tr><th class="text-muted fw-normal w-50">Owner Name</th><td class="fw-semibold js-status-name"></td></tr>
                        <tr><th class="text-muted fw-normal">Owner ID</th><td class="js-status-code"></td></tr>
                        <tr><th class="text-muted fw-normal">Plot Number</th><td class="js-status-plots"></td></tr>
                        <tr><th class="text-muted fw-normal">House Number</th><td class="js-status-houses"></td></tr>
                        <tr><th class="text-muted fw-normal">Outstanding Amount</th><td class="fw-semibold js-status-outstanding"></td></tr>
                        <tr class="js-status-advance-row"><th class="text-muted fw-normal">Advance Credit</th><td class="js-status-advance"></td></tr>
                    </tbody>
                </table>

                <div class="alert alert-warning py-2 small js-status-due-warning d-none" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                    This owner has an outstanding maintenance balance of <strong class="js-status-due-amount"></strong>.
                    The owner will be deactivated, but existing financial records will be retained.
                </div>

                <div class="js-status-deactivate">
                    <ul class="small text-muted ps-3 mb-3">
                        <li>No new monthly maintenance will be generated for this owner.</li>
                        <li>Existing bills, payments, receipts, statements and audit history are kept.</li>
                        <li>Outstanding dues can still be collected; the owner can be reactivated at any time.</li>
                    </ul>
                    <label class="form-label" for="ownerStatusReason">Reason <span class="text-muted small">(optional)</span></label>
                    <textarea class="form-control" id="ownerStatusReason" rows="2" maxlength="255" placeholder="e.g. Plot sold to a new owner"></textarea>
                </div>
                <div class="js-status-reactivate d-none">
                    <ul class="small text-muted ps-3 mb-0">
                        <li>The owner becomes Active and is included in future maintenance generation.</li>
                        <li>Months while the owner was inactive are not billed automatically.</li>
                        <li>All historical records stay unchanged.</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="ownerStatusConfirm">Deactivate owner</button>
            </div>
        </div>
    </div>
</div>
