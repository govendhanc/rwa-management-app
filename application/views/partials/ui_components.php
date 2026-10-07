<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!-- Toast notifications (App.toast) -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

<!-- Confirmation dialog (App.confirm) -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="confirmModalTitle">Please confirm</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="confirmModalMessage"></p>
                <div class="mt-3 d-none" id="confirmModalReasonWrap">
                    <label class="form-label" for="confirmModalReason" id="confirmModalReasonLabel">Reason</label>
                    <textarea class="form-control" id="confirmModalReason" rows="2" maxlength="255"></textarea>
                    <div class="invalid-feedback">Please enter a reason.</div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmModalOk">Confirm</button>
            </div>
        </div>
    </div>
</div>
