/**
 * Urgent Cases Management JavaScript Controller
 * School Reconciliation Management System
 */

document.addEventListener('DOMContentLoaded', () => {
    loadUrgentCases();

    const statusFilter = document.getElementById('urgentStatusFilter');
    const sessionFilter = document.getElementById('urgentSessionFilter');

    if (statusFilter) statusFilter.addEventListener('change', loadUrgentCases);
    if (sessionFilter) sessionFilter.addEventListener('change', loadUrgentCases);

    bindUrgentModalForm();
});

let casesCache = [];

function loadUrgentCases() {
    const tableBody = document.getElementById('urgentCasesTableBody');
    if (!tableBody) return;

    const status = document.getElementById('urgentStatusFilter')?.value || '';
    const session = document.getElementById('urgentSessionFilter')?.value || '';

    tableBody.innerHTML = `
        <tr>
            <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                Loading urgent cases...
            </td>
        </tr>
    `;

    fetch(`api/urgent_cases.php?action=list&status=${encodeURIComponent(status)}&session=${encodeURIComponent(session)}`)
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                casesCache = res.data;
                renderUrgentTable(casesCache);
            } else {
                showToast('error', 'Error', res.message || 'Failed to load urgent cases.');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('error', 'Network Error', 'Failed to reach server.');
        });
}

function renderUrgentTable(cases) {
    const tableBody = document.getElementById('urgentCasesTableBody');
    if (!tableBody) return;

    if (!cases || cases.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 30px; color: var(--text-muted);">
                    No urgent cases found matching the criteria.
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    cases.forEach(c => {
        let prioBadge = 'badge-pending';
        if (c.priority === 'Critical') prioBadge = 'badge-issue';
        else if (c.priority === 'High') prioBadge = 'badge-refund';

        let statusBadge = 'badge-pending';
        if (c.status === 'Resolved') statusBadge = 'badge-completed';
        else if (c.status === 'In Progress') statusBadge = 'badge-no-payment';

        html += `
            <tr>
                <td>
                    <div style="font-weight: 700; color: var(--text-main); font-size: 14px;">${escapeHtml(c.school_name)}</div>
                    <div style="font-size: 12px; color: var(--text-muted);">${escapeHtml(c.school_code)} | ${escapeHtml(c.school_phone || 'No phone')}</div>
                </td>
                <td>
                    <span class="session-pill-btn active ${c.session === 'AM' ? 'am-pill' : 'pm-pill'}" style="font-size: 11px; padding: 2px 8px; pointer-events: none;">
                        ${c.session}
                    </span>
                    <span style="font-size: 12px; color: var(--text-muted); margin-left: 4px;">${escapeHtml(c.record_date)}</span>
                </td>
                <td>
                    <span class="status-badge ${prioBadge}">${escapeHtml(c.priority)}</span>
                </td>
                <td>
                    <div style="font-family: monospace; font-weight: 700; color: #dc2626;">
                        ${escapeHtml(c.issue_reference)}
                    </div>
                    ${c.description ? `<div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">${escapeHtml(c.description)}</div>` : ''}
                </td>
                <td>
                    <span class="status-badge ${statusBadge}">${escapeHtml(c.status)}</span>
                    ${c.resolved_by_user ? `<div style="font-size: 11px; color: #16a34a; margin-top: 2px;">Resolved by ${escapeHtml(c.resolved_by_user)}</div>` : ''}
                </td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="openUrgentModal(${c.id})">
                        Manage Case
                    </button>
                </td>
            </tr>
        `;
    });

    tableBody.innerHTML = html;
}

window.openUrgentModal = function(id) {
    const c = casesCache.find(x => parseInt(x.id, 10) === parseInt(id, 10));
    if (!c) return;

    document.getElementById('urgentCaseId').value = c.id;
    document.getElementById('urgentSchoolLabel').textContent = `${c.school_name} (${c.school_code})`;
    document.getElementById('urgentIssueLabel').textContent = c.issue_reference;
    document.getElementById('urgentPrioritySelect').value = c.priority;
    document.getElementById('urgentStatusSelect').value = c.status;
    document.getElementById('urgentNotesInput').value = c.description || '';

    const modal = document.getElementById('urgentModalBackdrop');
    if (modal) modal.classList.add('show');
};

function bindUrgentModalForm() {
    const form = document.getElementById('urgentCaseForm');
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const formData = new FormData(form);
            formData.append('csrf_token', getCsrfToken());

            fetch('api/urgent_cases.php?action=update_status', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showToast('success', 'Case Updated', res.message);
                    closeModalById('urgentModalBackdrop');
                    loadUrgentCases();
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
