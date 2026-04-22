<?php
// pages/integration_sync_history.php – View and manage external data syncs
// ByteBandits QA Management System
require_once __DIR__ . '/../config/database.php';
$page_title = 'External Data Integration';

$conn = getConnection();

// Get mapping status
$mapping_query = "
    SELECT 
        mapping_id,
        source_system,
        external_field,
        indicator_id,
        indicator_name,
        sync_frequency,
        is_active,
        last_sync_date,
        sync_status,
        error_message
    FROM qa_external_data_mapping
    ORDER BY source_system, external_field
";
$mappings_result = $conn->query($mapping_query);
$mappings = [];
while ($row = $mappings_result->fetch_assoc()) {
    $mappings[] = $row;
}

// Get recent cache entries
$cache_query = "
    SELECT 
        cache_id,
        source_system,
        academic_period,
        year,
        semester,
        external_field,
        raw_value,
        converted_value,
        validation_status,
        synced_to_record_id,
        sync_date,
        (SELECT COUNT(*) FROM qa_external_data_cache WHERE sync_operation_id = (SELECT MAX(sync_operation_id) FROM qa_external_data_cache WHERE source_system = edc.source_system)) as batch_count
    FROM qa_external_data_cache edc
    ORDER BY sync_date DESC
    LIMIT 100
