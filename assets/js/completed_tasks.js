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
                <td colspan="8" style="text-align: center; padding: 40px; color: #000000;">
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
                            <td colspan="8" style="text-align: center; padding: 30px; color: #000000;">
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
                        <td colspan="8" style="text-align: center; padding: 30px; color: #000000;">
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
                <td colspan="8" style="text-align: center; padding: 40px; color: #000000;">
                    <div style="font-size: 14px; font-weight: 700; color: #000000;">No Completed Tasks Found</div>
                    <div style="font-size: 12.5px; color: #000000; margin-top: 4px;">
                        Try adjusting your filters or session selection above.
                    </div>
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    tasks.forEach(t => {
        // 1. School Name in one line alignment, black text
        const schoolCodeStr = t.school_code ? ` (${t.school_code})` : '';
        const schoolDisplay = `${escapeHtml(t.school_name)}${escapeHtml(schoolCodeStr)}`;

        // 2. Session, Date & Time combined in one column in one line alignment, black text
        const sessionName = t.session === 'PM' ? 'Evening' : 'Morning';
        let dateFormatted = t.record_date || '';
        if (t.record_date && t.record_date.includes('-')) {
            const parts = t.record_date.split('-');
            if (parts.length === 3) dateFormatted = `${parts[2]}.${parts[1]}.${parts[0]}`;
        }

        const timeStr = t.completed_at || t.updated_at || '';
        let formattedTime = '';
        if (timeStr && timeStr.includes(' ')) {
            const [dPart, tPart] = timeStr.split(' ');
            if (tPart) {
                const [h, m] = tPart.split(':');
                let hour = parseInt(h, 10);
                const ampm = hour >= 12 ? 'PM' : 'AM';
                hour = hour % 12 || 12;
                const padH = hour < 10 ? '0' + hour : hour;
                formattedTime = `${padH}:${m} ${ampm}`;
            }
        }
        const sessionDateTimeDisplay = formattedTime 
            ? `${sessionName} - ${dateFormatted} ${formattedTime}`
            : `${sessionName} - ${dateFormatted}`;

        // 3. Completed By: ONLY the username, in one line alignment, black text
        const completedUsername = t.completed_by_user || 'None';

        // 4. Reconciliation: ONLY the number/reference entered (or None), black text
        const reconVal = (t.issue_reference && t.issue_reference.trim() !== '') ? t.issue_reference.trim() : 'None';

        // 5. Refund: ONLY the number/reference entered (or None), black text
        const refundVal = (t.refund_reference && t.refund_reference.trim() !== '') ? t.refund_reference.trim() : 'None';

        // 6. Duplicate: ONLY the number/reference entered (or None), black text
        const dupVal = (t.duplicate_receipt_no && t.duplicate_receipt_no.trim() !== '') ? t.duplicate_receipt_no.trim() : 'None';

        // 7. Remarks: manually entered remark as entered by user (or None), black text
        let rawRemark = t.custom_remark;
        if (!rawRemark || rawRemark.trim() === '') {
            if (t.overall_remark && t.overall_remark !== 'Completed' && t.overall_remark !== 'Pending') {
                rawRemark = t.overall_remark;
            }
        }
        const remarkVal = (rawRemark && rawRemark.trim() !== '') ? rawRemark.trim() : 'None';

        // 8. Action: View Details button showing ONLY eye icon (clean black SVG, no text, no emoji)
        const eyeIconSvg = `
            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
        `;

        html += `
            <tr style="border-bottom: 1px solid #e5e7eb;">
                <td style="padding: 10px 12px; color: #000000; font-size: 13px; font-weight: 600; white-space: nowrap;">
                    ${escapeHtml(schoolDisplay)}
                </td>
                <td style="padding: 10px 12px; color: #000000; font-size: 13px; white-space: nowrap;">
                    ${escapeHtml(sessionDateTimeDisplay)}
                </td>
                <td style="padding: 10px 12px; color: #000000; font-size: 13px; font-weight: 600; white-space: nowrap;">
                    ${escapeHtml(completedUsername)}
                </td>
                <td style="padding: 10px 12px; color: #000000; font-size: 13px; white-space: nowrap;">
                    ${escapeHtml(reconVal)}
                </td>
                <td style="padding: 10px 12px; color: #000000; font-size: 13px; white-space: nowrap;">
                    ${escapeHtml(refundVal)}
                </td>
                <td style="padding: 10px 12px; color: #000000; font-size: 13px; white-space: nowrap;">
                    ${escapeHtml(dupVal)}
                </td>
                <td style="padding: 10px 12px; color: #000000; font-size: 13px; white-space: nowrap; max-width: 260px; overflow: hidden; text-overflow: ellipsis;" title="${escapeHtml(remarkVal)}">
                    ${escapeHtml(remarkVal)}
                </td>
                <td style="padding: 10px 12px; text-align: center; white-space: nowrap;">
                    <button type="button" class="btn btn-sm" onclick="viewTaskDetails(${t.id})" title="View Details" style="background: transparent; border: 1px solid #000000; border-radius: 4px; padding: 4px 8px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; color: #000000;">
                        ${eyeIconSvg}
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

/**
 * Open task details modal (Popup only, single close icon button)
 */
function viewTaskDetails(recordId) {
    const rec = completedTasksCache.find(t => parseInt(t.id, 10) === parseInt(recordId, 10));
    if (!rec) return;

    const modalTitle = document.getElementById('taskDetailsTitle');
    const modalBody = document.getElementById('taskDetailsBody');
    const backdrop = document.getElementById('taskDetailsModalBackdrop');

    if (modalTitle) {
        const schoolCodeStr = rec.school_code ? ` (${rec.school_code})` : '';
        modalTitle.textContent = `${rec.school_name}${schoolCodeStr} — Task Details`;
    }

    const sessionName = rec.session === 'PM' ? 'Evening Session' : 'Morning Session';
    let dateFormatted = rec.record_date || '';
    if (rec.record_date && rec.record_date.includes('-')) {
        const parts = rec.record_date.split('-');
        if (parts.length === 3) dateFormatted = `${parts[2]}.${parts[1]}.${parts[0]}`;
    }

    const timeStr = rec.completed_at || rec.updated_at || '';
    let formattedTime = '';
    if (timeStr && timeStr.includes(' ')) {
        const [dPart, tPart] = timeStr.split(' ');
        if (tPart) {
            const [h, m] = tPart.split(':');
            let hour = parseInt(h, 10);
            const ampm = hour >= 12 ? 'PM' : 'AM';
            hour = hour % 12 || 12;
            const padH = hour < 10 ? '0' + hour : hour;
            formattedTime = `${padH}:${m} ${ampm}`;
        }
    }
    const sessionDateTime = formattedTime 
        ? `${sessionName} - ${dateFormatted} ${formattedTime}`
        : `${sessionName} - ${dateFormatted}`;

    const issueVal = (rec.issue_reference && rec.issue_reference.trim() !== '') ? escapeHtml(rec.issue_reference.trim()) : 'None';
    const refundVal = (rec.refund_reference && rec.refund_reference.trim() !== '') ? escapeHtml(rec.refund_reference.trim()) : 'None';
    const dupVal = (rec.duplicate_receipt_no && rec.duplicate_receipt_no.trim() !== '') ? escapeHtml(rec.duplicate_receipt_no.trim()) : 'None';

    let rawRemark = rec.custom_remark;
    if (!rawRemark || rawRemark.trim() === '') {
        if (rec.overall_remark && rec.overall_remark !== 'Completed' && rec.overall_remark !== 'Pending') {
            rawRemark = rec.overall_remark;
        }
    }
    const remarkVal = (rawRemark && rawRemark.trim() !== '') ? escapeHtml(rawRemark.trim()) : 'None';
    const completedUser = rec.completed_by_user || 'None';

    if (modalBody) {
        modalBody.innerHTML = `
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; color: #000000; margin-bottom: 0;">
                <tbody>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 10px 8px; font-weight: 700; width: 38%; color: #000000;">School Name:</td>
                        <td style="padding: 10px 8px; color: #000000;">${escapeHtml(rec.school_name)} ${rec.school_code ? `(${escapeHtml(rec.school_code)})` : ''}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 10px 8px; font-weight: 700; color: #000000;">Session, Date & Time:</td>
                        <td style="padding: 10px 8px; color: #000000;">${escapeHtml(sessionDateTime)}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 10px 8px; font-weight: 700; color: #000000;">Completed By:</td>
                        <td style="padding: 10px 8px; color: #000000; font-weight: 600;">${escapeHtml(completedUser)}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 10px 8px; font-weight: 700; color: #000000;">Reconciliation:</td>
                        <td style="padding: 10px 8px; color: #000000;">${issueVal}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 10px 8px; font-weight: 700; color: #000000;">Refund:</td>
                        <td style="padding: 10px 8px; color: #000000;">${refundVal}</td>
                    </tr>
                    <tr style="border-bottom: 1px solid #e5e7eb;">
                        <td style="padding: 10px 8px; font-weight: 700; color: #000000;">Duplicate:</td>
                        <td style="padding: 10px 8px; color: #000000;">${dupVal}</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px 8px; font-weight: 700; color: #000000;">Remarks:</td>
                        <td style="padding: 10px 8px; color: #000000; word-break: break-word;">${remarkVal}</td>
                    </tr>
                </tbody>
            </table>
        `;
    }

    if (backdrop) {
        backdrop.style.display = 'flex';
        void backdrop.offsetWidth; // trigger reflow
        backdrop.classList.add('show');
    }
}

/**
 * Close task details modal
 */
function closeTaskDetailsModal() {
    const backdrop = document.getElementById('taskDetailsModalBackdrop');
    if (backdrop) {
        backdrop.classList.remove('show');
        setTimeout(() => {
            if (!backdrop.classList.contains('show')) {
                backdrop.style.display = 'none';
            }
        }, 150);
    }
}

/**
 * Backdrop click handler to close modal when clicking outside dialog
 */
function handleTaskDetailsBackdropClick(e) {
    if (e.target && e.target.id === 'taskDetailsModalBackdrop') {
        closeTaskDetailsModal();
    }
}

// Close task details popup on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeTaskDetailsModal();
    }
});

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
