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
$params = [];
$types = '';
if ($ind_filter) {
    $where[] = 'r.indicator_id = ?';
    $params[] = $ind_filter;
    $types .= 'i';
}
if ($year_filter) {
    $where[] = 'r.year = ?';
    $params[] = $year_filter;
    $types .= 'i';
}
if ($sem_filter) {
    $where[] = 'r.semester = ?';
    $params[] = $sem_filter;
    $types .= 's';
}
$whereSQL = implode(' AND ', $where);

$count_stmt = $conn->prepare("SELECT COUNT(*) as c FROM qa_records r WHERE $whereSQL");
if ($types) $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['c'];
$total_pages = max(1, ceil($total / $per_page));
$offset = ($page - 1) * $per_page;

$stmt = $conn->prepare("
    SELECT r.*, i.name as indicator_name, i.target_value, i.unit
    FROM qa_records r
    JOIN qa_indicators i ON r.indicator_id = i.indicator_id
    WHERE $whereSQL
    ORDER BY r.year DESC, r.created_at DESC
    LIMIT ? OFFSET ?
");
$all_params = array_merge($params, [$per_page, $offset]);
$stmt->bind_param($types . 'ii', ...$all_params);
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
            <?php foreach ($ind_opts as $i): ?>
                <option value="<?= $i['indicator_id'] ?>" <?= $ind_filter == $i['indicator_id'] ? 'selected' : '' ?>><?= htmlspecialchars($i['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div style="min-width:110px">
        <label class="qa-form-label">Year</label>
        <select id="f-year" class="qa-form-control">
            <option value="">All Years</option>
            <?php while ($y = $years->fetch_assoc()): ?>
                <option value="<?= $y['year'] ?>" <?= $year_filter == $y['year'] ? 'selected' : '' ?>><?= $y['year'] ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div style="min-width:130px">
        <label class="qa-form-label">Semester</label>
        <select id="f-sem" class="qa-form-control">
            <option value="">All</option>
            <option value="1st" <?= $sem_filter === '1st' ? 'selected' : '' ?>>1st Semester</option>
            <option value="2nd" <?= $sem_filter === '2nd' ? 'selected' : '' ?>>2nd Semester</option>
            <option value="Annual" <?= $sem_filter === 'Annual' ? 'selected' : '' ?>>Annual</option>
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
                    <tr>
                        <td colspan="9">
                            <div class="empty-state"><i class="bi bi-inbox"></i>
                                <p>No records found</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $n = $offset + 1;
                    while ($rec = $records->fetch_assoc()): ?>
                        <?php
                        $met = $rec['actual_value'] >= $rec['target_value'];
                        $variance = $rec['actual_value'] - $rec['target_value'];
                        $pct = $rec['target_value'] > 0 ? ($rec['actual_value'] / $rec['target_value']) * 100 : 0;
                        if ($rec['target_value'] <= 0) {
                            $statusClass = 'badge-inactive';
                            $statusIcon = 'bi-dash-circle-fill';
                            $statusText = 'No Target';
                            $barClass = 'warning';
                        } elseif ($pct >= 100) {
                            $statusClass = 'badge-active';
                            $statusIcon = 'bi-check-circle-fill';
                            $statusText = 'Met Target';
                            $barClass = 'success';
                        } else {
                            $statusClass = 'badge-closed';
                            $statusIcon = 'bi-x-circle-fill';
                            $statusText = 'Below Target';
                            $barClass = 'danger';
                        }
                        ?>
                        <tr>
                            <td class="text-muted-qa mono"><?= $n++ ?></td>
                            <td class="fw-600"><?= htmlspecialchars($rec['indicator_name']) ?></td>
                            <td class="mono"><?= $rec['semester'] ?> <?= $rec['year'] ?></td>
                            <td>
                                <span style="color:var(--<?= $met ? 'success' : 'danger' ?>);font-weight:600">
                                    <?= number_format($rec['actual_value'], 2) ?>
                                </span>
                                <span class="text-muted-qa"> <?= htmlspecialchars($rec['unit']) ?></span>
                                <div class="qa-progress" style="width:90px">
                                    <div class="qa-progress-bar <?= $barClass ?>" style="width:<?= min(100, round($pct)) ?>%"></div>
                                </div>
                            </td>
                            <td class="text-muted-qa"><?= number_format($rec['target_value'], 2) ?> <?= htmlspecialchars($rec['unit']) ?></td>
                            <td style="color:var(--<?= $variance >= 0 ? 'success' : 'danger' ?>)">
                                <?= ($variance >= 0 ? '+' : '') . number_format($variance, 2) ?>
                            </td>
                            <td>
                                <span class="badge-status <?= $statusClass ?>">
                                    <i class="bi <?= $statusIcon ?>"></i> <?= $statusText ?>
                                </span>
                            </td>
                            <td class="text-muted-qa"><?= htmlspecialchars($rec['recorded_by'] ?? 'N/A') ?></td>
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
    <span class="text-muted-qa">Showing <?= min($offset + 1, $total) ?>-<?= min($offset + $per_page, $total) ?> of <?= $total ?> records</span>
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

                <!-- Tab Navigation -->
                <ul class="nav nav-tabs mb-3" id="recTabNav" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual-entry" type="button" role="tab">
                            <i class="bi bi-pencil-fill"></i> Manual Entry
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="external-tab" data-bs-toggle="tab" data-bs-target="#external-data" type="button" role="tab">
                            <i class="bi bi-cloud-download"></i> From External Systems
                        </button>
                    </li>
                </ul>

                <!-- Manual Entry Tab -->
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="manual-entry" role="tabpanel">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="qa-form-label">Indicator *</label>
                                <select id="rec_indicator" class="qa-form-control">
                                    <option value="">Select indicator</option>
                                    <?php foreach ($ind_opts as $i): ?>
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
                                <textarea id="rec_remarks" class="qa-form-control" rows="3" placeholder="Notes or observations"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- External Data Tab -->
                    <div class="tab-pane fade" id="external-data" role="tabpanel">
                        <div class="alert alert-info" role="alert">
                            <i class="bi bi-info-circle"></i> <strong>Automated Data Sync</strong><br>
                            Select data from integrated systems (LMS, HRIS, Faculty Evaluation). These values are pre-populated automatically.
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="qa-form-label">Select Data Source</label>
                                <select id="ext_source" class="qa-form-control" onchange="loadExternalData()">
                                    <option value="">-- Choose System --</option>
                                    <option value="LMS">Learning Management System (LMS)</option>
                                    <option value="HRIS">Human Resources (HRIS)</option>
                                    <option value="FACULTY_EVAL">Faculty Evaluation</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="qa-form-label">Select Metric</label>
                                <select id="ext_metric" class="qa-form-control" onchange="populateExternalValue()">
                                    <option value="">-- Choose Metric --</option>
                                </select>
                            </div>
                        </div>

                        <div id="ext_data_container" style="display:none">
                            <div class="qa-card p-3 mb-3" style="background:var(--bg-light)">
                                <p class="text-muted-qa mb-1">Available Data</p>
                                <table class="table table-sm mb-0" id="ext_data_table">
                                    <thead>
                                        <tr>
                                            <th>Period</th>
                                            <th>Value</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ext_data_body">
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="qa-form-label">Indicator *</label>
                                <select id="ext_indicator" class="qa-form-control">
                                    <option value="">Select indicator</option>
                                    <?php foreach ($ind_opts as $i): ?>
                                        <option value="<?= $i['indicator_id'] ?>"><?= htmlspecialchars($i['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="qa-form-label">Year *</label>
                                <input type="number" id="ext_year" class="qa-form-control" min="2000" max="2099">
                            </div>
                            <div class="col-md-3">
                                <label class="qa-form-label">Semester</label>
                                <select id="ext_semester" class="qa-form-control">
                                    <option value="1st">1st Semester</option>
                                    <option value="2nd">2nd Semester</option>
                                    <option value="Annual">Annual</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="qa-form-label">Auto-Populated Value</label>
                                <input type="number" id="ext_actual" class="qa-form-control" step="0.01" placeholder="0.00" readonly style="background-color:var(--bg-light)">
                            </div>
                        </div>
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
$extra_js = <<<JS
<script>
// External Data JSON definitions
const externalDataSources = {
    LMS: {
        system: "Learning Management System (LMS)",
        data: [],
        kpi_mapping: {
            "avg_grade": { indicator_id: 1, unit: "%", target_value: 85.00 },
            "submission_rate": { indicator_id: 6, unit: "%", target_value: 90.00 },
            "quiz_pass_rate": { indicator_id: 8, unit: "%", target_value: 85.00 }
        }
    },
    HRIS: {
        system: "Human Resources Information System (HRIS)",
        data: [
            {
                "academic_period": "2024-1st",
                "year": 2024,
                "semester": "1st",
                "metrics": {
                    "faculty_evaluation_average": 82.50,
                    "research_publications_count": 8
                },
                "remarks": "Sample data"
            }
        ],
        kpi_mapping: {
            "faculty_evaluation_average": { indicator_id: 4, unit: "%", target_value: 85.00 },
            "research_publications_count": { indicator_id: 5, unit: "count", target_value: 10.00 }
        }
    },
    FACULTY_EVAL: {
        system: "Faculty Evaluation & Performance System",
        data: [
            {
                "academic_period": "2024-1st",
                "year": 2024,
                "semester": "1st",
                "metrics": {
                    "faculty_evaluation_average": 82.80,
                    "employer_satisfaction_with_graduates": 79.40
                },
                "remarks": "Sample data"
            }
        ],
        kpi_mapping: {
            "faculty_evaluation_average": { indicator_id: 4, unit: "%", target_value: 85.00 },
            "employer_satisfaction_with_graduates": { indicator_id: 7, unit: "%", target_value: 80.00 }
        }
    }
};

function resetModalForm() {
    document.getElementById('rec_id').value = '';
    document.getElementById('rec_indicator').value = '';
    document.getElementById('rec_actual').value = '';
    document.getElementById('rec_remarks').value = '';
    document.getElementById('rec_by').value = '';
    document.getElementById('rec_semester').value = '1st';
    document.getElementById('rec_year').value = new Date().getFullYear();
}
$(function(){ 
    buildPagination("paginationContainer", $page, $total_pages, "goPage"); 
    
    // Fix modal backdrop
    $('#recModal').on('hidden.bs.modal', function (e) {
        if (e.target) {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }
        resetModalForm();
    });
});

function goPage(p){
    const url = new URL(window.location);
    url.searchParams.set("page", p);
    window.location = url;
}

function applyFilters(){
    const url = new URL(window.location);
    url.searchParams.set("indicator_id", $("#f-ind").val());
    url.searchParams.set("year", $("#f-year").val());
    url.searchParams.set("semester", $("#f-sem").val());
    url.searchParams.set("page", 1);
    window.location = url;
}

function openAddRecord(){
    resetModalForm();
    document.getElementById("recModalTitle").innerText = "Add QA Record";
    var myModal = new bootstrap.Modal(document.getElementById('recModal'));
    myModal.show();
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
    const varianceText = (variance >= 0 ? "+" : "") + variance.toFixed(2);
    const varianceHTML = variance >= 0 ? `<span style="color: var(--success)">\${varianceText}</span>` : `<span style="color: var(--danger)">\${varianceText}</span>`;
    $("#view_rec_variance").html(varianceHTML);

    const target = Number(data.target_value || 0);
    const actual = Number(data.actual_value || 0);
    const pct = target > 0 ? (actual / target) * 100 : 0;
    let statusBadge = "";
    if (target <= 0) {
        statusBadge = `<span class="badge-status badge-inactive"><i class="bi bi-dash-circle-fill"></i> No Target</span>`;
    } else if (pct >= 100) {
        statusBadge = `<span class="badge-status badge-active"><i class="bi bi-check-circle-fill"></i> Met Target</span>`;
    } else {
        statusBadge = `<span class="badge-status badge-closed"><i class="bi bi-x-circle-fill"></i> Below Target</span>`;
    }

    $("#view_rec_status").html(statusBadge);
    $("#view_rec_by").text(data.recorded_by || "N/A");
    const dateStr = new Date(data.created_at).toLocaleDateString("en-US", {year: "numeric", month: "short", day: "numeric", hour: "2-digit", minute: "2-digit"});
    $("#view_rec_date").text(dateStr);
    $("#view_rec_remarks").text(data.remarks || "N/A");
    new bootstrap.Modal(document.getElementById("viewRecModal")).show();
}

function saveRecord(){
    const activeTab = document.querySelector('.tab-pane.active');
    const isManual = activeTab.id === 'manual-entry';
    
    let indicator_id, actual_value, year, semester, recorded_by, remarks;
    
    if (isManual) {
        indicator_id = $("#rec_indicator").val();
        actual_value = $("#rec_actual").val();
        year = $("#rec_year").val();
        semester = $("#rec_semester").val();
        recorded_by = $("#rec_by").val() || "QA System";
        remarks = $("#rec_remarks").val() || "";
    } else {
        indicator_id = $("#ext_indicator").val();
        actual_value = $("#ext_actual").val();
        year = $("#ext_year").val();
        semester = $("#ext_semester").val();
        recorded_by = "QA System";
        remarks = "";
    }
    
    if(!indicator_id) { 
        alert("Please select an Indicator."); 
        return; 
    }
    if(!year || year < 2000 || year > 2099) { 
        alert("Please enter a valid Year."); 
        return; 
    }
    if(!actual_value && actual_value !== 0) { 
        alert("Please enter an Actual Value."); 
        return; 
    }

    const isUpdate = $("#rec_id").val();
    
    const payload = {
        action: isUpdate ? "update" : "create",
        indicator_id: indicator_id,
        year: year,
        semester: semester,
        actual_value: actual_value,
        recorded_by: recorded_by,
        remarks: remarks
    };
    
    if (isUpdate) {
        payload.record_id = $("#rec_id").val();
    }
    
    console.log("Saving record:", payload);
    
    qaAjax("../api/records.php", payload, 
    function(res) {
        console.log("Success response:", res);
        location.reload();
    },
    function(err) {
        console.error("Error response object:", err);
        let errMsg = "Failed to save record.";
        if (err && typeof err === 'object' && err.message) {
            errMsg = err.message;
        }
        alert(errMsg);
    });
}

function deleteRecord(id){
    confirmDelete("../api/records.php", {action:"delete", record_id:id}, () => location.reload());
}

async function loadExternalData() {
    const source = $("#ext_source").val();
    if (!source) {
        $("#ext_metric").html("<option value=''>-- Choose Metric --</option>");
        $("#ext_data_container").hide();
        $("#ext_indicator").val("");
        $("#ext_year").val("");
        $("#ext_semester").val("1st");
        $("#ext_actual").val("");
        return;
    }

    if (source !== 'LMS') {
        const sourceData = externalDataSources[source];
        if (!sourceData || !sourceData.data || sourceData.data.length === 0) {
            alert("No data available for this system.");
            return;
        }
        
        const metricSet = new Set();
        sourceData.data.forEach(record => {
            Object.keys(record.metrics).forEach(metric => metricSet.add(metric));
        });
        
        let options = '<option value="">-- Choose Metric --</option>';
        metricSet.forEach(metric => {
            const displayName = metric.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            options += `<option value="\${metric}">\${displayName}</option>`;
        });
        $("#ext_metric").html(options);
        $("#ext_data_container").hide();
        return;
    }
    
    try {
        $("#ext_metric").html('<option value="">Loading LMS data...</option>');
        
        const response = await fetch('../api/fetch_lms_performance.php?action=get_overview');
        
        if (!response.ok) {
            throw new Error(`HTTP \${response.status}: \${response.statusText}`);
        }
        
        const result = await response.json();
        console.log("LMS API Response:", result);
        
        if (result.status === 'success' && result.data) {
            const apiData = result.data;
            const currentYear = new Date().getFullYear();
            const lmsDataRecord = [{
                academic_period: currentYear + "-1st",
                year: currentYear,
                semester: "1st",
                metrics: {
                    avg_grade: parseFloat(apiData.avg_grade) || 0,
                    submission_rate: parseFloat(apiData.submission_rate) || 0,
                    quiz_pass_rate: parseFloat(apiData.quiz_pass_rate) || 0,
                    quiz_attempts: parseInt(apiData.quiz_attempts) || 0,
                    total_students: parseInt(apiData.total_students) || 0,
                    total_expected: parseInt(apiData.total_expected) || 0,
                    total_submitted: parseInt(apiData.total_submitted) || 0,
                    total_tasks: parseInt(apiData.total_tasks) || 0,
                    total_quizzes: parseInt(apiData.total_quizzes) || 0,
                    total_classes: parseInt(apiData.total_classes) || 0,
                    quiz_passed: parseInt(apiData.quiz_passed) || 0
                },
                remarks: "Real-time data from ArtisansLMS"
            }];
            
            externalDataSources.LMS.data = lmsDataRecord;
            
            const metricSet = new Set(Object.keys(lmsDataRecord[0].metrics));
            let options = '<option value="">-- Choose Metric --</option>';
            metricSet.forEach(metric => {
                const displayName = metric.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                options += `<option value="\${metric}">\${displayName}</option>`;
            });
            $("#ext_metric").html(options);
            $("#ext_data_container").hide();
            console.log("LMS data loaded successfully");
        } else {
            const errorMsg = result.message || "Invalid response format";
            console.error("LMS API error:", errorMsg);
            $("#ext_metric").html('<option value="">-- Choose Metric --</option>');
            alert("Failed to fetch LMS data: " + errorMsg);
        }
    } catch (error) {
        console.error("Error fetching LMS data:", error);
        $("#ext_metric").html('<option value="">-- Choose Metric --</option>');
        alert("Error connecting to ArtisansLMS API: " + error.message);
    }
}

function populateExternalValue() {
    const source = $("#ext_source").val();
    const metric = $("#ext_metric").val();

    if (!source || !metric) {
        $("#ext_data_container").hide();
        return;
    }

    const sourceData = externalDataSources[source];
    if (!sourceData || !sourceData.data) return;

    const matchingRecords = [];
    sourceData.data.forEach(record => {
        if (record.metrics.hasOwnProperty(metric)) {
            const value = record.metrics[metric];
            const mapping = sourceData.kpi_mapping && sourceData.kpi_mapping[metric];
            matchingRecords.push({
                year: record.year,
                semester: record.semester,
                actual_value: value,
                academic_period: record.academic_period,
                indicator_id: mapping ? mapping.indicator_id : null,
                unit: mapping ? mapping.unit : "",
                target_value: mapping ? mapping.target_value : null
            });
        }
    });

    if (matchingRecords.length === 0) {
        $("#ext_data_container").hide();
        return;
    }

    window.externalDataRows = matchingRecords;

    let html = "";
    matchingRecords.forEach((rec, idx) => {
        html += '<tr>';
        html += '<td>' + rec.academic_period + '</td>';
        html += '<td>' + (typeof rec.actual_value === 'number' ? rec.actual_value.toFixed(2) : rec.actual_value) + '</td>';
        html += '<td><button class="btn-qa btn-qa-sm btn-qa-primary select-external-btn" data-idx="' + idx + '"><i class="bi bi-check"></i> Select</button></td>';
        html += '</tr>';
    });

    $("#ext_data_body").html(html);
    $("#ext_data_container").show();
}

function selectExternalData(index) {
    const data = window.externalDataRows[index];
    if (!data) return;

    $("#ext_year").val(data.year);
    $("#ext_semester").val(data.semester);
    $("#ext_actual").val(data.actual_value);

    if (data.indicator_id) {
        $("#ext_indicator").val(data.indicator_id);
    } else {
        $("#ext_indicator").val("");
    }

    document.getElementById("ext_indicator").scrollIntoView({ behavior: "smooth" });
}

$(document).on('click', '.select-external-btn', function() {
    const idx = $(this).data('idx');
    if (idx !== undefined && window.externalDataRows && window.externalDataRows[idx]) {
        selectExternalData(idx);
    }
});
</script>
JS;
require_once __DIR__ . '/../includes/footer.php';
?>