<?php
// pages/executive_report.php – Executive Summary Report (Bootstrap Enhanced)
// ByteBandits QA Management System

require_once __DIR__ . '/../config/database.php';

$page_title = 'Executive Report';
$conn = getConnection();

// Get filters
$year_from = (int)($_GET['year_from'] ?? date('Y') - 1);
$year_to   = (int)($_GET['year_to'] ?? date('Y'));
$semester  = trim($_GET['semester'] ?? '');
$category  = trim($_GET['category'] ?? '');
$export    = trim($_GET['export'] ?? '');

// Build filter params for API calls
$filter_params = "year_from=$year_from&year_to=$year_to";
if (!empty($semester) && $semester !== 'All') $filter_params .= "&semester=" . urlencode($semester);
if (!empty($category) && $category !== 'All') $filter_params .= "&category=" . urlencode($category);

// Get filter options from DB
$categories = ['All'];
$res = $conn->query("SELECT DISTINCT category FROM qa_indicators WHERE status='Active' ORDER BY category");
while ($row = $res->fetch_assoc()) $categories[] = $row['category'];
$semesters = ['All', '1st', '2nd', 'Annual'];

$organization = 'Pamantasan ng Lungsod ng San Pablo';

// Fetch full report data from API
function fetchReportData($filter_params) {
    $url = "http://localhost/qa_system/api/executive_report.php?action=full_report&$filter_params";
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => $url, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http !== 200 || !$resp) return ['error' => 'Unable to fetch report data'];
    return json_decode($resp, true) ?? ['error' => 'Invalid data format'];
}

$data = fetchReportData($filter_params);

// CSV Export
if ($export === 'csv') {
    exportToCSV($data);
    exit;
}

