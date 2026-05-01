<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Dashboard — QA Management System</title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" />
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />

    <style>
        /* ─── Page shell ─────────────────────────────────────────────── */
        body { background: #f4f6fb; font-family: 'Inter', 'Segoe UI', sans-serif; }

        /* ─── Stat cards ─────────────────────────────────────────────── */
        .stat-card {
            display: flex; align-items: center; gap: 14px;
            background: #fff; border-radius: 12px;
            padding: 18px 20px; height: 100%;
            box-shadow: 0 1px 4px rgba(0,0,0,.07);
            transition: box-shadow .15s;
        }
        .stat-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.10); }
        .stat-card-link { text-decoration: none; color: inherit; display: block; height: 100%; }
        .stat-card-clickable { cursor: pointer; }
        .stat-card-critical { border: 1.5px solid #fecaca; }

        .stat-icon {
            width: 44px; height: 44px; border-radius: 10px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.3rem;
        }
        .stat-icon.blue   { background: #eff6ff; color: #2563eb; }
        .stat-icon.cyan   { background: #ecfeff; color: #0891b2; }
        .stat-icon.violet { background: #f5f3ff; color: #7c3aed; }
        .stat-icon.amber  { background: #fffbeb; color: #d97706; }
        .stat-icon.green  { background: #f0fdf4; color: #16a34a; }
        .stat-icon.red    { background: #fef2f2; color: #dc2626; }

        .stat-value { font-size: 1.55rem; font-weight: 700; line-height: 1.2; color: #111827; }
        .stat-label { font-size: .78rem; color: #6b7280; margin-top: 2px; }
        .stat-inline-alert { display: block; font-size: .72rem; color: #dc2626; margin-top: 2px; }

        /* ─── Section heads ──────────────────────────────────────────── */
        .dashboard-section-head { margin-bottom: 16px; }
        .dashboard-section-head h3 { font-size: 1.05rem; font-weight: 700; color: #111827; margin: 0; }
        .dashboard-section-head p  { font-size: .83rem; color: #6b7280; margin: 2px 0 0; }

        /* ─── QA cards ───────────────────────────────────────────────── */
        .qa-card {
            background: #fff; border-radius: 12px;
            padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.07);
        }
        .qa-card-title { font-size: .9rem; font-weight: 700; color: #374151; margin-bottom: 14px; }

        /* ─── Charts ─────────────────────────────────────────────────── */
        .chart-wrapper { position: relative; height: 220px; }

        /* ─── Quick actions ──────────────────────────────────────────── */
        .quick-actions-grid {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 8px;
        }
        .qa-quick-action {
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            gap: 8px; padding: 18px 10px; border-radius: 10px;
            background: #f9fafb; border: 1.5px solid #e5e7eb;
            text-decoration: none; color: #374151;
            transition: background .15s, border-color .15s;
        }
        .qa-quick-action:hover { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
        .qa-quick-action-icon { font-size: 1.4rem; }
        .qa-quick-action-label { font-size: .78rem; font-weight: 600; }

        /* ─── Tables ─────────────────────────────────────────────────── */
        .qa-table th { font-size: .75rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; border-bottom: 1.5px solid #e5e7eb; }
        .qa-table td { font-size: .83rem; border-color: #f3f4f6; vertical-align: middle; }
        .fw-600 { font-weight: 600; }
        .mono { font-family: 'Fira Mono', monospace; font-size: .8rem; }
        .text-muted-qa { color: #9ca3af; }
        .table-empty-cell { text-align: center; color: #9ca3af; font-size: .83rem; padding: 20px 0; }

        /* ─── Badges ─────────────────────────────────────────────────── */
        .badge-status {
            display: inline-block; padding: 2px 9px; border-radius: 99px;
            font-size: .72rem; font-weight: 600;
        }
        .badge-active  { background: #d1fae5; color: #065f46; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-closed  { background: #fee2e2; color: #991b1b; }
        .badge-draft   { background: #fde8d8; color: #9a3412; }
        .badge-compact { padding: 2px 7px; font-size: .68rem; }

        /* ─── Buttons ────────────────────────────────────────────────── */
        .btn-qa { display: inline-flex; align-items: center; gap: 5px; border-radius: 7px; font-size: .8rem; font-weight: 600; padding: 5px 12px; cursor: pointer; text-decoration: none; transition: background .15s; }
        .btn-qa-secondary { background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb; }
        .btn-qa-secondary:hover { background: #e5e7eb; color: #111827; }
        .btn-qa-sm { padding: 3px 10px; font-size: .75rem; }

        /* ─── Loading overlay ────────────────────────────────────────── */
        #dashboard-loading {
            display: flex; align-items: center; justify-content: center;
            min-height: 260px; gap: 12px; color: #6b7280; font-size: .9rem;
        }

        /* ─── Responsive tweaks ──────────────────────────────────────── */
        @media (max-width: 576px) {
            .stat-value { font-size: 1.25rem; }
            .quick-actions-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<?php /* Include your existing header/nav here */ ?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="container-fluid" id="dashboard-root">

    <!-- Loading state -->
    <div id="dashboard-loading">
        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
        Loading dashboard…
    </div>

    <!-- Content (hidden until data loads) -->
    <div id="dashboard-content" style="display:none">

        <!-- ① Quality Data ─────────────────────────────────────────── -->
        <section class="dashboard-section mb-4">
            <div class="dashboard-section-head">
                <h3>Quality Data</h3>
                <p>Core indicator and survey activity snapshot.</p>
            </div>
            <div class="row g-4" id="section-quality-data"></div>
        </section>

        <!-- ② Governance & Compliance ─────────────────────────────── -->
        <section class="dashboard-section mb-4">
            <div class="dashboard-section-head">
                <h3>Governance and Compliance</h3>
                <p>Standards coverage, audit workload, and action plan flow.</p>
            </div>
            <div class="row g-4" id="section-governance"></div>
        </section>

        <!-- ③ Performance & Workflow ───────────────────────────────── -->
        <section class="dashboard-section mb-4">
            <div class="dashboard-section-head">
                <h3>Performance and Workflow</h3>
                <p>Compare targets, monitor survey participation, and track action plan progress.</p>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-lg-7">
                    <div class="qa-card h-100">
                        <div class="qa-card-title">KPI Performance — Actual vs Target</div>
                        <div class="chart-wrapper"><canvas id="kpiChart"></canvas></div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="qa-card h-100">
                        <div class="qa-card-title">Survey Response Trend (6 months)</div>
                        <div class="chart-wrapper"><canvas id="trendChart"></canvas></div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="qa-card h-100">
                        <div class="qa-card-title">Action Plans by Status</div>
                        <div class="chart-wrapper"><canvas id="actionStatusChart"></canvas></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="qa-card h-100">
                        <div class="qa-card-title">Quick Actions</div>
                        <div class="quick-actions-grid">
                            <a href="/qa_system/pages/standards.php" class="qa-quick-action">
                                <div class="qa-quick-action-icon"><i class="bi bi-file-earmark-text"></i></div>
                                <div class="qa-quick-action-label">Standards</div>
                            </a>
                            <a href="/qa_system/pages/audits.php" class="qa-quick-action">
                                <div class="qa-quick-action-icon"><i class="bi bi-search"></i></div>
                                <div class="qa-quick-action-label">Audits</div>
                            </a>
                            <a href="/qa_system/pages/action_plans.php" class="qa-quick-action">
                                <div class="qa-quick-action-icon"><i class="bi bi-clipboard-check"></i></div>
                                <div class="qa-quick-action-label">Action Plans</div>
                            </a>
                            <a href="/qa_system/pages/indicators.php" class="qa-quick-action">
                                <div class="qa-quick-action-icon"><i class="bi bi-speedometer2"></i></div>
                                <div class="qa-quick-action-label">KPIs</div>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ④ Recent Activity ──────────────────────────────────────── -->
        <section class="dashboard-section">
            <div class="dashboard-section-head">
                <h3>Recent Activity</h3>
                <p>Latest records, open actions, and internal audits.</p>
            </div>

            <div class="row g-4 mb-4">
                <!-- Recent QA Records -->
                <div class="col-lg-6">
                    <div class="qa-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="qa-card-title mb-0">Recent QA Records</div>
                            <a href="/qa_system/pages/records.php" class="btn-qa btn-qa-secondary btn-qa-sm">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table qa-table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Indicator</th><th>Period</th>
                                        <th>Actual</th><th>Target</th><th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="recent-records-body">
                                    <tr><td colspan="5" class="table-empty-cell">Loading…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Pending Action Plans -->
                <div class="col-lg-6">
                    <div class="qa-card">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="qa-card-title mb-0">Pending Action Plans</div>
                            <a href="/qa_system/pages/action_plans.php" class="btn-qa btn-qa-secondary btn-qa-sm">View All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table qa-table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th>Title</th><th>Priority</th>
                                        <th>Assigned To</th><th>Target Date</th><th></th>
                                    </tr>
                                </thead>
                                <tbody id="pending-actions-body">
                                    <tr><td colspan="5" class="table-empty-cell">Loading…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Internal Audits -->
            <div class="qa-card">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="qa-card-title mb-0">Recent Internal Audits</div>
                    <a href="/qa_system/pages/audits.php" class="btn-qa btn-qa-secondary btn-qa-sm">View All</a>
                </div>
                <div class="table-responsive">
                    <table class="table qa-table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Audit Title</th><th>Scheduled Date</th>
                                <th>Status</th><th>Findings</th>
                            </tr>
                        </thead>
                        <tbody id="recent-audits-body">
                            <tr><td colspan="4" class="table-empty-cell">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </div><!-- /#dashboard-content -->

    <!-- Error state -->
    <div id="dashboard-error" style="display:none" class="alert alert-danger mt-4">
        <i class="bi bi-exclamation-circle me-2"></i>
        <span id="dashboard-error-msg">Could not load dashboard data.</span>
    </div>

</div><!-- /.container-fluid -->

<!-- Scripts ─────────────────────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<script>
$(function () {

    /* ── API endpoint ─────────────────────────────────────────────── */
    const API_URL = '/qa_system/api/dashboard.php';

    /* ── Colour palette ───────────────────────────────────────────── */
    const PALETTE = {
        actual:    'rgba(79,142,247,0.78)',
        target:    'rgba(124,99,245,0.42)',
        trendLine: 'rgba(34,197,94,0.95)',
        trendFill: 'rgba(34,197,94,0.14)',
        statusColors: {
            'Open':                 'rgba(239,68,68,0.78)',
            'In Progress':          'rgba(245,158,11,0.78)',
            'Pending Verification': 'rgba(59,124,244,0.78)',
            'Closed':               'rgba(34,197,94,0.78)',
            'Cancelled':            'rgba(148,163,184,0.72)',
        },
    };

    const CHART_DEFAULTS = {
        textColor:  '#6b7280',
        gridColor:  'rgba(0,0,0,.05)',
        fontFamily: "'Inter','Segoe UI',sans-serif",
    };

    /* ── Helpers ──────────────────────────────────────────────────── */
    function esc(str) {
        return $('<div>').text(str ?? '').html();
    }

    function statCard(iconClass, iconColor, value, label) {
        return `
        <div class="col-sm-6 col-lg-4 col-xxl-2">
            <div class="stat-card">
                <div class="stat-icon ${iconColor}"><i class="bi ${iconClass}"></i></div>
                <div>
                    <div class="stat-value">${value}</div>
                    <div class="stat-label">${label}</div>
                </div>
            </div>
        </div>`;
    }

    function statCardLink(href, iconClass, iconColor, value, label, extraClass) {
        return `
        <div class="col-sm-6 col-lg-4 col-xxl-2">
            <a href="${href}" class="stat-card-link">
                <div class="stat-card stat-card-clickable ${extraClass || ''}">
                    <div class="stat-icon ${iconColor}"><i class="bi ${iconClass}"></i></div>
                    <div>
                        <div class="stat-value">${value}</div>
                        <div class="stat-label">${label}</div>
                    </div>
                </div>
            </a>
        </div>`;
    }

    /* ── Render stat sections ─────────────────────────────────────── */
    function renderQualityData(s) {
        const html = [
            statCard('bi-speedometer2',   'blue',   s.total_indicators, 'Active Indicators'),
            statCard('bi-journal-check',  'cyan',   s.total_records,    'QA Records'),
            statCard('bi-clipboard-data', 'violet', s.total_surveys,    'Total Surveys'),
            statCard('bi-activity',       'amber',  s.active_surveys,   'Active Surveys'),
            statCard('bi-chat-square-dots','blue',  s.total_responses,  'Responses'),
            statCard('bi-check2-circle',  'green',  s.meeting_target,   'KPIs On Target'),
        ].join('');
        $('#section-quality-data').html(html);
    }

    function renderGovernance(s) {
        const overdueAlert = s.overdue_actions > 0
            ? `<span class="stat-inline-alert"><i class="bi bi-exclamation-circle-fill"></i> ${s.overdue_actions} overdue</span>`
            : '';
        const pendingValue = `${s.open_actions}`;
        const pendingLabel = `Pending ${overdueAlert}`;
        const critClass    = s.overdue_actions > 0 ? 'stat-card-critical' : '';
        const pendingIcon  = s.open_actions > 0 ? 'red' : 'amber';

        const html = [
            statCardLink('/qa_system/pages/standards.php',    'bi-file-earmark-text', 'blue',   s.total_standards, 'Standards'),
            statCardLink('/qa_system/pages/standards.php',    'bi-file-text',         'violet', s.total_policies,  'Policies'),
            statCardLink('/qa_system/pages/audits.php',       'bi-search',            'amber',  s.total_audits,    'Total Audits'),
            statCardLink('/qa_system/pages/audits.php',       'bi-graph-up',          'cyan',   s.active_audits,   'Active Audits'),
            statCardLink('/qa_system/pages/action_plans.php', 'bi-clipboard-check',   'blue',   s.total_actions,   'Action Plans'),
            `
            <div class="col-sm-6 col-lg-4 col-xxl-2">
                <a href="/qa_system/pages/action_plans.php" class="stat-card-link">
                    <div class="stat-card stat-card-clickable ${critClass}">
                        <div class="stat-icon ${pendingIcon}"><i class="bi bi-hourglass-split"></i></div>
                        <div>
                            <div class="stat-value ${s.overdue_actions > 0 ? 'text-danger' : ''}">${pendingValue}</div>
                            <div class="stat-label">${pendingLabel}</div>
                        </div>
                    </div>
                </a>
            </div>`,
        ].join('');
        $('#section-governance').html(html);
    }

    /* ── Render tables ────────────────────────────────────────────── */
    function renderRecentRecords(records) {
        if (!records.length) {
            $('#recent-records-body').html('<tr><td colspan="5" class="table-empty-cell">No recent records</td></tr>');
            return;
        }
        const rows = records.map(r => `
            <tr>
                <td class="fw-600">${esc(r.indicator_name)}</td>
                <td class="mono">${esc(r.semester)} ${esc(r.year)}</td>
                <td class="fw-600 ${r.met ? 'text-success' : 'text-danger'}">
                    ${parseFloat(r.actual_value).toFixed(2)} ${esc(r.unit)}
                </td>
                <td class="text-muted-qa">${parseFloat(r.target_value).toFixed(2)} ${esc(r.unit)}</td>
                <td><span class="badge-status ${esc(r.risk_badge)}">${esc(r.risk_label)}</span></td>
            </tr>`).join('');
        $('#recent-records-body').html(rows);
    }

    function renderPendingActions(actions) {
        if (!actions.length) {
            $('#pending-actions-body').html('<tr><td colspan="5" class="table-empty-cell">No pending action plans</td></tr>');
            return;
        }
        const priorityBadge = p => p === 'Critical' ? 'badge-closed' : (p === 'High' ? 'badge-draft' : 'badge-pending');
        const rows = actions.map(ap => {
            const titleShort = ap.title.length > 30 ? ap.title.slice(0, 30) + '…' : ap.title;
            return `
            <tr>
                <td class="fw-600" title="${esc(ap.title)}">${esc(titleShort)}</td>
                <td><span class="badge-status badge-compact ${priorityBadge(ap.priority)}">${esc(ap.priority)}</span></td>
                <td class="text-muted-qa">${esc(ap.assigned_to)}</td>
                <td class="mono ${ap.is_overdue ? 'text-danger fw-600' : 'text-muted-qa'}">
                    ${esc(ap.target_date_f)}
                    ${ap.is_overdue ? '<i class="bi bi-exclamation-circle-fill text-danger ms-1" title="Overdue"></i>' : ''}
                </td>
                <td>
                    <a href="/qa_system/pages/action_plans.php" class="btn-qa btn-qa-secondary btn-qa-sm">
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </td>
            </tr>`;
        }).join('');
        $('#pending-actions-body').html(rows);
    }

    function renderRecentAudits(audits) {
        if (!audits.length) {
            $('#recent-audits-body').html('<tr><td colspan="4" class="table-empty-cell">No audits scheduled</td></tr>');
            return;
        }
        const statusClass = {
            'Pending':     'badge-draft',
            'In Progress': 'badge-pending',
            'Completed':   'badge-active',
            'Cancelled':   'badge-closed',
        };
        const rows = audits.map(au => `
            <tr>
                <td class="fw-600">${esc(au.title)}</td>
                <td class="mono">${esc(au.scheduled_date)}</td>
                <td><span class="badge-status ${statusClass[au.status] || 'badge-pending'}">${esc(au.status)}</span></td>
                <td>
                    ${au.has_findings
                        ? '<span class="badge-status badge-closed">Has findings</span>'
                        : '<span class="text-muted-qa">—</span>'}
                </td>
            </tr>`).join('');
        $('#recent-audits-body').html(rows);
    }

    /* ── Render charts ────────────────────────────────────────────── */
    function renderCharts(chart, trend, actionStatus) {
        const d = CHART_DEFAULTS;

        // KPI Bar chart
        new Chart(document.getElementById('kpiChart'), {
            type: 'bar',
            data: {
                labels: chart.labels,
                datasets: [
                    { label: 'Actual', data: chart.actuals, backgroundColor: PALETTE.actual,  borderRadius: 5 },
                    { label: 'Target', data: chart.targets, backgroundColor: PALETTE.target, borderRadius: 5 },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color: d.textColor, font: { family: d.fontFamily, size: 11 } } } },
                scales: {
                    x: { ticks: { color: d.textColor, font: { family: d.fontFamily, size: 10 }, maxRotation: 30 }, grid: { color: d.gridColor } },
                    y: { ticks: { color: d.textColor, font: { family: d.fontFamily } }, grid: { color: d.gridColor }, beginAtZero: true },
                },
            },
        });

        // Trend line chart
        new Chart(document.getElementById('trendChart'), {
            type: 'line',
            data: {
                labels: trend.labels,
                datasets: [{
                    label: 'Responses',
                    data: trend.counts,
                    borderColor: PALETTE.trendLine,
                    backgroundColor: PALETTE.trendFill,
                    fill: true, tension: 0.35,
                    pointBackgroundColor: PALETTE.trendLine, pointRadius: 3,
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color: d.textColor, font: { family: d.fontFamily, size: 11 } } } },
                scales: {
                    x: { ticks: { color: d.textColor, font: { family: d.fontFamily } }, grid: { color: d.gridColor } },
                    y: { ticks: { color: d.textColor, font: { family: d.fontFamily } }, grid: { color: d.gridColor }, beginAtZero: true },
                },
            },
        });

        // Action Plans doughnut chart
        new Chart(document.getElementById('actionStatusChart'), {
            type: 'doughnut',
            data: {
                labels: actionStatus.labels,
                datasets: [{
                    data: actionStatus.counts,
                    backgroundColor: actionStatus.labels.map(l => PALETTE.statusColors[l] || 'rgba(148,163,184,0.72)'),
                }],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { labels: { color: d.textColor, font: { family: d.fontFamily } } } },
            },
        });
    }

    /* ── Main AJAX load ───────────────────────────────────────────── */
    $.ajax({
        url: API_URL,
        method: 'GET',
        dataType: 'json',
        success: function (data) {
            if (!data.success) {
                showError(data.message || 'Unknown error from server.');
                return;
            }

            // Merge meeting_target into stats for convenience
            const s = { ...data.stats, meeting_target: data.stats.meeting_target };

            renderQualityData(s);
            renderGovernance(s);
            renderRecentRecords(data.recent_records);
            renderPendingActions(data.pending_actions);
            renderRecentAudits(data.recent_audits);
            renderCharts(data.chart, data.trend, data.action_status);

            $('#dashboard-loading').hide();
            $('#dashboard-content').fadeIn(200);
        },
        error: function (xhr) {
            let msg = 'Failed to connect to the server.';
            try { msg = JSON.parse(xhr.responseText).message || msg; } catch (_) {}
            showError(msg);
        },
    });

    function showError(msg) {
        $('#dashboard-loading').hide();
        $('#dashboard-error-msg').text(msg);
        $('#dashboard-error').show();
    }

});
</script>

<?php /* Include your existing footer here */ ?>
<?php  require_once __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>