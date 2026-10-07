/* Audit log: server-side DataTable with filters and a detail modal (before / after values). */
(function ($, App) {
    'use strict';

    $(function () {
        var $table = $('#auditTable');
        if (!$table.length) {
            return;
        }
        var table = App.dataTable('#auditTable', {
            exportTitle: 'Audit log',
            serverSide: true,
            processing: true,
            searchDelay: 400,
            order: [[0, 'desc']],
            ajax: {
                url: $table.data('url'),
                data: function (d) {
                    d.from = $('#af_from').val();
                    d.to = $('#af_to').val();
                    d.module = $('#af_module').val();
                    d.user_id = $('#af_user').val();
                },
                suppressGlobalError: false
            },
            columns: [
                { data: 'id' },
                { data: 'created_at', orderable: false },
                { data: 'user', orderable: false, render: $.fn.dataTable.render.text() },
                { data: 'action', orderable: false, render: $.fn.dataTable.render.text() },
                { data: 'module', orderable: false, render: $.fn.dataTable.render.text() },
                { data: 'record_id', orderable: false, render: $.fn.dataTable.render.text() },
                { data: 'ip', orderable: false, render: $.fn.dataTable.render.text() },
                {
                    data: null, orderable: false, searchable: false, className: 'text-end',
                    render: function (row) {
                        return row.has_detail ? '<button type="button" class="btn btn-sm btn-outline-secondary js-audit-detail" data-id="' + row.id + '">Details</button>' : '';
                    }
                }
            ]
        });

        $('#af_apply').on('click', function () { table.ajax.reload(); });

        $table.on('click', '.js-audit-detail', function () {
            $.get($table.data('detail-url') + $(this).data('id')).done(function (res) {
                var d = res.data;
                $('#auditModalTitle').text(d.action + ' (' + d.module + (d.record_id ? ' #' + d.record_id : '') + ')');
                $('#auditMeta').text(d.created_at + ' · ' + (d.user || 'system') + ' · ' + (d.ip || '') + ' · ' + (d.user_agent || ''));
                var rows = '';
                d.changes.forEach(function (c) {
                    rows += '<tr><td class="fw-semibold">' + App.escape(c.field) + '</td><td class="text-danger text-break">' + App.escape(c.old) +
                        '</td><td class="text-success text-break">' + App.escape(c.new) + '</td></tr>';
                });
                $('#auditChanges').html(rows || '<tr><td colspan="3" class="text-muted">No field details</td></tr>');
                window.bootstrap.Modal.getOrCreateInstance(document.getElementById('auditModal')).show();
            });
        });
    });
})(window.jQuery, window.App);
