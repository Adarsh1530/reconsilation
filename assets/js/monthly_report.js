/**
 * Monthly Reconciliation Report JavaScript
 * School Reconciliation Management System
 * Powers the interactive month matrix, PDF print preview, and Excel download
 */

document.addEventListener('DOMContentLoaded', () => {
    initMonthlyReport();
});

let currentReportData = null;

function initMonthlyReport() {
    const monthInput = document.getElementById('reportMonthInput');
    const sessionSelect = document.getElementById('reportSessionSelect');
    const prevBtn = document.getElementById('btnPrevMonth');
    const nextBtn = document.getElementById('btnNextMonth');
    const currentBtn = document.getElementById('btnCurrentMonth');
    const searchInput = document.getElementById('reportSchoolSearch');
    const downloadExcelBtn = document.getElementById('btnDownloadExcel');
    const downloadPdfBtn = document.getElementById('btnDownloadPdf');

    // Initial Load
    loadMonthlyReport();

    // Event listeners for month navigation
    if (monthInput) {
        monthInput.addEventListener('change', () => {
            loadMonthlyReport();
        });
    }

    if (sessionSelect) {
        sessionSelect.addEventListener('change', () => {
            loadMonthlyReport();
        });
    }

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            shiftMonth(-1);
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            shiftMonth(1);
        });
    }

    if (currentBtn) {
        currentBtn.addEventListener('click', () => {
            const now = new Date();
            const y = now.getFullYear();
            const m = String(now.getMonth() + 1).padStart(2, '0');
            if (monthInput) {
                monthInput.value = `${y}-${m}`;
                loadMonthlyReport();
            }
        });
    }

    // Live search filter on school name/code
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            filterMatrixSchools(searchInput.value.trim().toLowerCase());
        });
    }

    // Excel Download Button
    if (downloadExcelBtn) {
        downloadExcelBtn.addEventListener('click', () => {
            const { year, month } = getSelectedYearMonth();
            const session = sessionSelect ? sessionSelect.value : 'ALL';
            const url = `api/monthly_report.php?action=export_excel&year=${year}&month=${month}&session=${encodeURIComponent(session)}`;
            window.location.href = url;
        });
    }

    // PDF Download Button (Triggers Landscape High-Def Print to PDF)
    if (downloadPdfBtn) {
        downloadPdfBtn.addEventListener('click', () => {
            window.print();
        });
    }
}

/**
 * Helper to get currently selected year & month numbers
 */
function getSelectedYearMonth() {
    const monthInput = document.getElementById('reportMonthInput');
    let val = monthInput ? monthInput.value : '';
    if (!val) {
        const d = new Date();
        val = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    }
    const parts = val.split('-');
    const year = parseInt(parts[0], 10) || new Date().getFullYear();
    const month = parseInt(parts[1], 10) || (new Date().getMonth() + 1);
    return { year, month };
}

/**
 * Shift current month by delta (+1 or -1)
 */
function shiftMonth(delta) {
    const { year, month } = getSelectedYearMonth();
    const d = new Date(year, month - 1 + delta, 1);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const monthInput = document.getElementById('reportMonthInput');
    if (monthInput) {
        monthInput.value = `${y}-${m}`;
        loadMonthlyReport();
    }
}

/**
 * Fetch and render monthly reconciliation report
 */
