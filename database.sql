-- ============================================================
-- ByteBandits – Quality Assurance Management System
-- Database: plsp_integrated (shared with other teams)
-- QA-related tables only. Do not modify tables owned by other teams.
-- ============================================================

-- Shared tables (created by other teams – included here for FK reference only)
-- departments, employees, students are owned by HRIS team (BusyBugs)
CREATE DATABASE IF NOT EXISTS plsp_integrated;
USE plsp_integrated;
-- ============================================================
-- QA TABLES
-- ============================================================

-- QA Indicators (KPIs/Benchmarks)
CREATE TABLE IF NOT EXISTS qa_indicators (
    indicator_id    INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(150)        NOT NULL,
    description     TEXT,
    target_value    DECIMAL(10,2)       NOT NULL DEFAULT 0,
    unit            VARCHAR(50)         DEFAULT '%',         -- e.g. %, count, score
    category        VARCHAR(100)        DEFAULT 'General',   -- e.g. Academic, Operations
    status          ENUM('Active','Inactive') DEFAULT 'Active',
    created_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- QA Records (actual measured values per indicator per period)
CREATE TABLE IF NOT EXISTS qa_records (
    record_id       INT AUTO_INCREMENT PRIMARY KEY,
    indicator_id    INT                 NOT NULL,
    year            YEAR                NOT NULL,
    semester        ENUM('1st','2nd','Annual') DEFAULT '1st',
    actual_value    DECIMAL(10,2)       NOT NULL,
    remarks         TEXT,
    recorded_by     VARCHAR(100),       -- name of person entering the record
    source_system   VARCHAR(50),        -- 'LMS', 'HRIS', 'FACULTY_EVAL', 'Manual'
    external_sync_id INT,               -- Reference to qa_external_data_cache if auto-synced
    created_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_qa_indicator FOREIGN KEY (indicator_id) REFERENCES qa_indicators(indicator_id) ON DELETE CASCADE
);

-- ============================================================
-- EXTERNAL DATA INTEGRATION TABLES
-- ============================================================

-- Mapping configuration for external system fields to QA indicators
CREATE TABLE IF NOT EXISTS qa_external_data_mapping (
    mapping_id      INT AUTO_INCREMENT PRIMARY KEY,
    source_system   VARCHAR(50)         NOT NULL,       -- 'LMS', 'HRIS', 'FACULTY_EVAL'
    external_field  VARCHAR(150)        NOT NULL,       -- e.g., 'course_completion_rate', 'faculty_evaluation_average'
    indicator_id    INT,                                -- FK to qa_indicators; NULL if new indicator to create
    indicator_name  VARCHAR(150),                       -- Fall-back name if indicator_id is NULL
    unit            VARCHAR(50),                        -- '%', 'count', 'score', etc.
    target_value    DECIMAL(10,2),                      -- Suggested target
    sync_frequency  ENUM('semester','annual','monthly') DEFAULT 'semester',  -- How often to sync
    is_active       TINYINT(1)          DEFAULT 1,      -- Enable/disable this mapping
    last_sync_date  DATETIME,                           -- Last successful sync for this mapping
    sync_status     ENUM('pending','synced','error')    DEFAULT 'pending',
    error_message   TEXT,                               -- Store error details from last sync
    created_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_edm_indicator FOREIGN KEY (indicator_id) REFERENCES qa_indicators(indicator_id) ON DELETE SET NULL,
    UNIQUE KEY unique_mapping (source_system, external_field)
);

-- Raw cache of external data before transformation to qa_records
CREATE TABLE IF NOT EXISTS qa_external_data_cache (
    cache_id        INT AUTO_INCREMENT PRIMARY KEY,
    mapping_id      INT                 NOT NULL,       -- Reference to mapping configuration
    source_system   VARCHAR(50)         NOT NULL,       -- 'LMS', 'HRIS', 'FACULTY_EVAL'
    academic_period VARCHAR(50),                        -- '2024-1st', '2024-2nd', '2024-Annual'
    year            YEAR,
    semester        ENUM('1st','2nd','Annual'),
    external_field  VARCHAR(150),
    raw_value       VARCHAR(255),                       -- Raw value from external system (before conversion)
    converted_value DECIMAL(10,2),                      -- Value converted to numeric for qa_records
    validation_status ENUM('pending','valid','invalid') DEFAULT 'pending',
    validation_error TEXT,                              -- Details if validation failed
    sync_operation_id INT,                              -- Batch identifier for multi-record syncs
    synced_to_record_id INT,                            -- FK to qa_records if successfully created
    sync_date       DATETIME            DEFAULT CURRENT_TIMESTAMP,
    created_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cache_mapping FOREIGN KEY (mapping_id) REFERENCES qa_external_data_mapping(mapping_id) ON DELETE CASCADE,
    CONSTRAINT fk_cache_record FOREIGN KEY (synced_to_record_id) REFERENCES qa_records(record_id) ON DELETE SET NULL,
    INDEX idx_source_period (source_system, academic_period),
    INDEX idx_sync_status (validation_status)
);

-- Surveys (with metadata)
CREATE TABLE IF NOT EXISTS surveys (
    survey_id       INT AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(200)        NOT NULL,
    description     TEXT,
    target_audience ENUM('Student','Employee','Employer','Alumni','General') DEFAULT 'General',
    status          ENUM('Draft','Active','Closed') DEFAULT 'Draft',
    start_date      DATE,
    end_date        DATE,
    qr_token        VARCHAR(64)         UNIQUE,    -- unique token for QR-based access
    require_name    TINYINT(1)          DEFAULT 0, -- whether respondent name is required
    require_email   TINYINT(1)          DEFAULT 0, -- whether respondent email is required
    created_date    DATE                DEFAULT (CURRENT_DATE),
    created_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Survey Questionnaires (questions per survey)
CREATE TABLE IF NOT EXISTS survey_questions (
    question_id     INT AUTO_INCREMENT PRIMARY KEY,
    survey_id       INT                 NOT NULL,
    question_text   TEXT                NOT NULL,
    question_type   ENUM('rating','text','multiple_choice','yes_no') DEFAULT 'rating',
    choices         TEXT,               -- JSON array for multiple_choice options
    is_required     TINYINT(1)          DEFAULT 1,
    sort_order      INT                 DEFAULT 0,
    created_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sq_survey FOREIGN KEY (survey_id) REFERENCES surveys(survey_id) ON DELETE CASCADE
);

-- Survey Responses (one response session per respondent per survey)
CREATE TABLE IF NOT EXISTS survey_responses (
    response_id         INT AUTO_INCREMENT PRIMARY KEY,
    survey_id           INT             NOT NULL,
    respondent_role     VARCHAR(50),                -- e.g. 'Student','Employee','Employer'
    respondent_name     VARCHAR(150),               -- optional
    respondent_email    VARCHAR(150),               -- optional
    session_token       VARCHAR(64),                -- unique per submission to prevent duplicates
    submitted_at        TIMESTAMP       DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sr_survey FOREIGN KEY (survey_id) REFERENCES surveys(survey_id) ON DELETE CASCADE
);

-- Survey Answers (individual question answers within a response)
CREATE TABLE IF NOT EXISTS survey_answers (
    answer_id       INT AUTO_INCREMENT PRIMARY KEY,
    response_id     INT                 NOT NULL,
    question_id     INT                 NOT NULL,
    answer_text     TEXT,
    rating          INT,                -- 1-5 scale for rating questions
    created_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sa_response FOREIGN KEY (response_id) REFERENCES survey_responses(response_id) ON DELETE CASCADE,
    CONSTRAINT fk_sa_question FOREIGN KEY (question_id) REFERENCES survey_questions(question_id) ON DELETE CASCADE
);

-- ============================================================
-- GOVERNANCE & COMPLIANCE TABLES
-- ============================================================

-- Standards & Requirements
CREATE TABLE IF NOT EXISTS qa_standards (
    standard_id     INT AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(200)        NOT NULL,
    description     TEXT,
    compliance_body VARCHAR(100),       -- e.g., 'CHED', 'ISO 9001', 'PEP'
    category        VARCHAR(100),       -- e.g., 'Academic', 'Governance', 'Operational'
    status          ENUM('Active','Archived') DEFAULT 'Active',
    effective_date  DATE,
    review_date     DATE,
    created_by      VARCHAR(100),
    created_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- QA Policies & Procedures
CREATE TABLE IF NOT EXISTS qa_policies (
    policy_id       INT AUTO_INCREMENT PRIMARY KEY,
    title           VARCHAR(200)        NOT NULL,
    description     TEXT,
    category        VARCHAR(100),       -- e.g., 'Student Assessment', 'Faculty Development'
    standard_id     INT,
    document_url    VARCHAR(1024),      -- Cloudinary URL to policy document
    version         VARCHAR(20) DEFAULT '1.0',
    effective_date  DATE,
    expiry_date     DATE,
    status          ENUM('Draft','Active','Archived') DEFAULT 'Draft',
    owner           VARCHAR(100),       -- Department/Office responsible
    last_reviewed   DATE,
    created_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_policy_standard FOREIGN KEY (standard_id) REFERENCES qa_standards(standard_id) ON DELETE SET NULL
);

-- Internal Audits
CREATE TABLE IF NOT EXISTS qa_audits (
    audit_id        INT AUTO_INCREMENT PRIMARY KEY,
    standard_id     INT NULL,
    audit_type      VARCHAR(100),       -- e.g., 'Internal Audit', 'External Accreditation', 'Process Audit'
    title           VARCHAR(200)        NOT NULL,
    description     TEXT,
    scheduled_date  DATE,
    actual_date     DATE,
    auditor_name    VARCHAR(100),
    auditor_email   VARCHAR(150),
    scope           TEXT,               -- What was audited (departments, processes)
    findings        TEXT,               -- Findings summary and details
    status          ENUM('Pending','In Progress','Completed','Cancelled') DEFAULT 'Pending',
    created_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ,CONSTRAINT fk_qa_audit_standard FOREIGN KEY (standard_id) REFERENCES qa_standards(standard_id) ON DELETE SET NULL
);

-- If qa_audits already exists in your local DB, run this manually before using Standards linkage:
-- ALTER TABLE qa_audits ADD COLUMN standard_id INT NULL AFTER audit_id;
-- ALTER TABLE qa_audits ADD CONSTRAINT fk_qa_audit_standard FOREIGN KEY (standard_id) REFERENCES qa_standards(standard_id) ON DELETE SET NULL;

-- Corrective/Preventive Action Plans
CREATE TABLE IF NOT EXISTS qa_action_plans (
    action_id       INT AUTO_INCREMENT PRIMARY KEY,
    audit_id        INT,                         -- Link to audit if from findings
    title           VARCHAR(200)        NOT NULL,
    description     TEXT,
    root_cause      TEXT,
    action_type     ENUM('Corrective','Preventive','Improvement') DEFAULT 'Corrective',
    priority        ENUM('Critical','High','Medium','Low') DEFAULT 'High',
    assigned_to     VARCHAR(100)        NOT NULL,
    assigned_to_email VARCHAR(150),
    department      VARCHAR(100),
    target_date     DATE                NOT NULL,
    actual_date     DATE,
    status          ENUM('Open','In Progress','Pending Verification','Closed','Cancelled') DEFAULT 'Open',
    expected_outcome TEXT,
    actual_outcome  TEXT,
    effectiveness_verified TINYINT(1) DEFAULT 0,
    verified_by     VARCHAR(100),
    verified_date   DATE,
    created_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP           DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ap_audit FOREIGN KEY (audit_id) REFERENCES qa_audits(audit_id) ON DELETE SET NULL
);


-- ============================================================
-- SAMPLE DATA
-- ============================================================

INSERT INTO qa_indicators (name, description, target_value, unit, category) VALUES
('Board Exam Passing Rate',     'Percentage of graduates passing licensure/board exams',    80.00, '%',     'Academic'),
('Graduation Rate',             'Percentage of enrolled students who graduate on time',      75.00, '%',     'Academic'),
('Student Satisfaction Score',  'Average satisfaction rating from student surveys',          4.00,  'score', 'Student Life'),
('Faculty Evaluation Average',  'Average score from faculty performance evaluations',        85.00, '%',     'Faculty'),
('Research Output Count',       'Number of research publications per year',                  10.00, 'count', 'Research'),
('Dropout Rate',                'Percentage of students who drop out per semester',           5.00, '%',     'Academic'),
('Employer Satisfaction Rate',  'Satisfaction rating from employers of graduates',            80.00, '%',     'Industry'),
('Course Completion Rate',      'Percentage of enrolled students completing courses',         90.00, '%',     'Academic');

INSERT INTO qa_records (indicator_id, year, semester, actual_value, remarks, recorded_by) VALUES
(1, 2023, 'Annual', 78.50,  'Slightly below target; remedial programs recommended', 'QA Office'),
(1, 2024, 'Annual', 82.10,  'Target achieved; improvement noted after review program', 'QA Office'),
(2, 2023, 'Annual', 71.00,  'Below target; advising program strengthened', 'QA Office'),
(2, 2024, 'Annual', 74.50,  'Below target; continuing improvement', 'QA Office'),
(3, 2023, '1st',    3.80,   'Good but below target; action plan initiated', 'QA Office'),
(3, 2023, '2nd',    4.10,   'Target exceeded', 'QA Office'),
(4, 2023, 'Annual', 83.00,  'Slightly below target', 'QA Office'),
(4, 2024, 'Annual', 87.50,  'Target exceeded; training programs effective', 'QA Office'),
(5, 2023, 'Annual', 8.00,   'Below target; research funding increased', 'QA Office'),
(5, 2024, 'Annual', 12.00,  'Target exceeded', 'QA Office');

INSERT INTO surveys (title, description, target_audience, status, start_date, end_date, qr_token, created_date) VALUES
('Student Experience Survey AY 2024-2025 1st Sem', 'Evaluate overall student experience and services', 'Student',  'Active', '2024-09-01', '2024-10-31', 'tok_student_2024_s1', '2024-08-25'),
('Faculty Performance Feedback',                    'Peer and admin feedback on faculty performance',   'Employee', 'Closed', '2024-01-15', '2024-02-15', 'tok_faculty_2024',   '2024-01-10'),
('Employer Satisfaction Survey 2024',               'Gather feedback from employers of PLSP graduates', 'Employer', 'Active', '2024-10-01', '2024-11-30', 'tok_employer_2024',  '2024-09-20');

INSERT INTO survey_questions (survey_id, question_text, question_type, is_required, sort_order) VALUES
(1, 'How satisfied are you with the overall academic quality of your program?',        'rating',          1, 1),
(1, 'How satisfied are you with the library and learning resources?',                  'rating',          1, 2),
(1, 'How would you rate the support from academic advisors?',                          'rating',          1, 3),
(1, 'What aspect of the university experience do you value most?',                     'text',            0, 4),
(1, 'Would you recommend PLSP to others?',                                             'yes_no',          1, 5),
(2, 'How would you rate the faculty member\'s teaching effectiveness?',                'rating',          1, 1),
(2, 'Does the faculty member demonstrate subject-matter expertise?',                   'yes_no',          1, 2),
(2, 'Rate the faculty member\'s availability for student consultations',               'rating',          1, 3),
(2, 'Additional comments on faculty performance',                                      'text',            0, 4),
(3, 'How satisfied are you with the skills of PLSP graduates?',                        'rating',          1, 1),
(3, 'Are PLSP graduates adequately prepared for industry demands?',                    'yes_no',          1, 2),
(3, 'Which competency area needs most improvement?',                                   'multiple_choice', 1, 3),
(3, 'Overall rating of PLSP graduates in your organization',                           'rating',          1, 4);

UPDATE survey_questions SET choices = '["Technical Skills","Communication","Problem Solving","Teamwork","Leadership"]' WHERE question_id = 12;

INSERT INTO qa_standards (title, description, compliance_body, category, status, effective_date) VALUES
('CHED Institutional Accreditation Framework', 'Requirements for institutional accreditation by CHED', 'CHED', 'Accreditation', 'Active', '2023-01-01'),
('ISO 9001:2015 Quality Management', 'International standard for quality management systems', 'ISO', 'Governance', 'Active', '2023-06-01'),
('Program Educational Objectives (PEO)', 'Institutional strategic direction and program outcomes', 'Internal', 'Academic', 'Active', '2022-09-01');

INSERT INTO qa_policies (title, description, category, standard_id, document_url, version, effective_date, expiry_date, status, owner, last_reviewed) VALUES
('Student Assessment Policy', 'Guidelines for assessing student learning outcomes', 'Student Assessment', 1, NULL, '2.0', '2024-01-01', '2026-12-31', 'Active', 'Academic Affairs', '2025-12-15'),
('Faculty Evaluation Procedure', 'Process for evaluating faculty performance', 'Faculty Development', 1, NULL, '1.5', '2023-09-01', NULL, 'Active', 'Human Resources', '2025-07-10'),
('Quality Management System Policy', 'Foundational policy for QA system operations', 'Governance', 2, NULL, '1.0', '2023-06-01', NULL, 'Active', 'QA Office', '2025-03-01');

-- Remove legacy local file URLs. New uploads are stored in Cloudinary.
UPDATE qa_policies
SET document_url = NULL
WHERE document_url LIKE '/qa_system/docs/%';
