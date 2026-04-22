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
    <span class="text-muted-qa">Showing <?= min($offset+1,$total) ?>-<?= min($offset+$per_page,$total) ?> of <?= $total ?> records</span>
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
                    <?php foreach($ind_opts as $i): ?>
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
        data: [
            {
                "academic_period": "2024-1st",
                "year": 2024,
                "semester": "1st",
                "metrics": {
                    "course_completion_rate": 88.50,
                    "student_enrollment": 1250,
                    "courses_completed": 1107,
                    "courses_failed": 143,
                    "dropout_rate": 5.20,
                    "average_gpa": 2.98,
                    "pass_rate": 81.20,
                    "distinction_rate": 12.40,
                    "credit_hours_earned": 34500
                },
                "remarks": "Slight improvement in completion rate due to enhanced tutoring support"
            },
            {
                "academic_period": "2024-2nd",
                "year": 2024,
                "semester": "2nd",
                "metrics": {
                    "course_completion_rate": 90.75,
                    "student_enrollment": 1260,
                    "courses_completed": 1143,
                    "courses_failed": 117,
                    "dropout_rate": 4.80,
                    "average_gpa": 3.02,
                    "pass_rate": 82.80,
                    "distinction_rate": 13.20,
                    "credit_hours_earned": 35700
                },
                "remarks": "Strong performance with reduced dropout rate"
            },
            {
                "academic_period": "2024-Annual",
                "year": 2024,
                "semester": "Annual",
                "metrics": {
                    "course_completion_rate": 89.63,
                    "total_students": 1255,
                    "total_courses_completed": 2250,
                    "total_failed": 260,
                    "annual_dropout_rate": 5.00,
                    "annual_average_gpa": 3.00,
                    "annual_pass_rate": 82.00,
                    "graduation_rate": 73.50,
                    "retained_students": 1256,
                    "graduates": 921,
                    "student_satisfaction_score": 4.15
                },
                "remarks": "Annual consolidation of LMS metrics"
            },
            {
                "academic_period": "2025-1st",
                "year": 2025,
                "semester": "1st",
                "metrics": {
                    "course_completion_rate": 91.20,
                    "student_enrollment": 1280,
                    "courses_completed": 1166,
                    "courses_failed": 114,
                    "dropout_rate": 4.50,
                    "average_gpa": 3.05,
                    "pass_rate": 83.50,
                    "distinction_rate": 14.10,
                    "credit_hours_earned": 36000
                },
                "remarks": "Continued improvement with new learning interventions"
            },
            {
                "academic_period": "2025-2nd",
                "year": 2025,
                "semester": "2nd",
                "metrics": {
                    "course_completion_rate": 92.10,
                    "student_enrollment": 1290,
                    "courses_completed": 1187,
                    "courses_failed": 103,
                    "dropout_rate": 4.30,
                    "average_gpa": 3.08,
                    "pass_rate": 84.20,
                    "distinction_rate": 14.80,
                    "credit_hours_earned": 36500
                },
                "remarks": "Strong performance trend continues"
            }
        ],
        kpi_mapping: {
            "course_completion_rate": { indicator_id: 8, unit: "%", target_value: 90.00 },
            "dropout_rate": { indicator_id: 6, unit: "%", target_value: 5.00 },
            "pass_rate": { indicator_id: 1, unit: "%", target_value: 80.00 },
            "graduation_rate": { indicator_id: 2, unit: "%", target_value: 75.00 },
            "student_satisfaction_score": { indicator_id: 3, unit: "score", target_value: 4.00 }
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
                    "total_faculty": 145,
                    "faculty_evaluation_average": 82.50,
                    "faculty_satisfaction_score": 78.30,
                    "research_publications_count": 8,
                    "conference_presentations": 12,
                    "grant_applications": 5,
                    "grant_awards": 2,
                    "research_projects_ongoing": 18,
                    "faculty_training_hours": 450,
                    "professional_development_participants": 98,
                    "faculty_turnover_rate": 3.50,
                    "new_faculty_hired": 4,
                    "faculty_retired": 2
                },
                "remarks": "Good research output; professional development strong"
            },
            {
                "academic_period": "2024-2nd",
                "year": 2024,
                "semester": "2nd",
                "metrics": {
                    "total_faculty": 147,
                    "faculty_evaluation_average": 84.10,
                    "faculty_satisfaction_score": 79.50,
                    "research_publications_count": 10,
                    "conference_presentations": 14,
                    "grant_applications": 7,
                    "grant_awards": 3,
                    "research_projects_ongoing": 20,
                    "faculty_training_hours": 520,
                    "professional_development_participants": 112,
                    "faculty_turnover_rate": 2.80,
                    "new_faculty_hired": 3,
                    "faculty_retired": 1
                },
                "remarks": "Improved faculty evaluation scores and research activity"
            },
            {
                "academic_period": "2024-Annual",
                "year": 2024,
                "semester": "Annual",
                "metrics": {
                    "total_faculty": 146,
                    "faculty_evaluation_average": 83.30,
                    "faculty_satisfaction_score": 78.90,
                    "research_publications_count": 18,
                    "conference_presentations": 26,
                    "grant_applications": 12,
                    "grant_awards": 5,
                    "research_projects_total": 38,
                    "faculty_training_hours": 970,
                    "professional_development_participants": 210,
                    "faculty_turnover_rate": 3.15,
                    "new_faculty_hired": 7,
                    "faculty_retired": 3,
                    "employee_retention_rate": 96.85
                },
                "remarks": "Strong annual performance in research and faculty development"
            },
            {
                "academic_period": "2025-1st",
                "year": 2025,
                "semester": "1st",
                "metrics": {
                    "total_faculty": 148,
                    "faculty_evaluation_average": 85.40,
                    "faculty_satisfaction_score": 80.20,
                    "research_publications_count": 9,
                    "conference_presentations": 13,
                    "grant_applications": 6,
                    "grant_awards": 2,
                    "research_projects_ongoing": 21,
                    "faculty_training_hours": 480,
                    "professional_development_participants": 105,
                    "faculty_turnover_rate": 2.70,
                    "new_faculty_hired": 3,
                    "faculty_retired": 0
                },
                "remarks": "Increased faculty evaluation scores; good retention"
            },
            {
                "academic_period": "2025-2nd",
                "year": 2025,
                "semester": "2nd",
                "metrics": {
                    "total_faculty": 150,
                    "faculty_evaluation_average": 86.20,
                    "faculty_satisfaction_score": 81.10,
                    "research_publications_count": 11,
                    "conference_presentations": 16,
                    "grant_applications": 8,
                    "grant_awards": 4,
                    "research_projects_ongoing": 22,
                    "faculty_training_hours": 550,
                    "professional_development_participants": 120,
                    "faculty_turnover_rate": 2.00,
                    "new_faculty_hired": 4,
                    "faculty_retired": 1
                },
                "remarks": "Excellent faculty development and research activity momentum"
            }
        ],
        kpi_mapping: {
            "faculty_evaluation_average": { indicator_id: 4, unit: "%", target_value: 85.00 },
            "research_publications_count": { indicator_id: 5, unit: "count", target_value: 10.00 },
            "faculty_satisfaction_score": { indicator_id: null, unit: "%", target_value: 80.00 },
            "employee_retention_rate": { indicator_id: null, unit: "%", target_value: 95.00 }
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
                    "total_faculty_evaluated": 142,
                    "faculty_evaluation_average": 82.80,
                    "teaching_effectiveness_score": 84.20,
                    "student_feedback_average": 80.50,
                    "research_and_scholarship": 81.30,
                    "service_contribution": 82.50,
                    "professional_conduct": 85.10,
                    "faculty_with_excellent_rating": 38,
                    "faculty_with_satisfactory_rating": 96,
                    "faculty_with_needs_improvement": 8,
                    "employer_satisfaction_with_graduates": 79.40,
                    "graduate_competency_rating": 3.85
                },
                "remarks": "Overall positive evaluation results with strong professional conduct"
            },
            {
                "academic_period": "2024-2nd",
                "year": 2024,
                "semester": "2nd",
                "metrics": {
                    "total_faculty_evaluated": 144,
                    "faculty_evaluation_average": 84.30,
                    "teaching_effectiveness_score": 85.50,
                    "student_feedback_average": 82.10,
                    "research_and_scholarship": 83.40,
                    "service_contribution": 84.20,
                    "professional_conduct": 86.00,
                    "faculty_with_excellent_rating": 42,
                    "faculty_with_satisfactory_rating": 98,
                    "faculty_with_needs_improvement": 4,
                    "employer_satisfaction_with_graduates": 81.20,
                    "graduate_competency_rating": 3.92
                },
                "remarks": "Improved faculty evaluation average; strong employer feedback"
            },
            {
                "academic_period": "2024-Annual",
                "year": 2024,
                "semester": "Annual",
                "metrics": {
                    "total_faculty_evaluated": 143,
                    "faculty_evaluation_average": 83.55,
                    "teaching_effectiveness_score": 84.85,
                    "student_feedback_average": 81.30,
                    "research_and_scholarship": 82.35,
                    "service_contribution": 83.35,
                    "professional_conduct": 85.55,
                    "faculty_with_excellent_rating": 80,
                    "faculty_with_satisfactory_rating": 194,
                    "faculty_with_needs_improvement": 12,
                    "employer_satisfaction_rate": 80.30,
                    "graduate_competency_rating": 3.88,
                    "industry_placement_rate": 87.50
                },
                "remarks": "Annual consolidation shows consistent faculty quality and employer satisfaction"
            },
            {
                "academic_period": "2025-1st",
                "year": 2025,
                "semester": "1st",
                "metrics": {
                    "total_faculty_evaluated": 145,
                    "faculty_evaluation_average": 85.10,
                    "teaching_effectiveness_score": 86.30,
                    "student_feedback_average": 83.20,
                    "research_and_scholarship": 84.50,
                    "service_contribution": 85.10,
                    "professional_conduct": 86.50,
                    "faculty_with_excellent_rating": 45,
                    "faculty_with_satisfactory_rating": 98,
                    "faculty_with_needs_improvement": 2,
                    "employer_satisfaction_with_graduates": 82.50,
                    "graduate_competency_rating": 3.95
                },
                "remarks": "Significant improvement in faculty evaluation scores and student feedback"
            },
            {
                "academic_period": "2025-2nd",
                "year": 2025,
                "semester": "2nd",
                "metrics": {
                    "total_faculty_evaluated": 147,
                    "faculty_evaluation_average": 86.40,
                    "teaching_effectiveness_score": 87.50,
                    "student_feedback_average": 84.80,
                    "research_and_scholarship": 85.90,
                    "service_contribution": 86.50,
                    "professional_conduct": 87.20,
                    "faculty_with_excellent_rating": 52,
                    "faculty_with_satisfactory_rating": 93,
                    "faculty_with_needs_improvement": 2,
                    "employer_satisfaction_with_graduates": 83.70,
                    "graduate_competency_rating": 4.02
                },
                "remarks": "Excellent performance across all evaluation categories; strong employer feedback"
            }
        ],
        kpi_mapping: {
            "faculty_evaluation_average": { indicator_id: 4, unit: "%", target_value: 85.00 },
            "employer_satisfaction_with_graduates": { indicator_id: 7, unit: "%", target_value: 80.00 },
            "teaching_effectiveness_score": { indicator_id: null, unit: "%", target_value: 85.00 },
            "graduate_competency_rating": { indicator_id: null, unit: "score", target_value: 4.00 },
            "industry_placement_rate": { indicator_id: null, unit: "%", target_value: 85.00 }
        }
    }
};

