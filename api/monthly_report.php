<?php
/**
 * Monthly Reconciliation Report API
 * School Reconciliation Management System
 * Accessible to ALL authenticated roles (Super Admin, Admin, User/Staff)
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/xlsx_writer.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? 'get_monthly_data';

try {
    $pdo = getDBConnection();

    switch ($action) {
        case 'get_monthly_data':
            $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
            $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
            $session = $_GET['session'] ?? 'ALL';

            if ($month < 1 || $month > 12) $month = (int)date('m');
            if ($year < 2000 || $year > 2100) $year = (int)date('Y');

            $data = buildMonthlyReportData($pdo, $year, $month, $session);
            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            exit;

        case 'export_excel':
            $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
            $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
            $session = $_GET['session'] ?? 'ALL';

            if ($month < 1 || $month > 12) $month = (int)date('m');
            if ($year < 2000 || $year > 2100) $year = (int)date('Y');

            $data = buildMonthlyReportData($pdo, $year, $month, $session);

            // Construct Header row matching reference: ['school name', 1, 2, 3, ...]
            $headers = ['school name'];
            for ($d = 1; $d <= $data['days_in_month']; $d++) {
                $headers[] = $d;
            }

            // Construct Data rows: [ School Code, Day 1 cell text, Day 2 cell text, ... ]
            $rows = [];
            foreach ($data['matrix'] as $sRow) {
                $row = [];
                // First column: School Code (or School Name if code is empty)
                $row[] = !empty($sRow['school_code']) ? $sRow['school_code'] : $sRow['school_name'];

                for ($d = 1; $d <= $data['days_in_month']; $d++) {
                    $dayCell = $sRow['days'][$d] ?? null;
                    if ($dayCell && !empty($dayCell['formatted_text'])) {
                        $row[] = $dayCell['formatted_text'];
                    } else {
                        $row[] = '';
                    }
                }
                $rows[] = $row;
            }

            $tempFile = tempnam(sys_get_temp_dir(), 'recon_report_') . '.xlsx';
            $sheetTitle = date('F Y', strtotime("{$year}-{$month}-01"));

            if (!SimpleXlsxWriter::create($tempFile, $headers, $rows, $sheetTitle)) {
                throw new Exception('Failed to generate Excel file.');
            }

            $monthPad = str_pad($month, 2, '0', STR_PAD_LEFT);
            $fileName = "Reconciliation_Monthly_Report_{$year}_{$monthPad}.xlsx";

            // Clear output buffer and send headers
            if (ob_get_level()) ob_end_clean();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($tempFile));
            header('Cache-Control: max-age=0');
            header('Pragma: public');

            readfile($tempFile);
            @unlink($tempFile);
            exit;

        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action specified.'
            ]);
            exit;
    }

} catch (Exception $e) {
    error_log("Monthly Report API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
    exit;
}

/**
 * Helper to build all monthly data structure
 */
