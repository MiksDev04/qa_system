<?php
// api/analytics.php – Analytics & Insights API for Indicators
// ByteBandits QA Management System
// Provides trending analysis, forecasting, benchmarking, and time-series data

require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors directly, log them instead

// Get database connection
$conn = getConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
    exit;
}

$action = trim($_GET['action'] ?? '');

/**
 * Unified JSON response function
 */
function respond(bool $ok, string $msg, array $data = []): void {
    http_response_code($ok ? 200 : 400);
    echo json_encode(array_merge(['status' => $ok ? 'success' : 'error', 'message' => $msg], $data));
    exit;
}

/**
 * Linear regression to forecast future values
 * Returns array of predicted values for next n periods
 */
function linearRegression(array $yValues): array {
    $n = count($yValues);
    if ($n < 2) return [];

    // Calculate simple linear regression: y = mx + b
    $xValues = range(1, $n);
    $sumX = array_sum($xValues);
    $sumY = array_sum($yValues);
    $sumXY = 0;
    $sumXX = 0;

    for ($i = 0; $i < $n; $i++) {
        $sumXY += $xValues[$i] * $yValues[$i];
        $sumXX += $xValues[$i] * $xValues[$i];
    }

    $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
    $intercept = ($sumY - $slope * $sumX) / $n;

    // Predict next 2-5 periods (using 3 as default)
    $forecast = [];
    for ($i = 1; $i <= 3; $i++) {
        $xNext = $n + $i;
        $yPredicted = round($slope * $xNext + $intercept, 2);
        $forecast[] = $yPredicted;
    }

    return $forecast;
}

/**
 * ACTION: timeseries
 * Get historical time-series data for an indicator (all records across years/semesters)
 * Returns: array of records with year, semester, actual_value for charting
 */