\$(function(){ buildPagination("paginationContainer", $page, $total_pages, "goPage"); });

function goPage(p){
    const url = new URL(window.location);
    url.searchParams.set("page", p);
    window.location = url;
}

function applyFilters(){
    const url = new URL(window.location);
    url.searchParams.set("indicator_id", \$("#f-ind").val());
    url.searchParams.set("year",         \$("#f-year").val());
    url.searchParams.set("semester",     \$("#f-sem").val());
    url.searchParams.set("page", 1);
    window.location = url;
}

function openAddRecord(){
    \$("#recModalTitle").text("Add QA Record");
    \$("#rec_id,#rec_actual,#rec_remarks,#rec_by").val("");
    \$("#rec_indicator").val("");
    \$("#rec_semester").val("1st");
    \$("#rec_year").val(new Date().getFullYear());
}

function editRecord(data){
    \$("#recModalTitle").text("Edit QA Record");
    \$("#rec_id").val(data.record_id);
    \$("#rec_indicator").val(data.indicator_id);
    \$("#rec_year").val(data.year);
    \$("#rec_semester").val(data.semester);
    \$("#rec_actual").val(data.actual_value);
    \$("#rec_by").val(data.recorded_by);
    \$("#rec_remarks").val(data.remarks);
    new bootstrap.Modal(document.getElementById("recModal")).show();
}