function loadMonthlyReport() {
    const { year, month } = getSelectedYearMonth();
    const sessionSelect = document.getElementById('reportSessionSelect');
    const session = sessionSelect ? sessionSelect.value : 'ALL';

    const tbody = document.getElementById('reportMatrixTbody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="32" style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <span class="spinner" style="display: inline-block; width: 18px; height: 18px; border: 2px solid #cbd5e1; border-top-color: #2563eb; border-radius: 50%; animation: spin 0.8s linear infinite;"></span>
                        <span style="font-size: 13.5px; font-weight: 600;">Loading Reconciliation Matrix for ${month}/${year}...</span>
                    </div>
                </td>
            </tr>
        `;
    }

    fetch(`api/monthly_report.php?action=get_monthly_data&year=${year}&month=${month}&session=${encodeURIComponent(session)}`)
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data) {
                currentReportData = res.data;
                renderReportSummary(res.data);
                renderMatrixTable(res.data);
            } else {
                if (tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="32" style="text-align: center; padding: 30px; color: #dc2626;">
                                ${escapeHtml(res.message || 'Failed to load report data.')}
                            </td>
                        </tr>
                    `;
                }
            }
        })
        .catch(err => {
            console.error('Report fetch error:', err);
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="32" style="text-align: center; padding: 30px; color: #dc2626;">
                            Network or server error while loading report.
                        </td>
                    </tr>
                `;
            }
        });
}

/**
 * Render Overview Summary KPI cards
 */
function renderReportSummary(data) {
    const s = data.summary || {};
    const totalSchoolsEl = document.getElementById('summaryTotalSchools');
    const daysInMonthEl = document.getElementById('summaryDaysInMonth');
    const monthLabelEl = document.getElementById('summaryMonthLabel');
    const completedEl = document.getElementById('summaryCompleted');
    const issuesEl = document.getElementById('summaryIssues');
    const duplicatesEl = document.getElementById('summaryDuplicates');

    const matrixMonthLabel = document.getElementById('matrixMonthLabel');
    const printMonthLabel = document.getElementById('printMonthLabel');

    if (totalSchoolsEl) totalSchoolsEl.textContent = s.total_schools ?? '-';
    if (daysInMonthEl) daysInMonthEl.textContent = s.days_in_month ?? '-';
    if (monthLabelEl) monthLabelEl.textContent = data.month_label ?? '';
    if (completedEl) completedEl.textContent = s.total_completed ?? 0;
    if (issuesEl) issuesEl.textContent = s.total_issues ?? 0;
    if (duplicatesEl) duplicatesEl.textContent = s.total_duplicates ?? 0;

    if (matrixMonthLabel) matrixMonthLabel.textContent = data.month_label ?? '';
    if (printMonthLabel) printMonthLabel.textContent = `${data.month_label} (${data.session_filter === 'ALL' ? 'Both Sessions' : data.session_filter + ' Session'})`;
}

/**
 * Render the full matrix table (Thead & Tbody)
 */
function renderMatrixTable(data) {
    const thead = document.getElementById('reportMatrixThead');
    const tbody = document.getElementById('reportMatrixTbody');
    if (!thead || !tbody) return;

    // 1. Build Header
    let theadHtml = '<tr>';
    theadHtml += '<th class="school-col-header">School Name</th>';

    data.days.forEach(d => {
        const cls = [
            'day-header',
            d.is_today ? 'is-today' : '',
            d.is_weekend ? 'is-weekend' : ''
        ].filter(Boolean).join(' ');

        theadHtml += `
            <th class="${cls}">
                <div style="font-size: 14px; font-weight: 800;">${d.day}</div>
                <div style="font-size: 10px; opacity: 0.8; font-weight: 600; text-transform: uppercase;">${escapeHtml(d.day_name)}</div>
            </th>
        `;
    });
    theadHtml += '</tr>';
    thead.innerHTML = theadHtml;

    // 2. Build Rows
    if (!data.matrix || data.matrix.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="${data.days_in_month + 1}" style="text-align: center; padding: 40px; color: var(--text-muted);">
                    No active schools found in system.
                </td>
            </tr>
        `;
        return;
    }

    let tbodyHtml = '';
    data.matrix.forEach(sRow => {
        tbodyHtml += `<tr class="school-row" data-school-name="${escapeHtml(sRow.school_name.toLowerCase())}" data-school-code="${escapeHtml((sRow.school_code || '').toLowerCase())}">`;

        // First Column: School Name & Code
        tbodyHtml += `
            <td class="school-cell">
                <div style="display: flex; flex-direction: column; gap: 3px;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        ${sRow.school_code ? `<span style="font-size: 10px; font-weight: 800; background: #e2e8f0; color: #334155; padding: 1px 6px; border-radius: 4px; font-family: monospace;">${escapeHtml(sRow.school_code)}</span>` : ''}
                    </div>
                    <div style="font-size: 13px; font-weight: 700; color: var(--text-main);">${escapeHtml(sRow.school_name)}</div>
                </div>
            </td>
        `;

        // Day Columns (1..daysInMonth)
        for (let d = 1; d <= data.days_in_month; d++) {
            const cell = sRow.days[d];

            if (!cell || !cell.has_data) {
                tbodyHtml += `<td class="data-cell cell-empty">-</td>`;
                continue;
            }

            // Cell has data! Render exactly matching ref_report.xlsx layout
            const usersStr = cell.users && cell.users.length > 0 ? cell.users.join(', ') : '-';
            const timesStr = cell.times && cell.times.length > 0 ? cell.times.join(', ') : '-';

            let pillsHtml = '';
            if (cell.payment_statuses && cell.payment_statuses.length > 0) {
                cell.payment_statuses.forEach(ps => {
                    const pillCls = ps === 'PS' ? 'status-ps' : 'status-nil';
                    pillsHtml += `<span class="pill-status ${pillCls}">${escapeHtml(ps)}</span>`;
                });
            } else {
                pillsHtml += `<span class="pill-status status-nil">Nil</span>`;
            }

            const reconVal = cell.issues && cell.issues.length > 0 ? cell.issues.join(', ') : '';
            const refundVal = cell.refunds && cell.refunds.length > 0 ? cell.refunds.join(', ') : '';
            const dupVal = cell.duplicates && cell.duplicates.length > 0 ? cell.duplicates.join(', ') : '';

            tbodyHtml += `
                <td class="data-cell" onclick="openCellDetailModal(${sRow.school_id}, ${d})" title="Click to view full day details">
                    <div class="cell-block">
                        <div class="cell-row-user">👤 ${escapeHtml(usersStr)}</div>
                        <div class="cell-row-time">🕒 ${escapeHtml(timesStr)}</div>
                        <div class="cell-row-pills">${pillsHtml}</div>
                        <div class="cell-row-item">
                            Recon: ${reconVal ? `<span class="cell-row-recon">${escapeHtml(reconVal)}</span>` : ''}
                        </div>
                        <div class="cell-row-item">
                            Refund: ${refundVal ? `<span class="cell-row-refund">${escapeHtml(refundVal)}</span>` : ''}
                        </div>
                        <div class="cell-row-item">
                            Duplicate: ${dupVal ? `<span class="cell-row-dup">${escapeHtml(dupVal)}</span>` : ''}
                        </div>
                        ${cell.remarks && cell.remarks.length > 0 ? `<div class="cell-row-item" style="font-size: 10.5px; color: #475569; margin-top: 2px;">💬 ${escapeHtml(cell.remarks.join(' | '))}</div>` : ''}
                    </div>
                </td>
            `;
        }

        tbodyHtml += '</tr>';
    });

    tbody.innerHTML = tbodyHtml;
}

