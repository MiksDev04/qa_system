<?php
// pages/standards.php â€“ Standards & Policies Management
// ByteBandits QA Management System
require_once __DIR__ . '/../config/database.php';
$page_title = 'Standards & Policies';

$conn = getConnection();
$per_page = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$type_filter = trim($_GET['type'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$where = ['1=1'];
$params = [];
$types = '';

if ($type_filter === 'standards') {
    $table = 'qa_standards';
    if ($status_filter) { $where[] = 'status = ?'; $params[] = $status_filter; $types .= 's'; }
    $whereSQL = implode(' AND ', $where);

    $count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM $table WHERE $whereSQL");
    if ($types) $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total = $count_stmt->get_result()->fetch_assoc()['c'];
    $total_pages = max(1, ceil($total / $per_page));
    $offset = ($page - 1) * $per_page;

    $stmt = $conn->prepare("SELECT * FROM $table WHERE $whereSQL ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $all_params = array_merge($params, [$per_page, $offset]);
    $stmt->bind_param($types . 'ii', ...$all_params);
    $stmt->execute();
    $records = $stmt->get_result();
    $record_type = 'Standards';
} else {
    $table = 'qa_policies';
    if ($status_filter) { $where[] = 'status = ?'; $params[] = $status_filter; $types .= 's'; }
    $whereSQL = implode(' AND ', $where);

    $count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM $table WHERE $whereSQL");
    if ($types) $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total = $count_stmt->get_result()->fetch_assoc()['c'];
    $total_pages = max(1, ceil($total / $per_page));
    $offset = ($page - 1) * $per_page;

    $stmt = $conn->prepare("SELECT * FROM $table WHERE $whereSQL ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $all_params = array_merge($params, [$per_page, $offset]);
    $stmt->bind_param($types . 'ii', ...$all_params);
    $stmt->execute();
    $records = $stmt->get_result();
    $record_type = 'Policies';
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-file-earmark-text me-2 text-accent"></i>Standards & Policies</h1>
        <p>Manage accreditation standards, quality policies, and procedural documentation</p>
    </div>
    <button class="btn-qa btn-qa-primary" data-bs-toggle="modal" data-bs-target="#stdModal" onclick="openAdd()">
        <i class="bi bi-plus-lg"></i> Add <?= $record_type ?>
    </button>
</div>

<!-- Tab Navigation -->
<div class="qa-tabs mb-3">
    <a href="?type=standards" class="qa-tab <?= $type_filter !== 'policies' ? 'active' : '' ?>">
        <i class="bi bi-bookmark me-1"></i> Standards
    </a>
    <a href="?type=policies" class="qa-tab <?= $type_filter === 'policies' ? 'active' : '' ?>">
        <i class="bi bi-file-text me-1"></i> Policies & Procedures
    </a>
</div>

<!-- Filters -->
<div class="filter-bar">
    <div style="min-width:150px">
        <label class="qa-form-label">Status</label>
        <select id="f-status" class="qa-form-control">
            <option value="">All</option>
            <option value="Active" <?= $status_filter === 'Active' ? 'selected' : '' ?>>Active</option>
            <option value="Draft" <?= $status_filter === 'Draft' ? 'selected' : '' ?>>Draft</option>
            <option value="Archived" <?= $status_filter === 'Archived' ? 'selected' : '' ?>>Archived</option>
        </select>
    </div>
    <div style="display:flex;align-items:flex-end;gap:8px">
        <button class="btn-qa btn-qa-primary" onclick="applyFilters()"><i class="bi bi-funnel"></i> Filter</button>
        <a href="standards.php" class="btn-qa btn-qa-secondary"><i class="bi bi-x-circle"></i> Clear</a>
    </div>
</div>

<!-- Table -->
<div class="qa-card p-0">
    <div class="qa-table-wrapper table-responsive" style="border:none;border-radius:var(--radius)">
        <table class="table qa-table table-sm align-middle">
            <thead>
                <tr>
                    <th style="max-width:35%">Title</th>
                    <th>Compliance Body</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Effective Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($records->num_rows === 0): ?>
                <tr><td colspan="6"><div class="empty-state"><i class="bi bi-inbox"></i><p>No records found</p></div></td></tr>
                <?php else: ?>
                <?php while ($rec = $records->fetch_assoc()): ?>
                <tr>
                    <td>
                        <div class="fw-600"><?= htmlspecialchars($rec['title'] ?? $rec['title']) ?></div>
                        <div class="text-muted-qa" style="font-size:0.75rem"><?= htmlspecialchars(substr($rec['description'] ?? '', 0, 60)) ?>â€¦</div>
                    </td>
                    <td><?= htmlspecialchars($rec['compliance_body'] ?? $rec['category'] ?? 'â€”') ?></td>
                    <td><span class="badge-status badge-pending"><?= htmlspecialchars($rec['category'] ?? $rec['owner'] ?? 'â€”') ?></span></td>
                    <td>
                        <span class="badge-status <?= ($rec['status'] ?? '') === 'Active' ? 'badge-active' : 'badge-draft' ?>">
                            <?= $rec['status'] ?? $rec['status'] ?>
                        </span>
                    </td>
                    <td class="mono"><?= $rec['effective_date'] ?? $rec['effective_date'] ?? 'â€”' ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <?php if ($type_filter !== 'policies'): ?>
                            <a class="btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon"
                               href="/qa_system/pages/audits.php?new_from_standard=<?= (int)$rec['standard_id'] ?>"
                               title="Create Audit from Standard">
                                <i class="bi bi-clipboard-plus"></i>
                            </a>
                            <?php endif; ?>
                            <button class="btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon"
                                onclick='editRecord(<?= json_encode($rec) ?>)' title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn-qa btn-qa-danger btn-qa-sm btn-qa-icon"
                                onclick="deleteRecord(<?= $rec[array_key_first($rec)] ?>)" title="Delete">
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
    <span class="text-muted-qa">Showing <?= min(($page-1)*$per_page+1, $total) ?>â€“<?= min($page*$per_page, $total) ?> of <?= $total ?> records</span>
    <div id="paginationContainer" class="qa-pagination"></div>
</div>

<!-- Modal -->
<div class="modal fade" id="stdModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="stdModalTitle">Add <?= $record_type ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rec_id">
        <input type="hidden" id="rec_type" value="<?= htmlspecialchars($type_filter) ?>">
        <div class="row g-3">
            <div class="col-12">
                <label class="qa-form-label">Title *</label>
                <input type="text" id="rec_title" class="qa-form-control" placeholder="e.g. CHED Accreditation Standards" maxlength="200">
            </div>
            <div class="col-12">
                <label class="qa-form-label">Description</label>
                <textarea id="rec_desc" class="qa-form-control" rows="3" placeholder="Detailed descriptionâ€¦"></textarea>
            </div>
            <div class="col-md-4">
                <label class="qa-form-label"><?= $record_type === 'Standards' ? 'Compliance Body' : 'Owner' ?> *</label>
                <input type="text" id="rec_body" class="qa-form-control" placeholder="CHED, ISO 9001, etcâ€¦" maxlength="100">
            </div>
            <div class="col-md-4">
                <label class="qa-form-label">Category *</label>
                <input type="text" id="rec_category" class="qa-form-control" placeholder="Academic, Governance, etcâ€¦" maxlength="100">
            </div>
            <div class="col-md-4">
                <label class="qa-form-label">Status</label>
                <select id="rec_status" class="qa-form-control">
                    <option value="Active">Active</option>
                    <option value="Draft">Draft</option>
                    <option value="Archived">Archived</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="qa-form-label">Effective Date</label>
                <input type="date" id="rec_date" class="qa-form-control">
            </div>
            <div class="col-md-6" id="reviewCol" style="display:none">
                <label class="qa-form-label">Review Date</label>
                <input type="date" id="rec_review" class="qa-form-control">
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-qa btn-qa-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-qa btn-qa-primary" onclick="saveRecord()"><i class="bi bi-save"></i> Save</button>
      </div>
    </div>
  </div>
</div>

<?php
$record_type_js = htmlspecialchars($record_type ?? 'Standards', ENT_QUOTES, 'UTF-8');
$is_standards = ($record_type ?? '') === 'standards' ? 'true' : 'false';
$extra_js = '<script>
$(function(){ buildPagination("paginationContainer", '.$page.', '.$total_pages.', "goPage"); });

function goPage(p){
    const url = new URL(window.location);
    url.searchParams.set("page", p);
    window.location = url;
}

function applyFilters(){
    const url = new URL(window.location);
    url.searchParams.set("status", $("#f-status").val());
    url.searchParams.set("page", 1);
    window.location = url;
}

function openAdd(){
    $("#stdModalTitle").text("Add ' . $record_type_js . '");
    $("#rec_id,#rec_title,#rec_desc,#rec_body,#rec_category,#rec_date,#rec_review").val("");
    $("#rec_status").val("Active");
    $("#reviewCol").toggle(' . $is_standards . ');
}

function editRecord(data){
    $("#stdModalTitle").text("Edit");
    $("#rec_id").val(data[Object.keys(data)[0]]);
    $("#rec_title").val(data.title);
    $("#rec_desc").val(data.description);
    $("#rec_body").val(data.compliance_body || data.owner);
    $("#rec_category").val(data.category);
    $("#rec_status").val(data.status);
    $("#rec_date").val(data.effective_date);
    $("#rec_review").val(data.review_date || data.last_reviewed);
    new bootstrap.Modal(document.getElementById("stdModal")).show();
}

function saveRecord(){
    const title = $("#rec_title").val().trim();
    const body = $("#rec_body").val().trim();
    const category = $("#rec_category").val().trim();
    if(!title || !body || !category){ showToast("Required fields missing","error"); return; }

    const type = $("#rec_type").val() || "standards";
    qaAjax("/qa_system/api/standards.php", {
        action: $("#rec_id").val() ? "update" : "create",
        rec_id: $("#rec_id").val(),
        type: type,
        title, description: $("#rec_desc").val(),
        body: $("#rec_body").val(),
        category: $("#rec_category").val(),
        status: $("#rec_status").val(),
        effective_date: $("#rec_date").val(),
        review_date: $("#rec_review").val()
    }, () => location.reload());
}

function deleteRecord(id){
    confirmDelete("/qa_system/api/standards.php", {action:"delete", rec_id:id}, () => location.reload());
}
</script>';
require_once __DIR__ . '/../includes/footer.php';
?>


