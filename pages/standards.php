<?php
// pages/standards.php - Standards & Policies Management
require_once __DIR__ . '/../config/database.php';

$page_title = 'Standards & Policies';
$conn = getConnection();

$per_page = 10;
$page = max(1, (int)($_GET['page'] ?? 1));

$type_filter = strtolower(trim($_GET['type'] ?? 'standards'));
if (!in_array($type_filter, ['standards', 'policies'], true)) {
    $type_filter = 'standards';
}

$is_policy = $type_filter === 'policies';
$status_filter = trim($_GET['status'] ?? '');

$allowed_statuses = $is_policy
    ? ['Draft', 'Active', 'Archived']
    : ['Active', 'Archived'];

if ($status_filter !== '' && !in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = '';
}

$table_alias = $is_policy ? 'p' : 's';

$record_label = $is_policy ? 'Policy' : 'Standard';
$record_label_plural = $is_policy ? 'Policies' : 'Standards';
$body_label = $is_policy ? 'Owner' : 'Compliance Body';
$review_label = $is_policy ? 'Last Reviewed' : 'Review Date';

$where = [];
$params = [];
$types = '';

if ($status_filter !== '') {
    $where[] = $table_alias . '.status = ?';
    $params[] = $status_filter;
    $types .= 's';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count_sql = $is_policy
    ? "SELECT COUNT(*) AS c FROM qa_policies p {$where_sql}"
    : "SELECT COUNT(*) AS c FROM qa_standards s {$where_sql}";

$count_stmt = $conn->prepare($count_sql);
if ($types !== '') {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total = (int)($count_stmt->get_result()->fetch_assoc()['c'] ?? 0);

$total_pages = max(1, (int)ceil($total / $per_page));
if ($page > $total_pages) {
    $page = $total_pages;
}

$offset = ($page - 1) * $per_page;

if ($is_policy) {
    $list_sql = "
        SELECT
            p.policy_id AS rec_id,
            p.title,
            p.description,
            p.category,
            p.status,
            p.effective_date,
            p.expiry_date,
            p.owner AS body_name,
            p.last_reviewed AS review_date,
            p.standard_id,
            p.document_url,
            p.version,
            st.title AS standard_title
        FROM qa_policies p
        LEFT JOIN qa_standards st ON p.standard_id = st.standard_id
        {$where_sql}
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?
    ";
} else {
    $list_sql = "
        SELECT
            s.standard_id AS rec_id,
            s.title,
            s.description,
            s.category,
            s.status,
            s.effective_date,
            NULL AS expiry_date,
            s.compliance_body AS body_name,
            s.review_date AS review_date,
            NULL AS standard_id,
            NULL AS document_url,
            NULL AS version,
            NULL AS standard_title
        FROM qa_standards s
        {$where_sql}
        ORDER BY s.created_at DESC
        LIMIT ? OFFSET ?
    ";
}

$list_stmt = $conn->prepare($list_sql);
$list_params = array_merge($params, [$per_page, $offset]);
$list_stmt->bind_param($types . 'ii', ...$list_params);
$list_stmt->execute();

$records = [];
$result = $list_stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $records[] = $row;
}

$policy_standard_options = [];
$policy_standard_result = $conn->query('SELECT standard_id, title FROM qa_standards ORDER BY title ASC');
if ($policy_standard_result) {
    while ($row = $policy_standard_result->fetch_assoc()) {
        $policy_standard_options[] = $row;
    }
}

$status_badges = [
    'Draft' => 'badge-draft',
    'Active' => 'badge-active',
    'Archived' => 'badge-inactive',
];

$format_date = static function (?string $value): string {
    if ($value === null || $value === '') {
        return 'N/A';
    }

    $ts = strtotime($value);
    if ($ts === false) {
        return 'N/A';
    }

    return date('M d, Y', $ts);
};

require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1 d-flex align-items-center gap-2">
            <i class="bi bi-file-earmark-text text-accent"></i>
            Standards & Policies
        </h1>
        <p class="text-muted-qa mb-0">
            Manage accreditation standards, quality policies, and procedural documentation.
        </p>
    </div>
    <button
        type="button"
        class="btn btn-primary d-inline-flex align-items-center gap-2"
        data-bs-toggle="modal"
        data-bs-target="#stdModal"
        onclick="openAdd()"
    >
        <i class="bi bi-plus-lg"></i>
        Add <?= htmlspecialchars($record_label) ?>
    </button>
</div>

<ul class="nav nav-pills gap-2 mb-3">
    <li class="nav-item">
        <a href="standards.php?type=standards" class="nav-link <?= $is_policy ? '' : 'active' ?>">
            <i class="bi bi-bookmark me-1"></i>
            Standards
        </a>
    </li>
    <li class="nav-item">
        <a href="standards.php?type=policies" class="nav-link <?= $is_policy ? 'active' : '' ?>">
            <i class="bi bi-file-text me-1"></i>
            Policies & Procedures
        </a>
    </li>
</ul>

<div class="card border-0 mb-3" style="background:var(--bg-card);border:1px solid var(--border) !important;">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-sm-6 col-lg-4">
                <label for="f-status" class="form-label mb-1">Status</label>
                <select id="f-status" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($allowed_statuses as $status_option): ?>
                        <option value="<?= htmlspecialchars($status_option) ?>" <?= $status_filter === $status_option ? 'selected' : '' ?>>
                            <?= htmlspecialchars($status_option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-lg-8 d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" onclick="applyFilters()">
                    <i class="bi bi-funnel"></i>
                    Filter
                </button>
                <a href="standards.php?type=<?= urlencode($type_filter) ?>" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                    <i class="bi bi-x-circle"></i>
                    Clear
                </a>
            </div>
        </div>
    </div>
</div>

<div class="qa-card p-0">
    <div class="qa-table-wrapper table-responsive" style="border:none;border-radius:var(--radius)">
        <table class="table qa-table table-sm align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th><?= htmlspecialchars($body_label) ?></th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Effective Date</th>
                    <th><?= htmlspecialchars($review_label) ?></th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($records)): ?>
                    <tr>
                        <td colspan="8"><div class="empty-state"><i class="bi bi-inbox"></i><p>No <?= htmlspecialchars(strtolower($record_label_plural)) ?> found</p></div></td>
                    </tr>
                <?php else: ?>
                    <?php $n = $offset + 1; ?>
                    <?php foreach ($records as $rec): ?>
                        <?php
                        $description = trim((string)($rec['description'] ?? ''));
                        $desc_preview = $description;
                        if ($desc_preview === '') {
                            $desc_preview = 'No description provided.';
                        } elseif (strlen($desc_preview) > 80) {
                            $desc_preview = substr($desc_preview, 0, 80) . '...';
                        }

                        $status_value = (string)($rec['status'] ?? '');
                        $status_class = $status_badges[$status_value] ?? 'badge-inactive';
                        ?>
                        <tr>
                            <td class="text-muted-qa mono"><?= $n++ ?></td>
                            <td>
                                <div class="fw-600"><?= htmlspecialchars((string)($rec['title'] ?? '')) ?></div>
                                <div class="text-muted-qa" style="font-size:0.78rem;margin-top:2px"><?= htmlspecialchars($desc_preview) ?></div>
                                <?php if ($is_policy): ?>
                                    <div class="text-muted-qa" style="font-size:0.74rem;margin-top:2px">
                                        Version <?= htmlspecialchars((string)(($rec['version'] ?? '') !== '' ? $rec['version'] : 'N/A')) ?>
                                        <?php if (!empty($rec['standard_title'])): ?>
                                            • Standard: <?= htmlspecialchars((string)$rec['standard_title']) ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars((string)($rec['body_name'] ?? 'N/A')) ?></td>
                            <td><span class="badge-status badge-pending"><?= htmlspecialchars((string)($rec['category'] ?? 'N/A')) ?></span></td>
                            <td>
                                <span class="badge-status <?= $status_class ?>">
                                    <?= htmlspecialchars($status_value === '' ? 'N/A' : $status_value) ?>
                                </span>
                            </td>
                            <td class="mono"><?= htmlspecialchars($format_date($rec['effective_date'] ?? null)) ?></td>
                            <td class="mono"><?= htmlspecialchars($format_date($rec['review_date'] ?? null)) ?></td>
                            <td>
                                <div class="d-flex gap-1">
                                    <?php if (!$is_policy): ?>
                                        <a
                                            class="btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon"
                                            href="/qa_system/pages/audits.php?new_from_standard=<?= (int)($rec['rec_id'] ?? 0) ?>"
                                            title="Create audit from standard"
                                        >
                                            <i class="bi bi-clipboard-plus"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button
                                        type="button"
                                        class="btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon"
                                        onclick='viewRecord(<?= json_encode($rec, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'
                                        title="View"
                                    >
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-qa btn-qa-secondary btn-qa-sm btn-qa-icon"
                                        onclick='editRecord(<?= json_encode($rec, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'
                                        title="Edit"
                                    >
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button
                                        type="button"
                                        class="btn-qa btn-qa-danger btn-qa-sm btn-qa-icon"
                                        onclick="deleteRecord(<?= (int)($rec['rec_id'] ?? 0) ?>)"
                                        title="Delete"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
    <span class="text-muted-qa">Showing <?= min($offset + 1, $total) ?>-<?= min($offset + $per_page, $total) ?> of <?= $total ?> records</span>
    <div id="paginationContainer" class="qa-pagination"></div>
</div>

<div class="modal fade" id="viewStdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <p class="text-muted-qa mb-1">Title</p>
                        <p class="fw-600 mb-0" id="view_rec_title">N/A</p>
                    </div>
                    <div class="col-md-4">
                        <p class="text-muted-qa mb-1">Status</p>
                        <p class="mb-0" id="view_rec_status">N/A</p>
                    </div>
                    <div class="col-md-4">
                        <p class="text-muted-qa mb-1"><?= htmlspecialchars($body_label) ?></p>
                        <p class="mb-0" id="view_rec_body">N/A</p>
                    </div>
                    <div class="col-md-4">
                        <p class="text-muted-qa mb-1">Category</p>
                        <p class="mb-0" id="view_rec_category">N/A</p>
                    </div>
                    <div class="col-md-4">
                        <p class="text-muted-qa mb-1">Effective Date</p>
                        <p class="mono mb-0" id="view_rec_effective">N/A</p>
                    </div>
                    <div class="col-md-4">
                        <p class="text-muted-qa mb-1"><?= htmlspecialchars($review_label) ?></p>
                        <p class="mono mb-0" id="view_rec_review">N/A</p>
                    </div>
                    <div class="col-md-4 d-none" id="view_policy_version_col">
                        <p class="text-muted-qa mb-1">Version</p>
                        <p class="mono mb-0" id="view_rec_version">N/A</p>
                    </div>
                    <div class="col-md-4 d-none" id="view_policy_expiry_col">
                        <p class="text-muted-qa mb-1">Expiry Date</p>
                        <p class="mono mb-0" id="view_rec_expiry">N/A</p>
                    </div>
                    <div class="col-md-4 d-none" id="view_policy_standard_col">
                        <p class="text-muted-qa mb-1">Linked Standard</p>
                        <p class="mb-0" id="view_rec_standard">N/A</p>
                    </div>
                    <div class="col-12 d-none" id="view_policy_document_col">
                        <p class="text-muted-qa mb-1">Document</p>
                        <p class="mb-2" id="view_rec_document">N/A</p>
                        <div class="d-none mb-2 gap-2 flex-wrap" id="view_policy_document_actions">
                            <a id="view_rec_document_open" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener noreferrer">
                                <i class="bi bi-box-arrow-up-right me-1"></i>
                                Open Document
                            </a>
                            <a id="view_rec_document_download" class="btn btn-sm btn-outline-secondary" download>
                                <i class="bi bi-download me-1"></i>
                                Download
                            </a>
                        </div>
                        <div class="d-none" id="view_policy_document_preview">
                            <div class="border rounded overflow-hidden" style="background:var(--bg-secondary);height:240px;max-width:420px;">
                                <iframe id="view_rec_document_frame" title="Policy document preview" src="about:blank" style="width:100%;height:100%;border:0;"></iframe>
                            </div>
                            <small class="text-muted-qa d-block mt-1">Compact preview only. Use Open Document for full view.</small>
                        </div>
                    </div>
                    <div class="col-12">
                        <p class="text-muted-qa mb-1">Description</p>
                        <p class="mb-0" id="view_rec_description" style="line-height:1.6">N/A</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="stdModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stdModalTitle">Add <?= htmlspecialchars($record_label) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rec_id">
                <input type="hidden" id="rec_type" value="<?= htmlspecialchars($type_filter) ?>">

                <div class="row g-3">
                    <div class="col-12">
                        <label for="rec_title" class="form-label">Title *</label>
                        <input type="text" id="rec_title" class="form-control" placeholder="Enter title" maxlength="200">
                    </div>

                    <div class="col-12">
                        <label for="rec_desc" class="form-label">Description</label>
                        <textarea id="rec_desc" class="form-control" rows="3" placeholder="Enter description"></textarea>
                    </div>

                    <div class="col-md-4">
                        <label for="rec_body" class="form-label"><?= htmlspecialchars($body_label) ?> *</label>
                        <input type="text" id="rec_body" class="form-control" placeholder="Enter <?= strtolower($body_label) ?>" maxlength="100">
                    </div>

                    <div class="col-md-4">
                        <label for="rec_category" class="form-label">Category *</label>
                        <input type="text" id="rec_category" class="form-control" placeholder="Enter category" maxlength="100">
                    </div>

                    <div class="col-md-4">
                        <label for="rec_status" class="form-label">Status</label>
                        <select id="rec_status" class="form-select">
                            <?php foreach ($allowed_statuses as $status_option): ?>
                                <option value="<?= htmlspecialchars($status_option) ?>"><?= htmlspecialchars($status_option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 <?= $is_policy ? '' : 'd-none' ?>" id="policyStandardCol">
                        <label for="rec_standard_id" class="form-label">Linked Standard</label>
                        <select id="rec_standard_id" class="form-select">
                            <option value="">Not linked</option>
                            <?php foreach ($policy_standard_options as $std_opt): ?>
                                <option value="<?= (int)$std_opt['standard_id'] ?>"><?= htmlspecialchars((string)$std_opt['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 <?= $is_policy ? '' : 'd-none' ?>" id="policyVersionCol">
                        <label for="rec_version" class="form-label">Version</label>
                        <input type="text" id="rec_version" class="form-control" maxlength="20" placeholder="e.g. 1.0">
                    </div>

                    <div class="col-md-4 <?= $is_policy ? '' : 'd-none' ?>" id="policyExpiryCol">
                        <label for="rec_expiry" class="form-label">Expiry Date</label>
                        <input type="date" id="rec_expiry" class="form-control">
                    </div>

                    <div class="col-12 <?= $is_policy ? '' : 'd-none' ?>" id="policyDocumentCol">
                        <label for="rec_document_file" class="form-label">Document File (PDF)</label>
                        <input type="file" id="rec_document_file" class="form-control" accept="application/pdf,.pdf">
                        <small class="text-muted-qa d-block mt-1">Upload a PDF file. When editing, leave this blank to keep the current document.</small>
                        <div class="mt-2 d-none" id="rec_document_current_wrap">
                            <small class="text-muted-qa">Current document:</small>
                            <a id="rec_document_current_link" class="ms-1" target="_blank" rel="noopener noreferrer"></a>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="rec_date" class="form-label">Effective Date</label>
                        <input type="date" id="rec_date" class="form-control">
                    </div>

                    <div class="col-md-6" id="reviewCol">
                        <label for="rec_review" class="form-label"><?= htmlspecialchars($review_label) ?></label>
                        <input type="date" id="rec_review" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" onclick="saveRecord()">
                    <i class="bi bi-save"></i>
                    Save
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$type_js = json_encode($type_filter, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$record_label_js = json_encode($record_label, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$body_label_js = json_encode($body_label, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$is_policy_js = $is_policy ? 'true' : 'false';
$current_page_js = (int)$page;
$total_pages_js = (int)$total_pages;

$extra_js = <<<SCRIPT
<script>
const currentPage = {$current_page_js};
const totalPages = {$total_pages_js};

const standardsState = {
    type: {$type_js},
    recordLabel: {$record_label_js},
    bodyLabel: {$body_label_js},
    isPolicy: {$is_policy_js}
};

$(function () {
    buildPagination('paginationContainer', currentPage, totalPages, 'goPage');
    togglePolicyFields();
});

function goPage(p) {
    const url = new URL(window.location.href);
    const status = $('#f-status').val();

    url.searchParams.set('type', standardsState.type);
    url.searchParams.set('page', p);

    if (status) {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }

    window.location.href = url.toString();
}

function applyFilters() {
    const url = new URL(window.location.href);
    const status = $('#f-status').val();

    url.searchParams.set('type', standardsState.type);

    if (status) {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }

    url.searchParams.delete('page');
    window.location.href = url.toString();
}

function togglePolicyFields() {
    const hidePolicyOnly = !standardsState.isPolicy;
    $('#policyStandardCol, #policyVersionCol, #policyExpiryCol, #policyDocumentCol').toggleClass('d-none', hidePolicyOnly);
}

function formatDateValue(value) {
    const raw = String(value || '').trim();
    if (!raw) return 'N/A';

    const parsed = new Date(raw + 'T00:00:00');
    if (Number.isNaN(parsed.getTime())) {
        return raw;
    }

    return parsed.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: '2-digit'
    });
}

function normalizeDocumentUrl(value) {
    const raw = String(value || '').trim();
    if (!raw || raw.startsWith('//')) {
        return '';
    }

    if (/^https?:\/\//i.test(raw) || raw.startsWith('/') || raw.startsWith('./') || raw.startsWith('../')) {
        return raw;
    }

    return '/' + raw.replace(/^\/+/, '');
}

function isPdfDocumentUrl(url) {
    const normalized = String(url || '').trim().toLowerCase();
    if (!normalized) {
        return false;
    }

    return /\.pdf($|[?#&])/i.test(normalized)
        || /[?&]format=pdf(&|$)/i.test(normalized)
        || /\/raw\/download/i.test(normalized);
}

function viewRecord(data) {
    const status = String(data.status || '').trim() || 'N/A';
    let statusClass = 'badge-inactive';
    if (status === 'Active') statusClass = 'badge-active';
    if (status === 'Draft') statusClass = 'badge-draft';

    $('#view_rec_title').text(data.title || 'N/A');
    $('#view_rec_body').text(data.body_name || 'N/A');
    $('#view_rec_category').text(data.category || 'N/A');
    $('#view_rec_effective').text(formatDateValue(data.effective_date));
    $('#view_rec_review').text(formatDateValue(data.review_date));
    $('#view_rec_description').text((data.description || '').trim() || 'N/A');

    $('#view_rec_status').empty().append(
        $('<span>').addClass('badge-status ' + statusClass).text(status)
    );

    const policyViewCols = $('#view_policy_version_col, #view_policy_expiry_col, #view_policy_standard_col, #view_policy_document_col');
    policyViewCols.toggleClass('d-none', !standardsState.isPolicy);

    if (standardsState.isPolicy) {
        $('#view_rec_version').text((String(data.version || '').trim()) || 'N/A');
        $('#view_rec_expiry').text(formatDateValue(data.expiry_date));
        $('#view_rec_standard').text((String(data.standard_title || '').trim()) || 'Not linked');

        const documentContainer = $('#view_rec_document');
        const documentActions = $('#view_policy_document_actions');
        const documentPreview = $('#view_policy_document_preview');
        const documentFrame = $('#view_rec_document_frame');
        const documentOpen = $('#view_rec_document_open');
        const documentDownload = $('#view_rec_document_download');
        const documentUrl = normalizeDocumentUrl(data.document_url);

        documentContainer.text('N/A');
        documentActions.addClass('d-none').removeClass('d-flex');
        documentPreview.addClass('d-none');
        documentFrame.attr('src', 'about:blank');
        documentOpen.removeAttr('href');
        documentDownload.removeAttr('href');

        if (documentUrl) {
            const isSafeLink = /^(https?:\/\/|\/|\.\/|\.\.\/)/i.test(documentUrl);
            if (isSafeLink) {
                documentContainer.text('PDF document is available.');

                documentOpen.attr('href', documentUrl);
                documentDownload.attr('href', documentUrl);
                documentActions.removeClass('d-none').addClass('d-flex');

                if (isPdfDocumentUrl(documentUrl)) {
                    const previewUrl = documentUrl.includes('#')
                        ? documentUrl
                        : documentUrl + '#page=1&zoom=page-width&toolbar=0&navpanes=0&scrollbar=0';
                    documentFrame.attr('src', previewUrl);
                    documentPreview.removeClass('d-none');
                }
            } else {
                documentContainer.text('Document is available. Use Open Document.');
            }
        }
    }

    new bootstrap.Modal(document.getElementById('viewStdModal')).show();
}

function openAdd() {
    $('#stdModalTitle').text('Add ' + standardsState.recordLabel);
    $('#rec_id').val('');
    $('#rec_type').val(standardsState.type);
    $('#rec_title').val('');
    $('#rec_desc').val('');
    $('#rec_body').val('');
    $('#rec_category').val('');
    $('#rec_date').val('');
    $('#rec_review').val('');
    $('#rec_standard_id').val('');
    $('#rec_document_file').val('');
    $('#rec_expiry').val('');
    $('#rec_version').val('1.0');
    $('#rec_document_current_wrap').addClass('d-none');
    $('#rec_document_current_link').removeAttr('href').text('');

    const firstStatus = $('#rec_status option:first').val();
    $('#rec_status').val(firstStatus);

    togglePolicyFields();
}

function editRecord(data) {
    $('#stdModalTitle').text('Edit ' + standardsState.recordLabel);
    $('#rec_id').val(data.rec_id || '');
    $('#rec_type').val(standardsState.type);
    $('#rec_title').val(data.title || '');
    $('#rec_desc').val(data.description || '');
    $('#rec_body').val(data.body_name || '');
    $('#rec_category').val(data.category || '');
    $('#rec_status').val(data.status || $('#rec_status option:first').val());
    $('#rec_date').val(data.effective_date || '');
    $('#rec_review').val(data.review_date || '');
    $('#rec_standard_id').val(data.standard_id || '');
    $('#rec_document_file').val('');
    $('#rec_expiry').val(data.expiry_date || '');
    $('#rec_version').val(data.version || '1.0');

    const currentDocumentUrl = normalizeDocumentUrl(data.document_url);
    if (standardsState.isPolicy && currentDocumentUrl) {
        $('#rec_document_current_link').attr('href', currentDocumentUrl).text('Open current document');
        $('#rec_document_current_wrap').removeClass('d-none');
    } else {
        $('#rec_document_current_link').removeAttr('href').text('');
        $('#rec_document_current_wrap').addClass('d-none');
    }

    togglePolicyFields();

    new bootstrap.Modal(document.getElementById('stdModal')).show();
}

function saveRecord() {
    const title = $('#rec_title').val().trim();
    const body = $('#rec_body').val().trim();
    const category = $('#rec_category').val().trim();
    const recId = $('#rec_id').val();
    const fileInput = document.getElementById('rec_document_file');
    const selectedDocument = fileInput && fileInput.files && fileInput.files.length > 0 ? fileInput.files[0] : null;

    if (!title || !body || !category) {
        showToast('Title, ' + standardsState.bodyLabel + ', and Category are required.', 'error');
        return;
    }

    if (standardsState.isPolicy && !recId && !selectedDocument) {
        showToast('Please upload a policy document (PDF).', 'error');
        return;
    }

    if (selectedDocument && !/\.pdf$/i.test(selectedDocument.name)) {
        showToast('Only PDF files are allowed for policy documents.', 'error');
        return;
    }

    const payload = {
        action: recId ? 'update' : 'create',
        rec_id: recId,
        type: standardsState.type,
        title: title,
        description: $('#rec_desc').val(),
        body: body,
        category: category,
        status: $('#rec_status').val(),
        effective_date: $('#rec_date').val(),
        review_date: $('#rec_review').val(),
        standard_id: $('#rec_standard_id').val(),
        version: ($('#rec_version').val() || '').trim(),
        expiry_date: $('#rec_expiry').val()
    };

    let requestData = payload;
    if (selectedDocument) {
        const formData = new FormData();
        Object.keys(payload).forEach(function (key) {
            formData.append(key, payload[key] || '');
        });
        formData.append('document_file', selectedDocument);
        requestData = formData;
    }

    qaAjax('/qa_system/api/standards.php', requestData, function () {
        window.location.reload();
    });
}

function deleteRecord(id) {
    confirmDelete('/qa_system/api/standards.php', {
        action: 'delete',
        type: standardsState.type,
        rec_id: id
    }, function () {
        window.location.reload();
    });
}
</script>
SCRIPT;

require_once __DIR__ . '/../includes/footer.php';
?>
