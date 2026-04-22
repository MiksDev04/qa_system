# External Data Integration - Setup & Implementation Guide

## Overview

The QA System now automatically integrates KPI data from three external systems:
- **LMS (Learning Management System)** — Course completion rates, dropout rates, student satisfaction
- **HRIS (Human Resources Information System)** — Faculty evaluation scores, research publications
- **FACULTY_EVAL (Faculty Evaluation System)** — Teaching effectiveness, employer satisfaction

When you click "Add QA Record", you can either:
1. **Manual Entry** — Enter KPI values manually (existing workflow)
2. **From External Systems** — Select pre-populated data from integrated systems (new workflow)

---

## Quick Start (5 Steps)

### Step 1: Update Database Schema

Run the database migrations to add the new tables:

```bash
# Option A: Direct MySQL
mysql -u root -p plsp_integrated < c:\xampp\htdocs\qa_system\database.sql

# Option B: Via phpMyAdmin
# 1. Navigate to phpMyAdmin
# 2. Select 'plsp_integrated' database
# 3. Import the updated database.sql file
```

**New tables created:**
- `qa_external_data_mapping` — Stores field mappings from external systems to KPI indicators
- `qa_external_data_cache` — Caches raw data before transformation to qa_records

**Updated table:**
- `qa_records` — Added 2 new columns: `source_system` and `external_sync_id`

---

### Step 2: Initialize Mappings

Navigate to the mapping initializer:

```
http://yoursite/qa_system/config/sync_mappings.php?action=init
```

This creates default mappings for all KPI indicators:
- **LMS** → Course Completion Rate, Dropout Rate, Pass Rate, Graduation Rate, Student Satisfaction
- **HRIS** → Faculty Evaluation Average, Research Output Count
- **FACULTY_EVAL** → Faculty Evaluation Average, Employer Satisfaction Rate

**Response example:**
```json
{
  "success": true,
  "message": "Initialized mappings: 12 successful, 0 failed",
  "success_count": 12,
  "error_count": 0
}
```

---

### Step 3: Review Sample Data Files

The system includes sample JSON files in `/data/` directory:

**Location:** `c:\xampp\htdocs\qa_system\data\`

**Files:**
- `lms_data.json` — Learning Management System data (2024-2025 semesters)
- `hris_data.json` — Human Resources data (faculty evaluations, research publications)
- `faculty_evaluation_data.json` — Faculty evaluation results and employer satisfaction

**Sample structure (lms_data.json):**
```json
{
  "system": "Learning Management System (LMS)",
  "last_updated": "2026-04-21",
  "data": [
    {
      "academic_period": "2024-1st",
      "year": 2024,
      "semester": "1st",
      "metrics": {
        "course_completion_rate": 88.50,
        "dropout_rate": 5.20,
        "pass_rate": 81.20,
        "graduation_rate": 73.50,
        "student_satisfaction_score": 4.15
      }
    }
  ]
}
```

---

### Step 4: Trigger Initial Sync

Navigate to the External Data Integration page:

```
http://yoursite/qa_system/pages/integration_sync_history.php
```

Click the **"Sync Now"** button to pull data from all JSON files:

1. Reads data from `/data/*.json` files
2. Caches raw data in `qa_external_data_cache`
3. Creates `qa_records` from cached data
4. Links records to source systems via `source_system` field

**Response:**
```json
{
  "success": true,
  "message": "Auto-sync complete. Cached 15 records, created 14, skipped 1, failed 0",
  "sync_operation_id": 1234567890,
  "cached_records": 15,
  "created_records": 14,
  "skipped_records": 1,
  "failed_records": 0
}
```

---

### Step 5: Add QA Records (New Workflow)

Navigate to QA Records page:

```
http://yoursite/qa_system/pages/records.php
```

Click **"Add Record"** button:

1. **Tab 1: Manual Entry** — Traditional form (unchanged)
2. **Tab 2: From External Systems** — NEW
   - Select data source (LMS, HRIS, FACULTY_EVAL)
   - Choose metric (course_completion_rate, faculty_evaluation_average, etc.)
   - Review available data (shows all cached values for that metric)
   - Click "Select" on the row you want
   - Form auto-populates with Year, Semester, Value
   - Choose indicator if not pre-filled
   - Click "Save" — Record created with `source_system='LMS'/'HRIS'/'FACULTY_EVAL'`

---

## API Reference

### External Data Sync API

**Base URL:** `http://yoursite/qa_system/api/external_data_sync.php`

#### 1. Get Mapping Configuration

```
GET /api/external_data_sync.php?action=get_mapping
```

**Response:**
```json
{
  "success": true,
  "message": "Retrieved 12 mappings",
  "data": [
    {
      "mapping_id": 1,
      "source_system": "LMS",
      "external_field": "course_completion_rate",
      "indicator_id": 8,
      "indicator_name": "Course Completion Rate",
      "unit": "%",
      "target_value": 90.00,
      "sync_frequency": "semester",
      "is_active": 1,
      "last_sync_date": "2026-04-21 14:30:00",
      "sync_status": "synced"
    }
  ]
}
```

---

#### 2. Sync from External (Load JSON → Cache)

```
GET /api/external_data_sync.php?action=sync_from_external
GET /api/external_data_sync.php?action=sync_from_external&source=LMS
```

**Parameters:**
- `source` (optional) — Filter to single system: 'LMS', 'HRIS', 'FACULTY_EVAL'

**Response:**
```json
{
  "success": true,
  "message": "Synced 15 records from external systems",
  "synced_records": 15,
  "sync_operation_id": 1234567890,
  "errors": [],
  "timestamp": "2026-04-21 14:35:22"
}
```

---

#### 3. Validate and Create Records

```
GET /api/external_data_sync.php?action=validate_and_create&sync_operation_id=1234567890
```

**Parameters:**
- `sync_operation_id` (required) — From sync_from_external response

**Response:**
```json
{
  "success": true,
  "message": "Created 14 records, skipped 1, failed 0",
  "created_records": 14,
  "skipped_records": 1,
  "failed_records": 0,
  "created_list": [
    {
      "status": "created",
      "record_id": 45,
      "indicator_id": 8,
      "year": 2024,
      "semester": "1st",
      "value": 88.5,
      "source": "LMS"
    }
  ]
}
```

---

#### 4. Auto Sync (Full Workflow)

```
GET /api/external_data_sync.php?action=auto_sync
GET /api/external_data_sync.php?action=auto_sync&source=HRIS
```

Combines sync_from_external + validate_and_create in one call.

**Response:**
```json
{
  "success": true,
  "message": "Auto-sync complete. Cached 15 records, created 14, skipped 1, failed 0",
  "sync_operation_id": 1234567890,
  "cached_records": 15,
  "created_records": 14,
  "skipped_records": 1,
  "failed_records": 0
}
```

---

#### 5. Get Sync Status

```
GET /api/external_data_sync.php?action=get_sync_status&limit=20
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "source_system": "LMS",
      "last_sync_date": "2026-04-21 14:35:22",
      "sync_status": "synced",
      "mapping_count": 5
    }
  ]
}
```

---

#### 6. Get Cached Data (Review Before Creation)

```
GET /api/external_data_sync.php?action=get_cache
GET /api/external_data_sync.php?action=get_cache&sync_operation_id=1234567890&limit=50
```

**Response:**
```json
{
  "success": true,
  "count": 15,
  "data": [
    {
      "cache_id": 1,
      "source_system": "LMS",
      "academic_period": "2024-1st",
      "year": 2024,
      "semester": "1st",
      "external_field": "course_completion_rate",
      "raw_value": "88.5",
      "converted_value": 88.5,
      "validation_status": "valid",
      "indicator_id": 8,
      "indicator_name": "Course Completion Rate",
      "synced_to_record_id": 45,
      "sync_date": "2026-04-21 14:35:22"
    }
  ]
}
```

---

## Records API (Updated)

### Create Record with Source Tracking

```
POST /api/records.php
```

**Parameters:**
```json
{
  "action": "create",
  "indicator_id": 8,
  "year": 2024,
  "semester": "1st",
  "actual_value": 88.5,
  "recorded_by": "Auto-sync from LMS",
  "remarks": "Pulled from LMS on 2026-04-21",
  "source_system": "LMS"  // NEW: 'LMS', 'HRIS', 'FACULTY_EVAL', or 'Manual'
}
```

**Response:**
```json
{
  "status": "success",
  "message": "Record created.",
  "id": 45,
  "source_system": "LMS"
}
```

### List Records (with Source Tracking)

```
GET /api/records.php?action=list&page=1&per_page=20
```

**Response:**
```json
{
  "status": "success",
  "message": "OK",
  "data": [
    {
      "record_id": 45,
      "indicator_id": 8,
      "year": 2024,
      "semester": "1st",
      "actual_value": 88.5,
      "remarks": "Pulled from LMS",
      "recorded_by": "Auto-sync from LMS",
      "source_system": "LMS",  // NEW: Shows data origin
      "external_sync_id": 1,
      "created_at": "2026-04-21 14:35:22",
      "indicator_name": "Course Completion Rate",
      "target_value": 90.0,
      "unit": "%"
    }
  ]
}
```

---

## File Structure

```
/qa_system/
├── api/
│   ├── external_data_sync.php    [NEW] Sync API endpoint
│   ├── records.php               [UPDATED] Added source_system tracking
│   └── ...
├── config/
│   ├── sync_mappings.php         [NEW] Initialize mappings
│   ├── database.php
│   └── ...
├── data/
│   ├── lms_data.json             [NEW] Sample LMS data
│   ├── hris_data.json            [NEW] Sample HRIS data
│   └── faculty_evaluation_data.json [NEW] Sample Faculty Eval data
├── pages/
│   ├── integration_sync_history.php [NEW] Sync management page
│   ├── records.php               [UPDATED] Added external data tab
│   └── ...
├── database.sql                  [UPDATED] New tables added
└── ...
```

---

## Connecting Real External Systems (Future)

Currently, the system reads from **JSON sample files**. To connect to real systems:

### 1. LMS Integration

**Replace in `external_data_sync.php` (lines ~90-130):**

```php
// Instead of reading lms_data.json
// Call LMS API:
$lms_url = "https://yourLMS.com/api/v1/kpi/metrics";
$lms_data = curl_get($lms_url, [
    'auth_token' => LMS_API_TOKEN,
    'period' => '2024-1st'
]);

// Then process $lms_data same way as JSON
```

**Supported metrics from LMS:**
- Course completion rate, Dropout rate, Pass rate
- Graduation rate, Student satisfaction score
- Average GPA, Credit hours earned

---

### 2. HRIS Integration

**Replace in `external_data_sync.php` (lines ~130-170):**

```php
// Call HRIS API:
$hris_url = "https://yourHRIS.com/api/v2/faculty/metrics";
$hris_data = curl_get($hris_url, [
    'api_key' => HRIS_API_KEY,
    'period' => '2024-1st'
]);
```

**Supported metrics from HRIS:**
- Faculty evaluation average, Research publications
- Faculty satisfaction score, Employee retention rate

---

### 3. Faculty Evaluation Integration

**Replace in `external_data_sync.php` (lines ~170-210):**

```php
// Call Faculty Eval API:
$faculty_url = "https://yourFacultyEval.com/api/evaluations";
$faculty_data = curl_get($faculty_url, [
    'token' => FACULTY_API_TOKEN,
    'academic_period' => '2024-1st'
]);
```

**Supported metrics from Faculty Eval:**
- Faculty evaluation average, Employer satisfaction
- Teaching effectiveness, Graduate competency rating

---

## Troubleshooting

### "No cached data found" Error

**Problem:** External Data tab shows no data

**Solution:**
1. Navigate to `pages/integration_sync_history.php`
2. Click "Sync Now" button
3. Wait for sync to complete
4. Refresh Records page
5. Try again

---

### "Record already exists for this period"

**Problem:** Sync operation skips creating a record

**Reason:** A record already exists for indicator + year + semester

**Solution:**
- Option A: Delete the existing record and sync again
- Option B: Manually update the existing record instead

---

### Mapping shows "Error" Status

**Problem:** Sync failed for a mapping

**Solution:**
1. Check `qa_external_data_mapping.error_message` for details
2. Verify JSON file syntax in `/data/` directory
3. Ensure external field names in JSON match mapping configuration
4. Try re-syncing via `integration_sync_history.php`

---

## Sample Data Reference

### LMS Data (lms_data.json)

**Metrics provided:**
- `course_completion_rate` — % of students who complete courses (Target: 90%)
- `dropout_rate` — % of students who drop out (Target: 5%)
- `pass_rate` — % of students who pass courses (Target: 80%)
- `graduation_rate` — % of students who graduate on time (Target: 75%)
- `student_satisfaction_score` — Avg satisfaction 1-5 (Target: 4.0)

**Data coverage:** 2024 (1st, 2nd semester), 2025 (1st, 2nd semester)

---

### HRIS Data (hris_data.json)

**Metrics provided:**
- `faculty_evaluation_average` — Avg evaluation score (Target: 85%)
- `research_publications_count` — Number of publications per year (Target: 10)
- `faculty_satisfaction_score` — Avg satisfaction (Target: 80%)

**Data coverage:** 2024 (1st, 2nd semester, annual), 2025 (1st, 2nd semester)

---

### Faculty Evaluation Data (faculty_evaluation_data.json)

**Metrics provided:**
- `faculty_evaluation_average` — Avg evaluation score (Target: 85%)
- `employer_satisfaction_with_graduates` — Employer satisfaction (Target: 80%)
- `teaching_effectiveness_score` — Avg teaching score (Target: 85%)
- `graduate_competency_rating` — Avg competency 1-5 (Target: 4.0)

**Data coverage:** 2024 (1st, 2nd semester, annual), 2025 (1st, 2nd semester)

---

## Dashboard Integration (Future Phase)

The dashboard will eventually display:
- **Sync status badges** on each KPI indicator
- **Last sync timestamp** for each external system
- **Data source indicator** showing if value came from LMS/HRIS/FACULTY_EVAL or was manually entered
- **Automatic refresh** after scheduled syncs

---

## Scheduled Syncs (Future)

Set up automated syncs by adding a cron job:

```bash
# Sync every morning at 6 AM
0 6 * * * curl -s "http://yoursite/qa_system/api/external_data_sync.php?action=auto_sync" >> /var/log/qa_sync.log

# Sync every Monday at 8 AM
0 8 * * 1 curl -s "http://yoursite/qa_system/api/external_data_sync.php?action=auto_sync" >> /var/log/qa_sync.log
```

---

## Summary of Changes

| Component | Change | Impact |
|-----------|--------|--------|
| `database.sql` | Added 2 new tables, 2 new columns | Schema migration required |
| `api/records.php` | Added source_system parameter | Records now track data origin |
| `api/external_data_sync.php` | NEW API endpoint | Enables automated data sync |
| `pages/records.php` | Added "From External Systems" tab | Users can select external data |
| `pages/integration_sync_history.php` | NEW management page | View sync history and status |
| `config/sync_mappings.php` | NEW initializer | Set up default mappings |
| `data/*.json` | NEW sample files | 3 JSON files with sample data |

---

## Support

For issues or questions:
1. Check this guide's Troubleshooting section
2. Review API response JSON for error details
3. Check MySQL error logs in `qa_external_data_cache.error_message`
4. Verify JSON file syntax with a JSON validator
5. Contact the development team with sync operation ID for debugging

---

**Last Updated:** April 21, 2026  
**Version:** 1.0 - Initial Release