function viewRecord(data){
    \$("#view_rec_indicator").text(data.indicator_name);
    \$("#view_rec_period").text(data.semester + " " + data.year);
    \$("#view_rec_actual").text(data.actual_value + " " + data.unit);
    \$("#view_rec_target").text(data.target_value + " " + data.unit);
    const variance = data.actual_value - data.target_value;
    const varianceText = (variance >= 0 ? "+" : "") + variance.toFixed(2);
    const varianceHTML = variance >= 0 ? `<span style="color: var(--success)">\${varianceText}</span>` : `<span style="color: var(--danger)">\${varianceText}</span>`;
    \$("#view_rec_variance").html(varianceHTML);

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

    \$("#view_rec_status").html(statusBadge);
    \$("#view_rec_by").text(data.recorded_by || "N/A");
    const dateStr = new Date(data.created_at).toLocaleDateString("en-US", {year: "numeric", month: "short", day: "numeric", hour: "2-digit", minute: "2-digit"});
    \$("#view_rec_date").text(dateStr);
    \$("#view_rec_remarks").text(data.remarks || "N/A");
    new bootstrap.Modal(document.getElementById("viewRecModal")).show();
}

function saveRecord(){
    const activeTab = document.querySelector('.tab-pane.active');
    const isManual = activeTab.id === 'manual-entry';
    
    let indicator_id, actual_value, year, semester, recorded_by, remarks;
    
    if (isManual) {
        indicator_id = \$("#rec_indicator").val();
        actual_value = \$("#rec_actual").val();
        year = \$("#rec_year").val();
        semester = \$("#rec_semester").val();
        recorded_by = \$("#rec_by").val() || "QA System";
        remarks = \$("#rec_remarks").val() || "";
    } else {
        indicator_id = \$("#ext_indicator").val();
        actual_value = \$("#ext_actual").val();
        year = \$("#ext_year").val();
        semester = \$("#ext_semester").val();
        recorded_by = "QA System";
        remarks = "";
    }
    
    // Validate required fields
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

    const isUpdate = \$("#rec_id").val();
    
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
        payload.record_id = \$("#rec_id").val();
    }
    
    console.log("Saving record:", payload);
    
    qaAjax("../api/records.php", payload, 
    function(res) {
        console.log("Success response:", res);
        location.reload();
    },
    function(err) {
        console.error("Error response object:", err);
        console.error("Error response string:", JSON.stringify(err));
        let errMsg = "Failed to save record.";
        
        // Try to extract message from error object
        if (err && typeof err === 'object') {
            if (err.message) {
                errMsg = err.message;
            } else if (err.status === 'error' && typeof err === 'object') {
                errMsg = Object.keys(err).length > 0 ? JSON.stringify(err) : "Unknown error";
            }
        }
        
        console.error("Final error message to show:", errMsg);
        alert(errMsg);
    });
}

