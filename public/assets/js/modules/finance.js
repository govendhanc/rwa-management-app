/* Expenses & other income lists: tables and cancel-with-reason. */
(function ($, App) {
    'use strict';

    $(function () {
        if ($('#expensesTable').length) {
            App.dataTable('#expensesTable', {
                exportTitle: 'Expenses',
                order: [[0, 'desc']],
                columnDefs: [
                    { targets: -1, orderable: false, searchable: false, responsivePriority: 3 },
                    { targets: 3, responsivePriority: 1 },
                    { targets: 6, responsivePriority: 2 },
                    { targets: 8, orderable: false }
                ]
            });
        }
        if ($('#incomesTable').length) {
            App.dataTable('#incomesTable', {
                exportTitle: 'Other income',
                order: [[0, 'desc']],
                columnDefs: [{ targets: -1, orderable: false, searchable: false, responsivePriority: 3 }, { targets: 2, responsivePriority: 1 }, { targets: 4, responsivePriority: 2 }]
            });
        }

        $(document).on('click', '.js-cancel-entry', function () {
            var $btn = $(this);
            App.confirm({
                title: 'Cancel ' + $btn.data('label') + '?',
                message: 'The entry stays on record marked Cancelled and is excluded from totals and reports.',
                confirmText: 'Cancel entry',
                requireReason: true
            }).then(function (reason) {
                var $form = $('#cancelEntryForm');
                $form.attr('action', $btn.data('url'));
                $('#cancelEntryReason').val(reason);
                $form.trigger('submit');
            }, function () { /* cancelled */ });
        });
    });
})(window.jQuery, window.App);
