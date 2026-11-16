/**
 * Dashboard & Reconciliation Workspace Controller
 * School Reconciliation Management System
 */

document.addEventListener('DOMContentLoaded', () => {
    initDashboard();
});

let currentSession = 'AM';
let currentDate = new Date().toISOString().split('T')[0];
let activeSchoolId = null;
let isWorkflowLocked = false;
let recordsCache = [];
let warningThreshold = 10;

function getSessionDisplayName(sess) {
    return sess === 'AM' ? 'Morning Session' : 'Evening Session';
}

function initDashboard() {
    // Read session & date from DOM elements if present
    const sessionInput = document.getElementById('currentSessionInput');
    if (sessionInput) currentSession = sessionInput.value;

    const dateInput = document.getElementById('workingDateInput');
    const todayStr = new Date().toISOString().split('T')[0];
    if (dateInput) {
        dateInput.max = todayStr;
        currentDate = dateInput.value;
        if (currentDate > todayStr) {
            currentDate = todayStr;
            dateInput.value = todayStr;
        }
        updateHighlightBadge(currentSession, currentDate);
        dateInput.addEventListener('change', (e) => {
            if (e.target.value > todayStr) {
                showToast('warning', 'Future Date Not Allowed', 'Future dates cannot be selected. Setting to today.');
                e.target.value = todayStr;
                currentDate = todayStr;
            } else {
                currentDate = e.target.value;
            }
            updateHighlightBadge(currentSession, currentDate);
            loadSessionRecords();
        });
    }

    // Read warning threshold
    const warnEl = document.getElementById('warningThresholdVal');
    if (warnEl) warningThreshold = parseInt(warnEl.value, 10) || 10;

    // Notifications Module (Super Admin & All Users)
    initNotifications();

    // Session Switcher Buttons
    const sessionBtns = document.querySelectorAll('.session-switch-btn');
    sessionBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const targetSession = btn.getAttribute('data-session');
            if (targetSession === currentSession) return;

            // If a school task is active and incomplete, warn user
            if (isWorkflowLocked) {
                confirmModal({
                    title: 'Incomplete Reconciliation Task',
                    message: 'A school reconciliation task is currently in progress. Switching sessions will close the current workspace. Are you sure?',
                    confirmText: 'Switch Session',
                    confirmClass: 'btn-warning',
                    onConfirm: () => {
                        unlockWorkspace();
                        switchSession(targetSession);
                    }
                });
            } else {
                switchSession(targetSession);
            }
        });
    });

    // School Dropdown Selector
    const schoolSelect = document.getElementById('schoolSelector');
    if (schoolSelect) {
        schoolSelect.addEventListener('change', (e) => {
            const sid = parseInt(e.target.value, 10);
            if (sid) {
                selectSchool(sid);
            } else {
                closeWorkspace();
            }
        });
    }

    // Input length / count warning listeners for Reconciliation Issue input
    const issueInput = document.getElementById('wsIssueInput');
    const issueWarning = document.getElementById('wsIssueWarning');
    if (issueInput && issueWarning) {
        issueInput.addEventListener('input', (e) => {
            const val = e.target.value.trim();
            const isAbove = val.length > warningThreshold || (!isNaN(val) && val !== '' && parseFloat(val) > 10);
            if (isAbove) {
                issueWarning.classList.add('show');
                issueWarning.innerHTML = `⚠️ Value exceeds 10 (${val}). Saving will automatically escalate this school to the Admin & Super Admin panel.`;
            } else {
                issueWarning.classList.remove('show');
            }
        });
    }

    // Workspace Action Buttons
    bindWorkspaceActions();

    // Table Search Filter
    const searchInput = document.getElementById('tableSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            filterTableRows(e.target.value.toLowerCase().trim());
        });
    }

    // Initial Load
    loadSessionRecords();
}

/**
 * Switch Morning/Evening Session
 */
