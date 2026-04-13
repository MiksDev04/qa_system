<?php
// index.php – Dashboard
// ByteBandits QA Management System
require_once __DIR__ . '/config/database.php';
$page_title = 'Dashboard';

$conn = getConnection();

// Summary stats - Quality Indicators
$total_indicators = $conn->query("SELECT COUNT(*) as c FROM qa_indicators WHERE status='Active'")->fetch_assoc()['c'];
$total_records    = $conn->query("SELECT COUNT(*) as c FROM qa_records")->fetch_assoc()['c'];
$total_surveys    = $conn->query("SELECT COUNT(*) as c FROM surveys")->fetch_assoc()['c'];
$active_surveys   = $conn->query("SELECT COUNT(*) as c FROM surveys WHERE status='Active'")->fetch_assoc()['c'];
$total_responses  = $conn->query("SELECT COUNT(*) as c FROM survey_responses")->fetch_assoc()['c'];

// Summary stats - Governance & Compliance (NEW)
$total_standards  = $conn->query("SELECT COUNT(*) as c FROM qa_standards WHERE status='Active'")->fetch_assoc()['c'];
$total_audits     = $conn->query("SELECT COUNT(*) as c FROM qa_audits")->fetch_assoc()['c'];
$active_audits    = $conn->query("SELECT COUNT(*) as c FROM qa_audits WHERE status IN ('Pending','In Progress')")->fetch_assoc()['c'];
$total_actions    = $conn->query("SELECT COUNT(*) as c FROM qa_action_plans")->fetch_assoc()['c'];
$open_actions     = $conn->query("SELECT COUNT(*) as c FROM qa_action_plans WHERE status IN ('Open','In Progress')")->fetch_assoc()['c'];
$overdue_actions  = $conn->query("SELECT COUNT(*) as c FROM qa_action_plans WHERE target_date < CURDATE() AND status NOT IN ('Closed','Cancelled')")->fetch_assoc()['c'];
$total_policies   = $conn->query("SELECT COUNT(*) as c FROM qa_policies WHERE status='Active'")->fetch_assoc()['c'];

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

require_once __DIR__ . '/includes/header.php';
?>

<!-- Stats Row - Quality Metrics -->
<div class="row g-3 mb-2">
    <div class="col-12">
        <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Quality Data</div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-speedometer2"></i></div>
            <div>
                <div class="stat-value"><?= $total_indicators ?></div>
                <div class="stat-label">Active Indicators</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-journal-check"></i></div>
            <div>
                <div class="stat-value"><?= $total_records ?></div>
                <div class="stat-label">QA Records</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon violet"><i class="bi bi-clipboard-data"></i></div>
            <div>
                <div class="stat-value"><?= $total_surveys ?></div>
                <div class="stat-label">Total Surveys</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-activity"></i></div>
            <div>
                <div class="stat-value"><?= $active_surveys ?></div>
                <div class="stat-label">Active Surveys</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon cyan"><i class="bi bi-chat-square-dots"></i></div>
            <div>
                <div class="stat-value"><?= $total_responses ?></div>
                <div class="stat-label">Responses</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check2-circle"></i></div>
            <div>
                <div class="stat-value"><?= $meeting_target ?></div>
                <div class="stat-label">KPIs On Target</div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Row - Governance & Compliance (NEW) -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Governance & Compliance</div>
    </div>
    <div class="col-sm-6 col-xl-2">
        <a href="pages/standards.php" style="text-decoration: none;">
        <div class="stat-card" style="cursor: pointer; transition: var(--transition);">
            <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"><i class="bi bi-file-earmark-text"></i></div>
            <div>
                <div class="stat-value"><?= $total_standards ?></div>
                <div class="stat-label">Standards</div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-2">
        <a href="pages/standards.php" style="text-decoration: none;">
        <div class="stat-card" style="cursor: pointer; transition: var(--transition);">
            <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);"><i class="bi bi-file-text"></i></div>
            <div>
                <div class="stat-value"><?= $total_policies ?></div>
                <div class="stat-label">Policies</div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-2">
        <a href="pages/audits.php" style="text-decoration: none;">
        <div class="stat-card" style="cursor: pointer; transition: var(--transition);">
            <div class="stat-icon" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);"><i class="bi bi-search"></i></div>
            <div>
                <div class="stat-value"><?= $total_audits ?></div>
                <div class="stat-label">Total Audits</div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-2">
        <a href="pages/audits.php" style="text-decoration: none;">
        <div class="stat-card" style="cursor: pointer; transition: var(--transition);">
            <div class="stat-icon" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);"><i class="bi bi-graph-up"></i></div>
            <div>
                <div class="stat-value"><?= $active_audits ?></div>
                <div class="stat-label">Active Audits</div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-2">
        <a href="pages/action_plans.php" style="text-decoration: none;">
        <div class="stat-card" style="cursor: pointer; transition: var(--transition);">
            <div class="stat-icon" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);"><i class="bi bi-clipboard-check"></i></div>
            <div>
                <div class="stat-value"><?= $total_actions ?></div>
                <div class="stat-label">Action Plans</div>
            </div>
        </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-2">
        <a href="pages/action_plans.php" style="text-decoration: none;">
        <div class="stat-card <?= $open_actions > 0 ? 'alert' : '' ?>" style="cursor: pointer; transition: var(--transition); <?= $overdue_actions > 0 ? 'border: 2px solid #dc3545;' : '' ?>">
            <div class="stat-icon <?= $open_actions > 0 ? 'red' : 'orange' ?>"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-value <?= $overdue_actions > 0 ? 'text-danger' : '' ?>"><?= $open_actions ?></div>
                <div class="stat-label">Pending <?= $overdue_actions > 0 ? "<i class='bi bi-exclamation-circle text-danger' style='font-size: 0.75rem;'></i>" : "" ?></div>
            </div>
        </div>
        </a>
    </div>
