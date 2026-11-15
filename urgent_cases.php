<?php
/**
 * Urgent Case Management
 * School Reconciliation Management System
 */

$page_title = 'Urgent Case Management';
$extra_js = ['assets/js/urgent.js'];

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_permission('access_urgent_cases');

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 800; color: var(--text-main);">Urgent Case Management</h1>
        <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px;">
            Identify, prioritize, and track critical reconciliation issues requiring escalation or urgent resolution.
        </p>
    </div>
    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <select id="urgentStatusFilter" class="form-control" style="width: auto; padding: 6px 12px; font-size: 13px;">
            <option value="">All Statuses (Open, In Progress, Resolved)</option>
            <option value="Open">Open Cases Only</option>
            <option value="In Progress">In Progress Only</option>
            <option value="Resolved">Resolved Cases Only</option>
        </select>
        <select id="urgentSessionFilter" class="form-control" style="width: auto; padding: 6px 12px; font-size: 13px;">
            <option value="">All Sessions (Morning & Evening)</option>
            <option value="AM">Morning Session</option>
            <option value="PM">Evening Session</option>
        </select>
    </div>
</div>

<div class="table-card">
    <div class="card-header">
        <h2 class="card-title">Priority Reconciliation Cases</h2>
        <span style="font-size: 12px; color: #dc2626; font-weight: 700;">Admin & Super Admin Escalation Panel</span>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 25%;">School Details</th>
                    <th style="width: 15%;">Session & Date</th>
                    <th style="width: 12%;">Priority</th>
                    <th style="width: 20%;">Issue Reference / Notes</th>
                    <th style="width: 14%;">Case Status</th>
                    <th style="width: 14%;">Actions</th>
                </tr>
            </thead>
            <tbody id="urgentCasesTableBody">
                <!-- Dynamically populated via assets/js/urgent.js -->
            </tbody>
        </table>
    </div>
</div>

<!-- ==========================================================================
     MANAGE URGENT CASE MODAL
     ========================================================================== -->
<div id="urgentModalBackdrop" class="modal-backdrop">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Manage Urgent Case</h3>
            <button class="modal-close-btn" type="button" onclick="closeModalById('urgentModalBackdrop')">&times;</button>
        </div>
        <form id="urgentCaseForm">
            <input type="hidden" id="urgentCaseId" name="id">
            <div class="modal-body">
                <div style="background: #f8fafc; padding: 12px 16px; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 16px;">
                    <div style="font-size: 12px; color: var(--text-muted);">Target School:</div>
                    <div id="urgentSchoolLabel" style="font-size: 14px; font-weight: 700; color: var(--text-main);"></div>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 6px;">Reported Issue Code:</div>
                    <div id="urgentIssueLabel" style="font-family: monospace; font-size: 13.5px; font-weight: 700; color: #dc2626;"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="urgentPrioritySelect">Priority Level</label>
                    <select id="urgentPrioritySelect" name="priority" class="form-control">
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                        <option value="Critical">Critical</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="urgentStatusSelect">Case Status</label>
                    <select id="urgentStatusSelect" name="status" class="form-control">
                        <option value="Open">Open (Pending Attention)</option>
                        <option value="In Progress">In Progress (Under Review)</option>
                        <option value="Resolved">Resolved (Completed)</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="urgentNotesInput">Resolution Remarks / Notes</label>
                    <textarea id="urgentNotesInput" name="description" class="form-control" rows="3" placeholder="Enter investigation notes or resolution summary..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="closeModalById('urgentModalBackdrop')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Urgent Case</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