function switchSession(newSession) {
    currentSession = newSession;

    // Update active button classes
    document.querySelectorAll('.session-switch-btn').forEach(btn => {
        if (btn.getAttribute('data-session') === newSession) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    // Update hidden field
    const sessionInput = document.getElementById('currentSessionInput');
    if (sessionInput) sessionInput.value = newSession;

    // Update header badge
    const headerPill = document.querySelector('.topbar-right .session-pill-btn');
    if (headerPill) {
        if (newSession === 'AM') {
            headerPill.className = 'session-pill-btn active am-pill';
            headerPill.innerHTML = '☀️ Morning Session';
        } else {
            headerPill.className = 'session-pill-btn active pm-pill';
            headerPill.innerHTML = '🌙 Evening Session';
        }
    }

    // Update active highlight badge
    updateHighlightBadge(newSession, currentDate);

    showToast('info', 'Session Switched', `Loaded ${getSessionDisplayName(newSession)} records for ${currentDate}.`);
    loadSessionRecords();
}

/**
 * Update Selected Session & Date Highlight Badge
 */
function updateHighlightBadge(session, dateStr) {
    const hlSession = document.getElementById('highlightSessionText');
    const hlDate = document.getElementById('highlightDateText');
    if (hlSession) {
        hlSession.textContent = getSessionDisplayName(session);
        if (session === 'PM') {
            hlSession.classList.add('evening');
        } else {
            hlSession.classList.remove('evening');
        }
    }
    if (hlDate && dateStr) {
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            hlDate.textContent = `${parts[2]}.${parts[1]}.${parts[0]}`;
        } else {
            hlDate.textContent = dateStr;
        }
    }
}

/**
 * Fetch and render all records for current session and date
 */
function loadSessionRecords() {
    const tableBody = document.getElementById('reconciliationTableBody');
    if (tableBody) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align:center; padding: 40px; color: var(--text-muted);">
                    <div style="display:inline-block; animation: spin 1s linear infinite; margin-bottom: 8px;">⏳</div>
                    <div>Loading ${getSessionDisplayName(currentSession)} records...</div>
                </td>
            </tr>
        `;
    }

    fetch(`api/reconciliation.php?action=get_records&date=${encodeURIComponent(currentDate)}&session=${encodeURIComponent(currentSession)}&_t=${Date.now()}`, {
        cache: 'no-store'
    })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                recordsCache = res.data.records;
                renderOverviewCards(res.data.summary);
                renderUrgentAlerts(recordsCache);
                renderSchoolDropdown(recordsCache);
                renderMainTable(recordsCache);

                // If currently open school is in cache, refresh its workspace state
                if (activeSchoolId) {
                    const currentRec = recordsCache.find(r => parseInt(r.school_id, 10) === activeSchoolId);
                    if (currentRec) {
                        renderWorkspace(currentRec);
                    }
                }
            } else {
                showToast('error', 'Error', res.message || 'Failed to load records.');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('error', 'Network Error', 'Could not reach server to fetch records.');
        });
}

/**
 * Render Overview Cards (Total, Pending, Completed)
 */
function renderOverviewCards(summary) {
    const totalEl = document.getElementById('cardTotalSchools');
    const pendingEl = document.getElementById('cardPending');
    const completedEl = document.getElementById('cardCompleted');
    const subtextEls = document.querySelectorAll('.card-session-subtext');

    if (totalEl) totalEl.textContent = summary.total_schools;
    if (pendingEl) pendingEl.textContent = summary.pending_count;
    if (completedEl) completedEl.textContent = summary.completed_count;

    // Update real-time count on the Completed Tasks sidebar badge
    const completedBadge = document.getElementById('completedTasksNavBadge');
    if (completedBadge) {
        const count = parseInt(summary.completed_count || 0, 10);
        completedBadge.textContent = count;
        completedBadge.style.display = count > 0 ? 'inline-block' : 'none';
    }

    subtextEls.forEach(el => {
        el.textContent = `${getSessionDisplayName(currentSession)} (${currentDate})`;
    });
}

/**
 * Populate School Dropdown
 */
function renderSchoolDropdown(records) {
    const select = document.getElementById('schoolSelector');
    if (!select) return;

    const currentVal = select.value;
    select.innerHTML = '<option value="">-- Choose a School to Reconcile --</option>';

    records.forEach(r => {
        const opt = document.createElement('option');
        opt.value = r.school_id;
        if (r.is_completed == 1) {
            opt.textContent = `✓ [Completed] ${r.school_code} - ${r.school_name}`;
        } else {
            opt.textContent = `⏳ ${r.school_code} - ${r.school_name} [${r.overall_remark || 'Pending'}]`;
        }
        select.appendChild(opt);
    });

    if (activeSchoolId) {
        select.value = activeSchoolId;
    }
}

/**
 * Render Urgent Escalations (>10) & Resolved Notifications Banner (For All Users)
 */
function renderUrgentAlerts(records) {
    const container = document.getElementById('urgentAlertsContainer');
    if (!container) return;

    if (!records || records.length === 0) {
        container.innerHTML = '';
        return;
    }

    const canManage = document.getElementById('canManageUrgentCases')?.value === '1';
    let html = '';

    records.forEach(r => {
        if (r.reconciliation_status === 'ISSUE' && r.issue_reference) {
            const isAbove10 = (r.issue_reference.length > warningThreshold) || (!isNaN(r.issue_reference) && parseFloat(r.issue_reference) > 10);
            const isResolved = (r.urgent_case_status === 'Resolved' || (r.overall_remark && r.overall_remark.startsWith('Resolved by')));

            // Only show active unresolved alerts on the dashboard (completed/resolved tasks are in the Completed Tasks module)
            if (!isResolved && (isAbove10 || r.is_urgent == 1)) {
                // Open Escalation Alert for all users
                html += `
                    <div class="urgent-alert-card urgent-alert-warning">
                        <div class="urgent-alert-icon">⚠️</div>
                        <div class="urgent-alert-body">
                            <div class="urgent-alert-title">Urgent Reconciliation Alert: Count Exceeds 10 (Moved to Admin Panel)</div>
                            <div class="urgent-alert-desc">
                                School: <strong>${escapeHtml(r.school_name)}</strong> &nbsp;|&nbsp; 
                                Issue Count / Ref: <span class="urgent-count-pill">${escapeHtml(r.issue_reference)}</span>
                                — This issue exceeds 10 and has been automatically escalated to the <strong>Admin & Super Admin Panel</strong> for review.
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="badge-urgent-tag">Under Admin Review</span>
                            ${canManage ? `
                                <button type="button" class="btn btn-sm btn-success" onclick="resolveUrgentPrompt(${r.school_id}, '${escapeHtml(r.school_name)}', '${escapeHtml(r.issue_reference)}')">
                                    ✓ Resolve as Admin
                                </button>
                            ` : ''}
                        </div>
                    </div>
                `;
            }
        }
    });

    container.innerHTML = html;
}

/**
 * Render Reconciliation Main Table
 * Columns: School Name | Reconciliation | Refund | Remark
 */
function renderMainTable(records) {
    const tableBody = document.getElementById('reconciliationTableBody');
    if (!tableBody) return;

    if (!records || records.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="5" style="text-align: center; padding: 30px; color: var(--text-muted);">
                    No active schools found in the database.
                </td>
            </tr>
        `;
        return;
    }

    const canManageUrgent = document.getElementById('canManageUrgentCases')?.value === '1';
    const isSuperAdmin = document.getElementById('isSuperAdmin')?.value === '1';

    let html = '';
    records.forEach(rec => {
        const isCurrentActive = activeSchoolId && parseInt(rec.school_id, 10) === activeSchoolId;

        // Check if issue is above 10 or resolved
        const isAbove10 = rec.issue_reference && (rec.issue_reference.length > warningThreshold || (!isNaN(rec.issue_reference) && parseFloat(rec.issue_reference) > 10));
        const isResolved = (rec.urgent_case_status === 'Resolved' || (rec.overall_remark && rec.overall_remark.startsWith('Resolved by')));
        const isEscalatedOpen = !isResolved && (isAbove10 || rec.is_urgent == 1);

        const rowClasses = [
            isCurrentActive ? 'row-active' : '',
            isEscalatedOpen ? 'row-urgent' : ''
        ].filter(Boolean).join(' ');

        // Status badge & remark text
        const hasCustomRemark = rec.custom_remark && rec.custom_remark.trim() !== '';

        // 1. Reconciliation column display: shows only the number/reference entered in the box (or None)
        let reconDisplay = '<span style="color: #94a3b8; font-size: 12.5px; white-space: nowrap;">None</span>';
        if (rec.issue_reference && rec.issue_reference.trim() !== '') {
            reconDisplay = `<strong style="font-family: monospace; font-size: 13px; color: #dc2626; white-space: nowrap;" title="Reconciliation Issue: ${escapeHtml(rec.issue_reference)}">${escapeHtml(rec.issue_reference)}</strong>`;
        }

        // 2. Refund column display: shows only the number/reference entered in the box (or None)
        let refundDisplay = '<span style="color: #94a3b8; font-size: 12.5px; white-space: nowrap;">None</span>';
        if (rec.refund_reference && rec.refund_reference.trim() !== '') {
            refundDisplay = `<strong style="font-family: monospace; font-size: 13px; color: #ea580c; white-space: nowrap;" title="Refund Reference: ${escapeHtml(rec.refund_reference)}">${escapeHtml(rec.refund_reference)}</strong>`;
        }

        // 3. Dedicated Duplicate Receipt column display: shows only the number entered in the box (or None)
        let dupDisplay = '<span style="color: #94a3b8; font-size: 12.5px; white-space: nowrap;">None</span>';
        if (rec.duplicate_receipt_no && rec.duplicate_receipt_no.trim() !== '') {
            const desc = rec.duplicate_receipt_desc ? ` - ${escapeHtml(rec.duplicate_receipt_desc)}` : '';
            dupDisplay = `<strong style="font-family: monospace; font-size: 13px; color: #0284c7; white-space: nowrap;" title="Duplicate Receipt: ${escapeHtml(rec.duplicate_receipt_no)}${desc}">${escapeHtml(rec.duplicate_receipt_no)}</strong>`;
        }

        // 4. Remarks column display: shows only manually entered remarks (or None)
        let remarkHtml = '<span style="color: #94a3b8; font-size: 12.5px; white-space: nowrap;">None</span>';
        if (hasCustomRemark) {
            remarkHtml = `<span style="font-size: 12.5px; color: #334155; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; vertical-align: middle;" title="Remark: ${escapeHtml(rec.custom_remark)}">${escapeHtml(rec.custom_remark)}</span>`;
        }

        // Admin resolve button if escalated
        let adminResolveBtn = '';
        if (isEscalatedOpen && canManageUrgent) {
            adminResolveBtn = `
                <button type="button" class="btn btn-sm btn-success" onclick="event.stopPropagation(); resolveUrgentPrompt(${rec.school_id}, '${escapeHtml(rec.school_name)}', '${escapeHtml(rec.issue_reference || '')}')">
                    Resolve
                </button>
            `;
        }

        // Action buttons: When completed, do NOT show Select option. Show Reopen Task button to re-edit.
        // For pending tasks: show Select button only.
        let actionBtn = '';
        if (rec.is_completed == 1) {
            actionBtn = `
                <div style="display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;">
                    ${adminResolveBtn}
                    <span class="badge-status-completed" style="cursor: pointer;" onclick="event.stopPropagation(); toggleSchoolCompletion(${rec.school_id}, 0)" title="View stored data and reopen task">
                        ✓ Completed
                    </span>
                    <button type="button" class="btn btn-sm btn-reopen-task" onclick="event.stopPropagation(); toggleSchoolCompletion(${rec.school_id}, 0)" title="View stored data and reopen task to re-edit">
                        ↺ Reopen Task
                    </button>
                </div>
            `;
        } else {
            // Pending task: only show Select button (Complete button is only shown after the task is completed)
            actionBtn = `
                <div style="display: inline-flex; align-items: center; gap: 6px; white-space: nowrap;">
                    ${adminResolveBtn}
                    <button class="btn btn-sm btn-outline-secondary" onclick="handleSelectSchoolFromTable(${rec.school_id})">
                        Select
                    </button>
                </div>
            `;
        }

        // ERP Login button on the right side of school name
        let erpLoginBtn = '';
        if (rec.erp_url) {
            erpLoginBtn = `
                <button type="button" class="btn-erp-login" onclick="openErpPortal(${rec.school_id}, event)" title="Auto-login directly to Online Payment: ${escapeHtml(rec.school_name)}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                    <span>ERP Login</span>
                </button>
            `;
        }

        // Completion tracking: User – Date – Time (displayed on right side)
        let completionTrackingHtml = '';
        if (rec.is_completed == 1) {
            const who = rec.completed_by_user || rec.updated_by_user || 'User';
            let compDate = '';
            let compTime = '';
            const timeStr = rec.completed_at || rec.updated_at;
            if (timeStr) {
                const parts = timeStr.split(' ');
                compDate = parts[0];
                if (parts[1]) {
                    const timeParts = parts[1].split(':');
                    let h = parseInt(timeParts[0], 10);
                    const m = timeParts[1];
                    const ampm = h >= 12 ? 'PM' : 'AM';
                    h = h % 12 || 12;
                    const padH = h < 10 ? '0' + h : h;
                    compTime = `${padH}:${m} ${ampm}`;
                }
            } else {
                compDate = currentDate;
            }
            const formattedMeta = `${who} – ${compDate}${compTime ? ` – ${compTime}` : ''}`;
            completionTrackingHtml = `
                <div class="completion-tracking-pill" title="Completed by ${escapeHtml(rec.completed_by_name || who)} on ${escapeHtml(timeStr || compDate)}">
                    <span>👤</span>
                    <span>${escapeHtml(formattedMeta)}</span>
                </div>
            `;
        }

        html += `
            <tr class="${rowClasses}" id="school-row-${rec.school_id}">
                <td>
                    <div class="school-name-cell" style="white-space: nowrap;">
                        <div class="school-title-row" style="display: flex; align-items: center; gap: 10px; white-space: nowrap;">
                            <span class="school-title" style="white-space: nowrap;">${escapeHtml(rec.school_name)}</span>
                            ${erpLoginBtn}
                        </div>
                    </div>
                </td>
                <td>${reconDisplay}</td>
                <td>${refundDisplay}</td>
                <td>${dupDisplay}</td>
                <td>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; white-space: nowrap;">
                        <div style="display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0; white-space: nowrap;">
                            ${remarkHtml}
                        </div>
                        <div style="display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0; white-space: nowrap;">
                            ${completionTrackingHtml}
                            ${actionBtn}
                        </div>
                    </div>
                </td>
            </tr>
        `;
    });

    tableBody.innerHTML = html;
}

