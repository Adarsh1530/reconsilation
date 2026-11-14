<?php
/**
 * Super Admin - School Management
 * School Reconciliation Management System
 */

$page_title = 'Manage Schools';
$extra_js = ['assets/js/schools.js'];

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_permission('manage_schools');

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 800; color: var(--text-main);">School Management</h1>
        <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px;">
            Add, update, and manage participating schools in the reconciliation directory.
        </p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="openCreateSchoolModal()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add New School
        </button>
    </div>
</div>

<div class="table-card">
    <div class="card-header">
        <h2 class="card-title">Registered Educational Institutions</h2>
        <span style="font-size: 12px; color: var(--text-muted);">Active schools participate in AM and PM reconciliations</span>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 14%; text-align: center;">Order</th>
                    <th style="width: 36%;">School Name</th>
                    <th style="width: 35%;">ERP Login Settings</th>
                    <th style="width: 15%; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="schoolsTableBody">
                <!-- Dynamically populated via assets/js/schools.js -->
            </tbody>
        </table>
    </div>
</div>

<!-- ==========================================================================
     CREATE SCHOOL MODAL (Simplified to Name & ERP Settings)
     ========================================================================== -->
<div id="createSchoolModalBackdrop" class="modal-backdrop">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Register New School</h3>
            <button class="modal-close-btn" type="button" onclick="closeModalById('createSchoolModalBackdrop')">&times;</button>
        </div>
        <form id="createSchoolForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="createSchoolName">School Name *</label>
                    <input type="text" id="createSchoolName" name="name" class="form-control" placeholder="e.g. Heritage Valley Public School" required>
                </div>

                <div style="background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px dashed #cbd5e1; margin-top: 14px;">
                    <h4 style="font-size: 12px; font-weight: 700; color: #4338ca; text-transform: uppercase; margin-bottom: 10px;">
                        🔐 ERP Auto-Login Settings
                    </h4>
                    <div class="form-group">
                        <label class="form-label" for="createSchoolErpUrl">ERP Target URL (Fees Collection)</label>
                        <input type="url" id="createSchoolErpUrl" name="erp_url" class="form-control" placeholder="https://<subdomain>.amserp.in/index.php/core/feescollection/onlinepayment">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="createSchoolErpUser">ERP Username</label>
                            <input type="text" id="createSchoolErpUser" name="erp_username" class="form-control" placeholder="admin">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="createSchoolErpPass">ERP Password</label>
                            <input type="text" id="createSchoolErpPass" name="erp_password" class="form-control" placeholder="Password">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="closeModalById('createSchoolModalBackdrop')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save School</button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================================================
     EDIT SCHOOL MODAL (Simplified to Name & ERP Settings)
     ========================================================================== -->
<div id="editSchoolModalBackdrop" class="modal-backdrop">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Edit School Details</h3>
            <button class="modal-close-btn" type="button" onclick="closeModalById('editSchoolModalBackdrop')">&times;</button>
        </div>
        <form id="editSchoolForm">
            <input type="hidden" id="editSchoolId" name="id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="editSchoolName">School Name *</label>
                    <input type="text" id="editSchoolName" name="name" class="form-control" required>
                </div>

                <div style="background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px dashed #cbd5e1; margin-top: 14px;">
                    <h4 style="font-size: 12px; font-weight: 700; color: #4338ca; text-transform: uppercase; margin-bottom: 10px;">
                        🔐 ERP Auto-Login Settings
                    </h4>
                    <div class="form-group">
                        <label class="form-label" for="editSchoolErpUrl">ERP Target URL (Fees Collection)</label>
                        <input type="url" id="editSchoolErpUrl" name="erp_url" class="form-control">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="editSchoolErpUsername">ERP Username</label>
                            <input type="text" id="editSchoolErpUsername" name="erp_username" class="form-control">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="editSchoolErpPassword">ERP Password</label>
                            <input type="text" id="editSchoolErpPassword" name="erp_password" class="form-control">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="closeModalById('editSchoolModalBackdrop')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update School</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