</div>

<!-- KPI Risk Snapshot -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">KPI Risk Snapshot (Latest Records)</div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="stat-value"><?= $kpi_on_track ?></div>
                <div class="stat-label">On Track</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-exclamation-triangle"></i></div>
            <div>
                <div class="stat-value"><?= $kpi_at_risk ?></div>
                <div class="stat-label">At Risk</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-x-octagon"></i></div>
            <div>
                <div class="stat-value"><?= $kpi_off_track ?></div>
                <div class="stat-label">Off Track</div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="qa-card h-100">
            <div class="qa-card-title">KPI Performance — Actual vs Target</div>
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

<!-- Action Plans Status Chart (NEW) -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="qa-card h-100">
            <div class="qa-card-title">Action Plans by Status</div>
            <div class="chart-wrapper">
                <canvas id="actionStatusChart"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="qa-card">
            <div class="qa-card-title">Quick Actions</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 16px;">
                <a href="pages/standards.php" class="qa-quick-action" style="padding: 12px; border-radius: var(--radius-sm); background: var(--bg-secondary); text-decoration: none; text-align: center; transition: var(--transition);">
                    <div style="font-size: 1.5rem; margin-bottom: 8px;"><i class="bi bi-file-earmark-text"></i></div>
                    <div style="font-size: 0.8rem; font-weight: 600;">Standards &<br>Policies</div>
                </a>
                <a href="pages/audits.php" class="qa-quick-action" style="padding: 12px; border-radius: var(--radius-sm); background: var(--bg-secondary); text-decoration: none; text-align: center; transition: var(--transition);">
                    <div style="font-size: 1.5rem; margin-bottom: 8px;"><i class="bi bi-search"></i></div>
                    <div style="font-size: 0.8rem; font-weight: 600;">Internal<br>Audits</div>
                </a>
                <a href="pages/action_plans.php" class="qa-quick-action" style="padding: 12px; border-radius: var(--radius-sm); background: var(--bg-secondary); text-decoration: none; text-align: center; transition: var(--transition);">
                    <div style="font-size: 1.5rem; margin-bottom: 8px;"><i class="bi bi-clipboard-check"></i></div>
                    <div style="font-size: 0.8rem; font-weight: 600;">Action<br>Plans</div>
                </a>
                <a href="pages/indicators.php" class="qa-quick-action" style="padding: 12px; border-radius: var(--radius-sm); background: var(--bg-secondary); text-decoration: none; text-align: center; transition: var(--transition);">
                    <div style="font-size: 1.5rem; margin-bottom: 8px;"><i class="bi bi-speedometer2"></i></div>
                    <div style="font-size: 0.8rem; font-weight: 600;">KPI<br>Indicators</div>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Records -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="qa-card">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="qa-card-title mb-0">Recent QA Records</div>
                <a href="pages/records.php" class="btn-qa btn-qa-secondary btn-qa-sm">View All</a>
            </div>
            <div class="qa-table-wrapper">
                <table class="qa-table">
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
                            $barClass = $met ? 'success' : ($pct >= 80 ? 'warning' : 'danger');
                            $riskLabel = $pct >= 100 ? 'On Track' : ($pct >= 80 ? 'At Risk' : 'Off Track');
                            $riskBadge = $pct >= 100 ? 'badge-active' : ($pct >= 80 ? 'badge-pending' : 'badge-closed');
                        ?>
                        <tr>
                            <td class="fw-600"><?= htmlspecialchars($rec['indicator_name']) ?></td>
                            <td class="mono"><?= $rec['semester'] ?> <?= $rec['year'] ?></td>
                            <td class="fw-600" style="color:var(--<?= $met ? 'success' : 'danger' ?>)">
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
                <a href="pages/action_plans.php" class="btn-qa btn-qa-secondary btn-qa-sm">View All</a>
            </div>
            <div class="qa-table-wrapper">
                <table class="qa-table">
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
                            $priority_class = ['Critical' => 'text-danger', 'High' => 'text-warning', 'Medium' => 'text-info', 'Low' => 'text-success'];
                        ?>
                        <tr>
                            <td class="fw-600" style="font-size: 0.9rem"><?= htmlspecialchars(substr($ap['title'], 0, 25)) ?></td>
                            <td><span class="badge-status <?= $ap['priority'] === 'Critical' ? 'badge-closed' : 'badge-pending' ?>" style="font-size: 0.75rem"><?= $ap['priority'] ?></span></td>
                            <td class="text-muted-qa" style="font-size: 0.9rem"><?= htmlspecialchars($ap['assigned_to']) ?></td>
                            <td class="mono <?= $is_overdue ? 'text-danger fw-600' : '' ?>" style="font-size: 0.9rem">
                                <?= date('M d', strtotime($ap['target_date'])) ?>
                                <?php if ($is_overdue): ?><i class="bi bi-exclamation-circle-fill text-danger" style="font-size: 0.8rem;" title="Overdue"></i><?php endif; ?>
                            </td>
                            <td>
                                <a href="pages/action_plans.php" class="btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon" style="font-size: 0.8rem;">
                                    <i class="bi bi-arrow-right"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if (!$has_pending): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 20px; color: var(--text-secondary);">No pending action plans</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Recent Audits (NEW) -->
