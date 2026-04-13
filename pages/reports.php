<?php
// pages/reports.php – QA Reports (filterable, exportable)
// ByteBandits QA Management System
require_once __DIR__ . '/../config/database.php';
$page_title = 'Reports';

$conn = getConnection();

// Report type
$report_type = trim($_GET['report_type'] ?? 'kpi_summary');
$year_from   = (int)($_GET['year_from'] ?? date('Y')-1);
$year_to     = (int)($_GET['year_to']   ?? date('Y'));
$semester    = trim($_GET['semester']   ?? '');
$category    = trim($_GET['category']   ?? '');
$survey_id   = (int)($_GET['survey_id'] ?? 0);
$indicator_id= (int)($_GET['indicator_id'] ?? 0);
$audit_status = trim($_GET['audit_status'] ?? '');
$standard_id = (int)($_GET['standard_id'] ?? 0);

// Helpers
$categories = [];
$r = $conn->query("SELECT DISTINCT category FROM qa_indicators ORDER BY category");
while($c=$r->fetch_assoc()) $categories[] = $c['category'];

$survey_list = $conn->query("SELECT survey_id,title FROM surveys ORDER BY created_date DESC");
$indicator_list = $conn->query("SELECT indicator_id,name FROM qa_indicators WHERE status='Active' ORDER BY name");
$standards_list = $conn->query("SELECT standard_id,title FROM qa_standards WHERE status='Active' ORDER BY title");

$has_audit_standard_link = false;
$auditColCheck = $conn->query("SHOW COLUMNS FROM qa_audits LIKE 'standard_id'");
if ($auditColCheck && $auditColCheck->num_rows > 0) {
    $has_audit_standard_link = true;
}

// ─── Build report data ──────────────────────────────
$report_data = [];
$report_headers = [];