// ------------------------------------------------------------------
// CSV Export Function (complete)
// ------------------------------------------------------------------
function exportToCSV($data) {
    if (isset($data['error'])) {
        header('Content-Type: text/plain');
        echo "Error: " . $data['error'];
        exit;
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Executive_Report_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Quality Assurance Executive Summary']);
    fputcsv($out, ['Generated:', date('Y-m-d H:i:s')]);
    fputcsv($out, ['Period:', $data['period'] ?? 'N/A']);
    fputcsv($out, []);
    
    fputcsv($out, ['PERFORMANCE SCORECARD']);
    fputcsv($out, ['Metric', 'Value']);
    $score = $data['scorecard'] ?? [];
    fputcsv($out, ['Total Indicators', $score['total_indicators'] ?? 0]);
    fputcsv($out, ['On Track', $score['on_track'] ?? 0]);
    fputcsv($out, ['At Risk', $score['at_risk'] ?? 0]);
    fputcsv($out, ['Off Track', $score['off_track'] ?? 0]);
    fputcsv($out, ['Avg Performance %', $score['avg_performance_pct'] ?? 0]);
    fputcsv($out, ['Quality Score', $data['quality_score']['score'] ?? 'N/A']);
    fputcsv($out, []);
    
    fputcsv($out, ['RISK ALERTS']);
    fputcsv($out, ['Indicator', 'Severity', 'Recommendation']);
    foreach ($data['risk_alerts'] ?? [] as $alert) {
        fputcsv($out, [$alert['indicator'], $alert['severity'], $alert['recommendation']]);
    }
    fputcsv($out, []);
    
    fputcsv($out, ['YEARLY TREND']);
    fputcsv($out, ['Year', 'Avg Performance %', 'On Track / Total', 'Change %']);
    foreach ($data['yearly_trend'] ?? [] as $year => $trend) {
        fputcsv($out, [$year, $trend['avg_performance'], $trend['on_track'] . '/' . $trend['total'], ($trend['change'] > 0 ? '+' : '') . $trend['change']]);
    }
    fputcsv($out, []);
    
    fputcsv($out, ['COMPLIANCE HEALTH']);
    $comp = $data['compliance_health'] ?? [];
    fputcsv($out, ['Active Standards', $comp['standards'] ?? 0]);
    fputcsv($out, ['Active Policies', $comp['policies'] ?? 0]);
    fputcsv($out, ['Pending Audits', $comp['audits_pending'] ?? 0]);
    fputcsv($out, ['Compliance Rate %', $comp['compliance_rate'] ?? 0]);
    fputcsv($out, []);
    
    fputcsv($out, ['ACTION PLANS']);
    $act = $data['action_plans'] ?? [];
    fputcsv($out, ['Total Actions', $act['total_actions'] ?? 0]);
    fputcsv($out, ['Closed', $act['closed_actions'] ?? 0]);
    fputcsv($out, ['Overdue', $act['overdue_actions'] ?? 0]);
    fputcsv($out, ['Completion Rate %', $act['completion_rate'] ?? 0]);
    fputcsv($out, []);
    
    fputcsv($out, ['SURVEY FEEDBACK']);
    $surv = $data['surveys'] ?? [];
    fputcsv($out, ['Total Surveys', $surv['total_surveys'] ?? 0]);
    fputcsv($out, ['Responses', $surv['total_responses'] ?? 0]);
    fputcsv($out, ['Avg Rating', $surv['avg_rating'] ?? 0]);
    fputcsv($out, []);
    
    fputcsv($out, ['DETAILED INDICATORS']);
    fputcsv($out, ['Indicator', 'Category', 'Target', 'Actual', 'Performance %', 'Status', 'Year', 'Semester']);
    foreach ($data['indicators'] ?? [] as $ind) {
        fputcsv($out, [$ind['name'], $ind['category'], $ind['target_value'], $ind['actual_value'], $ind['performance_pct'], $ind['status'], $ind['year'], $ind['semester']]);
    }
    fclose($out);
    exit;
}

// ------------------------------------------------------------------
// Helper: generate HTML report (used for web view and PDF capture)
// ------------------------------------------------------------------
function generateReportHTML($data, $org) {
    if (isset($data['error'])) return '<div class="alert alert-danger">' . htmlspecialchars($data['error']) . '</div>';
    
    $scorecard   = $data['scorecard'] ?? [];
    $indicators  = $data['indicators'] ?? [];
    $surveys     = $data['surveys'] ?? [];
    $actions     = $data['action_plans'] ?? [];
    $quality     = $data['quality_score'] ?? ['score' => 0, 'grade' => 'F'];
    $riskAlerts  = $data['risk_alerts'] ?? [];
    $compliance  = $data['compliance_health'] ?? [];
    $trend       = $data['yearly_trend'] ?? [];
    $period      = $data['period'] ?? 'N/A';
    $generated   = $data['generated_at'] ?? date('Y-m-d H:i:s');
    
    $scoreColor = $quality['grade'] === 'A' ? 'success' : ($quality['grade'] === 'B' ? 'primary' : ($quality['grade'] === 'C' ? 'warning' : 'danger'));
    
    ob_start();
?>
<div class="report-content">
    <!-- Header -->
    <div class="bg-gradient-primary text-white p-4 rounded-3 mb-4 text-center">
        <div class="h5 mb-1"><?php echo htmlspecialchars($org); ?></div>
        <h1 class="display-6 fw-bold">Quality Assurance Executive Summary</h1>
        <p class="mb-0">Reporting Period: <?php echo htmlspecialchars($period); ?> | Generated: <?php echo date('F d, Y', strtotime($generated)); ?></p>
    </div>
    
    <!-- Key Metrics Row -->
    <div class="row g-4 mb-5">
        <div class="col-md-3"><div class="card h-100 text-center shadow-sm"><div class="card-body"><h6 class="text-muted">Quality Score</h6><div class="display-4 fw-bold text-<?php echo $scoreColor; ?>"><?php echo (int)$quality['score']; ?>%</div><span class="badge bg-<?php echo $scoreColor; ?>">Grade <?php echo $quality['grade']; ?></span></div></div></div>
        <div class="col-md-3"><div class="card h-100 text-center shadow-sm"><div class="card-body"><h6 class="text-muted">On Track</h6><div class="display-4 fw-bold text-success"><?php echo (int)$scorecard['on_track']; ?></div><span class="text-muted">of <?php echo (int)$scorecard['total_indicators']; ?></span></div></div></div>
        <div class="col-md-3"><div class="card h-100 text-center shadow-sm"><div class="card-body"><h6 class="text-muted">Action Completion</h6><div class="display-4 fw-bold text-info"><?php echo (float)($actions['completion_rate'] ?? 0); ?>%</div><span class="text-danger"><?php echo (int)($actions['overdue_actions'] ?? 0); ?> overdue</span></div></div></div>
        <div class="col-md-3"><div class="card h-100 text-center shadow-sm"><div class="card-body"><h6 class="text-muted">Satisfaction</h6><div class="display-4 fw-bold text-warning"><?php echo (float)($surveys['avg_rating'] ?? 0); ?></div><span>/5 from <?php echo (int)($surveys['total_responses'] ?? 0); ?> responses</span></div></div></div>
    </div>
    
    <!-- Risk Alerts -->
    <?php if (!empty($riskAlerts)): ?>
    <div class="alert alert-danger border-start border-5 border-danger mb-4"><h5><i class="bi bi-exclamation-triangle-fill"></i> Critical Risk Alerts</h5><ul class="mb-0"><?php foreach ($riskAlerts as $alert): ?><li><strong><?php echo htmlspecialchars($alert['indicator']); ?></strong> (<?php echo $alert['severity']; ?>): <?php echo htmlspecialchars($alert['recommendation']); ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    
    <!-- Performance Scorecard -->
    <div class="card shadow-sm mb-4"><div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-bar-chart-steps"></i> Performance Scorecard</h5></div><div class="card-body"><div class="row text-center"><div class="col-sm-4"><div class="p-3 border rounded"><div class="h5 text-success fw-bold"><?php echo (int)$scorecard['on_track']; ?></div><small>On Track (≥100%)</small></div></div><div class="col-sm-4"><div class="p-3 border rounded"><div class="h5 text-warning fw-bold"><?php echo (int)$scorecard['at_risk']; ?></div><small>At Risk (80-99%)</small></div></div><div class="col-sm-4"><div class="p-3 border rounded"><div class="h5 text-danger fw-bold"><?php echo (int)$scorecard['off_track']; ?></div><small>Off Track (<80%)</small></div></div></div><div class="mt-3 text-center"><h6>Average Performance: <strong><?php echo (float)($scorecard['avg_performance_pct'] ?? 0); ?>%</strong></h6><div class="progress" style="height:8px"><div class="progress-bar bg-success" style="width:<?php echo min(100, (float)($scorecard['avg_performance_pct'] ?? 0)); ?>%"></div></div></div></div></div>
    
    <!-- Yearly Trend -->
    <?php if (!empty($trend)): ?>
    <div class="card shadow-sm mb-4"><div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-graph-up"></i> Year-over-Year Trend</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Year</th><th>Avg Performance</th><th>On Track / Total</th><th>Change</th></tr></thead><tbody><?php foreach ($trend as $y => $t): ?><tr><td><?php echo $y; ?></td><td><?php echo round($t['avg_performance'],1); ?>%</td><td><?php echo $t['on_track']; ?>/<?php echo $t['total']; ?></td><td class="<?php echo $t['change']>=0?'text-success':'text-danger'; ?>"><?php echo ($t['change']>0?'+':'').$t['change']; ?>%</td></tr><?php endforeach; ?></tbody></table></div></div></div>
    <?php endif; ?>
    
    <!-- Compliance Health -->
    <div class="card shadow-sm mb-4"><div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-building"></i> Governance & Compliance</h5></div><div class="card-body"><div class="row g-3"><div class="col-md-3"><div class="border rounded p-2 text-center"><div class="h5"><?php echo (int)($compliance['standards']??0); ?></div><small>Standards</small></div></div><div class="col-md-3"><div class="border rounded p-2 text-center"><div class="h5"><?php echo (int)($compliance['policies']??0); ?></div><small>Policies</small></div></div><div class="col-md-3"><div class="border rounded p-2 text-center"><div class="h5"><?php echo (int)($compliance['audits_pending']??0); ?></div><small>Pending Audits</small></div></div><div class="col-md-3"><div class="border rounded p-2 text-center"><div class="h5"><?php echo (int)($compliance['compliance_rate']??0); ?>%</div><small>Compliance</small></div></div></div></div></div>
    
    <!-- Detailed Indicators -->
    <div class="card shadow-sm mb-4"><div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-table"></i> Detailed Indicator Performance</h5></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-striped table-hover mb-0"><thead class="table-dark"><tr><th>Indicator</th><th>Category</th><th>Target</th><th>Actual</th><th>Achievement</th><th>Status</th></tr></thead><tbody><?php foreach (array_slice($indicators,0,15) as $ind): $badge = $ind['status']==='On Track'?'success':($ind['status']==='At Risk'?'warning':'danger'); ?><tr><td><?php echo htmlspecialchars($ind['name']); ?></td><td><?php echo htmlspecialchars($ind['category']); ?></td><td><?php echo number_format((float)$ind['target_value'],1).' '.htmlspecialchars($ind['unit']); ?></td><td><?php echo number_format((float)$ind['actual_value'],1); ?></td><td><?php echo (float)$ind['performance_pct']; ?>%</td><td><span class="badge bg-<?php echo $badge; ?>"><?php echo $ind['status']; ?></span></td></tr><?php endforeach; if(count($indicators)>15): ?><tr><td colspan="6" class="text-muted text-center">... and <?php echo count($indicators)-15; ?> more indicators. Download full report.</td></tr><?php endif; ?></tbody></table></div></div></div>
    
    <!-- Strategic Recommendations -->
    <div class="card shadow-sm mb-4"><div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-lightbulb"></i> Strategic Recommendations</h5></div><div class="card-body"><ul class="list-group list-group-flush"><?php if(($scorecard['off_track']??0)>0):?><li class="list-group-item">🔴 Prioritize corrective actions for <?php echo $scorecard['off_track']; ?> off-track indicators.</li><?php endif; if(($actions['overdue_actions']??0)>0):?><li class="list-group-item">⏰ <?php echo $actions['overdue_actions']; ?> action plans are overdue.</li><?php endif; if(($surveys['avg_rating']??0)<3.5):?><li class="list-group-item">📢 Stakeholder satisfaction is below target. Launch improvement initiatives.</li><?php endif; ?><li class="list-group-item">📊 Schedule quarterly executive reviews to monitor KPI trends.</li><li class="list-group-item">🏅 Leverage top-performing indicators as benchmarks.</li></ul></div></div>
    
    <div class="text-center text-muted small mt-4 pt-3 border-top">Confidential – ByteBandits QA System | Contact QA Office | <?php echo date('Y'); ?></div>
</div>
<?php
    return ob_get_clean();
}

// ------------------------------------------------------------------
// Include header (Bootstrap + custom styles)
// ------------------------------------------------------------------
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mt-3 mb-4">
        <div><h1 class="h3 mb-0"><i class="bi bi-graph-up me-2 text-primary"></i>Executive Summary</h1><p class="text-muted">Strategic QA dashboard with predictive alerts and compliance insights</p></div>
    </div>
    
    <!-- Filters Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-2"><label class="form-label small fw-bold">From Year</label><select name="year_from" class="form-select form-select-sm"><?php for($y=date('Y')-5;$y<=date('Y');$y++):?><option value="<?php echo $y;?>" <?php echo $year_from==$y?'selected':'';?>><?php echo $y;?></option><?php endfor;?></select></div>
                <div class="col-md-2"><label class="form-label small fw-bold">To Year</label><select name="year_to" class="form-select form-select-sm"><?php for($y=date('Y')-4;$y<=date('Y');$y++):?><option value="<?php echo $y;?>" <?php echo $year_to==$y?'selected':'';?>><?php echo $y;?></option><?php endfor;?></select></div>
                <div class="col-md-2"><label class="form-label small fw-bold">Semester</label><select name="semester" class="form-select form-select-sm"><?php foreach($semesters as $sem):?><option value="<?php echo $sem;?>" <?php echo $semester==$sem?'selected':'';?>><?php echo $sem;?></option><?php endforeach;?></select></div>
                <div class="col-md-3"><label class="form-label small fw-bold">Category</label><select name="category" class="form-select form-select-sm"><?php foreach($categories as $cat):?><option value="<?php echo $cat;?>" <?php echo $category==$cat?'selected':'';?>><?php echo htmlspecialchars($cat);?></option><?php endforeach;?></select></div>
                <div class="col-md-3"><button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i> Apply Filters</button></div>
            </form>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="?<?php echo $filter_params; ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-eye"></i> Refresh</a>
            <a href="?<?php echo $filter_params; ?>&export=csv" class="btn btn-success btn-sm"><i class="bi bi-file-spreadsheet"></i> Export CSV</a>
            <button onclick="downloadPDF()" class="btn btn-danger btn-sm"><i class="bi bi-file-pdf"></i> Export PDF</button>
        </div>
    </div>
    
    <!-- Report Container (for PDF capture) -->
    <div id="reportContainer" class="bg-white rounded-3 shadow-sm p-4 mb-5">
        <?php echo generateReportHTML($data, $organization); ?>
    </div>
</div>

<!-- Libraries for PDF generation -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

<script>
function downloadPDF() {
    const element = document.getElementById('reportContainer');
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Generating PDF...';
    btn.disabled = true;
    
    html2canvas(element, {
        scale: 2,
        logging: false,
        useCORS: true,
        backgroundColor: '#ffffff'
    }).then(canvas => {
        const imgData = canvas.toDataURL('image/png');
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const imgWidth = 210; // A4 width in mm
        const pageHeight = 297;
        const imgHeight = (canvas.height * imgWidth) / canvas.width;
        let position = 0;
        
        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        let heightLeft = imgHeight - pageHeight;
        while (heightLeft > 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }
        pdf.save('Executive_Report_' + new Date().toISOString().slice(0,19).replace(/:/g, '-') + '.pdf');
        btn.innerHTML = originalText;
        btn.disabled = false;
    }).catch(error => {
        console.error('PDF generation failed:', error);
        alert('PDF generation failed. Please use Print > Save as PDF instead.');
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}
</script>

<style>
@media print {
    .btn, .card-footer, .page-header, .no-print, .card:first-of-type { display: none !important; }
    body { background: white; padding: 0; margin: 0; }
    .report-content { margin: 0; padding: 0; }
    .bg-gradient-primary { background: #0d6efd !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .card { border: none !important; box-shadow: none !important; }
}
.bg-gradient-primary { background: linear-gradient(135deg, #0d6efd, #0a58ca); }
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>