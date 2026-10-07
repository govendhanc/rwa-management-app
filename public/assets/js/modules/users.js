/* Users list: activate/deactivate, reset password, unlock. */
(function ($, App) {
    'use strict';

    $(function () {
        App.dataTable('#usersTable', {
            exportTitle: 'Portal Users',
            order: [[5, 'asc'], [1, 'asc']],
            columnDefs: [{ targets: -1, orderable: false, searchable: false }]
        });

        $(document).on('click', '.js-toggle', function () {
            var $btn = $(this);
            var activating = $btn.data('status') !== 'Active';
            App.confirm({
                title: activating ? 'Activate user?' : 'Deactivate user?',
                message: activating
                    ? 'User "' + $btn.data('name') + '" will be able to sign in again.'
                    : 'User "' + $btn.data('name') + '" will be signed out and will not be able to sign in.',
                confirmText: activating ? 'Activate' : 'Deactivate',
                variant: activating ? 'success' : 'danger'
            }).then(function () {
                App.loading($btn, true, '');
                $.post($btn.data('url')).done(function (res) {
                    App.toast(res.message, 'success');
                    window.setTimeout(function () { window.location.reload(); }, 700);
                }).always(function () {
                    App.loading($btn, false);
                });
            }, function () { /* cancelled */ });
        });

        $(document).on('click', '.js-reset', function () {
            var $btn = $(this);
            App.confirm({
                title: 'Reset password?',
                message: 'A temporary password will be generated for "' + $btn.data('name') + '". Their current password stops working immediately.',
                confirmText: 'Generate password',
                variant: 'warning'
            }).then(function () {
                $.post($btn.data('url')).done(function (res) {
                    $('#tempPasswordUser').text($btn.data('name'));
                    $('#tempPasswordValue').val(res.data.temporary_password);
                    window.bootstrap.Modal.getOrCreateInstance(document.getElementById('tempPasswordModal')).show();
                });
            }, function () { /* cancelled */ });
        });

        $('#tempPasswordCopy').on('click', function () {
            var value = $('#tempPasswordValue').val();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(value).then(function () { App.toast('Copied to clipboard', 'success'); });
            } else {
                $('#tempPasswordValue').trigger('select');
                document.execCommand('copy');
                App.toast('Copied to clipboard', 'success');
            }
        });

        $('#tempPasswordModal').on('hidden.bs.modal', function () {
            $('#tempPasswordValue').val('');
        });

        $(document).on('click', '.js-unlock', function () {
            var $btn = $(this);
            $.post($btn.data('url')).done(function (res) {
                App.toast(res.message, 'success');
                $btn.closest('tr').find('.js-locked').remove();
                $btn.remove();
            });
        });
    });
})(window.jQuery, window.App);