";
$cache_result = $conn->query($cache_query);
$cache_entries = [];
while ($row = $cache_result->fetch_assoc()) {
    $cache_entries[] = $row;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-cloud-arrow-down me-2 text-accent"></i>External Data Integration</h1>
        <p>Sync KPI data from LMS, HRIS, and Faculty Evaluation systems</p>
    </div>
    <div style="display:flex;gap:10px">
        <button class="btn-qa btn-qa-primary" onclick="triggerAutoSync()">
            <i class="bi bi-arrow-clockwise"></i> Sync Now
        </button>
        <button class="btn-qa btn-qa-secondary" onclick="viewMappings()">
            <i class="bi bi-gear"></i> Mappings
        </button>
    </div>
</div>

<!-- System Status -->
<div class="row mb-4" id="systemStatus">
    <div class="col-md-4">
        <div class="qa-card text-center">
            <div style="font-size:32px;color:var(--primary);margin:20px 0">
                <i class="bi bi-cloud-check"></i>
            </div>
            <p class="text-muted-qa">LMS Sync Status</p>
            <p id="lms_status" class="fw-600">Loading...</p>
            <p id="lms_time" class="mono text-muted-qa" style="font-size:12px">Last sync: --</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="qa-card text-center">
            <div style="font-size:32px;color:var(--info);margin:20px 0">
                <i class="bi bi-people-fill"></i>
            </div>
            <p class="text-muted-qa">HRIS Sync Status</p>
            <p id="hris_status" class="fw-600">Loading...</p>
            <p id="hris_time" class="mono text-muted-qa" style="font-size:12px">Last sync: --</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="qa-card text-center">
            <div style="font-size:32px;color:var(--success);margin:20px 0">
                <i class="bi bi-star-fill"></i>
            </div>
            <p class="text-muted-qa">Faculty Eval Sync Status</p>
            <p id="faculty_status" class="fw-600">Loading...</p>
            <p id="faculty_time" class="mono text-muted-qa" style="font-size:12px">Last sync: --</p>
        </div>
    </div>
</div>

<!-- Sync Workflow -->
<div class="qa-card">
    <h5 class="mb-3"><i class="bi bi-arrow-right-circle"></i> Sync Workflow</h5>
    <div style="display:grid;grid-template-columns:1fr auto 1fr auto 1fr;align-items:center;gap:15px;margin-bottom:20px">
        <div style="text-align:center">
            <div style="background:var(--primary);color:white;width:50px;height:50px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;font-weight:600">1</div>
            <p style="margin-top:8px;font-size:12px">Load JSON Files</p>
        </div>
        <div style="color:var(--primary);font-size:20px">→</div>
        <div style="text-align:center">
            <div style="background:var(--info);color:white;width:50px;height:50px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;font-weight:600">2</div>
            <p style="margin-top:8px;font-size:12px">Cache Raw Data</p>
        </div>
        <div style="color:var(--info);font-size:20px">→</div>
        <div style="text-align:center">
            <div style="background:var(--success);color:white;width:50px;height:50px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;font-weight:600">3</div>
            <p style="margin-top:8px;font-size:12px">Create Records</p>
        </div>
    </div>
    <div style="background:var(--bg-light);padding:15px;border-radius:var(--radius);font-size:14px">
        <p><strong>1. Load JSON Files:</strong> System reads data from /data/lms_data.json, hris_data.json, faculty_evaluation_data.json</p>
        <p><strong>2. Cache Raw Data:</strong> Stores raw values in qa_external_data_cache for review and validation</p>
        <p><strong>3. Create Records:</strong> Validates cached data and creates qa_records, linking to source systems via source_system field</p>
    </div>
</div>

<!-- Recent Sync Activity -->
<div class="qa-card mt-4">
    <h5 class="mb-3"><i class="bi bi-hourglass-split"></i> Recent Sync Activity</h5>
    <div class="qa-table-wrapper table-responsive">
        <table class="table qa-table table-sm align-middle">
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>Source System</th>
                    <th>Period</th>
                    <th>Metric</th>
                    <th>Raw Value</th>
                    <th>Converted Value</th>
                    <th>Status</th>
                    <th>Linked Record</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($cache_entries) === 0): ?>
                <tr><td colspan="8"><div class="empty-state"><i class="bi bi-inbox"></i><p>No sync data yet. Click "Sync Now" to start.</p></div></td></tr>
                <?php else: ?>
                <?php foreach ($cache_entries as $entry): ?>
                <tr>
                    <td class="mono text-muted-qa" style="font-size:12px"><?= date('M d, Y H:i', strtotime($entry['sync_date'])) ?></td>
                    <td>
                        <?php
                            $badge_color = match($entry['source_system']) {
                                'LMS' => 'primary',
                                'HRIS' => 'info',
                                'FACULTY_EVAL' => 'success',
                                default => 'secondary'
                            };
                        ?>
                        <span class="badge-status badge-<?= $badge_color ?>"><?= htmlspecialchars($entry['source_system']) ?></span>
                    </td>
                    <td class="mono"><?= htmlspecialchars($entry['academic_period']) ?></td>
                    <td><?= htmlspecialchars($entry['external_field']) ?></td>
                    <td class="mono"><?= htmlspecialchars($entry['raw_value']) ?></td>
                    <td class="fw-600"><?= number_format($entry['converted_value'], 2) ?></td>
                    <td>
                        <?php if ($entry['validation_status'] === 'valid'): ?>
                            <span class="badge-status badge-active"><i class="bi bi-check-circle"></i> Valid</span>
                        <?php else: ?>
                            <span class="badge-status badge-closed"><i class="bi bi-x-circle"></i> <?= ucfirst($entry['validation_status']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($entry['synced_to_record_id']): ?>
                            <span class="badge-status badge-active"><i class="bi bi-check"></i> Linked</span>
                        <?php else: ?>
                            <span class="badge-status badge-inactive"><i class="bi bi-dash"></i> Pending</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Mappings Modal -->
<div class="modal fade" id="mappingsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Data Mappings Configuration</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="max-height:500px;overflow-y:auto">
        <div class="qa-table-wrapper table-responsive">
          <table class="table qa-table table-sm align-middle">
            <thead>
              <tr>
                <th>Source System</th>
                <th>External Field</th>
                <th>Maps to Indicator</th>
                <th>Sync Frequency</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($mappings as $m): ?>
              <tr>
                <td>
                  <?php
                      $badge_color = match($m['source_system']) {
                          'LMS' => 'primary',
                          'HRIS' => 'info',
                          'FACULTY_EVAL' => 'success',
                          default => 'secondary'
                      };
                  ?>
                  <span class="badge-status badge-<?= $badge_color ?>"><?= htmlspecialchars($m['source_system']) ?></span>
                </td>
                <td class="mono"><?= htmlspecialchars($m['external_field']) ?></td>
                <td><?= htmlspecialchars($m['indicator_name'] ?? $m['indicator_id'] ?? 'TBD') ?></td>
                <td><span class="badge bg-secondary"><?= ucfirst($m['sync_frequency']) ?></span></td>
                <td>
                  <?php if ($m['is_active']): ?>
                    <span class="badge-status badge-active"><i class="bi bi-toggle-on"></i> Active</span>
                  <?php else: ?>
                    <span class="badge-status badge-inactive"><i class="bi bi-toggle-off"></i> Inactive</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
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
$(function() {
    loadSyncStatus();
    setInterval(loadSyncStatus, 10000); // Refresh every 10 seconds
});

function loadSyncStatus() {
    $.get("/qa_system/api/external_data_sync.php?action=get_mapping", function(response) {
        if (response.success && response.data) {
            const lms = response.data.find(m => m.source_system === "LMS");
            const hris = response.data.find(m => m.source_system === "HRIS");
            const faculty = response.data.find(m => m.source_system === "FACULTY_EVAL");
            
            updateStatusBox("lms", lms);
            updateStatusBox("hris", hris);
            updateStatusBox("faculty", faculty);
        }
    });
}

function updateStatusBox(prefix, data) {
    if (!data) return;
    
    const statusEl = document.getElementById(prefix + "_status");
    const timeEl = document.getElementById(prefix + "_time");
    
    if (data.sync_status === "synced") {
        statusEl.innerHTML = "<i class=\"bi bi-check-circle\" style=\"color:var(--success)\"></i> Synced";
        statusEl.style.color = "var(--success)";
    } else if (data.sync_status === "error") {
        statusEl.innerHTML = "<i class=\"bi bi-x-circle\" style=\"color:var(--danger)\"></i> Error";
        statusEl.style.color = "var(--danger)";
    } else {
        statusEl.innerHTML = "<i class=\"bi bi-hourglass-split\" style=\"color:var(--warning)\"></i> Pending";
        statusEl.style.color = "var(--warning)";
    }
    
    if (data.last_sync_date) {
        const date = new Date(data.last_sync_date);
        timeEl.textContent = "Last sync: " + date.toLocaleDateString() + " " + date.toLocaleTimeString();
    }
}

function triggerAutoSync() {
    if (confirm("Start auto-sync of all external data? This may take a moment.")) {
        $("#systemStatus").fadeTo(200, 0.5);
        $.get("/qa_system/api/external_data_sync.php?action=auto_sync", function(response) {
            $("#systemStatus").fadeTo(200, 1);
            if (response.success) {
                alert("Sync successful!\\n" + response.message + "\\nCreated: " + response.created_records + " | Skipped: " + response.skipped_records);
                setTimeout(() => location.reload(), 1000);
            } else {
                alert("Sync error: " + response.error);
            }
        });
    }
}

function viewMappings() {
    new bootstrap.Modal(document.getElementById("mappingsModal")).show();
}
</script>';

require_once __DIR__ . '/../includes/footer.php';
?>
