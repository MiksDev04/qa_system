<?php
/**
 * Sync Mappings Configuration Initializer
 * This script initializes the qa_external_data_mapping table with default mappings
 * from LMS, HRIS, and FACULTY_EVAL systems to QA indicators
 * 
 * Run this ONCE after creating the database schema:
 * Navigate to: http://yoursite/config/sync_mappings.php?action=init
 */

require_once dirname(__DIR__) . '/config/database.php';

$action = isset($_GET['action']) ? $_GET['action'] : 'show';

if ($action === 'init') {
    // Initialize default mappings
    $mappings = [
        // LMS Mappings
        [
            'source_system' => 'LMS',
            'external_field' => 'course_completion_rate',
            'indicator_id' => 8, // Course Completion Rate
            'indicator_name' => 'Course Completion Rate',
            'unit' => '%',
            'target_value' => 90.00,
            'sync_frequency' => 'semester'
        ],
        [
            'source_system' => 'LMS',
            'external_field' => 'dropout_rate',
            'indicator_id' => 6, // Dropout Rate
            'indicator_name' => 'Dropout Rate',
            'unit' => '%',
            'target_value' => 5.00,
            'sync_frequency' => 'semester'
        ],
        [
            'source_system' => 'LMS',
            'external_field' => 'pass_rate',
            'indicator_id' => 1, // Board Exam Passing Rate
            'indicator_name' => 'Board Exam Passing Rate',
            'unit' => '%',
            'target_value' => 80.00,
            'sync_frequency' => 'semester'
        ],
        [
            'source_system' => 'LMS',
            'external_field' => 'graduation_rate',
            'indicator_id' => 2, // Graduation Rate
            'indicator_name' => 'Graduation Rate',
            'unit' => '%',
            'target_value' => 75.00,
            'sync_frequency' => 'annual'
        ],
        [
            'source_system' => 'LMS',
            'external_field' => 'student_satisfaction_score',
            'indicator_id' => 3, // Student Satisfaction Score
            'indicator_name' => 'Student Satisfaction Score',
            'unit' => 'score',
            'target_value' => 4.00,
            'sync_frequency' => 'semester'
        ],
        
        // HRIS Mappings
        [
            'source_system' => 'HRIS',
            'external_field' => 'faculty_evaluation_average',
            'indicator_id' => 4, // Faculty Evaluation Average
            'indicator_name' => 'Faculty Evaluation Average',
            'unit' => '%',
            'target_value' => 85.00,
            'sync_frequency' => 'semester'
        ],
        [
            'source_system' => 'HRIS',
            'external_field' => 'research_publications_count',
            'indicator_id' => 5, // Research Output Count
            'indicator_name' => 'Research Output Count',
            'unit' => 'count',
            'target_value' => 10.00,
            'sync_frequency' => 'annual'
        ],
        [
            'source_system' => 'HRIS',
            'external_field' => 'faculty_satisfaction_score',
            'indicator_id' => null, // No existing indicator - need to create
            'indicator_name' => 'Faculty Satisfaction Score',
            'unit' => '%',
            'target_value' => 80.00,
            'sync_frequency' => 'semester'
        ],
        
        // FACULTY_EVAL Mappings
        [
            'source_system' => 'FACULTY_EVAL',
            'external_field' => 'faculty_evaluation_average',
            'indicator_id' => 4, // Faculty Evaluation Average
            'indicator_name' => 'Faculty Evaluation Average',
            'unit' => '%',
            'target_value' => 85.00,
            'sync_frequency' => 'semester'
        ],
        [
            'source_system' => 'FACULTY_EVAL',
            'external_field' => 'employer_satisfaction_with_graduates',
            'indicator_id' => 7, // Employer Satisfaction Rate
            'indicator_name' => 'Employer Satisfaction Rate',
            'unit' => '%',
            'target_value' => 80.00,
            'sync_frequency' => 'semester'
        ],
        [
            'source_system' => 'FACULTY_EVAL',
            'external_field' => 'teaching_effectiveness_score',
            'indicator_id' => null, // No existing indicator
            'indicator_name' => 'Teaching Effectiveness Score',
            'unit' => '%',
            'target_value' => 85.00,
            'sync_frequency' => 'semester'
        ],
        [
            'source_system' => 'FACULTY_EVAL',
            'external_field' => 'graduate_competency_rating',
            'indicator_id' => null, // No existing indicator
            'indicator_name' => 'Graduate Competency Rating',
            'unit' => 'score',
            'target_value' => 4.00,
            'sync_frequency' => 'annual'
        ],
    ];
    
    // Clear existing mappings (optional - comment out to preserve)
    // $conn->query("DELETE FROM qa_external_data_mapping");
    
    $success_count = 0;
    $error_count = 0;
    $errors = [];
    
    foreach ($mappings as $mapping) {
        $query = "
            INSERT INTO qa_external_data_mapping 
            (source_system, external_field, indicator_id, indicator_name, unit, target_value, sync_frequency, is_active, sync_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'pending')
            ON DUPLICATE KEY UPDATE 
            indicator_id = VALUES(indicator_id),
            indicator_name = VALUES(indicator_name),
            unit = VALUES(unit),
            target_value = VALUES(target_value),
            sync_frequency = VALUES(sync_frequency),
            is_active = VALUES(is_active)
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param(
            'ssdsss',
            $mapping['source_system'],
            $mapping['external_field'],
            $mapping['indicator_id'],
            $mapping['indicator_name'],
            $mapping['unit'],
            $mapping['target_value'],
            $mapping['sync_frequency']
        );
        
        if ($stmt->execute()) {
            $success_count++;
        } else {
            $error_count++;
            $errors[] = $stmt->error;
        }
        $stmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Initialized mappings: $success_count successful, $error_count failed",
        'success_count' => $success_count,
        'error_count' => $error_count,
        'errors' => $errors
    ]);
    
} else if ($action === 'show') {
    // Display current mappings
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
            sync_status
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
        'count' => count($mappings),
        'data' => $mappings
    ]);
    
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid action. Use ?action=init to initialize, or ?action=show to display current mappings'
    ]);
}

$conn->close();
?>
