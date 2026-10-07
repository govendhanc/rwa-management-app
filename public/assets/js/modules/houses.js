/* Houses / plots list: filters and activate/deactivate. */
(function ($, App) {
    'use strict';

    var escapeRegex = function (s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    };

    $(function () {
        if ($('#housesTable').length) {
            var table = App.dataTable('#housesTable', {
                exportTitle: 'Houses and Plots',
                order: [[1, 'asc']],
                columnDefs: [
                    { targets: 12, orderable: false, searchable: false, responsivePriority: 3 },
                    { targets: 1, responsivePriority: 1 },
                    { targets: 8, responsivePriority: 2 },
                    { targets: 7, responsivePriority: 4 },
                    { targets: 9, responsivePriority: 5 },
                    { targets: 10, responsivePriority: 6 },
                    { targets: 11, responsivePriority: 7 },
                    { targets: 2, responsivePriority: 8 },
                    { targets: [0, 3, 4, 5, 6], responsivePriority: 10 }
                ]
            });

            $('.js-filter').on('change', function () {
                var value = $(this).val();
                table.column($(this).data('column')).search(value ? '^' + escapeRegex(value) + '$' : '', true, false).draw();
            });
        }

        $(document).on('click', '.js-toggle-house', function () {
            var $btn = $(this);
            var deactivate = $btn.data('status') === 'Active';
            App.confirm({
                title: deactivate ? 'Deactivate plot?' : 'Activate plot?',
                message: deactivate
                    ? 'Plot ' + $btn.data('plot') + ' will no longer be billed. Existing bills and payments are kept.'
                    : 'Plot ' + $btn.data('plot') + ' will be included in future maintenance generation.',
                confirmText: deactivate ? 'Deactivate' : 'Activate',
                variant: deactivate ? 'danger' : 'success'
            }).then(function () {
                $.post($btn.data('url')).done(function (res) {
                    App.toast(res.message, 'success');
                    window.setTimeout(function () { window.location.reload(); }, 700);
                });
            }, function () { /* cancelled */ });
        });

        // Rate hint for the selected built status
        var $built = $('#built_status');
        if ($built.length) {
            var showRate = function () {
                var constructed = $built.val() === 'Built';
                var rate = constructed ? $built.data('rate-constructed') : $built.data('rate-vacant');
                $('#rateHint').text(rate ? 'Billed at the ' + (constructed ? 'Constructed' : 'Vacant Plot') + ' rate: ' + rate + ' / month' : '');
            };
            $built.on('change', showRate);
            showRate();
        }

        // Unassigned plots are always vacant
        var $owner = $('#owner_id');
        var $occupancy = $('#occupancy_status');
        if ($owner.length && $occupancy.is('select')) {
            var sync = function () {
                if (!$owner.val()) {
                    $occupancy.val('Vacant');
                }
                $occupancy.prop('disabled', !$owner.val());
            };
            $owner.on('change', function () {
                if ($owner.val() && $occupancy.val() === 'Vacant') {
                    $occupancy.val('Owner Occupied');
                }
                sync();
            });
            sync();
            $occupancy.closest('form').on('submit', function () { $occupancy.prop('disabled', false); });
        }
    });
})(window.jQuery, window.App);
