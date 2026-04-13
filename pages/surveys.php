<?php
// pages/surveys.php – Survey Management (create with questions inline)
// ByteBandits QA Management System
require_once __DIR__ . '/../config/database.php';
$page_title = 'Manage Surveys';

$conn = getConnection();
$per_page = 8;
$page = max(1,(int)($_GET['page'] ?? 1));
$status_f = trim($_GET['status'] ?? '');
$audience_f = trim($_GET['audience'] ?? '');

$where = ['1=1']; $params=[]; $types='';
if($status_f)   { $where[] = 'status = ?';           $params[] = $status_f;   $types.='s'; }
if($audience_f) { $where[] = 'target_audience = ?';  $params[] = $audience_f; $types.='s'; }
$whereSQL = implode(' AND ',$where);

$count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM surveys WHERE $whereSQL");
if($types) $count_stmt->bind_param($types,...$params);
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['c'];
$total_pages = max(1,ceil($total/$per_page));
$offset = ($page-1)*$per_page;

$stmt = $conn->prepare("
    SELECT s.*, 
        (SELECT COUNT(*) FROM survey_questions q WHERE q.survey_id=s.survey_id) as q_count,
        (SELECT COUNT(*) FROM survey_responses r WHERE r.survey_id=s.survey_id) as r_count
    FROM surveys s
    WHERE $whereSQL
    ORDER BY s.created_at DESC
    LIMIT ? OFFSET ?
");
$all = array_merge($params,[$per_page,$offset]);
$stmt->bind_param($types.'ii',...$all);
$stmt->execute();
$surveys = $stmt->get_result();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-clipboard-data me-2 text-accent"></i>Manage Surveys</h1>
        <p>Create surveys with questionnaires and monitor response activity</p>
    </div>
    <button class="btn-qa btn-qa-primary" data-bs-toggle="modal" data-bs-target="#surveyModal" onclick="openAddSurvey()">
        <i class="bi bi-plus-lg"></i> New Survey
    </button>
</div>

<!-- Filters -->
<div class="filter-bar">
    <div style="min-width:140px">
        <label class="qa-form-label">Status</label>
        <select id="f-status" class="qa-form-control">
            <option value="">All</option>
            <option value="Draft"  <?= $status_f==='Draft' ?'selected':'' ?>>Draft</option>
            <option value="Active" <?= $status_f==='Active'?'selected':'' ?>>Active</option>
            <option value="Closed" <?= $status_f==='Closed'?'selected':'' ?>>Closed</option>
        </select>
    </div>
    <div style="min-width:160px">
        <label class="qa-form-label">Target Audience</label>
        <select id="f-audience" class="qa-form-control">
            <option value="">All Audiences</option>
            <option value="Student"  <?= $audience_f==='Student'  ?'selected':'' ?>>Student</option>
            <option value="Employee" <?= $audience_f==='Employee' ?'selected':'' ?>>Employee</option>
            <option value="Employer" <?= $audience_f==='Employer' ?'selected':'' ?>>Employer</option>
            <option value="Alumni"   <?= $audience_f==='Alumni'   ?'selected':'' ?>>Alumni</option>
            <option value="General"  <?= $audience_f==='General'  ?'selected':'' ?>>General</option>
        </select>
    </div>
    <div style="display:flex;align-items:flex-end;gap:8px">
        <button class="btn-qa btn-qa-primary" onclick="applyFilters()"><i class="bi bi-funnel"></i> Filter</button>
        <a href="surveys.php" class="btn-qa btn-qa-secondary"><i class="bi bi-x-circle"></i> Clear</a>
    </div>
</div>

<!-- Survey Cards -->
<div class="row g-3 mb-3">
<?php if($surveys->num_rows===0): ?>
<div class="col-12"><div class="qa-card"><div class="empty-state"><i class="bi bi-clipboard-x"></i><p>No surveys found</p></div></div></div>
<?php else: ?>
<?php while($s=$surveys->fetch_assoc()): ?>
<div class="col-md-6 col-xl-4">
    <div class="qa-card h-100" style="padding:20px">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="badge-status <?=
                $s['status']==='Active' ? 'badge-active' :
                ($s['status']==='Closed'? 'badge-closed' : 'badge-draft')
            ?>"><?= $s['status'] ?></span>
            <span class="badge-status badge-pending"><?= htmlspecialchars($s['target_audience']) ?></span>
        </div>
        <div class="fw-600 mb-1" style="font-size:0.95rem"><?= htmlspecialchars($s['title']) ?></div>
        <div class="text-muted-qa mb-3" style="font-size:0.8rem">
            <?= htmlspecialchars(substr($s['description']??'',0,90)) ?>
        </div>
        <div class="d-flex gap-3 mb-3" style="font-size:0.8rem">
            <span><i class="bi bi-question-circle me-1 text-accent"></i><?= $s['q_count'] ?> questions</span>
            <span><i class="bi bi-chat-dots me-1" style="color:var(--success)"></i><?= $s['r_count'] ?> responses</span>
        </div>
        <?php if($s['start_date']): ?>
        <div class="text-muted-qa" style="font-size:0.76rem;margin-bottom:12px">
            <i class="bi bi-calendar3 me-1"></i><?= $s['start_date'] ?> – <?= $s['end_date'] ?? '—' ?>
        </div>
        <?php endif; ?>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn-qa btn-qa-secondary btn-qa-sm" onclick="viewQuestions(<?= $s['survey_id'] ?>, <?= htmlspecialchars(json_encode($s['title']),ENT_QUOTES) ?>)">
                <i class="bi bi-list-ul"></i> Questions
            </button>
            <button class="btn-qa btn-qa-secondary btn-qa-sm" onclick='editSurvey(<?= json_encode($s) ?>)'>
                <i class="bi bi-pencil"></i>
            </button>
            <button class="btn-qa btn-qa-success btn-qa-sm" onclick="showQR(<?= $s['survey_id'] ?>, '<?= htmlspecialchars($s['qr_token']) ?>')">
                <i class="bi bi-qr-code"></i> QR
            </button>
            <button class="btn-qa btn-qa-danger btn-qa-sm" onclick="deleteSurvey(<?= $s['survey_id'] ?>)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    </div>
</div>
<?php endwhile; ?>
<?php endif; ?>
</div>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span class="text-muted-qa">Showing <?= min($offset+1,$total) ?>–<?= min($offset+$per_page,$total) ?> of <?= $total ?> surveys</span>
    <div id="paginationContainer" class="qa-pagination"></div>
</div>

<!-- ─── Create/Edit Survey Modal ─────────────────────── -->
<div class="modal fade" id="surveyModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="surveyModalTitle">New Survey</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="sv_id">
        <div class="row g-3 mb-4">
            <div class="col-12">
                <label class="qa-form-label">Survey Title *</label>
                <input type="text" id="sv_title" class="qa-form-control" placeholder="e.g. Student Experience Survey AY 2025-2026" maxlength="200">
            </div>
            <div class="col-12">
                <label class="qa-form-label">Description</label>
                <textarea id="sv_desc" class="qa-form-control" rows="2" placeholder="Briefly describe the purpose of this survey…"></textarea>
            </div>
            <div class="col-md-3">
                <label class="qa-form-label">Target Audience</label>
                <select id="sv_audience" class="qa-form-control">
                    <option>General</option><option>Student</option><option>Employee</option>
                    <option>Employer</option><option>Alumni</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="qa-form-label">Status</label>
                <select id="sv_status" class="qa-form-control">
                    <option>Draft</option><option>Active</option><option>Closed</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="qa-form-label">Start Date</label>
                <input type="date" id="sv_start" class="qa-form-control">
            </div>
            <div class="col-md-3">
                <label class="qa-form-label">End Date</label>
                <input type="date" id="sv_end" class="qa-form-control">
            </div>
        </div>

        <div style="border-top:1px solid var(--border);padding-top:20px;margin-top:20px">
            <!-- Questions builder -->
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <div style="font-weight:600;font-size:1rem;margin-bottom:4px">Questionnaires</div>
                    <div class="text-muted-qa" style="font-size:0.85rem">Add questions for your survey below</div>
                </div>
                <button class="btn-qa btn-qa-primary btn-qa-sm" onclick="addQuestion()" style="white-space:nowrap">
                    <i class="bi bi-plus-circle"></i> Add Question
                </button>
            </div>

            <div id="questionsList" style="max-height:400px;overflow-y:auto;padding-right:8px"></div>
            <div id="noQuestionsMsg" class="empty-state" style="padding:40px;border:2px dashed var(--accent);border-radius:var(--radius);text-align:center">
                <i class="bi bi-question-circle" style="font-size:2rem;color:var(--accent);opacity:0.3"></i>
                <p style="margin-top:12px;color:var(--accent);opacity:0.5">No questions added yet</p>
                <p style="font-size:0.8rem;color:var(--accent);opacity:0.4">Click "Add Question" to create your first question</p>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-qa btn-qa-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-qa btn-qa-primary" onclick="saveSurvey()"><i class="bi bi-save"></i> Save Survey</button>
      </div>
    </div>
  </div>
</div>

<!-- Questions viewer modal -->
<div class="modal fade" id="questionsViewModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="qvTitle">Questions</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="qvBody">Loading…</div>
    </div>
  </div>
</div>

<!-- QR Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content text-center">
      <div class="modal-header"><h5 class="modal-title">Survey QR Code</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="text-muted-qa mb-3" id="qrModalDesc"></div>
        <div class="qr-wrapper mx-auto mb-3" style="max-width:200px" id="qrContainer"></div>
        <div class="text-muted-qa small-mono" id="qrUrl" style="word-break:break-all;font-size:0.72rem"></div>
      </div>
      <div class="modal-footer justify-content-center">
                <button id="btnExportQR" class="btn-qa btn-qa-primary btn-qa-sm" onclick="exportQRPng()" disabled><i class="bi bi-download"></i> Export PNG</button>
      </div>
    </div>
  </div>
</div>

<?php
$extra_js = '<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
$(function(){ buildPagination("paginationContainer",'.$page.','.$total_pages.',"goPage"); });

function goPage(p){ const url=new URL(window.location); url.searchParams.set("page",p); window.location=url; }
function applyFilters(){ const url=new URL(window.location); url.searchParams.set("status",$("#f-status").val()); url.searchParams.set("audience",$("#f-audience").val()); url.searchParams.set("page",1); window.location=url; }

let questionCounter = 0;
let qrExportPngUrl = "";
let qrExportFileName = "survey-qr.png";

function openAddSurvey(){
    $("#surveyModalTitle").text("New Survey");
    $("#sv_id,#sv_title,#sv_desc,#sv_start,#sv_end").val("");
    $("#sv_audience").val("General");
    $("#sv_status").val("Draft");
    $("#questionsList").empty();
    $("#noQuestionsMsg").show();
    questionCounter = 0;
}

function editSurvey(data){
    $("#surveyModalTitle").text("Edit Survey");
    $("#sv_id").val(data.survey_id);
    $("#sv_title").val(data.title);
    $("#sv_desc").val(data.description);
    $("#sv_audience").val(data.target_audience);
    $("#sv_status").val(data.status);
    $("#sv_start").val(data.start_date);
    $("#sv_end").val(data.end_date);
    // Load existing questions
    $("#questionsList").empty();
    $("#noQuestionsMsg").show();
    questionCounter = 0;
    $.get("/qa_system/api/surveys.php", {action:"get_questions", survey_id: data.survey_id}, function(res){
        if(res.status==="success" && res.data.length){
            res.data.forEach(function(q){ addQuestion(q); });
        }
    });
    new bootstrap.Modal(document.getElementById("surveyModal")).show();
}

function addQuestion(data){
    const idx = ++questionCounter;
    const id = "q_"+idx;
    $("#noQuestionsMsg").hide();

    const typeOpts = ["rating","text","multiple_choice","yes_no"].map(t =>
        `<option value="${t}" ${data&&data.question_type===t?"selected":""}>${t.replace(/_/g," ")}</option>`
    ).join("");

    const html = `
    <div class="qa-card mb-3" id="${id}" style="padding:16px;border-left:4px solid var(--accent)">
        <input type="hidden" class="q-id" value="${data?data.question_id:""}">
        <div class="row g-2 align-items-start">
            <div class="col-md-7">
                <label class="qa-form-label" style="font-size:0.85rem">Question Text *</label>
                <input type="text" class="qa-form-control q-text" value="${data?data.question_text.replace(/"/g,"&quot;"):""}" placeholder="Enter your question…">
            </div>
            <div class="col-md-2">
                <label class="qa-form-label" style="font-size:0.85rem">Type</label>
                <select class="qa-form-control q-type" onchange="toggleChoices(\'${id}\',this.value)">
                    ${typeOpts}
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="d-flex gap-2 w-100">
                    <label style="display:flex;align-items:center;gap:6px;font-size:0.75rem;cursor:pointer;white-space:nowrap">
                        <input type="checkbox" class="q-required" ${!data||data.is_required?"checked":""} style="cursor:pointer"> Required
                    </label>
                    <button class="btn-qa btn-qa-danger btn-qa-icon btn-qa-sm ms-auto" onclick="removeQuestion(\'${id}\')" title="Remove question">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
            <div class="col-12 q-choices" style="display:${data&&data.question_type==="multiple_choice"?"block":"none"};padding:10px;background:var(--bg-base);border-radius:var(--radius-sm)">
                <label class="qa-form-label" style="font-size:0.85rem">Choices (comma separated)</label>
                <input type="text" class="qa-form-control q-choices-input" value="${data&&data.choices?JSON.parse(data.choices).join(", "):""}" placeholder="Option 1, Option 2, Option 3">
                <div class="text-muted-qa" style="font-size:0.7rem;margin-top:6px">Separate each choice with a comma</div>
            </div>
        </div>
    </div>`;
    $("#questionsList").append(html);
}

function toggleChoices(id, type){
    $(`#${id} .q-choices`).toggle(type === "multiple_choice");
}

function removeQuestion(id){
    $(`#${id}`).remove();
    if($("#questionsList .qa-card").length === 0) $("#noQuestionsMsg").show();
}

function saveSurvey(){
    const title = $("#sv_title").val().trim();
    if(!title){ showToast("Survey title is required.","error"); return; }

    const questions = [];
    let valid = true;
    $("#questionsList .qa-card").each(function(i){
        const text = $(this).find(".q-text").val().trim();
        if(!text){ showToast("All questions must have text.","error"); valid=false; return false; }
        const type = $(this).find(".q-type").val();
        let choices = null;
        if(type === "multiple_choice"){
            const raw = $(this).find(".q-choices-input").val().trim();
            choices = raw ? JSON.stringify(raw.split(",").map(s=>s.trim()).filter(Boolean)) : null;
        }
        questions.push({
            question_id: $(this).find(".q-id").val(),
            question_text: text,
            question_type: type,
            choices: choices,
            is_required: $(this).find(".q-required").is(":checked") ? 1 : 0,
            sort_order: i+1
        });
    });
    if(!valid) return;

    const payload = {
        action: $("#sv_id").val() ? "update" : "create",
        survey_id: $("#sv_id").val(),
        title,
        description: $("#sv_desc").val(),
        target_audience: $("#sv_audience").val(),
        status: $("#sv_status").val(),
        start_date: $("#sv_start").val(),
        end_date: $("#sv_end").val(),
        questions: JSON.stringify(questions)
    };

    qaAjax("/qa_system/api/surveys.php", payload, () => location.reload());
}

function viewQuestions(surveyId, title){
    $("#qvTitle").text(title);
    $("#qvBody").html(`<div class="text-center py-3"><div class="spinner-border spinner-border-sm" style="color:var(--accent)"></div></div>`);
    new bootstrap.Modal(document.getElementById("questionsViewModal")).show();
    $.get("/qa_system/api/surveys.php", {action:"get_questions", survey_id:surveyId}, function(res){
        if(!res.data || !res.data.length){ $("#qvBody").html(`<p class="text-muted-qa text-center">No questions.</p>`); return; }
        let html = "";
        res.data.forEach(function(q,i){
            html += `<div class="mb-3 p-3" style="border:1px solid var(--border);border-radius:var(--radius-sm)">
                <div class="d-flex justify-content-between">
                    <span class="fw-600">${i+1}. ${q.question_text}</span>
                    <span class="badge-status badge-pending small-mono">${q.question_type}</span>
                </div>
                ${q.choices ? `<div class="text-muted-qa mt-1" style="font-size:0.8rem">Choices: ${JSON.parse(q.choices).join(" · ")}</div>` : ""}
                ${q.is_required ? `<span class="badge-status badge-closed mt-1" style="font-size:0.7rem">Required</span>` : ""}
            </div>`;
        });
        $("#qvBody").html(html);
    });
}

function showQR(surveyId, token){
    const modal = new bootstrap.Modal(document.getElementById("qrModal"));
    $("#qrModalDesc").text("Share this QR code so respondents can access the survey");
    $("#qrContainer").html(`<div class="text-muted-qa" style="font-size:0.8rem">Generating QR code...</div>`);
    $("#qrUrl").text("");
    $("#btnExportQR").prop("disabled", true);
    qrExportPngUrl = "";
    qrExportFileName = `survey-${surveyId}-qr.png`;
    modal.show();

    const renderQR = function(finalToken){
        const url = window.location.origin + "/qa_system/survey.php?token=" + finalToken;
        $("#qrUrl").text(url);

        if(typeof QRCode === "undefined" || !QRCode.toCanvas){
            const fallbackSrc = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&format=png&data=" + encodeURIComponent(url);
            $("#qrContainer").html(`<img src="${fallbackSrc}" alt="Survey QR Code" style="width:180px;height:180px;border-radius:4px;display:block;margin:0 auto;">`);
            qrExportPngUrl = fallbackSrc;
            $("#btnExportQR").prop("disabled", false);
            return;
        }

        QRCode.toCanvas(document.createElement("canvas"), url, {width:180}, function(err, canvas){
            if(err){
                $("#qrContainer").html(`<div class="text-danger" style="font-size:0.8rem">Failed to generate QR code.</div>`);
                $("#btnExportQR").prop("disabled", true);
                return;
            }
            canvas.style.borderRadius = "4px";
            $("#qrContainer").empty().append(canvas);
            qrExportPngUrl = canvas.toDataURL("image/png");
            $("#btnExportQR").prop("disabled", false);
        });
    };

    if(token){
        renderQR(token);
        return;
    }

    $.post("/qa_system/api/surveys.php", {action:"ensure_token", survey_id:surveyId}, function(res){
        if(res && res.status === "success" && res.token){
            renderQR(res.token);
            return;
        }
        $("#qrContainer").html(`<div class="text-danger" style="font-size:0.8rem">Unable to generate QR token.</div>`);
        $("#btnExportQR").prop("disabled", true);
    }, "json").fail(function(){
        $("#qrContainer").html(`<div class="text-danger" style="font-size:0.8rem">Server error while preparing QR code.</div>`);
        $("#btnExportQR").prop("disabled", true);
    });
}

function exportQRPng(){
    if(!qrExportPngUrl){
        showToast("QR image is not ready yet.", "error");
        return;
    }
    const link = document.createElement("a");
    link.href = qrExportPngUrl;
    link.download = qrExportFileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
}

function deleteSurvey(id){
    confirmDelete("/qa_system/api/surveys.php", {action:"delete", survey_id:id}, () => location.reload());
}
</script>';
require_once __DIR__ . '/../includes/footer.php';
?>
