<?php
// pages/audits.php - Internal Audit Management
// ByteBandits QA Management System
require_once __DIR__ . '/../config/database.php';
$page_title = 'Internal Audits';

$conn = getConnection();
$has_standard_link = false;
$stdColCheck = $conn->query("SHOW COLUMNS FROM qa_audits LIKE 'standard_id'");
if ($stdColCheck && $stdColCheck->num_rows > 0) {
    $has_standard_link = true;
}

$standards_options = [];
$stdQ = $conn->query("SELECT standard_id, title FROM qa_standards WHERE status='Active' ORDER BY title");
if ($stdQ) {
    while ($s = $stdQ->fetch_assoc()) {
        $standards_options[] = $s;
    }
}

$per_page = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$status_filter = trim($_GET['status'] ?? '');
$standard_filter = (int)($_GET['standard_id'] ?? 0);
$prefill_standard_id = (int)($_GET['new_from_standard'] ?? 0);

$where = ['1=1'];
$params = [];
$types = '';

if ($status_filter) {
    $where[] = $has_standard_link ? 'a.status = ?' : 'status = ?';
    $params[] = $status_filter;
    $types .= 's';
}
if ($has_standard_link && $standard_filter > 0) {
    $where[] = 'a.standard_id = ?';
    $params[] = $standard_filter;
    $types .= 'i';
}

$whereSQL = implode(' AND ', $where);

$count_sql = $has_standard_link
    ? "SELECT COUNT(*) as c FROM qa_audits a WHERE $whereSQL"
    : "SELECT COUNT(*) as c FROM qa_audits WHERE $whereSQL";
$count_stmt = $conn->prepare($count_sql);
if ($types) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total = (int)$count_stmt->get_result()->fetch_assoc()['c'];
$total_pages = max(1, ceil($total / $per_page));
$offset = ($page - 1) * $per_page;
$show_start = $total > 0 ? ($offset + 1) : 0;
$show_end = $total > 0 ? min($offset + $per_page, $total) : 0;

$main_sql = $has_standard_link
    ? "SELECT a.*, s.title AS standard_title
       FROM qa_audits a
       LEFT JOIN qa_standards s ON a.standard_id = s.standard_id
       WHERE $whereSQL
       ORDER BY a.scheduled_date DESC
       LIMIT ? OFFSET ?"
    : "SELECT * FROM qa_audits
       WHERE $whereSQL
       ORDER BY scheduled_date DESC
       LIMIT ? OFFSET ?";
$stmt = $conn->prepare($main_sql);
$all_params = array_merge($params, [$per_page, $offset]);
$stmt->bind_param($types . 'ii', ...$all_params);
$stmt->execute();
$audits = $stmt->get_result();

