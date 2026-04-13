<?php
// pages/responses.php – Survey Responses viewer
// ByteBandits QA Management System
require_once __DIR__ . '/../config/database.php';
$page_title = 'Survey Responses';

$conn = getConnection();
$per_page = 12;
$page = max(1,(int)($_GET['page'] ?? 1));
$survey_filter = (int)($_GET['survey_id'] ?? 0);
$role_filter   = trim($_GET['role'] ?? '');

$where=['1=1']; $params=[]; $types='';
if($survey_filter){ $where[] = 'r.survey_id = ?'; $params[]=$survey_filter; $types.='i'; }
if($role_filter)  { $where[] = 'r.respondent_role = ?'; $params[]=$role_filter; $types.='s'; }
$whereSQL = implode(' AND ',$where);

$count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM survey_responses r WHERE $whereSQL");
if($types) $count_stmt->bind_param($types,...$params);
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['c'];
$total_pages = max(1,ceil($total/$per_page));
$offset=($page-1)*$per_page;

$stmt = $conn->prepare("
    SELECT r.*, s.title as survey_title,
        (SELECT AVG(a.rating) FROM survey_answers a
         JOIN survey_questions q ON a.question_id=q.question_id
         WHERE a.response_id=r.response_id AND q.question_type='rating') as avg_rating,
        (SELECT COUNT(*) FROM survey_answers a WHERE a.response_id=r.response_id) as answer_count
    FROM survey_responses r
    JOIN surveys s ON r.survey_id=s.survey_id
    WHERE $whereSQL
    ORDER BY r.submitted_at DESC
    LIMIT ? OFFSET ?
");
$all=array_merge($params,[$per_page,$offset]);
$stmt->bind_param($types.'ii',...$all);
$stmt->execute();
$responses = $stmt->get_result();

$survey_list = $conn->query("SELECT survey_id,title FROM surveys ORDER BY created_date DESC");
$roles = $conn->query("SELECT DISTINCT respondent_role FROM survey_responses WHERE respondent_role IS NOT NULL ORDER BY respondent_role");

$segWhere = ['1=1'];
$segParams = [];
$segTypes = '';
if ($survey_filter) { $segWhere[] = 'sr.survey_id = ?'; $segParams[] = $survey_filter; $segTypes .= 'i'; }

$segSql = "SELECT
                        COALESCE(NULLIF(sr.respondent_role,''), 'Unspecified') AS respondent_role,
                        COUNT(DISTINCT sr.response_id) AS response_count,
                        ROUND(AVG(sa.rating),2) AS avg_rating
                    FROM survey_responses sr
                    LEFT JOIN survey_answers sa ON sa.response_id=sr.response_id AND sa.rating IS NOT NULL
                    WHERE " . implode(' AND ', $segWhere) . "
                    GROUP BY COALESCE(NULLIF(sr.respondent_role,''), 'Unspecified')
                    ORDER BY response_count DESC, respondent_role ASC";

$seg_stmt = $conn->prepare($segSql);
if ($segTypes) $seg_stmt->bind_param($segTypes, ...$segParams);
$seg_stmt->execute();
$seg_result = $seg_stmt->get_result();
$role_breakdown = [];
while ($seg = $seg_result->fetch_assoc()) {
        $role_breakdown[] = $seg;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-chat-square-text me-2 text-accent"></i>Responses</h1>
        <p>View and analyze all survey responses submitted by respondents</p>
    </div>
</div>

<!-- Filters -->
<div class="filter-bar">
    <div style="min-width:220px;flex:1">
        <label class="qa-form-label">Survey</label>
        <select id="f-survey" class="qa-form-control">
            <option value="">All Surveys</option>
            <?php while($s=$survey_list->fetch_assoc()): ?>
            <option value="<?= $s['survey_id'] ?>" <?= $survey_filter==$s['survey_id']?'selected':'' ?>><?= htmlspecialchars($s['title']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div style="min-width:150px">
        <label class="qa-form-label">Respondent Role</label>
        <select id="f-role" class="qa-form-control">
            <option value="">All Roles</option>
            <?php while($r=$roles->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($r['respondent_role']) ?>" <?= $role_filter===$r['respondent_role']?'selected':'' ?>><?= htmlspecialchars($r['respondent_role']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div style="display:flex;align-items:flex-end;gap:8px">
        <button class="btn-qa btn-qa-primary" onclick="applyFilters()"><i class="bi bi-funnel"></i> Filter</button>
        <a href="responses.php" class="btn-qa btn-qa-secondary"><i class="bi bi-x-circle"></i> Clear</a>
    </div>
</div>

<!-- Summary Stats -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-chat-dots"></i></div>
            <div><div class="stat-value"><?= $total ?></div><div class="stat-label">Total Responses</div></div>
        </div>
    </div>
    <div class="col-sm-4">
        <?php
        $avg_q = $conn->query("SELECT AVG(rating) as avg FROM survey_answers WHERE rating IS NOT NULL")->fetch_assoc()['avg'];
        ?>
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-star-half"></i></div>
            <div><div class="stat-value"><?= $avg_q ? number_format($avg_q,2) : '—' ?></div><div class="stat-label">Avg Rating</div></div>
        </div>
    </div>
    <div class="col-sm-4">
        <?php
        $today_count = $conn->query("SELECT COUNT(*) as c FROM survey_responses WHERE DATE(submitted_at)=CURDATE()")->fetch_assoc()['c'];
        ?>
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-calendar-check"></i></div>
            <div><div class="stat-value"><?= $today_count ?></div><div class="stat-label">Responses Today</div></div>
        </div>
    </div>
</div>

<!-- Respondent Segmentation -->
<div class="qa-card mb-4">
    <div class="qa-card-title">Respondent Segmentation</div>
    <div class="qa-table-wrapper" style="border:none;margin-top:12px">
        <table class="qa-table">
            <thead>
                <tr>
                    <th>Role</th>
                    <th>Responses</th>
                    <th>Share</th>
                    <th>Average Rating</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($role_breakdown)): ?>
                <tr><td colspan="4"><div class="empty-state"><i class="bi bi-bar-chart"></i><p>No segmentation data</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($role_breakdown as $seg): ?>
                <?php $segCount = (int)$seg['response_count']; $share = $total > 0 ? round(($segCount / $total) * 100, 1) : 0; ?>
                <tr>
                    <td><span class="badge-status badge-pending"><?= htmlspecialchars($seg['respondent_role']) ?></span></td>
                    <td class="mono"><?= $segCount ?></td>
                    <td>
                        <div class="qa-progress" style="width:140px;display:inline-flex;vertical-align:middle">
                            <div class="qa-progress-bar info" style="width:<?= max(2, min(100, $share)) ?>%"></div>
                        </div>
                        <span class="text-muted-qa mono" style="margin-left:8px"><?= $share ?>%</span>
                    </td>
                    <td>
                        <?php if (!empty($seg['avg_rating'])): ?>
                        <span class="fw-600" style="color:var(--warning)"><i class="bi bi-star-fill"></i> <?= number_format((float)$seg['avg_rating'], 2) ?></span>
                        <?php else: ?>
                        <span class="text-muted-qa">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Response Table -->
<div class="qa-card p-0">
    <div class="qa-table-wrapper" style="border:none;border-radius:var(--radius)">
        <table class="qa-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Survey</th>
                    <th>Respondent</th>
                    <th>Role</th>
                    <th>Avg Rating</th>
                    <th>Answers</th>
                    <th>Submitted</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($responses->num_rows===0): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-inbox"></i><p>No responses found</p></div></td></tr>
                <?php else: ?>
                <?php $n=$offset+1; while($res=$responses->fetch_assoc()): ?>
                <tr>
                    <td class="text-muted-qa mono"><?= $n++ ?></td>
                    <td style="max-width:220px">
                        <div class="fw-600" style="font-size:0.85rem;white-space:normal"><?= htmlspecialchars($res['survey_title']) ?></div>
                    </td>
                    <td><?= htmlspecialchars($res['respondent_name'] ?? 'Anonymous') ?></td>
                    <td>
                        <?php if($res['respondent_role']): ?>
                        <span class="badge-status badge-pending"><?= htmlspecialchars($res['respondent_role']) ?></span>
                        <?php else: echo '<span class="text-muted-qa">—</span>'; endif; ?>
                    </td>
                    <td>
                        <?php if($res['avg_rating']): ?>
                        <span class="fw-600" style="color:var(--warning)">
                            <i class="bi bi-star-fill"></i> <?= number_format($res['avg_rating'],1) ?>
                        </span>
                        <?php else: echo '<span class="text-muted-qa">—</span>'; endif; ?>
                    </td>
                    <td class="mono"><?= $res['answer_count'] ?></td>
                    <td class="text-muted-qa mono" style="font-size:0.8rem"><?= date('M d, Y H:i',strtotime($res['submitted_at'])) ?></td>
                    <td>
                        <button class="btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon"
                            onclick="viewResponse(<?= $res['response_id'] ?>)" title="View answers">
                            <i class="bi bi-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
    <span class="text-muted-qa">Showing <?= min($offset+1,$total) ?>–<?= min($offset+$per_page,$total) ?> of <?= $total ?> responses</span>
    <div id="paginationContainer" class="qa-pagination"></div>
</div>

<!-- Response detail modal -->
<div class="modal fade" id="responseDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Response Detail</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="responseDetailBody">Loading…</div>
    </div>
  </div>
</div>

<?php
$extra_js = '<script>
$(function(){ buildPagination("paginationContainer",'.$page.','.$total_pages.',"goPage"); });

function goPage(p){ const url=new URL(window.location); url.searchParams.set("page",p); window.location=url; }
function applyFilters(){ const url=new URL(window.location); url.searchParams.set("survey_id",$("#f-survey").val()); url.searchParams.set("role",$("#f-role").val()); url.searchParams.set("page",1); window.location=url; }

function viewResponse(responseId){
    $("#responseDetailBody").html(`<div class="text-center py-3"><div class="spinner-border spinner-border-sm" style="color:var(--accent)"></div></div>`);
    new bootstrap.Modal(document.getElementById("responseDetailModal")).show();
    $.get("/qa_system/api/responses.php", {action:"get_detail", response_id:responseId}, function(res){
        if(res.status!=="success"){ $("#responseDetailBody").html("<p class=\"text-danger\">Error loading response.</p>"); return; }
        const d = res.data;
        let html = `<div class="mb-3 p-3" style="background:var(--bg-hover);border-radius:var(--radius-sm)">
            <div class="row g-2">
                <div class="col-md-6"><span class="text-muted-qa">Respondent: </span><b>${d.respondent_name||"Anonymous"}</b></div>
                <div class="col-md-6"><span class="text-muted-qa">Role: </span><b>${d.respondent_role||"—"}</b></div>
                <div class="col-md-6"><span class="text-muted-qa">Email: </span>${d.respondent_email||"—"}</div>
                <div class="col-md-6"><span class="text-muted-qa">Submitted: </span>${d.submitted_at}</div>
            </div>
        </div>`;
        if(d.answers && d.answers.length){
            d.answers.forEach(function(a,i){
                let ansHtml = "";
                if(a.question_type==="rating" && a.rating){
                    const stars = "★".repeat(a.rating) + "☆".repeat(5-a.rating);
                    ansHtml = `<span style="color:var(--warning);font-size:1.1rem">${stars}</span> <span class="text-muted-qa">(${a.rating}/5)</span>`;
                } else {
                    ansHtml = `<span style="color:var(--text-primary)">${a.answer_text||"—"}</span>`;
                }
                html += `<div class="mb-2 p-3" style="border:1px solid var(--border);border-radius:var(--radius-sm)">
                    <div class="fw-600 mb-1">${i+1}. ${a.question_text}</div>
                    <div>${ansHtml}</div>
                </div>`;
            });
        } else {
            html += "<p class=\"text-muted-qa text-center\">No answers found.</p>";
        }
        $("#responseDetailBody").html(html);
    });
}
</script>';
require_once __DIR__ . '/../includes/footer.php';
?>
