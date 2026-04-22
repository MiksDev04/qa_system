<?php
// api/executive_report.php – Enhanced Executive Summary API with Real-World QA Logic
// ByteBandits QA Management System
// Improvements: Risk alerts, quality score, compliance health, yearly trends, advanced filters

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

$conn = getConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$action = trim($_GET['action'] ?? 'summary');
$year_from = (int)($_GET['year_from'] ?? date('Y') - 1);
$year_to = (int)($_GET['year_to'] ?? date('Y'));
$category = trim($_GET['category'] ?? '');
$semester = trim($_GET['semester'] ?? '');
$indicator_id = (int)($_GET['indicator_id'] ?? 0);

// Helper to build WHERE clause for semester filter
function buildSemesterCondition($semester, $conn) {
    if (empty($semester) || $semester === 'All') return '';
    return " AND r.semester = '" . $conn->real_escape_string($semester) . "'";
}

/**
 * Executive Dashboard Summary
 */
if ($action === 'summary') {
    $response = [
        'status' => 'success',
        'report_type' => 'executive_dashboard',
        'generated_at' => date('Y-m-d H:i:s'),
        'period' => "FY $year_from - $year_to" . ($semester && $semester !== 'All' ? " ($semester Sem)" : ""),
        'metrics' => []
    ];

    $semCond = buildSemesterCondition($semester, $conn);
    $catCond = !empty($category) && $category !== 'All' ? " AND i.category = '" . $conn->real_escape_string($category) . "'" : "";

    // Overall Performance Scorecard
    $sql = "SELECT 
                COUNT(*) as total_indicators,
                SUM(CASE WHEN r.actual_value >= i.target_value THEN 1 ELSE 0 END) as on_track,
                SUM(CASE WHEN r.actual_value >= i.target_value * 0.8 AND r.actual_value < i.target_value THEN 1 ELSE 0 END) as at_risk,
                SUM(CASE WHEN r.actual_value < i.target_value * 0.8 THEN 1 ELSE 0 END) as off_track,
                ROUND(AVG((r.actual_value / i.target_value) * 100), 1) as avg_performance
            FROM qa_records r
            JOIN qa_indicators i ON r.indicator_id = i.indicator_id
            WHERE r.year BETWEEN ? AND ? AND i.status = 'Active' $semCond $catCond";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $year_from, $year_to);
    $stmt->execute();
    $scorecard = $stmt->get_result()->fetch_assoc();

    $response['metrics']['overall_scorecard'] = [
        'total_indicators' => (int)$scorecard['total_indicators'],
        'on_track' => (int)$scorecard['on_track'],
        'at_risk' => (int)$scorecard['at_risk'],
        'off_track' => (int)$scorecard['off_track'],
        'avg_performance_pct' => (float)$scorecard['avg_performance'],
        'completion_rate' => $scorecard['total_indicators'] > 0 ? 
            round((($scorecard['on_track'] + $scorecard['at_risk']) / $scorecard['total_indicators']) * 100, 1) : 0
    ];

    echo json_encode($response);
}

/**
 * Full Executive Report (Enhanced with Real-World QA Logic)
 */
