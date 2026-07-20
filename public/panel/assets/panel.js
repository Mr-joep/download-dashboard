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

    // ---- storage: rename modal + delete confirmation -------------------------
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-rename-name]');
        if (!btn) return;
        var name = btn.dataset.renameName;
        document.getElementById('rename-name').value = name;
        var input = document.getElementById('rename-new-name');
        input.value = name;
        var modalEl = document.getElementById('rename-modal');
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        modalEl.addEventListener('shown.bs.modal', function () {
            input.focus();
            input.select();
        }, { once: true });
    });

    document.addEventListener('submit', function (e) {
        var form = e.target.closest('[data-confirm]');
        if (form && !window.confirm(form.dataset.confirm)) {
            e.preventDefault();
        }
    });

    // ---- sortable table columns ---------------------------------------------
    // <table data-sortable> + <th data-sort> (value taken from that column's
    // td[data-value], falling back to its text). Click toggles desc/asc,
    // starting with desc (high to low).
    document.querySelectorAll('table[data-sortable]').forEach(function (table) {
        var headers = table.querySelectorAll('th[data-sort]');
        headers.forEach(function (th) {
            th.addEventListener('click', function () {
                var dir = th.dataset.dir === 'desc' ? 'asc' : 'desc';
                headers.forEach(function (h) { delete h.dataset.dir; });
                th.dataset.dir = dir;

                var idx = Array.prototype.indexOf.call(th.parentNode.children, th);
                var tbody = table.tBodies[0];
                var rows = Array.prototype.slice.call(tbody.rows);
                rows.sort(function (a, b) {
                    var cellA = a.cells[idx], cellB = b.cells[idx];
                    var av = parseFloat((cellA.dataset.value ?? cellA.textContent).replace(/,/g, '')) || 0;
                    var bv = parseFloat((cellB.dataset.value ?? cellB.textContent).replace(/,/g, '')) || 0;
                    return dir === 'desc' ? bv - av : av - bv;
                });
                rows.forEach(function (r) { tbody.appendChild(r); });
            });
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

    // ---- upload: multi-file AJAX upload with a progress bar per file --------
    if (page === 'upload') {
        var form = document.getElementById('upload-form');
        if (form) {
            var fileInput = document.getElementById('file');
            var overwrite = document.getElementById('overwrite');
            var submitBtn = document.getElementById('upload-submit');
            var list = document.getElementById('upload-progress-list');
            var csrf = form.querySelector('[name="csrf"]').value;
            // Files above this size are split into pieces client-side (see upload.php,
            // which sizes this to fit under both the Cloudflare 100 MB request cap and
            // this server's PHP limits) and reassembled server-side once all arrive.
            var chunkSize = parseInt(fileInput.dataset.chunkSize, 10) || (45 * 1024 * 1024);

            var makeUploadId = function () {
                return Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
            };

            var setRowState = function (row, cls, text) {
                row.querySelector('.upload-status').textContent = text;
                row.querySelector('.progress-bar').classList.remove(
                    'bg-primary', 'bg-success', 'bg-danger'
                );
                row.querySelector('.progress-bar').classList.add(cls);
            };

            var buildRow = function (file) {
                var row = document.createElement('div');
                row.className = 'upload-row mb-2';

                var head = document.createElement('div');
                head.className = 'd-flex justify-content-between small mb-1';

                var nameEl = document.createElement('span');
                nameEl.className = 'text-truncate mw-path';
                nameEl.textContent = file.name;

                var statusEl = document.createElement('span');
                statusEl.className = 'upload-status text-secondary text-nowrap ms-2';
                statusEl.textContent = 'Waiting…';

                head.appendChild(nameEl);
                head.appendChild(statusEl);

                var bar = document.createElement('div');
                bar.className = 'progress';
                bar.style.height = '6px';
                var barInner = document.createElement('div');
                barInner.className = 'progress-bar bg-primary';
                barInner.setAttribute('role', 'progressbar');
                barInner.style.width = '0%';
                bar.appendChild(barInner);

                row.appendChild(head);
                row.appendChild(bar);
                list.appendChild(row);
                return row;
            };

            var uploadWhole = function (file, allowOverwrite, row) {
                return new Promise(function (resolve) {
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', 'upload.php', true);
                    xhr.upload.addEventListener('progress', function (e) {
                        if (!e.lengthComputable) return;
                        var pct = Math.round((e.loaded / e.total) * 100);
                        row.querySelector('.progress-bar').style.width = pct + '%';
                        setRowState(row, 'bg-primary', pct + '%');
                    });
                    xhr.onload = function () {
                        var data = null;
                        try { data = JSON.parse(xhr.responseText); } catch (err) { /* ignore */ }
                        if (data && data.success) {
                            row.querySelector('.progress-bar').style.width = '100%';
                            setRowState(row, 'bg-success', 'Done (' + data.size + ')');
                        } else {
                            setRowState(row, 'bg-danger', (data && data.error) || 'Upload failed');
                        }
                        resolve();
                    };
                    xhr.onerror = function () {
                        setRowState(row, 'bg-danger', 'Network error');
                        resolve();
                    };

                    var fd = new FormData();
                    fd.append('csrf', csrf);
                    fd.append('ajax', '1');
                    fd.append('overwrite', allowOverwrite ? '1' : '0');
                    fd.append('file', file);
                    xhr.send(fd);
                });
            };

            // Sends one chunk per request so no single request body ever
            // approaches the Cloudflare 100 MB limit, however large the file is.
            var uploadChunked = function (file, allowOverwrite, row) {
                var id = makeUploadId();
                var totalChunks = Math.ceil(file.size / chunkSize);
                var uploadedBytes = 0;

                var sendChunk = function (index) {
                    return new Promise(function (resolve, reject) {
                        var start = index * chunkSize;
                        var end = Math.min(start + chunkSize, file.size);
                        var blob = file.slice(start, end);

                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', 'upload.php', true);
                        xhr.upload.addEventListener('progress', function (e) {
                            if (!e.lengthComputable) return;
                            var pct = Math.round(((uploadedBytes + e.loaded) / file.size) * 100);
                            row.querySelector('.progress-bar').style.width = pct + '%';
                            setRowState(row, 'bg-primary', pct + '%');
                        });
                        xhr.onload = function () {
                            var data = null;
                            try { data = JSON.parse(xhr.responseText); } catch (err) { /* ignore */ }
                            if (!data || !data.success) {
                                reject((data && data.error) || 'Upload failed');
                                return;
                            }
                            uploadedBytes += (end - start);
                            resolve(data);
                        };
                        xhr.onerror = function () { reject('Network error'); };

                        var fd = new FormData();
                        fd.append('csrf', csrf);
                        fd.append('ajax', '1');
                        fd.append('chunked', '1');
                        fd.append('upload_id', id);
                        fd.append('chunk_index', String(index));
                        fd.append('total_chunks', String(totalChunks));
                        fd.append('filename', file.name);
                        fd.append('overwrite', allowOverwrite ? '1' : '0');
                        fd.append('chunk', blob, file.name);
                        xhr.send(fd);
                    });
                };

                var sendNext = function (index) {
                    return sendChunk(index).then(function (data) {
                        if (index === totalChunks - 1) {
                            row.querySelector('.progress-bar').style.width = '100%';
                            setRowState(row, 'bg-success', 'Done (' + data.size + ')');
                            return;
                        }
                        return sendNext(index + 1);
                    });
                };

                return sendNext(0).catch(function (err) {
                    setRowState(row, 'bg-danger', err || 'Upload failed');
                });
            };

            var uploadFile = function (file, allowOverwrite) {
                var row = buildRow(file);
                return file.size > chunkSize
                    ? uploadChunked(file, allowOverwrite, row)
                    : uploadWhole(file, allowOverwrite, row);
            };

            var skipRow = function (file) {
                var row = buildRow(file);
                setRowState(row, 'bg-secondary', 'Skipped (already exists)');
                row.querySelector('.progress-bar').style.width = '100%';
            };

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var files = Array.prototype.slice.call(fileInput.files);
                if (!files.length) return;

                list.textContent = '';
                list.classList.remove('d-none');
                fileInput.disabled = true;
                submitBtn.disabled = true;

                var checkExisting = overwrite.checked
                    ? Promise.resolve(files.map(function () { return false; }))
                    : Promise.all(files.map(function (file) {
                        return fetch('api.php?action=file_exists&name=' + encodeURIComponent(file.name))
                            .then(function (r) { return r.json(); })
                            .then(function (data) { return !!data.exists; })
                            .catch(function () { return false; });
                    }));

                checkExisting.then(function (existsFlags) {
                    var existingNames = files.filter(function (f, i) { return existsFlags[i]; })
                        .map(function (f) { return f.name; });

                    var overwriteExisting = overwrite.checked;
                    if (existingNames.length && !overwrite.checked) {
                        overwriteExisting = window.confirm(
                            'These files already exist:\n' + existingNames.join('\n') +
                            '\n\nOverwrite them? (Cancel skips them, other files still upload)'
                        );
                    }

                    var uploads = files.map(function (file, i) {
                        if (existsFlags[i] && !overwriteExisting) {
                            skipRow(file);
                            return Promise.resolve();
                        }
                        return uploadFile(file, existsFlags[i] ? overwriteExisting : overwrite.checked);
                    });

                    Promise.all(uploads).then(function () {
                        fileInput.disabled = false;
                        submitBtn.disabled = false;
                        fileInput.value = '';
                        setTimeout(function () { window.location.reload(); }, 900);
                    });
                });
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