function buildMonthlyReportData(PDO $pdo, int $year, int $month, string $sessionFilter): array {
    $monthPad = str_pad($month, 2, '0', STR_PAD_LEFT);
    $startDate = "{$year}-{$monthPad}-01";
    $daysInMonth = (int)date('t', strtotime($startDate));
    $endDate = "{$year}-{$monthPad}-" . str_pad($daysInMonth, 2, '0', STR_PAD_LEFT);

    // 1. Fetch active schools
    $schoolStmt = $pdo->query("SELECT id, code, name FROM schools WHERE status = 'active' ORDER BY sort_order ASC, name ASC");
    $schools = $schoolStmt->fetchAll();

    // 2. Fetch reconciliation records for the month
    $sql = "
        SELECT 
            r.id,
            r.school_id,
            r.record_date,
            DAY(r.record_date) AS day_num,
            r.session,
            COALESCE(r.payment_status, 'NONE') AS payment_status,
            COALESCE(r.reconciliation_status, 'NONE') AS reconciliation_status,
            r.issue_reference,
            COALESCE(r.refund_status, 'NONE') AS refund_status,
            r.refund_reference,
            r.duplicate_receipt_no,
            r.duplicate_receipt_desc,
            COALESCE(r.overall_remark, 'Pending') AS overall_remark,
            r.custom_remark,
            COALESCE(r.is_completed, 0) AS is_completed,
            r.completed_at,
            r.updated_at,
            cu.full_name AS completed_by_name,
            cu.username AS completed_by_user,
            uu.username AS updated_by_user
        FROM reconciliation_records r
        LEFT JOIN users cu ON r.completed_by = cu.id
        LEFT JOIN users uu ON r.updated_by = uu.id
        WHERE r.record_date BETWEEN :start_date AND :end_date
    ";

    $params = [
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ];

    if ($sessionFilter === SESSION_AM || $sessionFilter === SESSION_PM) {
        $sql .= " AND r.session = :session";
        $params[':session'] = $sessionFilter;
    }

    $sql .= " ORDER BY r.record_date ASC, r.session ASC, r.id ASC";

    $recStmt = $pdo->prepare($sql);
    $recStmt->execute($params);
    $records = $recStmt->fetchAll();

    // Group records by school_id and day_num
    $recordsBySchoolDay = [];
    $totalCompleted = 0;
    $totalIssues = 0;
    $totalRefunds = 0;
    $totalDuplicates = 0;

    foreach ($records as $r) {
        $sId = (int)$r['school_id'];
        $dNum = (int)$r['day_num'];
        if (!isset($recordsBySchoolDay[$sId])) {
            $recordsBySchoolDay[$sId] = [];
        }
        if (!isset($recordsBySchoolDay[$sId][$dNum])) {
            $recordsBySchoolDay[$sId][$dNum] = [];
        }
        $recordsBySchoolDay[$sId][$dNum][] = $r;

        if ($r['is_completed'] == 1) {
            $totalCompleted++;
        }
        if ($r['reconciliation_status'] === 'ISSUE' && !empty($r['issue_reference'])) {
            $totalIssues++;
        }
        if ($r['refund_status'] === 'PROCESSED' && !empty($r['refund_reference'])) {
            $totalRefunds++;
        }
        if (!empty($r['duplicate_receipt_no'])) {
            $totalDuplicates++;
        }
    }

    // 3. Build days list
    $days = [];
    $todayStr = date('Y-m-d');
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $dPad = str_pad($d, 2, '0', STR_PAD_LEFT);
        $dateStr = "{$year}-{$monthPad}-{$dPad}";
        $ts = strtotime($dateStr);
        $dayOfWeek = date('D', $ts);
        $days[] = [
            'day' => $d,
            'date' => $dateStr,
            'day_name' => $dayOfWeek,
            'is_today' => ($dateStr === $todayStr),
            'is_weekend' => ($dayOfWeek === 'Sun' || $dayOfWeek === 'Sat')
        ];
    }

    // 4. Build Matrix
    $matrix = [];
    foreach ($schools as $sch) {
        $sId = (int)$sch['id'];
        $daysData = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $dayRecs = $recordsBySchoolDay[$sId][$d] ?? [];

            if (empty($dayRecs)) {
                $daysData[$d] = null;
                continue;
            }

            // Extract users, times, payment statuses, recon, refund, duplicate
            $users = [];
            $times = [];
            $paymentStatuses = [];
            $issues = [];
            $refunds = [];
            $duplicates = [];
            $remarks = [];

            foreach ($dayRecs as $dr) {
                // User
                $uName = !empty($dr['completed_by_name']) ? $dr['completed_by_name'] : (!empty($dr['completed_by_user']) ? $dr['completed_by_user'] : (!empty($dr['updated_by_user']) ? $dr['updated_by_user'] : ''));
                if ($uName && !in_array($uName, $users)) {
                    $users[] = $uName;
                }

                // Time
                $rawTime = $dr['completed_at'] ?: $dr['updated_at'];
                if ($rawTime) {
                    $tFormatted = date('h:i A', strtotime($rawTime));
                    if (!in_array($tFormatted, $times)) {
                        $times[] = $tFormatted;
                    }
                }

                // Payment Status: NO_PAYMENT -> Nil, PAYMENT_SUCCESS -> PS
                if ($dr['payment_status'] === 'NO_PAYMENT') {
                    if (!in_array('Nil', $paymentStatuses)) $paymentStatuses[] = 'Nil';
                } elseif ($dr['payment_status'] === 'PAYMENT_SUCCESS') {
                    if (!in_array('PS', $paymentStatuses)) $paymentStatuses[] = 'PS';
                }

                // Recon issues
                if ($dr['reconciliation_status'] === 'ISSUE' && !empty($dr['issue_reference'])) {
                    if (!in_array($dr['issue_reference'], $issues)) $issues[] = $dr['issue_reference'];
                }

                // Refunds
                if ($dr['refund_status'] === 'PROCESSED' && !empty($dr['refund_reference'])) {
                    if (!in_array($dr['refund_reference'], $refunds)) $refunds[] = $dr['refund_reference'];
                }

                // Duplicates (include description for report / PDF / Excel)
                if (!empty($dr['duplicate_receipt_no'])) {
                    $dupText = $dr['duplicate_receipt_no'];
                    if (!empty($dr['duplicate_receipt_desc'])) {
                        $dupText .= ' (' . trim($dr['duplicate_receipt_desc']) . ')';
                    }
                    if (!in_array($dupText, $duplicates)) {
                        $duplicates[] = $dupText;
                    }
                }

                // Custom remarks
                if (!empty($dr['custom_remark'])) {
                    if (!in_array($dr['custom_remark'], $remarks)) $remarks[] = $dr['custom_remark'];
                }
            }

            // Build reference formatted text (matching ref_report.xlsx layout)
            $textLines = [];
            $textLines[] = 'User: ' . (!empty($users) ? implode(', ', $users) : '-');
            $textLines[] = 'Time: ' . (!empty($times) ? implode(', ', $times) : '-');

            // Payment statuses (Nil, PS)
            if (!empty($paymentStatuses)) {
                foreach ($paymentStatuses as $ps) {
                    $textLines[] = $ps;
                }
            } else {
                $textLines[] = 'Nil';
            }

            // Recon line
            $textLines[] = 'Recon: ' . (!empty($issues) ? implode(', ', $issues) : '');

            // Refund line
            $textLines[] = 'Refund: ' . (!empty($refunds) ? implode(', ', $refunds) : '');

            // Duplicate line
            $textLines[] = 'Duplicate: ' . (!empty($duplicates) ? implode(', ', $duplicates) : '');

            // Custom remark if present
            if (!empty($remarks)) {
                $textLines[] = 'Remark: ' . implode(' | ', $remarks);
            }

            $formattedText = implode("\n", $textLines);

            $daysData[$d] = [
                'has_data' => true,
                'users' => $users,
                'times' => $times,
                'payment_statuses' => $paymentStatuses,
                'issues' => $issues,
                'refunds' => $refunds,
                'duplicates' => $duplicates,
                'remarks' => $remarks,
                'formatted_text' => $formattedText,
                'record_count' => count($dayRecs)
            ];
        }

        $matrix[] = [
            'school_id' => $sId,
            'school_code' => $sch['code'],
            'school_name' => $sch['name'],
            'days' => $daysData
        ];
    }

    return [
        'year' => $year,
        'month' => $month,
        'month_name' => date('F', strtotime($startDate)),
        'month_label' => date('F Y', strtotime($startDate)),
        'days_in_month' => $daysInMonth,
        'days' => $days,
        'session_filter' => $sessionFilter,
        'total_schools' => count($schools),
        'summary' => [
            'total_schools' => count($schools),
            'days_in_month' => $daysInMonth,
            'total_completed' => $totalCompleted,
            'total_issues' => $totalIssues,
            'total_refunds' => $totalRefunds,
            'total_duplicates' => $totalDuplicates
        ],
        'matrix' => $matrix
    ];
}
