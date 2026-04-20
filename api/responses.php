<?php
// api/responses.php – REST API for survey_responses
// ByteBandits QA Management System
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$conn   = getConnection();
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

function respond(bool $ok, string $msg, array $data = []): void {
    echo json_encode(array_merge(['status' => $ok ? 'success' : 'error', 'message' => $msg], $data));
    exit;
}

switch ($action) {
    // ─── GET RESPONSE DETAIL ──────────────────────────────
    case 'get_detail':
        $id = (int)($_GET['response_id'] ?? 0);
        if (!$id) respond(false, 'Response ID required.');

        $stmt = $conn->prepare("SELECT * FROM survey_responses WHERE response_id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $response = $stmt->get_result()->fetch_assoc();
        if (!$response) respond(false, 'Response not found.');

        // Get answers with question text
        $a_stmt = $conn->prepare("
            SELECT sa.*, sq.question_text, sq.question_type
            FROM survey_answers sa
            JOIN survey_questions sq ON sa.question_id=sq.question_id
            WHERE sa.response_id=?
            ORDER BY sq.sort_order, sq.question_id
        ");
        $a_stmt->bind_param('i', $id);
        $a_stmt->execute();
        $answers = []; $r = $a_stmt->get_result();
        while($a=$r->fetch_assoc()) $answers[] = $a;

        $response['answers'] = $answers;
        respond(true, 'OK', ['data' => $response]);
        break;

    // ─── STATS PER SURVEY ─────────────────────────────────
    case 'get_stats':
        $survey_id = (int)($_GET['survey_id'] ?? 0);
        if (!$survey_id) respond(false, 'Survey ID required.');

        // Rating averages per question (only for rating-type questions with valid ratings)
        $stmt = $conn->prepare("
            SELECT sq.question_id, sq.question_text, sq.question_type,
                   AVG(sa.rating) as avg_rating, COUNT(sa.answer_id) as total
            FROM survey_questions sq
            LEFT JOIN survey_answers sa ON sq.question_id=sa.question_id 
                AND sa.rating IS NOT NULL 
                AND sa.rating BETWEEN 1 AND 5
            WHERE sq.survey_id=? AND sq.question_type='rating'
            GROUP BY sq.question_id
            ORDER BY sq.sort_order
        ");
        $stmt->bind_param('i', $survey_id);
        $stmt->execute();
        $rows=[]; $res=$stmt->get_result(); while($r=$res->fetch_assoc()) $rows[]=$r;
        respond(true, 'OK', ['data'=>$rows]);
        break;

    // ─── ROLE SEGMENTATION STATS ─────────────────────────
    case 'get_role_breakdown':
        $survey_id = (int)($_GET['survey_id'] ?? 0);

        $where = ['1=1'];
        $params = [];
        $types = '';

        if ($survey_id > 0) {
            $where[] = 'sr.survey_id = ?';
            $params[] = $survey_id;
            $types .= 'i';
        }

        $sql = "SELECT
                    COALESCE(NULLIF(sr.respondent_role, ''), 'Unspecified') AS respondent_role,
                    COUNT(DISTINCT sr.response_id) AS response_count,
                    ROUND(AVG(response_avg), 1) AS avg_rating
                FROM survey_responses sr
                LEFT JOIN (
                    SELECT sa.response_id, AVG(sa.rating) as response_avg
                    FROM survey_answers sa
                    JOIN survey_questions sq ON sa.question_id = sq.question_id
                    WHERE sq.question_type = 'rating'
                      AND sa.rating IS NOT NULL
                      AND sa.rating BETWEEN 1 AND 5
                    GROUP BY sa.response_id
                ) rating_avgs ON sr.response_id = rating_avgs.response_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY COALESCE(NULLIF(sr.respondent_role, ''), 'Unspecified')
                ORDER BY response_count DESC, respondent_role ASC";

        $stmt = $conn->prepare($sql);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();

        $rows = [];
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
        respond(true, 'OK', ['data' => $rows]);
        break;

    default:
        respond(false, 'Unknown action.');
}
