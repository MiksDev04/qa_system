<?php
// pages/records.php â€“ QA Records CRUD
// ByteBandits QA Management System
require_once __DIR__ . '/../config/database.php';
$page_title = 'QA Records';

$conn = getConnection();
$per_page = 10;
$page     = max(1, (int)($_GET['page'] ?? 1));
$ind_filter = (int)($_GET['indicator_id'] ?? 0);
$year_filter = trim($_GET['year'] ?? '');
$sem_filter  = trim($_GET['semester'] ?? '');

$where = ['1=1'];
$params = []; $types = '';
if ($ind_filter) { $where[] = 'r.indicator_id = ?'; $params[] = $ind_filter; $types .= 'i'; }
if ($year_filter) { $where[] = 'r.year = ?'; $params[] = $year_filter; $types .= 'i'; }
if ($sem_filter)  { $where[] = 'r.semester = ?'; $params[] = $sem_filter; $types .= 's'; }
$whereSQL = implode(' AND ', $where);

$count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM qa_records r WHERE $whereSQL");
if ($types) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['c'];
$total_pages = max(1, ceil($total / $per_page));
$offset = ($page-1) * $per_page;

$stmt = $conn->prepare("
    SELECT r.*, i.name as indicator_name, i.target_value, i.unit
    FROM qa_records r
    JOIN qa_indicators i ON r.indicator_id = i.indicator_id
    WHERE $whereSQL
    ORDER BY r.year DESC, r.created_at DESC
    LIMIT ? OFFSET ?
");
$all_params = array_merge($params, [$per_page, $offset]);
$stmt->bind_param($types.'ii', ...$all_params);
$stmt->execute();
$records = $stmt->get_result();

$indicators_list = $conn->query("SELECT indicator_id, name FROM qa_indicators WHERE status='Active' ORDER BY name");
$ind_opts = [];
while ($i = $indicators_list->fetch_assoc()) $ind_opts[] = $i;

$years = $conn->query("SELECT DISTINCT year FROM qa_records ORDER BY year DESC");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-journal-text me-2 text-accent"></i>QA Records</h1>
        <p>Track actual measured values against KPI targets</p>
    </div>
    <button class="btn-qa btn-qa-primary" data-bs-toggle="modal" data-bs-target="#recModal" onclick="openAddRecord()">
        <i class="bi bi-plus-lg"></i> Add Record
    </button>
</div>

<!-- Filters -->
<div class="filter-bar">
    <div style="min-width:200px;flex:1">
        <label class="qa-form-label">Indicator</label>
        <select id="f-ind" class="qa-form-control">
            <option value="">All Indicators</option>
            <?php foreach($ind_opts as $i): ?>
            <option value="<?= $i['indicator_id'] ?>" <?= $ind_filter == $i['indicator_id'] ? 'selected' : '' ?>><?= htmlspecialchars($i['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="min-width:110px">
        <label class="qa-form-label">Year</label>
        <select id="f-year" class="qa-form-control">
            <option value="">All Years</option>
            <?php while($y = $years->fetch_assoc()): ?>
            <option value="<?= $y['year'] ?>" <?= $year_filter == $y['year'] ? 'selected' : '' ?>><?= $y['year'] ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div style="min-width:130px">
        <label class="qa-form-label">Semester</label>
        <select id="f-sem" class="qa-form-control">
            <option value="">All</option>
            <option value="1st" <?= $sem_filter==='1st' ? 'selected':'' ?>>1st Semester</option>
            <option value="2nd" <?= $sem_filter==='2nd' ? 'selected':'' ?>>2nd Semester</option>
            <option value="Annual" <?= $sem_filter==='Annual' ? 'selected':'' ?>>Annual</option>
        </select>
    </div>
    <div style="display:flex;align-items:flex-end;gap:8px">
        <button class="btn-qa btn-qa-primary" onclick="applyFilters()"><i class="bi bi-funnel"></i> Filter</button>
        <a href="records.php" class="btn-qa btn-qa-secondary"><i class="bi bi-x-circle"></i> Clear</a>
    </div>
</div>

<!-- Table -->
<div class="qa-card p-0">
    <div class="qa-table-wrapper table-responsive" style="border:none;border-radius:var(--radius)">
        <table class="table qa-table table-sm align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Indicator</th>
                    <th>Period</th>
                    <th>Actual Value</th>
                    <th>Target</th>
                    <th>Variance</th>
                    <th>Status</th>
                    <th>Recorded By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($records->num_rows === 0): ?>
                <tr><td colspan="9"><div class="empty-state"><i class="bi bi-inbox"></i><p>No records found</p></div></td></tr>
                <?php else: ?>
                <?php $n = $offset+1; while($rec = $records->fetch_assoc()): ?>
                <?php
                    $met = $rec['actual_value'] >= $rec['target_value'];
                    $variance = $rec['actual_value'] - $rec['target_value'];
                    $pct = $rec['target_value'] > 0 ? ($rec['actual_value']/$rec['target_value'])*100 : 0;
                    $barClass = $met ? 'success' : ($pct >= 80 ? 'warning' : 'danger');
                ?>
                <tr>
                    <td class="text-muted-qa mono"><?= $n++ ?></td>
                    <td class="fw-600"><?= htmlspecialchars($rec['indicator_name']) ?></td>
                    <td class="mono"><?= $rec['semester'] ?> <?= $rec['year'] ?></td>
                    <td>
                        <span style="color:var(--<?= $met?'success':'danger' ?>);font-weight:600">
                            <?= number_format($rec['actual_value'],2) ?>
                        </span>
                        <span class="text-muted-qa"> <?= htmlspecialchars($rec['unit']) ?></span>
                        <div class="qa-progress" style="width:90px">
                            <div class="qa-progress-bar <?= $barClass ?>" style="width:<?= min(100,round($pct)) ?>%"></div>
                        </div>
                    </td>
                    <td class="text-muted-qa"><?= number_format($rec['target_value'],2) ?> <?= htmlspecialchars($rec['unit']) ?></td>
                    <td style="color:var(--<?= $variance >= 0 ? 'success':'danger' ?>)">
                        <?= ($variance >= 0 ? '+' : '') . number_format($variance, 2) ?>
                    </td>
                    <td>
                        <span class="badge-status <?= $met ? 'badge-active':'badge-closed' ?>">
                            <?= $met ? 'âœ“ Met':'âœ— Below' ?>
                        </span>
                    </td>
                    <td class="text-muted-qa"><?= htmlspecialchars($rec['recorded_by'] ?? 'â€”') ?></td>
                    <td>
                        <div class="d-flex gap-1">
                            <button class="btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon"
                                onclick='viewRecord(<?= json_encode($rec) ?>)'
                                title="View"><i class="bi bi-eye"></i></button>
                            <button class="btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon"
                                onclick='editRecord(<?= json_encode($rec) ?>)'
                                title="Edit"><i class="bi bi-pencil"></i></button>
                            <button class="btn-qa btn-qa-danger btn-qa-sm btn-qa-icon"
                                onclick="deleteRecord(<?= $rec['record_id'] ?>)"
                                title="Delete"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
    <span class="text-muted-qa">Showing <?= min($offset+1,$total) ?>â€“<?= min($offset+$per_page,$total) ?> of <?= $total ?> records</span>
    <div id="paginationContainer" class="qa-pagination"></div>
</div>

<!-- Modal -->
<div class="modal fade" id="recModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="recModalTitle">Add QA Record</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rec_id">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="qa-form-label">Indicator *</label>
                <select id="rec_indicator" class="qa-form-control">
                    <option value="">Select indicatorâ€¦</option>
                    <?php foreach($ind_opts as $i): ?>
                    <option value="<?= $i['indicator_id'] ?>"><?= htmlspecialchars($i['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="qa-form-label">Year *</label>
                <input type="number" id="rec_year" class="qa-form-control" placeholder="<?= date('Y') ?>" min="2000" max="2099" value="<?= date('Y') ?>">
            </div>
            <div class="col-md-3">
                <label class="qa-form-label">Semester</label>
                <select id="rec_semester" class="qa-form-control">
                    <option value="1st">1st Semester</option>
                    <option value="2nd">2nd Semester</option>
                    <option value="Annual">Annual</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="qa-form-label">Actual Value *</label>
                <input type="number" id="rec_actual" class="qa-form-control" step="0.01" placeholder="0.00">
            </div>
            <div class="col-md-4">
                <label class="qa-form-label">Recorded By</label>
                <input type="text" id="rec_by" class="qa-form-control" placeholder="Name or department" maxlength="100">
            </div>
            <div class="col-12">
                <label class="qa-form-label">Remarks</label>
                <textarea id="rec_remarks" class="qa-form-control" rows="3" placeholder="Notes or observationsâ€¦"></textarea>
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

<!-- View Record Modal -->
<div class="modal fade" id="viewRecModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">QA Record Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
          <div>
            <p class="text-muted-qa mb-1">Indicator</p>
            <p class="fw-600" id="view_rec_indicator"></p>
          </div>
          <div>
            <p class="text-muted-qa mb-1">Period</p>
            <p class="fw-600" id="view_rec_period"></p>
          </div>
          <div>
            <p class="text-muted-qa mb-1">Actual Value</p>
            <p class="fw-600" id="view_rec_actual"></p>
          </div>
          <div>
            <p class="text-muted-qa mb-1">Target Value</p>
            <p class="fw-600 text-muted-qa" id="view_rec_target"></p>
          </div>
          <div>
            <p class="text-muted-qa mb-1">Variance</p>
            <p class="fw-600" id="view_rec_variance"></p>
          </div>
          <div>
            <p class="text-muted-qa mb-1">Status</p>
            <p id="view_rec_status"></p>
          </div>
          <div>
            <p class="text-muted-qa mb-1">Recorded By</p>
            <p class="fw-600" id="view_rec_by"></p>
          </div>
          <div>
            <p class="text-muted-qa mb-1">Date</p>
            <p class="mono text-muted-qa" id="view_rec_date"></p>
          </div>
        </div>
        <div style="margin-top:20px">
          <p class="text-muted-qa mb-1">Remarks</p>
          <p id="view_rec_remarks" style="line-height:1.6"></p>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-qa btn-qa-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php
$extra_js = '<script>
$(function(){ buildPagination("paginationContainer", '.$page.', '.$total_pages.', "goPage"); });

function goPage(p){
    const url = new URL(window.location);
    url.searchParams.set("page", p);
    window.location = url;
}

function applyFilters(){
    const url = new URL(window.location);
    url.searchParams.set("indicator_id", $("#f-ind").val());
    url.searchParams.set("year",         $("#f-year").val());
    url.searchParams.set("semester",     $("#f-sem").val());
    url.searchParams.set("page", 1);
    window.location = url;
}

function openAddRecord(){
    $("#recModalTitle").text("Add QA Record");
    $("#rec_id,#rec_actual,#rec_remarks,#rec_by").val("");
    $("#rec_indicator").val("");
    $("#rec_semester").val("1st");
    $("#rec_year").val(new Date().getFullYear());
}

function editRecord(data){
    $("#recModalTitle").text("Edit QA Record");
    $("#rec_id").val(data.record_id);
    $("#rec_indicator").val(data.indicator_id);
    $("#rec_year").val(data.year);
    $("#rec_semester").val(data.semester);
    $("#rec_actual").val(data.actual_value);
    $("#rec_by").val(data.recorded_by);
    $("#rec_remarks").val(data.remarks);
    new bootstrap.Modal(document.getElementById("recModal")).show();
}

function viewRecord(data){
    $("#view_rec_indicator").text(data.indicator_name);
    $("#view_rec_period").text(data.semester + " " + data.year);
    $("#view_rec_actual").text(data.actual_value + " " + data.unit);
    $("#view_rec_target").text(data.target_value + " " + data.unit);
    const variance = data.actual_value - data.target_value;
    const met = data.actual_value >= data.target_value;
    const varianceText = (variance >= 0 ? "+" : "") + variance.toFixed(2);
    const varianceHTML = met ? `<span style="color: var(--success)">${varianceText}</span>` : `<span style="color: var(--danger)">${varianceText}</span>`;
    $("#view_rec_variance").html(varianceHTML);
    const statusBadge = met ? `<span class="badge-status badge-active">âœ“ Met Target</span>` : `<span class="badge-status badge-closed">âœ— Below Target</span>`;
    $("#view_rec_status").html(statusBadge);
    $("#view_rec_by").text(data.recorded_by || "â€”");
    const dateStr = new Date(data.created_at).toLocaleDateString("en-US", {year: "numeric", month: "short", day: "numeric", hour: "2-digit", minute: "2-digit"});
    $("#view_rec_date").text(dateStr);
    $("#view_rec_remarks").text(data.remarks || "â€”");
    new bootstrap.Modal(document.getElementById("viewRecModal")).show();
}

function saveRecord(){
    const indicator_id = $("#rec_indicator").val();
    const actual_value = $("#rec_actual").val();
    const year = $("#rec_year").val();
    if(!indicator_id || !actual_value || !year){ showToast("Indicator, Year, and Actual Value are required.","error"); return; }

    qaAjax("/qa_system/api/records.php", {
        action: $("#rec_id").val() ? "update" : "create",
        record_id: $("#rec_id").val(),
        indicator_id, year,
        semester: $("#rec_semester").val(),
        actual_value,
        recorded_by: $("#rec_by").val(),
        remarks: $("#rec_remarks").val()
    }, () => location.reload());
}

function deleteRecord(id){
    confirmDelete("/qa_system/api/records.php", {action:"delete", record_id:id}, () => location.reload());
}
</script>';
require_once __DIR__ . '/../includes/footer.php';
?>


