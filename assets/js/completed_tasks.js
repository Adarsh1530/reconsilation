/**
 * Completed Tasks & Audit Activity JavaScript
 * School Reconciliation Management System
 */

let completedTasksCache = [];
let searchDebounceTimer = null;

document.addEventListener('DOMContentLoaded', () => {
    loadCompletedTasks();
});

/**
 * Fetch and render completed tasks from API
 */
function loadCompletedTasks() {
    const session = document.getElementById('filterSession')?.value || '';
    const date = document.getElementById('filterDate')?.value || '';
    const userId = document.getElementById('filterUser')?.value || '';
    const search = document.getElementById('filterSearch')?.value.trim() || '';

    const tbody = document.getElementById('completedTasksTableBody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <div style="display: inline-block; animation: spin 1s linear infinite; margin-bottom: 8px;">⏳</div>
                    <div>Loading completed tasks log...</div>
                </td>
            </tr>
        `;
    }

    const params = new URLSearchParams({
        action: 'get_completed',
        session: session,
        date: date,
        user_id: userId,
        search: search,
        _t: Date.now()
    });

    fetch(`api/completed_tasks.php?${params.toString()}`, { cache: 'no-store' })
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data) {
                completedTasksCache = res.data.tasks || [];
                renderKPICards(res.data.summary);
                populateUserDropdown(res.data.users);
                renderTasksTable(completedTasksCache);
            } else {
                if (tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 30px; color: #dc2626;">
                                Failed to load completed tasks: ${escapeHtml(res.message || 'Unknown error')}
                            </td>
                        </tr>
                    `;
                }
            }
        })
        .catch(err => {
            console.error('Error fetching completed tasks:', err);
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 30px; color: #dc2626;">
                            Network or server communication failure.
                        </td>
                    </tr>
                `;
            }
        });
}

/**
 * Render summary statistics
 */
function renderKPICards(summary) {
    if (!summary) return;
    const totalEl = document.getElementById('statTotalCompleted');
    const todayEl = document.getElementById('statTodayCompleted');
    const issuesEl = document.getElementById('statIssuesCount');
    const resolvedEl = document.getElementById('statResolvedCount');

    if (totalEl) totalEl.textContent = summary.total || 0;
    if (todayEl) todayEl.textContent = summary.today || 0;
    if (issuesEl) issuesEl.textContent = summary.issues || 0;
    if (resolvedEl) resolvedEl.textContent = summary.resolved || 0;
}

/**
 * Populate users filter dropdown dynamically
 */
function populateUserDropdown(users) {
    const select = document.getElementById('filterUser');
    if (!select || !users) return;

    const currentVal = select.value;
    // Keep first option
    let html = '<option value="">All Users / Staff</option>';
    users.forEach(u => {
        const selected = (currentVal && currentVal == u.id) ? 'selected' : '';
        const displayName = u.full_name ? `${u.username} (${u.full_name})` : u.username;
        html += `<option value="${u.id}" ${selected}>${escapeHtml(displayName)}</option>`;
    });
    select.innerHTML = html;
}

/**
 * Render table rows
 */
function renderTasksTable(tasks) {
    const tbody = document.getElementById('completedTasksTableBody');
    const countEl = document.getElementById('tableRecordCount');

    if (countEl) {
        countEl.textContent = `Showing ${tasks.length} completed ${tasks.length === 1 ? 'task' : 'tasks'}`;
    }

    if (!tbody) return;

    if (!tasks || tasks.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <div style="font-size: 28px; margin-bottom: 8px;">📋</div>
                    <div style="font-size: 14px; font-weight: 600; color: var(--text-main);">No Completed Tasks Found</div>
                    <div style="font-size: 12.5px; color: var(--text-muted); margin-top: 4px;">
                        Try adjusting your filters or session selection above.
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    tasks.forEach(t => {
        const sessionBadge = t.session === 'PM' 
            ? '<span style="background: #f3e8ff; color: #7c3aed; border: 1px solid #d8b4fe; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-weight: 700;">🌙 Evening</span>'
            : '<span style="background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-weight: 700;">☀️ Morning</span>';

        // Format Date
        let dateFormatted = t.record_date;
        if (t.record_date && t.record_date.includes('-')) {
            const parts = t.record_date.split('-');
            if (parts.length === 3) dateFormatted = `${parts[2]}.${parts[1]}.${parts[0]}`;
        }

        // Who completed it
        const userName = t.completed_by_user || 'User';
        const fullName = t.completed_by_name || '';
        const roleName = t.completed_by_role || '';
        const userDisplay = `
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 28px; height: 28px; border-radius: 50%; background: #e0f2fe; color: #0369a1; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; flex-shrink: 0;">
                    ${escapeHtml(userName.charAt(0).toUpperCase())}
                </div>
                <div style="line-height: 1.25;">
                    <div style="font-weight: 700; color: var(--text-main); font-size: 13px;">${escapeHtml(userName)}</div>
                    <div style="font-size: 11px; color: var(--text-muted);">${escapeHtml(roleName || fullName || 'Staff')}</div>
                </div>
            </div>
        `;

        // Task Outcome Summary
        let taskSummaryHtml = '';
        if (t.payment_status === 'PAYMENT_SUCCESS') {
            taskSummaryHtml += '<span class="status-badge" style="background: #dcfce7; color: #15803d; border: 1px solid #86efac; font-size: 11.5px; padding: 2px 8px; font-weight: 600;">✓ Payment Success</span> ';
        } else if (t.payment_status === 'NO_PAYMENT') {
            taskSummaryHtml += '<span class="status-badge" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #93c5fd; font-size: 11.5px; padding: 2px 8px; font-weight: 600;">✓ No Payment</span> ';
        }

        if (t.issue_reference) {
            taskSummaryHtml += `<span class="status-badge" style="background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; font-size: 11.5px; padding: 2px 8px; font-weight: 600;">Issue: ${escapeHtml(t.issue_reference)}</span> `;
        }

        if (t.refund_reference) {
            taskSummaryHtml += `<span class="status-badge" style="background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; font-size: 11.5px; padding: 2px 8px; font-weight: 600;">Refund: ${escapeHtml(t.refund_reference)}</span> `;
        }

        if (t.duplicate_receipt_no) {
            taskSummaryHtml += `<span class="status-badge" style="background: #e0f2fe; color: #075985; border: 1px solid #7dd3fc; font-size: 11.5px; padding: 2px 8px; font-weight: 600;">Dup: ${escapeHtml(t.duplicate_receipt_no)}</span> `;
        }

        // Resolved indicator
        if (t.urgent_case_status === 'Resolved' || (t.overall_remark && t.overall_remark.startsWith('Resolved by'))) {
            const resBy = t.urgent_resolved_by_user || 'Admin';
            taskSummaryHtml += `<div style="margin-top: 4px;"><span style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; border-radius: 4px; padding: 1px 6px; font-size: 11px; font-weight: 700;">✓ Resolved by ${escapeHtml(resBy)}</span></div>`;
        }

        if (!taskSummaryHtml) {
            taskSummaryHtml = '<span style="color: var(--text-muted); font-style: italic; font-size: 12px;">Completed without issues</span>';
        }

        // Remarks
        const remarkText = t.custom_remark || (t.overall_remark !== 'Completed' && t.overall_remark !== 'Pending' ? t.overall_remark : '');
        const remarkHtml = remarkText 
            ? `<div style="font-size: 12.5px; color: var(--text-main); font-weight: 500;">${escapeHtml(remarkText)}</div>`
            : '<span style="color: var(--text-muted); font-style: italic; font-size: 12px;">None</span>';

        // Time
        const timeStr = t.completed_at || t.updated_at || '';
        let formattedTime = timeStr;
        if (timeStr && timeStr.includes(' ')) {
            const [dPart, tPart] = timeStr.split(' ');
            if (tPart) {
                const [h, m] = tPart.split(':');
                let hour = parseInt(h, 10);
                const ampm = hour >= 12 ? 'PM' : 'AM';
                hour = hour % 12 || 12;
                const padH = hour < 10 ? '0' + hour : hour;
                formattedTime = `${dPart} ${padH}:${m} ${ampm}`;
            }
        }

        html += `
            <tr>
                <td>
                    <div style="font-weight: 700; color: var(--text-main); font-size: 13.5px;">${escapeHtml(t.school_name)}</div>
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">
                        <span style="font-family: monospace; background: #f1f5f9; padding: 1px 5px; border-radius: 3px;">${escapeHtml(t.school_code || '')}</span>
                        ${t.school_city ? ` &bull; ${escapeHtml(t.school_city)}` : ''}
                    </div>
                </td>
                <td>
                    <div style="margin-bottom: 3px;">${sessionBadge}</div>
                    <div style="font-size: 12px; font-weight: 600; color: var(--text-main);">${escapeHtml(dateFormatted)}</div>
                </td>
                <td>
                    ${userDisplay}
                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">
                        🕒 ${escapeHtml(formattedTime)}
                    </div>
                </td>
                <td>
                    ${taskSummaryHtml}
                </td>
                <td>
                    ${remarkHtml}
                </td>
                <td style="text-align: right;">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="viewTaskDetails(${t.id})" style="padding: 4px 10px; font-size: 12px;" title="View complete task audit details">
                        👁️ View Details
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

/**
 * Open task details modal
 */
function viewTaskDetails(recordId) {
    const rec = completedTasksCache.find(t => parseInt(t.id, 10) === parseInt(recordId, 10));
    if (!rec) return;

    const modalTitle = document.getElementById('taskDetailsTitle');
    const modalBody = document.getElementById('taskDetailsBody');
    const backdrop = document.getElementById('taskDetailsModalBackdrop');
    const modal = document.getElementById('taskDetailsModal');

    if (modalTitle) {
        modalTitle.textContent = `${rec.school_name} — Completed Task Details`;
    }

    let paymentBadge = '<span style="color: #64748b; font-style: italic;">None</span>';
    if (rec.payment_status === 'PAYMENT_SUCCESS') {
        paymentBadge = '<span style="color: #15803d; font-weight: 700;">✓ Payment Success</span>';
    } else if (rec.payment_status === 'NO_PAYMENT') {
        paymentBadge = '<span style="color: #1d4ed8; font-weight: 700;">✓ No Payment</span>';
    }

    const issueVal = rec.issue_reference ? `<strong style="color: #b91c1c;">${escapeHtml(rec.issue_reference)}</strong>` : '<span style="color: #94a3b8; font-style: italic;">None</span>';
    const refundVal = rec.refund_reference ? `<strong style="color: #d97706;">${escapeHtml(rec.refund_reference)}</strong>` : '<span style="color: #94a3b8; font-style: italic;">None</span>';
    
    let dupVal = '<span style="color: #94a3b8; font-style: italic;">None</span>';
    if (rec.duplicate_receipt_no) {
        dupVal = `<strong style="color: #0284c7;">${escapeHtml(rec.duplicate_receipt_no)}</strong>`;
        if (rec.duplicate_receipt_desc) {
            dupVal += ` <span style="color: #64748b; font-size: 12px;">(${escapeHtml(rec.duplicate_receipt_desc)})</span>`;
        }
    }

    const remarkVal = rec.custom_remark ? `<span style="color: #047857; font-weight: 500;">${escapeHtml(rec.custom_remark)}</span>` : '<span style="color: #94a3b8; font-style: italic;">None</span>';
    const completedUser = rec.completed_by_user || 'System';
    const completedRole = rec.completed_by_role ? ` (${rec.completed_by_role})` : '';
    const completedTime = rec.completed_at || rec.updated_at || '';

    let resolutionHtml = '';
    if (rec.urgent_case_status === 'Resolved' || (rec.overall_remark && rec.overall_remark.startsWith('Resolved by'))) {
        const resBy = rec.urgent_resolved_by_user || 'Admin';
        const resTime = rec.urgent_resolved_at ? ` on ${rec.urgent_resolved_at}` : '';
        resolutionHtml = `
            <tr style="border-bottom: 1px solid #f1f5f9; background: #f0fdf4;">
                <td style="padding: 7px 8px; font-weight: 700; color: #166534;">Admin Resolution:</td>
                <td style="padding: 7px 8px; color: #166534; font-weight: 600;">✓ Resolved by ${escapeHtml(resBy)}${escapeHtml(resTime)}</td>
            </tr>
        `;
    }

    if (modalBody) {
        modalBody.innerHTML = `
            <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <tbody>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 6px 4px; font-weight: 600; color: #475569; width: 40%;">School:</td>
                            <td style="padding: 6px 4px; font-weight: 700; color: #0f172a;">${escapeHtml(rec.school_name)} (${escapeHtml(rec.school_code || '')})</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 6px 4px; font-weight: 600; color: #475569;">Working Session:</td>
                            <td style="padding: 6px 4px; color: #0f172a;">${rec.session === 'PM' ? '🌙 Evening Session' : '☀️ Morning Session'} (${escapeHtml(rec.record_date)})</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 6px 4px; font-weight: 600; color: #475569;">Payment Status:</td>
                            <td style="padding: 6px 4px;">${paymentBadge}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 6px 4px; font-weight: 600; color: #475569;">Reconciliation Issue:</td>
                            <td style="padding: 6px 4px;">${issueVal}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 6px 4px; font-weight: 600; color: #475569;">Refund Reference:</td>
                            <td style="padding: 6px 4px;">${refundVal}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 6px 4px; font-weight: 600; color: #475569;">Duplicate Receipt:</td>
                            <td style="padding: 6px 4px;">${dupVal}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 6px 4px; font-weight: 600; color: #475569;">Custom Remark:</td>
                            <td style="padding: 6px 4px;">${remarkVal}</td>
                        </tr>
                        ${resolutionHtml}
                        <tr>
                            <td style="padding: 6px 4px; font-weight: 600; color: #475569;">Completed By:</td>
                            <td style="padding: 6px 4px; color: #0f172a;">👤 <strong>${escapeHtml(completedUser)}</strong>${escapeHtml(completedRole)}</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 4px; font-weight: 600; color: #475569;">Timestamp:</td>
                            <td style="padding: 6px 4px; color: #64748b; font-size: 12px;">🕒 ${escapeHtml(completedTime)}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 10px 12px; color: #166534; font-size: 12px;">
                ✓ This task has been verified and registered into the system audit trail.
            </div>
        `;
    }

    if (backdrop) backdrop.style.display = 'block';
    if (modal) modal.style.display = 'block';
}

function closeTaskDetailsModal() {
    const backdrop = document.getElementById('taskDetailsModalBackdrop');
    const modal = document.getElementById('taskDetailsModal');
    if (backdrop) backdrop.style.display = 'none';
    if (modal) modal.style.display = 'none';
}

function clearDateFilter() {
    const dateInput = document.getElementById('filterDate');
    if (dateInput) {
        dateInput.value = '';
        loadCompletedTasks();
    }
}

function handleSearchKeyup(e) {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
        loadCompletedTasks();
    }, 300);
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
