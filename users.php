<?php
/**
 * Super Admin - User Management
 * School Reconciliation Management System
 */

$page_title = 'User Management';
$extra_js = ['assets/js/users.js'];

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_permission('manage_users');

$pdo = getDBConnection();
$roles = $pdo->query("SELECT id, name, slug FROM roles ORDER BY id ASC")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
    <div>
        <h1 style="font-size: 22px; font-weight: 800; color: var(--text-main);">User Management</h1>
        <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 4px;">
            Create, view, edit, and delete staff accounts and assign system roles.
        </p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="openCreateUserModal()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Create New User
        </button>
    </div>
</div>

<!-- Users Table Card -->
<div class="table-card">
    <div class="card-header">
        <h2 class="card-title">Registered System Users</h2>
        <span style="font-size: 12px; color: var(--text-muted);">Passwords securely encrypted via bcrypt</span>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 30%;">Username</th>
                    <th style="width: 25%;">Assigned Role</th>
                    <th style="width: 25%;">Last Activity</th>
                    <th style="width: 20%;">Actions</th>
                </tr>
            </thead>
            <tbody id="usersTableBody">
                <!-- Dynamically populated via assets/js/users.js -->
            </tbody>
        </table>
    </div>
</div>

<!-- ==========================================================================
     CREATE USER MODAL
     Only requires Username, Password, Role (User, Admin, Super Admin)
     ========================================================================== -->
<div id="createUserModalBackdrop" class="modal-backdrop">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Create New User Account</h3>
            <button class="modal-close-btn" type="button" onclick="closeModalById('createUserModalBackdrop')">&times;</button>
        </div>
        <form id="createUserForm">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="createUsername">Username *</label>
                    <input type="text" id="createUsername" name="username" class="form-control" placeholder="e.g. staff_john" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label class="form-label" for="createPassword">Password *</label>
                    <input type="password" id="createPassword" name="password" class="form-control" placeholder="Minimum 6 characters" required autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label class="form-label" for="createRoleId">Role *</label>
                    <select id="createRoleId" name="role_id" class="form-control" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= ($r['slug'] === 'user') ? 'selected' : '' ?>>
                                <?= e($r['name']) ?> (<?= e($r['slug']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="createFullName">Full Name (Optional)</label>
                    <input type="text" id="createFullName" name="full_name" class="form-control" placeholder="e.g. John Doe">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="closeModalById('createUserModalBackdrop')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User Account</button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================================================
     EDIT USER MODAL
     ========================================================================== -->
<div id="editUserModalBackdrop" class="modal-backdrop">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Edit User Account</h3>
            <button class="modal-close-btn" type="button" onclick="closeModalById('editUserModalBackdrop')">&times;</button>
        </div>
        <form id="editUserForm">
            <input type="hidden" id="editUserId" name="id">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="editUsername">Username *</label>
                    <input type="text" id="editUsername" name="username" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="editPassword">New Password (Leave blank to keep unchanged)</label>
                    <input type="password" id="editPassword" name="password" class="form-control" placeholder="Leave empty to preserve existing password">
                </div>

                <div class="form-group">
                    <label class="form-label" for="editRoleId">Assigned Role *</label>
                    <select id="editRoleId" name="role_id" class="form-control" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="editFullName">Full Name</label>
                    <input type="text" id="editFullName" name="full_name" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" onclick="closeModalById('editUserModalBackdrop')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