if($report_type === 'kpi_summary'){
    $report_headers = ['Indicator','Category','Unit','Target','Year','Semester','Actual','Variance','Status','Risk Flag','Remarks'];
    $w = ["r.year BETWEEN ? AND ?"]; $p=[$year_from,$year_to]; $t='ii';
    if($semester)    { $w[]='r.semester = ?'; $p[]=$semester; $t.='s'; }
    if($category)    { $w[]='i.category = ?'; $p[]=$category; $t.='s'; }
    if($indicator_id){ $w[]='i.indicator_id = ?'; $p[]=$indicator_id; $t.='i'; }
    $sql="SELECT i.name,i.category,i.unit,i.target_value,r.year,r.semester,r.actual_value,r.remarks
          FROM qa_records r JOIN qa_indicators i ON r.indicator_id=i.indicator_id
          WHERE ".implode(' AND ',$w)."
          ORDER BY i.category,i.name,r.year DESC,r.semester";
    $stmt=$conn->prepare($sql); $stmt->bind_param($t,...$p); $stmt->execute();
    $res=$stmt->get_result();
    while($row=$res->fetch_assoc()){
        $met = $row['actual_value'] >= $row['target_value'];
        $pct = $row['target_value'] > 0 ? (($row['actual_value'] / $row['target_value']) * 100) : 0;
        $riskFlag = $pct >= 100 ? 'On Track' : ($pct >= 80 ? 'At Risk' : 'Off Track');
        $report_data[] = [
            htmlspecialchars($row['name']),
            htmlspecialchars($row['category']),
            htmlspecialchars($row['unit']),
            number_format($row['target_value'],2),
            $row['year'], $row['semester'],
            number_format($row['actual_value'],2),
            ($row['actual_value']-$row['target_value'] >= 0 ? '+':'').number_format($row['actual_value']-$row['target_value'],2),
            $met ? 'Met' : 'Below Target',
            $riskFlag,
            htmlspecialchars($row['remarks']??'')
        ];
    }
}
elseif($report_type === 'survey_summary'){
    $report_headers = ['Survey','Audience','Status','Total Responses','Avg Rating','Questions','Period'];
    $w=['1=1']; $p=[]; $t='';
    if($survey_id){ $w[]='s.survey_id = ?'; $p[]=$survey_id; $t.='i'; }
    $sql="SELECT s.*,
          (SELECT COUNT(*) FROM survey_responses r WHERE r.survey_id=s.survey_id) as resp_count,
          (SELECT AVG(a.rating) FROM survey_answers a JOIN survey_responses r ON a.response_id=r.response_id WHERE r.survey_id=s.survey_id AND a.rating IS NOT NULL) as avg_r,
          (SELECT COUNT(*) FROM survey_questions q WHERE q.survey_id=s.survey_id) as q_count
          FROM surveys s WHERE ".implode(' AND ',$w)." ORDER BY s.created_date DESC";
    $stmt=$conn->prepare($sql); if($t) $stmt->bind_param($t,...$p); $stmt->execute();
    $res=$stmt->get_result();
    while($row=$res->fetch_assoc()){
        $report_data[] = [
            htmlspecialchars($row['title']),
            htmlspecialchars($row['target_audience']),
            $row['status'],
            $row['resp_count'],
            $row['avg_r'] ? number_format($row['avg_r'],2).'★' : '—',
            $row['q_count'],
            ($row['start_date']??'?').' – '.($row['end_date']??'?')
        ];
    }
}
elseif($report_type === 'response_detail'){
    $report_headers = ['Survey','Question','Type','Answer / Rating','Respondent','Role','Date'];
    $w=['1=1']; $p=[]; $t='';
    if($survey_id){ $w[]='sr.survey_id = ?'; $p[]=$survey_id; $t.='i'; }
    $sql="SELECT s.title,sq.question_text,sq.question_type,sa.answer_text,sa.rating,
          sre.respondent_name,sre.respondent_role,sre.submitted_at
          FROM survey_answers sa
          JOIN survey_questions sq ON sa.question_id=sq.question_id
          JOIN survey_responses sre ON sa.response_id=sre.response_id
          JOIN surveys s ON sre.survey_id=s.survey_id
          WHERE ".implode(' AND ',$w)." ORDER BY sre.submitted_at DESC LIMIT 500";
    $stmt=$conn->prepare($sql); if($t) $stmt->bind_param($t,...$p); $stmt->execute();
    $res=$stmt->get_result();
    while($row=$res->fetch_assoc()){
        $ans = $row['question_type']==='rating' ? ($row['rating'].'★/5') : ($row['answer_text']??'—');
        $report_data[] = [
            htmlspecialchars($row['title']),
            htmlspecialchars($row['question_text']),
            $row['question_type'],
            $ans,
            htmlspecialchars($row['respondent_name']??'Anonymous'),
            htmlspecialchars($row['respondent_role']??'—'),
            date('M d, Y',strtotime($row['submitted_at']))
        ];
    }
}
elseif($report_type === 'indicator_trend'){
    $report_headers = ['Indicator','Category','Year','Semester','Target','Actual','% of Target','Risk Flag'];
    $w=['1=1']; $p=[]; $t='';
    if($indicator_id){ $w[]='i.indicator_id = ?'; $p[]=$indicator_id; $t.='i'; }
    if($category)    { $w[]='i.category = ?'; $p[]=$category; $t.='s'; }
    $sql="SELECT i.name,i.category,i.target_value,r.year,r.semester,r.actual_value
          FROM qa_records r JOIN qa_indicators i ON r.indicator_id=i.indicator_id
          WHERE ".implode(' AND ',$w)." ORDER BY i.name,r.year,r.semester";
    $stmt=$conn->prepare($sql); if($t) $stmt->bind_param($t,...$p); $stmt->execute();
    $res=$stmt->get_result();
    while($row=$res->fetch_assoc()){
        $pct = $row['target_value'] > 0 ? round(($row['actual_value']/$row['target_value'])*100,1) : 0;
        $riskFlag = $pct >= 100 ? 'On Track' : ($pct >= 80 ? 'At Risk' : 'Off Track');
        $report_data[] = [
            htmlspecialchars($row['name']),
            htmlspecialchars($row['category']),
            $row['year'],$row['semester'],
            number_format($row['target_value'],2),
            number_format($row['actual_value'],2),
            $pct.'%',
            $riskFlag
        ];
    }
}
elseif($report_type === 'audit_action_trace'){
        $report_headers = ['Audit ID','Audit Title','Standard','Audit Status','Scheduled Date','Findings','Total Actions','Open Actions','Closed Actions','Overdue Actions','Completion Rate'];
    $w=['1=1']; $p=[]; $t='';
        if($audit_status){ $w[]='a.status = ?'; $p[]=$audit_status; $t.='s'; }
        if($has_audit_standard_link && $standard_id > 0){ $w[]='a.standard_id = ?'; $p[]=$standard_id; $t.='i'; }

        if ($has_audit_standard_link) {
                $sql="SELECT
                                a.audit_id,
                                a.title,
                                s.title AS standard_title,
                                a.status,
                                a.scheduled_date,
                                a.findings,
                                COUNT(ap.action_id) AS total_actions,
                                SUM(CASE WHEN ap.status IN ('Open','In Progress','Pending Verification') THEN 1 ELSE 0 END) AS open_actions,
                                SUM(CASE WHEN ap.status = 'Closed' THEN 1 ELSE 0 END) AS closed_actions,
                                SUM(CASE WHEN ap.target_date < CURDATE() AND ap.status NOT IN ('Closed','Cancelled') THEN 1 ELSE 0 END) AS overdue_actions
                            FROM qa_audits a
                            LEFT JOIN qa_standards s ON a.standard_id = s.standard_id
                            LEFT JOIN qa_action_plans ap ON ap.audit_id = a.audit_id
                            WHERE ".implode(' AND ',$w)."
                            GROUP BY a.audit_id, a.title, s.title, a.status, a.scheduled_date, a.findings
                            ORDER BY a.scheduled_date DESC, a.audit_id DESC";
        } else {
                $sql="SELECT
                                a.audit_id,
                                a.title,
                                NULL AS standard_title,
                                a.status,
                                a.scheduled_date,
                                a.findings,
                                COUNT(ap.action_id) AS total_actions,
                                SUM(CASE WHEN ap.status IN ('Open','In Progress','Pending Verification') THEN 1 ELSE 0 END) AS open_actions,
                                SUM(CASE WHEN ap.status = 'Closed' THEN 1 ELSE 0 END) AS closed_actions,
                                SUM(CASE WHEN ap.target_date < CURDATE() AND ap.status NOT IN ('Closed','Cancelled') THEN 1 ELSE 0 END) AS overdue_actions
                            FROM qa_audits a
                            LEFT JOIN qa_action_plans ap ON ap.audit_id = a.audit_id
                            WHERE ".implode(' AND ',$w)."
                            GROUP BY a.audit_id, a.title, a.status, a.scheduled_date, a.findings
                            ORDER BY a.scheduled_date DESC, a.audit_id DESC";
        }

    $stmt=$conn->prepare($sql);
    if($t) $stmt->bind_param($t,...$p);
    $stmt->execute();
    $res=$stmt->get_result();
    while($row=$res->fetch_assoc()){
        $totalActions = (int)$row['total_actions'];
        $closedActions = (int)$row['closed_actions'];
        $completionRate = $totalActions > 0 ? round(($closedActions / $totalActions) * 100, 1).'%' : '0%';
        $report_data[] = [
            'AUD-' . str_pad((string)$row['audit_id'], 4, '0', STR_PAD_LEFT),
            htmlspecialchars($row['title']),
            htmlspecialchars($row['standard_title'] ?? '—'),
            htmlspecialchars($row['status']),
            $row['scheduled_date'] ? date('M d, Y', strtotime($row['scheduled_date'])) : '—',
            !empty(trim((string)$row['findings'])) ? 'Yes' : 'No',
            $totalActions,
            (int)$row['open_actions'],
            $closedActions,
            (int)$row['overdue_actions'],
            $completionRate
        ];
    }
}
elseif($report_type === 'standard_compliance'){
    $report_headers = ['Standard ID','Standard Title','Compliance Body','Category','Total Audits','Completed Audits','Audit Completion Rate','Linked Actions','Open Actions','Overdue Actions'];

    if ($has_audit_standard_link) {
        $w=['1=1']; $p=[]; $t='';
        if($standard_id > 0){ $w[]='s.standard_id = ?'; $p[]=$standard_id; $t.='i'; }
        if($audit_status){ $w[]='a.status = ?'; $p[]=$audit_status; $t.='s'; }

        $sql="SELECT
                s.standard_id,
                s.title,
                s.compliance_body,
                s.category,
                COUNT(DISTINCT a.audit_id) AS total_audits,
                COUNT(DISTINCT CASE WHEN a.status = 'Completed' THEN a.audit_id END) AS completed_audits,
                COUNT(DISTINCT ap.action_id) AS total_actions,
                COUNT(DISTINCT CASE WHEN ap.status IN ('Open','In Progress','Pending Verification') THEN ap.action_id END) AS open_actions,
                COUNT(DISTINCT CASE WHEN ap.target_date < CURDATE() AND ap.status NOT IN ('Closed','Cancelled') THEN ap.action_id END) AS overdue_actions
              FROM qa_standards s
              LEFT JOIN qa_audits a ON a.standard_id = s.standard_id
              LEFT JOIN qa_action_plans ap ON ap.audit_id = a.audit_id
              WHERE ".implode(' AND ',$w)."
              GROUP BY s.standard_id, s.title, s.compliance_body, s.category
              ORDER BY s.title";

        $stmt=$conn->prepare($sql);
        if($t) $stmt->bind_param($t,...$p);
        $stmt->execute();
        $res=$stmt->get_result();
        while($row=$res->fetch_assoc()){
            $totalAudits = (int)$row['total_audits'];
            $completedAudits = (int)$row['completed_audits'];
            $completionRate = $totalAudits > 0 ? round(($completedAudits / $totalAudits) * 100, 1).'%' : '0%';

            $report_data[] = [
                'STD-' . str_pad((string)$row['standard_id'], 4, '0', STR_PAD_LEFT),
                htmlspecialchars($row['title']),
                htmlspecialchars($row['compliance_body'] ?? '—'),
                htmlspecialchars($row['category'] ?? '—'),
                $totalAudits,
                $completedAudits,
                $completionRate,
                (int)$row['total_actions'],
                (int)$row['open_actions'],
                (int)$row['overdue_actions']
            ];
        }
    } else {
        $w=['1=1']; $p=[]; $t='';
        if($standard_id > 0){ $w[]='s.standard_id = ?'; $p[]=$standard_id; $t.='i'; }

        $sql="SELECT s.standard_id, s.title, s.compliance_body, s.category
              FROM qa_standards s
              WHERE ".implode(' AND ',$w)."
              ORDER BY s.title";
        $stmt=$conn->prepare($sql);
        if($t) $stmt->bind_param($t,...$p);
        $stmt->execute();
        $res=$stmt->get_result();
        while($row=$res->fetch_assoc()){
            $report_data[] = [
                'STD-' . str_pad((string)$row['standard_id'], 4, '0', STR_PAD_LEFT),
                htmlspecialchars($row['title']),
                htmlspecialchars($row['compliance_body'] ?? '—'),
                htmlspecialchars($row['category'] ?? '—'),
                0,
                0,
                '0%',
                0,
                0,
                0
            ];
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-bar-chart-line me-2 text-accent"></i>QA Reports</h1>
        <p>Generate detailed, filterable reports. Export to PDF or CSV.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn-qa btn-qa-secondary" onclick="exportCSV()"><i class="bi bi-filetype-csv"></i> CSV</button>
        <button class="btn-qa btn-qa-primary" onclick="exportPDF()"><i class="bi bi-file-earmark-pdf"></i> PDF</button>
    </div>
</div>

<!-- Report Filters -->
<div class="qa-card mb-4" style="padding:20px">
    <div class="qa-card-title mb-3">Report Configuration</div>
    <form method="GET" id="reportForm">
    <div class="row g-3">
        <div class="col-md-4">
            <label class="qa-form-label">Report Type *</label>
            <select name="report_type" id="report_type" class="qa-form-control" onchange="toggleFilters()">
                <option value="kpi_summary"    <?= $report_type==='kpi_summary'    ?'selected':'' ?>>KPI Performance Summary</option>
                <option value="indicator_trend"<?= $report_type==='indicator_trend'?'selected':'' ?>>Indicator Trend Analysis</option>
                <option value="survey_summary" <?= $report_type==='survey_summary' ?'selected':'' ?>>Survey Summary</option>
                <option value="response_detail"<?= $report_type==='response_detail'?'selected':'' ?>>Response Detail</option>
                <option value="audit_action_trace"<?= $report_type==='audit_action_trace'?'selected':'' ?>>Audit-Action Plan Traceability</option>
                <option value="standard_compliance"<?= $report_type==='standard_compliance'?'selected':'' ?>>Standards Compliance Summary</option>
            </select>
        </div>

        <!-- KPI filters -->
        <div class="col-md-2 filter-kpi">
            <label class="qa-form-label">Year From</label>
            <input type="number" name="year_from" class="qa-form-control" value="<?= $year_from ?>" min="2000" max="2099">
        </div>
        <div class="col-md-2 filter-kpi">
            <label class="qa-form-label">Year To</label>
            <input type="number" name="year_to" class="qa-form-control" value="<?= $year_to ?>" min="2000" max="2099">
        </div>
        <div class="col-md-2 filter-kpi">
            <label class="qa-form-label">Semester</label>
            <select name="semester" class="qa-form-control">
                <option value="">All</option>
                <option value="1st"    <?= $semester==='1st'?'selected':'' ?>>1st</option>
                <option value="2nd"    <?= $semester==='2nd'?'selected':'' ?>>2nd</option>
                <option value="Summer" <?= $semester==='Summer'?'selected':'' ?>>Summer</option>
                <option value="Annual" <?= $semester==='Annual'?'selected':'' ?>>Annual</option>
            </select>
        </div>
        <div class="col-md-2 filter-kpi">
            <label class="qa-form-label">Category</label>
            <select name="category" class="qa-form-control">
                <option value="">All</option>
                <?php foreach($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat) ?>" <?= $category===$cat?'selected':'' ?>><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 filter-kpi filter-trend">
            <label class="qa-form-label">Indicator</label>
            <select name="indicator_id" class="qa-form-control">
                <option value="">All Indicators</option>
                <?php $indicator_list->data_seek(0); while($i=$indicator_list->fetch_assoc()): ?>
                <option value="<?= $i['indicator_id'] ?>" <?= $indicator_id==$i['indicator_id']?'selected':'' ?>><?= htmlspecialchars($i['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Survey filters -->
        <div class="col-md-4 filter-survey">
            <label class="qa-form-label">Survey</label>
            <select name="survey_id" class="qa-form-control">
                <option value="">All Surveys</option>
                <?php $survey_list->data_seek(0); while($s=$survey_list->fetch_assoc()): ?>
                <option value="<?= $s['survey_id'] ?>" <?= $survey_id==$s['survey_id']?'selected':'' ?>><?= htmlspecialchars($s['title']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Audit filters -->
        <div class="col-md-3 filter-audit">
            <label class="qa-form-label">Audit Status</label>
            <select name="audit_status" class="qa-form-control">
                <option value="">All</option>
                <option value="Pending" <?= $audit_status==='Pending'?'selected':'' ?>>Pending</option>
                <option value="In Progress" <?= $audit_status==='In Progress'?'selected':'' ?>>In Progress</option>
                <option value="Completed" <?= $audit_status==='Completed'?'selected':'' ?>>Completed</option>
                <option value="Cancelled" <?= $audit_status==='Cancelled'?'selected':'' ?>>Cancelled</option>
            </select>
        </div>
        <div class="col-md-5 filter-audit filter-standard">
            <label class="qa-form-label">Standard</label>
            <select name="standard_id" class="qa-form-control">
                <option value="">All Standards</option>
                <?php if($standards_list): $standards_list->data_seek(0); while($st=$standards_list->fetch_assoc()): ?>
                <option value="<?= $st['standard_id'] ?>" <?= $standard_id==(int)$st['standard_id']?'selected':'' ?>><?= htmlspecialchars($st['title']) ?></option>
                <?php endwhile; endif; ?>
            </select>
        </div>

        <div class="col-12">
            <button type="submit" class="btn-qa btn-qa-primary"><i class="bi bi-play-fill"></i> Generate Report</button>
        </div>
    </div>
    </form>
</div>

<!-- Report Output -->
<div class="qa-card p-0" id="reportOutput">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
        <div>
            <div class="fw-600"><?= [
                'kpi_summary'=>'KPI Performance Summary',
                'indicator_trend'=>'Indicator Trend Analysis',
                'survey_summary'=>'Survey Summary',
                'response_detail'=>'Response Detail',
                'audit_action_trace'=>'Audit-Action Plan Traceability',
                'standard_compliance'=>'Standards Compliance Summary'
            ][$report_type] ?? 'Report' ?></div>
            <div class="text-muted-qa" style="font-size:0.8rem">Generated: <?= date('F d, Y \a\t h:i A') ?> · <?= count($report_data) ?> records</div>
        </div>
        <span class="badge-status badge-active">PLSP QA Office</span>
    </div>
    <div class="qa-table-wrapper" style="border:none;border-radius:0 0 var(--radius) var(--radius);overflow-x:auto">
        <table class="qa-table" id="reportTable">
            <thead>
                <tr>
                    <?php foreach($report_headers as $h): ?><th><?= $h ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($report_data)): ?>
                <tr><td colspan="<?= count($report_headers) ?>">
                    <div class="empty-state"><i class="bi bi-inbox"></i><p>No data for selected filters</p></div>
                </td></tr>
                <?php else: ?>
                <?php foreach($report_data as $row): ?>
                <tr>
                    <?php foreach($row as $cell): ?>
                    <td><?= $cell ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$report_type_label = [
    'kpi_summary'=>'KPI Performance Summary',
    'indicator_trend'=>'Indicator Trend Analysis',
    'survey_summary'=>'Survey Summary',
    'response_detail'=>'Response Detail',
    'audit_action_trace'=>'Audit-Action Plan Traceability',
    'standard_compliance'=>'Standards Compliance Summary'
][$report_type] ?? 'Report';

$extra_js = '<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script>
const REPORT_LABEL = '.json_encode($report_type_label).';

function toggleFilters(){
    const type = $("#report_type").val();
    $(".filter-kpi").toggle(type==="kpi_summary"||type==="indicator_trend");
    $(".filter-trend").toggle(type==="indicator_trend");
    $(".filter-survey").toggle(type==="survey_summary"||type==="response_detail");
    $(".filter-audit").toggle(type==="audit_action_trace"||type==="standard_compliance");
}
$(toggleFilters);

function exportCSV(){
    const table = document.getElementById("reportTable");
    if(!table){ showToast("No data to export","error"); return; }
    let csv = [];
    table.querySelectorAll("tr").forEach(row => {
        const cells = [...row.querySelectorAll("th,td")].map(c => `"${c.innerText.replace(/"/g,"\\\"").replace(/\\n/g," ")}"`);
        csv.push(cells.join(","));
    });
    const blob = new Blob([csv.join("\\n")], {type:"text/csv;charset=utf-8;"});
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `PLSP_QA_${REPORT_LABEL.replace(/ /g,"_")}_${new Date().toISOString().slice(0,10)}.csv`;
    link.click();
    showToast("CSV exported successfully","success");
}

function exportPDF(){
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: "landscape", unit: "mm", format: "a4" });

    // Header
    doc.setFontSize(14); doc.setFont(undefined,"bold");
    doc.text("Pamantasan ng Lungsod ng San Pablo", 14, 16);
    doc.setFontSize(11); doc.setFont(undefined,"normal");
    doc.text("Quality Assurance Management System – " + REPORT_LABEL, 14, 23);
    doc.setFontSize(9);
    doc.text("Generated: " + new Date().toLocaleDateString("en-US",{year:"numeric",month:"long",day:"numeric",hour:"2-digit",minute:"2-digit"}), 14, 29);
    doc.text("ByteBandits Development Team", 14, 34);

    const table = document.getElementById("reportTable");
    const headers = [...table.querySelectorAll("thead th")].map(h=>h.innerText);
    const rows = [...table.querySelectorAll("tbody tr")].map(tr =>
        [...tr.querySelectorAll("td")].map(td=>td.innerText.replace(/\\n/g," "))
    ).filter(r=>r.length > 1);

    if(rows.length === 0){ showToast("No data to export","error"); return; }

    doc.autoTable({
        startY: 38,
        head: [headers],
        body: rows,
        styles: { fontSize: 8, cellPadding: 2 },
        headStyles: { fillColor: [79,142,247], textColor: 255, fontStyle: "bold" },
        alternateRowStyles: { fillColor: [245,247,255] },
        theme: "grid"
    });

    doc.save(`PLSP_QA_${REPORT_LABEL.replace(/ /g,"_")}_${new Date().toISOString().slice(0,10)}.pdf`);
    showToast("PDF exported successfully","success");
}
</script>';
require_once __DIR__ . '/../includes/footer.php';
?>
