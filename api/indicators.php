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
        $status = $_GET['status'] ?? '';
        $w = ['1=1']; $p=[]; $t='';
        if($status){ $w[]='status=?'; $p[]=$status; $t.='s'; }
        $stmt = $conn->prepare("SELECT * FROM qa_indicators WHERE ".implode(' AND ',$w)." ORDER BY category,name");
        if($t) $stmt->bind_param($t,...$p);
        $stmt->execute();
        $rows=[]; $res=$stmt->get_result(); while($r=$res->fetch_assoc()) $rows[]=$r;
        respond(true, 'OK', ['data'=>$rows]);
        break;

    default:
        respond(false, 'Unknown action.');
}
