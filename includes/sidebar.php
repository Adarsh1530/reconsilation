<?php
/**
 * Sidebar Navigation
 * School Reconciliation Management System
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

$current_page = basename($_SERVER['PHP_SELF']);
$user = current_user();

// Get count of open urgent cases for badge
$open_urgent_count = 0;
if (has_permission('access_urgent_cases')) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM urgent_cases WHERE status != 'Resolved'");
        $open_urgent_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {}
}
?>
<aside class="app-sidebar">
    <div class="sidebar-header">
        <div class="brand-icon">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
            </svg>
        </div>
        <div>
            <div class="brand-title">School Recon</div>
            <div class="brand-subtitle">Reconciliation Portal</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-title">Core Operations</div>
        
        <!-- Reconciliation Workspace -->
        <a href="dashboard.php" class="nav-link <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span>Reconciliation</span>
        </a>

        <!-- Urgent Cases (Admin & Super Admin) -->
        <?php if (has_permission('access_urgent_cases')): ?>
        <a href="urgent_cases.php" class="nav-link <?= ($current_page === 'urgent_cases.php') ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>
            <span>Urgent Cases</span>
            <?php if ($open_urgent_count > 0): ?>
                <span class="nav-badge"><?= $open_urgent_count ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

        <!-- Super Admin & Management Sections -->
        <?php if (has_role(ROLE_SUPER_ADMIN) || has_permission('manage_schools') || has_permission('manage_users') || has_permission('undo_actions')): ?>
            <div class="nav-section-title">Administration</div>

            <?php if (has_permission('undo_actions') || has_role(ROLE_SUPER_ADMIN)): ?>
            <a href="history.php" class="nav-link <?= ($current_page === 'history.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 8 8 12 12 16"></polyline>
                    <path d="M16 12H8"></path>
                </svg>
                <span>Undo / Redo History</span>
            </a>
            <?php endif; ?>

            <?php if (has_permission('manage_schools') || has_role(ROLE_SUPER_ADMIN)): ?>
            <a href="schools.php" class="nav-link <?= ($current_page === 'schools.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 21h18"></path>
                    <path d="M5 21V7l8-4v18"></path>
                    <path d="M19 21V11l-6-4"></path>
                    <path d="M9 9h1"></path>
                    <path d="M9 13h1"></path>
                    <path d="M9 17h1"></path>
                </svg>
                <span>Manage Schools</span>
            </a>
            <?php endif; ?>

            <?php if (has_permission('manage_users') || has_role(ROLE_SUPER_ADMIN)): ?>
            <a href="users.php" class="nav-link <?= ($current_page === 'users.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span>User Management</span>
            </a>
            <?php endif; ?>

            <?php if (has_role(ROLE_SUPER_ADMIN)): ?>
            <a href="privileges.php" class="nav-link <?= ($current_page === 'privileges.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <span>Privilege Matrix</span>
            </a>

            <a href="settings.php" class="nav-link <?= ($current_page === 'settings.php') ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
                <span>System Settings</span>
            </a>
            <?php endif; ?>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-card-mini">
            <div class="user-avatar-circle">
                <?= strtoupper(substr($user['username'], 0, 2)) ?>
            </div>
            <div class="user-mini-info">
                <div class="user-mini-name"><?= e($user['username']) ?></div>
                <div class="user-mini-role"><?= e($user['role_name']) ?></div>
            </div>
        </div>
    </div>
</aside>