elseif ($action === 'full_report') {
    $semCond = buildSemesterCondition($semester, $conn);
    $catCond = !empty($category) && $category !== 'All' ? " AND i.category = '" . $conn->real_escape_string($category) . "'" : "";
    
    // Get scorecard
    $sql = "SELECT 
                COUNT(*) as total_indicators,
                SUM(CASE WHEN r.actual_value >= i.target_value THEN 1 ELSE 0 END) as on_track,
                SUM(CASE WHEN r.actual_value >= i.target_value * 0.8 AND r.actual_value < i.target_value THEN 1 ELSE 0 END) as at_risk,
                SUM(CASE WHEN r.actual_value < i.target_value * 0.8 THEN 1 ELSE 0 END) as off_track,
                ROUND(AVG((r.actual_value / i.target_value) * 100), 1) as avg_performance
            FROM qa_records r
            JOIN qa_indicators i ON r.indicator_id = i.indicator_id
            WHERE r.year BETWEEN ? AND ? AND i.status = 'Active' $semCond $catCond";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $year_from, $year_to);
    $stmt->execute();
    $scorecard = $stmt->get_result()->fetch_assoc();

    // Detailed indicators
    $sql = "SELECT 
                i.indicator_id, i.name, i.category, i.target_value, i.unit,
                r.actual_value, r.year, r.semester,
                ROUND((r.actual_value / i.target_value) * 100, 1) as performance_pct,
                CASE 
                    WHEN r.actual_value >= i.target_value THEN 'On Track'
                    WHEN r.actual_value >= i.target_value * 0.8 THEN 'At Risk'
                    ELSE 'Off Track'
                END as status
            FROM qa_records r
            JOIN qa_indicators i ON r.indicator_id = i.indicator_id
            WHERE r.year BETWEEN ? AND ? AND i.status = 'Active' $semCond $catCond
            ORDER BY i.category, performance_pct DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $year_from, $year_to);
    $stmt->execute();
    $indicators = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Survey stats
    $sql = "SELECT 
                COUNT(DISTINCT s.survey_id) as total_surveys,
                COUNT(DISTINCT sr.response_id) as total_responses,
                ROUND(AVG(sa.rating), 2) as avg_rating
            FROM surveys s
            LEFT JOIN survey_responses sr ON s.survey_id = sr.survey_id
            LEFT JOIN survey_answers sa ON sr.response_id = sa.response_id AND sa.rating IS NOT NULL
            WHERE s.status = 'Active'";
    $survey_stats = $conn->query($sql)->fetch_assoc();

    // Action Plans
    $sql = "SELECT 
                COUNT(*) as total_actions,
                SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed_actions,
                SUM(CASE WHEN status IN ('Open','In Progress','Pending Verification') THEN 1 ELSE 0 END) as open_actions,
                SUM(CASE WHEN target_date < CURDATE() AND status NOT IN ('Closed','Cancelled') THEN 1 ELSE 0 END) as overdue_actions
            FROM qa_action_plans";
    $action_stats = $conn->query($sql)->fetch_assoc();
    $action_stats['completion_rate'] = $action_stats['total_actions'] > 0 ? 
        round(($action_stats['closed_actions'] / $action_stats['total_actions']) * 100, 1) : 0;

    // ========== REAL-WORLD QA LOGIC ==========
    
    // 1. Quality Score (weighted: performance 50%, action completion 20%, satisfaction 30%)
    $avgPerf = (float)$scorecard['avg_performance'];
    $actionCompletion = (float)$action_stats['completion_rate'];
    $satScore = (float)($survey_stats['avg_rating'] / 5 * 100);
    $qualityScore = round(($avgPerf * 0.5) + ($actionCompletion * 0.2) + ($satScore * 0.3), 1);
    $grade = $qualityScore >= 85 ? 'A' : ($qualityScore >= 70 ? 'B' : ($qualityScore >= 55 ? 'C' : 'F'));
    
    // 2. Risk Alerts: Indicators off-track for 2+ consecutive years
    $riskAlerts = [];
    $sql = "SELECT i.name, r.year, r.actual_value, i.target_value, 
                   ROUND((r.actual_value / i.target_value) * 100, 1) as pct
            FROM qa_records r
            JOIN qa_indicators i ON r.indicator_id = i.indicator_id
            WHERE r.year >= ? AND i.status = 'Active'
            ORDER BY i.indicator_id, r.year DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $year_from);
    $stmt->execute();
    $allRecords = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $indicatorTrends = [];
    foreach ($allRecords as $rec) {
        $indicatorTrends[$rec['name']][] = $rec['pct'];
    }
    foreach ($indicatorTrends as $name => $trend) {
        if (count($trend) >= 2 && $trend[0] < 80 && $trend[1] < 80) {
            $riskAlerts[] = [
                'indicator' => $name,
                'severity' => $trend[0] < 60 ? 'Critical' : 'High',
                'recommendation' => 'Immediate corrective action required. Review process and resources.'
            ];
        } elseif (count($trend) >= 2 && $trend[0] < 80 && $trend[1] >= 80) {
            $riskAlerts[] = [
                'indicator' => $name,
                'severity' => 'Improving',
                'recommendation' => 'Performance improving but still below target. Sustain interventions.'
            ];
        }
    }
    
    // 3. Yearly Trend Analysis
    $yearlyTrend = [];
    for ($y = $year_from; $y <= $year_to; $y++) {
        $sql = "SELECT 
                    AVG((r.actual_value / i.target_value) * 100) as avg_perf,
                    SUM(CASE WHEN r.actual_value >= i.target_value THEN 1 ELSE 0 END) as on_track,
                    COUNT(*) as total
                FROM qa_records r
                JOIN qa_indicators i ON r.indicator_id = i.indicator_id
                WHERE r.year = ? AND i.status = 'Active'";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $y);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $yearlyTrend[$y] = [
            'avg_performance' => round($row['avg_perf'] ?? 0, 1),
            'on_track' => (int)$row['on_track'],
            'total' => (int)$row['total'],
            'change' => ($y > $year_from) ? round(($row['avg_perf'] - $yearlyTrend[$y-1]['avg_performance']), 1) : 0
        ];
    }
    
    // 4. Compliance Health
    $standardsCount = $conn->query("SELECT COUNT(*) as cnt FROM qa_standards WHERE status='Active'")->fetch_assoc()['cnt'];
    $policiesCount = $conn->query("SELECT COUNT(*) as cnt FROM qa_policies WHERE status='Active'")->fetch_assoc()['cnt'];
    $pendingAudits = $conn->query("SELECT COUNT(*) as cnt FROM qa_audits WHERE status IN ('Pending','In Progress')")->fetch_assoc()['cnt'];
    $complianceRate = $standardsCount > 0 ? round(($policiesCount / ($standardsCount * 2)) * 100, 1) : 100;
    
    // Build final response
    $response = [
        'status' => 'success',
        'report_type' => 'executive_full_report',
        'generated_at' => date('Y-m-d H:i:s'),
        'organization' => 'Pamantasan ng Lungsod ng San Pablo',
        'report_title' => 'Quality Assurance Executive Summary',
        'period' => "Fiscal Year $year_from - $year_to" . ($semester && $semester !== 'All' ? " ($semester Semester)" : ""),
        'scorecard' => [
            'total_indicators' => (int)$scorecard['total_indicators'],
            'on_track' => (int)$scorecard['on_track'],
            'at_risk' => (int)$scorecard['at_risk'],
            'off_track' => (int)$scorecard['off_track'],
            'avg_performance_pct' => (float)$scorecard['avg_performance'],
            'completion_rate' => $scorecard['total_indicators'] > 0 ? 
                round((($scorecard['on_track'] + $scorecard['at_risk']) / $scorecard['total_indicators']) * 100, 1) : 0
        ],
        'indicators' => $indicators,
        'surveys' => [
            'total_surveys' => (int)$survey_stats['total_surveys'],
            'total_responses' => (int)$survey_stats['total_responses'],
            'avg_rating' => (float)($survey_stats['avg_rating'] ?? 0)
        ],
        'action_plans' => $action_stats,
        'quality_score' => ['score' => $qualityScore, 'grade' => $grade],
        'risk_alerts' => $riskAlerts,
        'yearly_trend' => $yearlyTrend,
        'compliance_health' => [
            'standards' => (int)$standardsCount,
            'policies' => (int)$policiesCount,
            'audits_pending' => (int)$pendingAudits,
            'compliance_rate' => (int)$complianceRate
        ]
    ];
    
    echo json_encode($response);
}