if ($action === 'timeseries') {
    $indicator_id = (int)($_GET['indicator_id'] ?? 0);
    if (!$indicator_id) respond(false, 'Indicator ID is required.');

    // Get indicator target and unit
    $indStmt = $conn->prepare("SELECT name, target_value, unit, category FROM qa_indicators WHERE indicator_id=?");
    if (!$indStmt) respond(false, 'Database error: ' . $conn->error);
    
    $indStmt->bind_param('i', $indicator_id);
    if (!$indStmt->execute()) respond(false, 'Query error: ' . $indStmt->error);
    
    $indResult = $indStmt->get_result()->fetch_assoc();
    if (!$indResult) respond(false, 'Indicator not found.');

    // Get all records for this indicator, ordered by year and semester
    $recStmt = $conn->prepare("
        SELECT 
            year,
            semester,
            actual_value
        FROM qa_records
        WHERE indicator_id = ?
        ORDER BY year ASC, FIELD(semester, '1st', '2nd', 'Summer', 'Annual') ASC
    ");
    if (!$recStmt) respond(false, 'Database error preparing records: ' . $conn->error);
    
    $recStmt->bind_param('i', $indicator_id);
    if (!$recStmt->execute()) respond(false, 'Records query error: ' . $recStmt->error);
    
    $recResult = $recStmt->get_result();
    if (!$recResult) respond(false, 'Records result error: ' . $recStmt->error);

    $records = [];
    while ($row = $recResult->fetch_assoc()) {
        $records[] = [
            'year' => (int)$row['year'],
            'semester' => $row['semester'],
            'actual_value' => (float)$row['actual_value']
        ];
    }

    if (empty($records)) {
        respond(false, 'No data available for this indicator.');
    }

    respond(true, 'OK', [
        'data' => $records,
        'indicator' => [
            'name' => $indResult['name'],
            'unit' => $indResult['unit'],
            'target_value' => (float)$indResult['target_value']
        ]
    ]);
}

/**
 * ACTION: trend
 * Calculate trend direction (up/down/stable) and percentage change
 * Compares oldest vs newest value
 */
else if ($action === 'trend') {
    $indicator_id = (int)($_GET['indicator_id'] ?? 0);
    if (!$indicator_id) respond(false, 'Indicator ID is required.');

    // Get all records for this indicator
    $stmt = $conn->prepare("
        SELECT actual_value
        FROM qa_records
        WHERE indicator_id = ?
        ORDER BY year ASC, 
                 CASE semester WHEN '1st' THEN 1 WHEN '2nd' THEN 2 ELSE 3 END ASC
    ");
    $stmt->bind_param('i', $indicator_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $values = [];
    while ($row = $result->fetch_assoc()) {
        $values[] = (float)$row['actual_value'];
    }

    if (count($values) < 2) {
        respond(false, 'Insufficient data for trend analysis (minimum 2 records required).');
    }

    $oldest = $values[0];
    $newest = $values[count($values) - 1];
    $change = $newest - $oldest;
    $percentChange = $oldest != 0 ? round(($change / $oldest) * 100, 2) : 0;

    // Determine direction
    if (abs($change) < 0.01) {
        $direction = 'stable';
    } else if ($change > 0) {
        $direction = 'up';
    } else {
        $direction = 'down';
    }

    respond(true, 'OK', [
        'trend' => $direction,
        'percent_change' => $percentChange,
        'oldest_value' => $oldest,
        'newest_value' => $newest,
        'absolute_change' => round($change, 2),
        'periods_count' => count($values)
    ]);
}

/**
 * ACTION: forecast
 * Linear regression forecasting for next 3 periods
 * Returns predicted values with period labels
 */
else if ($action === 'forecast') {
    $indicator_id = (int)($_GET['indicator_id'] ?? 0);
    if (!$indicator_id) respond(false, 'Indicator ID is required.');

    // Get all records for this indicator
    $stmt = $conn->prepare("
        SELECT actual_value
        FROM qa_records
        WHERE indicator_id = ?
        ORDER BY year ASC, FIELD(semester, '1st', '2nd', 'Summer', 'Annual') ASC
    ");
    $stmt->bind_param('i', $indicator_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $values = [];
    while ($row = $result->fetch_assoc()) {
        $values[] = (float)$row['actual_value'];
    }

    if (count($values) < 2) {
        respond(false, 'Insufficient data for forecasting (minimum 2 records required).');
    }

    $forecasted = linearRegression($values);
    
    $forecast = [];
    for ($i = 0; $i < count($forecasted); $i++) {
        $forecast[] = [
            'period' => $i + 1,
            'predicted_value' => $forecasted[$i]
        ];
    }

    respond(true, 'OK', [
        'forecast' => $forecast,
        'based_on_records' => count($values),
        'accuracy_note' => 'Linear regression based on historical trend'
    ]);
}

/**
 * ACTION: benchmark
 * Compare indicator performance against category average
 * Provides peer comparison and ranking within category
 */
else if ($action === 'benchmark') {
    $indicator_id = (int)($_GET['indicator_id'] ?? 0);
    if (!$indicator_id) respond(false, 'Indicator ID is required.');

    // Get indicator details and category
    $indStmt = $conn->prepare("
        SELECT indicator_id, name, target_value, category
        FROM qa_indicators
        WHERE indicator_id = ? AND status = 'Active'
    ");
    $indStmt->bind_param('i', $indicator_id);
    $indStmt->execute();
    $indicator = $indStmt->get_result()->fetch_assoc();
    if (!$indicator) respond(false, 'Indicator not found or inactive.');

    // Get latest actual value for this indicator
    $yourStmt = $conn->prepare("
        SELECT actual_value
        FROM qa_records
        WHERE indicator_id = ?
        ORDER BY year DESC, FIELD(semester, 'Annual', '2nd', '1st', 'Summer') ASC
        LIMIT 1
    ");
    $yourStmt->bind_param('i', $indicator_id);
    $yourStmt->execute();
    $yourResult = $yourStmt->get_result()->fetch_assoc();
    $currentValue = $yourResult ? (float)$yourResult['actual_value'] : null;

    if ($currentValue === null) {
        respond(false, 'No actual value found for this indicator.');
    }

    // Get all active indicators in the same category (for comparison)
    $catStmt = $conn->prepare("
        SELECT i.indicator_id, i.name, i.target_value
        FROM qa_indicators i
        WHERE i.category = ? AND i.status = 'Active'
        ORDER BY i.name ASC
    ");
    $catStmt->bind_param('s', $indicator['category']);
    $catStmt->execute();
    $catResult = $catStmt->get_result();

    $benchmarks = [];
    $categoryValues = [];
    while ($row = $catResult->fetch_assoc()) {
        // Get latest value for each category indicator
        $latestStmt = $conn->prepare("
            SELECT actual_value
            FROM qa_records
            WHERE indicator_id = ?
            ORDER BY year DESC, FIELD(semester, 'Annual', '2nd', '1st', 'Summer') ASC
            LIMIT 1
        ");
        $latestStmt->bind_param('i', $row['indicator_id']);
        $latestStmt->execute();
        $latestResult = $latestStmt->get_result()->fetch_assoc();
        $actualVal = $latestResult ? (float)$latestResult['actual_value'] : null;

        if ($actualVal !== null) {
            $categoryValues[] = $actualVal;
        }

        $benchmarks[] = [
            'name' => $row['name'],
            'target' => (float)$row['target_value'],
            'actual' => $actualVal
        ];
    }

    // Calculate category average
    $categoryAverage = !empty($categoryValues) ? round(array_sum($categoryValues) / count($categoryValues), 2) : 0;
    $performanceVsCategory = $categoryAverage != 0 ? round($currentValue - $categoryAverage, 2) : 0;

    respond(true, 'OK', [
        'current_value' => $currentValue,
        'category_average' => $categoryAverage,
        'performance_vs_category' => $performanceVsCategory,
        'category' => $indicator['category'],
        'benchmarks' => $benchmarks
    ]);
}

/**
 * Default: Invalid action
 */
else {
    respond(false, 'Invalid action. Supported actions: timeseries, trend, forecast, benchmark');
}
