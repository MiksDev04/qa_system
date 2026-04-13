<?php
// pages/action_plans.php – Corrective & Preventive Action Management
// ByteBandits QA Management System
require_once __DIR__ . '/../config/database.php';
$page_title = 'Action Plans';

$conn = getConnection();
$per_page = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$status_filter = trim($_GET['status'] ?? '');
$priority_filter = trim($_GET['priority'] ?? '');
$prefill_audit_id = (int)($_GET['new_from_audit'] ?? 0);

$where = ['1=1'];
$params = [];
$types = '';

if ($status_filter) { $where[] = 'ap.status = ?'; $params[] = $status_filter; $types .= 's'; }
if ($priority_filter) { $where[] = 'ap.priority = ?'; $params[] = $priority_filter; $types .= 's'; }

$whereSQL = implode(' AND ', $where);

$count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM qa_action_plans ap WHERE $whereSQL");
if ($types) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['c'];
$total_pages = max(1, ceil($total / $per_page));
$offset = ($page - 1) * $per_page;

$stmt = $conn->prepare("
    SELECT ap.*, a.title AS audit_title
    FROM qa_action_plans ap
    LEFT JOIN qa_audits a ON ap.audit_id = a.audit_id
    WHERE $whereSQL
    ORDER BY CASE WHEN ap.priority='Critical' THEN 1 WHEN ap.priority='High' THEN 2 WHEN ap.priority='Medium' THEN 3 ELSE 4 END ASC,
             ap.target_date ASC
    LIMIT ? OFFSET ?
");
$all_params = array_merge($params, [$per_page, $offset]);
$stmt->bind_param($types . 'ii', ...$all_params);
$stmt->execute();
$plans = $stmt->get_result();

$audits_list = $conn->query("SELECT audit_id, title, scheduled_date, status FROM qa_audits ORDER BY scheduled_date DESC, audit_id DESC");
$audit_opts = [];
while ($a = $audits_list->fetch_assoc()) {
    $audit_opts[] = $a;
}

$prefill_audit = null;
if ($prefill_audit_id > 0) {
    $prefill_stmt = $conn->prepare('SELECT audit_id, title, findings, scope, auditor_name, auditor_email FROM qa_audits WHERE audit_id = ? LIMIT 1');
    $prefill_stmt->bind_param('i', $prefill_audit_id);
    $prefill_stmt->execute();
    $prefill_audit = $prefill_stmt->get_result()->fetch_assoc() ?: null;
}

$prefill_audit_json = json_encode($prefill_audit, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-clipboard-check me-2 text-accent"></i>Corrective & Preventive Actions</h1>
        <p>Track and manage quality improvement action plans and corrective actions</p>
    </div>
    <button class="btn-qa btn-qa-primary" data-bs-toggle="modal" data-bs-target="#apModal" onclick="openAdd()">
        <i class="bi bi-plus-lg"></i> New Action Plan
    </button>
</div>

<!-- Filters -->
<div class="filter-bar">
    <div style="min-width:140px">
        <label class="qa-form-label">Status</label>
        <select id="f-status" class="qa-form-control">
            <option value="">All</option>
            <option value="Open" <?= $status_filter === 'Open' ? 'selected' : '' ?>>Open</option>
            <option value="In Progress" <?= $status_filter === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
            <option value="Pending Verification" <?= $status_filter === 'Pending Verification' ? 'selected' : '' ?>>Pending Verification</option>
            <option value="Closed" <?= $status_filter === 'Closed' ? 'selected' : '' ?>>Closed</option>
        </select>
    </div>
    <div style="min-width:130px">
        <label class="qa-form-label">Priority</label>
        <select id="f-priority" class="qa-form-control">
            <option value="">All</option>
            <option value="Critical" <?= $priority_filter === 'Critical' ? 'selected' : '' ?>>Critical</option>
            <option value="High" <?= $priority_filter === 'High' ? 'selected' : '' ?>>High</option>
            <option value="Medium" <?= $priority_filter === 'Medium' ? 'selected' : '' ?>>Medium</option>
            <option value="Low" <?= $priority_filter === 'Low' ? 'selected' : '' ?>>Low</option>
        </select>
    </div>
    <div style="display:flex;align-items:flex-end;gap:8px">
        <button class="btn-qa btn-qa-primary" onclick="applyFilters()"><i class="bi bi-funnel"></i> Filter</button>
        <a href="action_plans.php" class="btn-qa btn-qa-secondary"><i class="bi bi-x-circle"></i> Clear</a>
    </div>
</div>

<!-- Cards -->
<div class="row g-3 mb-3">
<?php if ($plans->num_rows === 0): ?>
<div class="col-12"><div class="qa-card"><div class="empty-state"><i class="bi bi-inbox"></i><p>No action plans found</p></div></div></div>
<?php else: ?>
<?php while ($ap = $plans->fetch_assoc()):
    $daysLeft = (new DateTime($ap['target_date']))->diff(new DateTime())->days;
    $isOverdue = new DateTime($ap['target_date']) < new DateTime() && $ap['status'] !== 'Closed';
    $priorityColor = ['Critical' => '#dc3545', 'High' => '#fd7e14', 'Medium' => '#ffc107', 'Low' => '#28a745'];
?>
<div class="col-md-6 col-lg-4">
    <div class="qa-card h-100" style="border-left:4px solid <?= $priorityColor[$ap['priority']] ?? '#0d6efd' ?>">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <span class="badge-status <?php
                echo $ap['status'] === 'Closed' ? 'badge-active' :
                     ($ap['status'] === 'Open' ? 'badge-draft' :
                      ($ap['status'] === 'Pending Verification' ? 'badge-pending' : 'badge-closed'));
            ?>"><?= $ap['status'] ?></span>
            <span class="badge-status badge-pending"><?= $ap['priority'] ?></span>
        </div>
        <div class="fw-600 mb-1" style="font-size:0.95rem"><?= htmlspecialchars($ap['title']) ?></div>
        <div class="text-muted-qa" style="font-size:0.75rem;margin-bottom:8px">
            AP-<?= date('Y') ?>-<?= str_pad($ap['action_id'], 3, '0', STR_PAD_LEFT) ?>
        </div>

        <div style="font-size:0.8rem;margin-bottom:12px">
            <div class="mb-2"><strong>Assigned to:</strong> <?= htmlspecialchars($ap['assigned_to']) ?></div>
            <div class="mb-2"><strong>Type:</strong> <?= ucfirst($ap['action_type']) ?></div>
            <div class="mb-2"><strong>Linked Audit:</strong> <?= $ap['audit_id'] ? htmlspecialchars($ap['audit_title'] ?: ('#' . $ap['audit_id'])) : 'None' ?></div>
            <div style="display:flex;align-items:baseline;gap:8px">
                <strong>Target:</strong>
                <span class="<?= $isOverdue ? 'text-danger' : '' ?>"><?= date('M d, Y', strtotime($ap['target_date'])) ?></span>
            </div>
        </div>

        <div class="row-stack" style="gap:6px">
            <button class="btn-qa btn-qa-secondary btn-qa-sm" onclick="viewDetails(<?= $ap['action_id'] ?>)">
                <i class="bi bi-eye"></i> View Details
            </button>
            <button class="btn-qa btn-qa-secondary btn-qa-sm" onclick='editPlan(<?= json_encode($ap) ?>)'>
                <i class="bi bi-pencil"></i> Edit
            </button>
        </div>
    </div>
</div>
<?php endwhile; ?>
<?php endif; ?>
</div>

<!-- Pagination -->
<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
    <span class="text-muted-qa">Showing <?= min(($page-1)*$per_page+1, $total) ?>–<?= min($page*$per_page, $total) ?> of <?= $total ?> action plans</span>
    <div id="paginationContainer" class="qa-pagination"></div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="apModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="apModalTitle">New Action Plan</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="ap_id">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="qa-form-label">Title *</label>
                <input type="text" id="ap_title" class="qa-form-control" placeholder="Brief description of action" maxlength="200">
            </div>
            <div class="col-md-6">
                <label class="qa-form-label">Linked Audit</label>
                <select id="ap_audit" class="qa-form-control">
                    <option value="">None</option>
                    <?php foreach($audit_opts as $audit): ?>
                    <option value="<?= $audit['audit_id'] ?>">
                        #<?= $audit['audit_id'] ?> - <?= htmlspecialchars($audit['title']) ?>
                        <?php if (!empty($audit['scheduled_date'])): ?>
                            (<?= date('M d, Y', strtotime($audit['scheduled_date'])) ?>)
                        <?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="qa-form-label">Type *</label>
                <select id="ap_type" class="qa-form-control">
                    <option value="Corrective">Corrective</option>
                    <option value="Preventive">Preventive</option>
                    <option value="Improvement">Improvement</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="qa-form-label">Priority *</label>
                <select id="ap_priority" class="qa-form-control">
                    <option value="High">High</option>
                    <option value="Medium">Medium</option>
                    <option value="Low">Low</option>
                    <option value="Critical">Critical</option>
                </select>
            </div>
            <div class="col-12">
                <label class="qa-form-label">Description *</label>
                <textarea id="ap_desc" class="qa-form-control" rows="2" placeholder="What needs to be done…"></textarea>
            </div>
            <div class="col-12">
                <label class="qa-form-label">Root Cause Analysis</label>
                <textarea id="ap_root" class="qa-form-control" rows="2" placeholder="Why did this issue occur…"></textarea>
            </div>
            <div class="col-md-4">
                <label class="qa-form-label">Assigned To *</label>
                <input type="text" id="ap_assigned" class="qa-form-control" placeholder="Person name" maxlength="100">
            </div>
            <div class="col-md-4">
                <label class="qa-form-label">Email</label>
                <input type="email" id="ap_email" class="qa-form-control" placeholder="email@example.com">
            </div>
            <div class="col-md-4">
                <label class="qa-form-label">Department</label>
                <input type="text" id="ap_dept" class="qa-form-control" placeholder="Department" maxlength="100">
            </div>
            <div class="col-md-6">
                <label class="qa-form-label">Target Date *</label>
                <input type="date" id="ap_target" class="qa-form-control">
            </div>
            <div class="col-md-6">
                <label class="qa-form-label">Expected Outcome</label>
                <textarea id="ap_outcome" class="qa-form-control" rows="1" placeholder="Expected result…"></textarea>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-qa btn-qa-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-qa btn-qa-primary" onclick="savePlan()"><i class="bi bi-save"></i> Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Action Plan Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detailsBody">Loading…</div>
    </div>
  </div>
</div>

<?php
$extra_js = '<script>
$(function(){
    buildPagination("paginationContainer", '.$page.', '.$total_pages.', "goPage");

    const prefillAudit = '.$prefill_audit_json.';
    if (prefillAudit && prefillAudit.audit_id) {
        openAdd();
        $("#ap_audit").val(String(prefillAudit.audit_id));
        $("#ap_title").val(`Address findings from audit: ${prefillAudit.title}`);
        $("#ap_desc").val(prefillAudit.findings || prefillAudit.scope || "");
        $("#ap_root").val(prefillAudit.findings || "");
        $("#ap_assigned").val(prefillAudit.auditor_name || "");
        $("#ap_email").val(prefillAudit.auditor_email || "");
        new bootstrap.Modal(document.getElementById("apModal")).show();
    }
});

function goPage(p){
    const url = new URL(window.location);
    url.searchParams.set("page", p);
    window.location = url;
}

function applyFilters(){
    const url = new URL(window.location);
    url.searchParams.set("status", $("#f-status").val());
    url.searchParams.set("priority", $("#f-priority").val());
    url.searchParams.set("page", 1);
    window.location = url;
}

function openAdd(){
    $("#apModalTitle").text("New Action Plan");
    $("#ap_id,#ap_title,#ap_desc,#ap_root,#ap_assigned,#ap_email,#ap_dept,#ap_target,#ap_outcome,#ap_audit").val("");
    $("#ap_type").val("Corrective");
    $("#ap_priority").val("High");
}

function editPlan(data){
    $("#apModalTitle").text("Edit Action Plan");
    $("#ap_id").val(data.action_id);
    $("#ap_title").val(data.title);
    $("#ap_desc").val(data.description);
    $("#ap_root").val(data.root_cause);
    $("#ap_audit").val(data.audit_id || "");
    $("#ap_assigned").val(data.assigned_to);
    $("#ap_email").val(data.assigned_to_email);
    $("#ap_dept").val(data.department);
    $("#ap_target").val(data.target_date);
    $("#ap_outcome").val(data.expected_outcome);
    $("#ap_type").val(data.action_type);
    $("#ap_priority").val(data.priority);
    new bootstrap.Modal(document.getElementById("apModal")).show();
}

function savePlan(){
    const title = $("#ap_title").val().trim();
    const assigned = $("#ap_assigned").val().trim();
    const target = $("#ap_target").val();
    if(!title || !assigned || !target){ showToast("Required fields missing","error"); return; }

    qaAjax("/qa_system/api/action_plans.php", {
        action: $("#ap_id").val() ? "update" : "create",
        action_id: $("#ap_id").val(),
        audit_id: $("#ap_audit").val(),
        title, description: $("#ap_desc").val(), root_cause: $("#ap_root").val(),
        action_type: $("#ap_type").val(), priority: $("#ap_priority").val(),
        assigned_to: assigned, assigned_to_email: $("#ap_email").val(),
        department: $("#ap_dept").val(), target_date: target,
        expected_outcome: $("#ap_outcome").val()
    }, () => location.reload());
}

function viewDetails(id){
    $("#detailsBody").html("<div class=\"text-center py-3\"><div class=\"spinner-border\" style=\"color:var(--accent)\"></div></div>");
    new bootstrap.Modal(document.getElementById("detailsModal")).show();
    $.get("/qa_system/api/action_plans.php", {action:"get_details", action_id: id}, function(res){
        if(res.status === "success"){
            const ap = res.data;
            let html = `<div class="row g-3">
                <div class="col-md-6"><strong>Type:</strong> ${ap.action_type}</div>
                <div class="col-md-6"><strong>Priority:</strong> <span class="badge-status">${ap.priority}</span></div>
                <div class="col-md-6"><strong>Status:</strong> ${ap.status}</div>
                <div class="col-md-6"><strong>Assigned to:</strong> ${ap.assigned_to}</div>
                <div class="col-md-6"><strong>Linked Audit:</strong> ${ap.audit_id ? (ap.audit_title || ("#" + ap.audit_id)) : "None"}</div>
                <div class="col-md-6"><strong>Department:</strong> ${ap.department || "—"}</div>
                <div class="col-md-6"><strong>Target Date:</strong> ${ap.target_date}</div>
                <div class="col-md-6"><strong>Actual Date:</strong> ${ap.actual_date || "—"}</div>
                <div class="col-12"><strong>Description:</strong><p>${ap.description}</p></div>
                <div class="col-12"><strong>Root Cause:</strong><p>${ap.root_cause || "Not provided"}</p></div>
                <div class="col-12"><strong>Expected Outcome:</strong><p>${ap.expected_outcome || "—"}</p></div>
                <div class="col-12"><strong>Actual Outcome:</strong><p>${ap.actual_outcome || "—"}</p></div>
            </div>`;
            $("#detailsBody").html(html);
        } else {
            $("#detailsBody").html("<p class=\"text-danger\">Error loading details</p>");
        }
    });
}
</script>';
require_once __DIR__ . '/../includes/footer.php';
?>
