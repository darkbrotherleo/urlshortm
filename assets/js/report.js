/* Báo cáo — dựng biểu đồ từ dữ liệu click_events (GĐ2). */
(function () {
    'use strict';

    var dataEl = document.getElementById('report-data');
    if (!dataEl) return;

    var report;
    try {
        report = JSON.parse(dataEl.textContent || '{}');
    } catch (e) {
        return;
    }

    var colors = ['#FF6B4A', '#FF8E6E', '#FFB199', '#2E9E6B', '#2563EB', '#E8A13C', '#8B5CF6', '#14B8A6', '#F43F5E', '#64748B'];
    var base = window.Chart ? Chart.defaults : null;
    if (base) {
        base.color = '#8A7B78';
        base.font.family = 'Lexend, system-ui, sans-serif';
    }

    function labels(list) {
        return list.map(function (d) { return d.label; });
    }

    function values(list) {
        return list.map(function (d) { return d.count; });
    }

    function makePie(canvasId, list) {
        var el = document.getElementById(canvasId);
        if (!el) return;
        if (!list || list.length === 0) return;
        new Chart(el.getContext('2d'), {
            type: 'pie',
            data: {
                labels: labels(list),
                datasets: [{
                    data: values(list),
                    backgroundColor: colors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14 } }
                }
            }
        });
    }

    function makeBar(canvasId, list, horizontal) {
        var el = document.getElementById(canvasId);
        if (!el) return;
        if (!list || list.length === 0) return;
        new Chart(el.getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels(list),
                datasets: [{
                    data: values(list),
                    backgroundColor: colors
                }]
            },
            options: {
                indexAxis: horizontal ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // Theo ngày (line)
    var dayEl = document.getElementById('chart-day');
    if (dayEl && report.byDay && report.byDay.length > 0) {
        new Chart(dayEl.getContext('2d'), {
            type: 'line',
            data: {
                labels: labels(report.byDay),
                datasets: [{
                    label: 'Lượt mở',
                    data: values(report.byDay),
                    borderColor: '#FF6B4A',
                    backgroundColor: 'rgba(255,107,74,0.15)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    }

    makePie('chart-device', report.byDevice);
    makePie('chart-browser', report.byBrowser);
    makePie('chart-os', report.byOs);
    makePie('chart-country', report.byCountry);
    makeBar('chart-referrer', report.byReferrer, true);
    makeBar('chart-top', report.topLinks, true);

    // GĐ4: nhân khẩu học (Meta)
    if (report.demographics) {
        makeBar('chart-demo-age', report.demographics.age || [], false);
        makePie('chart-demo-gender', report.demographics.gender || []);
    }
})();
