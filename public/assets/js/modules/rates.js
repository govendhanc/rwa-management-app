/* Maintenance rates: history table and delete of unused rates. */
(function ($, App) {
    'use strict';

    $(function () {
        App.dataTable('#ratesTable', {
            exportTitle: 'Maintenance Rate History',
            order: [[1, 'desc'], [0, 'asc']],
            columnDefs: [{ targets: -1, orderable: false, searchable: false }]
        });

        $(document).on('click', '.js-delete-rate', function () {
            var $btn = $(this);
            App.confirm({
                title: 'Delete rate?',
                message: 'Delete the ' + $btn.data('label') + ' rate? No bills have used it yet.',
                confirmText: 'Delete rate'
            }).then(function () {
                $.post($btn.data('url')).done(function (res) {
                    App.toast(res.message, 'success');
                    window.setTimeout(function () { window.location.reload(); }, 700);
                });
            }, function () { /* cancelled */ });
        });
    });
})(window.jQuery, window.App);
