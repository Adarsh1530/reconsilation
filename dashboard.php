<?php
/**
 * Main Reconciliation Dashboard
 * School Reconciliation Management System
 */

$page_title = 'Reconciliation Dashboard';
$extra_css = ['assets/css/dashboard.css'];
$extra_js = ['assets/js/dashboard.js'];

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_login();

// Current active session defaults to current time-based session or AM
$active_session = $_SESSION['active_session'] ?? (date('H') >= 14 ? SESSION_PM : SESSION_AM);
$working_date = date('Y-m-d');
$user = current_user();

// Retrieve warning threshold and session times from database settings
$warning_threshold = (int)get_setting('issue_ref_warning_len', 10);
$am_start = get_setting('am_start_time', '07:00');
$am_end = get_setting('am_end_time', '13:00');
$pm_start = get_setting('pm_start_time', '14:00');
$pm_end = get_setting('pm_end_time', '19:00');

include __DIR__ . '/includes/header.php';
?>

<input type="hidden" id="currentSessionInput" value="<?= e($active_session) ?>">
<input type="hidden" id="warningThresholdVal" value="<?= $warning_threshold ?>">
<input type="hidden" id="canManageUrgentCases" value="<?= has_permission('access_urgent_cases') ? '1' : '0' ?>">
<input type="hidden" id="isSuperAdmin" value="<?= has_role(ROLE_SUPER_ADMIN) ? '1' : '0' ?>">
<input type="hidden" id="currentUserRole" value="<?= e($user['role_slug'] ?? '') ?>">

<!-- ==========================================================================
     1. SESSION SWITCHER & DATE SELECTION BAR
     ========================================================================== -->
<div class="session-control-bar">
    <div class="session-toggle-wrapper">
        <span class="session-toggle-label">Session:</span>
        <div class="session-pills">
            <button type="button" class="session-pill-btn session-switch-btn am-pill <?= ($active_session === 'AM') ? 'active' : '' ?>" data-session="AM">
                <span>☀️ Morning</span>
                <span class="session-time-hint"><?= e($am_start) ?>–<?= e($am_end) ?></span>
            </button>
            <button type="button" class="session-pill-btn session-switch-btn pm-pill <?= ($active_session === 'PM') ? 'active' : '' ?>" data-session="PM">
                <span>🌙 Evening</span>
                <span class="session-time-hint"><?= e($pm_start) ?>–<?= e($pm_end) ?></span>
            </button>
        </div>
    </div>

    <!-- Active Selection Highlight (Selected Session & Date Display) -->
    <div class="active-selection-highlight-badge" id="activeSelectionHighlightBadge" title="Currently Selected Session and Working Date">
        <span class="highlight-badge-session <?= ($active_session === 'PM') ? 'evening' : '' ?>" id="highlightSessionText"><?= ($active_session === 'AM') ? 'Morning Session' : 'Evening Session' ?></span>
        <span class="highlight-badge-divider">•</span>
        <span class="highlight-badge-date" id="highlightDateText"><?= date('d.m.Y', strtotime($working_date)) ?></span>
    </div>

    <div class="session-bar-right-tools">
        <div class="date-picker-group" title="Working Date">
            <input type="date" id="workingDateInput" class="date-input" value="<?= e($working_date) ?>" max="<?= date('Y-m-d') ?>" title="Working Date">
        </div>
    </div>
</div>

<!-- ==========================================================================
     SUPER ADMIN ANNOUNCEMENTS BANNER (VISIBLE TO ALL USERS AND ADMINS)
     ========================================================================== -->
<div id="superAdminAnnouncementsBanner" class="announcements-banner-container" style="display: none; margin-bottom: 24px;"></div>

<!-- ==========================================================================
     2. THREE MAIN OVERVIEW CARDS (BLUE, ORANGE, GREEN)
     ========================================================================== -->
<div class="overview-cards-grid">
    <!-- Card 1: Total Schools – Blue -->
    <div class="overview-card card-total-schools">
        <div class="card-top">
            <span class="card-label">Total Schools</span>
            <div class="card-icon-badge">🏫</div>
        </div>
        <div class="card-bottom">
            <div class="card-value" id="cardTotalSchools">0</div>
            <div class="card-session-subtext">Active Schools in System</div>
        </div>
    </div>

    <!-- Card 2: Pending – Orange -->
    <div class="overview-card card-pending">
        <div class="card-top">
            <span class="card-label">Pending</span>
            <div class="card-icon-badge">⏳</div>
        </div>
        <div class="card-bottom">
            <div class="card-value" id="cardPending">0</div>
            <div class="card-session-subtext"><?= e($active_session) ?> Session Pending</div>
        </div>
    </div>

    <!-- Card 3: Completed – Green -->
    <div class="overview-card card-completed">
        <div class="card-top">
            <span class="card-label">Completed</span>
            <div class="card-icon-badge">✓</div>
        </div>
        <div class="card-bottom">
            <div class="card-value" id="cardCompleted">0</div>
            <div class="card-session-subtext"><?= e($active_session) ?> Session Completed</div>
        </div>
    </div>
