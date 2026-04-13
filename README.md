# Quality Assurance Management System
## PLSP Integrated College Information Systems
### ByteBandits Development Team

---

## Table of Contents
1. [Project Overview](#project-overview)
2. [Team Information](#team-information)
3. [System Architecture](#system-architecture)
4. [Technology Stack](#technology-stack)
5. [Folder Structure](#folder-structure)
6. [Database Design](#database-design)
7. [Pages & Features](#pages--features)
8. [REST API Reference](#rest-api-reference)
9. [System Integration](#system-integration)
10. [Installation Guide](#installation-guide)
11. [Design System](#design-system)
12. [System Flow](#system-flow)

---

## Project Overview

The **Quality Assurance Management System (QA System)** is one module of the **Integrated College Information System** for **Pamantasan ng Lungsod ng San Pablo (PLSP)**. It is developed by the **ByteBandits** group as part of a multi-team ERP-like platform.

The system enables the QA office to:
- Define and track Key Performance Indicators (KPIs/indicators)
- Record actual measured values against targets
- Create surveys with embedded questionnaires
- Collect responses via QR code-accessible public forms
- Generate detailed, filterable, and exportable reports

All data is stored in a **shared centralized MySQL database** (`plsp_integrated`) that integrates with other team modules (HRIS, LMS, Finance, Scheduling, Facilities, Faculty Evaluation, Inventory).

---

## Team Information

| Field        | Detail                                           |
|--------------|--------------------------------------------------|
| Team Name    | ByteBandits                                      |
| Module       | Quality Assurance Management System              |
| Institution  | Pamantasan ng Lungsod ng San Pablo (PLSP)        |
| Course       | Systems Integration / Capstone Project           |
| Database     | `plsp_integrated` (shared, MySQL)                |

---

## System Architecture

```
┌─────────────────────────────────────────────────────────┐
│                    CLIENT BROWSER                        │
│  HTML + Bootstrap 5 + jQuery + AJAX + Chart.js          │
└──────────────────────┬──────────────────────────────────┘
                       │  HTTP Requests (GET/POST)
                       │  REST API (JSON responses)
┌──────────────────────▼──────────────────────────────────┐
│                   PHP BACKEND (Apache)                   │
│  ┌──────────────┐  ┌──────────────┐  ┌───────────────┐  │
│  │  Pages/UI    │  │  REST APIs   │  │   Includes    │  │
│  │  (PHP+HTML)  │  │  /api/*.php  │  │  header/footer│  │
│  └──────────────┘  └──────┬───────┘  └───────────────┘  │
└─────────────────────────── ┼───────────────────────────┘
                             │  mysqli queries
┌────────────────────────────▼────────────────────────────┐
│              MySQL Database: plsp_integrated             │
│  qa_indicators │ qa_records │ surveys │ survey_questions │
│  survey_responses │ survey_answers                       │
│  [+ shared tables: departments, employees, students…]   │
└─────────────────────────────────────────────────────────┘
```

### Client–Server Communication Pattern

```
Client (Browser)                    Server (PHP API)
      │                                    │
      │  POST /qa_system/api/surveys.php   │
      │  { action: "create", title: … }   │
      │ ────────────────────────────────► │
      │                                    │  mysqli query
      │                                    │ ──────────► DB
      │                                    │ ◄────────── DB
      │  { status: "success",              │
      │    message: "Survey created.",     │
      │    id: 4 }                         │
      │ ◄────────────────────────────────  │
```

---

## Technology Stack

| Layer        | Technology                              |
|--------------|-----------------------------------------|
| Frontend     | HTML5, CSS3, Bootstrap 5.3              |
| JavaScript   | Vanilla JS + jQuery 3.7                 |
| Charts       | Chart.js 4.4                            |
| PDF Export   | jsPDF + jsPDF-AutoTable                 |
| QR Codes     | qrcode.js                               |
| Backend      | PHP 8.1+ (procedural)                   |
| DB Driver    | mysqli (as required)                    |
| Database     | MySQL 8.0+                              |
| Web Server   | Apache (with mod_rewrite)               |

---

## Folder Structure

```
qa_system/
│
├── index.php                   ← Dashboard (stats + charts)
├── survey.php                  ← Public survey form (QR access, no login)
├── database.sql                ← Full SQL schema + sample data
│
├── config/
│   └── database.php            ← DB connection (mysqli singleton)
│
├── includes/
│   ├── header.php              ← Sidebar, topbar, theme toggle, <head>
│   └── footer.php              ← JS scripts, closing tags
│
├── pages/
│   ├── indicators.php          ← KPI Indicators – CRUD + filter + paginate
│   ├── records.php             ← QA Records – CRUD + filter + paginate
│   ├── surveys.php             ← Survey Management – CRUD + questions builder
│   ├── responses.php           ← Response viewer + detail modal
│   └── reports.php             ← Filterable reports, PDF + CSV export
│
├── api/
│   ├── indicators.php          ← REST API: create/update/delete/list indicators
│   ├── records.php             ← REST API: create/update/delete records
│   ├── surveys.php             ← REST API: create/update/delete surveys + questions
│   └── responses.php           ← REST API: get_detail, get_stats
│
└── assets/
    ├── css/
    │   └── main.css            ← Full design system (dark/light theme, all components)
    └── js/
        └── main.js             ← Theme toggle, sidebar, toast, AJAX helper, pagination
```

---

## Database Design

### Tables Owned by ByteBandits (QA Module)

#### `qa_indicators`
| Column        | Type                 | Description                          |
|---------------|----------------------|--------------------------------------|
| indicator_id  | INT PK AUTO          | Primary key                          |
| name          | VARCHAR(150)         | Indicator name (e.g. "Board Passing Rate") |
| description   | TEXT                 | Detailed description                 |
| target_value  | DECIMAL(10,2)        | Target/benchmark value               |
| unit          | VARCHAR(50)          | Unit of measure (%, score, count…)   |
| category      | VARCHAR(100)         | Grouping (Academic, Faculty…)        |
| status        | ENUM Active/Inactive | Whether indicator is tracked         |
| created_at    | TIMESTAMP            | Auto-set on creation                 |
| updated_at    | TIMESTAMP            | Auto-updated on changes              |

> **Changes from original schema:** Added `unit`, `category`, `status`, `created_at`, `updated_at` for full operational use.

---

#### `qa_records`
| Column        | Type                        | Description                      |
|---------------|-----------------------------|----------------------------------|
| record_id     | INT PK AUTO                 | Primary key                      |
| indicator_id  | INT FK → qa_indicators      | Linked indicator                 |
| year          | YEAR                        | Measurement year                 |
| semester      | ENUM 1st/2nd/Summer/Annual  | Period                           |
| actual_value  | DECIMAL(10,2)               | Measured actual value            |
| remarks       | TEXT                        | Notes or observations            |
| recorded_by   | VARCHAR(100)                | Person or department             |
| created_at    | TIMESTAMP                   | Auto-set on creation             |
| updated_at    | TIMESTAMP                   | Auto-updated                     |

> **Changes from original schema:** Added `semester`, `remarks`, `recorded_by`, `created_at`, `updated_at`.

---

#### `surveys`
| Column          | Type                            | Description                    |
|-----------------|---------------------------------|--------------------------------|
| survey_id       | INT PK AUTO                     | Primary key                    |
| title           | VARCHAR(200)                    | Survey title                   |
| description     | TEXT                            | Purpose/description            |
| target_audience | ENUM Student/Employee/Employer/Alumni/General | Who should fill this |
| status          | ENUM Draft/Active/Closed        | Availability status            |
| start_date      | DATE                            | When it becomes available      |
| end_date        | DATE                            | Expiry date                    |
| qr_token        | VARCHAR(64) UNIQUE              | Token for QR code URL          |
| created_date    | DATE                            | Date created                   |
| created_at      | TIMESTAMP                       | Auto-set                       |
| updated_at      | TIMESTAMP                       | Auto-updated                   |

> **Changes from original schema:** Added `target_audience`, `status`, `start_date`, `end_date`, `qr_token`, timestamps. Original `respondent_role`, `question`, `answer`, `rating` moved to dedicated child tables.

---

#### `survey_questions` *(NEW TABLE)*
| Column        | Type                                     | Description              |
|---------------|------------------------------------------|--------------------------|
| question_id   | INT PK AUTO                              | Primary key              |
| survey_id     | INT FK → surveys                         | Parent survey            |
| question_text | TEXT                                     | The question             |
| question_type | ENUM rating/text/multiple_choice/yes_no  | Input type               |
| choices       | TEXT (JSON)                              | For multiple_choice only |
| is_required   | TINYINT(1)                               | 1 = required             |
| sort_order    | INT                                      | Display order            |

---

#### `survey_responses` *(NEW TABLE — replaces original `survey_responses`)*
| Column           | Type           | Description                   |
|------------------|----------------|-------------------------------|
| response_id      | INT PK AUTO    | Primary key                   |
| survey_id        | INT FK         | Which survey                  |
| respondent_role  | VARCHAR(50)    | Student, Employee, etc.       |
| respondent_name  | VARCHAR(150)   | Optional name                 |
| respondent_email | VARCHAR(150)   | Optional email                |
| session_token    | VARCHAR(64)    | Unique submission token       |
| submitted_at     | TIMESTAMP      | When submitted                |

---

#### `survey_answers` *(NEW TABLE)*
| Column      | Type             | Description                       |
|-------------|------------------|-----------------------------------|
| answer_id   | INT PK AUTO      | Primary key                       |
| response_id | INT FK → survey_responses | Parent response          |
| question_id | INT FK → survey_questions | Which question           |
| answer_text | TEXT             | For text/yes_no/multiple_choice   |
| rating      | INT              | 1–5 for rating questions          |

---

### Entity Relationship Summary

```
qa_indicators ──< qa_records
surveys ──< survey_questions
surveys ──< survey_responses ──< survey_answers
survey_questions ──< survey_answers
```

---

## Pages & Features

### 1. Dashboard (`index.php`)
- **No login required** — goes directly to dashboard
- **Sidebar navigation** with icons and active state
- **Dark mode toggle** (persisted to localStorage)
- **Stat cards:** Active Indicators, QA Records, Total Surveys, Active Surveys, Total Responses, KPIs On Target
- **Chart 1 (Bar):** KPI Performance — Actual vs Target (all active indicators)
- **Chart 2 (Line):** Survey Response Trend (last 6 months)
- **Recent Records table:** Latest 5 QA records with progress bars and status badges

---

### 2. KPI Indicators (`pages/indicators.php`)
- **Full CRUD** via modal (create, edit, delete)
- **Filters:** Search by name/description, Category, Status
- **Pagination** (10 per page)
- **Inline description preview** in table rows
- **Category badges** and status indicators
- **Delete protection** — cannot delete if records exist
- **Columns:** Name, Category, Target, Unit, Status, Actions

---

### 3. QA Records (`pages/records.php`)
- **Full CRUD** via modal
- **Filters:** Indicator, Year, Semester
- **Pagination** (10 per page)
- **Visual progress bars** showing % of target
- **Variance column** (green for positive, red for negative)
- **Met/Below Target status badge**
- **Columns:** Indicator, Period, Actual, Target, Variance, Status, Recorded By, Actions

---

### 4. Manage Surveys (`pages/surveys.php`)
- **Survey cards** layout (not table) for visual clarity
- **Create + Edit survey** in one modal with:
  - Survey metadata (title, description, audience, status, dates)
  - **Inline questionnaire builder** — add unlimited questions
  - Per-question: text, type (rating/text/multiple_choice/yes_no), required toggle, choices
- **View Questions** — see questions in a read modal without editing
- **QR Code generation** — click QR button, modal shows scannable code + URL
- **Status filter + audience filter**
- **Pagination** (8 surveys per page)
- **Delete** cascades through questions, responses, answers

---

### 5. Responses (`pages/responses.php`)
- **View all submitted responses** in a table
- **Filters:** By survey, by respondent role
- **Summary stats:** Total responses, Avg rating, Today's responses
- **Per-row info:** Survey, Respondent, Role, Avg Rating, Answers count, Submitted date
- **Eye button** → modal showing all answers for that response
- Response detail shows: who submitted, all Q&A pairs, star display for ratings

---

### 6. Public Survey Form (`survey.php`) *(Separate — no sidebar)*
- Accessed via **QR code URL**: `/qa_system/survey.php?token=tok_xxxxx`
- **No login, no sidebar** — completely standalone page
- Shows survey title, description
- **Optional respondent info** (name, email, self-select role)
- Renders all questions by type:
  - **Rating** → interactive star buttons (1–5)
  - **Text** → textarea
  - **Yes/No** → radio buttons
  - **Multiple Choice** → radio buttons with configured options
- **Required validation** before submit
- **Duplicate prevention** via session token
- **Success screen** after submission with thank-you message
- **Error screens** for expired/inactive surveys

---

### 7. Reports (`pages/reports.php`)
- **4 Report Types:**
  1. **KPI Performance Summary** — actual vs target per indicator/period
  2. **Indicator Trend Analysis** — historical values for selected indicator(s)
  3. **Survey Summary** — per-survey: response counts, avg rating, questions
  4. **Response Detail** — granular per-answer data for survey analysis
- **Filters adapt** to report type (dynamic show/hide)
- **Filters available:** Year range, Semester, Category, Indicator, Survey
- **Export to CSV** — native browser download
- **Export to PDF** — jsPDF with autoTable, landscape A4, PLSP header
- **Record count** shown on report header
- **Empty state** when no data matches filters

---

## REST API Reference

All APIs return JSON: `{ status: "success"|"error", message: "…", [data: …] }`

### Indicators API — `POST /api/indicators.php`

| action   | Params                                              | Response        |
|----------|-----------------------------------------------------|-----------------|
| `create` | name, description, target_value, unit, category, status | id             |
| `update` | indicator_id, name, description, target_value, unit, category, status | —  |
| `delete` | indicator_id                                        | error if records exist |
| `list`   | (GET) status?                                       | data: [array]  |

### Records API — `POST /api/records.php`

| action   | Params                                                      | Response  |
|----------|-------------------------------------------------------------|-----------|
| `create` | indicator_id, year, semester, actual_value, recorded_by, remarks | id   |
| `update` | record_id, indicator_id, year, semester, actual_value, recorded_by, remarks | — |
| `delete` | record_id                                                   | —         |

### Surveys API — `POST /api/surveys.php`

| action          | Params                                                   | Response        |
|-----------------|----------------------------------------------------------|-----------------|
| `create`        | title, description, target_audience, status, start_date, end_date, questions (JSON) | id, token |
| `update`        | survey_id, title, …, questions (JSON)                   | —               |
| `delete`        | survey_id                                                | cascades all    |
| `get_questions` | (GET) survey_id                                          | data: [array]  |

**Questions JSON format:**
```json
[
  {
    "question_id": "",
    "question_text": "How satisfied are you?",
    "question_type": "rating",
    "choices": null,
    "is_required": 1,
    "sort_order": 1
  }
]
```

### Responses API — `GET /api/responses.php`

| action       | Params      | Response                              |
|--------------|-------------|---------------------------------------|
| `get_detail` | response_id | response meta + answers array         |
| `get_stats`  | survey_id   | per-question avg ratings + totals     |

---

## System Integration

The QA System integrates with other ByteBandits systems through the shared `plsp_integrated` database:

| Integration Point       | How                                                   |
|-------------------------|-------------------------------------------------------|
| **HRIS (BusyBugs)**     | `employees` table → `recorded_by` can reference employee names; faculty evaluation scores feed QA indicators |
| **Faculty Evaluation (Jollidave)** | Evaluation scores can be pulled as QA indicator values (Faculty Evaluation Average KPI) |
| **LMS (Artisans)**      | Course completion rates from LMS → qa_records for "Course Completion Rate" KPI |
| **Finance (CodeBlue)**  | Budget data not directly integrated but shared DB allows cross-module reporting |
| **Departments**         | QA indicators can be scoped to `departments` from HRIS |

**Example integration flow:**
```
Faculty Evaluation System (Jollidave)
  → Completes faculty evaluations
  → QA Office reads average scores from faculty_evaluations table
  → Records value in qa_records (indicator: "Faculty Evaluation Average")
  → QA Reports show trend of faculty performance
```

---

## Installation Guide

### Requirements
- PHP 8.0+
- MySQL 8.0+ or MariaDB 10.4+
- Apache with mod_rewrite enabled
- Web root or virtual host configured

### Steps

**1. Clone/Copy Files**
```bash
# Place the qa_system folder in your web root
cp -r qa_system/ /var/www/html/qa_system/
# Or for XAMPP:
cp -r qa_system/ C:/xampp/htdocs/qa_system/
```

**2. Create Database**
```sql
CREATE DATABASE IF NOT EXISTS plsp_integrated CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**3. Import Schema**
```bash
mysql -u root -p plsp_integrated < qa_system/database.sql
```

**4. Configure Database**

Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // your MySQL user
define('DB_PASS', '');           // your MySQL password
define('DB_NAME', 'plsp_integrated');
```

**5. Configure Base URL**

If not running at web root, update paths in `includes/header.php` and `includes/footer.php`. All asset and API paths use `/qa_system/` as base.

**6. Access the System**
```
Dashboard:    http://localhost/qa_system/
Survey Form:  http://localhost/qa_system/survey.php?token=tok_student_2024_s1
```

---

## Design System

### Color Tokens (CSS Variables)

| Variable        | Dark Value   | Light Value  | Usage                   |
|-----------------|--------------|--------------|-------------------------|
| `--bg-base`     | `#0d0f14`    | `#f0f2f8`    | Page background         |
| `--bg-card`     | `#1a1e2e`    | `#ffffff`    | Cards, modals           |
| `--accent`      | `#4f8ef7`    | `#3b7cf4`    | Primary buttons, links  |
| `--accent-2`    | `#7c63f5`    | `#6f52e8`    | Gradient end            |
| `--success`     | `#22c55e`    | `#16a34a`    | Met targets, success    |
| `--warning`     | `#f59e0b`    | `#d97706`    | Near-target, draft      |
| `--danger`      | `#ef4444`    | `#dc2626`    | Below target, delete    |
| `--info`        | `#06b6d4`    | `#0891b2`    | Neutral badges          |

### Typography
- **Primary font:** Space Grotesk (Google Fonts) — used for all UI text
- **Mono font:** DM Mono — used for codes, labels, metadata, pagination

### Theme
- Default: **Dark mode**
- Toggle persisted to `localStorage` as `qa_theme`
- Smooth transitions on all color changes

### Components Available
- `stat-card` — stat display with icon
- `qa-card` — surface card with border
- `qa-table` / `qa-table-wrapper` — styled tables
- `badge-status` — colored inline badges
- `qa-progress` / `qa-progress-bar` — thin progress bars
- `qa-form-control` — styled input/select/textarea
- `btn-qa`, `btn-qa-primary/secondary/danger/success/sm/icon` — buttons
- `qa-pagination` / `qa-page-btn` — paginator
- `qa-toast` — auto-dismiss notification
- `filter-bar` — filter row container
- `empty-state` — no-data placeholder
- `rating-stars` — interactive star rating (survey form)

---

## System Flow

```
1. Admin opens Dashboard
   └── Sees KPI charts, stat cards, recent records

2. Admin manages KPI Indicators
   └── Add/Edit/Delete indicators in modal
   └── Filter by category/status
   └── Paginate through list

3. Admin records QA data
   └── Go to QA Records
   └── Select indicator, enter actual value + period
   └── Progress bar + variance shown per record

4. Admin creates a Survey
   └── Go to Manage Surveys → New Survey
   └── Fill metadata (title, audience, dates, status)
   └── Add questions inline (type, choices, required flag)
   └── Save → system generates unique QR token

5. Share Survey via QR
   └── Click QR button on survey card
   └── Modal shows QR code + URL
   └── Print or share QR code

6. Respondent fills survey
   └── Scan QR code → opens survey.php?token=…
   └── Optionally enters name/role
   └── Answers all questions (star rating, text, radio)
   └── Submits → stored in survey_responses + survey_answers

7. Admin reviews responses
   └── Go to Responses page
   └── Filter by survey or role
   └── Click eye icon → see full answer detail

8. Admin generates report
   └── Go to Reports
   └── Select report type (KPI Summary, Trend, Survey, Detail)
   └── Apply filters (year, semester, category, indicator, survey)
   └── View tabular results
   └── Export to PDF (landscape A4 with PLSP header) or CSV
```

---

## Notes for Integration with Other Teams

- **Do not modify** shared tables: `departments`, `employees`, `students`, `rooms`, `reservations`, `courses`, `classes`, etc. These are owned by their respective teams.
- The QA system **only writes to**: `qa_indicators`, `qa_records`, `surveys`, `survey_questions`, `survey_responses`, `survey_answers`
- To read employee data for "recorded_by" or integration features, use read-only SELECT queries on the `employees` table
- All API endpoints return standardized JSON: `{ status, message, [data] }`
- Coordinate with **Jollidave** (Faculty Evaluation) for feeding evaluation scores as QA records

---

*ByteBandits · PLSP Quality Assurance Management System · 2025*
