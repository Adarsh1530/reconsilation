<?php
/**
 * Reconciliation API Endpoint
 * Handles AM/PM records, payment toggles, issue references, refunds, and workflow locking
 */

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$pdo = getDBConnection();
$user = current_user();

try {
    // Restrict all actions to past and present dates (future dates not allowed)
    $inputDate = $_GET['date'] ?? $_POST['date'] ?? '';
    if (!empty($inputDate) && $inputDate > date('Y-m-d')) {
        json_response(['success' => false, 'message' => 'Future dates cannot be selected. Please choose today or a past date.'], 400);
    }

    switch ($action) {
        /**
         * Fetch dashboard overview summary counts (Total, Pending, Completed)
         */
        case 'get_summary':
            $date = $_GET['date'] ?? date('Y-m-d');
            $session = $_GET['session'] ?? SESSION_AM;
            if (!in_array($session, [SESSION_AM, SESSION_PM])) {
                $session = SESSION_AM;
            }

            $summary = get_reconciliation_summary($date, $session);
            json_response(['success' => true, 'data' => $summary]);
            break;

        /**
         * Fetch all schools and their reconciliation status for selected date and session
         * Ensures AM and PM records are strictly isolated.
         */
        case 'get_records':
            $date = $_GET['date'] ?? date('Y-m-d');
            $session = $_GET['session'] ?? SESSION_AM;
            if (!in_array($session, [SESSION_AM, SESSION_PM])) {
                $session = SESSION_AM;
            }

            // Get all active schools and join the record for this specific date and session
            $sql = "
                SELECT 
                    s.id AS school_id,
                    s.code AS school_code,
                    s.name AS school_name,
                    s.contact_person,
                    s.phone,
                    s.email,
                    s.erp_url,
                    s.erp_login_url,
                    s.erp_username,
                    s.erp_password,
                    r.id AS record_id,
                    r.record_date,
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
                    COALESCE(r.is_urgent, 0) AS is_urgent,
                    r.completed_by,
                    cu.username AS completed_by_user,
                    cu.full_name AS completed_by_name,
                    r.completed_at,
                    r.updated_at,
                    u.username AS updated_by_user,
                    uc.id AS urgent_case_id,
                    COALESCE(uc.status, 'None') AS urgent_case_status,
                    uc.priority AS urgent_priority,
                    uc.resolved_at AS urgent_resolved_at,
                    ru.username AS urgent_resolved_by_user
                FROM schools s
                LEFT JOIN reconciliation_records r 
                    ON s.id = r.school_id 
                    AND r.record_date = :record_date 
                    AND r.session = :session
                LEFT JOIN users u 
                    ON r.updated_by = u.id
                LEFT JOIN users cu 
                    ON r.completed_by = cu.id
                LEFT JOIN urgent_cases uc 
                    ON uc.record_id = r.id
                LEFT JOIN users ru 
                    ON uc.resolved_by = ru.id
                WHERE s.status = 'active'
                ORDER BY s.sort_order ASC, s.id ASC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':record_date' => $date, ':session' => $session]);
            $schools = $stmt->fetchAll();

            $summary = get_reconciliation_summary($date, $session);

            json_response([
                'success' => true,
                'data' => [
                    'date' => $date,
                    'session' => $session,
                    'summary' => $summary,
                    'records' => $schools
                ]
            ]);
            break;

        /**
         * Save "No Payment" option
         * Automatically disables: Payment Success, Reconciliation, Refund.
         */
        case 'save_no_payment':
            require_csrf();
            require_permission('manage_payment_status', true);

            $school_id = (int)($_POST['school_id'] ?? 0);
            $date = $_POST['date'] ?? date('Y-m-d');
            $session = $_POST['session'] ?? SESSION_AM;

            if (!$school_id || !in_array($session, [SESSION_AM, SESSION_PM])) {
                json_response(['success' => false, 'message' => 'Invalid school or session parameter.'], 400);
            }

            $pdo->beginTransaction();

            // Fetch or create record
            $rec = get_or_create_record($pdo, $school_id, $date, $session);
            $prev_state = json_encode($rec);

            $comp_by = !empty($rec['completed_by']) ? $rec['completed_by'] : $user['id'];
            $comp_at = !empty($rec['completed_at']) ? $rec['completed_at'] : date('Y-m-d H:i:s');

            // Mutually exclusive: disables payment success, recon issue, refund
            $upd = $pdo->prepare("
                UPDATE reconciliation_records 
                SET payment_status = 'NO_PAYMENT',
                    reconciliation_status = 'NONE',
                    issue_reference = NULL,
                    refund_status = 'NONE',
                    refund_reference = NULL,
                    overall_remark = 'No Payment',
                    is_completed = 1,
                    completed_by = :completed_by,
                    completed_at = :completed_at,
                    updated_by = :uid,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $upd->execute([
                ':completed_by' => $comp_by,
                ':completed_at' => $comp_at,
                ':uid' => $user['id'],
                ':id' => $rec['id']
            ]);

            $new_rec = get_or_create_record($pdo, $school_id, $date, $session);
            $new_state = json_encode($new_rec);

            // Record in history for Super Admin Undo/Redo
            record_history($pdo, $rec['id'], $school_id, $date, $session, 'NO_PAYMENT', $prev_state, $new_state, $user);

            $pdo->commit();
            log_activity('RECON_NO_PAYMENT', "Marked No Payment for School ID #{$school_id} in {$session} ({$date})");

            json_response([
                'success' => true,
                'message' => 'No Payment recorded successfully. Workflow completed for this school.',
                'record' => $new_rec,
                'summary' => get_reconciliation_summary($date, $session)
            ]);
            break;

        /**
         * Save "Payment Success" option
         * Automatically disables: No Payment, Reconciliation, Refund.
         */
        case 'save_payment_success':
            require_csrf();
            require_permission('manage_payment_status', true);

            $school_id = (int)($_POST['school_id'] ?? 0);
            $date = $_POST['date'] ?? date('Y-m-d');
            $session = $_POST['session'] ?? SESSION_AM;

            if (!$school_id || !in_array($session, [SESSION_AM, SESSION_PM])) {
                json_response(['success' => false, 'message' => 'Invalid school or session parameter.'], 400);
            }

            $pdo->beginTransaction();

            // Fetch or create record
            $rec = get_or_create_record($pdo, $school_id, $date, $session);
            $prev_state = json_encode($rec);

            $comp_by = !empty($rec['completed_by']) ? $rec['completed_by'] : $user['id'];
            $comp_at = !empty($rec['completed_at']) ? $rec['completed_at'] : date('Y-m-d H:i:s');

            // Mutually exclusive: disables no payment, recon issue, refund
            $upd = $pdo->prepare("
                UPDATE reconciliation_records 
                SET payment_status = 'PAYMENT_SUCCESS',
                    reconciliation_status = 'NONE',
                    issue_reference = NULL,
                    refund_status = 'NONE',
                    refund_reference = NULL,
                    overall_remark = 'Payment Success',
                    is_completed = 1,
                    completed_by = :completed_by,
                    completed_at = :completed_at,
                    updated_by = :uid,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $upd->execute([
                ':completed_by' => $comp_by,
                ':completed_at' => $comp_at,
                ':uid' => $user['id'],
                ':id' => $rec['id']
            ]);

            $new_rec = get_or_create_record($pdo, $school_id, $date, $session);
            $new_state = json_encode($new_rec);

            record_history($pdo, $rec['id'], $school_id, $date, $session, 'PAYMENT_SUCCESS', $prev_state, $new_state, $user);

            $pdo->commit();
            log_activity('RECON_PAYMENT_SUCCESS', "Marked Payment Success for School ID #{$school_id} in {$session} ({$date})");

            json_response([
                'success' => true,
                'message' => 'Payment Success recorded successfully. Workflow completed for this school.',
                'record' => $new_rec,
                'summary' => get_reconciliation_summary($date, $session)
            ]);
            break;

        /**
         * Save Reconciliation Issue
         * Red button + input field (accepts letters and numbers)
         * Warning if > 10 chars, but does not block saving.
         */
        /**
         * Save Reconciliation Issue
         * Normal if up to 10.
         * If goes above 10 (characters or numerical count > 10):
         * Automatically escalates to Admin & Super Admin Panel with alert to all users.
         */
        case 'save_issue':
            require_csrf();
            require_permission('manage_reconciliation', true);

            $school_id = (int)($_POST['school_id'] ?? 0);
            $date = $_POST['date'] ?? date('Y-m-d');
            $session = $_POST['session'] ?? SESSION_AM;
            $issue_reference = trim($_POST['issue_reference'] ?? '');
            $is_urgent = (int)($_POST['is_urgent'] ?? 0);
            $urgent_priority = $_POST['urgent_priority'] ?? 'High';

            if (!$school_id || empty($issue_reference)) {
                json_response(['success' => false, 'message' => 'Please enter an issue reference or count.'], 400);
            }

            // Check if issue reference or count goes above 10
            $isAbove10 = (mb_strlen($issue_reference) > 10) || (is_numeric($issue_reference) && (float)$issue_reference > 10);
            if ($isAbove10) {
                $is_urgent = 1;
                $urgent_priority = 'High';
                $remark = 'Urgent Cases';
                $is_completed = 0; // Requires Admin or Super Admin resolution
            } else {
                $remark = 'Reconciliation Issue';
                $is_completed = 1;
            }

            $pdo->beginTransaction();

            $rec = get_or_create_record($pdo, $school_id, $date, $session);
            $prev_state = json_encode($rec);

            $comp_by = ($is_completed === 1) ? (!empty($rec['completed_by']) ? $rec['completed_by'] : $user['id']) : $rec['completed_by'];
            $comp_at = ($is_completed === 1) ? (!empty($rec['completed_at']) ? $rec['completed_at'] : date('Y-m-d H:i:s')) : $rec['completed_at'];

            $upd = $pdo->prepare("
                UPDATE reconciliation_records 
                SET reconciliation_status = 'ISSUE',
                    issue_reference = :ref,
                    payment_status = 'NONE',
                    overall_remark = :remark,
                    is_urgent = :is_urgent,
                    is_completed = :is_completed,
                    completed_by = :completed_by,
                    completed_at = :completed_at,
                    updated_by = :uid,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $upd->execute([
                ':ref' => $issue_reference,
                ':remark' => $remark,
                ':is_urgent' => $is_urgent,
                ':is_completed' => $is_completed,
                ':completed_by' => $comp_by,
                ':completed_at' => $comp_at,
                ':uid' => $user['id'],
                ':id' => $rec['id']
            ]);

            // If flagged as urgent (> 10 or manually flagged), create/update urgent_cases entry
            if ($is_urgent) {
                $urg_stmt = $pdo->prepare("
                    INSERT INTO urgent_cases (record_id, school_id, session, record_date, priority, issue_reference, status, flagged_by, resolved_by, resolved_at)
                    VALUES (:rec_id, :school_id, :session, :rec_date, :priority, :ref, 'Open', :flagged_by, NULL, NULL)
                    ON DUPLICATE KEY UPDATE 
                        priority = VALUES(priority),
                        issue_reference = VALUES(issue_reference),
                        status = 'Open',
                        resolved_by = NULL,
                        resolved_at = NULL
                ");
                $urg_stmt->execute([
                    ':rec_id' => $rec['id'],
                    ':school_id' => $school_id,
                    ':session' => $session,
                    ':rec_date' => $date,
                    ':priority' => $urgent_priority,
                    ':ref' => $issue_reference,
                    ':flagged_by' => $user['id']
                ]);
            }

            $new_rec = get_or_create_record($pdo, $school_id, $date, $session);
            $new_state = json_encode($new_rec);

            record_history($pdo, $rec['id'], $school_id, $date, $session, 'ISSUE', $prev_state, $new_state, $user);

            $pdo->commit();
            log_activity('RECON_ISSUE', "Recorded Reconciliation Issue for School ID #{$school_id}: {$issue_reference} (Escalated: " . ($isAbove10 ? 'YES' : 'NO') . ")");

            $msg = $isAbove10 
                ? "Issue count/reference ({$issue_reference}) exceeds 10! Automatically moved to Admin & Super Admin panel." 
                : "Reconciliation issue ({$issue_reference}) saved successfully.";

            json_response([
                'success' => true,
                'message' => $msg,
                'record' => $new_rec,
                'summary' => get_reconciliation_summary($date, $session)
            ]);
            break;

        /**
         * Resolve an urgent escalated case by Admin or Super Admin
         */
        case 'resolve_urgent':
            require_csrf();
            require_permission('access_urgent_cases', true);

            $school_id = (int)($_POST['school_id'] ?? 0);
            $date = $_POST['date'] ?? date('Y-m-d');
            $session = $_POST['session'] ?? SESSION_AM;
            $resolution_notes = trim($_POST['notes'] ?? 'Resolved by administrator');

            if (!$school_id || !in_array($session, [SESSION_AM, SESSION_PM])) {
                json_response(['success' => false, 'message' => 'Invalid school or session parameter.'], 400);
            }

            $pdo->beginTransaction();

            $rec = get_or_create_record($pdo, $school_id, $date, $session);
            $prev_state = json_encode($rec);

            // 1. Update urgent_cases
            $urgUpd = $pdo->prepare("
                UPDATE urgent_cases 
                SET status = 'Resolved',
                    resolved_by = :uid,
                    resolved_at = NOW(),
                    description = COALESCE(NULLIF(:notes, ''), description),
                    updated_at = NOW()
                WHERE record_id = :rec_id
            ");
            $urgUpd->execute([
                ':uid' => $user['id'],
                ':notes' => $resolution_notes,
                ':rec_id' => $rec['id']
            ]);

            // 2. Update reconciliation_records
            $remarkText = 'Resolved by ' . $user['username'];
            $recUpd = $pdo->prepare("
                UPDATE reconciliation_records 
                SET is_completed = 1,
                    overall_remark = :remark,
                    updated_by = :uid,
                    updated_at = NOW()
                WHERE id = :rec_id
            ");
            $recUpd->execute([
                ':remark' => $remarkText,
                ':uid' => $user['id'],
                ':rec_id' => $rec['id']
            ]);

            $new_rec = get_or_create_record($pdo, $school_id, $date, $session);
            $new_state = json_encode($new_rec);

            record_history($pdo, $rec['id'], $school_id, $date, $session, 'RESOLVED', $prev_state, $new_state, $user);

            $pdo->commit();
            log_activity('RECON_RESOLVED', "Urgent reconciliation for School ID #{$school_id} marked as Resolved by {$user['username']}");

            json_response([
                'success' => true,
                'message' => "Reconciliation issue successfully resolved by {$user['username']}.",
                'record' => $new_rec,
                'summary' => get_reconciliation_summary($date, $session)
            ]);
            break;

        /**
         * Save Refund
         * Light-orange button + input field (accepts letters and numbers)
         */
        case 'save_refund':
            require_csrf();
            require_permission('manage_refunds', true);

            $school_id = (int)($_POST['school_id'] ?? 0);
            $date = $_POST['date'] ?? date('Y-m-d');
            $session = $_POST['session'] ?? SESSION_AM;
            $refund_reference = trim($_POST['refund_reference'] ?? '');

            if (!$school_id || empty($refund_reference)) {
                json_response(['success' => false, 'message' => 'Please enter refund reference or transaction details.'], 400);
            }

            $pdo->beginTransaction();

            $rec = get_or_create_record($pdo, $school_id, $date, $session);
            $prev_state = json_encode($rec);

            $comp_by = !empty($rec['completed_by']) ? $rec['completed_by'] : $user['id'];
            $comp_at = !empty($rec['completed_at']) ? $rec['completed_at'] : date('Y-m-d H:i:s');

            $upd = $pdo->prepare("
                UPDATE reconciliation_records 
                SET refund_status = 'PROCESSED',
                    refund_reference = :ref,
                    payment_status = 'NONE',
                    overall_remark = 'Refund',
                    is_completed = 1,
                    completed_by = :completed_by,
                    completed_at = :completed_at,
                    updated_by = :uid,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $upd->execute([
                ':ref' => $refund_reference,
                ':completed_by' => $comp_by,
                ':completed_at' => $comp_at,
                ':uid' => $user['id'],
                ':id' => $rec['id']
            ]);

            $new_rec = get_or_create_record($pdo, $school_id, $date, $session);
            $new_state = json_encode($new_rec);

            record_history($pdo, $rec['id'], $school_id, $date, $session, 'REFUND', $prev_state, $new_state, $user);

            $pdo->commit();
            log_activity('RECON_REFUND', "Processed Refund for School ID #{$school_id}: {$refund_reference}");

            json_response([
                'success' => true,
                'message' => 'Refund processed and recorded successfully.',
                'record' => $new_rec,
                'summary' => get_reconciliation_summary($date, $session)
            ]);
            break;

        /**
         * Save Duplicate Receipt (Number and Description)
         * Available for all users, admin and super admin
         */
        case 'save_duplicate_receipt':
            require_csrf();
            require_permission('manage_reconciliation', true);

            $school_id = (int)($_POST['school_id'] ?? 0);
            $date = $_POST['date'] ?? date('Y-m-d');
            $session = $_POST['session'] ?? SESSION_AM;
            $receipt_no = trim($_POST['receipt_no'] ?? '');
            $receipt_desc = trim($_POST['receipt_desc'] ?? '');

            if (!$school_id || (empty($receipt_no) && empty($receipt_desc))) {
                json_response(['success' => false, 'message' => 'Please provide receipt number or description.'], 400);
            }

            $pdo->beginTransaction();

            $rec = get_or_create_record($pdo, $school_id, $date, $session);
            $prev_state = json_encode($rec);

            $upd = $pdo->prepare("
                UPDATE reconciliation_records 
                SET duplicate_receipt_no = :no,
                    duplicate_receipt_desc = :desc,
                    updated_by = :uid,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $upd->execute([
                ':no' => $receipt_no ?: null,
                ':desc' => $receipt_desc ?: null,
                ':uid' => $user['id'],
                ':id' => $rec['id']
            ]);

            $new_rec = get_or_create_record($pdo, $school_id, $date, $session);
            $new_state = json_encode($new_rec);

            record_history($pdo, $rec['id'], $school_id, $date, $session, 'DUPLICATE_RECEIPT', $prev_state, $new_state, $user);

            $pdo->commit();
            log_activity('RECON_DUP_RECEIPT', "Saved Duplicate Receipt for School ID #{$school_id}: No={$receipt_no}");

            json_response([
                'success' => true,
                'message' => 'Duplicate receipt saved successfully.',
                'record' => $new_rec,
                'summary' => get_reconciliation_summary($date, $session)
            ]);
            break;

        /**
         * Save Custom Remark (varchar)
         * Dedicated for school reconciliation notes / remarks
         */
        case 'save_remark':
            require_csrf();
            require_permission('manage_reconciliation', true);

            $school_id = (int)($_POST['school_id'] ?? 0);
            $date = $_POST['date'] ?? date('Y-m-d');
            $session = $_POST['session'] ?? SESSION_AM;
            $remark = trim($_POST['remark'] ?? '');

            if (!$school_id) {
                json_response(['success' => false, 'message' => 'School ID is required.'], 400);
            }

            $pdo->beginTransaction();

            $rec = get_or_create_record($pdo, $school_id, $date, $session);
            $prev_state = json_encode($rec);

            // If overall_remark is 'Pending', set to remark or keep existing status
            $overall = ($rec['overall_remark'] === 'Pending' && !empty($remark)) ? $remark : $rec['overall_remark'];

            $upd = $pdo->prepare("
                UPDATE reconciliation_records 
                SET custom_remark = :remark,
                    overall_remark = :overall,
                    updated_by = :uid,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $upd->execute([
                ':remark' => $remark,
                ':overall' => $overall,
                ':uid' => $user['id'],
                ':id' => $rec['id']
            ]);

            $new_rec = get_or_create_record($pdo, $school_id, $date, $session);
            $new_state = json_encode($new_rec);

            record_history($pdo, $rec['id'], $school_id, $date, $session, 'REMARK', $prev_state, $new_state, $user);

            $pdo->commit();
            log_activity('RECON_REMARK', "Saved custom remark for School ID #{$school_id}: {$remark}");

            json_response([
                'success' => true,
                'message' => 'Remark saved successfully.',
                'record' => $new_rec,
                'summary' => get_reconciliation_summary($date, $session)
            ]);
            break;

        /**
         * Mark school reconciliation as completed or revert to pending
         */
        case 'mark_completed':
            require_csrf();
            require_permission('manage_reconciliation', true);

            $school_id = (int)($_POST['school_id'] ?? 0);
            $date = $_POST['date'] ?? date('Y-m-d');
            $session = $_POST['session'] ?? SESSION_AM;
            $is_completed = isset($_POST['is_completed']) ? (int)$_POST['is_completed'] : 1;

            if (!$school_id || !in_array($session, [SESSION_AM, SESSION_PM])) {
                json_response(['success' => false, 'message' => 'Invalid parameters.'], 400);
            }

            $pdo->beginTransaction();

            $rec = get_or_create_record($pdo, $school_id, $date, $session);
            $prev_state = json_encode($rec);

            if ($is_completed === 1) {
                // If remark was 'Pending' or empty, set to 'Completed'
                $remark = ($rec['overall_remark'] === 'Pending' || empty($rec['overall_remark'])) ? 'Completed' : $rec['overall_remark'];

                $issue_ref = trim($_POST['issue_reference'] ?? '');
                $refund_ref = trim($_POST['refund_reference'] ?? '');
                $dup_no = trim($_POST['duplicate_receipt_no'] ?? '');
                $dup_desc = trim($_POST['duplicate_receipt_desc'] ?? '');
                $custom_rem = trim($_POST['custom_remark'] ?? '');

                $has_issue = !empty($issue_ref) ? 1 : 0;
                $has_refund = !empty($refund_ref) ? 1 : 0;
                $has_dup = !empty($dup_no) ? 1 : 0;
                $has_custom = !empty($custom_rem) ? 1 : 0;

                if ($has_issue) {
                    $remark = (mb_strlen($issue_ref) > 10 || (is_numeric($issue_ref) && (float)$issue_ref > 10)) ? 'Urgent Cases' : 'Reconciliation Issue';
                }

                $upd = $pdo->prepare("
                    UPDATE reconciliation_records 
                    SET is_completed = 1,
                        overall_remark = :remark,
                        reconciliation_status = CASE WHEN :has_issue = 1 THEN 'ISSUE' ELSE reconciliation_status END,
                        issue_reference = CASE WHEN :has_issue = 1 THEN :issue_ref ELSE issue_reference END,
                        refund_status = CASE WHEN :has_refund = 1 THEN 'PROCESSED' ELSE refund_status END,
                        refund_reference = CASE WHEN :has_refund = 1 THEN :refund_ref ELSE refund_reference END,
                        duplicate_receipt_no = CASE WHEN :has_dup = 1 THEN :dup_no ELSE duplicate_receipt_no END,
                        duplicate_receipt_desc = CASE WHEN :has_dup = 1 THEN :dup_desc ELSE duplicate_receipt_desc END,
                        custom_remark = CASE WHEN :has_custom = 1 THEN :custom_rem ELSE custom_remark END,
                        completed_by = :completed_by,
                        completed_at = NOW(),
                        updated_by = :updated_by,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $upd->execute([
                    ':remark' => $remark,
                    ':has_issue' => $has_issue,
                    ':issue_ref' => $issue_ref,
                    ':has_refund' => $has_refund,
                    ':refund_ref' => $refund_ref,
                    ':has_dup' => $has_dup,
                    ':dup_no' => $dup_no,
                    ':dup_desc' => $dup_desc,
                    ':has_custom' => $has_custom,
                    ':custom_rem' => $custom_rem,
                    ':completed_by' => $user['id'],
                    ':updated_by' => $user['id'],
                    ':id' => $rec['id']
                ]);
                $msg = 'School marked as Completed.';
                $act = 'COMPLETED';
            } else {
                // Revert to pending while preserving all stored reference numbers and data for re-editing
                $upd = $pdo->prepare("
                    UPDATE reconciliation_records 
                    SET is_completed = 0,
                        overall_remark = 'Pending',
                        completed_by = NULL,
                        completed_at = NULL,
                        updated_by = :uid,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $upd->execute([
                    ':uid' => $user['id'],
                    ':id' => $rec['id']
                ]);
                $msg = 'School reopened and marked as Pending for re-editing.';
                $act = 'REOPENED';
            }

            $new_rec = get_or_create_record($pdo, $school_id, $date, $session);
            $new_state = json_encode($new_rec);

            record_history($pdo, $rec['id'], $school_id, $date, $session, $act, $prev_state, $new_state, $user);

            $pdo->commit();
            log_activity('RECON_' . $act, "Updated completion status for School ID #{$school_id} to {$is_completed} in {$session} ({$date})");

            json_response([
                'success' => true,
                'message' => $msg,
                'record' => $new_rec,
                'summary' => get_reconciliation_summary($date, $session)
            ]);
            break;

        /**
         * Reset Reconciliation Record for a school back to Pending
         */
        case 'reset_record':
            require_csrf();
            require_permission('manage_reconciliation', true);

            $school_id = (int)($_POST['school_id'] ?? 0);
            $date = $_POST['date'] ?? date('Y-m-d');
            $session = $_POST['session'] ?? SESSION_AM;

            $pdo->beginTransaction();

            $rec = get_or_create_record($pdo, $school_id, $date, $session);
            $prev_state = json_encode($rec);

            $upd = $pdo->prepare("
                UPDATE reconciliation_records 
                SET payment_status = 'NONE',
                    reconciliation_status = 'NONE',
                    issue_reference = NULL,
                    refund_status = 'NONE',
                    refund_reference = NULL,
                    overall_remark = 'Pending',
                    is_completed = 0,
                    is_urgent = 0,
                    updated_by = :uid,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $upd->execute([':uid' => $user['id'], ':id' => $rec['id']]);

            $new_rec = get_or_create_record($pdo, $school_id, $date, $session);
            $new_state = json_encode($new_rec);

            record_history($pdo, $rec['id'], $school_id, $date, $session, 'RESET', $prev_state, $new_state, $user);

            $pdo->commit();
            log_activity('RECON_RESET', "Reset status for School ID #{$school_id} in {$session} ({$date})");

            json_response([
                'success' => true,
                'message' => 'Reconciliation record has been reset to Pending.',
                'record' => $new_rec,
                'summary' => get_reconciliation_summary($date, $session)
            ]);
            break;

        default:
            json_response(['success' => false, 'message' => 'Unknown API action.'], 404);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Reconciliation API Error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'An internal server error occurred: ' . $e->getMessage()], 500);
}

/**
 * Helper: Find existing reconciliation record or create initial Pending record
 */
function get_or_create_record(PDO $pdo, $school_id, $date, $session) {
    $stmt = $pdo->prepare("
        SELECT * FROM reconciliation_records 
        WHERE school_id = ? AND record_date = ? AND session = ?
        LIMIT 1
    ");
    $stmt->execute([$school_id, $date, $session]);
    $rec = $stmt->fetch();

    if (!$rec) {
        $ins = $pdo->prepare("
            INSERT INTO reconciliation_records (school_id, record_date, session, payment_status, reconciliation_status, refund_status, overall_remark, is_completed)
            VALUES (?, ?, ?, 'NONE', 'NONE', 'NONE', 'Pending', 0)
        ");
        $ins->execute([$school_id, $date, $session]);
        $id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("SELECT * FROM reconciliation_records WHERE id = ?");
        $stmt->execute([$id]);
        $rec = $stmt->fetch();
    }
    return $rec;
}

/**
 * Helper: Record action in history table for Super Admin Undo/Redo tracking
 */
function record_history(PDO $pdo, $record_id, $school_id, $date, $session, $action_type, $prev_state, $new_state, $user) {
    $stmt = $pdo->prepare("
        INSERT INTO reconciliation_history 
        (record_id, school_id, record_date, session, action_type, previous_state, new_state, user_id, username, can_undo, can_redo, is_undone)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 0)
    ");
    $stmt->execute([
        $record_id,
        $school_id,
        $date,
        $session,
        $action_type,
        $prev_state,
        $new_state,
        $user['id'],
        $user['username']
    ]);
}