/**
 * Filter Matrix Schools live by name or code
 */
function filterMatrixSchools(query) {
    const rows = document.querySelectorAll('.school-row');
    rows.forEach(row => {
        const name = row.getAttribute('data-school-name') || '';
        const code = row.getAttribute('data-school-code') || '';
        if (!query || name.includes(query) || code.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

/**
 * Open detail popup modal for a specific cell
 */
window.openCellDetailModal = function(schoolId, dayNum) {
    if (!currentReportData) return;

    const schoolRow = currentReportData.matrix.find(s => s.school_id === schoolId);
    if (!schoolRow) return;

    const cell = schoolRow.days[dayNum];
    if (!cell) return;

    const modal = document.getElementById('cellDetailModal');
    const titleEl = document.getElementById('cellModalTitle');
    const subtitleEl = document.getElementById('cellModalSubtitle');
    const bodyEl = document.getElementById('cellModalBody');

    const dayObj = currentReportData.days.find(d => d.day === dayNum);
    const dateFormatted = dayObj ? dayObj.date : `${currentReportData.year}-${String(currentReportData.month).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}`;

    if (titleEl) titleEl.textContent = `${schoolRow.school_name}`;
    if (subtitleEl) subtitleEl.textContent = `Date: ${dateFormatted} (${dayObj ? dayObj.day_name : ''})`;

    if (bodyEl) {
        bodyEl.innerHTML = `
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <div style="background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 14px;">
                    <div style="font-size: 11.5px; font-weight: 700; color: #1e3a8a; text-transform: uppercase; margin-bottom: 8px;">
                        Report Summary Formatted Text:
                    </div>
                    <pre style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 6px; padding: 10px; font-family: monospace; font-size: 12px; margin: 0; line-height: 1.5; white-space: pre-wrap;">${escapeHtml(cell.formatted_text)}</pre>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 6px; padding: 10px;">
                        <span style="font-size: 11px; color: var(--text-muted); display: block;">Users Involved:</span>
                        <strong style="font-size: 13px; color: var(--text-main);">${escapeHtml(cell.users.join(', ') || '-')}</strong>
                    </div>
                    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 6px; padding: 10px;">
                        <span style="font-size: 11px; color: var(--text-muted); display: block;">Completion Times:</span>
                        <strong style="font-size: 13px; color: var(--text-main);">${escapeHtml(cell.times.join(', ') || '-')}</strong>
                    </div>
                    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 6px; padding: 10px;">
                        <span style="font-size: 11px; color: var(--text-muted); display: block;">Reconciliation Issue:</span>
                        <strong style="font-size: 13px; color: #dc2626; font-family: monospace;">${escapeHtml(cell.issues.join(', ') || 'No Issue Reported')}</strong>
                    </div>
                    <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: 6px; padding: 10px;">
                        <span style="font-size: 11px; color: var(--text-muted); display: block;">Duplicate Receipts:</span>
                        <strong style="font-size: 13px; color: #0284c7; font-family: monospace;">${escapeHtml(cell.duplicates.join(', ') || 'None')}</strong>
                    </div>
                </div>

                ${cell.remarks && cell.remarks.length > 0 ? `
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 10px 12px; font-size: 12.5px; color: #1e40af;">
                    <strong>Remarks:</strong> ${escapeHtml(cell.remarks.join(' | '))}
                </div>
                ` : ''}
            </div>
        `;
    }

    if (modal) modal.classList.add('show');
};
