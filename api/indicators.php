<?php
// api/indicators.php – CRUD REST API for qa_indicators
// ByteBandits QA Management System
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$conn   = getConnection();
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

function respond(bool $ok, string $msg, array $data = []): void {
    echo json_encode(array_merge(['status' => $ok ? 'success' : 'error', 'message' => $msg], $data));
    exit;
}

function sanitize(string $v): string { return trim(htmlspecialchars_decode(strip_tags($v))); }

switch ($action) {
    // ─── CREATE ───────────────────────────────────────────
    case 'create':
        $name        = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $target      = (float)($_POST['target_value'] ?? 0);
        $unit        = sanitize($_POST['unit'] ?? '%');
        $category    = sanitize($_POST['category'] ?? 'General');
        $status      = in_array($_POST['status'] ?? '', ['Active','Inactive']) ? $_POST['status'] : 'Active';

        if (!$name)   respond(false, 'Indicator name is required.');
        if ($target < 0) respond(false, 'Target value must be non-negative.');

        $stmt = $conn->prepare("INSERT INTO qa_indicators (name,description,target_value,unit,category,status) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('ssdsss', $name, $description, $target, $unit, $category, $status);
        $stmt->execute() ? respond(true, 'Indicator created.', ['id' => $conn->insert_id]) : respond(false, 'Failed to create indicator.');
        break;

    // ─── UPDATE ───────────────────────────────────────────
    case 'update':
        $id          = (int)($_POST['indicator_id'] ?? 0);
        $name        = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $target      = (float)($_POST['target_value'] ?? 0);
        $unit        = sanitize($_POST['unit'] ?? '%');
        $category    = sanitize($_POST['category'] ?? 'General');
        $status      = in_array($_POST['status'] ?? '', ['Active','Inactive']) ? $_POST['status'] : 'Active';

        if (!$id)   respond(false, 'Indicator ID is required.');
        if (!$name) respond(false, 'Indicator name is required.');

        $stmt = $conn->prepare("UPDATE qa_indicators SET name=?,description=?,target_value=?,unit=?,category=?,status=?,updated_at=NOW() WHERE indicator_id=?");
        $stmt->bind_param('ssdsssi', $name, $description, $target, $unit, $category, $status, $id);
        $stmt->execute() ? respond(true, 'Indicator updated.') : respond(false, 'Failed to update indicator.');
        break;

    // ─── DELETE ───────────────────────────────────────────
    case 'delete':
        $id = (int)($_POST['indicator_id'] ?? 0);
        if (!$id) respond(false, 'Indicator ID is required.');

        // Check for linked records
        $check = $conn->prepare("SELECT COUNT(*) as c FROM qa_records WHERE indicator_id=?");
        $check->bind_param('i', $id);
        $check->execute();
        $count = $check->get_result()->fetch_assoc()['c'];
        if ($count > 0) respond(false, "Cannot delete – this indicator has $count record(s). Delete records first.");

        $stmt = $conn->prepare("DELETE FROM qa_indicators WHERE indicator_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute() ? respond(true, 'Indicator deleted.') : respond(false, 'Failed to delete indicator.');
        break;

    // ─── LIST (GET) ───────────────────────────────────────
    case 'list':
        $page = max(1, (int)($_GET['page'] ?? 1));
        $search = trim($_GET['search'] ?? '');
        $category = trim($_GET['category'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $per_page = 10;

        // Build WHERE clause
        $where = ['1=1'];
        $params = [];
        $types = '';

        if ($search !== '') {
            $where[] = '(name LIKE ? OR description LIKE ?)';
            $s = "%$search%";
            $params[] = $s;
            $params[] = $s;
            $types .= 'ss';
        }
        if ($category !== '') {
            $where[] = 'category = ?';
            $params[] = $category;
            $types .= 's';
        }
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
            $types .= 's';
        }

        $whereSQL = implode(' AND ', $where);

        // Count total
        $count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM qa_indicators WHERE $whereSQL");
        if ($types) $count_stmt->bind_param($types, ...$params);
        $count_stmt->execute();
        $total = $count_stmt->get_result()->fetch_assoc()['c'];
        $total_pages = max(1, ceil($total / $per_page));
        $offset = ($page - 1) * $per_page;

        // Fetch data
        $stmt = $conn->prepare("SELECT * FROM qa_indicators WHERE $whereSQL ORDER BY category, name LIMIT ? OFFSET ?");
        $all_params = array_merge($params, [$per_page, $offset]);
        $all_types = $types . 'ii';
        $stmt->bind_param($all_types, ...$all_params);
        $stmt->execute();
        $rows = [];
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $showing_from = min($offset + 1, $total);
        $showing_to = min($offset + $per_page, $total);

        respond(true, 'OK', [
            'data' => $rows,
            'total' => $total,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'per_page' => $per_page,
            'offset' => $offset,
            'showing_from' => $showing_from,
            'showing_to' => $showing_to
        ]);
        break;

    default:
        respond(false, 'Unknown action.');
}
