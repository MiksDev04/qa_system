<?php
// api/standards.php - Standards & Policies API
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/cloudinary.php';
header('Content-Type: application/json');

$conn = getConnection();
$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

function respond(bool $ok, string $msg, array $data = []): void
{
    echo json_encode(array_merge([
        'status' => $ok ? 'success' : 'error',
        'message' => $msg,
    ], $data));
    exit;
}

function normalizeType(string $type): string
{
    return $type === 'policies' ? 'policies' : 'standards';
}

function allowedStatuses(string $type): array
{
    return $type === 'policies'
        ? ['Draft', 'Active', 'Archived']
        : ['Active', 'Archived'];
}

function sanitizeStatus(string $status, string $type): string
{
    $status = trim($status);
    if ($status === '') {
        return $type === 'policies' ? 'Draft' : 'Active';
    }

    return in_array($status, allowedStatuses($type), true) ? $status : '';
}

function requireStatement(mysqli $conn, string $sql): mysqli_stmt
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        respond(false, 'Failed to prepare statement.');
    }

    return $stmt;
}

function linkedStandardExists(mysqli $conn, int $standardId): bool
{
    if ($standardId <= 0) {
        return true;
    }

    $stmt = requireStatement($conn, 'SELECT standard_id FROM qa_standards WHERE standard_id = ?');
    $stmt->bind_param('i', $standardId);
    $stmt->execute();

    return (bool)$stmt->get_result()->fetch_assoc();
}

function uploadErrorMessage(int $errorCode): string
{
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Uploaded file is too large.';
        case UPLOAD_ERR_PARTIAL:
            return 'Uploaded file was only partially uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing temporary upload directory.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write uploaded file to disk.';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload stopped by a server extension.';
        default:
            return 'Failed to upload file.';
    }
}

function uniqueUploadToken(): string
{
    try {
        return bin2hex(random_bytes(4));
    } catch (Throwable $e) {
        return substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
    }
}

function sanitizeUploadName(string $originalName): string
{
    $nameOnly = pathinfo($originalName, PATHINFO_FILENAME);
    $nameOnly = preg_replace('/[^a-zA-Z0-9]+/', '-', $nameOnly);
    $nameOnly = trim((string)$nameOnly, '-');

    return $nameOnly !== '' ? strtolower($nameOnly) : 'policy-document';
}

function cloudinarySignature(array $params, string $apiSecret): string
{
    ksort($params);
    $parts = [];

    foreach ($params as $key => $value) {
        $stringValue = trim((string)$value);
        if ($stringValue === '') {
            continue;
        }
        $parts[] = $key . '=' . $stringValue;
    }

    return sha1(implode('&', $parts) . $apiSecret);
}

function cloudinarySignedRawDownloadUrl(string $publicId): string
{
    $cloudinary = getCloudinaryConfig();
    $cloudName = trim((string)($cloudinary['cloud_name'] ?? ''));
    $apiKey = trim((string)($cloudinary['api_key'] ?? ''));
    $apiSecret = trim((string)($cloudinary['api_secret'] ?? ''));

    if ($cloudName === '' || $apiKey === '' || $apiSecret === '') {
        http_response_code(500);
        respond(false, 'Cloudinary configuration is incomplete.');
    }

    $timestamp = time();
    $expiresAt = $timestamp + (10 * 365 * 24 * 60 * 60);
    $format = strtolower((string)pathinfo($publicId, PATHINFO_EXTENSION));
    if ($format === '') {
        $format = 'pdf';
    }

    $signatureParams = [
        'expires_at' => $expiresAt,
        'format' => $format,
        'public_id' => $publicId,
        'timestamp' => $timestamp,
        'type' => 'upload',
    ];

    $query = [
        'public_id' => $publicId,
        'format' => $format,
        'type' => 'upload',
        'timestamp' => $timestamp,
        'expires_at' => $expiresAt,
        'signature' => cloudinarySignature($signatureParams, $apiSecret),
        'api_key' => $apiKey,
    ];

    return 'https://api.cloudinary.com/v1_1/' . rawurlencode($cloudName) . '/raw/download?' . http_build_query($query);
}

