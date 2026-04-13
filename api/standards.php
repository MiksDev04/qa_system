<?php
// api/standards.php – Standards & Policies API
// ByteBandits QA Management System
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$conn = getConnection();
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

function respond(bool $ok, string $msg, array $data = []): void {
    echo json_encode(array_merge(['status' => $ok ? 'success' : 'error', 'message' => $msg], $data));
    exit;
}

function normalizeType(string $type): string {
    return $type === 'policies' ? 'policies' : 'standards';
}

$type = normalizeType(trim($_POST['type'] ?? $_GET['type'] ?? 'standards'));

if ($action === 'create') {
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');
    $eff_date = trim($_POST['effective_date'] ?? '');
    $rev_date = trim($_POST['review_date'] ?? '');

    if (!$title || !$body || !$category) {
        http_response_code(400);
        respond(false, 'Missing required fields');
    }

    if ($type === 'policies') {
        $stmt = $conn->prepare(
            'INSERT INTO qa_policies (title, description, category, owner, status, effective_date)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ssssss', $title, $desc, $category, $body, $status, $eff_date);
    } else {
        $stmt = $conn->prepare(
            'INSERT INTO qa_standards (title, description, category, compliance_body, status, effective_date, review_date)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssssss', $title, $desc, $category, $body, $status, $eff_date, $rev_date);
    }

    if ($stmt->execute()) {
        respond(true, 'Record created');
    }

    http_response_code(500);
    respond(false, $stmt->error);
}

if ($action === 'update') {
    $id = (int)($_POST['rec_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');
    $eff_date = trim($_POST['effective_date'] ?? '');
    $rev_date = trim($_POST['review_date'] ?? '');

    if (!$id || !$title) {
        http_response_code(400);
        respond(false, 'Invalid input');
    }

    if ($type === 'policies') {
        $stmt = $conn->prepare(
            'UPDATE qa_policies
             SET title=?, description=?, category=?, owner=?, status=?, effective_date=?
             WHERE policy_id=?'
        );
        $stmt->bind_param('ssssssi', $title, $desc, $category, $body, $status, $eff_date, $id);
    } else {
        $stmt = $conn->prepare(
            'UPDATE qa_standards
             SET title=?, description=?, category=?, compliance_body=?, status=?, effective_date=?, review_date=?
             WHERE standard_id=?'
        );
        $stmt->bind_param('sssssssi', $title, $desc, $category, $body, $status, $eff_date, $rev_date, $id);
    }

    if ($stmt->execute()) {
        respond(true, 'Record updated');
    }

    http_response_code(500);
    respond(false, $stmt->error);
}

if ($action === 'delete') {
    $id = (int)($_POST['rec_id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        respond(false, 'Record ID is required');
    }

    if ($type === 'policies') {
        $stmt = $conn->prepare('DELETE FROM qa_policies WHERE policy_id=?');
    } else {
        $stmt = $conn->prepare('DELETE FROM qa_standards WHERE standard_id=?');
    }

    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        respond(true, 'Record deleted');
    }

    http_response_code(500);
    respond(false, 'Failed to delete record');
}

if ($action === 'list') {
    $status = trim($_GET['status'] ?? '');

    if ($type === 'policies') {
        $sql = 'SELECT * FROM qa_policies';
    } else {
        $sql = 'SELECT * FROM qa_standards';
    }

    $params = [];
    $types = '';
    if ($status !== '') {
        $sql .= ' WHERE status = ?';
        $params[] = $status;
        $types .= 's';
    }

    if ($type === 'policies') {
        $sql .= ' ORDER BY effective_date DESC, created_at DESC';
    } else {
        $sql .= ' ORDER BY review_date ASC, created_at DESC';
    }

    $stmt = $conn->prepare($sql);
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $rows = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    respond(true, 'OK', ['data' => $rows]);
}

http_response_code(400);
respond(false, 'Invalid action');
