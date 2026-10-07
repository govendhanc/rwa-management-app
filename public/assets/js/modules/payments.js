/* Payments list, advance credits and payment cancellation. */
(function ($, App) {
    'use strict';

    $(function () {
        if ($('#paymentsTable').length) {
            App.dataTable('#paymentsTable', {
                exportTitle: 'Payments',
                order: [[0, 'desc']],
                columnDefs: [
                    { targets: -1, orderable: false, searchable: false, responsivePriority: 3 },
                    { targets: 2, responsivePriority: 1 },
                    { targets: 5, responsivePriority: 2 },
                    { targets: 1, responsivePriority: 4 }
                ]
            });
        }
        if ($('#advancesTable').length) {
            App.dataTable('#advancesTable', { exportTitle: 'Advance credits', order: [[3, 'desc']], columnDefs: [{ targets: -1, orderable: false, searchable: false }] });
        }

        $('.js-cancel-payment').on('click', function () {
            var $btn = $(this);
            App.confirm({
                title: 'Cancel payment ' + ($btn.data('receipt') || '') + '?',
                message: 'The payment and receipt stay on record marked CANCELLED, and the bills it paid become unpaid again. Use this for wrong entries or bounced cheques.',
                confirmText: 'Cancel payment',
                requireReason: true,
                reasonLabel: 'Reason (shown on the receipt and in the audit log)'
            }).then(function (reason) {
                var $form = $('#cancelPaymentForm');
                $form.attr('action', $btn.data('url'));
                $('#cancelPaymentReason').val(reason);
                $form.trigger('submit');
            }, function () { /* cancelled */ });
        });
    });
})(window.jQuery, window.App);