</div>

<!-- ==========================================================================
     URGENT ESCALATIONS (>10) & RESOLVED NOTIFICATIONS BANNER (FOR ALL USERS)
     ========================================================================== -->
<div id="urgentAlertsContainer" style="margin-bottom: 20px;"></div>

<!-- ==========================================================================
     3. ACTIVE SCHOOL RECONCILIATION WORKSPACE (DRAWER / PANEL)
     ========================================================================== -->
<div id="activeWorkspaceCard" class="workspace-card hidden">
    <div class="workspace-header">
        <div class="active-school-identity">
            <span class="active-school-code" id="wsSchoolCode">SCH-000</span>
            <h3 class="active-school-name" id="wsSchoolName">Select School</h3>
            <a href="#" target="_blank" id="wsErpLoginBtn" class="btn-erp-login" style="display: none;" title="Open Online Payment Portal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                    <polyline points="15 3 21 3 21 9"></polyline>
                    <line x1="10" y1="14" x2="21" y2="3"></line>
                </svg>
                <span>ERP Login</span>
            </a>
            <span class="active-school-session" id="wsSchoolSession">Morning Session</span>
            <span id="wsUrgentBadge" class="status-badge badge-urgent-cases" style="display: none;">Urgent Cases</span>
        </div>
        <div style="display: flex; align-items: center; gap: 8px;">
            <button type="button" class="btn btn-sm btn-success" id="btnWsMarkCompleted" title="Mark reconciliation as completed">
                ✓ Mark Completed
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCloseWorkspace">
                Close Workspace
            </button>
        </div>
    </div>

    <div class="workspace-body">
        <div class="workspace-controls-grid">
            <!-- Section A: School Payment Options (No Payment / Payment Success) -->
            <div class="control-panel" id="panelPayment">
                <div class="panel-heading">
                    <span>1. School Payment Options</span>
                    <span style="font-size: 11px; color: var(--text-muted); font-weight: normal;">Optional</span>
                </div>
                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 4px;">
                    Not required if entering Reconciliation or Refund:
                </div>
                <div class="payment-buttons-pair">
                    <!-- A. No Payment (Blue button) -->
                    <button type="button" class="btn btn-no-payment" id="btnNoPayment" title="Mark as No Payment">
                        No Payment
                    </button>
                    <!-- B. Payment Success (Green button) -->
                    <button type="button" class="btn btn-payment-success" id="btnPaymentSuccess" title="Mark as Payment Success">
                        Payment Success
                    </button>
                </div>
            </div>

            <!-- Section B: Reconciliation Controls (Red button + alphanumeric input) -->
            <div class="control-panel" id="panelReconciliation">
                <div class="panel-heading">
                    <span>2. Reconciliation Controls</span>
                    <span style="font-size: 11px; color: #dc2626; font-weight: 600;">Issue Tracker</span>
                </div>
                <button type="button" class="btn btn-recon-issue" id="btnSaveIssue">
                    ⚠️ Reconciliation Issue
                </button>
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="text" id="wsIssueInput" class="form-control" placeholder="Enter issue reference (e.g. REF102, 123)" autocomplete="off">
                    <div id="wsIssueWarning" class="input-warning-msg"></div>
                </div>
                <?php if (has_permission('access_urgent_cases')): ?>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 6px;">
                    <label style="font-size: 12px; color: #b91c1c; font-weight: 600; display: flex; align-items: center; gap: 6px; cursor: pointer;">
                        <input type="checkbox" id="wsUrgentCheck">
                        <span>Flag as Urgent Case</span>
                    </label>
                    <select id="wsUrgentPriority" style="font-size: 11px; padding: 2px 6px; border-radius: 4px; border: 1px solid #fecaca; background: #fff5f5; color: #b91c1c;">
                        <option value="High">High Priority</option>
                        <option value="Critical">Critical Priority</option>
                        <option value="Medium">Medium Priority</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <!-- Section C: Refund Controls (Light-orange button + alphanumeric input) -->
            <div class="control-panel" id="panelRefund">
                <div class="panel-heading">
                    <span>3. Refund Controls</span>
                    <span style="font-size: 11px; color: #ea580c; font-weight: 600;">Refund Processing</span>
                </div>
                <button type="button" class="btn btn-refund" id="btnSaveRefund">
                    🔄 Refund Process
                </button>
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="text" id="wsRefundInput" class="form-control" placeholder="Enter refund details or code" autocomplete="off">
                </div>
            </div>

            <!-- Section D: Duplicate Receipt (Number entry box + Description entry box) -->
            <div class="control-panel" id="panelDuplicateReceipt">
                <div class="panel-heading">
                    <span>4. Duplicate Receipt</span>
                    <span style="font-size: 11px; color: #0284c7; font-weight: 600;">Duplicate Tracker</span>
                </div>
                <button type="button" class="btn btn-dup-receipt" id="btnSaveDupReceipt">
                    📄 Save Duplicate Receipt
                </button>
                <div class="form-group" style="margin-bottom: 8px;">
                    <input type="text" id="wsDupReceiptNo" class="form-control" placeholder="Enter receipt number (e.g. REC-1002)" autocomplete="off">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="text" id="wsDupReceiptDesc" class="form-control" placeholder="Enter receipt description or reason" autocomplete="off">
                </div>
            </div>

            <!-- Section E: Remarks Section (Right side of Duplicate Receipt) -->
            <div class="control-panel" id="panelRemarks">
                <div class="panel-heading">
                    <span>5. Remarks Section</span>
                    <span style="font-size: 11px; color: #6366f1; font-weight: 600;">Custom Notes</span>
                </div>
                <button type="button" class="btn btn-remark" id="btnSaveRemark">
                    💬 Save Remark
                </button>
                <div class="form-group" style="margin-bottom: 0;">
                    <input type="text" id="wsRemarkInput" class="form-control" placeholder="Enter custom remark or notes" maxlength="255" autocomplete="off">
                </div>
            </div>
        </div>

        <div class="workspace-footer">
            <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                <button type="button" class="btn btn-payment-success" id="btnFooterMarkCompleted" style="font-weight: 700; padding: 8px 20px; font-size: 13px;">
                    ✓ Mark Reconciliation as Completed
                </button>
                <div style="font-size: 12px; color: var(--text-muted);">
                    Actions save immediately to the database and register into the audit trail.
                </div>
            </div>
            <div>
                <?php if (has_role(ROLE_SUPER_ADMIN) || has_permission('undo_actions')): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnResetRecord" style="display:none; color: #dc2626; border-color: #fecaca;">
                    ↺ Reset to Pending
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================================================
     5. RECONCILIATION MAIN TABLE
     Columns: School Name | Reconciliation | Refund | Remark
     ========================================================================== -->
