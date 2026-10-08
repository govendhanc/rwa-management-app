/* Owners: list filters, owner profile tables, deactivate / reactivate, remove. */
(function ($, App) {
    'use strict';

    var escapeRegex = function (s) {
        return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    };

    $(function () {
        /* ---------- Owner list (Active / Inactive) ---------- */
        if ($('#ownersTable').length) {
            var table = App.dataTable('#ownersTable', {
                exportTitle: $('#ownersTable').data('title'),
                order: [[0, 'asc']],
                // On narrow screens keep name, outstanding and actions; Occupancy (not a core column) collapses first
                columnDefs: [
                    { targets: 10, orderable: false, searchable: false, responsivePriority: 3 },
                    { targets: 4, responsivePriority: 1 },
                    { targets: 8, responsivePriority: 2 },
                    { targets: 2, responsivePriority: 4 },
                    { targets: 9, responsivePriority: 5 },
                    { targets: 7, responsivePriority: 6 },
                    { targets: 5, responsivePriority: 7 },
                    { targets: 1, responsivePriority: 8 },
                    { targets: 3, responsivePriority: 9 },
                    { targets: 0, responsivePriority: 10 },
                    { targets: 6, responsivePriority: 11 }
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
        var showTab = function (id) {
            var btn = document.getElementById(id);
            if (btn && window.bootstrap) {
                window.bootstrap.Tab.getOrCreateInstance(btn).show();
                btn.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        };
        var hashTabs = { '#payments': 'paymentsTabBtn', '#houses': 'housesTabBtn' };
        if (hashTabs[window.location.hash]) {
            showTab(hashTabs[window.location.hash]);
        }
        $('.js-show-payments').on('click', function () { showTab('paymentsTabBtn'); });

        /* ---------- Deactivate / Reactivate (list and profile) ---------- */
        var $modal = $('#ownerStatusModal');
        var $current = null;
        // The list re-renders rows (paging, responsive), so bind on the document
        $(document).on('click', '.js-owner-status', function () {
            if (!$modal.length || !window.bootstrap) {
                return;
            }
            $current = $(this);
            var deactivate = $current.data('action') === 'deactivate';
            var hasDue = String($current.data('has-due')) === '1';
            var hasAdvance = String($current.data('has-advance')) === '1';

            $('#ownerStatusTitle').text(deactivate ? 'Deactivate owner?' : 'Reactivate owner?');
            $modal.find('.js-status-question').text(deactivate
                ? 'Are you sure you want to deactivate this owner?'
                : 'Are you sure you want to reactivate this owner?');
            $modal.find('.js-status-name').text($current.data('name'));
            $modal.find('.js-status-code').text($current.data('code'));
            $modal.find('.js-status-plots').text($current.data('plots'));
            $modal.find('.js-status-houses').text($current.data('houses'));
            $modal.find('.js-status-outstanding').text($current.data('outstanding')).toggleClass('text-danger', hasDue);
            $modal.find('.js-status-advance').text($current.data('advance'));
            $modal.find('.js-status-advance-row').toggleClass('d-none', !hasAdvance);
            $modal.find('.js-status-due-amount').text($current.data('outstanding'));
            $modal.find('.js-status-due-warning').toggleClass('d-none', !(deactivate && hasDue));
            $modal.find('.js-status-deactivate').toggleClass('d-none', !deactivate);
            $modal.find('.js-status-reactivate').toggleClass('d-none', deactivate);
            $('#ownerStatusReason').val('');
            $('#ownerStatusConfirm')
                .text(deactivate ? 'Deactivate owner' : 'Reactivate owner')
                .toggleClass('btn-danger', deactivate)
                .toggleClass('btn-success', !deactivate);

            window.bootstrap.Modal.getOrCreateInstance($modal[0]).show();
        });

        $('#ownerStatusConfirm').on('click', function () {
            if (!$current) {
                return;
            }
            var $btn = $(this);
            var data = $current.data('action') === 'deactivate' ? { reason: $('#ownerStatusReason').val() } : {};
            App.loading($btn, true, 'Saving...');
            $.post($current.data('url'), data).done(function (res) {
                window.location.href = res.data.redirect;
            }).fail(function () {
                App.loading($btn, false);
            });
        });

        /* ---------- Edit form: confirm a status change to Inactive ---------- */
        var $status = $('#ownerForm #status[data-original]');
        if ($status.length) {
            var confirmed = false;
            $('#ownerForm').on('submit', function (ev) {
                if (confirmed || $status.data('original') !== 'Active' || $status.val() !== 'Inactive') {
                    return;
                }
                // Stop here so the global double-submit guard does not lock the form if the user cancels
                ev.preventDefault();
                ev.stopPropagation();
                var form = this;
                var message = 'The owner will not be billed while inactive. Existing bills, payments, receipts and audit history are kept.';
                if (String($status.data('has-due')) === '1') {
                    message = 'This owner has an outstanding maintenance balance of ' + $status.data('outstanding') +
                        '. The owner will be deactivated, but existing financial records will be retained.';
                }
                App.confirm({
                    title: 'Deactivate this owner?',
                    message: message,
                    confirmText: 'Save and deactivate'
                }).then(function () {
                    confirmed = true;
                    form.submit();
                }, function () { /* cancelled */ });
            });
        }

        /* ---------- Remove an owner entered by mistake (no financial history) ---------- */
        $('.js-delete-owner').on('click', function () {
            var $btn = $(this);
            App.confirm({
                title: 'Remove owner?',
                message: 'Remove "' + $btn.data('name') + '"? Use this only for an owner entered by mistake. Their plots will become unassigned. Owners who sell or leave should be deactivated instead.',
                confirmText: 'Remove owner'
            }).then(function () {
                App.loading($btn, true, 'Removing...');
                $.post($btn.data('url')).done(function (res) {
                    window.location.href = res.data.redirect;
                }).fail(function () {
                    App.loading($btn, false);
                });
            }, function () { /* cancelled */ });
        });
    });
})(window.jQuery, window.App);
