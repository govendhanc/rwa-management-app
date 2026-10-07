/* =====================================================================
   Dashboard charts (Chart.js 4)
   Colour = entity, fixed across charts: Collected/Collections = blue,
   Expenses = orange, Billed = aqua. Single-series charts carry no legend.
   No dual axes: collection % has its own chart. Each chart has a table view.
   ===================================================================== */
(function ($, App, Chart) {
    'use strict';

    if (!Chart) {
        return;
    }

    var COLORS = {
        collected: '#2a78d6',
        expenses: '#eb6834',
        billed: '#1baf7a',
        outstanding: '#4a3aa7',
        surface: '#ffffff',
        ink: '#0b0b0b',
        inkSecondary: '#52514e',
        muted: '#898781',
        grid: '#e1e0d9',
        axis: '#c3c2b7'
    };

    var payload;
    try {
        payload = JSON.parse(document.getElementById('dashboardData').textContent);
    } catch (e) {
        return;
    }

    var compactMoney = function (value) {
        var n = Number(value || 0);
        var abs = Math.abs(n);
        if (abs >= 10000000) { return App.currency + (n / 10000000).toFixed(1).replace(/\.0$/, '') + 'Cr'; }
        if (abs >= 100000) { return App.currency + (n / 100000).toFixed(1).replace(/\.0$/, '') + 'L'; }
        if (abs >= 1000) { return App.currency + (n / 1000).toFixed(1).replace(/\.0$/, '') + 'k'; }
        return App.currency + Math.round(n).toLocaleString('en-IN');
    };
    var pct = function (value) {
        return value === null || value === undefined ? '-' : Number(value).toFixed(1) + '%';
    };

    // Drop leading months that have no data so charts start where the history starts
    var trimLeading = function (rows, hasData) {
        var first = rows.findIndex(hasData);
        return first === -1 ? rows.slice(-1) : rows.slice(first);
    };

    /* ---------------- Global chart chrome ---------------- */
    Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = COLORS.muted;
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.animation.duration = 400;
    Chart.defaults.plugins.tooltip.backgroundColor = '#1f2a3a';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.boxPadding = 4;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.pointStyle = 'rectRounded';
    Chart.defaults.plugins.legend.labels.color = COLORS.inkSecondary;
    Chart.defaults.plugins.legend.align = 'start';

    /* Direct label: value of the LATEST point of each dataset only (never every point). */
    var lastValueLabel = {
        id: 'lastValueLabel',
        afterDatasetsDraw: function (chart, args, opts) {
            var ctx = chart.ctx;
            ctx.save();
            ctx.font = '600 11px ' + Chart.defaults.font.family;
            ctx.fillStyle = COLORS.inkSecondary;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'bottom';
            chart.data.datasets.forEach(function (ds, di) {
                var meta = chart.getDatasetMeta(di);
                if (meta.hidden) {
                    return;
                }
                for (var i = ds.data.length - 1; i >= 0; i--) {
                    if (ds.data[i] !== null && ds.data[i] !== undefined) {
                        var el = meta.data[i];
                        var text = opts.format ? opts.format(ds.data[i]) : String(ds.data[i]);
                        ctx.fillText(text, el.x, el.y - 6);
                        break;
                    }
                }
            });
            ctx.restore();
        }
    };
    Chart.register(lastValueLabel);

    var baseScales = function (yFormatter, yMax) {
        return {
            x: {
                grid: { display: false },
                border: { color: COLORS.axis },
                ticks: { color: COLORS.muted, maxRotation: 0, autoSkipPadding: 12 }
            },
            y: {
                beginAtZero: true,
                max: yMax,
                grace: '12%',
                grid: { color: COLORS.grid, lineWidth: 1 },
                border: { display: false },
                ticks: { color: COLORS.muted, maxTicksLimit: 6, callback: yFormatter }
            }
        };
    };

    var bar = function (label, data, color) {
        return {
            label: label,
            data: data,
            backgroundColor: color,
            hoverBackgroundColor: color,
            borderColor: COLORS.surface,
            borderWidth: 2,                 // 2px surface gap between adjacent bars
            borderSkipped: 'start',
            borderRadius: { topLeft: 4, topRight: 4 },
            maxBarThickness: 22,
            categoryPercentage: 0.72,
            barPercentage: 0.95
        };
    };

    var moneyTooltip = {
        mode: 'index',
        intersect: false,
        callbacks: {
            label: function (c) { return ' ' + c.dataset.label + ': ' + App.money(c.parsed.y); }
        }
    };

    var charts = {};

    /* ---------------- 1. Collections vs expenses (cash basis) ---------------- */
    var cash = trimLeading(payload.trend, function (r) { return Number(r.collections) > 0 || Number(r.expenses) > 0; });
    charts.cashflow = {
        rows: cash,
        columns: [
            { title: 'Month', value: function (r) { return r.label; } },
            { title: 'Collections', value: function (r) { return App.money(r.collections); }, align: 'end' },
            { title: 'Expenses', value: function (r) { return App.money(r.expenses); }, align: 'end' }
        ],
        chart: new Chart(document.getElementById('chartCashflow'), {
            type: 'bar',
            data: {
                labels: cash.map(function (r) { return r.label; }),
                datasets: [
                    bar('Collections', cash.map(function (r) { return Number(r.collections); }), COLORS.collected),
                    bar('Expenses', cash.map(function (r) { return Number(r.expenses); }), COLORS.expenses)
                ]
            },
            options: {
                scales: baseScales(compactMoney),
                interaction: { mode: 'index', intersect: false },
                plugins: { tooltip: moneyTooltip, lastValueLabel: { format: compactMoney } }
            }
        })
    };

    /* ---------------- 2. Billed vs collected (billing basis) ---------------- */
    var billing = trimLeading(payload.trend, function (r) { return Number(r.billed) > 0; });
    charts.billing = {
        rows: billing,
        columns: [
            { title: 'Billing month', value: function (r) { return r.label; } },
            { title: 'Billed', value: function (r) { return App.money(r.billed); }, align: 'end' },
            { title: 'Collected', value: function (r) { return App.money(r.collected); }, align: 'end' }
        ],
        chart: new Chart(document.getElementById('chartBilling'), {
            type: 'bar',
            data: {
                labels: billing.map(function (r) { return r.label; }),
                datasets: [
                    bar('Billed', billing.map(function (r) { return Number(r.billed); }), COLORS.billed),
                    bar('Collected', billing.map(function (r) { return Number(r.collected); }), COLORS.collected)
                ]
            },
            options: {
                scales: baseScales(compactMoney),
                interaction: { mode: 'index', intersect: false },
                plugins: { tooltip: moneyTooltip, lastValueLabel: { format: compactMoney } }
            }
        })
    };

    /* ---------------- 3. Collection % (single series, own axis 0-100) ---------------- */
    charts.percent = {
        rows: billing,
        columns: [
            { title: 'Billing month', value: function (r) { return r.label; } },
            { title: 'Collection %', value: function (r) { return pct(r.collection_pct); }, align: 'end' }
        ],
        chart: new Chart(document.getElementById('chartPercent'), {
            type: 'line',
            data: {
                labels: billing.map(function (r) { return r.label; }),
                datasets: [{
                    label: 'Collection %',
                    data: billing.map(function (r) { return r.collection_pct === null ? null : Number(r.collection_pct); }),
                    borderColor: COLORS.collected,
                    backgroundColor: COLORS.collected,
                    borderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    pointBorderColor: COLORS.surface,
                    pointBorderWidth: 2,
                    tension: 0.25,
                    spanGaps: false
                }]
            },
            options: {
                scales: baseScales(function (v) { return v + '%'; }, 100),
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: { label: function (c) { return ' Collected: ' + pct(c.parsed.y); } }
                    },
                    lastValueLabel: { format: pct }
                }
            }
        })
    };

    /* ---------------- 4. Outstanding by billing month (single series) ---------------- */
    charts.outstanding = {
        rows: billing,
        columns: [
            { title: 'Billing month', value: function (r) { return r.label; } },
            { title: 'Outstanding', value: function (r) { return App.money(r.outstanding); }, align: 'end' }
        ],
        chart: new Chart(document.getElementById('chartOutstanding'), {
            type: 'bar',
            data: {
                labels: billing.map(function (r) { return r.label; }),
                datasets: [bar('Outstanding', billing.map(function (r) { return Number(r.outstanding); }), COLORS.outstanding)]
            },
            options: {
                scales: baseScales(compactMoney),
                plugins: {
                    legend: { display: false },
                    tooltip: moneyTooltip,
                    lastValueLabel: { format: compactMoney }
                }
            }
        })
    };

    /* ---------------- Table view toggle (accessibility / exact values) ---------------- */
    var buildTable = function (def) {
        var html = '<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr>';
        def.columns.forEach(function (c) {
            html += '<th' + (c.align === 'end' ? ' class="text-end"' : '') + '>' + App.escape(c.title) + '</th>';
        });
        html += '</tr></thead><tbody>';
        def.rows.forEach(function (r) {
            html += '<tr>';
            def.columns.forEach(function (c) {
                html += '<td' + (c.align === 'end' ? ' class="text-end amount"' : '') + '>' + App.escape(c.value(r)) + '</td>';
            });
            html += '</tr>';
        });
        return html + '</tbody></table></div>';
    };

    $(document).on('click', '.js-chart-table', function () {
        var $card = $(this).closest('.chart-card');
        var def = charts[$card.data('chart')];
        var $table = $card.find('.chart-table');
        var showTable = $table.hasClass('d-none');
        if (showTable && !$table.data('built')) {
            $table.html(buildTable(def)).data('built', true);
        }
        $table.toggleClass('d-none', !showTable);
        $card.find('.chart-box').toggleClass('d-none', showTable);
        $(this).attr('aria-pressed', showTable ? 'true' : 'false')
            .html(showTable ? '<i class="fa-solid fa-chart-column me-1"></i>Chart' : '<i class="fa-solid fa-table me-1"></i>Table');
    });
})(window.jQuery, window.App, window.Chart);