window.copyPasswordOnDirectClick = function(pw) {
    if (pw && navigator.clipboard) {
        navigator.clipboard.writeText(pw).then(() => {
            showToast('info', 'Password Copied', 'School ERP password copied to clipboard!');
        }).catch(() => {});
    }
};

/**
 * Open School ERP Portal to the exact Fees Collection link with auto-login
 */
window.openErpPortal = function(schoolId) {
    const rec = recordsCache.find(r => parseInt(r.school_id, 10) === parseInt(schoolId, 10));
    if (!rec || !rec.erp_url) {
        showToast('error', 'ERP Error', 'ERP destination link not configured.');
        return;
    }

    // Auto-copy password to clipboard
    if (rec.erp_password && navigator.clipboard) {
        navigator.clipboard.writeText(rec.erp_password).catch(() => {});
    }

    showToast('info', 'Opening Online Fees Collection', `Connecting to ${rec.school_name}... (Password copied)`);

    const winName = 'amserp_tab_' + schoolId;
    
    // Step 1: Open the window to the exact target URL
    const erpWin = window.open(rec.erp_url, winName);

    // Step 2: Auto-submit credentials into that window to authenticate and redirect to exact target URL
    if (rec.erp_password) {
        const formId = 'erp_auto_form_' + schoolId;
        let form = document.getElementById(formId);
        if (!form) {
            form = document.createElement('form');
            form.id = formId;
            form.method = 'POST';
            form.target = winName;
            try {
                const domain = new URL(rec.erp_url).origin;
                form.action = domain + '/index.php/user/login';
            } catch (e) {
                form.action = rec.erp_url.replace('/core/feescollection/onlinepayment', '/user/login');
            }
            form.style.display = 'none';

            const uInput = document.createElement('input');
            uInput.type = 'hidden';
            uInput.name = 'UserLogin[username]';
            uInput.value = rec.erp_username || 'admin';
            form.appendChild(uInput);

            const pInput = document.createElement('input');
            pInput.type = 'hidden';
            pInput.name = 'UserLogin[password]';
            pInput.value = rec.erp_password;
            form.appendChild(pInput);

            const sInput = document.createElement('input');
            sInput.type = 'hidden';
            sInput.name = 'yt0';
            sInput.value = 'Sign in ';
            form.appendChild(sInput);

            document.body.appendChild(form);
        }

        // Wait 800ms for initial GET to hit the server and register returnUrl in session
        setTimeout(() => {
            form.submit();
        }, 800);

        // Step 3: Ensure landing directly on the exact Fees Collection Online Payment link once authenticated
        setTimeout(() => {
            try {
                if (erpWin && !erpWin.closed) {
                    erpWin.location.href = rec.erp_url;
                } else {
                    window.open(rec.erp_url, winName);
                }
            } catch (e) {
                window.open(rec.erp_url, winName);
            }
        }, 2200);

        // Safeguard retry at 4200ms
        setTimeout(() => {
            try {
                if (erpWin && !erpWin.closed) {
                    erpWin.location.href = rec.erp_url;
                }
            } catch (e) {
                // Ignore cross-origin access errors
            }
        }, 4200);
    }
};

