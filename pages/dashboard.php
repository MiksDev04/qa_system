<?php
// pages/dashboard.php â€“ Dashboard
// ByteBandits QA Management System
require_once __DIR__ . '/../config/database.php';
$page_title = 'Dashboard';

$conn = getConnection();

// Summary stats in one round-trip to keep dashboard load fast.
$summary = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM qa_indicators WHERE status='Active') AS total_indicators,
        (SELECT COUNT(*) FROM qa_records) AS total_records,
        (SELECT COUNT(*) FROM surveys) AS total_surveys,
        (SELECT COUNT(*) FROM surveys WHERE status='Active') AS active_surveys,
        (SELECT COUNT(*) FROM survey_responses) AS total_responses,
        (SELECT COUNT(*) FROM qa_standards WHERE status='Active') AS total_standards,
        (SELECT COUNT(*) FROM qa_audits) AS total_audits,
        (SELECT COUNT(*) FROM qa_audits WHERE status IN ('Pending','In Progress')) AS active_audits,
        (SELECT COUNT(*) FROM qa_action_plans) AS total_actions,
        (SELECT COUNT(*) FROM qa_action_plans WHERE status IN ('Open','In Progress')) AS open_actions,
        (SELECT COUNT(*) FROM qa_action_plans WHERE target_date < CURDATE() AND status NOT IN ('Closed','Cancelled')) AS overdue_actions,
        (SELECT COUNT(*) FROM qa_policies WHERE status='Active') AS total_policies
")->fetch_assoc();

$total_indicators = (int)($summary['total_indicators'] ?? 0);
$total_records = (int)($summary['total_records'] ?? 0);
$total_surveys = (int)($summary['total_surveys'] ?? 0);
$active_surveys = (int)($summary['active_surveys'] ?? 0);
$total_responses = (int)($summary['total_responses'] ?? 0);
$total_standards = (int)($summary['total_standards'] ?? 0);
$total_audits = (int)($summary['total_audits'] ?? 0);
$active_audits = (int)($summary['active_audits'] ?? 0);
$total_actions = (int)($summary['total_actions'] ?? 0);
$open_actions = (int)($summary['open_actions'] ?? 0);
$overdue_actions = (int)($summary['overdue_actions'] ?? 0);
$total_policies = (int)($summary['total_policies'] ?? 0);

