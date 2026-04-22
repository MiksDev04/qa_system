<?php
/**
 * External Data Sync API
 * Handles automated syncing of KPI data from external systems (LMS, HRIS, FACULTY_EVAL)
 * 
 * Actions:
 * - get_mapping: Get current mapping configuration
 * - sync_from_external: Load data from JSON files and cache it
 * - validate_and_create: Validate cached data and create qa_records
 * - auto_sync: Full sync workflow (sync_from_external → validate_and_create)
 * - get_sync_status: Get status of recent syncs
 * - get_cache: Get cached data (for review before final creation)
 */

header('Content-Type: application/json');

require_once '../config/database.php';

// Get action from query parameter
$action = isset($_GET['action']) ? $_GET['action'] : 'get_mapping';

try {
    
    // ============================================================
    // GET MAPPING - Show all configured external data mappings
    // ============================================================
    if ($action === 'get_mapping') {
        $query = "
            SELECT 
                mapping_id,
                source_system,
                external_field,
                indicator_id,
                indicator_name,
                unit,
                target_value,
                sync_frequency,
                is_active,
                last_sync_date,
                sync_status,
                error_message
            FROM qa_external_data_mapping
            ORDER BY source_system, external_field
        ";
        $result = $conn->query($query);
        $mappings = [];
        
        while ($row = $result->fetch_assoc()) {
            $mappings[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Retrieved ' . count($mappings) . ' mappings',
            'data' => $mappings
        ]);
    }
    
    // ============================================================
    // SYNC FROM EXTERNAL - Load data from JSON files into cache
    // ============================================================
    else if ($action === 'sync_from_external') {
        $source = isset($_GET['source']) ? $_GET['source'] : null; // 'LMS', 'HRIS', 'FACULTY_EVAL', or null for all
        $sync_operation_id = time(); // Unique ID for this sync batch
        
        $data_dir = dirname(__DIR__) . '/data';
        $synced_records = 0;
        $errors = [];
        
        // Define file mapping
        $files = [
            'LMS' => 'lms_data.json',
            'HRIS' => 'hris_data.json',
            'FACULTY_EVAL' => 'faculty_evaluation_data.json'
        ];
        
        // Filter to specific source if provided
        if ($source && array_key_exists($source, $files)) {
            $files = [$source => $files[$source]];
        } elseif ($source) {
            throw new Exception("Invalid source system: $source");
        }
        
        // Process each file
        foreach ($files as $system => $filename) {
            $filepath = $data_dir . '/' . $filename;
            
            if (!file_exists($filepath)) {
                $errors[] = "File not found: $filename";
                continue;
            }
            
            // Read and parse JSON
            $json_content = file_get_contents($filepath);
            $external_data = json_decode($json_content, true);
            
            if (!$external_data) {
                $errors[] = "Invalid JSON in $filename: " . json_last_error_msg();
                continue;
            }
            
            // Process each data entry
            if (isset($external_data['data']) && is_array($external_data['data'])) {
                foreach ($external_data['data'] as $entry) {
                    $academic_period = $entry['academic_period'];
                    $year = $entry['year'];
                    $semester = $entry['semester'];
                    $metrics = $entry['metrics'];
                    
                    // Get mappings for this source system
                    $mapping_query = "
                        SELECT mapping_id, external_field, indicator_id, indicator_name, unit
                        FROM qa_external_data_mapping
                        WHERE source_system = ? AND is_active = 1
                    ";
                    
                    $stmt = $conn->prepare($mapping_query);
                    $stmt->bind_param('s', $system);
                    $stmt->execute();
                    $mapping_result = $stmt->get_result();
                    
                    while ($mapping = $mapping_result->fetch_assoc()) {
                        $external_field = $mapping['external_field'];
                        
                        // Check if this metric exists in the data
                        if (isset($metrics[$external_field])) {
                            $raw_value = $metrics[$external_field];
                            $converted_value = floatval($raw_value);
                            
                            // Insert into cache
                            $cache_query = "
                                INSERT INTO qa_external_data_cache 
                                (mapping_id, source_system, academic_period, year, semester, external_field, raw_value, converted_value, validation_status, sync_operation_id)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'valid', ?)
                            ";
                            
                            $cache_stmt = $conn->prepare($cache_query);
                            $cache_stmt->bind_param(
                                'issiisidi',
                                $mapping['mapping_id'],
                                $system,
                                $academic_period,
                                $year,
                                $semester,
                                $external_field,
                                $raw_value,
                                $converted_value,
                                $sync_operation_id
                            );
                            
                            if ($cache_stmt->execute()) {
                                $synced_records++;
                            } else {
                                $errors[] = "Failed to cache $system.$external_field for $academic_period: " . $cache_stmt->error;
                            }
                        }
                    }
                    $stmt->close();
                }
            }
        }
        
        // Update sync status in mapping table
        $update_query = "
            UPDATE qa_external_data_mapping
            SET last_sync_date = NOW(), sync_status = 'synced'
            WHERE source_system IN ('" . implode("','", array_keys($files)) . "')
        ";
        $conn->query($update_query);
        
        echo json_encode([
            'success' => true,
            'message' => "Synced $synced_records records from external systems",
            'synced_records' => $synced_records,
            'sync_operation_id' => $sync_operation_id,
            'errors' => $errors,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    // ============================================================
    // VALIDATE AND CREATE - Create qa_records from cached data
    // ============================================================
    else if ($action === 'validate_and_create') {
        $sync_operation_id = isset($_GET['sync_operation_id']) ? intval($_GET['sync_operation_id']) : null;
        
        if (!$sync_operation_id) {
            throw new Exception("sync_operation_id is required");
        }
        
        // Get all cached records from this sync operation that haven't been processed
        $query = "
            SELECT 
                cache_id,
                mapping_id,
                source_system,
                year,
                semester,
                converted_value,
                (SELECT indicator_id FROM qa_external_data_mapping WHERE mapping_id = edm.mapping_id) as indicator_id
            FROM qa_external_data_cache edm
            WHERE sync_operation_id = ? AND validation_status = 'valid' AND synced_to_record_id IS NULL
            ORDER BY source_system, year, semester
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $sync_operation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $created_records = 0;
        $failed_records = 0;
        $skipped_records = 0;
        $created_list = [];
        
        while ($row = $result->fetch_assoc()) {
            $cache_id = $row['cache_id'];
            $indicator_id = $row['indicator_id'];
            $year = $row['year'];
            $semester = $row['semester'];
            $actual_value = $row['converted_value'];
            $source_system = $row['source_system'];
            
            // Check if record already exists for this indicator+year+semester
            $check_query = "
                SELECT record_id FROM qa_records 
                WHERE indicator_id = ? AND year = ? AND semester = ?
            ";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param('iis', $indicator_id, $year, $semester);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $skipped_records++;
                $existing = $check_result->fetch_assoc();
                $created_list[] = [
                    'status' => 'skipped',
                    'reason' => 'Record already exists',
                    'existing_record_id' => $existing['record_id'],
                    'cache_id' => $cache_id
                ];
                continue;
            }
            $check_stmt->close();
            
            // Create the qa_record
            $insert_query = "
                INSERT INTO qa_records (indicator_id, year, semester, actual_value, recorded_by, source_system, external_sync_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            
            $insert_stmt = $conn->prepare($insert_query);
            $recorded_by = "Auto-sync from $source_system";
            $insert_stmt->bind_param(
                'iisdssi',
                $indicator_id,
                $year,
                $semester,
                $actual_value,
                $recorded_by,
                $source_system,
                $cache_id
            );
            
            if ($insert_stmt->execute()) {
                $new_record_id = $insert_stmt->insert_id;
                
                // Update cache to link to created record
                $link_query = "UPDATE qa_external_data_cache SET synced_to_record_id = ? WHERE cache_id = ?";
                $link_stmt = $conn->prepare($link_query);
                $link_stmt->bind_param('ii', $new_record_id, $cache_id);
                $link_stmt->execute();
                $link_stmt->close();
                
                $created_records++;
                $created_list[] = [
                    'status' => 'created',
                    'record_id' => $new_record_id,
                    'indicator_id' => $indicator_id,
                    'year' => $year,
                    'semester' => $semester,
                    'value' => $actual_value,
                    'source' => $source_system
                ];
            } else {
                $failed_records++;
                $created_list[] = [
                    'status' => 'error',
                    'error' => $insert_stmt->error,
                    'cache_id' => $cache_id
                ];
            }
            $insert_stmt->close();
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Created $created_records records, skipped $skipped_records, failed $failed_records",
            'created_records' => $created_records,
            'skipped_records' => $skipped_records,
            'failed_records' => $failed_records,
            'created_list' => $created_list,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    // ============================================================
    // AUTO SYNC - Full workflow (sync + validate + create)
    // ============================================================
    else if ($action === 'auto_sync') {
        $source = isset($_GET['source']) ? $_GET['source'] : null;
        
        // Step 1: Sync from external
        $sync_operation_id = time();
        
        // Step 1a: Load data from JSON files
        $data_dir = dirname(__DIR__) . '/data';
        $synced_records = 0;
        
        $files = [
            'LMS' => 'lms_data.json',
            'HRIS' => 'hris_data.json',
            'FACULTY_EVAL' => 'faculty_evaluation_data.json'
        ];
        
        if ($source && array_key_exists($source, $files)) {
            $files = [$source => $files[$source]];
        } elseif ($source) {
            throw new Exception("Invalid source system: $source");
        }
        
        // Process files and cache data
        foreach ($files as $system => $filename) {
            $filepath = $data_dir . '/' . $filename;
            
            if (!file_exists($filepath)) {
                continue;
            }
            
            $json_content = file_get_contents($filepath);
            $external_data = json_decode($json_content, true);
            
            if (!$external_data) {
                continue;
            }
            
            if (isset($external_data['data']) && is_array($external_data['data'])) {
                foreach ($external_data['data'] as $entry) {
                    $academic_period = $entry['academic_period'];
                    $year = $entry['year'];
                    $semester = $entry['semester'];
                    $metrics = $entry['metrics'];
                    
                    $mapping_query = "
                        SELECT mapping_id, external_field, indicator_id
                        FROM qa_external_data_mapping
                        WHERE source_system = ? AND is_active = 1
                    ";
                    
                    $stmt = $conn->prepare($mapping_query);
                    $stmt->bind_param('s', $system);
                    $stmt->execute();
                    $mapping_result = $stmt->get_result();
                    
                    while ($mapping = $mapping_result->fetch_assoc()) {
                        $external_field = $mapping['external_field'];
                        
                        if (isset($metrics[$external_field])) {
                            $raw_value = $metrics[$external_field];
                            $converted_value = floatval($raw_value);
                            
                            $cache_query = "
                                INSERT INTO qa_external_data_cache 
                                (mapping_id, source_system, academic_period, year, semester, external_field, raw_value, converted_value, validation_status, sync_operation_id)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'valid', ?)
                            ";
                            
                            $cache_stmt = $conn->prepare($cache_query);
                            $cache_stmt->bind_param(
                                'issiisidi',
                                $mapping['mapping_id'],
                                $system,
                                $academic_period,
                                $year,
                                $semester,
                                $external_field,
                                $raw_value,
                                $converted_value,
                                $sync_operation_id
                            );
                            $cache_stmt->execute();
                            $synced_records++;
                        }
                    }
                    $stmt->close();
                }
            }
        }
        
        // Step 2: Create records from cache
        $create_query = "
            SELECT 
                cache_id,
                source_system,
                year,
                semester,
                converted_value,
                (SELECT indicator_id FROM qa_external_data_mapping WHERE mapping_id = edm.mapping_id) as indicator_id
            FROM qa_external_data_cache edm
            WHERE sync_operation_id = ? AND validation_status = 'valid' AND synced_to_record_id IS NULL
        ";
        
        $create_stmt = $conn->prepare($create_query);
        $create_stmt->bind_param('i', $sync_operation_id);
        $create_stmt->execute();
        $create_result = $create_stmt->get_result();
        
        $created_records = 0;
        $skipped_records = 0;
        $failed_records = 0;
        
        while ($row = $create_result->fetch_assoc()) {
            $cache_id = $row['cache_id'];
            $indicator_id = $row['indicator_id'];
            $year = $row['year'];
            $semester = $row['semester'];
            $actual_value = $row['converted_value'];
            $source_system = $row['source_system'];
            
            // Check if exists
            $check_query = "
                SELECT record_id FROM qa_records 
                WHERE indicator_id = ? AND year = ? AND semester = ?
            ";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param('iis', $indicator_id, $year, $semester);
            $check_stmt->execute();
            
            if ($check_stmt->get_result()->num_rows > 0) {
                $skipped_records++;
                continue;
            }
            $check_stmt->close();
            
            // Create record
            $insert_query = "
                INSERT INTO qa_records (indicator_id, year, semester, actual_value, recorded_by, source_system, external_sync_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ";
            
            $insert_stmt = $conn->prepare($insert_query);
            $recorded_by = "Auto-sync from $source_system";
            $insert_stmt->bind_param(
                'iisdssi',
                $indicator_id,
                $year,
                $semester,
                $actual_value,
                $recorded_by,
                $source_system,
                $cache_id
            );
            
            if ($insert_stmt->execute()) {
                $new_record_id = $insert_stmt->insert_id;
                
                // Link cache to record
                $link_query = "UPDATE qa_external_data_cache SET synced_to_record_id = ? WHERE cache_id = ?";
                $link_stmt = $conn->prepare($link_query);
                $link_stmt->bind_param('ii', $new_record_id, $cache_id);
                $link_stmt->execute();
                $link_stmt->close();
                
                $created_records++;
            } else {
                $failed_records++;
            }
            $insert_stmt->close();
        }
        $create_stmt->close();
        
        echo json_encode([
            'success' => true,
            'message' => "Auto-sync complete. Cached $synced_records records, created $created_records, skipped $skipped_records, failed $failed_records",
            'sync_operation_id' => $sync_operation_id,
            'cached_records' => $synced_records,
            'created_records' => $created_records,
            'skipped_records' => $skipped_records,
            'failed_records' => $failed_records,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
    
    // ============================================================
    // GET SYNC STATUS - Show recent sync operations
    // ============================================================
    else if ($action === 'get_sync_status') {
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
        
        $query = "
            SELECT 
                source_system,
                last_sync_date,
                sync_status,
                error_message,
                COUNT(*) as mapping_count
            FROM qa_external_data_mapping
            GROUP BY source_system, last_sync_date, sync_status
            ORDER BY last_sync_date DESC
            LIMIT ?
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $status = [];
        
        while ($row = $result->fetch_assoc()) {
            $status[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $status
        ]);
        $stmt->close();
    }
    
    // ============================================================
    // GET CACHE - Get cached data for review
    // ============================================================
    else if ($action === 'get_cache') {
        $sync_operation_id = isset($_GET['sync_operation_id']) ? intval($_GET['sync_operation_id']) : null;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
        
        $query = "
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
                (SELECT indicator_id FROM qa_external_data_mapping WHERE mapping_id = edc.mapping_id) as indicator_id,
                (SELECT name FROM qa_indicators WHERE indicator_id = (SELECT indicator_id FROM qa_external_data_mapping WHERE mapping_id = edc.mapping_id)) as indicator_name,
                synced_to_record_id,
                sync_date
            FROM qa_external_data_cache edc
        ";
        
        if ($sync_operation_id) {
            $query .= " WHERE sync_operation_id = $sync_operation_id";
        }
        
        $query .= " ORDER BY sync_date DESC LIMIT $limit";
        
        $result = $conn->query($query);
        $cache = [];
        
        while ($row = $result->fetch_assoc()) {
            $cache[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'count' => count($cache),
            'data' => $cache
        ]);
    }
    
    // ============================================================
    // INVALID ACTION
    // ============================================================
    else {
        throw new Exception("Invalid action: $action");
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
