<?php
/**
 * Super Admin - Privilege Management
 * School Reconciliation Management System
 */

$page_title = 'Privilege Matrix';
$extra_js = ['assets/js/privileges.js'];

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_role(ROLE_SUPER_ADMIN);

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 800; color: var(--text-main);">Role-Based Privilege Matrix</h1>
        <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px;">
            Configure granular permissions for User, Admin, and Super Admin roles. All toggles are enforced by backend security checks.
        </p>
    </div>
</div>

<div class="table-card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Permissions Configuration Matrix</h2>
            <p class="card-subtitle">Changes take effect immediately across all sessions.</p>
        </div>
        <div style="font-size: 12px; color: #16a34a; font-weight: 600;">
            ✓ Real-time Sync Active
        </div>
    </div>

    <div class="table-responsive" id="privilegeMatrixContainer">
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            Loading privilege matrix...
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
