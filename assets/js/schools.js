/**
 * School Management JavaScript Controller
 * School Reconciliation Management System
 */

document.addEventListener('DOMContentLoaded', () => {
    loadSchoolsList();
    bindSchoolForms();
});

let schoolsCache = [];

function loadSchoolsList() {
    const tableBody = document.getElementById('schoolsTableBody');
    if (!tableBody) return;

    tableBody.innerHTML = `
        <tr>
            <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                Loading schools...
            </td>
        </tr>
    `;

    fetch('api/schools.php?action=list')
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                schoolsCache = res.data;
                renderSchoolsTable(schoolsCache);
            } else {
                showToast('error', 'Error', res.message || 'Failed to load schools.');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('error', 'Network Error', 'Failed to reach server.');
        });
}

function renderSchoolsTable(schools) {
    const tableBody = document.getElementById('schoolsTableBody');
    if (!tableBody) return;

    if (!schools || schools.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="4" style="text-align: center; padding: 30px; color: var(--text-muted);">
                    No schools registered in system yet.
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    schools.forEach((s, index) => {
        const isFirst = (index === 0);
        const isLast = (index === schools.length - 1);

        const orderControls = `
            <div style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; background: #f8fafc; padding: 4px 10px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <span style="font-weight: 800; font-size: 13px; color: #1e3a8a; min-width: 26px; text-align: center;">#${index + 1}</span>
                <button type="button" class="btn btn-sm" style="padding: 3px 8px; font-size: 12px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 4px; cursor: ${isFirst ? 'not-allowed' : 'pointer'}; opacity: ${isFirst ? '0.35' : '1'}; transition: all 0.15s ease;" ${isFirst ? 'disabled' : ''} onclick="reorderSchool(${s.id}, 'up')" title="Move Up">
                    ⬆️
                </button>
                <button type="button" class="btn btn-sm" style="padding: 3px 8px; font-size: 12px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 4px; cursor: ${isLast ? 'not-allowed' : 'pointer'}; opacity: ${isLast ? '0.35' : '1'}; transition: all 0.15s ease;" ${isLast ? 'disabled' : ''} onclick="reorderSchool(${s.id}, 'down')" title="Move Down">
                    ⬇️
                </button>
            </div>
        `;

        const erpSettingsContent = s.erp_url ? `
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <div style="font-size: 12.5px; font-family: monospace; word-break: break-all;">
                    <a href="${escapeHtml(s.erp_url)}" target="_blank" rel="noopener noreferrer" style="color: #4f46e5; text-decoration: underline; font-weight: 600;">
                        ${escapeHtml(s.erp_url)}
                    </a>
                </div>
                <div style="font-size: 12px; color: #64748b;">
                    Username: <span style="font-weight: 700; color: #1e293b;">${escapeHtml(s.erp_username || 'admin')}</span> &nbsp;|&nbsp;
                    Password: <span style="font-weight: 700; color: #1e293b;">${s.erp_password ? '••••••••' : 'None'}</span>
                </div>
            </div>
        ` : `
            <span style="color: var(--text-muted); font-size: 12.5px; font-style: italic;">No ERP Login configured</span>
        `;

        html += `
            <tr>
                <td style="text-align: center; vertical-align: middle;">
                    ${orderControls}
                </td>
                <td style="vertical-align: middle;">
                    <div style="font-weight: 700; color: var(--text-main); font-size: 14.5px;">${escapeHtml(s.name)}</div>
                </td>
                <td style="vertical-align: middle;">
                    ${erpSettingsContent}
                </td>
                <td style="text-align: right; vertical-align: middle;">
                    <div style="display: flex; gap: 8px; justify-content: flex-end;">
                        <button class="btn btn-sm btn-outline-secondary" onclick="editSchoolModal(${s.id})">
                            Edit
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deleteSchoolPrompt(${s.id}, '${escapeHtml(s.name)}')">
                            Delete
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    tableBody.innerHTML = html;
}

window.reorderSchool = function(id, direction) {
    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    formData.append('id', id);
    formData.append('direction', direction);

    fetch('api/schools.php?action=reorder', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            showToast('success', 'Order Updated', res.message);
            loadSchoolsList();
        } else {
            showToast('error', 'Error', res.message || 'Failed to reorder schools.');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('error', 'Network Error', 'Failed to update school order.');
    });
};

function bindSchoolForms() {
    const createForm = document.getElementById('createSchoolForm');
    if (createForm) {
        createForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(createForm);
            formData.append('csrf_token', getCsrfToken());

            fetch('api/schools.php?action=create', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showToast('success', 'School Added', res.message);
                    createForm.reset();
                    closeModalById('createSchoolModalBackdrop');
                    loadSchoolsList();
                } else {
                    showToast('error', 'Error', res.message);
                }
            })
            .catch(err => {
                console.error(err);
                showToast('error', 'Error', 'Failed to communicate with server.');
            });
        });
    }

    const editForm = document.getElementById('editSchoolForm');
    if (editForm) {
        editForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(editForm);
            formData.append('csrf_token', getCsrfToken());

            fetch('api/schools.php?action=edit', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showToast('success', 'School Updated', res.message);
                    closeModalById('editSchoolModalBackdrop');
                    loadSchoolsList();
                } else {
                    showToast('error', 'Error', res.message);
                }
            })
            .catch(err => {
                console.error(err);
                showToast('error', 'Error', 'Failed to communicate with server.');
            });
        });
    }
}

window.openCreateSchoolModal = function() {
    const form = document.getElementById('createSchoolForm');
    if (form) form.reset();
    const modal = document.getElementById('createSchoolModalBackdrop');
    if (modal) modal.classList.add('show');
};

window.editSchoolModal = function(id) {
    const s = schoolsCache.find(x => parseInt(x.id, 10) === parseInt(id, 10));
    if (!s) return;

    document.getElementById('editSchoolId').value = s.id;
    document.getElementById('editSchoolName').value = s.name;
    if (document.getElementById('editSchoolErpUrl')) {
        document.getElementById('editSchoolErpUrl').value = s.erp_url || '';
    }
    if (document.getElementById('editSchoolErpUsername')) {
        document.getElementById('editSchoolErpUsername').value = s.erp_username || '';
    }
    if (document.getElementById('editSchoolErpPassword')) {
        document.getElementById('editSchoolErpPassword').value = s.erp_password || '';
    }

    const modal = document.getElementById('editSchoolModalBackdrop');
    if (modal) modal.classList.add('show');
};

window.deleteSchoolPrompt = function(id, name) {
    confirmModal({
        title: '⚠️ Confirm School Deletion',
        message: `
            <div style="font-size: 14px; line-height: 1.5;">
                <p style="margin-bottom: 10px; color: var(--text-main);">
                    Are you sure you want to permanently delete <strong>"${escapeHtml(name)}"</strong>?
                </p>
                <div style="background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 8px; padding: 12px 14px; color: #991b1b; font-size: 13px;">
                    <strong>⚠️ Irreversible Action:</strong> All associated reconciliation records, payment logs, issue reports, refunds, and duplicate receipts for this school will be permanently deleted from the database.
                </div>
            </div>
        `,
        confirmText: 'Yes, Delete School',
        cancelText: 'Cancel',
        confirmClass: 'btn-danger',
        onConfirm: () => {
            const formData = new FormData();
            formData.append('csrf_token', getCsrfToken());
            formData.append('id', id);

            fetch('api/schools.php?action=delete', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showToast('success', 'School Deleted', res.message);
                    loadSchoolsList();
                } else {
                    showToast('error', 'Error', res.message);
                }
            })
            .catch(err => {
                console.error(err);
                showToast('error', 'Error', 'Failed to communicate with server.');
            });
        }
    });
};

window.deleteSchoolFromCurrentEdit = function() {
    const id = document.getElementById('editSchoolId')?.value;
    const name = document.getElementById('editSchoolName')?.value || 'this school';
    if (!id) return;
    closeModalById('editSchoolModalBackdrop');
    deleteSchoolPrompt(id, name);
};
