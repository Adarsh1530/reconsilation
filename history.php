<?php
/**
 * Super Admin - Undo / Redo & Reconciliation Action History
 * School Reconciliation Management System
 */

$page_title = 'Undo / Redo History';
$extra_js = ['assets/js/undo_redo.js'];

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_role(ROLE_SUPER_ADMIN);

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 800; color: var(--text-main);">Reconciliation Audit & Undo/Redo</h1>
        <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px;">
            Exclusively for Super Admin. Audit staff activities, inspect previous and updated states, and restore records if mistakes occur.
        </p>
    </div>
    <div style="display: flex; align-items: center; gap: 10px;">
        <label for="historySessionFilter" style="font-size: 13px; font-weight: 600; color: var(--text-muted);">Session:</label>
        <select id="historySessionFilter" class="form-control" style="width: auto; padding: 6px 12px; font-size: 13px;">
            <option value="">All Sessions (Morning & Evening)</option>
            <option value="AM">Morning Session</option>
            <option value="PM">Evening Session</option>
        </select>
    </div>
</div>

<div class="table-card">
    <div class="card-header">
        <div>
            <h2 class="card-title">Immutable Action Trail</h2>
            <p class="card-subtitle">Every state transition preserves full history for correction.</p>
        </div>
        <div style="font-size: 12px; color: var(--text-muted);">
            Total traceability & accountability
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 22%;">School Target</th>
                    <th style="width: 14%;">Session / Date</th>
                    <th style="width: 14%;">Action Type</th>
                    <th style="width: 20%;">State Transition</th>
                    <th style="width: 16%;">Staff User</th>
                    <th style="width: 14%;">Corrective Action</th>
                </tr>
            </thead>
            <tbody id="historyTableBody">
                <!-- Dynamically populated via assets/js/undo_redo.js -->
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
