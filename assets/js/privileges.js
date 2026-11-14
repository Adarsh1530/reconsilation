/**
 * Privilege Matrix JavaScript Controller
 * School Reconciliation Management System
 */

document.addEventListener('DOMContentLoaded', () => {
    loadPrivilegeMatrix();
});

function loadPrivilegeMatrix() {
    const container = document.getElementById('privilegeMatrixContainer');
    if (!container) return;

    fetch('api/privileges.php?action=matrix')
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                renderPrivilegeMatrix(res.data);
            } else {
                showToast('error', 'Error', res.message || 'Failed to load privilege matrix.');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('error', 'Network Error', 'Failed to reach server.');
        });
}

function renderPrivilegeMatrix(data) {
    const container = document.getElementById('privilegeMatrixContainer');
    if (!container) return;

    const roles = data.roles;
    const permissions = data.permissions;
    const rolePerms = data.role_permissions;

    let tableHtml = `
        <table class="data-table" style="min-width: 600px;">
            <thead>
                <tr>
                    <th style="width: 45%;">Permission Capability</th>
    `;

    roles.forEach(role => {
        tableHtml += `<th style="text-align: center; width: ${55 / roles.length}%;">${escapeHtml(role.name)}</th>`;
    });

    tableHtml += `
                </tr>
            </thead>
            <tbody>
    `;

    // Group by category
    const categories = {};
    permissions.forEach(p => {
        const cat = p.category || 'General';
        if (!categories[cat]) categories[cat] = [];
        categories[cat].push(p);
    });

    for (const catName in categories) {
        tableHtml += `
            <tr style="background: #f1f5f9;">
                <td colspan="${roles.length + 1}" style="font-weight: 800; font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; padding: 10px 18px;">
                    📂 ${escapeHtml(catName)}
                </td>
            </tr>
        `;

        categories[catName].forEach(perm => {
            tableHtml += `
                <tr>
                    <td>
                        <div style="font-weight: 700; color: var(--text-main); font-size: 13.5px;">${escapeHtml(perm.name)}</div>
                        <div style="font-size: 12px; color: var(--text-muted);">${escapeHtml(perm.description || '')}</div>
                    </td>
            `;

            roles.forEach(role => {
                const isSuperAdmin = (role.id === 1);
                const hasPerm = isSuperAdmin || (rolePerms[role.id] && rolePerms[role.id].includes(perm.id));
                const disabledAttr = isSuperAdmin ? 'disabled title="Super Admin retains all permissions"' : '';

                tableHtml += `
                    <td style="text-align: center;">
                        <input type="checkbox" 
                            style="width: 18px; height: 18px; cursor: ${isSuperAdmin ? 'not-allowed' : 'pointer'}; accent-color: var(--primary);"
                            data-role-id="${role.id}" 
                            data-perm-id="${perm.id}" 
                            ${hasPerm ? 'checked' : ''} 
                            ${disabledAttr}
                            onchange="handlePermissionToggle(this, ${role.id}, ${perm.id})"
                        >
                    </td>
                `;
            });

            tableHtml += `</tr>`;
        });
    }

    tableHtml += `
            </tbody>
        </table>
    `;

    container.innerHTML = tableHtml;
}

window.handlePermissionToggle = function(checkbox, roleId, permId) {
    const isChecked = checkbox.checked;

    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('role_id', roleId);
    formData.append('permission_id', permId);
    formData.append('enable', isChecked ? 1 : 0);

    fetch('api/privileges.php?action=toggle', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            showToast('success', 'Privilege Updated', res.message);
        } else {
            showToast('error', 'Update Failed', res.message);
            // Revert checkbox state
            checkbox.checked = !isChecked;
        }
    })
    .catch(err => {
        console.error(err);
        showToast('error', 'Network Error', 'Could not save permission toggle.');
        checkbox.checked = !isChecked;
    });
};
