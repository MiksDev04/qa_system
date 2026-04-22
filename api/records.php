<?php
// api/records.php – CRUD REST API for qa_records
// ByteBandits QA Management System
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$conn   = getConnection();
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

error_log("Records API called with action: $action, POST data: " . json_encode($_POST));

function respond(bool $ok, string $msg, array $data = []): void {
    echo json_encode(array_merge(['status' => $ok ? 'success' : 'error', 'message' => $msg], $data));
    exit;
}

switch ($action) {
    case 'list':
        $indicator_id = (int)($_GET['indicator_id'] ?? 0);
        $year         = (int)($_GET['year'] ?? 0);
        $semester     = trim($_GET['semester'] ?? '');
        $page         = max(1, (int)($_GET['page'] ?? 1));
        $perPage      = max(1, min(100, (int)($_GET['per_page'] ?? 20)));
        $offset       = ($page - 1) * $perPage;

        $where = ['1=1'];
        $params = [];
        $types = '';

        if ($indicator_id > 0) {
            $where[] = 'r.indicator_id = ?';
            $params[] = $indicator_id;
            $types .= 'i';
        }
        if ($year > 0) {
            $where[] = 'r.year = ?';
            $params[] = $year;
            $types .= 'i';
        }
        if ($semester !== '') {
            $semester = in_array($semester, ['1st', '2nd', 'Summer', 'Annual'], true) ? $semester : 'Annual';
            $where[] = 'r.semester = ?';
            $params[] = $semester;
            $types .= 's';
        }

        $countSql = 'SELECT COUNT(*) AS c FROM qa_records r WHERE ' . implode(' AND ', $where);
        $countStmt = $conn->prepare($countSql);
        if ($types) {
            $countStmt->bind_param($types, ...$params);
        }
        $countStmt->execute();
        $total = (int)$countStmt->get_result()->fetch_assoc()['c'];

        $sql = 'SELECT r.record_id, r.indicator_id, r.year, r.semester, r.actual_value, r.remarks, r.recorded_by, r.source_system, r.external_sync_id, r.created_at, r.updated_at, i.name AS indicator_name, i.target_value, i.unit
                FROM qa_records r
                JOIN qa_indicators i ON r.indicator_id = i.indicator_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY r.year DESC, r.created_at DESC
                LIMIT ? OFFSET ?';

        $stmt = $conn->prepare($sql);
        $allParams = array_merge($params, [$perPage, $offset]);
        $stmt->bind_param($types . 'ii', ...$allParams);
        $stmt->execute();

        $rows = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        respond(true, 'OK', [
            'data' => $rows,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int)max(1, ceil($total / $perPage))
            ]
        ]);
        break;

    case 'create':
        $indicator_id = (int)($_POST['indicator_id'] ?? 0);
        $year         = (int)($_POST['year'] ?? date('Y'));
        $semester     = in_array($_POST['semester'] ?? '', ['1st','2nd','Summer','Annual']) ? $_POST['semester'] : 'Annual';
        $actual_value = (float)($_POST['actual_value'] ?? 0);
        $remarks      = trim($_POST['remarks'] ?? '');
        $recorded_by  = trim($_POST['recorded_by'] ?? '');

        if (!$indicator_id) respond(false, 'Indicator is required.');
        if ($year < 2000 || $year > 2099) respond(false, 'Invalid year.');

        // Check indicator exists
        $chk = $conn->prepare("SELECT indicator_id FROM qa_indicators WHERE indicator_id=?");
        $chk->bind_param('i', $indicator_id);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) respond(false, 'Indicator not found.');

        $stmt = $conn->prepare("INSERT INTO qa_records (indicator_id,year,semester,actual_value,remarks,recorded_by) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('iisdss', $indicator_id, $year, $semester, $actual_value, $remarks, $recorded_by);
        $stmt->execute() ? respond(true, 'Record created.', ['id' => $conn->insert_id]) : respond(false, 'Failed to create record.');
        break;

    case 'update':
        $id           = (int)($_POST['record_id'] ?? 0);
        $indicator_id = (int)($_POST['indicator_id'] ?? 0);
        $year         = (int)($_POST['year'] ?? date('Y'));
        $semester     = in_array($_POST['semester'] ?? '', ['1st','2nd','Summer','Annual']) ? $_POST['semester'] : 'Annual';
        $actual_value = (float)($_POST['actual_value'] ?? 0);
        $remarks      = trim($_POST['remarks'] ?? '');
        $recorded_by  = trim($_POST['recorded_by'] ?? '');

        if (!$id) respond(false, 'Record ID is required.');
        if (!$indicator_id) respond(false, 'Indicator is required.');
        if ($year < 2000 || $year > 2099) respond(false, 'Invalid year.');

        // Check record exists
        $chk_rec = $conn->prepare("SELECT record_id FROM qa_records WHERE record_id=?");
        $chk_rec->bind_param('i', $id);
        $chk_rec->execute();
        if (!$chk_rec->get_result()->fetch_assoc()) respond(false, 'Record not found.');

        // Check indicator exists
        $chk_ind = $conn->prepare("SELECT indicator_id FROM qa_indicators WHERE indicator_id=?");
        $chk_ind->bind_param('i', $indicator_id);
        $chk_ind->execute();
        if (!$chk_ind->get_result()->fetch_assoc()) respond(false, 'Indicator not found.');

        $stmt = $conn->prepare("UPDATE qa_records SET indicator_id=?,year=?,semester=?,actual_value=?,remarks=?,recorded_by=?,updated_at=NOW() WHERE record_id=?");
        if (!$stmt) respond(false, 'Database error: ' . $conn->error);
        
        $stmt->bind_param('iisdssi', $indicator_id, $year, $semester, $actual_value, $remarks, $recorded_by, $id);
        if (!$stmt->execute()) {
            respond(false, 'Update failed: ' . $stmt->error);
        }
        respond(true, 'Record updated.');
        break;

    case 'delete':
        $id = (int)($_POST['record_id'] ?? 0);
        if (!$id) respond(false, 'Record ID is required.');
        $stmt = $conn->prepare("DELETE FROM qa_records WHERE record_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute() ? respond(true, 'Record deleted.') : respond(false, 'Failed to delete record.');
        break;

    default:
        respond(false, 'Unknown action.');
}
