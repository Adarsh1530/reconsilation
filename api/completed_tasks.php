<?php
/**
 * Completed Tasks API Endpoint
 * School Reconciliation Management System
 * Accessible to ALL authenticated users / roles
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'get_completed';
$user = current_user();

try {
    $pdo = getDBConnection();

    switch ($action) {
        /**
         * Fetch all completed reconciliation tasks with filters
         */
        case 'get_completed':
            $date = $_GET['date'] ?? '';
            $session = $_GET['session'] ?? '';
            $search = trim($_GET['search'] ?? '');
            $user_id = (int)($_GET['user_id'] ?? 0);

            $sql = "
                SELECT 
                    r.id,
                    r.school_id,
                    r.record_date,
                    r.session,
                    r.payment_status,
                    r.reconciliation_status,
                    r.issue_reference,
                    r.refund_status,
                    r.refund_reference,
                    r.duplicate_receipt_no,
                    r.duplicate_receipt_desc,
                    r.overall_remark,
                    r.custom_remark,
                    r.is_completed,
                    r.is_urgent,
                    r.completed_by,
                    r.completed_at,
                    r.updated_at,
                    s.name AS school_name,
                    s.code AS school_code,
                    COALESCE(cu.username, u.username, 'System') AS completed_by_user,
                    COALESCE(cu.full_name, u.full_name, 'System') AS completed_by_name,
                    COALESCE(cr.name, ur.name, 'User') AS completed_by_role,
                    uc.status AS urgent_case_status,
                    ru.username AS urgent_resolved_by_user,
                    ru.full_name AS urgent_resolved_by_name,
                    uc.resolved_at AS urgent_resolved_at
                FROM reconciliation_records r
                JOIN schools s ON s.id = r.school_id
                LEFT JOIN users cu ON r.completed_by = cu.id
                LEFT JOIN roles cr ON cu.role_id = cr.id
                LEFT JOIN users u ON r.updated_by = u.id
                LEFT JOIN roles ur ON u.role_id = ur.id
                LEFT JOIN urgent_cases uc ON uc.record_id = r.id
                LEFT JOIN users ru ON uc.resolved_by = ru.id
                WHERE r.is_completed = 1
            ";

            $params = [];

            if (!empty($date)) {
                $sql .= " AND r.record_date = :record_date";
                $params[':record_date'] = $date;
            }

            if (!empty($session) && in_array($session, [SESSION_AM, SESSION_PM])) {
                $sql .= " AND r.session = :session";
                $params[':session'] = $session;
            }

            if ($user_id > 0) {
                $sql .= " AND (r.completed_by = :uid OR (r.completed_by IS NULL AND r.updated_by = :uid2))";
                $params[':uid'] = $user_id;
                $params[':uid2'] = $user_id;
            }

            if (!empty($search)) {
                $sql .= " AND (s.name LIKE :search OR s.code LIKE :search2 OR r.issue_reference LIKE :search3 OR r.refund_reference LIKE :search4 OR r.duplicate_receipt_no LIKE :search5)";
                $params[':search'] = "%{$search}%";
                $params[':search2'] = "%{$search}%";
                $params[':search3'] = "%{$search}%";
                $params[':search4'] = "%{$search}%";
                $params[':search5'] = "%{$search}%";
            }

            $sql .= " ORDER BY COALESCE(r.completed_at, r.updated_at) DESC, r.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch distinct users who have completed/updated tasks for the user filter
            $uStmt = $pdo->query("
                SELECT DISTINCT u.id, u.username, u.full_name 
                FROM users u 
                JOIN reconciliation_records r ON (r.completed_by = u.id OR r.updated_by = u.id)
                WHERE r.is_completed = 1
                ORDER BY u.username ASC
            ");
            $usersList = $uStmt->fetchAll(PDO::FETCH_ASSOC);

            // Overall stats
            $totalCount = count($tasks);
            $todayCount = 0;
            $issuesCount = 0;
            $resolvedCount = 0;
            $todayDate = date('Y-m-d');

            foreach ($tasks as $t) {
                if ($t['record_date'] === $todayDate || (isset($t['completed_at']) && strpos($t['completed_at'], $todayDate) === 0)) {
                    $todayCount++;
                }
                if (!empty($t['issue_reference'])) {
                    $issuesCount++;
                }
                if ($t['urgent_case_status'] === 'Resolved' || (isset($t['overall_remark']) && strpos($t['overall_remark'], 'Resolved by') === 0)) {
                    $resolvedCount++;
                }
            }

            json_response([
                'success' => true,
                'data' => [
                    'tasks' => $tasks,
                    'users' => $usersList,
                    'summary' => [
                        'total' => $totalCount,
                        'today' => $todayCount,
                        'issues' => $issuesCount,
                        'resolved' => $resolvedCount
                    ]
                ]
            ]);
            break;

        /**
         * Real-time count of completed/recently updated tasks for sidebar badge
         */
        case 'get_badge_count':
            $stmt = $pdo->query("
                SELECT COUNT(*) 
                FROM reconciliation_records 
                WHERE is_completed = 1 
                  AND (record_date = CURRENT_DATE() OR DATE(completed_at) = CURRENT_DATE() OR DATE(updated_at) = CURRENT_DATE())
            ");
            $cnt = (int)$stmt->fetchColumn();
            if ($cnt === 0) {
                $stmt2 = $pdo->query("SELECT COUNT(*) FROM reconciliation_records WHERE is_completed = 1");
                $cnt = (int)$stmt2->fetchColumn();
            }
            json_response(['success' => true, 'count' => $cnt]);
            break;

        default:
            json_response(['success' => false, 'message' => 'Invalid action.'], 400);
    }
} catch (Exception $e) {
    error_log("Completed Tasks API Error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
