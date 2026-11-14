<?php
/**
 * Urgent Cases API Endpoint
 * For Admin and Super Admin
 */

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_permission('access_urgent_cases', true);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = getDBConnection();
$user = current_user();

try {
    switch ($action) {
        /**
         * List urgent cases with filters
         */
        case 'list':
            $status = $_GET['status'] ?? '';
            $session = $_GET['session'] ?? '';

            $sql = "
                SELECT 
                    u.*,
                    s.name AS school_name,
                    s.code AS school_code,
                    s.phone AS school_phone,
                    f.username AS flagged_by_user,
                    r.username AS resolved_by_user,
                    rec.overall_remark,
                    rec.payment_status,
                    rec.reconciliation_status
                FROM urgent_cases u
                JOIN schools s ON s.id = u.school_id
                JOIN reconciliation_records rec ON rec.id = u.record_id
                LEFT JOIN users f ON f.id = u.flagged_by
                LEFT JOIN users r ON r.id = u.resolved_by
                WHERE 1=1
            ";
            $params = [];

            if (!empty($status)) {
                $sql .= " AND u.status = ?";
                $params[] = $status;
            }
            if (!empty($session) && in_array($session, [SESSION_AM, SESSION_PM])) {
                $sql .= " AND u.session = ?";
                $params[] = $session;
            }

            $sql .= " ORDER BY CASE u.status WHEN 'Open' THEN 1 WHEN 'In Progress' THEN 2 ELSE 3 END, u.created_at DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $cases = $stmt->fetchAll();

            json_response(['success' => true, 'data' => $cases]);
            break;

        /**
         * Update status of an urgent case
         */
        case 'update_status':
            require_csrf();
            $id = (int)($_POST['id'] ?? 0);
            $new_status = $_POST['status'] ?? '';
            $priority = $_POST['priority'] ?? '';
            $description = trim($_POST['description'] ?? '');

            if (!$id || !in_array($new_status, ['Open', 'In Progress', 'Resolved'])) {
                json_response(['success' => false, 'message' => 'Invalid case ID or status.'], 400);
            }

            $stmt = $pdo->prepare("SELECT * FROM urgent_cases WHERE id = ?");
            $stmt->execute([$id]);
            $case = $stmt->fetch();
            if (!$case) {
                json_response(['success' => false, 'message' => 'Urgent case not found.'], 404);
            }

            $resolved_by = ($new_status === 'Resolved') ? $user['id'] : null;
            $resolved_at = ($new_status === 'Resolved') ? date('Y-m-d H:i:s') : null;

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                UPDATE urgent_cases 
                SET status = :status,
                    priority = COALESCE(NULLIF(:priority, ''), priority),
                    description = COALESCE(NULLIF(:desc, ''), description),
                    resolved_by = :resolved_by,
                    resolved_at = :resolved_at,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':status' => $new_status,
                ':priority' => $priority,
                ':desc' => $description,
                ':resolved_by' => $resolved_by,
                ':resolved_at' => $resolved_at,
                ':id' => $id
            ]);

            // Synchronize with reconciliation_records
            if ($new_status === 'Resolved') {
                $recUpd = $pdo->prepare("
                    UPDATE reconciliation_records 
                    SET is_completed = 1,
                        overall_remark = :remark,
                        updated_by = :uid,
                        updated_at = NOW()
                    WHERE id = :rec_id
                ");
                $recUpd->execute([
                    ':remark' => 'Resolved by ' . $user['username'],
                    ':uid' => $user['id'],
                    ':rec_id' => $case['record_id']
                ]);
            } else {
                $recUpd = $pdo->prepare("
                    UPDATE reconciliation_records 
                    SET is_completed = 0,
                        overall_remark = 'Urgent Cases',
                        updated_by = :uid,
                        updated_at = NOW()
                    WHERE id = :rec_id
                ");
                $recUpd->execute([
                    ':uid' => $user['id'],
                    ':rec_id' => $case['record_id']
                ]);
            }

            $pdo->commit();

            log_activity('URGENT_CASE_UPDATED', "Urgent case #{$id} status changed to '{$new_status}' by {$user['username']}");

            json_response(['success' => true, 'message' => "Urgent case updated to {$new_status}."]);
            break;

        default:
            json_response(['success' => false, 'message' => 'Unknown Urgent Cases action.'], 404);
            break;
    }
} catch (Exception $e) {
    error_log("Urgent Cases API Error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