// Indicators meeting target this year
$meeting_target = $conn->query("
    SELECT COUNT(DISTINCT r.indicator_id) as c
    FROM qa_records r
    JOIN qa_indicators i ON r.indicator_id = i.indicator_id
    WHERE r.year = YEAR(CURDATE()) AND r.actual_value >= i.target_value
")->fetch_assoc()['c'];

// KPI risk flags based on latest value per active indicator
$kpi_risk = $conn->query("
    SELECT
        SUM(CASE WHEN t.target_value > 0 AND (t.latest_value / t.target_value) * 100 >= 100 THEN 1 ELSE 0 END) AS on_track,
        SUM(CASE WHEN t.target_value > 0 AND (t.latest_value / t.target_value) * 100 >= 80 AND (t.latest_value / t.target_value) * 100 < 100 THEN 1 ELSE 0 END) AS at_risk,
        SUM(CASE WHEN t.target_value > 0 AND (t.latest_value / t.target_value) * 100 < 80 THEN 1 ELSE 0 END) AS off_track
    FROM (
        SELECT i.indicator_id, i.target_value,
               (SELECT r.actual_value FROM qa_records r WHERE r.indicator_id = i.indicator_id ORDER BY r.year DESC, r.created_at DESC LIMIT 1) AS latest_value
        FROM qa_indicators i
        WHERE i.status = 'Active'
    ) t
    WHERE t.latest_value IS NOT NULL
")->fetch_assoc();

$kpi_on_track = (int)($kpi_risk['on_track'] ?? 0);
$kpi_at_risk = (int)($kpi_risk['at_risk'] ?? 0);
$kpi_off_track = (int)($kpi_risk['off_track'] ?? 0);

// Recent records (latest 5)
$recent_records = $conn->query("
    SELECT r.*, i.name as indicator_name, i.target_value, i.unit
    FROM qa_records r
    JOIN qa_indicators i ON r.indicator_id = i.indicator_id
    ORDER BY r.created_at DESC LIMIT 5
");

// Chart: actual vs target for all active indicators
$chart_data = $conn->query("
    SELECT i.name, i.target_value, i.unit,
           (SELECT r.actual_value FROM qa_records r
            WHERE r.indicator_id = i.indicator_id
            ORDER BY r.year DESC, r.created_at DESC LIMIT 1) as latest_value
    FROM qa_indicators i
    WHERE i.status='Active'
    ORDER BY i.name
");

$chart_labels = $chart_actuals = $chart_targets = [];
while ($row = $chart_data->fetch_assoc()) {
    if ($row['latest_value'] === null) continue;
    $chart_labels[]  = $row['name'];
    $chart_actuals[] = (float)$row['latest_value'];
    $chart_targets[] = (float)$row['target_value'];
}

// Survey response trend (last 6 months)
$trend = $conn->query("
    SELECT DATE_FORMAT(submitted_at,'%b %Y') as month, COUNT(*) as count
    FROM survey_responses
    WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(submitted_at,'%Y-%m')
    ORDER BY submitted_at ASC
");
$trend_labels = $trend_counts = [];
while ($r = $trend->fetch_assoc()) {
    $trend_labels[] = $r['month'];
    $trend_counts[] = (int)$r['count'];
}

// Action Plans status distribution (NEW)
$action_status = $conn->query("
    SELECT status, COUNT(*) as count FROM qa_action_plans GROUP BY status
");
$action_status_labels = $action_status_counts = [];
while ($row = $action_status->fetch_assoc()) {
    $action_status_labels[] = $row['status'];
    $action_status_counts[] = (int)$row['count'];
}

// Recent audits (NEW)
$recent_audits = $conn->query("
    SELECT audit_id, title, scheduled_date, status, findings
    FROM qa_audits
    ORDER BY scheduled_date DESC LIMIT 5
");

// Pending action plans (NEW)
$pending_actions = $conn->query("
    SELECT action_id, title, priority, assigned_to, target_date
    FROM qa_action_plans
    WHERE status != 'Closed'
    ORDER BY priority DESC, target_date ASC
    LIMIT 5
");

require_once __DIR__ . '/../includes/header.php';
?>

<section class="dashboard-section mb-4">
    <div class="dashboard-section-head">
        <h3>Quality Data</h3>
        <p>Core indicator and survey activity snapshot.</p>
    </div>
    <div class="row g-4">
        <div class="col-sm-6 col-lg-4 col-xxl-2">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-speedometer2"></i></div>
                <div>
                    <div class="stat-value"><?= $total_indicators ?></div>
                    <div class="stat-label">Active Indicators</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4 col-xxl-2">
            <div class="stat-card">
                <div class="stat-icon cyan"><i class="bi bi-journal-check"></i></div>
                <div>
                    <div class="stat-value"><?= $total_records ?></div>
                    <div class="stat-label">QA Records</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4 col-xxl-2">
            <div class="stat-card">
                <div class="stat-icon violet"><i class="bi bi-clipboard-data"></i></div>
                <div>
                    <div class="stat-value"><?= $total_surveys ?></div>
                    <div class="stat-label">Total Surveys</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4 col-xxl-2">
            <div class="stat-card">
                <div class="stat-icon amber"><i class="bi bi-activity"></i></div>
                <div>
                    <div class="stat-value"><?= $active_surveys ?></div>
                    <div class="stat-label">Active Surveys</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4 col-xxl-2">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-chat-square-dots"></i></div>
                <div>
                    <div class="stat-value"><?= $total_responses ?></div>
                    <div class="stat-label">Responses</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4 col-xxl-2">
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-check2-circle"></i></div>
                <div>
                    <div class="stat-value"><?= $meeting_target ?></div>
                    <div class="stat-label">KPIs On Target</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="dashboard-section mb-4">
    <div class="dashboard-section-head">
        <h3>Governance and Compliance</h3>
        <p>Standards coverage, audit workload, and action plan flow.</p>
    </div>
    <div class="row g-4">
        <div class="col-sm-6 col-lg-4 col-xxl-2">
            <a href="/qa_system/pages/standards.php" class="stat-card-link">
                <div class="stat-card stat-card-clickable">
                    <div class="stat-icon blue"><i class="bi bi-file-earmark-text"></i></div>
                    <div>
                        <div class="stat-value"><?= $total_standards ?></div>
                        <div class="stat-label">Standards</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4 col-xxl-2">
            <a href="/qa_system/pages/standards.php" class="stat-card-link">
                <div class="stat-card stat-card-clickable">
                    <div class="stat-icon violet"><i class="bi bi-file-text"></i></div>
                    <div>
                        <div class="stat-value"><?= $total_policies ?></div>
                        <div class="stat-label">Policies</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4 col-xxl-2">
            <a href="/qa_system/pages/audits.php" class="stat-card-link">
                <div class="stat-card stat-card-clickable">
                    <div class="stat-icon amber"><i class="bi bi-search"></i></div>
                    <div>
                        <div class="stat-value"><?= $total_audits ?></div>
                        <div class="stat-label">Total Audits</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4 col-xxl-2">
            <a href="/qa_system/pages/audits.php" class="stat-card-link">
                <div class="stat-card stat-card-clickable">
                    <div class="stat-icon cyan"><i class="bi bi-graph-up"></i></div>
                    <div>
                        <div class="stat-value"><?= $active_audits ?></div>
                        <div class="stat-label">Active Audits</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4 col-xxl-2">
            <a href="/qa_system/pages/action_plans.php" class="stat-card-link">
                <div class="stat-card stat-card-clickable">
                    <div class="stat-icon blue"><i class="bi bi-clipboard-check"></i></div>
                    <div>
                        <div class="stat-value"><?= $total_actions ?></div>
                        <div class="stat-label">Action Plans</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-sm-6 col-lg-4 col-xxl-2">
            <a href="/qa_system/pages/action_plans.php" class="stat-card-link">
                <div class="stat-card stat-card-clickable <?= $overdue_actions > 0 ? 'stat-card-critical' : '' ?>">
                    <div class="stat-icon <?= $open_actions > 0 ? 'red' : 'amber' ?>"><i class="bi bi-hourglass-split"></i></div>
                    <div>
                        <div class="stat-value <?= $overdue_actions > 0 ? 'text-danger' : '' ?>"><?= $open_actions ?></div>
                        <div class="stat-label">
                            Pending
                            <?php if ($overdue_actions > 0): ?>
                                <span class="stat-inline-alert"><i class="bi bi-exclamation-circle-fill"></i> <?= $overdue_actions ?> overdue</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    </div>
</section>

<section class="dashboard-section mb-4">
    <div class="dashboard-section-head">
        <h3>KPI Risk Snapshot</h3>
        <p>Based on each indicator&apos;s latest recorded value.</p>
    </div>
    <div class="row g-4">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
                <div>
                    <div class="stat-value"><?= $kpi_on_track ?></div>
                    <div class="stat-label">On Track</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon amber"><i class="bi bi-exclamation-triangle"></i></div>
                <div>
                    <div class="stat-value"><?= $kpi_at_risk ?></div>
                    <div class="stat-label">At Risk</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon red"><i class="bi bi-x-octagon"></i></div>
                <div>
                    <div class="stat-value"><?= $kpi_off_track ?></div>
                    <div class="stat-label">Off Track</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="dashboard-section mb-4">
    <div class="dashboard-section-head">
        <h3>Performance and Workflow</h3>
        <p>Compare targets, monitor survey participation, and track action plan progress.</p>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="qa-card h-100">
                <div class="qa-card-title">KPI Performance - Actual vs Target</div>
                <div class="chart-wrapper">
                    <canvas id="kpiChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="qa-card h-100">
                <div class="qa-card-title">Survey Response Trend (6 months)</div>
                <div class="chart-wrapper">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="qa-card h-100">
                <div class="qa-card-title">Action Plans by Status</div>
                <div class="chart-wrapper">
                    <canvas id="actionStatusChart"></canvas>
                </div>
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

<section class="dashboard-section">
    <div class="dashboard-section-head">
        <h3>Recent Activity</h3>
        <p>Latest records, open actions, and internal audits.</p>
    </div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="qa-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="qa-card-title mb-0">Recent QA Records</div>
                <a href="/qa_system/pages/records.php" class="btn-qa btn-qa-secondary btn-qa-sm">View All</a>
            </div>
            <div class="qa-table-wrapper table-responsive">
                <table class="table qa-table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Indicator</th>
                            <th>Period</th>
                            <th>Actual</th>
                            <th>Target</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($rec = $recent_records->fetch_assoc()): ?>
                        <?php
                            $pct = $rec['target_value'] > 0 ? ($rec['actual_value'] / $rec['target_value']) * 100 : 0;
                            $met = $rec['actual_value'] >= $rec['target_value'];
                            $riskLabel = $pct >= 100 ? 'On Track' : ($pct >= 80 ? 'At Risk' : 'Off Track');
                            $riskBadge = $pct >= 100 ? 'badge-active' : ($pct >= 80 ? 'badge-pending' : 'badge-closed');
                        ?>
                        <tr>
                            <td class="fw-600"><?= htmlspecialchars($rec['indicator_name']) ?></td>
                            <td class="mono"><?= $rec['semester'] ?> <?= $rec['year'] ?></td>
                            <td class="fw-600 <?= $met ? 'text-success' : 'text-danger' ?>">
                                <?= number_format($rec['actual_value'], 2) ?> <?= htmlspecialchars($rec['unit']) ?>
                            </td>
                            <td class="text-muted-qa"><?= number_format($rec['target_value'], 2) ?> <?= htmlspecialchars($rec['unit']) ?></td>
                            <td>
                                <span class="badge-status <?= $riskBadge ?>">
                                    <?= $riskLabel ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Pending Action Plans (NEW) -->
    <div class="col-lg-6">
        <div class="qa-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="qa-card-title mb-0">Pending Action Plans</div>
                <a href="/qa_system/pages/action_plans.php" class="btn-qa btn-qa-secondary btn-qa-sm">View All</a>
            </div>
            <div class="qa-table-wrapper table-responsive">
                <table class="table qa-table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Priority</th>
                            <th>Assigned To</th>
                            <th>Target Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $has_pending = false;
                        while ($ap = $pending_actions->fetch_assoc()):
                            $has_pending = true;
                            $is_overdue = strtotime($ap['target_date']) < time();
                            $priority_badge = $ap['priority'] === 'Critical' ? 'badge-closed' : ($ap['priority'] === 'High' ? 'badge-draft' : 'badge-pending');
                            $title_short = strlen($ap['title']) > 30 ? substr($ap['title'], 0, 30) . '...' : $ap['title'];
                        ?>
                        <tr>
                            <td class="fw-600 qa-table-title" title="<?= htmlspecialchars($ap['title']) ?>"><?= htmlspecialchars($title_short) ?></td>
                            <td><span class="badge-status badge-compact <?= $priority_badge ?>"><?= htmlspecialchars($ap['priority']) ?></span></td>
                            <td class="qa-table-subtle"><?= htmlspecialchars($ap['assigned_to']) ?></td>
                            <td class="mono <?= $is_overdue ? 'text-danger fw-600' : '' ?> qa-table-subtle">
                                <?= date('M d, Y', strtotime($ap['target_date'])) ?>
                                <?php if ($is_overdue): ?><i class="bi bi-exclamation-circle-fill text-danger table-status-icon" title="Overdue"></i><?php endif; ?>
                            </td>
                            <td>
                                <a href="/qa_system/pages/action_plans.php" class="btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon btn-qa-compact-icon">
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if (!$has_pending): ?>
                        <tr><td colspan="5" class="table-empty-cell">No pending action plans</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="qa-card">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="qa-card-title mb-0">Recent Internal Audits</div>
        <a href="/qa_system/pages/audits.php" class="btn-qa btn-qa-secondary btn-qa-sm">View All</a>
    </div>
    <div class="qa-table-wrapper table-responsive">
        <table class="table qa-table table-sm align-middle">
            <thead>
                <tr>
                    <th>Audit Title</th>
                    <th>Scheduled Date</th>
                    <th>Status</th>
                    <th>Findings</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $has_audits = false;
                while ($au = $recent_audits->fetch_assoc()):
                    $has_audits = true;
                    $status_class = ['Pending' => 'badge-draft', 'In Progress' => 'badge-pending', 'Completed' => 'badge-active', 'Cancelled' => 'badge-closed'];
                ?>
                <tr>
                    <td class="fw-600"><?= htmlspecialchars($au['title']) ?></td>
                    <td class="mono"><?= date('M d, Y', strtotime($au['scheduled_date'])) ?></td>
                    <td>
                        <span class="badge-status <?= $status_class[$au['status']] ?? 'badge-pending' ?>">
                            <?= $au['status'] ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($au['findings'])): ?>
                        <span class="badge-status badge-closed">Has findings</span>
                        <?php else: ?>
                        <span class="text-muted-qa">â€”</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if (!$has_audits): ?>
                <tr><td colspan="4" class="table-empty-cell">No audits scheduled</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</section>

<?php
$extra_js = '<script>
$(function(){
    const d = getChartDefaults();
    const labels  = ' . json_encode($chart_labels) . ';
    const actuals = ' . json_encode($chart_actuals) . ';
    const targets = ' . json_encode($chart_targets) . ';
    const palette = {
        actual: "rgba(79,142,247,0.78)",
        target: "rgba(124,99,245,0.42)",
        trendLine: "rgba(34,197,94,0.95)",
        trendFill: "rgba(34,197,94,0.14)"
    };

    // KPI Bar chart
    new Chart(document.getElementById("kpiChart"), {
        type: "bar",
        data: {
            labels,
            datasets: [
                { label: "Actual", data: actuals, backgroundColor: palette.actual, borderRadius: 5 },
                { label: "Target", data: targets, backgroundColor: palette.target, borderRadius: 5 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { labels: { color: d.textColor, font: { family: d.fontFamily, size: 11 } } } },
            scales: {
                x: { ticks: { color: d.textColor, font: { family: d.fontFamily, size: 10 }, maxRotation: 30 }, grid: { color: d.gridColor } },
                y: { ticks: { color: d.textColor, font: { family: d.fontFamily } }, grid: { color: d.gridColor }, beginAtZero: true }
            }
        }
    });

    // Trend line chart
    const trendLabels = ' . json_encode($trend_labels) . ';
    const trendCounts = ' . json_encode($trend_counts) . ';
    new Chart(document.getElementById("trendChart"), {
        type: "line",
        data: {
            labels: trendLabels,
            datasets: [{
                label: "Responses",
                data: trendCounts,
                borderColor: palette.trendLine,
                backgroundColor: palette.trendFill,
                fill: true, tension: 0.35, pointBackgroundColor: palette.trendLine, pointRadius: 3
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { labels: { color: d.textColor, font: { family: d.fontFamily, size: 11 } } } },
            scales: {
                x: { ticks: { color: d.textColor, font: { family: d.fontFamily } }, grid: { color: d.gridColor } },
                y: { ticks: { color: d.textColor, font: { family: d.fontFamily } }, grid: { color: d.gridColor }, beginAtZero: true }
            }
        }
    });

    // Action Plans Status Pie Chart (NEW)
    const actionLabels = ' . json_encode($action_status_labels) . ';
    const actionCounts = ' . json_encode($action_status_counts) . ';
    const statusColors = {
        "Open": "rgba(239,68,68,0.78)",
        "In Progress": "rgba(245,158,11,0.78)",
        "Pending Verification": "rgba(59,124,244,0.78)",
        "Closed": "rgba(34,197,94,0.78)",
        "Cancelled": "rgba(148,163,184,0.72)"
    };

    new Chart(document.getElementById("actionStatusChart"), {
        type: "doughnut",
        data: {
            labels: actionLabels,
            datasets: [{
                data: actionCounts,
                backgroundColor: actionLabels.map(l => statusColors[l] || "rgba(148,163,184,0.72)")
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { labels: { color: d.textColor, font: { family: d.fontFamily } } } }
        }
    });
});
</script>';
require_once __DIR__ . '/../includes/footer.php';
?>



