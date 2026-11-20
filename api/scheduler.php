<?php
/**
 * Reconciliation Scheduler & Task Queue Processor
 * School Reconciliation Management System
 * Handles Morning and Evening session task queues and cross-session verification
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Authentication: Support logged-in admin/superadmin, or Vercel cron header / cron key
$isVercelCron = !empty($_SERVER['HTTP_X_VERCEL_CRON']);
$cronKey = $_GET['cron_key'] ?? $_POST['cron_key'] ?? '';
$isAuthorizedKey = (!empty($cronKey) && $cronKey === (getenv('CRON_SECRET') ?: 'reconsilation_cron_2026'));

if (!$isVercelCron && !$isAuthorizedKey) {
    require_login();
    require_permission('manage_reconciliation', true);
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'process_session_queue';
$targetDate = $_GET['date'] ?? $_POST['date'] ?? date('Y-m-d');
if ($targetDate > date('Y-m-d')) {
    $targetDate = date('Y-m-d');
}

$pdo = getDBConnection();
ensure_warning_tracking_table($pdo);

try {
    switch ($action) {
        /**
         * 1. Morning Session Task Queue Processor
         * Ingests, verifies, and resolves morning session tasks so they do not hang until evening
         */
        case 'run_morning_queue':
            $result = processMorningQueue($pdo, $targetDate);
            log_activity('SCHEDULER_MORNING_QUEUE', "Processed Morning Queue for {$targetDate}: {$result['resolved_count']} resolved.");
            json_response([
                'success' => true,
                'session' => SESSION_AM,
                'date' => $targetDate,
                'data' => $result
            ]);
            break;

        /**
         * 2. Evening Session Task Queue Processor
         * Verifies morning session status, bypasses duplicate warnings for morning-validated records,
         * and updates evening session tasks
         */
        case 'run_evening_queue':
            $result = processEveningQueue($pdo, $targetDate);
            log_activity('SCHEDULER_EVENING_QUEUE', "Processed Evening Queue for {$targetDate}: {$result['resolved_count']} resolved, {$result['bypassed_warnings']} warnings bypassed.");
            json_response([
                'success' => true,
                'session' => SESSION_PM,
                'date' => $targetDate,
                'data' => $result
            ]);
            break;

        /**
         * 3. Automatic Session Queue Processor
         * Dynamically detects the current window (Morning vs. Evening) based on schedule configuration
         * and routes to the appropriate task processor
         */
        case 'process_session_queue':
            $currentWindow = get_current_scheduled_session();
            if ($currentWindow === SESSION_AM) {
                $queueResult = processMorningQueue($pdo, $targetDate);
            } else {
                $queueResult = processEveningQueue($pdo, $targetDate);
            }
            json_response([
                'success' => true,
                'dispatched_session' => $currentWindow,
                'date' => $targetDate,
                'data' => $queueResult
            ]);
            break;

        /**
         * 4. Scheduler Diagnostics & Status
         */
        case 'get_scheduler_status':
            $amStart = get_setting('am_start_time', '07:00');
            $amEnd = get_setting('am_end_time', '13:00');
            $pmStart = get_setting('pm_start_time', '14:00');
            $pmEnd = get_setting('pm_end_time', '19:00');
            $activeWindow = get_current_scheduled_session();

            $amSummary = get_reconciliation_summary($targetDate, SESSION_AM);
            $pmSummary = get_reconciliation_summary($targetDate, SESSION_PM);

            // Fetch recent scheduler logs
            $logStmt = $pdo->prepare("
                SELECT action, details, created_at 
                FROM activity_logs 
                WHERE action LIKE 'SCHEDULER_%' 
                ORDER BY id DESC LIMIT 10
            ");
            $logStmt->execute();
            $recentLogs = $logStmt->fetchAll();

            json_response([
                'success' => true,
                'server_time' => date('Y-m-d H:i:s'),
                'active_session_window' => $activeWindow,
                'schedule_config' => [
                    'am_window' => "{$amStart} - {$amEnd}",
                    'pm_window' => "{$pmStart} - {$pmEnd}"
                ],
                'target_date' => $targetDate,
                'morning_summary' => $amSummary,
                'evening_summary' => $pmSummary,
                'recent_logs' => $recentLogs
            ]);
            break;

        default:
            json_response(['success' => false, 'message' => 'Invalid scheduler action.'], 400);
    }
} catch (Exception $e) {
    error_log("Scheduler Error: " . $e->getMessage());
    json_response(['success' => false, 'message' => $e->getMessage()], 500);
}

