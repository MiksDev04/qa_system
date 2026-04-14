<?php
// includes/header.php
// ByteBandits – QA Management System
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? 'QA System') ?> | PLSP QA</title>
    <script>
        (function () {
            try {
                var savedTheme = localStorage.getItem('qa_theme');
                var theme = (savedTheme === 'light' || savedTheme === 'dark') ? savedTheme : 'dark';
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap">
    <link rel="stylesheet" href="/qa_system/assets/css/main.css?v=<?= filemtime(__DIR__ . '/../assets/css/main.css') ?>">
</head>
<body>
<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class="bi bi-shield-check"></i>
        </div>
        <div class="brand-text">
            <span class="brand-name">QA System</span>
            <span class="brand-sub">ByteBandits · PLSP</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-label">OVERVIEW</div>
        <a href="/qa_system/pages/dashboard.php" class="nav-item <?= in_array($current_page, ['index', 'dashboard'], true) ? 'active' : '' ?>">
            <i class="bi bi-grid-1x2"></i>
            <span>Dashboard</span>
        </a>

        <div class="nav-label mt-3">QUALITY DATA</div>
        <a href="/qa_system/pages/indicators.php" class="nav-item <?= $current_page === 'indicators' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i>
            <span>KPI Indicators</span>
        </a>
        <a href="/qa_system/pages/records.php" class="nav-item <?= $current_page === 'records' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i>
            <span>QA Records</span>
        </a>

        <div class="nav-label mt-3">SURVEYS</div>
        <a href="/qa_system/pages/surveys.php" class="nav-item <?= $current_page === 'surveys' ? 'active' : '' ?>">
            <i class="bi bi-clipboard-data"></i>
            <span>Manage Surveys</span>
        </a>
        <a href="/qa_system/pages/responses.php" class="nav-item <?= $current_page === 'responses' ? 'active' : '' ?>">
            <i class="bi bi-chat-square-text"></i>
            <span>Responses</span>
        </a>

        <div class="nav-label mt-3">GOVERNANCE & COMPLIANCE</div>
        <a href="/qa_system/pages/standards.php" class="nav-item <?= $current_page === 'standards' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-text"></i>
            <span>Standards & Policies</span>
        </a>
        <a href="/qa_system/pages/audits.php" class="nav-item <?= $current_page === 'audits' ? 'active' : '' ?>">
            <i class="bi bi-search"></i>
            <span>Internal Audits</span>
        </a>
        <a href="/qa_system/pages/action_plans.php" class="nav-item <?= $current_page === 'action_plans' ? 'active' : '' ?>">
            <i class="bi bi-clipboard-check"></i>
            <span>Action Plans</span>
        </a>

        <div class="nav-label mt-3">REPORTING</div>
        <a href="/qa_system/pages/reports.php" class="nav-item <?= $current_page === 'reports' ? 'active' : '' ?>">
            <i class="bi bi-bar-chart-line"></i>
            <span>Reports</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <button class="theme-toggle" onclick="toggleTheme()" title="Toggle dark/light mode">
            <i class="bi bi-moon-stars" id="theme-icon"></i>
            <span id="theme-label">Dark Mode</span>
        </button>
    </div>
</div>

<!-- Mobile overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<!-- Main content wrapper -->
<div class="main-wrapper" id="mainWrapper">
    <!-- Top bar -->
    <header class="topbar">
        <button class="btn-menu" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
        <div class="topbar-title"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></div>
        <div class="topbar-right">
            <span class="badge-team">ByteBandits</span>
        </div>
    </header>

    <!-- Page content starts here -->
    <main class="page-content">