function uploadPolicyDocumentToCloudinary(string $tmpName, string $originalName): string
{
    $cloudinary = getCloudinaryConfig();
    $cloudName = trim((string)($cloudinary['cloud_name'] ?? ''));
    $apiKey = trim((string)($cloudinary['api_key'] ?? ''));
    $apiSecret = trim((string)($cloudinary['api_secret'] ?? ''));
    $folder = trim((string)($cloudinary['folder'] ?? 'qa_system/policies'));

    if ($cloudName === '' || $apiKey === '' || $apiSecret === '') {
        http_response_code(500);
        respond(false, 'Cloudinary configuration is incomplete.');
    }

    if (!function_exists('curl_init')) {
        http_response_code(500);
        respond(false, 'cURL extension is required for Cloudinary uploads.');
    }

    $publicId = sanitizeUploadName($originalName) . '-' . date('YmdHis') . '-' . uniqueUploadToken();
    $timestamp = time();
    $signatureParams = [
        'folder' => $folder,
        'public_id' => $publicId,
        'timestamp' => $timestamp,
    ];

    $endpoint = 'https://api.cloudinary.com/v1_1/' . rawurlencode($cloudName) . '/raw/upload';
    $postFields = [
        'file' => new CURLFile($tmpName, 'application/pdf', $originalName),
        'api_key' => $apiKey,
        'timestamp' => (string)$timestamp,
        'signature' => cloudinarySignature($signatureParams, $apiSecret),
        'folder' => $folder,
        'public_id' => $publicId,
        'resource_type' => 'raw',
    ];

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $responseBody = curl_exec($ch);
    if ($responseBody === false) {
        $curlError = curl_error($ch);
        curl_close($ch);
        http_response_code(502);
        respond(false, 'Cloudinary upload failed: ' . ($curlError !== '' ? $curlError : 'Unknown cURL error.'));
    }

    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode((string)$responseBody, true);
    $uploadedPublicId = is_array($decoded) ? trim((string)($decoded['public_id'] ?? '')) : '';
    if ($httpCode >= 400 || $uploadedPublicId === '') {
        $cloudinaryError = '';
        if (is_array($decoded) && isset($decoded['error']) && is_array($decoded['error'])) {
            $cloudinaryError = trim((string)($decoded['error']['message'] ?? ''));
        }

        http_response_code(502);
        respond(false, $cloudinaryError !== '' ? $cloudinaryError : 'Failed to upload policy document to Cloudinary.');
    }

    return cloudinarySignedRawDownloadUrl($uploadedPublicId);
}

function savePolicyDocumentUpload(array $file): string
{
    $errorCode = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($errorCode !== UPLOAD_ERR_OK) {
        http_response_code(400);
        respond(false, uploadErrorMessage($errorCode));
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        http_response_code(400);
        respond(false, 'Invalid uploaded file.');
    }

    $fileSize = (int)($file['size'] ?? 0);
    $maxFileSize = 10 * 1024 * 1024;
    if ($fileSize <= 0 || $fileSize > $maxFileSize) {
        http_response_code(400);
        respond(false, 'Policy document must be a PDF file up to 10 MB.');
    }

    $originalName = (string)($file['name'] ?? 'policy-document.pdf');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension !== 'pdf') {
        http_response_code(400);
        respond(false, 'Only PDF files are allowed for policy documents.');
    }

    $pdfHeader = file_get_contents($tmpName, false, null, 0, 5);
    if ($pdfHeader !== '%PDF-') {
        http_response_code(400);
        respond(false, 'Uploaded document is not a valid PDF file.');
    }

    return uploadPolicyDocumentToCloudinary($tmpName, $originalName);
}

function getPolicyDocumentUrl(mysqli $conn, int $policyId): ?string
{
    $stmt = requireStatement($conn, 'SELECT document_url FROM qa_policies WHERE policy_id = ?');
    $stmt->bind_param('i', $policyId);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();
    if (!$row) {
        return null;
    }

    return (string)($row['document_url'] ?? '');
}

$type = normalizeType(trim($_POST['type'] ?? $_GET['type'] ?? 'standards'));