window.copyPasswordToClipboard = function(text, el) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            showToast('success', 'Copied', 'Password copied to clipboard!');
            if (el) {
                const orig = el.textContent;
                el.textContent = '✓ Copied!';
                setTimeout(() => { el.textContent = orig; }, 1500);
            }
        });
    }
};

/**
 * Handle selecting a school from the table "Select" button
 */
window.handleSelectSchoolFromTable = function(schoolId) {
    if (isWorkflowLocked && activeSchoolId !== schoolId) {
        showToast('warning', 'Task In Progress', 'Please complete the reconciliation for the current school before selecting another.');
        return;
    }
    const select = document.getElementById('schoolSelector');
    if (select) select.value = schoolId;
    selectSchool(schoolId);
};

/**
 * School Selection Workflow
 * Opens reconciliation workspace, manages workflow lock
 */
function selectSchool(schoolId) {
    activeSchoolId = parseInt(schoolId, 10);
    const rec = recordsCache.find(r => parseInt(r.school_id, 10) === activeSchoolId);
    if (!rec) return;

    const workspace = document.getElementById('activeWorkspaceCard');
    if (workspace) workspace.classList.remove('hidden');

    renderWorkspace(rec);

    // If task is not completed yet, engage workflow lock
    if (!rec.is_completed) {
        lockWorkspace();
    } else {
        unlockWorkspace();
    }

    // Scroll smoothly to workspace
    workspace.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/**
 * Render Workspace Controls & State
 */
function renderWorkspace(rec) {
    document.getElementById('wsSchoolCode').textContent = rec.school_code;
    document.getElementById('wsSchoolName').textContent = rec.school_name;
    document.getElementById('wsSchoolSession').textContent = `${getSessionDisplayName(currentSession)} (${currentDate})`;

    // Workspace ERP Login button
    const wsErpBtn = document.getElementById('wsErpLoginBtn');
    if (wsErpBtn) {
        if (rec.erp_url) {
            wsErpBtn.style.display = 'inline-flex';
            wsErpBtn.onclick = (e) => {
                if (e) e.preventDefault();
                openErpPortal(rec.school_id);
            };
        } else {
            wsErpBtn.style.display = 'none';
        }
    }

    // Workspace Urgent Badge highlight
    const wsUrgentBadge = document.getElementById('wsUrgentBadge');
    if (wsUrgentBadge) {
        const isResolvedUrgent = (rec.urgent_case_status === 'Resolved' || (rec.overall_remark && rec.overall_remark.startsWith('Resolved by')));
        const isUrgentActive = !isResolvedUrgent && (rec.is_urgent == 1 || (rec.issue_reference && ((rec.issue_reference.length > warningThreshold) || (!isNaN(rec.issue_reference) && parseFloat(rec.issue_reference) > 10))));
        wsUrgentBadge.style.display = isUrgentActive ? 'inline-flex' : 'none';
    }

    const noPaymentBtn = document.getElementById('btnNoPayment');
    const paymentSuccessBtn = document.getElementById('btnPaymentSuccess');
    const issueInput = document.getElementById('wsIssueInput');
    const issueBtn = document.getElementById('btnSaveIssue');
    const refundInput = document.getElementById('wsRefundInput');
    const refundBtn = document.getElementById('btnSaveRefund');
    const issueWarning = document.getElementById('wsIssueWarning');
    const urgentCheckbox = document.getElementById('wsUrgentCheck');

    const paymentPanel = document.getElementById('panelPayment');
    const reconPanel = document.getElementById('panelReconciliation');
    const refundPanel = document.getElementById('panelRefund');
    const dupPanel = document.getElementById('panelDuplicateReceipt');
    const dupNoInput = document.getElementById('wsDupReceiptNo');
    const dupDescInput = document.getElementById('wsDupReceiptDesc');
    const dupBtn = document.getElementById('btnSaveDupReceipt');

    // Reset fields first
    issueInput.value = rec.issue_reference || '';
    refundInput.value = rec.refund_reference || '';
    if (dupNoInput) dupNoInput.value = rec.duplicate_receipt_no || '';
    if (dupDescInput) dupDescInput.value = rec.duplicate_receipt_desc || '';
    const remarkInput = document.getElementById('wsRemarkInput');
    if (remarkInput) remarkInput.value = rec.custom_remark || '';
    if (urgentCheckbox) urgentCheckbox.checked = rec.is_urgent == 1;

    // Check issue warning length or count
    const issueVal = issueInput.value.trim();
    const isAbove = issueVal.length > warningThreshold || (!isNaN(issueVal) && issueVal !== '' && parseFloat(issueVal) > 10);
    if (isAbove) {
        issueWarning.classList.add('show');
        if (rec.urgent_case_status === 'Resolved' || (rec.overall_remark && rec.overall_remark.startsWith('Resolved by'))) {
            issueWarning.innerHTML = `✅ Issue (Count: ${escapeHtml(issueVal)}) was Resolved by ${escapeHtml(rec.urgent_resolved_by_user || 'Admin')}.`;
            issueWarning.style.color = '#059669';
        } else {
            issueWarning.innerHTML = `⚠️ Value exceeds 10 (${escapeHtml(issueVal)}). Saving will automatically escalate this school to the Admin & Super Admin panel.`;
            issueWarning.style.color = '#b91c1c';
        }
    } else {
        issueWarning.classList.remove('show');
    }

    // PAYMENT STATUS LOGIC
    // If "No Payment" or "Payment Success" is selected:
    // Automatically disable the other payment button AND reconciliation/refund controls!
    if (rec.payment_status === 'NO_PAYMENT') {
        noPaymentBtn.disabled = true;
        noPaymentBtn.textContent = '✓ No Payment Selected';
        paymentSuccessBtn.disabled = true;
        
        // Disable reconciliation and refund controls
        reconPanel.classList.add('disabled-panel');
        refundPanel.classList.add('disabled-panel');
        if (dupPanel) dupPanel.classList.add('disabled-panel');
        issueBtn.disabled = true;
        issueInput.disabled = true;
        refundBtn.disabled = true;
        refundInput.disabled = true;
        if (dupBtn) dupBtn.disabled = true;
        if (dupNoInput) dupNoInput.disabled = true;
        if (dupDescInput) dupDescInput.disabled = true;
    } else if (rec.payment_status === 'PAYMENT_SUCCESS') {
        paymentSuccessBtn.disabled = true;
        paymentSuccessBtn.textContent = '✓ Payment Success Selected';
        noPaymentBtn.disabled = true;

        // Disable reconciliation and refund controls
        reconPanel.classList.add('disabled-panel');
        refundPanel.classList.add('disabled-panel');
        if (dupPanel) dupPanel.classList.remove('disabled-panel');
        issueBtn.disabled = true;
        issueInput.disabled = true;
        refundBtn.disabled = true;
        refundInput.disabled = true;
        if (dupBtn) dupBtn.disabled = false;
        if (dupNoInput) dupNoInput.disabled = false;
        if (dupDescInput) dupDescInput.disabled = false;
    } else {
        // Neither payment status selected yet
        noPaymentBtn.disabled = false;
        noPaymentBtn.textContent = 'No Payment';
        paymentSuccessBtn.disabled = false;
        paymentSuccessBtn.textContent = 'Payment Success';

        reconPanel.classList.remove('disabled-panel');
        refundPanel.classList.remove('disabled-panel');
        if (dupPanel) dupPanel.classList.remove('disabled-panel');
        issueBtn.disabled = false;
        issueInput.disabled = false;
        refundBtn.disabled = false;
        refundInput.disabled = false;
        if (dupBtn) dupBtn.disabled = false;
        if (dupNoInput) dupNoInput.disabled = false;
        if (dupDescInput) dupDescInput.disabled = false;
    }

    // Update reset button & footer visibility (Admin/Super Admin only)
    const resetBtn = document.getElementById('btnResetRecord');
    const wsFooter = document.getElementById('workspaceFooter');
    if (resetBtn) {
        const canShow = (rec.is_completed == 1) ? 'inline-flex' : 'none';
        resetBtn.style.display = canShow;
        if (wsFooter) wsFooter.style.display = (rec.is_completed == 1) ? 'flex' : 'none';
    }
}

/**
 * Lock Workspace Workflow
 * Prevents accidental switching until current task is completed
 */
function lockWorkspace() {
    isWorkflowLocked = true;
    const lockBadge = document.getElementById('workflowLockStatus');
    const select = document.getElementById('schoolSelector');
    if (lockBadge) {
        lockBadge.className = 'workflow-lock-status workflow-locked';
        lockBadge.innerHTML = '🔒 In Progress (Locked)';
    }
    if (select) {
        select.disabled = true;
    }
}

/**
 * Unlock Workspace Workflow
 */
function unlockWorkspace() {
    isWorkflowLocked = false;
    const lockBadge = document.getElementById('workflowLockStatus');
    const select = document.getElementById('schoolSelector');
    if (lockBadge) {
        lockBadge.className = 'workflow-lock-status workflow-unlocked';
        lockBadge.innerHTML = '✓ Completed / Ready to Switch';
    }
    if (select) {
        select.disabled = false;
    }
}

/**
 * Close Workspace
 */
function closeWorkspace() {
    activeSchoolId = null;
    unlockWorkspace();
    const workspace = document.getElementById('activeWorkspaceCard');
    if (workspace) workspace.classList.add('hidden');
    const select = document.getElementById('schoolSelector');
    if (select) select.value = '';
}

/**
 * Bind Workspace Action Buttons (No Payment, Payment Success, Issue, Refund, Reset)
 */
function bindWorkspaceActions() {
    // 1. No Payment Button
    const noPaymentBtn = document.getElementById('btnNoPayment');
    if (noPaymentBtn) {
        noPaymentBtn.addEventListener('click', () => {
            if (!activeSchoolId) return;
            postReconAction('save_no_payment', {
                school_id: activeSchoolId,
                date: currentDate,
                session: currentSession
            }, 'Recording No Payment...');
        });
    }

    // 2. Payment Success Button
    const paymentSuccessBtn = document.getElementById('btnPaymentSuccess');
    if (paymentSuccessBtn) {
        paymentSuccessBtn.addEventListener('click', () => {
            if (!activeSchoolId) return;
            postReconAction('save_payment_success', {
                school_id: activeSchoolId,
                date: currentDate,
                session: currentSession
            }, 'Recording Payment Success...');
        });
    }

    // 3. Save Reconciliation Issue Button
    const issueBtn = document.getElementById('btnSaveIssue');
    if (issueBtn) {
        issueBtn.addEventListener('click', () => {
            if (!activeSchoolId) return;
            const input = document.getElementById('wsIssueInput');
            const ref = input ? input.value.trim() : '';
            if (!ref) {
                showToast('warning', 'Missing Reference', 'Please enter an issue reference, code, or description.');
                if (input) input.focus();
                return;
            }

            const isUrgent = document.getElementById('wsUrgentCheck')?.checked ? 1 : 0;
            const priority = document.getElementById('wsUrgentPriority')?.value || 'High';

            postReconAction('save_issue', {
                school_id: activeSchoolId,
                date: currentDate,
                session: currentSession,
                issue_reference: ref,
                is_urgent: isUrgent,
                urgent_priority: priority
            }, 'Saving reconciliation issue...');
        });
    }

    // 4. Save Refund Button
    const refundBtn = document.getElementById('btnSaveRefund');
    if (refundBtn) {
        refundBtn.addEventListener('click', () => {
            if (!activeSchoolId) return;
            const input = document.getElementById('wsRefundInput');
            const ref = input ? input.value.trim() : '';
            if (!ref) {
                showToast('warning', 'Missing Details', 'Please enter refund reference or transaction details.');
                if (input) input.focus();
                return;
            }

            postReconAction('save_refund', {
                school_id: activeSchoolId,
                date: currentDate,
                session: currentSession,
                refund_reference: ref
            }, 'Processing refund...');
        });
    }

    // 4b. Save Duplicate Receipt Button
    const dupReceiptBtn = document.getElementById('btnSaveDupReceipt');
    if (dupReceiptBtn) {
        dupReceiptBtn.addEventListener('click', () => {
            if (!activeSchoolId) return;
            const noInput = document.getElementById('wsDupReceiptNo');
            const descInput = document.getElementById('wsDupReceiptDesc');
            const receiptNo = noInput ? noInput.value.trim() : '';
            const receiptDesc = descInput ? descInput.value.trim() : '';

            if (!receiptNo && !receiptDesc) {
                showToast('warning', 'Missing Details', 'Please enter duplicate receipt number or description.');
                if (noInput) noInput.focus();
                return;
            }

            postReconAction('save_duplicate_receipt', {
                school_id: activeSchoolId,
                date: currentDate,
                session: currentSession,
                receipt_no: receiptNo,
                receipt_desc: receiptDesc
            }, 'Saving duplicate receipt...');
        });
    }

    // 5. Save Remark Button (varchar)
    const remarkBtn = document.getElementById('btnSaveRemark');
    if (remarkBtn) {
        remarkBtn.addEventListener('click', () => {
            if (!activeSchoolId) return;
            const input = document.getElementById('wsRemarkInput');
            const remarkText = input ? input.value.trim() : '';
            if (!remarkText) {
                showToast('warning', 'Missing Remark', 'Please enter a remark or notes for this school.');
                if (input) input.focus();
                return;
            }

            postReconAction('save_remark', {
                school_id: activeSchoolId,
                date: currentDate,
                session: currentSession,
                remark: remarkText
            }, 'Saving remark...');
        });
    }

    // 5. Reset Record Button (Super Admin / Admin confirmation modal)
    const resetBtn = document.getElementById('btnResetRecord');
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            if (!activeSchoolId) return;
            confirmModal({
                title: '⚠️ Reset School Reconciliation Record',
                message: `
                    <div style="font-size: 14px; line-height: 1.5;">
                        <p style="margin-bottom: 10px; color: var(--text-main);">
                            Are you sure you want to reset this school back to <strong>Pending</strong>?
                        </p>
                        <div style="background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 8px; padding: 12px 14px; color: #991b1b; font-size: 13px;">
                            <strong>⚠️ Warning:</strong> All payment selections, issue references, refund details, and duplicate receipts recorded for this session will be cleared from the overview table.
                        </div>
                    </div>
                `,
                confirmText: 'Yes, Reset to Pending',
                cancelText: 'Cancel',
                confirmClass: 'btn-danger',
                onConfirm: () => {
                    postReconAction('reset_record', {
                        school_id: activeSchoolId,
                        date: currentDate,
                        session: currentSession
                    }, 'Resetting record...');
                }
            });
        });
    }

    // 6. Close Workspace Button
    const closeBtn = document.getElementById('btnCloseWorkspace');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            if (isWorkflowLocked) {
                confirmModal({
                    title: 'Task Incomplete',
                    message: 'Reconciliation for this school is not marked complete. Are you sure you want to close without completing?',
                    confirmText: 'Close Workspace',
                    confirmClass: 'btn-warning',
                    onConfirm: closeWorkspace
                });
            } else {
                closeWorkspace();
            }
        });
    }
}

