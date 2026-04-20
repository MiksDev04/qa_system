<?php
// api/surveys.php – CRUD REST API for surveys + survey_questions
// ByteBandits QA Management System

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$conn   = getConnection();
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

function respond(bool $ok, string $msg, array $data = []): void {
    echo json_encode(array_merge(['status' => $ok ? 'success' : 'error', 'message' => $msg], $data));
    exit;
}

function generateToken(): string {
    return 'tok_' . bin2hex(random_bytes(16));
}

switch ($action) {
    // ─── LIST ─────────────────────────────────────────────
    case 'list':
        $status = trim($_GET['status'] ?? '');
        $audience = trim($_GET['target_audience'] ?? '');

        $where = ['1=1'];
        $params = [];
        $types = '';

        if ($status !== '') {
            $where[] = 's.status = ?';
            $params[] = $status;
            $types .= 's';
        }
        if ($audience !== '') {
            $where[] = 's.target_audience = ?';
            $params[] = $audience;
            $types .= 's';
        }

        $sql = "SELECT s.*, COUNT(sr.response_id) AS responses_count
                FROM surveys s
                LEFT JOIN survey_responses sr ON s.survey_id = sr.survey_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY s.survey_id
                ORDER BY s.created_at DESC";

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
        break;

    // ─── CREATE ───────────────────────────────────────────
    case 'create':
        $title     = trim($_POST['title'] ?? '');
        $desc      = trim($_POST['description'] ?? '');
        $audience  = in_array($_POST['target_audience']??'',['Student','Employee','Employer','Alumni','General']) ? $_POST['target_audience'] : 'General';
        $status    = in_array($_POST['status']??'',['Draft','Active','Closed']) ? $_POST['status'] : 'Draft';
        $start     = trim($_POST['start_date'] ?? '') ?: null;
        $end       = trim($_POST['end_date'] ?? '') ?: null;
        $require_name = (int)($_POST['require_name'] ?? 0);
        $require_email = (int)($_POST['require_email'] ?? 0);
        $questions = json_decode($_POST['questions'] ?? '[]', true) ?: [];

        if (!$title) respond(false, 'Survey title is required.');

        $token = generateToken();
        
        // Store respondent requirements as JSON metadata in description
        $metadata = [
            'require_name' => (bool)$require_name,
            'require_email' => (bool)$require_email,
            'original_desc' => $desc
        ];
        $desc_with_meta = !empty($desc) ? $desc : '';
        
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("INSERT INTO surveys (title,description,target_audience,status,start_date,end_date,qr_token,created_date) VALUES (?,?,?,?,?,?,?,CURDATE())");
            $stmt->bind_param('sssssss', $title, $desc_with_meta, $audience, $status, $start, $end, $token);
            $stmt->execute();
            $survey_id = $conn->insert_id;

            // Insert questions
            if (!empty($questions)) {
                $q_stmt = $conn->prepare("INSERT INTO survey_questions (survey_id,question_text,question_type,choices,is_required,sort_order) VALUES (?,?,?,?,?,?)");
                foreach ($questions as $q) {
                    $qt = in_array($q['question_type']??'',['rating','text','multiple_choice','yes_no']) ? $q['question_type'] : 'rating';
                    $choices = ($qt === 'multiple_choice' && !empty($q['choices'])) ? $q['choices'] : null;
                    $req = (int)($q['is_required'] ?? 1);
                    $ord = (int)($q['sort_order'] ?? 0);
                    $txt = trim($q['question_text'] ?? '');
                    if (!$txt) continue;
                    $q_stmt->bind_param('isssii', $survey_id, $txt, $qt, $choices, $req, $ord);
                    $q_stmt->execute();
                }
            }
            $conn->commit();
            
            // Store survey settings in session
            if (!isset($_SESSION['survey_settings'])) {
                $_SESSION['survey_settings'] = [];
            }
            $_SESSION['survey_settings'][$survey_id] = [
                'require_name' => (bool)$require_name,
                'require_email' => (bool)$require_email
            ];
            
            respond(true, 'Survey created.', ['id' => $survey_id, 'token' => $token]);
        } catch (Exception $e) {
            $conn->rollback();
            respond(false, 'Failed to create survey: ' . $e->getMessage());
        }
        break;

    // ─── UPDATE ───────────────────────────────────────────
    case 'update':
        $id        = (int)($_POST['survey_id'] ?? 0);
        $title     = trim($_POST['title'] ?? '');
        $desc      = trim($_POST['description'] ?? '');
        $audience  = in_array($_POST['target_audience']??'',['Student','Employee','Employer','Alumni','General']) ? $_POST['target_audience'] : 'General';
        $status    = in_array($_POST['status']??'',['Draft','Active','Closed']) ? $_POST['status'] : 'Draft';
        $start     = trim($_POST['start_date'] ?? '') ?: null;
        $end       = trim($_POST['end_date'] ?? '') ?: null;
        $require_name = (int)($_POST['require_name'] ?? 0);
        $require_email = (int)($_POST['require_email'] ?? 0);
        $questions = json_decode($_POST['questions'] ?? '[]', true) ?: [];

        if (!$id)    respond(false, 'Survey ID is required.');
        if (!$title) respond(false, 'Survey title is required.');

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE surveys SET title=?,description=?,target_audience=?,status=?,start_date=?,end_date=?,updated_at=NOW() WHERE survey_id=?");
            $stmt->bind_param('ssssssi', $title, $desc, $audience, $status, $start, $end, $id);
            $stmt->execute();

            // Sync questions: delete existing then re-insert
            $delQ = $conn->prepare('DELETE FROM survey_questions WHERE survey_id = ?');
            $delQ->bind_param('i', $id);
            $delQ->execute();

            if (!empty($questions)) {
                $q_stmt = $conn->prepare("INSERT INTO survey_questions (survey_id,question_text,question_type,choices,is_required,sort_order) VALUES (?,?,?,?,?,?)");
                foreach ($questions as $q) {
                    $qt = in_array($q['question_type']??'',['rating','text','multiple_choice','yes_no']) ? $q['question_type'] : 'rating';
                    $choices = ($qt === 'multiple_choice' && !empty($q['choices'])) ? $q['choices'] : null;
                    $req = (int)($q['is_required'] ?? 1);
                    $ord = (int)($q['sort_order'] ?? 0);
                    $txt = trim($q['question_text'] ?? '');
                    if (!$txt) continue;
                    $q_stmt->bind_param('isssii', $id, $txt, $qt, $choices, $req, $ord);
                    $q_stmt->execute();
                }
            }
            $conn->commit();
            
            // Store survey settings in session
            if (!isset($_SESSION['survey_settings'])) {
                $_SESSION['survey_settings'] = [];
            }
            $_SESSION['survey_settings'][$id] = [
                'require_name' => (bool)$require_name,
                'require_email' => (bool)$require_email
            ];
            
            respond(true, 'Survey updated.');
        } catch (Exception $e) {
            $conn->rollback();
            respond(false, 'Failed to update survey: ' . $e->getMessage());
        }
        break;

    // ─── DELETE ───────────────────────────────────────────
    case 'delete':
        $id = (int)($_POST['survey_id'] ?? 0);
        if (!$id) respond(false, 'Survey ID is required.');

        $conn->begin_transaction();
        try {
            // Delete answers → responses → questions → survey
            $delAnswers = $conn->prepare('DELETE sa FROM survey_answers sa JOIN survey_responses sr ON sa.response_id = sr.response_id WHERE sr.survey_id = ?');
            $delAnswers->bind_param('i', $id);
            $delAnswers->execute();

            $delResponses = $conn->prepare('DELETE FROM survey_responses WHERE survey_id = ?');
            $delResponses->bind_param('i', $id);
            $delResponses->execute();

            $delQuestions = $conn->prepare('DELETE FROM survey_questions WHERE survey_id = ?');
            $delQuestions->bind_param('i', $id);
            $delQuestions->execute();
            $stmt = $conn->prepare("DELETE FROM surveys WHERE survey_id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $conn->commit();
            respond(true, 'Survey deleted.');
        } catch (Exception $e) {
            $conn->rollback();
            respond(false, 'Failed to delete survey.');
        }
        break;

    // ─── GET QUESTIONS ────────────────────────────────────
    case 'get_questions':
        $id = (int)($_GET['survey_id'] ?? 0);
        if (!$id) respond(false, 'Survey ID is required.');
        $stmt = $conn->prepare("SELECT * FROM survey_questions WHERE survey_id=? ORDER BY sort_order,question_id");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $rows=[]; $res=$stmt->get_result(); while($r=$res->fetch_assoc()) $rows[]=$r;
        respond(true, 'OK', ['data'=>$rows]);
        break;

    // ─── ENSURE QR TOKEN ──────────────────────────────────
    case 'ensure_token':
        $id = (int)($_POST['survey_id'] ?? $_GET['survey_id'] ?? 0);
        if (!$id) respond(false, 'Survey ID is required.');

        $stmt = $conn->prepare("SELECT qr_token FROM surveys WHERE survey_id=? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) respond(false, 'Survey not found.');

        $current = trim((string)($row['qr_token'] ?? ''));
        if ($current !== '') {
            respond(true, 'QR token is ready.', ['token' => $current]);
        }

        $attempt = 0;
        while ($attempt < 5) {
            $attempt++;
            $newToken = generateToken();
            $up = $conn->prepare("UPDATE surveys SET qr_token=? WHERE survey_id=? AND (qr_token IS NULL OR qr_token='')");
            $up->bind_param('si', $newToken, $id);
            $up->execute();

            if ($up->affected_rows > 0) {
                respond(true, 'QR token generated.', ['token' => $newToken]);
            }

            $chk = $conn->prepare("SELECT qr_token FROM surveys WHERE survey_id=? LIMIT 1");
            $chk->bind_param('i', $id);
            $chk->execute();
            $existing = $chk->get_result()->fetch_assoc();
            $existingToken = trim((string)($existing['qr_token'] ?? ''));
            if ($existingToken !== '') {
                respond(true, 'QR token is ready.', ['token' => $existingToken]);
            }
        }

        respond(false, 'Unable to generate QR token. Please try again.');
        break;

    default:
        respond(false, 'Unknown action.');
}
