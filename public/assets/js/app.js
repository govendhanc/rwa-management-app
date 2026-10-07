/* =====================================================================
   ROWA Portal - shared front-end behaviour
   - CSRF token on every jQuery AJAX POST (CodeIgniter 3 checks the POST field)
   - Toasts, confirmation dialog, button loading state, double-submit guard
   - Global AJAX error handling (session timeout, permission, CSRF)
   - DataTables defaults with Excel / CSV / PDF / Print export
   - Mobile sidebar
   ===================================================================== */
(function (window, $) {
    'use strict';

    var meta = function (name) {
        var el = document.querySelector('meta[name="' + name + '"]');
        return el ? el.getAttribute('content') : '';
    };

    var App = {
        baseUrl: meta('base-url'),
        currency: meta('currency-symbol') || '₹',
        waMode: meta('wa-mode') || 'click_to_chat'
    };

    /* ---------------------------------------------------------------
       URLs & CSRF
       ------------------------------------------------------------ */
    App.url = function (path) {
        return App.baseUrl.replace(/\/+$/, '') + '/' + String(path || '').replace(/^\/+/, '');
    };

    App.csrf = function () {
        var cookieName = meta('csrf-cookie');
        var match = cookieName ? document.cookie.match(new RegExp('(?:^|; )' + cookieName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)')) : null;
        return {
            name: meta('csrf-name'),
            hash: match ? decodeURIComponent(match[1]) : meta('csrf-hash')
        };
    };

    if ($) {
        $.ajaxPrefilter(function (options) {
            var type = (options.type || options.method || 'GET').toUpperCase();
            if (type !== 'POST') {
                return;
            }
            var token = App.csrf();
            if (!token.name) {
                return;
            }
            if (window.FormData && options.data instanceof FormData) {
                if (!options.data.has(token.name)) {
                    options.data.append(token.name, token.hash);
                }
            } else if (typeof options.data === 'string' || options.data === undefined || options.data === null) {
                var data = options.data || '';
                if (data.indexOf(encodeURIComponent(token.name) + '=') === -1) {
                    options.data = data + (data ? '&' : '') + encodeURIComponent(token.name) + '=' + encodeURIComponent(token.hash);
                }
            } else if ($.isPlainObject(options.data)) {
                options.data[token.name] = token.hash;
                options.data = $.param(options.data);
            }
        });

        $.ajaxSetup({
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            dataType: 'json'
        });

        $(document).ajaxError(function (event, xhr, settings) {
            if (settings.suppressGlobalError) {
                return;
            }
            App.handleAjaxError(xhr);
        });
    }

    App.handleAjaxError = function (xhr) {
        var response = xhr.responseJSON || {};
        if (xhr.status === 401) {
            App.toast(response.message || 'Your session has expired. Please log in again.', 'warning');
            window.setTimeout(function () {
                window.location.href = response.redirect || App.url('auth/login?timeout=1');
            }, 1500);
            return;
        }
        if (xhr.status === 403 && !response.message) {
            App.toast('The request was blocked (security token expired). Please refresh the page and try again.', 'error');
            return;
        }
        if (xhr.status === 0) {
            App.toast('Network error. Please check your connection.', 'error');
            return;
        }
        App.toast(response.message || 'Something went wrong. Please try again.', 'error');
    };

    /* ---------------------------------------------------------------
       Formatting
       ------------------------------------------------------------ */
    App.escape = function (value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    App.money = function (amount, withSymbol) {
        var n = Number(amount || 0);
        var formatted = n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return (withSymbol === false ? '' : App.currency) + formatted;
    };

    /* ---------------------------------------------------------------
       Toasts
       ------------------------------------------------------------ */
    var toastStyles = {
        success: { bg: 'text-bg-success', icon: 'fa-circle-check' },
        error: { bg: 'text-bg-danger', icon: 'fa-circle-exclamation' },
        warning: { bg: 'text-bg-warning', icon: 'fa-triangle-exclamation' },
        info: { bg: 'text-bg-primary', icon: 'fa-circle-info' }
    };

    App.toast = function (message, type) {
        var container = document.getElementById('toastContainer');
        if (!container || !window.bootstrap) {
            window.alert(message);
            return;
        }
        var style = toastStyles[type] || toastStyles.info;
        var el = document.createElement('div');
        el.className = 'toast align-items-center border-0 ' + style.bg;
        el.setAttribute('role', type === 'error' ? 'alert' : 'status');
        el.innerHTML = '<div class="d-flex"><div class="toast-body"><i class="fa-solid ' + style.icon + ' me-2"></i>' +
            App.escape(message) + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
        container.appendChild(el);
        var toast = new window.bootstrap.Toast(el, { delay: type === 'error' ? 7000 : 4000 });
        el.addEventListener('hidden.bs.toast', function () { el.remove(); });
        toast.show();
    };

    /* ---------------------------------------------------------------
       Confirmation dialog -> Promise<string|true>
       App.confirm({ title, message, confirmText, variant, requireReason, reasonLabel })
       Resolves with the reason (when requireReason) or true; rejects on cancel.
       ------------------------------------------------------------ */
    App.confirm = function (opts) {
        opts = opts || {};
        return new Promise(function (resolve, reject) {
            var modalEl = document.getElementById('confirmModal');
            if (!modalEl || !window.bootstrap) {
                if (window.confirm(opts.message || 'Are you sure?')) {
                    resolve(true);
                } else {
                    reject();
                }
                return;
            }
            var okBtn = document.getElementById('confirmModalOk');
            var reasonWrap = document.getElementById('confirmModalReasonWrap');
            var reasonInput = document.getElementById('confirmModalReason');

            document.getElementById('confirmModalTitle').textContent = opts.title || 'Please confirm';
            document.getElementById('confirmModalMessage').textContent = opts.message || 'Are you sure?';
            document.getElementById('confirmModalReasonLabel').textContent = opts.reasonLabel || 'Reason';
            okBtn.textContent = opts.confirmText || 'Confirm';
            okBtn.className = 'btn btn-' + (opts.variant || 'danger');
            reasonWrap.classList.toggle('d-none', !opts.requireReason);
            reasonInput.value = '';
            reasonInput.classList.remove('is-invalid');

            var modal = window.bootstrap.Modal.getOrCreateInstance(modalEl);
            var confirmed = false;

            var onOk = function () {
                if (opts.requireReason) {
                    var reason = reasonInput.value.trim();
                    if (reason.length < 3) {
                        reasonInput.classList.add('is-invalid');
                        reasonInput.focus();
                        return;
                    }
                    confirmed = reason;
                } else {
                    confirmed = true;
                }
                modal.hide();
            };
            var onHidden = function () {
                okBtn.removeEventListener('click', onOk);
                modalEl.removeEventListener('hidden.bs.modal', onHidden);
                if (confirmed !== false) {
                    resolve(confirmed);
                } else {
                    reject();
                }
            };
            okBtn.addEventListener('click', onOk);
            modalEl.addEventListener('hidden.bs.modal', onHidden);
            modal.show();
        });
    };

    /* ---------------------------------------------------------------
       Button loading state
       ------------------------------------------------------------ */
    App.loading = function (button, isLoading, text) {
        var $btn = $(button);
        if (!$btn.length) {
            return;
        }
        if (isLoading) {
            if ($btn.attr('data-loading') === 'true') {
                return;
            }
            $btn.data('original-html', $btn.html());
            $btn.attr('data-loading', 'true').prop('disabled', true)
                .html('<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>' + App.escape(text || 'Please wait...'));
        } else {
            $btn.removeAttr('data-loading').prop('disabled', false).html($btn.data('original-html'));
        }
    };

    /* ---------------------------------------------------------------
       Show server-side validation errors on a form
       errors: { field_name: "message" }
       ------------------------------------------------------------ */
    App.showFormErrors = function (form, errors) {
        var $form = $(form);
        $form.find('.is-invalid').removeClass('is-invalid');
        $form.find('.invalid-feedback.js-error').remove();
        $.each(errors || {}, function (field, message) {
            var $input = $form.find('[name="' + field + '"]');
            if (!$input.length) {
                return;
            }
            $input.addClass('is-invalid');
            var $feedback = $('<div class="invalid-feedback js-error"></div>').text(message);
            var $group = $input.closest('.input-group');
            ($group.length ? $group : $input).after($feedback);
        });
        var $first = $form.find('.is-invalid').first();
        if ($first.length) {
            $first.trigger('focus');
        }
    };

    App.clearFormErrors = function (form) {
        $(form).find('.is-invalid').removeClass('is-invalid');
        $(form).find('.invalid-feedback.js-error').remove();
    };

    /* ---------------------------------------------------------------
       DataTables defaults
       ------------------------------------------------------------ */
    App.exportButtons = function (title, columns) {
        var exportOptions = { columns: columns || ':visible:not(.no-export)' };
        return [
            { extend: 'excelHtml5', text: '<i class="fa-solid fa-file-excel me-1"></i>Excel', className: 'btn btn-sm btn-outline-success', title: title, exportOptions: exportOptions },
            { extend: 'csvHtml5', text: '<i class="fa-solid fa-file-csv me-1"></i>CSV', className: 'btn btn-sm btn-outline-secondary', title: title, exportOptions: exportOptions },
            { extend: 'pdfHtml5', text: '<i class="fa-solid fa-file-pdf me-1"></i>PDF', className: 'btn btn-sm btn-outline-danger', title: title, orientation: 'landscape', pageSize: 'A4', exportOptions: exportOptions },
            { extend: 'print', text: '<i class="fa-solid fa-print me-1"></i>Print', className: 'btn btn-sm btn-outline-primary', title: title, exportOptions: exportOptions }
        ];
    };

    App.dataTable = function (selector, options) {
        options = options || {};
        var title = options.exportTitle || document.title;
        delete options.exportTitle;
        var defaults = {
            responsive: true,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
            autoWidth: false,
            dom: "<'row g-2 align-items-center mb-2'<'col-md-6'B><'col-md-6'f>>" +
                 "<'row'<'col-12'tr>>" +
                 "<'row g-2 align-items-center mt-2'<'col-md-4'l><'col-md-4'i><'col-md-4'p>>",
            // dom.button.className replaces Buttons' default "btn btn-secondary" so our outline styles apply
            buttons: {
                dom: { button: { className: 'btn' } },
                buttons: options.noExport ? [] : App.exportButtons(title, options.exportColumns)
            },
            language: {
                search: '',
                searchPlaceholder: 'Search...',
                lengthMenu: 'Show _MENU_',
                emptyTable: 'No records found',
                zeroRecords: 'No matching records found',
                processing: '<span class="spinner-border spinner-border-sm me-2"></span>Loading...'
            }
        };
        delete options.noExport;
        delete options.exportColumns;
        return $(selector).DataTable($.extend(true, {}, defaults, options));
    };

    /* ---------------------------------------------------------------
       DOM ready
       ------------------------------------------------------------ */
    $(function () {
        // Flash messages rendered by the server
        var flashEl = document.getElementById('flashData');
        if (flashEl) {
            try {
                JSON.parse(flashEl.textContent || '[]').forEach(function (item) {
                    App.toast(item.message, item.type);
                });
            } catch (e) { /* ignore malformed flash data */ }
        }

        // Mobile sidebar
        $('#sidebarToggle').on('click', function () {
            $('body').toggleClass('sidebar-open');
        });
        $('#sidebarBackdrop').on('click', function () {
            $('body').removeClass('sidebar-open');
        });

        // Prevent double submission of normal (non-AJAX) forms
        $(document).on('submit', 'form:not([data-ajax])', function () {
            var $form = $(this);
            if ($form.data('submitted')) {
                return false;
            }
            $form.data('submitted', true);
            var $btn = $form.find('[type="submit"]').first();
            if ($btn.length && !$btn.is('[data-no-loading]')) {
                App.loading($btn, true, $btn.data('loading-text'));
            }
            return true;
        });

        // Confirm before following links/submitting forms marked with data-confirm
        $(document).on('click', '[data-confirm]', function (e) {
            var $el = $(this);
            if ($el.data('confirmed')) {
                return true;
            }
            e.preventDefault();
            App.confirm({
                title: $el.data('confirm-title') || 'Please confirm',
                message: $el.data('confirm'),
                confirmText: $el.data('confirm-button') || 'Yes, continue',
                variant: $el.data('confirm-variant') || 'danger'
            }).then(function () {
                $el.data('confirmed', true);
                if ($el.is('a')) {
                    window.location.href = $el.attr('href');
                } else {
                    $el.trigger('click');
                }
            }, function () { /* cancelled */ });
            return false;
        });

        // Notifications dropdown (loaded on open)
        $('#notificationBell').on('show.bs.dropdown', function () {
            var $list = $('#notificationMenu .notification-list');
            var url = $list.data('url');
            if (!url || $list.data('loaded')) {
                return;
            }
            $.ajax({ url: url, type: 'GET', suppressGlobalError: true }).done(function (res) {
                $list.data('loaded', true);
                if (!res || !res.data || !res.data.items || !res.data.items.length) {
                    return;
                }
                $list.empty();
                res.data.items.forEach(function (n) {
                    var $a = $('<a class="dropdown-item notification-item py-2"></a>')
                        .attr('href', n.link ? App.url(n.link) : '#');
                    $a.append($('<div class="small"></div>').addClass(n.is_read ? '' : 'fw-semibold').text(n.title));
                    $a.append($('<div class="small text-muted"></div>').text(n.message || ''));
                    $a.append($('<div class="small text-muted"></div>').text(n.created_at_label || ''));
                    $list.append($a);
                });
                if (res.data.unread > 0) {
                    $.ajax({ url: App.url('notifications/mark-read'), type: 'POST', suppressGlobalError: true }).done(function () {
                        $('#notificationBell .topbar-badge').remove();
                    });
                }
            });
        });

        // Show / hide password fields
        $(document).on('click', '[data-toggle-password]', function () {
            var $input = $($(this).data('toggle-password'));
            var show = $input.attr('type') === 'password';
            $input.attr('type', show ? 'text' : 'password');
            $(this).find('i').toggleClass('fa-eye', !show).toggleClass('fa-eye-slash', show);
            $(this).attr('aria-label', show ? 'Hide password' : 'Show password');
        });

        // Bootstrap tooltips
        if (window.bootstrap) {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
                new window.bootstrap.Tooltip(el);
            });
        }
    });

    window.App = App;
})(window, window.jQuery);