window.resolveUrgentPrompt = function(schoolId, schoolName, countRef) {
    confirmModal({
        title: 'Resolve Escalated Reconciliation Issue',
        message: `Are you sure you want to resolve the escalated reconciliation issue (Count / Ref: <strong>${escapeHtml(countRef)}</strong>) for <strong>${escapeHtml(schoolName)}</strong>?<br><br>All users will be notified on their dashboards that this issue was resolved by you.`,
        confirmText: 'Resolve Case',
        confirmClass: 'btn-success',
        onConfirm: () => {
            postReconAction('resolve_urgent', {
                school_id: schoolId,
                date: currentDate,
                session: currentSession,
                notes: 'Issue verified and resolved by administrator.'
            }, 'Resolving urgent issue...');
        }
    });
};

window.toggleSchoolCompletion = function(schoolId, targetCompleted) {
    const rec = recordsCache.find(r => parseInt(r.school_id, 10) === parseInt(schoolId, 10));
    if (!rec) return;

    // CASE 1: Reopening a completed task - show all currently stored data and allow user to view & reopen to re-edit
    if (targetCompleted === 0) {
        // Build clear overview of all data currently stored for this school
        let paymentBadge = '<span style="color: #64748b; font-style: italic;">None</span>';
        if (rec.payment_status === 'PAYMENT_SUCCESS') {
            paymentBadge = '<span style="color: #059669; font-weight: 700;">✓ Payment Success</span>';
        } else if (rec.payment_status === 'NO_PAYMENT') {
            paymentBadge = '<span style="color: #2563eb; font-weight: 700;">✓ No Payment</span>';
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
        const completedUser = rec.completed_by_user || rec.updated_by_user || 'User';
        const completedTime = rec.completed_at || rec.updated_at || '';

        const modalHtml = `
            <div style="font-size: 13.5px; line-height: 1.5;">
                <p style="margin-bottom: 10px; color: #1e293b; font-weight: 500;">
                    Currently stored reconciliation data for <strong>${escapeHtml(rec.school_name)}</strong>:
                </p>
                <div style="background: #f8fafc; border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-bottom: 12px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <tbody>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 6px 4px; font-weight: 600; color: #475569; width: 42%;">Payment Status:</td>
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
                            <tr>
                                <td style="padding: 6px 4px; font-weight: 600; color: #475569;">Completed By:</td>
                                <td style="padding: 6px 4px; color: #0f172a;">👤 ${escapeHtml(completedUser)} ${completedTime ? `<span style="color:#64748b; font-size:12px;">(${escapeHtml(completedTime)})</span>` : ''}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 10px 12px; color: #92400e; font-size: 12.5px;">
                    ℹ️ Reopening will switch this school back to <strong>Pending</strong>. All stored data above is preserved and will be loaded directly into the workspace so you can re-edit and update it.
                </div>
            </div>
        `;

        confirmModal({
            title: 'Stored Reconciliation Data & Reopen Task',
            message: modalHtml,
            confirmText: '↺ Reopen Task to Re-edit',
            cancelText: 'Cancel / View Only',
            confirmClass: 'btn-warning',
            onConfirm: () => {
                postReconAction('mark_completed', {
                    school_id: schoolId,
                    date: currentDate,
                    session: currentSession,
                    is_completed: 0
                }, 'Reopening school task...', () => {
                    setTimeout(() => {
                        handleSelectSchoolFromTable(schoolId);
                        showToast('success', 'Task Reopened', 'School reopened for re-editing. Stored data is loaded into the workspace.');
                    }, 250);
                });
            }
        });
        return;
    }

    // CASE 2: Marking school statement as Completed
    // Check whether the active workspace has pending entries for this school
    let activeIssue = '';
    let activeRefund = '';
    let activeDupNo = '';
    let activeDupDesc = '';
    let activeRemark = '';

    if (activeSchoolId && parseInt(activeSchoolId, 10) === parseInt(schoolId, 10)) {
        activeIssue = document.getElementById('wsIssueInput')?.value.trim() || '';
        activeRefund = document.getElementById('wsRefundInput')?.value.trim() || '';
        activeDupNo = document.getElementById('wsDupReceiptNo')?.value.trim() || '';
        activeDupDesc = document.getElementById('wsDupReceiptDesc')?.value.trim() || '';
        activeRemark = document.getElementById('wsRemarkInput')?.value.trim() || '';
    }

    const effectiveIssue = activeIssue || rec.issue_reference || '';
    const effectiveRefund = activeRefund || rec.refund_reference || '';
    const effectiveDupNo = activeDupNo || rec.duplicate_receipt_no || '';
    const effectiveRemark = activeRemark || rec.custom_remark || '';

    // Check whether the school statement has been verified / completed
    const hasPayment = (rec.payment_status === 'NO_PAYMENT' || rec.payment_status === 'PAYMENT_SUCCESS');
    const hasIssue = Boolean(effectiveIssue);
    const hasRefund = Boolean(effectiveRefund);
    const hasDuplicate = Boolean(effectiveDupNo);
    const hasCustomRemark = Boolean(effectiveRemark);
    const hasReconOrRefund = hasIssue || hasRefund;
    const hasStatementAction = hasPayment || hasReconOrRefund || hasDuplicate || hasCustomRemark;

    let paymentLabel = '<span style="color: #94a3b8; font-style: italic;">Not Selected</span>';
    if (rec.payment_status === 'PAYMENT_SUCCESS') {
        paymentLabel = '<span style="color: #10b981; font-weight: 700;">✓ Payment Success</span>';
    } else if (rec.payment_status === 'NO_PAYMENT') {
        paymentLabel = '<span style="color: #2563eb; font-weight: 700;">✓ No Payment</span>';
    } else if (hasReconOrRefund) {
        paymentLabel = '<span style="color: #64748b; font-style: italic;">Not Required (Recon/Refund Entered)</span>';
    }

    let issueLabel = '<span style="color: #94a3b8; font-style: italic;">None</span>';
    if (effectiveIssue) {
        issueLabel = `<span style="color: #ef4444; font-weight: 700;">Issue (${escapeHtml(effectiveIssue)})</span>`;
    }

    let refundLabel = '<span style="color: #94a3b8; font-style: italic;">None</span>';
    if (effectiveRefund) {
        refundLabel = `<span style="color: #f97316; font-weight: 700;">Refund (${escapeHtml(effectiveRefund)})</span>`;
    }

    let dupLabel = '<span style="color: #94a3b8; font-style: italic;">None</span>';
    if (effectiveDupNo) {
        dupLabel = `<span style="color: #0284c7; font-weight: 700;">${escapeHtml(effectiveDupNo)}</span>`;
    }

    let customRemarkLabel = '<span style="color: #94a3b8; font-style: italic;">None</span>';
    if (effectiveRemark) {
        customRemarkLabel = `<span style="color: #6366f1; font-weight: 700;">${escapeHtml(effectiveRemark)}</span>`;
    }

    let statementNoticeHtml = '';
    let verificationCheckboxHtml = '';

    if (hasStatementAction) {
        let noteMsg = 'Reconciliation statement action is already recorded for this school.';
        if (hasReconOrRefund) {
            noteMsg = 'Reconciliation issue and/or refund details are recorded. Per operating rules, No Payment / Payment Success selection is not required.';
        } else if (hasPayment) {
            noteMsg = 'School payment status has been recorded for this statement.';
        } else if (hasDuplicate) {
            noteMsg = 'Duplicate receipt details have been recorded for this statement.';
        } else if (hasCustomRemark) {
            noteMsg = 'A custom remark has been saved for this statement.';
        }

        statementNoticeHtml = `
            <div style="background: #ecfdf5; border: 1.5px solid #a7f3d0; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px;">
                <div style="display: flex; align-items: center; gap: 8px; color: #065f46; font-weight: 700; font-size: 13.5px; margin-bottom: 4px;">
                    <span>✓ School Statement Checked & Recorded</span>
                </div>
                <div style="font-size: 12.5px; color: #047857; line-height: 1.4;">
                    ${noteMsg}
                </div>
            </div>
        `;
    } else {
        statementNoticeHtml = `
            <div style="background: #fffbeb; border: 1.5px solid #fde68a; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px;">
                <div style="display: flex; align-items: center; gap: 8px; color: #92400e; font-weight: 700; font-size: 13.5px; margin-bottom: 4px;">
                    <span>⚠️ School Statement Action Not Yet Selected</span>
                </div>
                <div style="font-size: 12.5px; color: #78350f; line-height: 1.4;">
                    Neither Payment Option, Reconciliation Issue, Refund, Duplicate Receipt, nor Custom Remark has been recorded for this school statement yet.
                </div>
            </div>
        `;

        verificationCheckboxHtml = `
            <label style="display: flex; align-items: flex-start; gap: 10px; background: #f8fafc; padding: 10px 14px; border-radius: 8px; border: 1.5px solid #cbd5e1; cursor: pointer; margin-top: 12px;">
                <input type="checkbox" id="modalVerifyStatementCheck" style="margin-top: 3px; transform: scale(1.2); cursor: pointer;">
                <span style="font-size: 12.5px; color: #1e293b; font-weight: 600; line-height: 1.4;">
                    I have checked the school statement on the ERP portal and confirm that all online payment transactions for this session are fully reconciled and completed.
                </span>
            </label>
        `;
    }

    const popupHtml = `
        <div style="font-size: 13.5px;">
            ${statementNoticeHtml}

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12.5px;">
                    <div><span style="color: var(--text-muted);">School:</span> <strong>${escapeHtml(rec.school_name)}</strong></div>
                    <div><span style="color: var(--text-muted);">Session:</span> <strong>${currentSession} (${currentDate})</strong></div>
                    <div><span style="color: var(--text-muted);">Payment Status:</span> ${paymentLabel}</div>
                    <div><span style="color: var(--text-muted);">Reconciliation:</span> ${issueLabel}</div>
                    <div><span style="color: var(--text-muted);">Refund:</span> ${refundLabel}</div>
                    <div><span style="color: var(--text-muted);">Duplicate:</span> ${dupLabel}</div>
                    <div><span style="color: var(--text-muted);">Custom Remark:</span> ${customRemarkLabel}</div>
                </div>
            </div>

            <p style="margin-bottom: 6px; font-weight: 600; color: var(--text-main);">
                Are you sure you want to mark this school's reconciliation as <strong>Completed</strong>?
            </p>

            ${verificationCheckboxHtml}
        </div>
    `;

    confirmModal({
        title: 'Confirm School Statement Completion',
        message: popupHtml,
        confirmText: '✓ Yes, Mark Completed',
        cancelText: 'Cancel',
        confirmClass: 'btn-payment-success',
        onConfirm: () => {
            const payload = {
                school_id: schoolId,
                date: currentDate,
                session: currentSession,
                is_completed: 1
            };
            if (activeIssue) payload.issue_reference = activeIssue;
            if (activeRefund) payload.refund_reference = activeRefund;
            if (activeDupNo) payload.duplicate_receipt_no = activeDupNo;
            if (activeDupDesc) payload.duplicate_receipt_desc = activeDupDesc;
            if (activeRemark) payload.custom_remark = activeRemark;

            postReconAction('mark_completed', payload, 'Marking school completed...');
        }
    });

    // If checkbox was rendered, require it to be checked before confirming
    if (!hasStatementAction) {
        setTimeout(() => {
            const btn = document.getElementById('globalModalConfirmBtn');
            const chk = document.getElementById('modalVerifyStatementCheck');
            if (btn && chk) {
                btn.disabled = true;
                btn.style.opacity = '0.4';
                btn.style.cursor = 'not-allowed';
                chk.addEventListener('change', () => {
                    btn.disabled = !chk.checked;
                    btn.style.opacity = chk.checked ? '1' : '0.4';
                    btn.style.cursor = chk.checked ? 'pointer' : 'not-allowed';
                });
            }
        }, 60);
    }
};

/**
 * Universal AJAX POST handler for reconciliation operations
 */
function postReconAction(actionName, data, loadingMsg = 'Processing...', onSuccess = null) {
    const formData = new FormData();
    formData.append('csrf_token', getCsrfToken());
    for (const key in data) {
        formData.append(key, data[key]);
    }

    fetch(`api/reconciliation.php?action=${actionName}&_t=${Date.now()}`, {
        method: 'POST',
        body: formData,
        cache: 'no-store'
    })
    .then(res => res.json())
    .then(res => {
        if (res.success) {
            showToast('success', 'Saved', res.message);
            // Optimistic instant in-memory update to immediately reflect on table overview
            if (res.record && recordsCache && data.school_id) {
                const targetId = parseInt(data.school_id, 10);
                const idx = recordsCache.findIndex(r => parseInt(r.school_id, 10) === targetId);
                if (idx !== -1) {
                    recordsCache[idx] = Object.assign({}, recordsCache[idx], res.record);
                    if (res.summary) {
                        renderOverviewCards(res.summary);
                    }
                    renderMainTable(recordsCache);
                    renderWorkspace(recordsCache[idx]);
                }
            }
            // Fresh network reload with cache-busting to ensure complete DB parity
            loadSessionRecords();
            unlockWorkspace();

            if (typeof onSuccess === 'function') {
                try {
                    onSuccess(res);
                } catch (e) {
                    console.error('Error in postReconAction onSuccess callback:', e);
                }
            }
        } else {
            showToast('error', 'Operation Failed', res.message || 'Error executing request.');
        }
    })
    .catch(err => {
        console.error(err);
        showToast('error', 'Network Error', 'Server communication failure.');
    });
}

/**
 * Table Search Filter
 */
function filterTableRows(query) {
    const rows = document.querySelectorAll('#reconciliationTableBody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

/**
 * ==========================================================================
 * SUPER ADMIN NOTIFICATIONS MODULE (All users view banner, Super Admin manages)
 * ==========================================================================
 */
function initNotifications() {
    fetchActiveNotifications();
}
window.fetchActiveNotifications = fetchActiveNotifications;

function fetchActiveNotifications() {
    const banner = document.getElementById('superAdminAnnouncementsBanner');
    if (!banner) return;

    fetch('api/notifications.php?action=get_active')
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data && res.data.length > 0) {
                let html = '';
                res.data.forEach(n => {
                    let icon = 'ℹ️';
                    let typeBadge = 'General Info';
                    if (n.type === 'warning') {
                        icon = '⚠️';
                        typeBadge = 'Important Notice';
                    } else if (n.type === 'urgent') {
                        icon = '🚨';
                        typeBadge = 'Urgent Priority';
                    } else if (n.type === 'success') {
                        icon = '✅';
                        typeBadge = 'Official Notice';
                    }

                    const timeFormatted = n.created_at ? n.created_at.replace('T', ' ') : '';

                    html += `
                        <div class="announcement-card type-${escapeHtml(n.type)}">
                            <div class="announcement-icon-badge">${icon}</div>
                            <div class="announcement-content">
                                <div class="announcement-title">
                                    <span>${escapeHtml(n.title)}</span>
                                    <span class="announcement-type-tag">${typeBadge}</span>
                                </div>
                                <div class="announcement-message">${escapeHtml(n.message)}</div>
                                <div class="announcement-meta">
                                    <span>📣 Published by Super Admin (${escapeHtml(n.author_full_name || n.author_name)})</span>
                                    <span>•</span>
                                    <span>🕒 ${escapeHtml(timeFormatted)}</span>
                                </div>
                            </div>
                        </div>
                    `;
                });
                banner.innerHTML = html;
                banner.style.display = 'flex';
            } else {
                banner.innerHTML = '';
                banner.style.display = 'none';
            }
        })
        .catch(err => {
            console.error('Active notifications fetch error:', err);
        });
}
