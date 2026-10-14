/* Opening Balance: live Total = Bank + Cash on the record and correction forms. */
(function ($) {
    'use strict';

    // Parse "12,345.50" into paise; NaN when invalid (the server validates again)
    var toPaise = function (value) {
        var s = String(value || '').replace(/[,\s₹]/g, '');
        if (s === '') {
            return 0;
        }
        if (!/^\d{1,10}(\.\d{1,2})?$/.test(s)) {
            return NaN;
        }
        var parts = s.split('.');
        return parseInt(parts[0], 10) * 100 + (parts[1] ? parseInt((parts[1] + '0').slice(0, 2), 10) : 0);
    };

    var format = function (paise) {
        var rupees = Math.floor(paise / 100).toString();
        var last3 = rupees.slice(-3);
        var rest = rupees.slice(0, -3);
        var grouped = rest ? rest.replace(/\B(?=(\d{2})+(?!\d))/g, ',') + ',' + last3 : last3;
        return '₹' + grouped + '.' + ('0' + (paise % 100)).slice(-2);
    };

    $(function () {
        $('#obRecordForm, #obCorrectForm').each(function () {
            var $form = $(this);
            var update = function () {
                var bank = toPaise($form.find('[name="bank_amount"]').val());
                var cash = toPaise($form.find('[name="cash_amount"]').val());
                $form.find('.js-ob-total').text(isNaN(bank) || isNaN(cash) ? 'Check the amounts' : format(bank + cash));
            };
            $form.on('input', '.js-ob-amount', update);
            update();
        });
    });
})(window.jQuery);
