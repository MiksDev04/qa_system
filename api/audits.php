<?php
// api/audits.php – Internal Audits API
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

function hasAuditStandardLink(mysqli $conn): bool {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $res = $conn->query("SHOW COLUMNS FROM qa_audits LIKE 'standard_id'");
    $cached = $res && $res->num_rows > 0;
    return $cached;
}

if ($action === 'create') {
    $hasStandard = hasAuditStandardLink($conn);
    $title = trim($_POST['title'] ?? '');
    $type = trim($_POST['audit_type'] ?? 'Internal Audit');
    $standardId = (int)($_POST['standard_id'] ?? 0);
    $scope = trim($_POST['scope'] ?? '');
    $findings = trim($_POST['findings'] ?? '');
    $scheduled = trim($_POST['scheduled_date'] ?? '');
    $status = allowedValue(trim($_POST['status'] ?? 'Pending'), ['Pending', 'In Progress', 'Completed', 'Cancelled'], 'Pending');
    $auditor_name = trim($_POST['auditor_name'] ?? '');
    $auditor_email = trim($_POST['auditor_email'] ?? '');

    if (!$title || !$scheduled) {
        http_response_code(400);
        respond(false, 'Missing required fields');
    }

    if ($hasStandard && $standardId > 0) {
        $chk = $conn->prepare('SELECT standard_id FROM qa_standards WHERE standard_id = ? LIMIT 1');
        $chk->bind_param('i', $standardId);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
            http_response_code(400);
            respond(false, 'Invalid standard_id');
        }
    }

    if ($hasStandard) {
        $stmt = $conn->prepare(
            'INSERT INTO qa_audits (standard_id, audit_type, title, description, scope, findings, scheduled_date, status, auditor_name, auditor_email)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('isssssssss', $standardId, $type, $title, $title, $scope, $findings, $scheduled, $status, $auditor_name, $auditor_email);
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO qa_audits (audit_type, title, description, scope, findings, scheduled_date, status, auditor_name, auditor_email)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssssssss', $type, $title, $title, $scope, $findings, $scheduled, $status, $auditor_name, $auditor_email);
    }

    if ($stmt->execute()) {
        respond(true, 'Audit created');
    }

    http_response_code(500);
    respond(false, $stmt->error);
}

if ($action === 'update') {
    $hasStandard = hasAuditStandardLink($conn);
    $id = (int)($_POST['audit_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $type = trim($_POST['audit_type'] ?? 'Internal Audit');
    $standardId = (int)($_POST['standard_id'] ?? 0);
    $scope = trim($_POST['scope'] ?? '');
    $findings = trim($_POST['findings'] ?? '');
    $actual_date = !empty($_POST['actual_date']) ? trim($_POST['actual_date']) : null;
    $status = allowedValue(trim($_POST['status'] ?? 'Pending'), ['Pending', 'In Progress', 'Completed', 'Cancelled'], 'Pending');
    $auditor_name = trim($_POST['auditor_name'] ?? '');
    $auditor_email = trim($_POST['auditor_email'] ?? '');

    if (!$id || !$title) {
        http_response_code(400);
        respond(false, 'Audit ID and title are required');
    }

    if ($hasStandard && $standardId > 0) {
        $chk = $conn->prepare('SELECT standard_id FROM qa_standards WHERE standard_id = ? LIMIT 1');
        $chk->bind_param('i', $standardId);
        $chk->execute();
        if (!$chk->get_result()->fetch_assoc()) {
            http_response_code(400);
            respond(false, 'Invalid standard_id');
        }
    }

    if ($hasStandard) {
        $stmt = $conn->prepare(
            'UPDATE qa_audits
             SET standard_id=?, title=?, audit_type=?, description=?, scope=?, findings=?, actual_date=?, status=?, auditor_name=?, auditor_email=?
             WHERE audit_id=?'
        );
        $stmt->bind_param('isssssssssi', $standardId, $title, $type, $title, $scope, $findings, $actual_date, $status, $auditor_name, $auditor_email, $id);
    } else {
        $stmt = $conn->prepare(
            'UPDATE qa_audits
             SET title=?, audit_type=?, description=?, scope=?, findings=?, actual_date=?, status=?, auditor_name=?, auditor_email=?
             WHERE audit_id=?'
        );
        $stmt->bind_param('sssssssssi', $title, $type, $title, $scope, $findings, $actual_date, $status, $auditor_name, $auditor_email, $id);
    }

    if ($stmt->execute()) {
        respond(true, 'Audit updated');
    }

    http_response_code(500);
    respond(false, 'Failed to update audit: ' . $stmt->error);
}

if ($action === 'delete') {
    $id = (int)($_POST['audit_id'] ?? 0);

    if (!$id) {
        http_response_code(400);
        respond(false, 'Audit ID is required');
    }

    $stmt = $conn->prepare('DELETE FROM qa_audits WHERE audit_id=?');
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        respond(true, 'Audit deleted');
    }

    http_response_code(500);
    respond(false, 'Failed to delete audit');
}

if ($action === 'get_detail') {
    $hasStandard = hasAuditStandardLink($conn);
    $id = (int)($_GET['audit_id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        respond(false, 'Audit ID is required');
    }

    if ($hasStandard) {
        $stmt = $conn->prepare('SELECT a.*, s.title AS standard_title FROM qa_audits a LEFT JOIN qa_standards s ON a.standard_id = s.standard_id WHERE a.audit_id = ? LIMIT 1');
    } else {
        $stmt = $conn->prepare('SELECT * FROM qa_audits WHERE audit_id = ? LIMIT 1');
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        http_response_code(404);
        respond(false, 'Audit not found');
    }

    respond(true, 'OK', ['data' => $row]);
}

if ($action === 'list') {
    $hasStandard = hasAuditStandardLink($conn);
    $status = trim($_GET['status'] ?? '');
    $auditType = trim($_GET['audit_type'] ?? '');
    $standardId = (int)($_GET['standard_id'] ?? 0);

    $where = ['1=1'];
    $params = [];
    $types = '';

    if ($status !== '') {
        $where[] = ($hasStandard ? 'a.status = ?' : 'status = ?');
        $params[] = $status;
        $types .= 's';
    }
    if ($auditType !== '') {
        $where[] = ($hasStandard ? 'a.audit_type = ?' : 'audit_type = ?');
        $params[] = $auditType;
        $types .= 's';
    }
    if ($hasStandard && $standardId > 0) {
        $where[] = 'a.standard_id = ?';
        $params[] = $standardId;
        $types .= 'i';
    }

    if ($hasStandard) {
        $sql = 'SELECT a.*, s.title AS standard_title
                FROM qa_audits a
                LEFT JOIN qa_standards s ON a.standard_id = s.standard_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY a.scheduled_date DESC, a.created_at DESC';
    } else {
        $sql = 'SELECT * FROM qa_audits WHERE ' . implode(' AND ', $where) . ' ORDER BY scheduled_date DESC, created_at DESC';
    }

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
}

http_response_code(400);
respond(false, 'Invalid action');
