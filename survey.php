<?php
// survey.php – Public survey form (accessed via QR code, no login)
// ByteBandits QA Management System
require_once __DIR__ . '/config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$token = trim($_GET['token'] ?? '');
$conn  = getConnection();
$error = '';
$survey = null;
$questions = [];
$submitted = false;
$all_roles = ['Student', 'Employee', 'Employer', 'Alumni', 'General'];
$allowed_roles = [];

if (!isset($_SESSION['survey_submitted'])) {
    $_SESSION['survey_submitted'] = [];
}
if (!isset($_SESSION['survey_form_nonce'])) {
    $_SESSION['survey_form_nonce'] = [];
}
if (!isset($_SESSION['survey_settings'])) {
    $_SESSION['survey_settings'] = [];
}

if (!$token) { $error = 'Invalid survey link. No token provided.'; }
else {
    $stmt = $conn->prepare("SELECT * FROM surveys WHERE qr_token = ? AND status = 'Active'");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $survey = $stmt->get_result()->fetch_assoc();
    if (!$survey) { $error = 'This survey is not available or has been closed.'; }
    else {
        // Check date
        $now = date('Y-m-d');
        if ($survey['end_date'] && $now > $survey['end_date']) $error = 'This survey has expired.';
        if ($survey['start_date'] && $now < $survey['start_date']) $error = 'This survey has not started yet.';
    }
    if (!$error) {
        $q_stmt = $conn->prepare("SELECT * FROM survey_questions WHERE survey_id = ? ORDER BY sort_order, question_id");
        $q_stmt->bind_param('i', $survey['survey_id']);
        $q_stmt->execute();
        $qr = $q_stmt->get_result();
        while($q = $qr->fetch_assoc()) $questions[] = $q;
    }
}

if ($survey) {
    $audience = trim((string)($survey['target_audience'] ?? 'General'));
    if ($audience === 'General') {
        $allowed_roles = $all_roles;
    } else {
        $allowed_roles = in_array($audience, $all_roles, true) ? [$audience] : ['General'];
    }

    if (!empty($_SESSION['survey_submitted'][$token])) {
        $submitted = true;
    }

    if (!$submitted && empty($_SESSION['survey_form_nonce'][$token])) {
        $_SESSION['survey_form_nonce'][$token] = bin2hex(random_bytes(16));
    }
}

