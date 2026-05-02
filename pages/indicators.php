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
if ($search !== '') {
  $where[] = '(name LIKE ? OR description LIKE ?)';
  $s = "%$search%";
  $params[] = $s;
  $params[] = $s;
  $types .= 'ss';
}
if ($category !== '') {
  $where[] = 'category = ?';
  $params[] = $category;
  $types .= 's';
}
if ($status !== '') {
  $where[] = 'status = ?';
  $params[] = $status;
  $types .= 's';
}
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
      <?php foreach ($categories as $cat): ?>
        <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div style="min-width:130px">
    <label class="qa-form-label">Status</label>
    <select id="f-status" class="qa-form-control">
      <option value="">All</option>
      <option value="Active" <?= $status === 'Active'   ? 'selected' : '' ?>>Active</option>
      <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
    </select>
  </div>
  <div style="display:flex;align-items:flex-end;gap:8px">
    <button class="btn-qa btn-qa-primary" onclick="applyFilters()"><i class="bi bi-funnel"></i> Filter</button>
    <button type="button" class="btn-qa btn-qa-secondary btn-clear-filters"><i class="bi bi-x-circle"></i> Clear</button>
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
      <tbody id="indicatorsTableBody">
        <?php if ($indicators->num_rows === 0): ?>
          <tr>
            <td colspan="7">
              <div class="empty-state"><i class="bi bi-inbox"></i>
                <p>No indicators found</p>
              </div>
            </td>
          </tr>
        <?php else: ?>
          <?php $n = $offset + 1;
          while ($ind = $indicators->fetch_assoc()): ?>
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
                <span class="badge-status <?= $ind['status'] === 'Active' ? 'badge-active' : 'badge-inactive' ?>">
                  <?= $ind['status'] ?>
                </span>
              </td>
              <td>
                <div class="d-flex gap-1">
                  <button class="btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon btn-view"
                    data-indicator="<?= htmlspecialchars(json_encode($ind)) ?>"
                    title="View">
                    <i class="bi bi-eye"></i>
                  </button>
                  <button class="btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon btn-analytics"
                    data-indicator-id="<?= $ind['indicator_id'] ?>"
                    data-indicator-name="<?= htmlspecialchars($ind['name']) ?>"
                    title="Analytics">
                    <i class="bi bi-graph-up"></i>
                  </button>
                  <button class="btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon btn-edit"
                    data-indicator="<?= htmlspecialchars(json_encode($ind)) ?>"
                    title="Edit">
                    <i class="bi bi-pencil"></i>
                  </button>
                  <button class="btn-qa btn-qa-danger btn-qa-sm btn-qa-icon btn-delete"
                    data-indicator-id="<?= $ind['indicator_id'] ?>"
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
  <span class="text-muted-qa indicators-info">Showing <?= min($offset + 1, $total) ?>-<?= min($offset + $per_page, $total) ?> of <?= $total ?> indicators</span>
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
            <textarea id="ind_desc" class="qa-form-control" placeholder="Describe what this indicator measures" rows="3"></textarea>
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

