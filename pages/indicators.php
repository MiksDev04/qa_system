<?php
// pages/indicators.php â€“ QA Indicators CRUD
// ByteBandits QA Management System
require_once __DIR__ . '/../config/database.php';
$page_title = 'KPI Indicators';

$conn = getConnection();
$per_page = 10;
$page     = max(1, (int)($_GET['page'] ?? 1));
$search   = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$status   = trim($_GET['status'] ?? '');

// Build WHERE
$where = ['1=1'];
$params = [];
$types  = '';
if ($search !== '') { $where[] = '(name LIKE ? OR description LIKE ?)'; $s = "%$search%"; $params[] = $s; $params[] = $s; $types .= 'ss'; }
if ($category !== '') { $where[] = 'category = ?'; $params[] = $category; $types .= 's'; }
if ($status !== '') { $where[] = 'status = ?'; $params[] = $status; $types .= 's'; }
$whereSQL = implode(' AND ', $where);

// Count
$count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM qa_indicators WHERE $whereSQL");
if ($types) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['c'];
$total_pages = max(1, ceil($total / $per_page));
$offset = ($page - 1) * $per_page;

// Fetch
$stmt = $conn->prepare("SELECT * FROM qa_indicators WHERE $whereSQL ORDER BY category, name LIMIT ? OFFSET ?");
$all_params  = array_merge($params, [$per_page, $offset]);
$all_types   = $types . 'ii';
$stmt->bind_param($all_types, ...$all_params);
$stmt->execute();
$indicators = $stmt->get_result();

