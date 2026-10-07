/* WhatsApp: template editor (live preview, placeholder insert), message log, settings test send. */
(function ($, App) {
    'use strict';

    /* WhatsApp-style formatting for previews: *bold*, _italic_, ~strike~ (text is escaped first; CSS keeps line breaks) */
    var waFormat = function (text) {
        return App.escape(text)
            .replace(/\*([^*\n]+)\*/g, '<strong>$1</strong>')
            .replace(/_([^_\n]+)_/g, '<em>$1</em>')
            .replace(/~([^~\n]+)~/g, '<s>$1</s>');
    };

    $(function () {
        /* ---------- Template editor ---------- */
        var $body = $('#message_body');
        if ($body.length) {
            var timer = null;
            var refresh = function () {
                var text = $body.val();
                $('#bodyLength').text(text.length);
                var order = [];
                (text.match(/\{[A-Z_]+\}/g) || []).forEach(function (t) {
                    if (order.indexOf(t) === -1) {
                        order.push(t);
                    }
                });
                $('#cloudOrder').text(order.length ? order.map(function (t, i) { return '{{' + (i + 1) + '}} = ' + t; }).join(', ') : 'no placeholders');

                window.clearTimeout(timer);
                timer = window.setTimeout(function () {
                    $.post($body.data('preview-url'), { message_body: text, template_key: $body.data('template-key') }).done(function (res) {
                        $('#previewText').html(waFormat(res.data.text));
                        var unknown = res.data.unknown || [];
                        $('#previewUnknown').toggleClass('d-none', !unknown.length)
                            .text(unknown.length ? 'Unknown placeholder(s): {' + unknown.join('}, {') + '} - these will not be replaced.' : '');
                    });
                }, 300);
            };
            $body.on('input', refresh);
            refresh();

            $('.js-insert').on('click', function () {
                var el = $body.get(0);
                var token = $(this).data('token');
                var start = el.selectionStart || 0;
                var end = el.selectionEnd || 0;
                el.value = el.value.slice(0, start) + token + el.value.slice(end);
                el.selectionStart = el.selectionEnd = start + token.length;
                el.focus();
                refresh();
            });
        }

        /* ---------- Static previews on the templates list ---------- */
        $('.wa-bubble').each(function () {
            if (!$(this).is('#previewText, #messageModalBody')) {
                $(this).html(waFormat($(this).text()));
            }
        });

        /* ---------- Message log ---------- */
        if ($('#waLogTable').length) {
            App.dataTable('#waLogTable', {
                exportTitle: 'WhatsApp message log',
                order: [[0, 'desc']],
                columnDefs: [{ targets: -1, orderable: false, searchable: false }, { targets: 1, responsivePriority: 1 }, { targets: 6, responsivePriority: 2 }]
            });
            $(document).on('click', '.js-show-message', function () {
                $('#messageModalBody').html(waFormat(String($(this).data('message'))));
                window.bootstrap.Modal.getOrCreateInstance(document.getElementById('messageModal')).show();
            });
        }

        /* ---------- Settings: test send ---------- */
        $('#testSendBtn').on('click', function () {
            var $btn = $(this);
            App.loading($btn, true, 'Sending...');
            $.post($btn.data('url'), { number: $('#testNumber').val() }).done(function (res) {
                App.toast(res.message, 'success');
            }).always(function () {
                App.loading($btn, false);
            });
        });
    });
})(window.jQuery, window.App);
