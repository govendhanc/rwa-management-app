/* Reports: export-ready tables, income/expense period switcher, statement Excel export, print. */
(function ($, App) {
    'use strict';

    $(function () {
        if ($('#outstandingTable').length) {
            App.dataTable('#outstandingTable', {
                exportTitle: $('#outstandingTable').data('title'),
                order: [[8, 'desc']],
                pageLength: 100,
                columnDefs: [{ targets: 2, responsivePriority: 1 }, { targets: 8, responsivePriority: 2 }, { targets: 0, responsivePriority: 3 }]
            });
        }
        if ($('#collectionTable').length) {
            App.dataTable('#collectionTable', {
                exportTitle: $('#collectionTable').data('title'),
                order: [[0, 'asc']],
                paging: false,
                columnDefs: [{ targets: 0, responsivePriority: 1 }, { targets: 7, responsivePriority: 2 }]
            });
        }

        /* Income & expense: show only the inputs for the chosen period type */
        var $type = $('#type');
        if ($type.length) {
            var sync = function () {
                $('.js-ie').each(function () {
                    var show = $(this).data('type') === $type.val();
                    $(this).toggleClass('d-none', !show).find('input').prop('disabled', !show);
                });
            };
            $type.on('change', sync);
            sync();
        }
        $('#printReport').on('click', function () { window.print(); });

        /* Owner statement ledger -> Excel (uses the bundled DataTables/JSZip export) */
        $('#statementExcel').on('click', function () {
            var $ledger = $('#statementLedger');
            if (!$ledger.length) {
                return;
            }
            var $copy = $ledger.clone().attr('id', 'ledgerExport').addClass('d-none');
            $('body').append($copy);
            var dt = $copy.DataTable({
                paging: false, ordering: false, searching: false, info: false,
                buttons: [{ extend: 'excelHtml5', title: document.title + ' - ' + $('#owner_id option:selected').text() }],
                dom: 'B'
            });
            dt.button(0).trigger();
            window.setTimeout(function () { dt.destroy(); $copy.remove(); }, 1000);
        });
    });
})(window.jQuery, window.App);