/**
 * Trend Analysis for Indicator
 */
elseif ($action === 'trends') {
    if (!$indicator_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'indicator_id required']);
        exit;
    }
    $sql = "SELECT r.year, r.semester, r.actual_value, i.target_value, i.name, i.unit
            FROM qa_records r JOIN qa_indicators i ON r.indicator_id = i.indicator_id
            WHERE r.indicator_id = ? AND r.year BETWEEN ? AND ?
            ORDER BY r.year, r.semester";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $indicator_id, $year_from, $year_to);
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $values = array_column($records, 'actual_value');
    $trend = 'stable';
    $pct_change = 0;
    if (count($values) >= 2) {
        $first = $values[0];
        $last = $values[count($values) - 1];
        $pct_change = $first > 0 ? round((($last - $first) / $first) * 100, 1) : 0;
        $trend = $pct_change > 5 ? 'increasing' : ($pct_change < -5 ? 'decreasing' : 'stable');
    }
    $response = [
        'status' => 'success',
        'report_type' => 'indicator_trends',
        'indicator_id' => $indicator_id,
        'trend_direction' => $trend,
        'percent_change' => $pct_change,
        'data_points' => $records,
        'summary' => [
            'first_value' => $records[0]['actual_value'] ?? null,
            'last_value' => $records[count($records)-1]['actual_value'] ?? null,
            'min' => min($values) ?? 0,
            'max' => max($values) ?? 0,
            'average' => round(array_sum($values) / count($values), 2)
        ]
    ];
    echo json_encode($response);
}

