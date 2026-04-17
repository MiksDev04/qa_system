# CHANGELOG - QA Management System

All notable changes to the Quality Assurance Management System are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## Unreleased

### Added
- (New features will be added here automatically)
- Added sample policy document files in `docs/` matching seeded `qa_policies.document_url` paths.

### Changed
- Added workflow rule to log each new user request in both TODO and CHANGELOG.
- Aligned Standards and Policies table layout with KPI Indicators, including row numbering and record spacing.
- Aligned Internal Audits table layout with KPI Indicators, including row numbering and consistent record spacing.
- Expanded Policies management to use additional `qa_policies` fields (version, linked standard, document URL, expiry date, last reviewed).
- Upgraded sample policy PDF files in `docs/` with a more professional format (header band, metadata section, structured content blocks, and footer).
- Migrated policy document storage from local files to Cloudinary uploads.

### Fixed
- Fixed Responses detail modal formatting and fallback values for respondent info and answers.
- Replaced broken character rendering in Responses page details (rating display and missing-value markers).
- Fixed QA Records status information display with clear status tiers and consistent status badge labels.
- Added a View action in Standards & Policies so policy details and document links can be read directly from the UI.
- Improved Policies View modal to preview PDF documents inline, with Open/Download fallback actions.
- Synchronized `qa_policies.document_url` values with generated files in `docs/` in both live DB records and `database.sql` seed sync block.
- Changed Policies Add/Edit form to use PDF file upload instead of manual document URL entry.
- Added backend policy document upload handling that stores uploaded files under `/qa_system/docs/policies/`.
- Fixed Internal Audits Findings fallback display to show `No findings` instead of garbled characters when empty.
- Fixed Cloudinary "Customer is marked as untrusted" PDF access issue by storing signed raw download URLs instead of blocked direct raw URLs.
- Fixed policy `document_url` truncation by expanding storage to support long signed Cloudinary URLs.
- Updated policy document UI to hide raw URLs and show a compact in-modal PDF preview with Open/Download actions.
- Fixed Policies View button preview detection for signed Cloudinary URLs so compact PDF preview appears reliably.

### Deprecated
- (Deprecations will be noted here)

### Removed
- (Removals will be noted here)
- Removed local policy document artifacts under `docs/`; policy files now use Cloudinary URLs.

### Security
- (Security-related changes will be noted here)

---

## [2.0.0] - 2026-04-13

### Added
- **Governance & Compliance Module**
  - Standards management (CHED, ISO, Internal requirements)
  - Policies management with version control
  - Internal Audits planning and tracking
  - Action Plans (Corrective, Preventive, Improvement)
  - Audit findings with severity levels

- **Enhanced Dashboard**
  - Governance stats cards (Standards, Policies, Audits, Action Plans)
  - Action Plans status pie chart
  - Pending action plans table
  - Recent internal audits table
  - Quick links to governance features

- **Database Enhancements**
  - `qa_standards` table (title, compliance_body, category, dates, status)
  - `qa_policies` table (title, category, owner, version, effective_date, status)
  - `qa_audits` table (title, type, scope, scheduled_date, auditor, status, findings_count)
  - `qa_audit_findings` table (audit_id, finding_text, severity, standard_violated, status)
  - `qa_action_plans` table (title, type, priority, assigned_to, department, target_date, status)

- **New API Endpoints**
  - `POST /api/standards.php` - Standards CRUD operations
  - `POST /api/audits.php` - Audits CRUD operations
  - `POST /api/action_plans.php` - Action Plans CRUD operations

- **New Pages**
  - `pages/standards.php` - Standards & Policies management
  - `pages/audits.php` - Internal Audits management
  - `pages/action_plans.php` - Action Plans management

### Changed
- Dashboard layout reorganized with Quality Data and Governance sections
- Updated navbar links to include Standards, Audits, and Actions
- Enhanced reports to include governance metrics

### Fixed
- (None)

### Documentation
- Comprehensive README.md with system architecture
- FEATURES_AND_FUNCTIONS.md with detailed user guides
- API reference documentation
- System flow diagrams and integration notes

---

## [1.0.0] - 2026-03-15

### Initial Release

#### Core Features
- **Dashboard** - Overview with KPI charts, stats, and recent records
- **KPI Indicators** - Full CRUD, filtering, categorization
- **QA Records** - Record actual performance data with variance tracking
- **Survey Management** - Create surveys with multiple question types
- **Public Survey Form** - QR-accessible, no login required
- **Survey Responses** - View and analyze collected responses
- **Reports** - Four report types with PDF/CSV export
- **Theme Toggle** - Dark/Light mode with localStorage persistence

#### Database Schema
- `qa_indicators` - Performance indicators/KPIs
- `qa_records` - Recorded indicator values
- `surveys` - Survey metadata and management
- `survey_questions` - Questions with multiple types
- `survey_responses` - Respondent submissions
- `survey_answers` - Individual question answers

#### API Endpoints
- `POST /api/indicators.php` - Indicators operations
- `POST /api/records.php` - Records operations
- `POST /api/surveys.php` - Survey operations
- `GET /api/responses.php` - Response analytics

#### Frontend
- Responsive Bootstrap 5 layout
- Dark/Light theme system
- Interactive star ratings
- Toast notifications
- Pagination and filtering
- Modal-based CRUD interfaces

#### Documentation
- Installation guide
- System architecture overview
- Technology stack documentation