// Handle submission
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($requestMethod === 'POST' && $survey && !$error && !$submitted) {
    $name   = trim($_POST['respondent_name'] ?? '');
    $email  = trim($_POST['respondent_email'] ?? '');
    $role   = trim($_POST['respondent_role'] ?? ($allowed_roles[0] ?? $survey['target_audience']));
    $sess   = bin2hex(random_bytes(16));
    $form_nonce = trim($_POST['form_nonce'] ?? '');
    $session_nonce = (string)($_SESSION['survey_form_nonce'][$token] ?? '');

    if ($session_nonce === '' || !hash_equals($session_nonce, $form_nonce)) {
        $error = 'This survey response was already submitted. Please open a fresh survey link to submit again.';
    }

    if (!in_array($role, $allowed_roles, true)) {
        $error = 'Selected role is not allowed for this survey.';
    }

    // Validate required questions
    $valid = empty($error);
    if ($valid) {
        foreach($questions as $q) {
            if($q['is_required']) {
                $key = 'q_'.$q['question_id'];
                if(empty($_POST[$key])) { $valid = false; break; }
            }
        }
    }

    if ($valid) {
        $conn->begin_transaction();
        try {
            $r_stmt = $conn->prepare("INSERT INTO survey_responses (survey_id,respondent_role,respondent_name,respondent_email,session_token) VALUES (?,?,?,?,?)");
            $r_stmt->bind_param('issss', $survey['survey_id'], $role, $name, $email, $sess);
            $r_stmt->execute();
            $response_id = $conn->insert_id;

            $a_stmt = $conn->prepare("INSERT INTO survey_answers (response_id,question_id,answer_text,rating) VALUES (?,?,?,?)");
            foreach($questions as $q) {
                $key = 'q_'.$q['question_id'];
                $answer_text = null; $rating = null;
                if($q['question_type'] === 'rating') {
                    $rating = (int)($_POST[$key] ?? 0);
                } else {
                    $answer_text = trim($_POST[$key] ?? '');
                }
                $a_stmt->bind_param('iisi', $response_id, $q['question_id'], $answer_text, $rating);
                $a_stmt->execute();
            }
            $conn->commit();
            $_SESSION['survey_submitted'][$token] = true;
            unset($_SESSION['survey_form_nonce'][$token]);

            header('Location: /qa_system/survey.php?token=' . urlencode($token) . '&submitted=1');
            exit;
        } catch(Exception $e) {
            $conn->rollback();
            $error = 'Submission failed. Please try again.';
        }
    } else {
        $error = 'Please answer all required questions before submitting.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= $survey ? htmlspecialchars($survey['title']) : 'Survey' ?> | PLSP QA</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="/qa_system/assets/css/main.css?v=<?= filemtime(__DIR__ . '/assets/css/main.css') ?>">
    <style>
        body { padding: 0; }
        .survey-public-wrapper { padding: 40px 16px 60px; }
    </style>
</head>
<body>
<div class="survey-public-wrapper">

    <?php if ($submitted): ?>
    <!-- Success state -->
    <div class="survey-public-card text-center" style="max-width:500px;margin:0 auto">
        <div style="font-size:3rem;color:var(--success);margin-bottom:16px"><i class="bi bi-check-circle-fill"></i></div>
        <h2 style="font-size:1.5rem;font-weight:700;margin-bottom:8px">Thank you!</h2>
        <p style="color:var(--text-secondary)">Your response has been submitted successfully. Your feedback helps us improve.</p>
        <div class="section-divider"></div>
        <div style="font-size:0.8rem;color:var(--text-muted)">
            <i class="bi bi-shield-check me-1"></i>Pamantasan ng Lungsod ng San Pablo · Quality Assurance Office
        </div>
    </div>

    <?php elseif ($error && !$survey): ?>
    <!-- Error state -->
    <div class="survey-public-card text-center" style="max-width:500px;margin:0 auto">
        <div style="font-size:3rem;color:var(--danger);margin-bottom:16px"><i class="bi bi-exclamation-circle"></i></div>
        <h2 style="font-size:1.3rem;font-weight:700">Survey Unavailable</h2>
        <p style="color:var(--text-secondary)"><?= htmlspecialchars($error) ?></p>
    </div>

    <?php else: ?>
    <!-- Survey form -->
    <div class="survey-public-card">
        <!-- Brand -->
        <div class="survey-brand-bar">
            <div class="brand-icon" style="width:36px;height:36px;font-size:0.9rem"><i class="bi bi-shield-check"></i></div>
            <div>
                <div style="font-size:0.78rem;font-weight:600;color:var(--text-muted)">PLSP · Quality Assurance Office</div>
            </div>
        </div>

        <h1 style="font-size:1.4rem;font-weight:700;margin-bottom:6px"><?= htmlspecialchars($survey['title']) ?></h1>
        <?php if($survey['description']): ?>
        <p style="color:var(--text-secondary);font-size:0.9rem;margin-bottom:20px"><?= htmlspecialchars($survey['description']) ?></p>
        <?php endif; ?>

        <?php if($error): ?>
        <div style="background:var(--danger-bg);border:1px solid rgba(239,68,68,0.3);color:var(--danger);padding:10px 14px;border-radius:var(--radius-sm);margin-bottom:16px;font-size:0.875rem">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="surveyForm">
    <input type="hidden" name="form_nonce" value="<?= htmlspecialchars((string)($_SESSION['survey_form_nonce'][$token] ?? '')) ?>">

        <!-- Respondent info -->
        <div class="qa-card mb-4" style="padding:18px">
            <div class="qa-card-title mb-3">About You<?php 
                $require_name = isset($_SESSION['survey_settings'][$survey['survey_id']]['require_name']) ? $_SESSION['survey_settings'][$survey['survey_id']]['require_name'] : false;
                $require_email = isset($_SESSION['survey_settings'][$survey['survey_id']]['require_email']) ? $_SESSION['survey_settings'][$survey['survey_id']]['require_email'] : false;
            ?></div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="qa-form-label">Your Name<?= $require_name ? ' <span style="color:var(--danger)">*</span>' : ' (optional)' ?></label>
                    <input type="text" name="respondent_name" class="qa-form-control" placeholder="<?= $require_name ? 'Required' : 'Anonymous' ?>" value="<?= htmlspecialchars($_POST['respondent_name']??'') ?>" <?= $require_name ? 'required' : '' ?>>
                </div>
                <div class="col-md-6">
                    <label class="qa-form-label">Email<?= $require_email ? ' <span style="color:var(--danger)">*</span>' : ' (optional)' ?></label>
                    <input type="email" name="respondent_email" class="qa-form-control" placeholder="<?= $require_email ? 'Required' : 'your@email.com' ?>" value="<?= htmlspecialchars($_POST['respondent_email']??'') ?>" <?= $require_email ? 'required' : '' ?>>
                </div>
                <div class="col-md-6">
                    <label class="qa-form-label">I am a…</label>
                    <?php if (count($allowed_roles) === 1): ?>
                    <input type="text" class="qa-form-control" value="<?= htmlspecialchars($allowed_roles[0]) ?>" readonly>
                    <input type="hidden" name="respondent_role" value="<?= htmlspecialchars($allowed_roles[0]) ?>">
                    <?php else: ?>
                    <select name="respondent_role" class="qa-form-control">
                        <?php foreach($allowed_roles as $r): ?>
                        <?php $selectedRole = $_POST['respondent_role'] ?? $allowed_roles[0]; ?>
                        <option value="<?= htmlspecialchars($r) ?>" <?= $selectedRole===$r?'selected':'' ?>><?= htmlspecialchars($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Questions -->
        <?php foreach($questions as $i => $q): ?>
        <?php $key = 'q_'.$q['question_id']; $posted = $_POST[$key] ?? ''; ?>
        <div class="qa-card mb-3" style="padding:18px">
            <div class="mb-2">
                <span style="font-weight:600"><?= ($i+1) ?>. <?= htmlspecialchars($q['question_text']) ?></span>
                <?php if($q['is_required']): ?>
                <span style="color:var(--danger);margin-left:4px">*</span>
                <?php endif; ?>
            </div>

            <?php if($q['question_type']==='rating'): ?>
            <div class="rating-stars" id="stars_<?= $q['question_id'] ?>">
                <input type="hidden" name="<?= $key ?>" id="ratingVal_<?= $q['question_id'] ?>" value="<?= (int)$posted ?>">
                <?php for($s=1;$s<=5;$s++): ?>
                <button type="button" onclick="setRating(<?= $q['question_id'] ?>,<?= $s ?>)" class="<?= (int)$posted>=$s?'active':'' ?>">★</button>
                <?php endfor; ?>
            </div>
            <div class="text-muted-qa mt-1" style="font-size:0.75rem">1 = Poor · 5 = Excellent</div>

            <?php elseif($q['question_type']==='text'): ?>
            <textarea name="<?= $key ?>" class="qa-form-control" rows="3" placeholder="Your answer…"><?= htmlspecialchars($posted) ?></textarea>

            <?php elseif($q['question_type']==='yes_no'): ?>
            <div class="d-flex gap-3 mt-1">
                <?php foreach(['Yes','No'] as $opt): ?>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:500">
                    <input type="radio" name="<?= $key ?>" value="<?= $opt ?>" <?= $posted===$opt?'checked':'' ?> style="accent-color:var(--accent)">
                    <?= $opt ?>
                </label>
                <?php endforeach; ?>
            </div>

            <?php elseif($q['question_type']==='multiple_choice'): ?>
            <?php $choices = $q['choices'] ? json_decode($q['choices'],true) : []; ?>
            <div class="d-flex flex-column gap-2 mt-1">
                <?php foreach($choices as $opt): ?>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:500">
                    <input type="radio" name="<?= $key ?>" value="<?= htmlspecialchars($opt) ?>" <?= $posted===htmlspecialchars($opt)?'checked':'' ?> style="accent-color:var(--accent)">
                    <?= htmlspecialchars($opt) ?>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="section-divider"></div>
        <div class="d-flex justify-content-end">
            <button type="submit" class="btn-qa btn-qa-primary" style="padding:12px 32px;font-size:1rem">
                <i class="bi bi-send"></i> Submit Response
            </button>
        </div>
        </form>

        <div style="text-align:center;margin-top:24px;font-size:0.75rem;color:var(--text-muted)">
            <i class="bi bi-lock me-1"></i>Your response is confidential and used solely for quality improvement.
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function setRating(qid, val) {
    document.getElementById('ratingVal_'+qid).value = val;
    const btns = document.querySelectorAll('#stars_'+qid+' button');
    btns.forEach((b,i) => { b.classList.toggle('active', i < val); });
}

const surveyForm = document.getElementById('surveyForm');
if (surveyForm) {
    surveyForm.addEventListener('submit', function () {
        const submitButton = surveyForm.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="bi bi-hourglass-split"></i> Submitting...';
        }
    });
}
</script>
</body>
</html>