/**
 * Process Morning Task Queue
 * Ensures tasks submitted in the morning window are resolved in the morning session
 */
function processMorningQueue(PDO $pdo, string $date): array {
    $stmt = $pdo->prepare("
        SELECT r.*, s.name AS school_name, s.code AS school_code
        FROM reconciliation_records r
        JOIN schools s ON s.id = r.school_id
        WHERE r.record_date = :date
          AND r.session = 'AM'
    ");
    $stmt->execute([':date' => $date]);
    $records = $stmt->fetchAll();

    $resolvedCount = 0;
    $processedCount = 0;

    foreach ($records as $rec) {
        $processedCount++;
        // If task has entered details (payment, recon issue, refund, duplicate) but is_completed is 0,
        // and is not blocked by an active urgent escalation, resolve in the morning session!
        if ((int)$rec['is_completed'] === 0 && (int)$rec['is_urgent'] === 0) {
            $hasValidDetails = (
                in_array($rec['payment_status'], ['NO_PAYMENT', 'PAYMENT_SUCCESS']) ||
                (!empty($rec['issue_reference']) && mb_strlen($rec['issue_reference']) <= 10) ||
                !empty($rec['refund_reference']) ||
                !empty($rec['duplicate_receipt_no'])
            );

            if ($hasValidDetails) {
                $upd = $pdo->prepare("
                    UPDATE reconciliation_records 
                    SET is_completed = 1,
                        completed_at = COALESCE(completed_at, NOW()),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $upd->execute([$rec['id']]);
                $resolvedCount++;
            }
        }
    }

    return [
        'session' => SESSION_AM,
        'records_processed' => $processedCount,
        'resolved_count' => $resolvedCount,
        'message' => "Morning session queue processed. {$resolvedCount} tasks resolved."
    ];
}

/**
 * Process Evening Task Queue
 * Cross-references Morning records, bypasses duplicate warnings for morning-validated records,
 * and updates evening session tasks
 */
function processEveningQueue(PDO $pdo, string $date): array {
    $stmt = $pdo->prepare("
        SELECT r.*, s.name AS school_name, s.code AS school_code
        FROM reconciliation_records r
        JOIN schools s ON s.id = r.school_id
        WHERE r.record_date = :date
          AND r.session = 'PM'
    ");
    $stmt->execute([':date' => $date]);
    $records = $stmt->fetchAll();

    $resolvedCount = 0;
    $bypassedWarnings = 0;
    $processedCount = 0;

    foreach ($records as $rec) {
        $processedCount++;
        $unique_id = $rec['issue_reference'] ?: ($rec['refund_reference'] ?: $rec['duplicate_receipt_no']);

        // Check morning session validation
        $morningValid = check_morning_session_validation($pdo, $rec['school_id'], $date, $unique_id);

        if ($morningValid) {
            $bypassedWarnings++;
            // If flagged as urgent, resolve false discrepancy because morning was already validated
            if ((int)$rec['is_urgent'] === 1) {
                $upd = $pdo->prepare("
                    UPDATE reconciliation_records 
                    SET is_urgent = 0,
                        is_completed = 1,
                        overall_remark = 'Verified via Morning Session',
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $upd->execute([$rec['id']]);

                // Resolve urgent_cases entry if exists
                $resUrg = $pdo->prepare("
                    UPDATE urgent_cases 
                    SET status = 'Resolved',
                        description = 'Auto-resolved: Validated in Morning session',
                        resolved_at = NOW()
                    WHERE record_id = ?
                ");
                $resUrg->execute([$rec['id']]);
                $resolvedCount++;
            }
        }
    }

    return [
        'session' => SESSION_PM,
        'records_processed' => $processedCount,
        'bypassed_warnings' => $bypassedWarnings,
        'resolved_count' => $resolvedCount,
        'message' => "Evening session queue processed. {$bypassedWarnings} duplicate warnings bypassed."
    ];
}
