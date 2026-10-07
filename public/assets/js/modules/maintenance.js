/* Maintenance list, bill page actions (reason prompts) and WhatsApp reminders. */
(function ($, App) {
    'use strict';

    $(function () {
        var $table = $('#maintenanceTable');
        if ($table.length) {
            App.dataTable('#maintenanceTable', {
                exportTitle: $table.data('title'),
                order: [[0, 'desc'], [1, 'asc']],
                columnDefs: [
                    { targets: -1, orderable: false, searchable: false, responsivePriority: 4 },
                    { targets: 1, responsivePriority: 1 },
                    { targets: 3, responsivePriority: 2 },
                    { targets: 9, responsivePriority: 3 },
                    { targets: 10, responsivePriority: 5 }
                ]
            });
        }

        if ($('#remindersTable').length) {
            App.dataTable('#remindersTable', {
                exportTitle: 'Pending maintenance reminders',
                order: [[4, 'desc']],
                columnDefs: [{ targets: -1, orderable: false, searchable: false, responsivePriority: 2 }, { targets: 0, responsivePriority: 1 }]
            });
        }

        /* Cancel bill / reverse adjustment: ask for a reason, then POST the hidden form */
        $(document).on('click', '.js-reason-post', function () {
            var $btn = $(this);
            App.confirm({
                title: $btn.data('title'),
                message: $btn.data('message'),
                confirmText: $btn.data('button'),
                requireReason: true,
                reasonLabel: 'Reason'
            }).then(function (reason) {
                var $form = $('#reasonPostForm');
                $form.attr('action', $btn.data('url'));
                $('#reasonPostValue').val(reason);
                $form.trigger('submit');
            }, function () { /* cancelled */ });
        });

        /*
         * WhatsApp reminder for one owner. Returns a jQuery promise.
         * Click-to-Chat: the tab must be opened synchronously inside the click (pop-up blockers),
         * then pointed at wa.me once the server has prepared the message.
         * Cloud API: the server sends; no tab is opened.
         */
        var sendReminder = function ($btn, quiet) {
            var clickToChat = App.waMode !== 'cloud_api';
            var win = clickToChat ? window.open('', '_blank') : null;
            App.loading($btn, true, '');
            return $.ajax({ url: $btn.data('url'), type: 'POST', data: { period: $btn.data('period') }, suppressGlobalError: !!quiet })
                .done(function (res) {
                    if (res.data.url) {
                        if (win) {
                            win.opener = null;
                            win.location.href = res.data.url;
                        } else {
                            window.location.href = res.data.url;
                        }
                    }
                    $btn.closest('tr').find('.js-last-sent').text(res.data.sent_label);
                    $btn.addClass('btn-outline-success').removeClass('btn-whatsapp').attr('data-done', '1');
                    if (!quiet) {
                        App.toast(clickToChat ? 'Reminder prepared in WhatsApp. Tap Send there to deliver it.' : res.message, 'success');
                    }
                })
                .fail(function () {
                    if (win) {
                        win.close();
                    }
                })
                .always(function () {
                    App.loading($btn, false);
                });
        };

        $(document).on('click', '.js-send-reminder', function () {
            sendReminder($(this), false);
        });

        /* Click-to-Chat guided sending: each click opens the next owner who has not been sent yet */
        $('#guidedSendBtn').on('click', function () {
            var $all = $('.js-send-reminder:not([disabled])');
            var $next = $all.filter(':not([data-done])').first();
            if (!$next.length) {
                App.toast('All owners in this list have been sent a reminder.', 'success');
                return;
            }
            var done = $all.filter('[data-done]').length + 1;
            sendReminder($next, true).done(function () {
                var $after = $all.filter(':not([data-done])').first();
                $('#bulkProgressWrap').removeClass('d-none');
                $('#bulkProgress').css('width', Math.round(done * 100 / $all.length) + '%');
                $('#bulkStatus').text(done + ' of ' + $all.length + ' opened in WhatsApp');
                $('#guidedLabel').text($after.length ? 'Send next (' + (done + 1) + '/' + $all.length + '): ' + $after.data('name') : 'All done');
            });
        });

        /* Cloud API bulk sending: one request after another, with progress */
        $('#bulkSendBtn').on('click', function () {
            var $btn = $(this);
            var $queue = $('.js-send-reminder:not([disabled])');
            App.confirm({
                title: 'Send reminders to ' + $queue.length + ' owners?',
                message: 'Each owner receives the maintenance reminder on WhatsApp now.',
                confirmText: 'Send all',
                variant: 'success'
            }).then(function () {
                var sent = 0;
                var failed = 0;
                var i = 0;
                $btn.prop('disabled', true);
                $('#bulkProgressWrap').removeClass('d-none');
                var next = function () {
                    if (i >= $queue.length) {
                        $('#bulkStatus').text('Finished: ' + sent + ' sent, ' + failed + ' failed (see Message Log for details).');
                        App.toast(sent + ' reminder(s) sent' + (failed ? ', ' + failed + ' failed' : '') + '.', failed ? 'warning' : 'success');
                        $btn.prop('disabled', false);
                        return;
                    }
                    var $row = $queue.eq(i);
                    i++;
                    $('#bulkStatus').text('Sending ' + i + ' of ' + $queue.length + ': ' + $row.data('name'));
                    sendReminder($row, true).done(function () { sent++; }).fail(function () { failed++; }).always(function () {
                        $('#bulkProgress').css('width', Math.round(i * 100 / $queue.length) + '%');
                        next();
                    });
                };
                next();
            }, function () { /* cancelled */ });
        });
    });
})(window.jQuery, window.App);
