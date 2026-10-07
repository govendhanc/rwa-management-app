/* Receipts: list table and "Share on WhatsApp" (download PDF + open Click-to-Chat). */
(function ($, App) {
    'use strict';

    $(function () {
        if ($('#receiptsTable').length) {
            App.dataTable('#receiptsTable', {
                exportTitle: 'Receipts',
                order: [[1, 'desc'], [0, 'desc']],
                columnDefs: [{ targets: -1, orderable: false, searchable: false, responsivePriority: 2 }, { targets: 0, responsivePriority: 1 }]
            });
        }

        $(document).on('click', '.js-share-receipt', function () {
            var $btn = $(this);
            if (App.waMode === 'cloud_api') {
                App.loading($btn, true, 'Sending...');
                $.post($btn.data('url')).done(function (res) {
                    App.toast(res.message, 'success');
                }).always(function () {
                    App.loading($btn, false);
                });
                return;
            }
            // Click-to-Chat: open the WhatsApp tab synchronously so pop-up blockers allow it
            var win = window.open('', '_blank');
            App.loading($btn, true, 'Preparing...');
            $.post($btn.data('url')).done(function (res) {
                if ($btn.data('pdf')) {
                    var a = document.createElement('a');
                    a.href = $btn.data('pdf');
                    a.setAttribute('download', '');
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                }
                if (win) {
                    win.opener = null;
                    win.location.href = res.data.url;
                } else {
                    window.location.href = res.data.url;
                }
                App.toast('PDF downloaded and WhatsApp opened. Attach the PDF in the chat, then tap Send.', 'success');
            }).fail(function () {
                if (win) {
                    win.close();
                }
            }).always(function () {
                App.loading($btn, false);
            });
        });
    });
})(window.jQuery, window.App);
