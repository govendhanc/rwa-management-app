/* Layout preview (development only) - exercises DataTables, toasts and the confirm dialog. */
(function ($, App) {
    'use strict';

    $(function () {
        App.dataTable('#previewTable', { exportTitle: 'Owners - Demo' });

        $('#previewToast').on('click', function () {
            App.toast('Payment recorded successfully.', 'success');
        });

        $('#previewConfirm').on('click', function () {
            App.confirm({
                title: 'Cancel payment?',
                message: 'The receipt will be marked as cancelled. This cannot be undone.',
                confirmText: 'Cancel Payment',
                requireReason: true,
                reasonLabel: 'Reason for cancellation'
            }).then(function (reason) {
                App.toast('Confirmed with reason: ' + reason, 'info');
            }, function () {
                App.toast('Cancelled by user', 'warning');
            });
        });
    });
})(window.jQuery, window.App);
