<?php
/**
 * Global Top Header
 * School Reconciliation Management System
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$user = current_user();
$csrf_token = generate_csrf_token();
$active_session = $_SESSION['active_session'] ?? (date('H') >= 14 ? SESSION_PM : SESSION_AM);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e($csrf_token) ?>">
    <title><?= isset($page_title) ? e($page_title) . ' | ' : '' ?><?= APP_NAME ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;600;700&display=swap" rel="stylesheet">
    
    <!-- Core CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <?php if (isset($extra_css)): ?>
        <?php foreach ($extra_css as $css_file): ?>
            <link rel="stylesheet" href="<?= e($css_file) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
<div class="app-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <main class="app-main">
        <!-- Sticky Topbar Navigation -->
        <header class="app-topbar">
            <div class="topbar-left">
                <button class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Toggle Navigation">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <div class="page-breadcrumb">
                    <span>Reconciliation</span>
                    <span>/</span>
                    <span class="current-page"><?= isset($page_title) ? e($page_title) : 'Dashboard' ?></span>
                </div>
            </div>

            <div class="topbar-right">
                <!-- Live Date and Time Display -->
                <div class="live-clock-card" title="Real-time Server Clock">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                    <span class="live-time-display" id="liveTimeDisplay">--:--:--</span>
                    <span class="live-date-display" id="liveDateDisplay">Loading...</span>
                </div>

                <!-- User Role Badge -->
                <span class="user-role-badge badge-<?= e($user['role_slug']) ?>">
                    <?= e($user['role_name']) ?>
                </span>

                <!-- Logout Link -->
                <a href="logout.php" class="btn-logout" title="Secure Sign Out">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    Logout
                </a>
            </div>
        </header>

        <div class="content-wrapper">
