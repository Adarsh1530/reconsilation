<?php
/**
 * Completed Tasks & Audit Activity Module
 * School Reconciliation Management System
 * Accessible to ALL Roles (Super Admin, Admin, Staff/User)
 */

$page_title = 'Completed Tasks';
$extra_js = ['assets/js/completed_tasks.js'];

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

require_login();
$user = current_user();

include __DIR__ . '/includes/header.php';
?>

<!-- ==========================================================================
     PAGE HEADER & BREADCRUMB
     ========================================================================== -->
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 800; color: var(--text-main); display: flex; align-items: center; gap: 10px;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M9 11l3 3L22 4"></path>
                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
            </svg>
            Completed Tasks
        </h1>
        <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px;">
            Full audit log of who completed reconciliation tasks, resolution records, and recent updates across all schools.
        </p>
    </div>

    <!-- Quick Refresh Button -->
    <div>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadCompletedTasks()" style="display: inline-flex; align-items: center; gap: 6px;">
            <span>🔄</span> Refresh Log
        </button>
    </div>
</div>

<!-- ==========================================================================
     KPI SUMMARY CARDS
     ========================================================================== -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
    <div style="background: #ffffff; border-radius: var(--radius-lg); padding: 18px 20px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); border-left: 4px solid #10b981;">
        <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">Total Completed</div>
        <div id="statTotalCompleted" style="font-size: 28px; font-weight: 800; color: #10b981; margin-top: 4px;">0</div>
        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">All recorded completions</div>
    </div>

    <div style="background: #ffffff; border-radius: var(--radius-lg); padding: 18px 20px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); border-left: 4px solid #3b82f6;">
        <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">Today's Completions</div>
        <div id="statTodayCompleted" style="font-size: 28px; font-weight: 800; color: #2563eb; margin-top: 4px;">0</div>
        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"><?= date('d M Y') ?></div>
    </div>

    <div style="background: #ffffff; border-radius: var(--radius-lg); padding: 18px 20px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); border-left: 4px solid #f59e0b;">
        <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">Issues Handled</div>
        <div id="statIssuesCount" style="font-size: 28px; font-weight: 800; color: #d97706; margin-top: 4px;">0</div>
        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Reconciliations with issues</div>
    </div>

    <div style="background: #ffffff; border-radius: var(--radius-lg); padding: 18px 20px; border: 1px solid var(--border-color); box-shadow: var(--shadow-sm); border-left: 4px solid #8b5cf6;">
        <div style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">Resolved Cases</div>
        <div id="statResolvedCount" style="font-size: 28px; font-weight: 800; color: #7c3aed; margin-top: 4px;">0</div>
        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">Admin verified & resolved</div>
    </div>
</div>

<!-- ==========================================================================
     FILTER CONTROLS BAR
     ========================================================================== -->
<div class="session-control-bar" style="margin-bottom: 20px; flex-wrap: wrap; gap: 12px; justify-content: flex-start;">
    <div style="display: flex; align-items: center; gap: 8px;">
        <label for="filterSession" style="font-size: 13px; font-weight: 700; color: var(--text-main);">Session:</label>
        <select id="filterSession" class="form-control" style="width: auto; padding: 6px 12px; font-size: 13px;" onchange="loadCompletedTasks()">
            <option value="">All Sessions (AM & PM)</option>
            <option value="AM">Morning Session</option>
            <option value="PM">Evening Session</option>
        </select>
    </div>

    <div style="display: flex; align-items: center; gap: 8px;">
        <label for="filterDate" style="font-size: 13px; font-weight: 700; color: var(--text-main);">Date:</label>
        <input type="date" id="filterDate" class="form-control" style="width: auto; padding: 6px 12px; font-size: 13px;" onchange="loadCompletedTasks()">
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearDateFilter()" title="Show all dates">
            Clear Date
        </button>
    </div>

    <div style="display: flex; align-items: center; gap: 8px;">
        <label for="filterUser" style="font-size: 13px; font-weight: 700; color: var(--text-main);">Completed By:</label>
        <select id="filterUser" class="form-control" style="width: auto; padding: 6px 12px; font-size: 13px;" onchange="loadCompletedTasks()">
            <option value="">All Users / Staff</option>
            <!-- Populated dynamically via JS -->
        </select>
    </div>

    <div style="margin-left: auto; display: flex; align-items: center; gap: 8px; min-width: 240px;">
        <input type="text" id="filterSearch" class="form-control" placeholder="Search school, ref, notes..." style="padding: 6px 14px; font-size: 13px;" onkeyup="handleSearchKeyup(event)">
    </div>
</div>

<!-- ==========================================================================
     COMPLETED TASKS TABLE
     ========================================================================== -->
<div class="table-card">
    <div class="card-header" style="display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h2 class="card-title">Completed Reconciliation Log</h2>
            <p class="card-subtitle">Showing details of who performed the reconciliation, what was entered, and timestamps.</p>
        </div>
        <div id="tableRecordCount" style="font-size: 12.5px; font-weight: 700; color: var(--text-muted);">
            Showing 0 records
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 22%;">School Name</th>
                    <th style="width: 14%;">Session & Date</th>
                    <th style="width: 18%;">Completed By</th>
                    <th style="width: 18%;">Task Summary</th>
                    <th style="width: 14%;">Remarks / Notes</th>
                    <th style="width: 14%; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody id="completedTasksTableBody">
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <div style="display: inline-block; animation: spin 1s linear infinite; margin-bottom: 8px;">⏳</div>
                        <div>Loading completed tasks log...</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ==========================================================================
     TASK DETAILS VIEW MODAL
     ========================================================================== -->
<div id="taskDetailsModalBackdrop" class="modal-backdrop" style="display: none;" onclick="closeTaskDetailsModal()"></div>
<div id="taskDetailsModal" class="confirm-modal-box" style="display: none; max-width: 580px; width: 90%;">
    <div class="confirm-modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
        <div class="confirm-modal-title" id="taskDetailsTitle" style="font-size: 16px; font-weight: 800; color: var(--text-main);">Task Details</div>
        <button type="button" onclick="closeTaskDetailsModal()" style="background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-muted);">&times;</button>
    </div>
    <div class="confirm-modal-body" id="taskDetailsBody" style="margin-bottom: 18px;">
        <!-- Filled dynamically -->
    </div>
    <div class="confirm-modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
        <button type="button" class="btn btn-outline-secondary" onclick="closeTaskDetailsModal()">Close</button>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