elseif ($action === 'benchmarks') {
    if (!$indicator_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'indicator_id required']);
        exit;
    }
    $sql = "SELECT i.indicator_id, i.name, i.category, i.target_value, i.unit, r.actual_value, r.year
            FROM qa_indicators i LEFT JOIN qa_records r ON i.indicator_id = r.indicator_id AND r.year = ?
            WHERE i.indicator_id = ? ORDER BY r.year DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $year_to, $indicator_id);
    $stmt->execute();
    $indicator = $stmt->get_result()->fetch_assoc();
    
    $sql = "SELECT ROUND(AVG(r.actual_value), 2) as category_avg, COUNT(DISTINCT r.indicator_id) as peer_count
            FROM qa_records r JOIN qa_indicators i ON r.indicator_id = i.indicator_id
            WHERE i.category = ? AND r.year BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sii', $indicator['category'], $year_from, $year_to);
    $stmt->execute();
    $benchmark = $stmt->get_result()->fetch_assoc();
    
    $current = $indicator['actual_value'] ?? 0;
    $category_avg = $benchmark['category_avg'] ?? 0;
    $variance = $category_avg > 0 ? $current - $category_avg : 0;
    $variance_pct = $category_avg > 0 ? round(($variance / $category_avg) * 100, 1) : 0;
    
    $response = [
        'status' => 'success',
        'report_type' => 'benchmark_analysis',
        'indicator' => [
            'id' => $indicator['indicator_id'],
            'name' => $indicator['name'],
            'category' => $indicator['category'],
            'unit' => $indicator['unit']
        ],
        'performance' => [
            'your_value' => (float)$current,
            'target_value' => (float)$indicator['target_value'],
            'category_average' => (float)$category_avg,
            'variance_from_category_avg' => (float)$variance,
            'variance_pct' => (float)$variance_pct,
            'performance_vs_target' => round(($current / $indicator['target_value']) * 100, 1),
            'performance_vs_category' => $variance_pct > 0 ? 'above_average' : 'below_average'
        ],
        'peer_count' => (int)$benchmark['peer_count']
    ];
    echo json_encode($response);
}

else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Unknown action: ' . $action]);
}

$conn->close();
?>