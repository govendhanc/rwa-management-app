/* Tenants: list filters and ID-proof number hint. */
(function ($, App) {
    'use strict';

    var escapeRegex = function (s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    };

    $(function () {
        if ($('#tenantsTable').length) {
            var table = App.dataTable('#tenantsTable', {
                exportTitle: 'Tenants',
                order: [[5, 'desc']],
                columnDefs: [
                    { targets: -1, orderable: false, searchable: false, responsivePriority: 3 },
                    { targets: 1, responsivePriority: 1 },
                    { targets: 3, responsivePriority: 2 },
                    { targets: 8, responsivePriority: 4 },
                    { targets: 6, responsivePriority: 5 }
                ]
            });

            var applyStatus = function () {
                var $sel = $('.js-filter');
                var value = $sel.val();
                table.column($sel.data('column')).search(value ? '^' + escapeRegex(value) + '$' : '', true, false).draw();
            };
            $('.js-filter').on('change', applyStatus);
            applyStatus();

            $.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
                if (settings.nTable.id !== 'tenantsTable' || !$('#filterAgreement').val()) {
                    return true;
                }
                return $(table.row(dataIndex).node()).data('agreement') === 'due';
            });
            $('#filterAgreement').on('change', function () { table.draw(); });
        }

        // Aadhaar: only the last 4 digits may be entered
        var $type = $('#id_proof_type');
        var $number = $('#id_proof_number');
        if ($type.length) {
            var syncId = function () {
                var aadhaar = $type.val() === 'Aadhaar';
                $('#idNumberLabel').text(aadhaar ? 'Aadhaar - last 4 digits only' : 'ID Number');
                $number.attr('maxlength', aadhaar ? 4 : 30)
                    .attr('inputmode', aadhaar ? 'numeric' : 'text')
                    .attr('placeholder', aadhaar ? '1234' : '')
                    .prop('disabled', !$type.val());
                $('#idNumberHint').toggleClass('d-none', !aadhaar);
            };
            $type.on('change', function () {
                $number.val('');
                syncId();
            });
            syncId();
        }
    });
})(window.jQuery, window.App);