// Categories list
$cats = $conn->query("SELECT DISTINCT category FROM qa_indicators ORDER BY category");
$categories = [];
while ($c = $cats->fetch_assoc()) $categories[] = $c['category'];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-speedometer2 me-2 text-accent"></i>KPI Indicators</h1>
        <p>Define and manage quality performance indicators and targets</p>
    </div>
    <button class="btn-qa btn-qa-primary" data-bs-toggle="modal" data-bs-target="#indModal" onclick="openAdd()">
        <i class="bi bi-plus-lg"></i> Add Indicator
    </button>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <div style="flex:1;min-width:200px">
        <label class="qa-form-label">Search</label>
        <input type="text" id="f-search" class="qa-form-control" placeholder="Search indicators" value="<?= htmlspecialchars($search) ?>">
    </div>
    <div style="min-width:150px">
        <label class="qa-form-label">Category</label>
        <select id="f-category" class="qa-form-control">
            <option value="">All Categories</option>
            <?php foreach($categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="min-width:130px">
        <label class="qa-form-label">Status</label>
        <select id="f-status" class="qa-form-control">
            <option value="">All</option>
            <option value="Active"   <?= $status === 'Active'   ? 'selected' : '' ?>>Active</option>
            <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
    </div>
    <div style="display:flex;align-items:flex-end;gap:8px">
        <button class="btn-qa btn-qa-primary" onclick="applyFilters()"><i class="bi bi-funnel"></i> Filter</button>
        <a href="indicators.php" class="btn-qa btn-qa-secondary"><i class="bi bi-x-circle"></i> Clear</a>
    </div>
</div>

<!-- Table -->
<div class="qa-card p-0">
    <div class="qa-table-wrapper table-responsive" style="border:none;border-radius:var(--radius)">
        <table class="table qa-table table-sm align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Indicator Name</th>
                    <th>Category</th>
                    <th>Target</th>
                    <th>Unit</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($indicators->num_rows === 0): ?>
                <tr><td colspan="7"><div class="empty-state"><i class="bi bi-inbox"></i><p>No indicators found</p></div></td></tr>
                <?php else: ?>
                <?php $n = $offset + 1; while ($ind = $indicators->fetch_assoc()): ?>
                <tr>
                    <td class="text-muted-qa mono"><?= $n++ ?></td>
                    <td>
                        <div class="fw-600"><?= htmlspecialchars($ind['name']) ?></div>
                        <?php if ($ind['description']): ?>
                        <div class="text-muted-qa" style="font-size:0.78rem;margin-top:2px"><?= htmlspecialchars(substr($ind['description'], 0, 80)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge-status badge-pending"><?= htmlspecialchars($ind['category']) ?></span></td>
                    <td class="fw-600"><?= number_format($ind['target_value'], 2) ?></td>
                    <td class="mono"><?= htmlspecialchars($ind['unit']) ?></td>
                    <td>
                        <span class="badge-status <?= $ind['status']==='Active' ? 'badge-active' : 'badge-inactive' ?>">
                            <?= $ind['status'] ?>
                        </span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon"
                                onclick='viewIndicator(<?= json_encode($ind) ?>)'
                                title="View">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon"
                                onclick='editIndicator(<?= json_encode($ind) ?>)'
                                title="Edit">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn-qa btn-qa-danger btn-qa-sm btn-qa-icon"
                                onclick="deleteIndicator(<?= $ind['indicator_id'] ?>)"
                                title="Delete">
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

<!-- Pagination info -->
<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
    <span class="text-muted-qa">Showing <?= min($offset+1,$total) ?>â€“<?= min($offset+$per_page,$total) ?> of <?= $total ?> indicators</span>
    <div id="paginationContainer" class="qa-pagination"></div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="indModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="indModalTitle">Add Indicator</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="ind_id">
        <div class="row g-3">
            <div class="col-12">
                <label class="qa-form-label">Indicator Name *</label>
                <input type="text" id="ind_name" class="qa-form-control" placeholder="e.g. Board Exam Passing Rate" maxlength="150">
            </div>
            <div class="col-12">
                <label class="qa-form-label">Description</label>
                <textarea id="ind_desc" class="qa-form-control" placeholder="Describe what this indicator measuresâ€¦" rows="3"></textarea>
            </div>
            <div class="col-md-4">
                <label class="qa-form-label">Target Value *</label>
                <input type="number" id="ind_target" class="qa-form-control" step="0.01" placeholder="80.00">
            </div>
            <div class="col-md-4">
                <label class="qa-form-label">Unit</label>
                <select id="ind_unit" class="qa-form-control">
                    <option value="">Select Unit</option>
                    <option value="%">Percentage (%)</option>
                    <option value="score">Score</option>
                    <option value="count">Count</option>
                    <option value="rate">Rate</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="qa-form-label">Category</label>
                <select id="ind_category" class="qa-form-control">
                    <option value="">Select Category</option>
                    <option value="Academic">Academic</option>
                    <option value="Faculty">Faculty</option>
                    <option value="Research">Research</option>
                    <option value="Student Life">Student Life</option>
                    <option value="Industry">Industry</option>
                    <option value="Operations">Operations</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="qa-form-label">Status</label>
                <select id="ind_status" class="qa-form-control">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-qa btn-qa-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn-qa btn-qa-primary" onclick="saveIndicator()"><i class="bi bi-save"></i> Save</button>
      </div>
    </div>
  </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewIndModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Indicator Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
          <div>
            <p class="text-muted-qa mb-1">Indicator Name</p>
            <p class="fw-600" id="view_ind_name"></p>
          </div>
          <div>
            <p class="text-muted-qa mb-1">Category</p>
            <p class="fw-600" id="view_ind_category"></p>
          </div>
          <div>
            <p class="text-muted-qa mb-1">Target Value</p>
            <p class="fw-600" id="view_ind_target"></p>
          </div>
          <div>
            <p class="text-muted-qa mb-1">Unit</p>
            <p class="fw-600" id="view_ind_unit"></p>
          </div>
          <div>
            <p class="text-muted-qa mb-1">Status</p>
            <p id="view_ind_status"></p>
          </div>
          <div>
            <p class="text-muted-qa mb-1">Created</p>
            <p class="mono text-muted-qa" id="view_ind_created"></p>
          </div>
        </div>
        <div style="margin-top:20px">
          <p class="text-muted-qa mb-1">Description</p>
          <p id="view_ind_desc" style="line-height:1.6"></p>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-qa btn-qa-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php
$cp = $page; $tp = $total_pages;
$extra_js = '<script>
const currentPage = ' . $cp . ';
const totalPages  = ' . $tp . ';

$(function(){
    buildPagination("paginationContainer", currentPage, totalPages, "goPage");
});

function goPage(p) {
    const url = new URL(window.location);
    url.searchParams.set("page", p);
    window.location = url;
}

function applyFilters() {
    const url = new URL(window.location);
    url.searchParams.set("search",   $("#f-search").val());
    url.searchParams.set("category", $("#f-category").val());
    url.searchParams.set("status",   $("#f-status").val());
    url.searchParams.set("page", 1);
    window.location = url;
}

function openAdd() {
    $("#indModalTitle").text("Add Indicator");
    $("#ind_id,#ind_name,#ind_desc,#ind_target,#ind_unit,#ind_category").val("");
    $("#ind_status").val("Active");
}

function editIndicator(data) {
    $("#indModalTitle").text("Edit Indicator");
    $("#ind_id").val(data.indicator_id);
    $("#ind_name").val(data.name);
    $("#ind_desc").val(data.description);
    $("#ind_target").val(data.target_value);
    $("#ind_unit").val(data.unit);
    $("#ind_category").val(data.category);
    $("#ind_status").val(data.status);
    new bootstrap.Modal(document.getElementById("indModal")).show();
}

function viewIndicator(data) {
    $("#view_ind_name").text(data.name);
    $("#view_ind_category").text(data.category);
    $("#view_ind_target").text(data.target_value + " " + data.unit);
    $("#view_ind_unit").text(data.unit);
    $("#view_ind_desc").text(data.description || "â€”");
    const statusBadge = data.status === "Active" ? "<span class=\"badge-status badge-active\">Active</span>" : "<span class=\"badge-status badge-inactive\">Inactive</span>";
    $("#view_ind_status").html(statusBadge);
    const dateStr = new Date(data.created_at).toLocaleDateString("en-US", {year: "numeric", month: "short", day: "numeric", hour: "2-digit", minute: "2-digit"});
    $("#view_ind_created").text(dateStr);
    new bootstrap.Modal(document.getElementById("viewIndModal")).show();
}

function saveIndicator() {
    const name = $("#ind_name").val().trim();
    const target = $("#ind_target").val().trim();
    if (!name || !target) { showToast("Name and Target are required.", "error"); return; }

    qaAjax("/qa_system/api/indicators.php", {
        action: $("#ind_id").val() ? "update" : "create",
        indicator_id: $("#ind_id").val(),
        name, description: $("#ind_desc").val(),
        target_value: target,
        unit: $("#ind_unit").val(),
        category: $("#ind_category").val(),
        status: $("#ind_status").val()
    }, () => { location.reload(); });
}

function deleteIndicator(id) {
    confirmDelete("/qa_system/api/indicators.php", { action:"delete", indicator_id: id }, () => location.reload());
}
</script>';
require_once __DIR__ . '/../includes/footer.php';
?>