<!-- Analytics Modal -->
<div class="modal fade" id="analyticsModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="analyticsTitle">Analytics & Insights</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="max-height:75vh;overflow-y:auto;">
        <!-- Loading Indicator -->
        <div id="analyticsLoading" class="text-center" style="display:none;padding:40px">
          <div class="spinner-border text-accent" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>

        <!-- Analytics Content -->
        <div id="analyticsContent" style="display:none;">
          <!-- Time Series Chart -->
          <div class="qa-card mb-4">
            <div class="qa-card-header">
              <h6 class="mb-0"><i class="bi bi-graph-up me-2 text-accent"></i>Time-Series Analysis</h6>
            </div>
            <div style="padding:20px;">
              <canvas id="timeSeriesChart" style="max-height:250px;"></canvas>
              <p class="text-muted-qa mt-3 mb-0" id="timeSeriesInfo" style="font-size:0.85rem;"></p>
            </div>
          </div>

          <!-- Trend Analysis & Forecasting (Side by Side) -->
          <div class="row g-3 mb-4">
            <div class="col-lg-6">
              <div class="qa-card h-100">
                <div class="qa-card-header">
                  <h6 class="mb-0"><i class="bi bi-arrow-up me-2 text-accent"></i>Trend Analysis</h6>
                </div>
                <div style="padding:20px;">
                  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
                    <div style="padding:10px;background:var(--bg-secondary);border-radius:6px;">
                      <p class="text-muted-qa mb-2" style="font-size:0.8rem;font-weight:500;">Direction</p>
                      <p id="trendDirection" class="fw-600" style="font-size:1.3rem;margin:0;"></p>
                    </div>
                    <div style="padding:10px;background:var(--bg-secondary);border-radius:6px;">
                      <p class="text-muted-qa mb-2" style="font-size:0.8rem;font-weight:500;">% Change</p>
                      <p id="trendPercent" class="fw-600" style="font-size:1.3rem;margin:0;"></p>
                    </div>
                    <div style="padding:10px;background:var(--bg-secondary);border-radius:6px;">
                      <p class="text-muted-qa mb-2" style="font-size:0.8rem;font-weight:500;">Previous</p>
                      <p id="trendOldest" class="mono" style="font-size:0.9rem;margin:0;"></p>
                    </div>
                    <div style="padding:10px;background:var(--bg-secondary);border-radius:6px;">
                      <p class="text-muted-qa mb-2" style="font-size:0.8rem;font-weight:500;">Latest</p>
                      <p id="trendNewest" class="mono" style="font-size:0.9rem;margin:0;"></p>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Forecasting -->
            <div class="col-lg-6">
              <div class="qa-card h-100">
                <div class="qa-card-header">
                  <h6 class="mb-0"><i class="bi bi-crystal-ball me-2 text-accent"></i>Forecast (Next 3 Periods)</h6>
                </div>
                <div style="padding:20px;">
                  <div id="forecastContent">
                    <p class="text-muted-qa text-center" style="font-size:0.9rem;margin:0;">Loading forecast...</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Benchmark Comparison -->
          <div class="qa-card">
            <div class="qa-card-header">
              <h6 class="mb-0"><i class="bi bi-bullseye me-2 text-accent"></i>Benchmark Comparison (<span id="benchmarkCategoryName">Category</span> Average)</h6>
            </div>
            <div style="padding:20px;">
              <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:15px;margin-bottom:20px;">
                <div style="text-align:center;padding:15px;background:var(--bg-secondary);border-radius:var(--radius);">
                  <p class="text-muted-qa mb-1" style="font-size:0.8rem;font-weight:500;">Your Value</p>
                  <p id="benchmarkCurrent" class="fw-600" style="font-size:1.4rem;margin:0;"></p>
                </div>
                <div style="text-align:center;padding:15px;background:var(--bg-secondary);border-radius:var(--radius);">
                  <p class="text-muted-qa mb-1" style="font-size:0.8rem;font-weight:500;">Category Average</p>
                  <p id="benchmarkAverage" class="fw-600" style="font-size:1.4rem;margin:0;"></p>
                </div>
                <div style="text-align:center;padding:15px;background:var(--bg-secondary);border-radius:var(--radius);">
                  <p class="text-muted-qa mb-1" style="font-size:0.8rem;font-weight:500;">Difference</p>
                  <p id="benchmarkDiff" class="fw-600" style="font-size:1.4rem;margin:0;"></p>
                </div>
              </div>
              <div id="benchmarkTable" style="overflow-x:auto;"></div>
            </div>
          </div>
        </div>

        <!-- Error Message -->
        <div id="analyticsError" class="alert alert-warning" style="display:none;">
          <p id="analyticsErrorMsg" class="mb-0;"></p>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn-qa btn-qa-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php
