/* Generate Monthly Maintenance: preview (AJAX) then generate (normal POST after confirmation). */
(function ($, App) {
    'use strict';

    $(function () {
        var $form = $('#generateForm');
        var $period = $('#period');
        var $due = $('#due_date');
        var $generate = $('#generateBtn');
        var lastPreview = null;
        var confirmed = false;

        var resetPreview = function () {
            lastPreview = null;
            $generate.prop('disabled', true);
            $('#generateLabel').text('Generate Maintenance');
            $('#previewResult').addClass('d-none');
            $('#previewHint').removeClass('d-none');
        };

        // Keep the due date in the selected month (same day of month)
        $period.on('change', function () {
            var p = $period.val();
            if (/^\d{4}-\d{2}$/.test(p)) {
                var day = String($period.data('due-day')).padStart(2, '0');
                $due.val(p + '-' + day);
            }
            resetPreview();
        });

        var badge = function (row) {
            if (row.action === 'bill') {
                return '<span class="badge rounded-pill bg-success">Will bill</span>';
            }
            if (row.action === 'billed') {
                return '<span class="badge rounded-pill bg-secondary">' + App.escape(row.reason) + '</span>';
            }
            return '<span class="badge rounded-pill text-bg-warning">Skip: ' + App.escape(row.reason) + '</span>';
        };

        var render = function (d) {
            lastPreview = d;
            var alerts = '';
            d.errors.forEach(function (msg) {
                alerts += '<div class="alert alert-danger py-2"><i class="fa-solid fa-circle-exclamation me-2"></i>' + App.escape(msg) + '</div>';
            });
            if (d.already_generated) {
                alerts += '<div class="alert alert-warning py-2"><i class="fa-solid fa-triangle-exclamation me-2"></i>Maintenance for ' +
                    App.escape(d.period_label) + ' has already been generated.</div>';
            } else if (d.batch_exists && d.plots > 0) {
                alerts += '<div class="alert alert-info py-2"><i class="fa-solid fa-circle-info me-2"></i>' + App.escape(d.period_label) +
                    ' was generated earlier. ' + d.plots + ' plot(s) added since then can be billed now; existing bills are not touched.</div>';
            }
            $('#previewAlerts').html(alerts);
            $('#pvPlots').text(d.plots);
            $('#pvOwners').text(d.owners);
            $('#pvAmount').text(d.amount);
            $('#pvSkipped').text(d.skipped);
            $('#pvBilled').text(d.already_billed);

            var cats = '';
            d.by_category.forEach(function (c) {
                cats += '<tr><td>' + App.escape(c.category) + '</td><td class="text-end">' + c.plots + '</td><td class="text-end amount">' +
                    App.escape(c.rate) + '</td><td class="text-end amount fw-semibold">' + App.escape(c.amount) + '</td></tr>';
            });
            $('#pvCategories').html(cats || '<tr><td colspan="4" class="text-muted">Nothing to bill</td></tr>');

            var rows = '';
            d.rows.forEach(function (r) {
                rows += '<tr data-action="' + App.escape(r.action) + '"><td>' + App.escape(r.plot) + '</td><td>' + App.escape(r.block) + '</td><td>' +
                    App.escape(r.owner) + '</td><td>' + App.escape(r.category) + '</td><td class="text-end amount">' +
                    (r.action === 'bill' ? App.escape(r.amount) : '') + '</td><td>' + badge(r) + '</td></tr>';
            });
            $('#pvRows').html(rows);
            $('[data-show]').removeClass('active').filter('[data-show="all"]').addClass('active');

            $('#previewHint').addClass('d-none');
            $('#previewResult').removeClass('d-none');

            var canGenerate = d.errors.length === 0 && d.plots > 0;
            $generate.prop('disabled', !canGenerate);
            $('#generateLabel').text(d.batch_exists ? 'Generate remaining (' + d.plots + ')' : 'Generate Maintenance (' + d.plots + ')');
        };

        $('#previewBtn').on('click', function () {
            var $btn = $(this);
            App.loading($btn, true, 'Loading preview...');
            $.post(App.url('maintenance/preview'), { period: $period.val() }).done(function (res) {
                render(res.data);
            }).always(function () {
                App.loading($btn, false);
            });
        });

        $(document).on('click', '[data-show]', function () {
            var show = $(this).data('show');
            $('[data-show]').removeClass('active');
            $(this).addClass('active');
            $('#pvRows tr').each(function () {
                $(this).toggleClass('d-none', show !== 'all' && $(this).data('action') !== show);
            });
        });

        $form.on('submit', function (e) {
            if (confirmed) {
                return true;
            }
            e.preventDefault();
            if (!lastPreview) {
                return false;
            }
            App.confirm({
                title: 'Generate maintenance for ' + lastPreview.period_label + '?',
                message: lastPreview.plots + ' bill(s) totalling ' + lastPreview.amount + ' will be created for ' + lastPreview.owners +
                    ' owner(s). Advance credit will be applied automatically.',
                confirmText: 'Generate',
                variant: 'primary'
            }).then(function () {
                confirmed = true;
                App.loading($generate, true, 'Generating...');
                $form.off('submit');
                $form.get(0).submit();
            }, function () { /* cancelled */ });
            return false;
        });

        if ($period.val()) {
            $('#previewBtn').trigger('click');
        }
    });
})(window.jQuery, window.App);
