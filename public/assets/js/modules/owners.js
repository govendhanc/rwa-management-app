/* Owners: list filters, owner profile tables, soft delete. */
(function ($, App) {
    'use strict';

    var escapeRegex = function (s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    };

    $(function () {
        /* ---------- Owner list ---------- */
        if ($('#ownersTable').length) {
            var table = App.dataTable('#ownersTable', {
                exportTitle: 'Owner List',
                order: [[0, 'asc']],
                // On narrow screens keep name, outstanding and actions; collapse e-mail/WhatsApp first
                columnDefs: [
                    { targets: 12, orderable: false, searchable: false, responsivePriority: 3 },
                    { targets: 4, responsivePriority: 1 },
                    { targets: 10, responsivePriority: 2 },
                    { targets: 2, responsivePriority: 4 },
                    { targets: 11, responsivePriority: 5 },
                    { targets: 5, responsivePriority: 6 },
                    { targets: 9, responsivePriority: 7 },
                    { targets: 1, responsivePriority: 8 },
                    { targets: 8, responsivePriority: 9 },
                    { targets: 3, responsivePriority: 10 },
                    { targets: 0, responsivePriority: 11 },
                    { targets: 6, responsivePriority: 12 },
                    { targets: 7, responsivePriority: 13 }
                ]
            });

            $('.js-filter').on('change', function () {
                var column = $(this).data('column');
                var value = $(this).val();
                var contains = $(this).data('match') === 'contains';
                var pattern = value ? (contains ? escapeRegex(value) : '^' + escapeRegex(value) + '$') : '';
                table.column(column).search(pattern, true, false).draw();
            });

            // Dues filter works on a row attribute, not a column value
            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                if (settings.nTable.id !== 'ownersTable') {
                    return true;
                }
                var wanted = $('#filterDues').val();
                if (!wanted) {
                    return true;
                }
                return $(table.row(dataIndex).node()).data('due') === wanted;
            });
            $('#filterDues').on('change', function () { table.draw(); });
        }

        /* ---------- Owner profile ---------- */
        if ($('#ownerMaintenanceTable').length) {
            App.dataTable('#ownerMaintenanceTable', { exportTitle: 'Maintenance History', order: [[0, 'desc']], pageLength: 12 });
        }
        if ($('#ownerPaymentsTable').length) {
            App.dataTable('#ownerPaymentsTable', { exportTitle: 'Payment History', order: [[0, 'desc']], pageLength: 12 });
        }
        // DataTables inside hidden tabs need a column re-measure once shown
        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
            $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust().responsive.recalc();
        });
        var hashTabs = { '#payments': 'paymentsTabBtn', '#houses': 'housesTabBtn' };
        if (hashTabs[window.location.hash]) {
            var btn = document.getElementById(hashTabs[window.location.hash]);
            if (btn && window.bootstrap) {
                window.bootstrap.Tab.getOrCreateInstance(btn).show();
            }
        }

        $('.js-delete-owner').on('click', function () {
            var $btn = $(this);
            App.confirm({
                title: 'Delete owner?',
                message: 'Delete "' + $btn.data('name') + '"? Their houses will become unassigned. This is only possible because the owner has no billing history.',
                confirmText: 'Delete owner'
            }).then(function () {
                App.loading($btn, true, 'Deleting...');
                $.post($btn.data('url')).done(function (res) {
                    window.location.href = res.data.redirect;
                }).fail(function () {
                    App.loading($btn, false);
                });
            }, function () { /* cancelled */ });
        });
    });
})(window.jQuery, window.App);