$cp = $page;
$tp = $total_pages;
$extra_js = "<script>
const currentPage = $cp;
const totalPages  = $tp;
let timeSeriesChart = null;

\$(function(){
    buildPagination('paginationContainer', currentPage, totalPages, 'goPage');
    attachButtonHandlers();
});

function attachButtonHandlers() {
    \$(document).off('click', '.btn-view').on('click', '.btn-view', function() {
        const data = JSON.parse(\$(this).attr('data-indicator'));
        viewIndicator(data);
    });
    
    \$(document).off('click', '.btn-edit').on('click', '.btn-edit', function() {
        const data = JSON.parse(\$(this).attr('data-indicator'));
        editIndicator(data);
    });
    
    \$(document).off('click', '.btn-analytics').on('click', '.btn-analytics', function() {
        const id = \$(this).attr('data-indicator-id');
        const name = \$(this).attr('data-indicator-name');
        viewAnalytics(id, name);
    });
    
    \$(document).off('click', '.btn-delete').on('click', '.btn-delete', function() {
        const id = \$(this).attr('data-indicator-id');
        deleteIndicator(id);
    });
}

function goPage(p) {
    loadIndicatorsTable(p);
}

function applyFilters() {
    loadIndicatorsTable(1);
}

// Clear filters function for indicators page
function clearFilters() {
    // Reset all filter inputs to empty/default values
    $('#f-search').val('');
    $('#f-category').val('');
    $('#f-status').val('');
    
    // Reload the table with cleared filters (page 1)
    loadIndicatorsTable(1);
}

// Attach clear button click handler
$(document).on('click', '.btn-clear-filters', function(e) {
    e.preventDefault();
    console.log('Clear filters button clicked');
    clearFilters();
});

