<?php
// api/action_plans.php – Corrective & Preventive Action API
// ByteBandits QA Management System
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$conn = getConnection();
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

function respond(bool $ok, string $msg, array $data = []): void {
    echo json_encode(array_merge(['status' => $ok ? 'success' : 'error', 'message' => $msg], $data));
    exit;
}

function allowedValue(string $value, array $allowed, string $fallback): string {
    return in_array($value, $allowed, true) ? $value : $fallback;
}

if ($action === 'create') {
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $root = trim($_POST['root_cause'] ?? '');
    $auditId = (int)($_POST['audit_id'] ?? 0);
    $type = allowedValue(trim($_POST['action_type'] ?? 'Corrective'), ['Corrective', 'Preventive', 'Improvement'], 'Corrective');
    $priority = allowedValue(trim($_POST['priority'] ?? 'High'), ['Critical', 'High', 'Medium', 'Low'], 'High');
    $assigned = trim($_POST['assigned_to'] ?? '');
    $email = trim($_POST['assigned_to_email'] ?? '');
    $dept = trim($_POST['department'] ?? '');
    $target = trim($_POST['target_date'] ?? '');
    $outcome = trim($_POST['expected_outcome'] ?? '');

    if (!$title || !$assigned || !$target) {
        http_response_code(400);
        respond(false, 'Missing required fields');
    }

    if ($auditId > 0) {
        $chk = $conn->prepare('SELECT audit_id FROM qa_audits WHERE audit_id = ? LIMIT 1');
        $chk->bind_param('i', $auditId);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
            http_response_code(400);
            respond(false, 'Invalid audit_id');
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO qa_action_plans (audit_id, title, description, root_cause, action_type, priority, assigned_to, assigned_to_email, department, target_date, expected_outcome, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Open')
    ");
    $stmt->bind_param('issssssssss', $auditId, $title, $desc, $root, $type, $priority, $assigned, $email, $dept, $target, $outcome);

    if ($stmt->execute()) {
        respond(true, 'Action plan created');
    } else {
        http_response_code(500);
        respond(false, $stmt->error);
    }
} elseif ($action === 'update') {
    $id = (int)($_POST['action_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $root = trim($_POST['root_cause'] ?? '');
    $auditId = (int)($_POST['audit_id'] ?? 0);
    $type = allowedValue(trim($_POST['action_type'] ?? 'Corrective'), ['Corrective', 'Preventive', 'Improvement'], 'Corrective');
    $priority = allowedValue(trim($_POST['priority'] ?? 'High'), ['Critical', 'High', 'Medium', 'Low'], 'High');
    $assigned = trim($_POST['assigned_to'] ?? '');
    $email = trim($_POST['assigned_to_email'] ?? '');
    $dept = trim($_POST['department'] ?? '');
    $target = trim($_POST['target_date'] ?? '');
    $outcome = trim($_POST['expected_outcome'] ?? '');

    if (!$id || !$title) {
        http_response_code(400);
        respond(false, 'Action ID and title are required');
    }

    if ($auditId > 0) {
        $chk = $conn->prepare('SELECT audit_id FROM qa_audits WHERE audit_id = ? LIMIT 1');
        $chk->bind_param('i', $auditId);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
            http_response_code(400);
            respond(false, 'Invalid audit_id');
        }
    }

    $stmt = $conn->prepare("
        UPDATE qa_action_plans
        SET audit_id=?, title=?, description=?, root_cause=?, action_type=?, priority=?, assigned_to=?, assigned_to_email=?, department=?, target_date=?, expected_outcome=?
        WHERE action_id=?
    ");
    $stmt->bind_param('issssssssssi', $auditId, $title, $desc, $root, $type, $priority, $assigned, $email, $dept, $target, $outcome, $id);

    if ($stmt->execute()) {
        respond(true, 'Action plan updated');
    } else {
        http_response_code(500);
        respond(false, 'Failed to update action plan');
    }
} elseif ($action === 'get_details') {
    $id = (int)($_GET['action_id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        respond(false, 'Action ID is required');
    }

    $stmt = $conn->prepare("SELECT ap.*, a.title AS audit_title FROM qa_action_plans ap LEFT JOIN qa_audits a ON ap.audit_id = a.audit_id WHERE ap.action_id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        respond(true, 'OK', ['data' => $data]);
    } else {
        http_response_code(404);
        respond(false, 'Action plan not found');
    }
} elseif ($action === 'close') {
    $id = (int)($_POST['action_id'] ?? 0);
    $outcome = trim($_POST['actual_outcome'] ?? '');
    $verified_by = trim($_POST['verified_by'] ?? '');

    if (!$id) {
        http_response_code(400);
        respond(false, 'Action ID is required');
    }

    $stmt = $conn->prepare("
        UPDATE qa_action_plans
        SET status='Closed', actual_date=NOW(), actual_outcome=?, effectiveness_verified=1, verified_by=?, verified_date=NOW()
        WHERE action_id=?
    ");
    $stmt->bind_param('ssi', $outcome, $verified_by, $id);

    if ($stmt->execute()) {
        respond(true, 'Action plan closed');
    } else {
        http_response_code(500);
        respond(false, 'Failed to close action plan');
    }
} elseif ($action === 'list') {
    $status = trim($_GET['status'] ?? '');
    $priority = trim($_GET['priority'] ?? '');

    $where = ['1=1'];
    $params = [];
    $types = '';

    if ($status !== '') {
           $where[] = 'ap.status = ?';
        $params[] = $status;
        $types .= 's';
    }
    if ($priority !== '') {
           $where[] = 'ap.priority = ?';
        $params[] = $priority;
        $types .= 's';
    }

        $sql = 'SELECT ap.*, a.title AS audit_title
                FROM qa_action_plans ap
                LEFT JOIN qa_audits a ON ap.audit_id = a.audit_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ap.target_date ASC, ap.created_at DESC';
    $stmt = $conn->prepare($sql);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    respond(true, 'OK', ['data' => $rows]);
} elseif ($action === 'delete') {
    $id = (int)($_POST['action_id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        respond(false, 'Action ID is required');
    }

    $stmt = $conn->prepare('DELETE FROM qa_action_plans WHERE action_id=?');
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        respond(true, 'Action plan deleted');
    } else {
        http_response_code(500);
        respond(false, 'Failed to delete action plan');
    }
} else {
    http_response_code(400);
    respond(false, 'Invalid action');
}
?>
