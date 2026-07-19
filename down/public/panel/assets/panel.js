/* Panel behaviour - vanilla JS only.
   Chart colors come from the validated dark palette (see assets/style.css):
   blue #3987e5 (downloads), green #008300 (known bots), yellow #c98500
   (suspicious), red #e66767 (404s) on the #1a1a19 card surface. */
(function () {
    'use strict';

    var page = document.body.dataset.page || '';

    // ---- clipboard copy buttons -------------------------------------------
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-copy]');
        if (!btn) return;
        navigator.clipboard.writeText(btn.dataset.copy).then(function () {
            var old = btn.textContent;
            btn.textContent = 'Copied';
            setTimeout(function () { btn.textContent = old; }, 1200);
        });
    });

    // ---- live visitors: refresh every 5 seconds ---------------------------
    if (page === 'live') {
        var tbody = document.getElementById('live-rows');
        var updated = document.getElementById('live-updated');

        var cell = function (tr, text, cls) {
            var td = document.createElement('td');
            if (cls) td.className = cls;
            td.textContent = text;
            tr.appendChild(td);
            return td;
        };

        var render = function (data) {
            tbody.textContent = '';
            if (!data.rows.length) {
                var tr = document.createElement('tr');
                var td = cell(tr, 'No active downloads.', 'text-center text-secondary py-4');
                td.colSpan = 5;
                tbody.appendChild(tr);
            }
            data.rows.forEach(function (r) {
                var tr = document.createElement('tr');
                cell(tr, r.started + ' (' + r.ago + ')', 'text-nowrap');
                cell(tr, r.file);
                cell(tr, r.ip, 'text-nowrap');
                cell(tr, r.size, 'text-nowrap');
                var td = document.createElement('td');
                var badge = document.createElement('span');
                badge.className = 'badge text-bg-warning';
                badge.textContent = 'In progress';
                td.appendChild(badge);
                tr.appendChild(td);
                tbody.appendChild(tr);
            });
            if (updated) updated.textContent = '- updated ' + data.updated;
        };

        var refresh = function () {
            fetch('api.php?action=live')
                .then(function (r) { return r.json(); })
                .then(render)
                .catch(function () { /* keep the last table on network errors */ });
        };
        setInterval(refresh, 5000);
        refresh();
    }

    // ---- upload: overwrite confirmation ------------------------------------
    if (page === 'upload') {
        var form = document.getElementById('upload-form');
        if (form) {
            var confirmed = false;
            form.addEventListener('submit', function (e) {
                var fileInput = document.getElementById('file');
                var overwrite = document.getElementById('overwrite');
                if (confirmed || overwrite.checked || !fileInput.files.length) return;

                e.preventDefault();
                var name = fileInput.files[0].name;
                fetch('api.php?action=file_exists&name=' + encodeURIComponent(name))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.exists &&
                            !window.confirm('"' + data.name + '" already exists. Overwrite it?')) {
                            return;
                        }
                        if (data.exists) overwrite.checked = true;
                        confirmed = true;
                        form.submit();
                    })
                    .catch(function () { confirmed = true; form.submit(); });
            });
        }
    }

    // ---- statistics charts --------------------------------------------------
    if (page === 'statistics' && window.Chart) {
        var C = {
            blue: '#3987e5',
            green: '#008300',
            yellow: '#c98500',
            red: '#e66767',
            ink: '#c3c2b7',
            muted: '#898781',
            grid: '#2c2c2a'
        };

        Chart.defaults.color = C.muted;
        Chart.defaults.borderColor = C.grid;
        Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", sans-serif';
        Chart.defaults.animation = false;

        var baseOptions = function () {
            return {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10 }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: C.grid },
                        border: { display: false },
                        ticks: { precision: 0 }
                    }
                }
            };
        };

        var barChart = function (id, labels, data, label, color) {
            new Chart(document.getElementById(id), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: data,
                        backgroundColor: color,
                        borderRadius: 4,
                        maxBarThickness: 18,
                        categoryPercentage: 0.8,
                        barPercentage: 0.9
                    }]
                },
                options: baseOptions()
            });
        };

        var hbarChart = function (id, labels, data, label, color) {
            var options = baseOptions();
            options.indexAxis = 'y';
            options.scales = {
                x: {
                    beginAtZero: true,
                    grid: { color: C.grid },
                    border: { display: false },
                    ticks: { precision: 0 }
                },
                y: { grid: { display: false } }
            };
            new Chart(document.getElementById(id), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: label,
                        data: data,
                        backgroundColor: color,
                        borderRadius: 4,
                        maxBarThickness: 16,
                        categoryPercentage: 0.8,
                        barPercentage: 0.9
                    }]
                },
                options: options
            });
        };

        // Direct series labels at the line ends (ink color, not series color),
        // with a small nudge when the two ends would collide.
        var endLabels = {
            id: 'endLabels',
            afterDatasetsDraw: function (chart) {
                var ctx = chart.ctx;
                var ends = [];
                chart.data.datasets.forEach(function (ds, i) {
                    var meta = chart.getDatasetMeta(i);
                    var last = meta.data[meta.data.length - 1];
                    if (last) ends.push({ x: last.x, y: last.y, label: ds.label });
                });
                if (ends.length === 2 && Math.abs(ends[0].y - ends[1].y) < 14) {
                    var top = ends[0].y <= ends[1].y ? 0 : 1;
                    ends[top].y -= 7;
                    ends[1 - top].y += 7;
                }
                ctx.save();
                ctx.font = '12px system-ui, sans-serif';
                ctx.fillStyle = C.ink;
                ctx.textAlign = 'left';
                ctx.textBaseline = 'middle';
                ends.forEach(function (p) { ctx.fillText(p.label, p.x + 8, p.y); });
                ctx.restore();
            }
        };

        var botChart = function (id, bots) {
            var options = baseOptions();
            options.plugins.legend = {
                display: true,
                labels: { usePointStyle: true, boxWidth: 8, color: C.ink }
            };
            options.interaction = { mode: 'index', intersect: false };
            options.layout = { padding: { right: 84 } };
            new Chart(document.getElementById(id), {
                type: 'line',
                data: {
                    labels: bots.labels,
                    datasets: [
                        {
                            label: 'Known bots',
                            data: bots.known,
                            borderColor: C.green,
                            backgroundColor: C.green,
                            pointStyle: 'circle',
                            borderWidth: 2,
                            pointRadius: 2.5,
                            pointHoverRadius: 5,
                            pointHitRadius: 12
                        },
                        {
                            label: 'Suspicious',
                            data: bots.suspicious,
                            borderColor: C.yellow,
                            backgroundColor: C.yellow,
                            pointStyle: 'rect',
                            borderWidth: 2,
                            pointRadius: 2.5,
                            pointHoverRadius: 5,
                            pointHitRadius: 12
                        }
                    ]
                },
                options: options,
                plugins: [endLabels]
            });
        };

        fetch('api.php?action=charts')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                barChart('chart-daily', d.daily.labels, d.daily.counts, 'Downloads', C.blue);
                barChart('chart-monthly', d.monthly.labels, d.monthly.counts, 'Downloads', C.blue);
                hbarChart('chart-top-files', d.topFiles.labels, d.topFiles.counts, 'Downloads', C.blue);
                hbarChart('chart-top-ips', d.topIps.labels, d.topIps.counts, 'Downloads', C.blue);
                barChart('chart-404', d.notFound.labels, d.notFound.counts, '404 requests', C.red);
                botChart('chart-bots', d.bots);
            });
    }
})();