$prefill_standard = null;
if ($has_standard_link && $prefill_standard_id > 0) {
    $pstmt = $conn->prepare('SELECT standard_id, title, compliance_body, category FROM qa_standards WHERE standard_id = ? LIMIT 1');
    $pstmt->bind_param('i', $prefill_standard_id);
    $pstmt->execute();
    $prefill_standard = $pstmt->get_result()->fetch_assoc() ?: null;
}
$prefill_standard_json = json_encode($prefill_standard, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-search me-2 text-accent"></i>Internal Audits</h1>
        <p>Schedule and manage internal quality audits, findings, and follow-up</p>
    </div>
    <button class="btn-qa btn-qa-primary" data-bs-toggle="modal" data-bs-target="#auditModal" onclick="openAdd()">
        <i class="bi bi-plus-lg"></i> New Audit
    </button>
</div>

<!-- Filters -->
<div class="filter-bar">
    <div style="min-width:140px">
        <label class="qa-form-label">Status</label>
        <select id="f-status" class="qa-form-control">
            <option value="">All</option>
            <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
            <option value="In Progress" <?= $status_filter === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
            <option value="Completed" <?= $status_filter === 'Completed' ? 'selected' : '' ?>>Completed</option>
            <option value="Cancelled" <?= $status_filter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
    </div>
    <?php if ($has_standard_link): ?>
    <div style="min-width:260px;flex:1">
        <label class="qa-form-label">Linked Standard</label>
        <select id="f-standard" class="qa-form-control">
            <option value="">All Standards</option>
            <?php foreach ($standards_options as $std): ?>
            <option value="<?= $std['standard_id'] ?>" <?= $standard_filter === (int)$std['standard_id'] ? 'selected' : '' ?>><?= htmlspecialchars($std['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <div style="display:flex;align-items:flex-end;gap:8px">
        <button class="btn-qa btn-qa-primary" onclick="applyFilters()"><i class="bi bi-funnel"></i> Filter</button>
        <a href="audits.php" class="btn-qa btn-qa-secondary"><i class="bi bi-x-circle"></i> Clear</a>
    </div>
</div>

<!-- Table -->
<div class="qa-card p-0">
    <div class="qa-table-wrapper table-responsive" style="border:none;border-radius:var(--radius)">
        <table class="table qa-table table-sm align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Audit Type</th>
                    <th>Scheduled Date</th>
                    <th>Auditor</th>
                    <th>Status</th>
                    <?php if ($has_standard_link): ?><th>Standard</th><?php endif; ?>
                    <th>Findings</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($audits->num_rows === 0): ?>
                <tr><td colspan="<?= $has_standard_link ? '9' : '8' ?>"><div class="empty-state"><i class="bi bi-inbox"></i><p>No audits found</p></div></td></tr>
                <?php else: ?>
                <?php $n = $offset + 1; while ($au = $audits->fetch_assoc()): ?>
                <?php
                    $scope_preview = trim((string)($au['scope'] ?? $au['description'] ?? ''));
                    if ($scope_preview !== '' && strlen($scope_preview) > 80) {
                        $scope_preview = substr($scope_preview, 0, 80) . '...';
                    }
                ?>
                <tr>
                    <td class="text-muted-qa mono"><?= $n++ ?></td>
                    <td>
                        <div class="fw-600"><?= htmlspecialchars($au['title']) ?></div>
                        <?php if ($scope_preview !== ''): ?>
                        <div class="text-muted-qa" style="font-size:0.78rem;margin-top:2px"><?= htmlspecialchars($scope_preview) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge-status badge-pending"><?= htmlspecialchars($au['audit_type']) ?></span></td>
                    <td class="mono"><?= $au['scheduled_date'] ? date('M d, Y', strtotime($au['scheduled_date'])) : 'N/A' ?></td>
                    <td>
                        <div><?= htmlspecialchars($au['auditor_name'] ?? 'N/A') ?></div>
                        <?php if (!empty($au['auditor_email'])): ?>
                        <div class="text-muted-qa" style="font-size:0.78rem;margin-top:2px"><?= htmlspecialchars($au['auditor_email']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge-status <?= $au['status'] === 'Completed' ? 'badge-active' : ($au['status'] === 'In Progress' ? 'badge-pending' : ($au['status'] === 'Cancelled' ? 'badge-closed' : 'badge-draft')) ?>">
                            <?= $au['status'] ?>
                        </span>
                    </td>
                    <?php if ($has_standard_link): ?>
                    <td>
                        <?php if (!empty($au['standard_title'])): ?>
                        <span class="badge-status badge-active"><?= htmlspecialchars($au['standard_title']) ?></span>
                        <?php else: ?>
                        <span class="text-muted-qa">N/A</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td>
                        <?php if (!empty($au['findings'])): ?>
                        <span class="badge-status badge-closed">Has findings</span>
                        <?php else: ?>
                        <span class="text-muted-qa">No findings</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon"
                                onclick="viewAuditDetails(<?= $au['audit_id'] ?>)" title="View Findings">
                                <i class="bi bi-eye"></i>
                            </button>
                            <a class="btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon"
                               href="/qa_system/pages/action_plans.php?new_from_audit=<?= $au['audit_id'] ?>"
                               title="Create Action Plan">
                                <i class="bi bi-clipboard-plus"></i>
                            </a>
                            <button class="btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon"
                                onclick='editAudit(<?= json_encode($au) ?>)' title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn-qa btn-qa-danger btn-qa-sm btn-qa-icon"
                                onclick="deleteAudit(<?= $au['audit_id'] ?>)" title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
    <span class="text-muted-qa">Showing <?= $show_start ?>-<?= $show_end ?> of <?= $total ?> audits</span>
    <div id="paginationContainer" class="qa-pagination"></div>
</div>

<!-- Add/Edit Audit Modal -->
<div class="modal fade" id="auditModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="auditModalTitle">New Audit</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="aud_id">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="qa-form-label">Audit Type *</label>
                <select id="aud_type" class="qa-form-control">
                    <option value="Internal Audit">Internal Audit</option>
                    <option value="External Accreditation">External Accreditation</option>
                    <option value="Process Audit">Process Audit</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="qa-form-label">Status *</label>
                <select id="aud_status" class="qa-form-control">
                    <option value="Pending">Pending</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>
            <div class="col-12">
                <label class="qa-form-label">Linked Standard</label>
                <select id="aud_standard" class="qa-form-control">
                    <option value="">None</option>
                    <?php foreach ($standards_options as $std): ?>
                    <option value="<?= $std['standard_id'] ?>"><?= htmlspecialchars($std['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="qa-form-label">Title *</label>
                <input type="text" id="aud_title" class="qa-form-control" placeholder="e.g. Academic Program Review" maxlength="200">
            </div>
            <div class="col-12">
                <label class="qa-form-label">Description / Scope</label>
                <textarea id="aud_desc" class="qa-form-control" rows="2" placeholder="What is being audited..."></textarea>
            </div>
            <div class="col-12">
                <label class="qa-form-label">Findings & Issues</label>
                <textarea id="aud_findings" class="qa-form-control" rows="3" placeholder="Document all findings, issues, or observations..."></textarea>
            </div>
            <div class="col-md-4">
                <label class="qa-form-label">Scheduled Date *</label>
                <input type="date" id="aud_scheduled" class="qa-form-control">
            </div>
            <div class="col-md-4">
                <label class="qa-form-label">Auditor Name</label>
                <input type="text" id="aud_name" class="qa-form-control" placeholder="Auditor name" maxlength="100">
            </div>
            <div class="col-md-4">
                <label class="qa-form-label">Auditor Email</label>
                <input type="email" id="aud_email" class="qa-form-control" placeholder="auditor@example.com">
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-qa btn-qa-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-qa btn-qa-primary" onclick="saveAudit()"><i class="bi bi-save"></i> Save</button>
      </div>
    </div>
  </div>
</div>

<!-- Audit Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Audit Findings</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="detailsBody">Loading...</div>
    </div>
  </div>
</div>

<?php
$extra_js = '<script>
$(function(){
    buildPagination("paginationContainer", '.$page.', '.$total_pages.', "goPage");

    const prefillStandard = '.$prefill_standard_json.';
    if (prefillStandard && prefillStandard.standard_id) {
        openAdd();
        if ($("#aud_standard").length) {
            $("#aud_standard").val(String(prefillStandard.standard_id));
        }
        $("#aud_type").val("Internal Audit");
        $("#aud_title").val(`Compliance Audit: ${prefillStandard.title}`);
        $("#aud_desc").val(`Audit linked to ${prefillStandard.compliance_body || "institutional"} standard under ${prefillStandard.category || "General"} category.`);
        new bootstrap.Modal(document.getElementById("auditModal")).show();
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
    const standard = $("#f-standard").length ? $("#f-standard").val() : "";
    if (standard) {
        url.searchParams.set("standard_id", standard);
    } else {
        url.searchParams.delete("standard_id");
    }
    url.searchParams.set("page", 1);
    window.location = url;
}

function openAdd(){
    $("#auditModalTitle").text("New Audit");
    $("#aud_id,#aud_title,#aud_desc,#aud_findings,#aud_scheduled,#aud_name,#aud_email,#aud_standard").val("");
    $("#aud_type").val("Internal Audit");
    $("#aud_status").val("Pending");
}

function editAudit(data){
    $("#auditModalTitle").text("Edit Audit");
    $("#aud_id").val(data.audit_id);
    $("#aud_title").val(data.title);
    $("#aud_desc").val(data.scope || data.description);
    $("#aud_findings").val(data.findings || "");
    $("#aud_standard").val(data.standard_id || "");
    $("#aud_type").val(data.audit_type);
    $("#aud_status").val(data.status);
    $("#aud_scheduled").val(data.scheduled_date);
    $("#aud_name").val(data.auditor_name);
    $("#aud_email").val(data.auditor_email);
    new bootstrap.Modal(document.getElementById("auditModal")).show();
}

function saveAudit(){
    const title = $("#aud_title").val().trim();
    const scheduled = $("#aud_scheduled").val();
    if(!title || !scheduled){ alert("Required fields missing"); return; }

    qaAjax("/qa_system/api/audits.php", {
        action: $("#aud_id").val() ? "update" : "create",
        audit_id: $("#aud_id").val(),
        standard_id: $("#aud_standard").length ? $("#aud_standard").val() : "",
        title, audit_type: $("#aud_type").val(), status: $("#aud_status").val(),
        scope: $("#aud_desc").val(), findings: $("#aud_findings").val(), scheduled_date: scheduled,
        auditor_name: $("#aud_name").val(), auditor_email: $("#aud_email").val()
    }, () => { location.reload(); }, (err) => {
        alert("Error: " + (err.message || "Failed to save audit"));
    });
}

function viewAuditDetails(id){
    $("#detailsBody").html("<div class=\"text-center py-3\"><div class=\"spinner-border\" style=\"color:var(--accent)\"></div></div>");
    new bootstrap.Modal(document.getElementById("detailsModal")).show();

    $.get("/qa_system/api/audits.php", {action: "get_detail", audit_id: id}, function(res){
        if(res.status !== "success"){
            $("#detailsBody").html("<p class=\"text-danger\">Unable to load audit details.</p>");
            return;
        }

        const au = res.data;
        const findings = (au.findings && au.findings.trim()) ? au.findings : "No findings documented yet.";
        const scope = (au.scope && au.scope.trim()) ? au.scope : (au.description || "N/A");

        const html = `<div class="row g-3">
            <div class="col-md-6"><strong>Audit Type:</strong> ${au.audit_type || "N/A"}</div>
            <div class="col-md-6"><strong>Status:</strong> ${au.status || "N/A"}</div>
            <div class="col-md-6"><strong>Scheduled Date:</strong> ${au.scheduled_date || "N/A"}</div>
            <div class="col-md-6"><strong>Actual Date:</strong> ${au.actual_date || "N/A"}</div>
            <div class="col-md-6"><strong>Standard:</strong> ${au.standard_title || (au.standard_id ? ("#" + au.standard_id) : "None")}</div>
            <div class="col-md-6"><strong>Auditor:</strong> ${au.auditor_name || "N/A"}</div>
            <div class="col-md-6"><strong>Email:</strong> ${au.auditor_email || "N/A"}</div>
            <div class="col-12"><strong>Title:</strong><p>${au.title || "N/A"}</p></div>
            <div class="col-12"><strong>Scope:</strong><p>${scope}</p></div>
            <div class="col-12"><strong>Findings:</strong><p>${findings}</p></div>
            <div class="col-12">
                <a class="btn-qa btn-qa-primary" href="/qa_system/pages/action_plans.php?new_from_audit=${au.audit_id}">
                    <i class="bi bi-clipboard-plus"></i> Create Action Plan From This Audit
                </a>
            </div>
        </div>`;

        $("#detailsBody").html(html);
    }).fail(function(){
        $("#detailsBody").html("<p class=\"text-danger\">Unable to load audit details.</p>");
    });
}

function deleteAudit(id) {
    confirmDelete("/qa_system/api/audits.php", {action:"delete", audit_id: id}, () => location.reload());
}
</script>';
require_once __DIR__ . '/../includes/footer.php';
?>


