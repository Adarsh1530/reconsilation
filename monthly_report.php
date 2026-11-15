<?php
/**
 * Monthly Reconciliation Report
 * School Reconciliation Management System
 * Available to ALL Roles (Super Admin, Admin, User/Staff)
 */

$page_title = 'Monthly Reconciliation Report';
$extra_js = ['assets/js/monthly_report.js'];

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

// Initial month and year from GET or current date
$selected_year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$selected_month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
if ($selected_month < 1 || $selected_month > 12) $selected_month = (int)date('m');
if ($selected_year < 2000 || $selected_year > 2100) $selected_year = (int)date('Y');
$month_str = sprintf('%04d-%02d', $selected_year, $selected_month);

include __DIR__ . '/includes/header.php';
?>

<!-- ==========================================================================
     MONTHLY REPORT CONTROLS & HEADER
     ========================================================================== -->
<div class="report-header-wrapper" style="margin-bottom: 20px;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;">
        <div>
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="background: #e0e7ff; color: #3730a3; padding: 6px 10px; border-radius: 8px; display: inline-flex; align-items: center; font-size: 18px;">
                    📊
                </div>
                <div>
                    <h1 style="font-size: 22px; font-weight: 800; color: var(--text-main); line-height: 1.2;">Monthly Reconciliation Report</h1>
                    <p style="font-size: 13.5px; color: var(--text-muted); margin-top: 2px;">
                        Month-wise daily reconciliation matrix, payment status, issues, refunds, and duplicate receipts across all schools.
                    </p>
                </div>
            </div>
        </div>

        <!-- Export Buttons (PDF & Excel) -->
        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnDownloadPdf" title="Download or print high-definition landscape PDF report">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 5px;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span>Download PDF</span>
            </button>

            <button type="button" class="btn btn-primary btn-sm" id="btnDownloadExcel" style="background: #15803d; border-color: #15803d;" title="Download exact formatted Excel (.xlsx) matching official reconciliation format">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 5px;">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Download Excel (.xlsx)</span>
            </button>
        </div>
    </div>

    <!-- Month Navigator Bar -->
    <div style="background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 12px 18px; display: flex; align-items: center; justify-content: flex-start; gap: 10px; box-shadow: var(--shadow-sm); flex-wrap: wrap;">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnPrevMonth" title="Previous Month">
            &larr; Prev Month
        </button>
        <div style="display: flex; align-items: center; gap: 6px;">
            <label for="reportMonthInput" style="font-size: 13px; font-weight: 700; color: var(--text-main); margin-bottom: 0;">Month:</label>
            <input type="month" id="reportMonthInput" class="form-control" value="<?= e($month_str) ?>" style="width: 160px; font-size: 13.5px; font-weight: 700; padding: 6px 10px;">
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnNextMonth" title="Next Month">
            Next Month &rarr;
        </button>
        <button type="button" class="btn btn-sm" id="btnCurrentMonth" style="background: #f1f5f9; color: #475569; font-weight: 600; border: 1px solid #cbd5e1;" title="Jump to Current Month">
            Current Month
        </button>
    </div>
</div>

<!-- ==========================================================================
     MONTHLY MATRIX TABLE
     ========================================================================== -->
<div class="table-card" style="box-shadow: var(--shadow-md); border-radius: var(--radius-lg); overflow: hidden;">
    <div class="card-header" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; padding: 14px 20px; background: #f8fafc; border-bottom: 1px solid var(--border-color);">
        <div>
            <h2 class="card-title" id="matrixTitle" style="font-size: 15px; font-weight: 700; color: #1e3a8a;">
                Monthly Reconciliation Matrix (<span id="matrixMonthLabel">Loading...</span>)
            </h2>
            <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                Numbers at top represent calendar dates of the month. Scroll horizontally to inspect all dates. Click any cell for detail breakdown.
            </div>
        </div>
        <div style="display: flex; align-items: center; gap: 8px; font-size: 11.5px; font-weight: 600;">
            <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 7px; background: #dcfce7; color: #15803d; border-radius: 4px;">PS = Payment Success</span>
            <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 7px; background: #fef3c7; color: #b45309; border-radius: 4px;">Nil = No Payment</span>
            <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 7px; background: #fee2e2; color: #b91c1c; border-radius: 4px;">Recon = Issue</span>
        </div>
    </div>

    <!-- Scrollable Matrix Table Container -->
    <div class="report-matrix-wrapper" style="position: relative; overflow-x: auto; max-width: 100%; -webkit-overflow-scrolling: touch;">
        <table class="report-matrix-table" id="reportMatrixTable">
            <thead id="reportMatrixThead">
                <!-- Populated dynamically via assets/js/monthly_report.js -->
            </thead>
            <tbody id="reportMatrixTbody">
                <tr>
                    <td colspan="32" style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <div style="font-size: 14px; font-weight: 600;">Loading Monthly Reconciliation Data...</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ==========================================================================
     CELL DETAIL POPUP MODAL
     ========================================================================== -->
<div id="cellDetailModal" class="modal-backdrop">
    <div class="modal-dialog" style="max-width: 500px; width: 95%;">
        <div class="modal-header">
            <div>
                <h3 class="modal-title" id="cellModalTitle">Daily Reconciliation Detail</h3>
                <p class="modal-subtitle" id="cellModalSubtitle">School & Date Details</p>
            </div>
            <button class="modal-close-btn" type="button" onclick="closeModalById('cellDetailModal')">&times;</button>
        </div>
        <div class="modal-body" id="cellModalBody" style="padding: 20px;">
            <!-- Content injected dynamically -->
        </div>
        <div class="modal-footer" style="padding: 12px 20px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="closeModalById('cellDetailModal')">Close</button>
        </div>
    </div>
</div>

<!-- ==========================================================================
     PRINT ONLY HEADER (VISIBLE ONLY IN PRINT / PDF VIEW)
     ========================================================================== -->
<div id="printOnlyHeader" class="print-only" style="display: none;">
    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #1e3a8a; padding-bottom: 10px; margin-bottom: 14px;">
        <div>
            <h1 style="font-size: 20px; font-weight: 800; color: #1e3a8a; margin: 0;">School Reconciliation Monthly Report</h1>
            <div id="printMonthLabel" style="font-size: 14px; font-weight: 700; color: #0f172a; margin-top: 2px;"></div>
        </div>
        <div style="text-align: right; font-size: 11px; color: #64748b;">
            <div>Generated by: <strong><?= e($user['full_name'] ?: $user['username']) ?> (<?= e($user['role_name']) ?>)</strong></div>
            <div>Date: <strong><?= date('Y-m-d h:i A') ?></strong></div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
