<?php
// api/pdf_generator.php – Enhanced PDF Report Generation with Analytics
// ByteBandits QA Management System

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';

$action = trim($_GET['action'] ?? 'kpi_with_analytics');
$year_from = (int)($_GET['year_from'] ?? date('Y') - 1);
$year_to = (int)($_GET['year_to'] ?? date('Y'));
$category = trim($_GET['category'] ?? '');
$conn = getConnection();

/**
 * Generate KPI Report with Analytics (Trends, Benchmarks, Forecasts)
 */
if ($action === 'kpi_with_analytics') {
    $indicators = [];
    
    $sql = "SELECT DISTINCT i.indicator_id, i.name, i.category, i.target_value, i.unit
            FROM qa_indicators i
            WHERE i.status = 'Active'";
    
    if ($category) {
        $sql .= " AND i.category = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $category);
    } else {
        $stmt = $conn->prepare($sql);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($indicator = $result->fetch_assoc()) {
        $ind_id = $indicator['indicator_id'];
        
        // Get time-series data for trend chart
        $sql_ts = "SELECT year, semester, actual_value 
                   FROM qa_records 
                   WHERE indicator_id = ? AND year BETWEEN ? AND ?
                   ORDER BY year, semester";
        $stmt_ts = $conn->prepare($sql_ts);
        $stmt_ts->bind_param('iii', $ind_id, $year_from, $year_to);
        $stmt_ts->execute();
        $timeseries = $stmt_ts->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Calculate trend
        $values = array_column($timeseries, 'actual_value');
        $trend_direction = 'stable';
        $trend_pct = 0;
        
        if (count($values) >= 2) {
            $first = $values[0];
            $last = $values[count($values) - 1];
            $trend_pct = $first > 0 ? round((($last - $first) / $first) * 100, 1) : 0;
            $trend_direction = $trend_pct > 5 ? 'increasing' : ($trend_pct < -5 ? 'decreasing' : 'stable');
        }
        
        // Get category average (benchmark)
        $sql_bench = "SELECT ROUND(AVG(r.actual_value), 2) as category_avg
                      FROM qa_records r
                      JOIN qa_indicators i ON r.indicator_id = i.indicator_id
                      WHERE i.category = ? AND r.year BETWEEN ? AND ?";
        $stmt_bench = $conn->prepare($sql_bench);
        $stmt_bench->bind_param('sii', $indicator['category'], $year_from, $year_to);
        $stmt_bench->execute();
        $benchmark = $stmt_bench->get_result()->fetch_assoc();
        
        $latest = end($values);
        $category_avg = $benchmark['category_avg'] ?? 0;
        $variance = $category_avg > 0 ? $latest - $category_avg : 0;
        $performance_vs_target = $latest > 0 && $indicator['target_value'] > 0 ? 
            round(($latest / $indicator['target_value']) * 100, 1) : 0;
        
        // Forecast using linear regression
        $forecast = [];
        if (count($values) >= 2) {
            $n = count($values);
            $sum_x = $n * ($n - 1) / 2;
            $sum_y = array_sum($values);
            $sum_xy = 0;
            $sum_x2 = $n * ($n - 1) * (2 * $n - 1) / 6;
            
            for ($i = 0; $i < $n; $i++) {
                $sum_xy += $i * $values[$i];
            }
            
            $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
            $intercept = ($sum_y - $slope * $sum_x) / $n;
            
            // Predict next 3 periods
            for ($i = 1; $i <= 3; $i++) {
                $predicted = $intercept + $slope * ($n + $i - 1);
                $forecast[] = [
                    'period' => "P+" . $i,
                    'predicted_value' => round($predicted, 2)
                ];
            }
        }
        
        $indicators[] = [
            'indicator_id' => $ind_id,
            'name' => $indicator['name'],
            'category' => $indicator['category'],
            'target' => $indicator['target_value'],
            'unit' => $indicator['unit'],
            'timeseries' => $timeseries,
            'trend' => [
                'direction' => $trend_direction,
                'percent_change' => $trend_pct
            ],
            'benchmark' => [
                'your_value' => $latest,
                'category_average' => $category_avg,
                'variance' => $variance,
                'performance_vs_target' => $performance_vs_target
            ],
            'forecast' => $forecast
        ];
    }
    
    $response = [
        'status' => 'success',
        'report_type' => 'kpi_with_analytics',
        'period' => "$year_from - $year_to",
        'indicators' => $indicators
    ];
    
    echo json_encode($response);
}

/**
 * Generate Summary Statistics for Report Header
 */
elseif ($action === 'summary_stats') {
    $sql = "SELECT 
                COUNT(DISTINCT i.indicator_id) as total_indicators,
                SUM(CASE WHEN r.actual_value >= i.target_value THEN 1 ELSE 0 END) as on_target,
                ROUND(AVG((r.actual_value / i.target_value) * 100), 1) as avg_performance
            FROM qa_records r
            JOIN qa_indicators i ON r.indicator_id = i.indicator_id
            WHERE r.year BETWEEN ? AND ? AND i.status = 'Active'";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $year_from, $year_to);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    $response = [
        'status' => 'success',
        'total_indicators' => $stats['total_indicators'],
        'on_target_count' => $stats['on_target'],
        'on_target_percentage' => $stats['total_indicators'] > 0 ? 
            round(($stats['on_target'] / $stats['total_indicators']) * 100, 1) : 0,
        'average_performance' => $stats['avg_performance']
    ];
    
    echo json_encode($response);
}

/**
 * Generate Comparison Data (Before/After for Indicator)
 */
elseif ($action === 'period_comparison') {
    $indicator_id = (int)($_GET['indicator_id'] ?? 0);
    
    if (!$indicator_id) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'indicator_id required']);
        exit;
    }
    
    // Get indicator info
    $sql = "SELECT name, category, target_value, unit FROM qa_indicators WHERE indicator_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $indicator_id);
    $stmt->execute();
    $indicator = $stmt->get_result()->fetch_assoc();
    
    // Compare start vs end of period
    $sql = "SELECT 
                MIN(r.actual_value) as start_value,
                MAX(r.actual_value) as end_value,
                ROUND(AVG(r.actual_value), 2) as avg_value,
                COUNT(*) as data_points
            FROM qa_records r
            WHERE r.indicator_id = ? AND r.year BETWEEN ? AND ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $indicator_id, $year_from, $year_to);
    $stmt->execute();
    $comparison = $stmt->get_result()->fetch_assoc();
    
    $improvement = $comparison['end_value'] - $comparison['start_value'];
    $improvement_pct = $comparison['start_value'] > 0 ? 
        round(($improvement / $comparison['start_value']) * 100, 1) : 0;
    
    $response = [
        'status' => 'success',
        'indicator' => $indicator,
        'period_start_value' => $comparison['start_value'],
        'period_end_value' => $comparison['end_value'],
        'period_average_value' => $comparison['avg_value'],
        'improvement' => $improvement,
        'improvement_percentage' => $improvement_pct,
        'data_points' => $comparison['data_points']
    ];
    
    echo json_encode($response);
}

else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
}

$conn->close();
?>