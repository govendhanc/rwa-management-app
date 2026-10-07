/* Collect Payment: owner search, dues, bill selection, live allocation preview, submit. */
(function ($, App) {
    'use strict';

    $(function () {
        var cfg = JSON.parse(document.getElementById('collectConfig').textContent);
        var $search = $('#ownerSearch');
        var $results = $('#searchResults');
        var dues = null;
        var searchTimer = null;
        var submitting = false;

        var toPaise = function (v) {
            var s = String(v || '').replace(/[,\s₹]/g, '');
            if (!/^\d+(\.\d{1,2})?$/.test(s)) {
                return null;
            }
            var parts = s.split('.');
            return Number(parts[0]) * 100 + Number(((parts[1] || '') + '00').slice(0, 2));
        };
        var fromPaise = function (p) { return App.money(p / 100); };

        /* ---------- Search ---------- */
        $search.on('input', function () {
            window.clearTimeout(searchTimer);
            var q = $.trim($search.val());
            if (q.length < 1) {
                $results.empty();
                return;
            }
            searchTimer = window.setTimeout(function () {
                $.get($search.data('url'), { q: q }).done(function (res) {
                    $results.empty();
                    if (!res.data.results.length) {
                        $results.append('<div class="list-group-item text-muted small">No owner found</div>');
                        return;
                    }
                    res.data.results.forEach(function (o) {
                        var $item = $('<button type="button" class="list-group-item list-group-item-action"></button>').data('id', o.id);
                        $item.append($('<div class="d-flex justify-content-between"></div>')
                            .append($('<strong></strong>').text(o.owner_name + ' (' + o.owner_code + ')'))
                            .append($('<span class="amount"></span>').text(o.outstanding)));
                        $item.append($('<div class="small text-muted"></div>').text('Plot ' + (o.plots || '-') + ' · ' + (o.house_nos || '-') + ' · ' + o.mobile +
                            (o.status !== 'Active' ? ' · ' + o.status : '') + (o.credit ? ' · credit ' + o.credit : '')));
                        $results.append($item);
                    });
                });
            }, 250);
        });

        $results.on('click', '.list-group-item-action', function () {
            loadOwner($(this).data('id'));
            $results.empty();
        });

        /* ---------- Owner & bills ---------- */
        var loadOwner = function (id) {
            $.get(cfg.duesUrl + id).done(function (res) {
                dues = res.data;
                var o = dues.owner;
                $('#owner_id').val(o.id);
                $('#ownerName').text(o.owner_name);
                $('#ownerMeta').text(o.owner_code + ' · ' + o.mobile + (o.status !== 'Active' ? ' · ' + o.status : ''));
                $('#ownerPlots').text(o.plots || '-');
                $('#ownerHouses').text(o.house_nos || '-');
                $('#ownerProfileLink').attr('href', cfg.ownerUrl + o.id);
                $('#dueOld').text(dues.previous_outstanding);
                $('#dueCurrent').text(dues.current_amount);
                $('#dueTotal').text(dues.total_payable);
                $('#dueCredit').text(dues.credit);
                $('#creditRow').toggleClass('d-none', !dues.credit);
                $('#lastPayment').text(o.last_payment);
                $search.val(o.owner_name);

                var rows = '';
                dues.bills.forEach(function (b) {
                    rows += '<tr><td><input class="form-check-input js-bill" type="checkbox" value="' + b.id + '" data-balance="' + App.escape(b.balance_value) +
                        '" aria-label="Select bill" checked></td><td>' + App.escape(b.month) + (b.overdue ? ' <span class="badge text-bg-danger">Overdue</span>' : '') +
                        '</td><td>' + App.escape(b.plot) + '</td><td class="text-end amount">' + App.escape(b.amount) + '</td><td class="text-end amount">' +
                        App.escape(b.paid) + '</td><td class="text-end amount fw-semibold">' + App.escape(b.balance) + '</td><td>' + App.escape(b.status) + '</td></tr>';
                });
                $('#billRows').html(rows || '<tr><td colspan="7" class="text-muted text-center py-3">No unpaid bills. Any payment will be kept as advance credit.</td></tr>');
                $('#allocAuto').prop('checked', true);
                syncAllocationMode();

                $('#amount').val(dues.total_payable_value !== '0.00' ? dues.total_payable_value : '');
                $('#is_advance').prop('checked', dues.bills.length === 0);
                $('#ownerCard, #billsCard, #paymentCard').removeClass('d-none');
                $('#placeholderCard').addClass('d-none');
                updatePreview();
                $('#amount').trigger('focus').trigger('select');
            });
        };

        var syncAllocationMode = function () {
            var selected = $('#allocSelected').is(':checked');
            $('#allocation').val(selected ? 'selected' : 'auto');
            $('.js-bill').prop('disabled', !selected);
            if (!selected) {
                $('.js-bill').prop('checked', true);
            }
            updatePreview();
        };
        $('input[name="allocation_ui"]').on('change', syncAllocationMode);
        $('#billRows').on('change', '.js-bill', updatePreviewDeferred);
        function updatePreviewDeferred() { updatePreview(); }

        /* ---------- Live allocation preview (mirrors the server rules) ---------- */
        function updatePreview() {
            if (!dues) {
                return;
            }
            var amount = toPaise($('#amount').val());
            var $box = $('#allocationPreview');
            var $hint = $('#amountHint').text('');
            if (amount === null || amount <= 0) {
                $box.html('<i class="fa-solid fa-circle-info me-1"></i>Enter the amount received.');
                return;
            }
            var targets = $('.js-bill:checked').map(function () {
                return { balance: toPaise($(this).data('balance')), label: $(this).closest('tr').find('td').eq(1).text() + ' / ' + $(this).closest('tr').find('td').eq(2).text() };
            }).get();
            var remaining = amount;
            var lines = [];
            targets.forEach(function (t) {
                if (remaining <= 0) {
                    return;
                }
                var use = Math.min(t.balance, remaining);
                remaining -= use;
                lines.push(App.escape(t.label) + ': ' + fromPaise(use) + (use < t.balance ? ' <span class="badge text-bg-warning">partial</span>' : ''));
            });
            var html = '<strong>This payment will be applied as:</strong><br>' + (lines.length ? lines.join('<br>') : 'No bills');
            if (remaining > 0) {
                if ($('#is_advance').is(':checked')) {
                    html += '<br><span class="text-success">Advance credit: ' + fromPaise(remaining) + '</span>';
                } else {
                    html += '<br><span class="text-danger">' + fromPaise(remaining) + ' is more than the dues. Tick "Advance payment" to keep it as credit, or reduce the amount.</span>';
                    $hint.html('<span class="text-danger">Exceeds dues by ' + fromPaise(remaining) + '</span>');
                }
            }
            $box.html(html);
        }
        $('#amount, #is_advance').on('input change', updatePreview);

        /* ---------- Reference required for non-cash ---------- */
        var syncMode = function () {
            var need = cfg.needRef.indexOf($('#payment_mode').val()) !== -1;
            $('#refLabel').html('Transaction Reference' + (need ? ' <span class="required">*</span>' : ''));
            $('#refHint').text($('#payment_mode').val() === 'Cheque' ? 'Cheque number and bank' : (need ? 'UTR / UPI transaction ID' : 'Optional'));
        };
        $('#payment_mode').on('change', syncMode);
        syncMode();

        /* ---------- Submit (AJAX, one click only) ---------- */
        $('#paymentForm').on('submit', function (e) {
            e.preventDefault();
            if (submitting) {
                return;
            }
            var $form = $(this);
            App.clearFormErrors($form);
            var $selected = $('#selectedBillInputs').empty();
            if ($('#allocation').val() === 'selected') {
                $('.js-bill:checked').each(function () {
                    $selected.append($('<input type="hidden" name="bill_ids[]">').val($(this).val()));
                });
                if (!$selected.children().length) {
                    App.toast('Select at least one bill, or choose "Oldest first".', 'warning');
                    return;
                }
            }
            submitting = true;
            App.loading($('#payBtn'), true, 'Saving payment...');
            $.ajax({ url: $form.attr('action'), type: 'POST', data: $form.serialize(), suppressGlobalError: true })
                .done(function (res) {
                    window.location.href = res.data.redirect;
                })
                .fail(function (xhr) {
                    submitting = false;
                    App.loading($('#payBtn'), false);
                    var r = xhr.responseJSON || {};
                    if (r.errors) {
                        App.showFormErrors($form, r.errors);
                    }
                    App.handleAjaxError(xhr);
                });
        });

        if (cfg.preselect) {
            loadOwner(cfg.preselect);
        }
    });
})(window.jQuery, window.App);