function deleteRecord(id){
    confirmDelete("../api/records.php", {action:"delete", record_id:id}, () => location.reload());
}

// ==================== REPLACE loadExternalData FUNCTION ====================
function loadExternalData() {
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

    const sourceData = externalDataSources[source];
    if (!sourceData || !sourceData.data || sourceData.data.length === 0) {
        alert("No data available for this system.");
        return;
    }

    // Collect unique metric keys from all data entries
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
    $("#ext_indicator").val("");
    $("#ext_year").val("");
    $("#ext_semester").val("1st");
    $("#ext_actual").val("");
}

// ==================== REPLACE populateExternalValue FUNCTION ====================
function populateExternalValue() {
    const source = $("#ext_source").val();
    const metric = $("#ext_metric").val();

    if (!source || !metric) {
        $("#ext_data_container").hide();
        return;
    }

    const sourceData = externalDataSources[source];
    if (!sourceData || !sourceData.data) return;

    // Filter records that contain the selected metric
    const matchingRecords = [];
    sourceData.data.forEach(record => {
        if (record.metrics.hasOwnProperty(metric)) {
            const value = record.metrics[metric];
            // Get indicator mapping if available
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

    // Store globally for selection
    window.externalDataRows = matchingRecords;

    // Build table rows
    let html = "";
    matchingRecords.forEach((rec, idx) => {
        html += `
            <tr>
                <td>\${rec.academic_period}</td>
                <td>\${rec.actual_value}</td>
                <td>
                    <button class="btn-qa btn-qa-sm btn-qa-primary" onclick="selectExternalData(\${idx})">
                        <i class="bi bi-check"></i> Select
                    </button>
                </td>
            </tr>
        `;
    });

    $("#ext_data_body").html(html);
    $("#ext_data_container").show();
}


// 3. ADD delegated click handler inside $(function(){ ... })
$(function(){ 
    buildPagination("paginationContainer", $page, $total_pages, "goPage");
    
    $(document).on('click', '.select-external-btn', function() {
        const idx = $(this).data('idx');
        if (idx !== undefined && window.externalDataRows && window.externalDataRows[idx]) {
            const data = window.externalDataRows[idx];
            $("#ext_year").val(data.year);
            $("#ext_semester").val(data.semester);
            $("#ext_actual").val(data.actual_value);
            if (data.indicator_id) $("#ext_indicator").val(data.indicator_id);
            else $("#ext_indicator").val("");
            document.getElementById("ext_indicator").scrollIntoView({ behavior: "smooth" });
        }
    });
});
// ==================== REPLACE selectExternalData FUNCTION ====================
function selectExternalData(index) {
    const data = window.externalDataRows[index];
    if (!data) return;

    // Populate year and semester
    $("#ext_year").val(data.year);
    $("#ext_semester").val(data.semester);
    $("#ext_actual").val(data.actual_value);

    // Auto-populate indicator if mapping exists
    if (data.indicator_id) {
        $("#ext_indicator").val(data.indicator_id);
    } else {
        // Optionally clear or leave as is
        $("#ext_indicator").val("");
    }

    // Optional: Scroll to indicator field
    document.getElementById("ext_indicator").scrollIntoView({ behavior: "smooth" });
}
</script>
JS;
require_once __DIR__ . '/../includes/footer.php';
?>