if ($action === 'create') {
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $status = sanitizeStatus((string)($_POST['status'] ?? ''), $type);
    $eff_date = trim($_POST['effective_date'] ?? '');
    $rev_date = trim($_POST['review_date'] ?? '');
    $standard_id = (int)($_POST['standard_id'] ?? 0);
    $document_url = trim($_POST['document_url'] ?? '');
    $version = trim($_POST['version'] ?? '');
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $uploaded_document_url = $type === 'policies'
        ? savePolicyDocumentUpload($_FILES['document_file'] ?? [])
        : '';

    if ($version === '') {
        $version = '1.0';
    }

    if ($title === '' || $body === '' || $category === '') {
        http_response_code(400);
        respond(false, 'Title, body/owner, and category are required.');
    }

    if ($status === '') {
        http_response_code(400);
        respond(false, 'Invalid status value for selected type.');
    }

    if ($type === 'policies') {
        if (!linkedStandardExists($conn, $standard_id)) {
            http_response_code(400);
            respond(false, 'Linked standard does not exist.');
        }

        if ($uploaded_document_url !== '') {
            $document_url = $uploaded_document_url;
        }

        if ($document_url === '') {
            http_response_code(400);
            respond(false, 'Policy document file is required.');
        }

        $stmt = requireStatement(
            $conn,
            'INSERT INTO qa_policies (title, description, category, standard_id, document_url, version, status, owner, effective_date, expiry_date, last_reviewed)
             VALUES (?, ?, ?, NULLIF(?, 0), NULLIF(?, \'\'), ?, ?, ?, NULLIF(?, \'\'), NULLIF(?, \'\'), NULLIF(?, \'\'))'
        );
        $stmt->bind_param('sssisssssss', $title, $desc, $category, $standard_id, $document_url, $version, $status, $body, $eff_date, $expiry_date, $rev_date);
    } else {
        $stmt = requireStatement(
            $conn,
            'INSERT INTO qa_standards (title, description, category, compliance_body, status, effective_date, review_date)
             VALUES (?, ?, ?, ?, ?, NULLIF(?, \'\'), NULLIF(?, \'\'))'
        );
        $stmt->bind_param('sssssss', $title, $desc, $category, $body, $status, $eff_date, $rev_date);
    }

    if ($stmt->execute()) {
        respond(true, 'Record created');
    }

    http_response_code(500);
    respond(false, $stmt->error ?: 'Failed to create record.');
}

if ($action === 'update') {
    $id = (int)($_POST['rec_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $status = sanitizeStatus((string)($_POST['status'] ?? ''), $type);
    $eff_date = trim($_POST['effective_date'] ?? '');
    $rev_date = trim($_POST['review_date'] ?? '');
    $standard_id = (int)($_POST['standard_id'] ?? 0);
    $document_url = trim($_POST['document_url'] ?? '');
    $version = trim($_POST['version'] ?? '');
    $expiry_date = trim($_POST['expiry_date'] ?? '');
    $uploaded_document_url = $type === 'policies'
        ? savePolicyDocumentUpload($_FILES['document_file'] ?? [])
        : '';

    if ($version === '') {
        $version = '1.0';
    }

    if ($id <= 0 || $title === '' || $body === '' || $category === '') {
        http_response_code(400);
        respond(false, 'Invalid input. Required fields: id, title, body/owner, category.');
    }

    if ($status === '') {
        http_response_code(400);
        respond(false, 'Invalid status value for selected type.');
    }

    if ($type === 'policies') {
        if (!linkedStandardExists($conn, $standard_id)) {
            http_response_code(400);
            respond(false, 'Linked standard does not exist.');
        }

        $existing_document_url = getPolicyDocumentUrl($conn, $id);
        if ($existing_document_url === null) {
            http_response_code(404);
            respond(false, 'Policy record not found.');
        }

        if ($uploaded_document_url !== '') {
            $document_url = $uploaded_document_url;
        } elseif ($document_url === '') {
            $document_url = $existing_document_url;
        }

        $stmt = requireStatement(
            $conn,
            'UPDATE qa_policies
             SET title=?, description=?, category=?, standard_id=NULLIF(?, 0), document_url=NULLIF(?, \'\'),
                 version=?, owner=?, status=?, effective_date=NULLIF(?, \'\'), expiry_date=NULLIF(?, \'\'),
                 last_reviewed=NULLIF(?, \'\')
             WHERE policy_id=?'
        );
        $stmt->bind_param('sssisssssssi', $title, $desc, $category, $standard_id, $document_url, $version, $body, $status, $eff_date, $expiry_date, $rev_date, $id);
    } else {
        $stmt = requireStatement(
            $conn,
            'UPDATE qa_standards
             SET title=?, description=?, category=?, compliance_body=?, status=?,
                 effective_date=NULLIF(?, \'\'), review_date=NULLIF(?, \'\')
             WHERE standard_id=?'
        );
        $stmt->bind_param('sssssssi', $title, $desc, $category, $body, $status, $eff_date, $rev_date, $id);
    }

    if ($stmt->execute()) {
        respond(true, 'Record updated');
    }

    http_response_code(500);
    respond(false, $stmt->error ?: 'Failed to update record.');
}

if ($action === 'delete') {
    $id = (int)($_POST['rec_id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        respond(false, 'Record ID is required.');
    }

    $table = $type === 'policies' ? 'qa_policies' : 'qa_standards';
    $id_col = $type === 'policies' ? 'policy_id' : 'standard_id';

    $stmt = requireStatement($conn, "DELETE FROM {$table} WHERE {$id_col} = ?");
    $stmt->bind_param('i', $id);

    if ($stmt->execute()) {
        respond(true, 'Record deleted');
    }

    http_response_code(500);
    respond(false, $stmt->error ?: 'Failed to delete record.');
}

if ($action === 'list') {
    $status = trim($_GET['status'] ?? '');

    if ($status !== '' && !in_array($status, allowedStatuses($type), true)) {
        http_response_code(400);
        respond(false, 'Invalid status value for selected type.');
    }

    if ($type === 'policies') {
        $sql = 'SELECT * FROM qa_policies';
    } else {
        $sql = 'SELECT * FROM qa_standards';
    }

    $params = [];
    $types = '';
    if ($status !== '') {
        $sql .= ' WHERE status = ?';
        $params[] = $status;
        $types .= 's';
    }

    $sql .= ' ORDER BY created_at DESC';

    $stmt = requireStatement($conn, $sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    $rows = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    respond(true, 'OK', ['data' => $rows]);
}

http_response_code(400);
respond(false, 'Invalid action.');
