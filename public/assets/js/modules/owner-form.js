/* Owner form: plot rows (add/remove, existing vs new plot) and "WhatsApp same as mobile". */
(function ($) {
    'use strict';

    $(function () {
        var $rows = $('#plotRows');
        var $template = $('#plotRowTemplate');
        var rates = $('#currentRates');
        var maxRows = Number($rows.data('max')) || 20;

        var syncRow = function ($row) {
            var mode = $row.find('.js-plot-mode:checked').val() || 'new';
            $row.find('.js-mode-panel').each(function () {
                var show = $(this).data('mode') === mode;
                $(this).toggleClass('d-none', !show);
                $(this).find('input, select').prop('disabled', !show);
            });
            var built = $row.find('.js-built-status').val();
            var rate = built === 'Built' ? rates.data('constructed') : rates.data('vacant');
            var label = built === 'Built' ? 'Constructed' : 'Vacant Plot';
            $row.find('.js-rate-hint').text(rate ? 'Billed at ' + label + ' rate ' + rate + ' / month' : '');
        };

        var renumber = function () {
            var $all = $rows.find('.js-plot-row');
            $all.each(function (i) {
                $(this).find('.js-plot-number').text(i + 1);
            });
            $('.js-no-plots').toggleClass('d-none', $all.length > 0);
            $('#addPlotRow').prop('disabled', $all.length >= maxRows);
        };

        var nextIndex = function () {
            var max = -1;
            $rows.find('.js-plot-row').each(function () {
                max = Math.max(max, Number($(this).data('index')));
            });
            return max + 1;
        };

        $('#addPlotRow').on('click', function () {
            if (!$template.length) {
                return;
            }
            var html = $template.html().replace(/__INDEX__/g, String(nextIndex()));
            var $row = $(html);
            $rows.append($row);
            syncRow($row);
            renumber();
            $row.find('select:visible, input[type="text"]:visible').first().trigger('focus');
        });

        $rows.on('click', '.js-remove-plot', function () {
            $(this).closest('.js-plot-row').remove();
            renumber();
        });

        $rows.on('change', '.js-plot-mode, .js-built-status', function () {
            syncRow($(this).closest('.js-plot-row'));
        });

        $rows.find('.js-plot-row').each(function () { syncRow($(this)); });
        renumber();

        /* WhatsApp same as mobile */
        var $mobile = $('#mobile');
        var $whatsapp = $('#whatsapp_no');
        var $same = $('#sameAsMobile');
        if ($whatsapp.val() === '' || $whatsapp.val() === $mobile.val()) {
            $same.prop('checked', true);
        }
        var syncWhatsapp = function () {
            if ($same.is(':checked')) {
                $whatsapp.val($mobile.val()).prop('readonly', true);
            } else {
                $whatsapp.prop('readonly', false);
            }
        };
        $same.on('change', syncWhatsapp);
        $mobile.on('input', syncWhatsapp);
        syncWhatsapp();
    });
})(window.jQuery);
