/**
 * Undo / Redo & Audit History Controller
 * School Reconciliation Management System
 */

document.addEventListener('DOMContentLoaded', () => {
    loadHistoryList();

    const sessionFilter = document.getElementById('historySessionFilter');
    if (sessionFilter) {
        sessionFilter.addEventListener('change', loadHistoryList);
    }
});

let historyCache = [];

function loadHistoryList() {
    const tableBody = document.getElementById('historyTableBody');
    if (!tableBody) return;

    const session = document.getElementById('historySessionFilter')?.value || '';

    tableBody.innerHTML = `
        <tr>
            <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                Loading reconciliation action history...
            </td>
        </tr>
    `;

    fetch(`api/undo_redo.php?action=list&session=${encodeURIComponent(session)}`)
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                historyCache = res.data;
                renderHistoryTable(historyCache);
            } else {
                showToast('error', 'Error', res.message || 'Failed to load action history.');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('error', 'Network Error', 'Failed to reach server.');
        });
}

function renderHistoryTable(items) {
    const tableBody = document.getElementById('historyTableBody');
    if (!tableBody) return;

    if (!items || items.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 30px; color: var(--text-muted);">
                    No reconciliation action logs recorded yet.
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    items.forEach(h => {
        let actionBadge = 'badge-pending';
        if (h.action_type === 'PAYMENT_SUCCESS') actionBadge = 'badge-payment-success';
        else if (h.action_type === 'NO_PAYMENT') actionBadge = 'badge-no-payment';
        else if (h.action_type === 'ISSUE') actionBadge = 'badge-issue';
        else if (h.action_type === 'REFUND') actionBadge = 'badge-refund';
        else if (h.action_type === 'RESET') actionBadge = 'badge-issue';

        const prev = JSON.parse(h.previous_state || '{}');
        const next = JSON.parse(h.new_state || '{}');

        let undoBtn = '';
        if (h.can_undo == 1 && h.is_undone == 0) {
            undoBtn = `
                <button class="btn btn-sm btn-outline-secondary" style="color: #dc2626; border-color: #fecaca;" onclick="promptUndo(${h.id}, '${escapeHtml(h.school_name)}')">
                    ↺ Undo
                </button>
            `;
        } else if (h.can_redo == 1 && h.is_undone == 1) {
            undoBtn = `
                <button class="btn btn-sm btn-outline-secondary" style="color: #2563eb; border-color: #bfdbfe;" onclick="promptRedo(${h.id}, '${escapeHtml(h.school_name)}')">
                    ↻ Redo
                </button>
            `;
        } else {
            undoBtn = `<span style="font-size: 11px; color: #94a3b8;">Locked</span>`;
        }

        let stateStatus = h.is_undone == 1 ? '<span style="color: #dc2626; font-size: 11px; font-weight:700;">(Undone)</span>' : '<span style="color: #10b981; font-size: 11px; font-weight:700;">(Active)</span>';

        html += `
            <tr>
                <td>
                    <div style="font-weight: 700; color: var(--text-main); font-size: 13.5px;">${escapeHtml(h.school_name)}</div>
                    <div style="font-size: 11.5px; color: var(--text-muted); font-family: monospace;">${escapeHtml(h.school_code)}</div>
                </td>
                <td>
                    <span class="session-pill-btn active ${h.session === 'AM' ? 'am-pill' : 'pm-pill'}" style="font-size: 11px; padding: 2px 8px; pointer-events: none;">
                        ${h.session}
                    </span>
                    <span style="font-size: 12px; color: var(--text-muted); margin-left: 4px;">${escapeHtml(h.record_date)}</span>
                </td>
                <td>
                    <span class="status-badge ${actionBadge}">${escapeHtml(h.action_type)}</span>
                    ${stateStatus}
                </td>
                <td>
                    <div style="font-size: 12px; line-height: 1.4;">
                        <div><span style="color: #94a3b8;">Before:</span> <strong>${escapeHtml(prev.overall_remark || 'None')}</strong></div>
                        <div><span style="color: #2563eb;">After:</span> <strong>${escapeHtml(next.overall_remark || 'None')}</strong></div>
                    </div>
                </td>
                <td>
                    <div style="font-size: 12.5px; font-weight: 600;">${escapeHtml(h.username)}</div>
                    <div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(h.created_at)}</div>
                </td>
                <td>
                    <div style="display: flex; gap: 6px; align-items: center;">
                        <button class="btn btn-sm btn-outline-secondary" onclick="viewHistoryState(${h.id})">
                            Inspect
                        </button>
                        ${undoBtn}
                    </div>
                </td>
            </tr>
        `;
    });

    tableBody.innerHTML = html;
}

window.viewHistoryState = function(id) {
    const item = historyCache.find(x => parseInt(x.id, 10) === parseInt(id, 10));
    if (!item) return;

    const prev = JSON.parse(item.previous_state || '{}');
    const next = JSON.parse(item.new_state || '{}');

    confirmModal({
        title: `Audit Inspection: ${item.school_name} (${item.session})`,
        message: `
            <div style="font-size: 13px;">
                <p style="margin-bottom: 12px;"><strong>Action:</strong> ${escapeHtml(item.action_type)} by <strong>${escapeHtml(item.username)}</strong> on ${escapeHtml(item.created_at)}</p>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <div>
                        <h4 style="font-size: 12px; text-transform: uppercase; color: #dc2626; margin-bottom: 6px;">Previous State</h4>
                        <div>Payment: ${escapeHtml(prev.payment_status || 'NONE')}</div>
                        <div>Recon Status: ${escapeHtml(prev.reconciliation_status || 'NONE')}</div>
                        <div>Issue Ref: ${escapeHtml(prev.issue_reference || 'None')}</div>
                        <div>Refund: ${escapeHtml(prev.refund_reference || 'None')}</div>
                        <div>Overall: ${escapeHtml(prev.overall_remark || 'Pending')}</div>
                    </div>
                    <div>
                        <h4 style="font-size: 12px; text-transform: uppercase; color: #16a34a; margin-bottom: 6px;">New State</h4>
                        <div>Payment: ${escapeHtml(next.payment_status || 'NONE')}</div>
                        <div>Recon Status: ${escapeHtml(next.reconciliation_status || 'NONE')}</div>
                        <div>Issue Ref: ${escapeHtml(next.issue_reference || 'None')}</div>
                        <div>Refund: ${escapeHtml(next.refund_reference || 'None')}</div>
                        <div>Overall: ${escapeHtml(next.overall_remark || 'Pending')}</div>
                    </div>
                </div>
            </div>
        `,
        confirmText: 'Done',
        confirmClass: 'btn-primary',
        cancelText: 'Close',
        onConfirm: () => {}
    });
};

window.promptUndo = function(id, schoolName) {
    confirmModal({
        title: 'Confirm Undo Action',
        message: `Are you sure you want to revert the last action on <strong>"${escapeHtml(schoolName)}"</strong>? The previous record state will be immediately restored in the live database.`,
        confirmText: 'Confirm Undo',
        confirmClass: 'btn-danger',
        onConfirm: () => {
            const formData = new FormData();
            formData.append('csrf_token', getCsrfToken());
            formData.append('history_id', id);

            fetch('api/undo_redo.php?action=undo', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showToast('success', 'Undo Successful', res.message);
                    loadHistoryList();
                } else {
                    showToast('error', 'Undo Failed', res.message);
                }
            })
            .catch(err => {
                console.error(err);
                showToast('error', 'Error', 'Failed to communicate with server.');
            });
        }
    });
};

window.promptRedo = function(id, schoolName) {
    confirmModal({
        title: 'Confirm Redo Action',
        message: `Are you sure you want to re-apply the previously undone action on <strong>"${escapeHtml(schoolName)}"</strong>?`,
        confirmText: 'Confirm Redo',
        confirmClass: 'btn-primary',
        onConfirm: () => {
            const formData = new FormData();
            formData.append('csrf_token', getCsrfToken());
            formData.append('history_id', id);

            fetch('api/undo_redo.php?action=redo', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(res => {
                if (res.success) {
                    showToast('success', 'Redo Successful', res.message);
                    loadHistoryList();
                } else {
                    showToast('error', 'Redo Failed', res.message);
                }
            })
            .catch(err => {
                console.error(err);
                showToast('error', 'Error', 'Failed to communicate with server.');
            });
        }
    });
};
