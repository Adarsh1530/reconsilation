/**
 * User Management JavaScript Controller
 * School Reconciliation Management System
 */

document.addEventListener('DOMContentLoaded', () => {
    loadUsersList();
    bindUserForms();
});

let usersCache = [];

function loadUsersList() {
    const tableBody = document.getElementById('usersTableBody');
    if (!tableBody) return;

    tableBody.innerHTML = `
        <tr>
            <td colspan="4" style="text-align: center; padding: 30px; color: var(--text-muted);">
                Loading users...
            </td>
        </tr>
    `;

    fetch('api/users.php?action=list')
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                usersCache = res.data;
                renderUsersTable(usersCache);
            } else {
                showToast('error', 'Error', res.message || 'Failed to load users.');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('error', 'Network Error', 'Could not reach server.');
        });
}

function renderUsersTable(users) {
    const tableBody = document.getElementById('usersTableBody');
    if (!tableBody) return;

    if (!users || users.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="4" style="text-align: center; padding: 30px; color: var(--text-muted);">
                    No users found.
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    users.forEach(u => {
        let badgeClass = 'badge-user';
        if (u.role_slug === 'super_admin') badgeClass = 'badge-super_admin';
        else if (u.role_slug === 'admin') badgeClass = 'badge-admin';

        html += `
            <tr>
                <td>
                    <div style="font-weight: 700; color: var(--text-main); font-size: 14px;">${escapeHtml(u.username)}</div>
                    ${u.full_name && u.full_name !== u.username ? `<div style="font-size: 12px; color: var(--text-muted);">${escapeHtml(u.full_name)}</div>` : ''}
                </td>
                <td>
                    <span class="user-role-badge ${badgeClass}">${escapeHtml(u.role_name)}</span>
                </td>
                <td>
                    <span style="font-size: 12.5px; color: var(--text-muted);">
                        ${u.last_login ? escapeHtml(u.last_login) : 'Never logged in'}
                    </span>
                </td>
                <td>
                    <div style="display: flex; gap: 8px;">
                        <button class="btn btn-sm btn-outline-secondary" onclick="viewUserModal(${u.id})">
                            View
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="editUserModal(${u.id})">
                            Edit
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteUserPrompt(${u.id}, '${escapeHtml(u.username)}')">
                            Delete
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    tableBody.innerHTML = html;
}

function bindUserForms() {
    const createForm = document.getElementById('createUserForm');
    if (createForm) {
        createForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(createForm);
            formData.append('csrf_token', getCsrfToken());

            fetch('api/users.php?action=create', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showToast('success', 'User Created', res.message);
                    createForm.reset();
                    closeModalById('createUserModalBackdrop');
                    loadUsersList();
                } else {
                    showToast('error', 'Creation Failed', res.message);
                }
            })
            .catch(err => {
                console.error(err);
                showToast('error', 'Error', 'Failed to communicate with server.');
            });
        });
    }

    const editForm = document.getElementById('editUserForm');
    if (editForm) {
        editForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(editForm);
            formData.append('csrf_token', getCsrfToken());

            fetch('api/users.php?action=edit', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showToast('success', 'User Updated', res.message);
                    closeModalById('editUserModalBackdrop');
                    loadUsersList();
                } else {
                    showToast('error', 'Update Failed', res.message);
                }
            })
            .catch(err => {
                console.error(err);
                showToast('error', 'Error', 'Failed to communicate with server.');
            });
        });
    }
}

window.openCreateUserModal = function() {
    const modal = document.getElementById('createUserModalBackdrop');
    if (modal) modal.classList.add('show');
};

window.viewUserModal = function(userId) {
    const u = usersCache.find(x => parseInt(x.id, 10) === parseInt(userId, 10));
    if (!u) return;

    confirmModal({
        title: `User Details: ${u.username}`,
        message: `
            <div style="display:flex; flex-direction:column; gap: 10px; font-size: 13.5px;">
                <div><strong>User ID:</strong> ${u.id}</div>
                <div><strong>Username:</strong> ${escapeHtml(u.username)}</div>
                <div><strong>Full Name:</strong> ${escapeHtml(u.full_name || 'N/A')}</div>
                <div><strong>Assigned Role:</strong> ${escapeHtml(u.role_name)} (${escapeHtml(u.role_slug)})</div>
                <div><strong>Account Status:</strong> ${u.is_active ? 'Active' : 'Inactive'}</div>
                <div><strong>Last Login:</strong> ${escapeHtml(u.last_login || 'Never')}</div>
                <div><strong>Created At:</strong> ${escapeHtml(u.created_at)}</div>
            </div>
        `,
        confirmText: 'Done',
        confirmClass: 'btn-primary',
        cancelText: 'Close',
        onConfirm: () => {}
    });
};

window.editUserModal = function(userId) {
    const u = usersCache.find(x => parseInt(x.id, 10) === parseInt(userId, 10));
    if (!u) return;

    document.getElementById('editUserId').value = u.id;
    document.getElementById('editUsername').value = u.username;
    document.getElementById('editFullName').value = u.full_name || '';
    document.getElementById('editRoleId').value = u.role_id;
    document.getElementById('editPassword').value = '';

    const modal = document.getElementById('editUserModalBackdrop');
    if (modal) modal.classList.add('show');
};

window.deleteUserPrompt = function(userId, username) {
    confirmModal({
        title: 'Delete User Account',
        message: `Are you sure you want to delete user account <strong>"${escapeHtml(username)}"</strong>? This action cannot be undone.`,
        confirmText: 'Delete User',
        confirmClass: 'btn-danger',
        onConfirm: () => {
            const formData = new FormData();
            formData.append('csrf_token', getCsrfToken());
            formData.append('id', userId);

            fetch('api/users.php?action=delete', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showToast('success', 'User Deleted', res.message);
                    loadUsersList();
                } else {
                    showToast('error', 'Delete Failed', res.message);
                }
            })
            .catch(err => {
                console.error(err);
                showToast('error', 'Error', 'Failed to communicate with server.');
            });
        }
    });
};

window.closeModalById = function(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('show');
};
