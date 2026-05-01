<?php
// api/fetch_lms_performance.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function fetchArtisansLMS($action, $year = null, $semester = null) {
    $apiKey = '0fvBAvRhGAkES6QVHXYojIVDQq5iPiRl';
    $baseUrl = 'https://artisanslms.onrender.com/backend/api/export_student_performance.php';
    
    $params = ['action' => $action];
    if ($year) $params['year'] = $year;
    if ($semester) $params['semester'] = $semester;
    
    $url = $baseUrl . '?' . http_build_query($params);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['X-API-Key: ' . $apiKey],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false, // Add this for external API
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['status' => 'error', 'message' => 'CURL Error: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        return ['status' => 'error', 'message' => "API returned HTTP $httpCode"];
    }
    
    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['status' => 'error', 'message' => 'Invalid JSON response: ' . json_last_error_msg()];
    }
    
    return $decoded;
}

// Handle the request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $year = $_GET['year'] ?? null;
    $semester = $_GET['semester'] ?? null;
    
    if (!$action) {
        echo json_encode(['status' => 'error', 'message' => 'Action parameter required']);
        exit;
    }
    
    $result = fetchArtisansLMS($action, $year, $semester);
    echo json_encode($result);
}
?>