function loadIndicatorsTable(page = 1) {
    const search = \$('#f-search').val();
    const category = \$('#f-category').val();
    const status = \$('#f-status').val();

    \$.ajax({
        url: '/qa_system/api/indicators.php',
        type: 'GET',
        data: {
            action: 'list',
            page: page,
            search: search,
            category: category,
            status: status
        },
        dataType: 'json',
        success: function(data) {
            if (data.status === 'success') {
                let tableHtml = '';
                if (data.data.length === 0) {
                    tableHtml = '<tr><td colspan=7><div class=\"empty-state\"><i class=\"bi bi-inbox\"></i><p>No indicators found</p></div></td></tr>';
                } else {
                    let rowNum = data.offset + 1;
                    \$.each(data.data, function(index, ind) {
                        const indJson = JSON.stringify(ind).replace(/\"/g, '&quot;');
                        tableHtml += '<tr>';
                        tableHtml += '<td class=\"text-muted-qa mono\">' + rowNum + '</td>';
                        tableHtml += '<td><div class=\"fw-600\">' + escapeHtml(ind.name) + '</div>';
                        if (ind.description) {
                            tableHtml += '<div class=\"text-muted-qa\" style=\"font-size:0.78rem;margin-top:2px\">' + escapeHtml(ind.description.substring(0, 80)) + '</div>';
                        }
                        tableHtml += '</td>';
                        tableHtml += '<td><span class=\"badge-status badge-pending\">' + escapeHtml(ind.category) + '</span></td>';
                        tableHtml += '<td class=\"fw-600\">' + parseFloat(ind.target_value).toFixed(2) + '</td>';
                        tableHtml += '<td class=\"mono\">' + escapeHtml(ind.unit) + '</td>';
                        tableHtml += '<td><span class=\"badge-status ' + (ind.status === 'Active' ? 'badge-active' : 'badge-inactive') + '\">' + ind.status + '</span></td>';
                        tableHtml += '<td>';
                        tableHtml += '<div class=\"d-flex gap-1\">';
                        tableHtml += '<button class=\"btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon btn-view\" data-indicator=\"' + indJson + '\" title=\"View\"><i class=\"bi bi-eye\"></i></button>';
                        tableHtml += '<button class=\"btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon btn-analytics\" data-indicator-id=\"' + ind.indicator_id + '\" data-indicator-name=\"' + escapeHtml(ind.name) + '\" title=\"Analytics\"><i class=\"bi bi-graph-up\"></i></button>';
                        tableHtml += '<button class=\"btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon btn-edit\" data-indicator=\"' + indJson + '\" title=\"Edit\"><i class=\"bi bi-pencil\"></i></button>';
                        tableHtml += '<button class=\"btn-qa btn-qa-danger btn-qa-sm btn-qa-icon btn-delete\" data-indicator-id=\"' + ind.indicator_id + '\" title=\"Delete\"><i class=\"bi bi-trash\"></i></button>';
                        tableHtml += '</div></td></tr>';
                        rowNum++;
                    });
                }
                \$('#indicatorsTableBody').html(tableHtml);
                buildPagination('paginationContainer', data.current_page, data.total_pages, 'goPage');
                \$('.indicators-info').text('Showing ' + data.showing_from + '-' + data.showing_to + ' of ' + data.total + ' indicators');
                attachButtonHandlers();
            } else {
                alert('Error loading indicators: ' + data.message);
            }
        },
        error: function(err) {
            console.error('Load indicators error:', err);
            alert('Error loading indicators');
        }
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function openAdd() {
    \$('#indModalTitle').text('Add Indicator');
    \$('#ind_id,#ind_name,#ind_desc,#ind_target,#ind_unit,#ind_category').val('');
    \$('#ind_status').val('Active');
}

function editIndicator(data) {
    \$('#indModalTitle').text('Edit Indicator');
    \$('#ind_id').val(data.indicator_id);
    \$('#ind_name').val(data.name);
    \$('#ind_desc').val(data.description);
    \$('#ind_target').val(data.target_value);
    \$('#ind_unit').val(data.unit);
    \$('#ind_category').val(data.category);
    \$('#ind_status').val(data.status);
    new bootstrap.Modal(document.getElementById('indModal')).show();
}

function viewIndicator(data) {
    \$('#view_ind_name').text(data.name);
    \$('#view_ind_category').text(data.category);
    \$('#view_ind_target').text(data.target_value + ' ' + data.unit);
    \$('#view_ind_unit').text(data.unit);
    \$('#view_ind_desc').text(data.description || '-');
    const statusBadge = data.status === 'Active' ? '<span class=\"badge-status badge-active\">Active</span>' : '<span class=\"badge-status badge-inactive\">Inactive</span>';
    \$('#view_ind_status').html(statusBadge);
    const dateStr = new Date(data.created_at).toLocaleDateString('en-US', {year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'});
    \$('#view_ind_created').text(dateStr);
    new bootstrap.Modal(document.getElementById('viewIndModal')).show();
}

function viewAnalytics(indicatorId, indicatorName) {
    \$('#analyticsTitle').text('Analytics & Insights: ' + indicatorName);
    \$('#analyticsLoading').show();
    \$('#analyticsContent').hide();
    \$('#analyticsError').hide();
    new bootstrap.Modal(document.getElementById('analyticsModal')).show();

    let tsData, trendData, forecastData, benchData;
    let completed = 0;

    \$.ajax({
        url: '/qa_system/api/analytics.php',
        type: 'GET',
        data: { action: 'timeseries', indicator_id: indicatorId },
        dataType: 'json',
        success: function(data) { tsData = data; completed++; if (completed === 4) processAnalyticsData(tsData, trendData, forecastData, benchData); },
        error: function(err) { console.error('Time series error:', err); \$('#analyticsLoading').hide(); \$('#analyticsError').show(); \$('#analyticsErrorMsg').text('No records found. Please create a record first.'); }
    });

    \$.ajax({
        url: '/qa_system/api/analytics.php',
        type: 'GET',
        data: { action: 'trend', indicator_id: indicatorId },
        dataType: 'json',
        success: function(data) { trendData = data; completed++; if (completed === 4) processAnalyticsData(tsData, trendData, forecastData, benchData); },
        error: function(err) { console.error('Trend error:', err); \$('#analyticsLoading').hide(); \$('#analyticsError').show(); \$('#analyticsErrorMsg').text('No records found. Please create a record first.'); }
    });

    \$.ajax({
        url: '/qa_system/api/analytics.php',
        type: 'GET',
        data: { action: 'forecast', indicator_id: indicatorId },
        dataType: 'json',
        success: function(data) { forecastData = data; completed++; if (completed === 4) processAnalyticsData(tsData, trendData, forecastData, benchData); },
        error: function(err) { console.error('Forecast error:', err); \$('#analyticsLoading').hide(); \$('#analyticsError').show(); \$('#analyticsErrorMsg').text('No records found. Please create a record first.'); }
    });

    \$.ajax({
        url: '/qa_system/api/analytics.php',
        type: 'GET',
        data: { action: 'benchmark', indicator_id: indicatorId },
        dataType: 'json',
        success: function(data) { benchData = data; completed++; if (completed === 4) processAnalyticsData(tsData, trendData, forecastData, benchData); },
        error: function(err) { console.error('Benchmark error:', err); \$('#analyticsLoading').hide(); \$('#analyticsError').show(); \$('#analyticsErrorMsg').text('No records found. Please create a record first.'); }
    });
}

function processAnalyticsData(tsData, trendData, forecastData, benchData) {
    \$('#analyticsLoading').hide();
    if (!tsData.status || tsData.status !== 'success') {
        \$('#analyticsError').show();
        \$('#analyticsErrorMsg').text('Failed to load time-series data: ' + (tsData.message || 'Unknown error'));
        return;
    }
    \$('#analyticsContent').show();
    renderTimeSeriesChart(tsData.data, tsData.indicator);
    renderTrendAnalysis(trendData);
    renderForecast(forecastData);
    renderBenchmark(benchData);
}

function renderTimeSeriesChart(data, indicator) {
    if (!data || data.length === 0) { \$('#timeSeriesInfo').text('No historical data available'); return; }
    const labels = data.map(d => d.year + ' ' + (d.semester === 'Annual' ? 'Annual' : d.semester + ' Sem'));
    const values = data.map(d => parseFloat(d.actual_value));
    const target = parseFloat(indicator.target_value);
    const ctx = document.getElementById('timeSeriesChart').getContext('2d');
    if (timeSeriesChart) timeSeriesChart.destroy();
    timeSeriesChart = new Chart(ctx, {
        type: 'line',
        data: { labels: labels, datasets: [
            { label: 'Actual Value', data: values, borderColor: 'var(--primary)', backgroundColor: 'rgba(76, 175, 80, 0.1)', borderWidth: 2, fill: true, pointRadius: 5, pointBackgroundColor: 'var(--primary)' },
            { label: 'Target', data: Array(labels.length).fill(target), borderColor: '#FFC107', borderWidth: 2, borderDash: [5, 5], fill: false, pointRadius: 0 }
        ]},
        options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: false } } }
    });
    \$('#timeSeriesInfo').text('Showing ' + data.length + ' records. Latest: ' + values[values.length - 1] + ' ' + indicator.unit);
}

function renderTrendAnalysis(data) {
    if (data.status !== 'success' || !data.trend) {
        \$('#trendDirection').text('-');
        \$('#trendPercent').text('-');
        \$('#trendOldest').text('No data');
        \$('#trendNewest').text('No data');
        return;
    }
    const trendIcon = data.trend === 'up' ? '📈' : (data.trend === 'down' ? '📉' : '→');
    const color = data.trend === 'up' ? 'color:green' : (data.trend === 'down' ? 'color:red' : '');
    \$('#trendDirection').html('<span style=\"' + color + '\">' + trendIcon + ' ' + data.trend.toUpperCase() + '</span>');
    \$('#trendPercent').html('<span style=\"' + color + '\">' + (data.percent_change > 0 ? '+' : '') + data.percent_change + '%</span>');
    \$('#trendOldest').text(data.oldest_value);
    \$('#trendNewest').text(data.newest_value);
}

function renderForecast(data) {
    if (data.status !== 'success' || !data.forecast || data.forecast.length === 0) {
        \$('#forecastContent').html('<p class=\"text-muted-qa text-center mb-0\">Insufficient data for forecasting</p>');
        return;
    }
    let html = '<table class=\"table table-sm\" style=\"margin-bottom:0\"><thead><tr><th>Period</th><th>Predicted Value</th></tr></thead><tbody>';
    data.forecast.forEach(f => { html += '<tr><td>+' + f.period + '</td><td class=\"fw-600\">' + f.predicted_value + '</td></tr>'; });
    html += '</tbody></table>';
    \$('#forecastContent').html(html);
}

function renderBenchmark(data) {
    if (data.status !== 'success') {
        \$('#benchmarkTable').html('<p class=\"text-muted-qa\">Unable to load benchmark data</p>');
        return;
    }
    \$('#benchmarkCategoryName').text(data.category || 'Category');
    \$('#benchmarkCurrent').text(data.current_value !== null ? data.current_value : '-');
    \$('#benchmarkAverage').text(data.category_average !== null ? data.category_average : '-');
    let diffText = '-';
    if (data.performance_vs_category !== null) {
        const sign = data.performance_vs_category > 0 ? '+' : '';
        diffText = '<span style=\"color:' + (data.performance_vs_category > 0 ? 'green' : 'red') + '\">' + sign + data.performance_vs_category + '</span>';
    }
    \$('#benchmarkDiff').html(diffText);
    if (data.benchmarks && data.benchmarks.length > 0) {
        let html = '<table class=\"table table-sm\"><thead><tr><th>Indicator</th><th>Target</th><th>Actual</th><th>Performance</th></tr></thead><tbody>';
        data.benchmarks.forEach(b => {
            const perf = b.actual !== null ? ((b.actual / b.target) * 100).toFixed(1) : '-';
            html += '<tr><td>' + b.name + '</td><td class=\"mono\">' + b.target + '</td><td class=\"mono\">' + (b.actual !== null ? b.actual : '-') + '</td><td class=\"mono\">' + perf + '%</td></tr>';
        });
        html += '</tbody></table>';
        \$('#benchmarkTable').html(html);
    }
}

function saveIndicator() {
    const name = \$('#ind_name').val().trim();
    const target = \$('#ind_target').val().trim();
    if (!name || !target) { alert('Name and Target are required.'); return; }
    const data = {
        action: \$('#ind_id').val() ? 'update' : 'create',
        indicator_id: \$('#ind_id').val(),
        name: name,
        description: \$('#ind_desc').val(),
        target_value: target,
        unit: \$('#ind_unit').val(),
        category: \$('#ind_category').val(),
        status: \$('#ind_status').val()
    };
    \$.ajax({
        url: '/qa_system/api/indicators.php',
        type: 'POST',
        data: data,
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('indModal')).hide();
                loadIndicatorsTable(1);
            } else {
                alert('Error: ' + (response.message || 'Failed to save indicator'));
            }
        },
        error: function(err) {
            console.error('Save error:', err);
            alert('Error saving indicator');
        }
    });
}

function deleteIndicator(id) {
    if (!confirm('Are you sure you want to delete this indicator?')) return;
    \$.ajax({
        url: '/qa_system/api/indicators.php',
        type: 'POST',
        data: { action: 'delete', indicator_id: id },
        dataType: 'json',
        success: function(response) {
            if (response.status === 'success') {
                loadIndicatorsTable(1);
            } else {
                alert('Error: ' + (response.message || 'Failed to delete indicator'));
            }
        },
        error: function(err) {
            console.error('Delete error:', err);
            alert('Error deleting indicator');
        }
    });
}
</script>";
require_once __DIR__ . '/../includes/footer.php';
?>