<div class="qa-card">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="qa-card-title mb-0">Recent Internal Audits</div>
        <a href="pages/audits.php" class="btn-qa btn-qa-secondary btn-qa-sm">View All</a>
    </div>
    <div class="qa-table-wrapper">
        <table class="qa-table">
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
                        <span class="text-muted-qa">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if (!$has_audits): ?>
                <tr><td colspan="4" style="text-align: center; padding: 20px; color: var(--text-secondary);">No audits scheduled</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$extra_js = '<script>
$(function(){
    const d = getChartDefaults();
    const labels  = ' . json_encode($chart_labels) . ';
    const actuals = ' . json_encode($chart_actuals) . ';
    const targets = ' . json_encode($chart_targets) . ';

    // KPI Bar chart
    new Chart(document.getElementById("kpiChart"), {
        type: "bar",
        data: {
            labels,
            datasets: [
                { label: "Actual", data: actuals, backgroundColor: "rgba(79,142,247,0.7)", borderRadius: 5 },
                { label: "Target", data: targets, backgroundColor: "rgba(124,99,245,0.4)", borderRadius: 5, borderDashed: [5,5] }
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
                borderColor: "#22c55e",
                backgroundColor: "rgba(34,197,94,0.12)",
                fill: true, tension: 0.4, pointBackgroundColor: "#22c55e", pointRadius: 4
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
        "Open": "rgba(255, 107, 107, 0.8)",
        "In Progress": "rgba(255, 193, 7, 0.8)",
        "Pending Verification": "rgba(66, 165, 245, 0.8)",
        "Closed": "rgba(76, 175, 80, 0.8)",
        "Cancelled": "rgba(158, 158, 158, 0.8)"
    };

    new Chart(document.getElementById("actionStatusChart"), {
        type: "doughnut",
        data: {
            labels: actionLabels,
            datasets: [{
                data: actionCounts,
                backgroundColor: actionLabels.map(l => statusColors[l] || "rgba(200,200,200,0.8)")
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { labels: { color: d.textColor, font: { family: d.fontFamily } } } }
        }
    });
});
</script>';
require_once __DIR__ . '/includes/footer.php';
?>
