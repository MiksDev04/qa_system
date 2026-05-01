<?php
// api/dashboard.php — Dashboard API
// ByteBandits QA Management System
// Returns all dashboard data as JSON

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

$conn = getConnection();

// --- Helper: safe fetch_assoc with fallback ---
function safeInt($val, $default = 0) {
    return (int)($val ?? $default);
}

try {

    // Summary stats in one round-trip
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

    // Indicators meeting target this year
    $meeting_target = (int)$conn->query("
        SELECT COUNT(DISTINCT r.indicator_id) as c
        FROM qa_records r
        JOIN qa_indicators i ON r.indicator_id = i.indicator_id
        WHERE r.year = YEAR(CURDATE()) AND r.actual_value >= i.target_value
    ")->fetch_assoc()['c'];

    // Recent QA records (latest 5)
    $recent_records_res = $conn->query("
        SELECT r.actual_value, r.semester, r.year,
               i.name AS indicator_name, i.target_value, i.unit
        FROM qa_records r
        JOIN qa_indicators i ON r.indicator_id = i.indicator_id
        ORDER BY r.created_at DESC LIMIT 5
    ");
    $recent_records = [];
    while ($row = $recent_records_res->fetch_assoc()) {
        $pct = $row['target_value'] > 0
            ? ($row['actual_value'] / $row['target_value']) * 100
            : 0;
        $row['pct']        = round($pct, 2);
        $row['met']        = $row['actual_value'] >= $row['target_value'];
        $row['risk_label'] = $pct >= 100 ? 'On Track' : ($pct >= 80 ? 'At Risk' : 'Off Track');
        $row['risk_badge'] = $pct >= 100 ? 'badge-active' : ($pct >= 80 ? 'badge-pending' : 'badge-closed');
        $recent_records[]  = $row;
    }

    // Chart: actual vs target for all active indicators
    $chart_data_res = $conn->query("
        SELECT i.name, i.target_value, i.unit,
               (SELECT r.actual_value FROM qa_records r
                WHERE r.indicator_id = i.indicator_id
                ORDER BY r.year DESC, r.created_at DESC LIMIT 1) AS latest_value
        FROM qa_indicators i
        WHERE i.status='Active'
        ORDER BY i.name
    ");
    $chart_labels = $chart_actuals = $chart_targets = [];
    while ($row = $chart_data_res->fetch_assoc()) {
        if ($row['latest_value'] === null) continue;
        $chart_labels[]  = $row['name'];
        $chart_actuals[] = (float)$row['latest_value'];
        $chart_targets[] = (float)$row['target_value'];
    }

    // Survey response trend (last 6 months)
    $trend_res = $conn->query("
        SELECT DATE_FORMAT(submitted_at,'%b %Y') AS month, COUNT(*) AS count
        FROM survey_responses
        WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(submitted_at,'%Y-%m')
        ORDER BY submitted_at ASC
    ");
    $trend_labels = $trend_counts = [];
    while ($r = $trend_res->fetch_assoc()) {
        $trend_labels[] = $r['month'];
        $trend_counts[] = (int)$r['count'];
    }

    // Action Plans status distribution
    $action_status_res = $conn->query("
        SELECT status, COUNT(*) AS count FROM qa_action_plans GROUP BY status
    ");
    $action_status_labels = $action_status_counts = [];
    while ($row = $action_status_res->fetch_assoc()) {
        $action_status_labels[] = $row['status'];
        $action_status_counts[] = (int)$row['count'];
    }

    // Recent audits (latest 5)
    $recent_audits_res = $conn->query("
        SELECT audit_id, title, scheduled_date, status, findings
        FROM qa_audits
        ORDER BY scheduled_date DESC LIMIT 5
    ");
    $recent_audits = [];
    while ($row = $recent_audits_res->fetch_assoc()) {
        $row['has_findings'] = !empty($row['findings']);
        unset($row['findings']); // don't expose raw findings text in list view
        $recent_audits[] = $row;
    }

    // Pending action plans (latest 5)
    $pending_actions_res = $conn->query("
        SELECT action_id, title, priority, assigned_to, target_date
        FROM qa_action_plans
        WHERE status != 'Closed'
        ORDER BY priority DESC, target_date ASC
        LIMIT 5
    ");
    $pending_actions = [];
    while ($row = $pending_actions_res->fetch_assoc()) {
        $row['is_overdue']    = strtotime($row['target_date']) < time();
        $row['target_date_f'] = date('M d, Y', strtotime($row['target_date']));
        $pending_actions[]    = $row;
    }

    // --- Build final response ---
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_indicators' => safeInt($summary['total_indicators']),
            'total_records'    => safeInt($summary['total_records']),
            'total_surveys'    => safeInt($summary['total_surveys']),
            'active_surveys'   => safeInt($summary['active_surveys']),
            'total_responses'  => safeInt($summary['total_responses']),
            'total_standards'  => safeInt($summary['total_standards']),
            'total_audits'     => safeInt($summary['total_audits']),
            'active_audits'    => safeInt($summary['active_audits']),
            'total_actions'    => safeInt($summary['total_actions']),
            'open_actions'     => safeInt($summary['open_actions']),
            'overdue_actions'  => safeInt($summary['overdue_actions']),
            'total_policies'   => safeInt($summary['total_policies']),
            'meeting_target'   => $meeting_target,
        ],
        'chart' => [
            'labels'  => $chart_labels,
            'actuals' => $chart_actuals,
            'targets' => $chart_targets,
        ],
        'trend' => [
            'labels' => $trend_labels,
            'counts' => $trend_counts,
        ],
        'action_status' => [
            'labels' => $action_status_labels,
            'counts' => $action_status_counts,
        ],
        'recent_records'  => $recent_records,
        'recent_audits'   => $recent_audits,
        'pending_actions' => $pending_actions,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
    ]);
}