<div class="table-card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Reconciliation Overview Table</h2>
            <p class="card-subtitle">Showing all schools created by Super Admin for the selected session.</p>
        </div>
        <div class="table-header-tools">
            <input type="text" id="tableSearchInput" class="table-search-input" placeholder="🔍 Search school name or code...">
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 28%;">School Name</th>
                    <th style="width: 18%;">Reconciliation</th>
                    <th style="width: 16%;">Refund</th>
                    <th style="width: 38%;">Remark</th>
                </tr>
            </thead>
            <tbody id="reconciliationTableBody">
                <!-- Dynamically populated via assets/js/dashboard.js -->
            </tbody>
        </table>
    </div>
</div>

<?php if (has_role(ROLE_SUPER_ADMIN)): ?>
<!-- ==========================================================================
     SUPER ADMIN NOTIFICATIONS MANAGEMENT MODAL
     ========================================================================== -->
<div class="modal-backdrop" id="notificationsModalBackdrop" style="display:none;">
    <div class="modal-card" style="max-width: 680px; width: 95%;">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" style="display: flex; align-items: center; gap: 8px;">
                    <span>📣</span>
                    <span>Super Admin Notification Module</span>
                </h3>
                <p class="modal-subtitle">Publish important alerts, information, and updates prominently on all user and admin dashboards.</p>
            </div>
            <button type="button" class="modal-close-btn" id="btnCloseNotificationModal">&times;</button>
        </div>

        <div class="modal-body" style="padding: 20px;">
            <!-- New Notification Form -->
            <form id="notificationPublishForm" style="margin-bottom: 24px; background: #f8fafc; padding: 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                <h4 style="font-size: 13px; font-weight: 700; color: #1e3a8a; margin-bottom: 12px; text-transform: uppercase;">Publish New Announcement</h4>
                <div class="form-group">
                    <label class="form-label" for="notifTitle">Title / Subject</label>
                    <input type="text" id="notifTitle" class="form-control" placeholder="e.g. System Maintenance Notice / Urgent Instructions" required maxlength="150">
                </div>
                <div class="form-group">
                    <label class="form-label" for="notifMessage">Announcement Message</label>
                    <textarea id="notifMessage" class="form-control" rows="3" placeholder="Enter message or important information for staff and admins..." required></textarea>
                </div>
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <label class="form-label" for="notifType" style="margin-bottom: 0;">Type:</label>
                        <select id="notifType" class="form-control" style="width: auto; padding: 6px 12px; font-size: 13px;">
                            <option value="info">ℹ️ General Info (Blue)</option>
                            <option value="warning">⚠️ Important Warning (Amber)</option>
                            <option value="urgent">🚨 Urgent Priority (Red)</option>
                            <option value="success">✅ Notice / Success (Green)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnPublishNotif">
                        <span>Publish to All Dashboards</span>
                    </button>
                </div>
            </form>

            <!-- Published Announcements List -->
            <div>
                <h4 style="font-size: 13px; font-weight: 700; color: var(--text-main); margin-bottom: 10px;">Existing Announcements</h4>
                <div id="notificationsManagerList" style="max-height: 280px; overflow-y: auto;">
                    <p style="text-align: center; color: var(--text-muted); font-size: 13px; padding: 20px;">Loading announcements...</p>
                </div>
            </div>
        </div>

        <div class="modal-footer" style="padding: 12px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnDoneNotificationModal">Close</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
