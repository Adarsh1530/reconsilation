<?php
/**
 * Super Admin - Announcements Management Page
 * School Reconciliation Management System
 */

$page_title = 'Announcements';

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

require_role(ROLE_SUPER_ADMIN);
$user = current_user();

include __DIR__ . '/includes/header.php';
?>

<!-- ==========================================================================
     PAGE HEADER & BREADCRUMB
     ========================================================================== -->
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 10px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M11 5L6 9H2v6h4l5 4V5z"></path>
                <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>
            </svg>
            Announcements
        </h1>
        <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px;">
            Publish important alerts, information, and updates prominently on all user and admin dashboards.
        </p>
    </div>

    <div>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadSuperAdminNotificationsList()" style="display: inline-flex; align-items: center; gap: 6px;">
            <span>🔄</span> Refresh Announcements
        </button>
    </div>
</div>

<!-- ==========================================================================
     1. PUBLISH NEW ANNOUNCEMENT CARD
     ========================================================================== -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h2 class="card-title" style="display: flex; align-items: center; gap: 8px;">
            <span>📣</span> Publish New Announcement
        </h2>
        <span style="font-size: 12px; color: var(--text-muted);">Displays immediately on all active user & admin dashboards</span>
    </div>

    <div class="card-body">
        <form id="notificationPublishForm" style="background: #f8fafc; padding: 20px; border-radius: var(--radius-md); border: 1.5px solid var(--border-color);">
            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" for="notifTitle" style="font-weight: 700; font-size: 13px; color: var(--text-main); margin-bottom: 6px;">
                    Title / Subject <span style="color: #dc2626;">*</span>
                </label>
                <input type="text" id="notifTitle" class="form-control" placeholder="e.g. System Maintenance Notice / Urgent Instructions" required maxlength="150" style="font-size: 13.5px;">
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label class="form-label" for="notifMessage" style="font-weight: 700; font-size: 13px; color: var(--text-main); margin-bottom: 6px;">
                    Announcement Message <span style="color: #dc2626;">*</span>
                </label>
                <textarea id="notifMessage" class="form-control" rows="4" placeholder="Enter message or important information for staff and admins..." required style="font-size: 13.5px;"></textarea>
            </div>

            <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; padding-top: 8px; border-top: 1px solid var(--border-color);">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <label class="form-label" for="notifType" style="margin-bottom: 0; font-weight: 700; font-size: 13px;">Notification Type:</label>
                    <select id="notifType" class="form-control" style="width: auto; min-width: 220px; padding: 6px 12px; font-size: 13px;">
                        <option value="info">ℹ️ General Info (Blue)</option>
                        <option value="warning" selected>⚠️ Important Notice (Amber)</option>
                        <option value="urgent">🚨 Urgent Priority (Red)</option>
                        <option value="success">✅ Notice / Success (Green)</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary" id="btnPublishNotif" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 700; padding: 8px 18px;">
                    <span>📢</span> Publish to All Dashboards
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================================================
     2. EXISTING ANNOUNCEMENTS FULL-PAGE CARD
     ========================================================================== -->
<div class="card">
    <div class="card-header" style="display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h2 class="card-title">Existing Announcements</h2>
            <p class="card-subtitle">Manage currently active and archived portal announcements.</p>
        </div>
    </div>

    <div class="card-body">
        <div id="notificationsManagerList" style="min-height: 120px;">
            <p style="text-align: center; color: var(--text-muted); font-size: 13px; padding: 30px;">Loading announcements...</p>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