---

## Version History

| Version | Date | Status | Notable Changes |
|---------|------|--------|-----------------|
| 2.0.0 | 2026-04-13 | Current | Governance features, audits, action plans |
| 1.0.0 | 2026-03-15 | Stable | Initial release with core QA features |

---

## Roadmap

### Planned for v2.1
- [ ] User authentication and role-based access control
- [ ] Email notifications for overdue action plans
- [ ] Advanced audit trail/activity logging
- [ ] Bulk import functionality (CSV)

### Planned for v2.2
- [ ] Predictive analytics and trend forecasting
- [ ] Mobile application
- [ ] Multi-language support (Filipino)
- [ ] Dashboard customization per user

### Planned for v3.0
- [ ] Machine learning integration for anomaly detection
- [ ] Real-time collaboration features
- [ ] Advanced scheduling and resource allocation
- [ ] Compliance automation tools

---

## How to Use This Changelog

1. **For Developers**: When making changes, document them in the "Unreleased" section
2. **For Release**: Move "Unreleased" content to a new version section before release
3. **Format**:
   - `### Added` - New features
   - `### Changed` - Changes to existing features
   - `### Fixed` - Bug fixes
   - `### Deprecated` - Soon-to-be removed features
   - `### Removed` - Removed features
   - `### Security` - Security fixes and improvements

---

## Git Hooks Setup

To automatically update this changelog on each commit:

1. Initialize Git repository (if not done):
   ```bash
   git init
   ```

2. Install the pre-commit hook:
   ```bash
   cp .git-hooks/prepare-commit-msg .git/hooks/prepare-commit-msg
   chmod +x .git/hooks/prepare-commit-msg
   ```

3. On each commit, the hook will:
   - Parse your git commit message
   - Extract change type (feat, fix, docs, etc.)
   - Automatically update CHANGELOG.md under "Unreleased"

---

## Contributors

- **ByteBandits Team** - PLSP Quality Assurance Management System

---

## Notes

- This changelog is maintained automatically via Git hooks
- Manual updates should follow the "Keep a Changelog" format
- Dates are in `YYYY-MM-DD` format (ISO 8601)
- All changes are documented before release
- Previous versions are archived in version history

---

**Last Updated**: 2026-04-17  
**Maintained By**: ByteBandits Development Team

---

# 📖 SETUP & COMMIT GUIDE

## Quick Setup (One-Time)

```bash
# 1. Initialize git
git init
git config user.name "Your Name"
git config user.email "your@email.com"

# 2. Install hooks
chmod +x .git-hooks/*
cp .git-hooks/* .git/hooks/

# 3. Done! Changelog will auto-update on commits
```

## Commit Format (Automatic Categorization)

Use this format in your commit messages:

```
<type>(<scope>): <description>
```

**Types that auto-update changelog:**
- `feat(scope): description` → Added to "### Added"
- `fix(scope): description` → Added to "### Fixed"
- `docs: description` → Added to "### Changed"
- `refactor(scope): description` → Added to "### Changed"
- `perf(scope): description` → Added to "### Changed"
- `test: description` → Added to "### Changed"
- `sec(scope): description` → Added to "### Security"
- `chore: description` → Skipped (no changelog update)

## Common Examples

```bash
# New feature
git commit -m "feat(indicators): add batch CSV import"

# Bug fix
git commit -m "fix(survey): prevent duplicate responses"

# Documentation
git commit -m "docs: update API reference"

# Security fix
git commit -m "sec: add SQL injection prevention"

# Performance
git commit -m "perf(reports): optimize PDF export"

# Refactoring
git commit -m "refactor(api): simplify database queries"

# Tests
git commit -m "test: add integration tests for audits"
```

## Workflow

```bash
# Make changes
nano pages/indicators.php

# Stage and commit
git add pages/indicators.php
git commit -m "feat(indicators): add category filtering"

# ✅ CHANGELOG.md automatically updated!
# Check it:
cat CHANGELOG.md | head -30
```

## Before Release

```bash
# Edit manually to move Unreleased → new version
nano CHANGELOG.md

# Example: Change this:
## Unreleased
### Added
- feature 1
- feature 2

# To this:
## [2.1.0] - 2026-05-15
### Added
- feature 1
- feature 2

## Unreleased
# (empty sections for next version)

# Then commit
git commit -m "chore: release version 2.1.0"
git tag v2.1.0
```

## Useful Git Commands

```bash
git log --oneline -10          # Last 10 commits
git status                      # Current status
git diff HEAD                   # See what changed
git commit --amend -m "..."     # Fix last commit
git reset --soft HEAD~1         # Undo last commit (keep changes)
```

## Troubleshooting

**Hooks not running?**
```bash
chmod +x .git/hooks/*
cp .git-hooks/* .git/hooks/
```

**Changelog not updating?**
Check commit message format matches: `type(scope): description`

**Need to update manually?**
```bash
nano CHANGELOG.md
# Add entries under correct section
git add CHANGELOG.md
git commit -m "docs: update changelog"
```

## Reference

- **Format**: [Keep a Changelog](https://keepachangelog.com/)
- **Commits**: [Conventional Commits](https://www.conventionalcommits.org/)
- **Versioning**: [Semantic Versioning](https://semver.org/)
- **Git Hooks**: [Git Hooks Docs](https://git-scm.com/docs/githooks)
