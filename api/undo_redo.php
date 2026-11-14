<?php
/**
 * Undo / Redo API Endpoint
 * Exclusively for Super Admin / authorized correction managers
 */

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_role(ROLE_SUPER_ADMIN, true);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = getDBConnection();
$user = current_user();

try {
    switch ($action) {
        /**
         * Fetch action history list
         */
        case 'list':
            $limit = min(100, max(10, (int)($_GET['limit'] ?? 50)));
            $session = $_GET['session'] ?? '';
            $school_id = (int)($_GET['school_id'] ?? 0);

            $sql = "
                SELECT 
                    h.*,
                    s.name AS school_name,
                    s.code AS school_code
                FROM reconciliation_history h
                JOIN schools s ON s.id = h.school_id
                WHERE 1=1
            ";
            $params = [];

            if (!empty($session) && in_array($session, [SESSION_AM, SESSION_PM])) {
                $sql .= " AND h.session = ?";
                $params[] = $session;
            }
            if ($school_id > 0) {
                $sql .= " AND h.school_id = ?";
                $params[] = $school_id;
            }

            $sql .= " ORDER BY h.id DESC LIMIT " . $limit;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $history = $stmt->fetchAll();

            json_response(['success' => true, 'data' => $history]);
            break;

        /**
         * Execute Undo Action
         * Reverts record to its previous state
         */
        case 'undo':
            require_csrf();
            $history_id = (int)($_POST['history_id'] ?? 0);
            if (!$history_id) {
                json_response(['success' => false, 'message' => 'History ID is required.'], 400);
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM reconciliation_history WHERE id = ? FOR UPDATE");
            $stmt->execute([$history_id]);
            $item = $stmt->fetch();

            if (!$item) {
                $pdo->rollBack();
                json_response(['success' => false, 'message' => 'History record not found.'], 404);
            }

            if ($item['is_undone'] == 1 || $item['can_undo'] == 0) {
                $pdo->rollBack();
                json_response(['success' => false, 'message' => 'This action has already been undone or cannot be reverted.'], 400);
            }

            $prev = json_decode($item['previous_state'], true);
            if (!$prev || !is_array($prev)) {
                $pdo->rollBack();
                json_response(['success' => false, 'message' => 'Invalid historical state data.'], 500);
            }

            // Restore record fields from previous_state
            $upd = $pdo->prepare("
                UPDATE reconciliation_records
                SET payment_status = :payment_status,
                    reconciliation_status = :reconciliation_status,
                    issue_reference = :issue_reference,
                    refund_status = :refund_status,
                    refund_reference = :refund_reference,
                    overall_remark = :overall_remark,
                    is_completed = :is_completed,
                    is_urgent = :is_urgent,
                    updated_by = :uid,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $upd->execute([
                ':payment_status'        => $prev['payment_status'] ?? 'NONE',
                ':reconciliation_status' => $prev['reconciliation_status'] ?? 'NONE',
                ':issue_reference'       => $prev['issue_reference'] ?? null,
                ':refund_status'         => $prev['refund_status'] ?? 'NONE',
                ':refund_reference'      => $prev['refund_reference'] ?? null,
                ':overall_remark'        => $prev['overall_remark'] ?? 'Pending',
                ':is_completed'          => $prev['is_completed'] ?? 0,
                ':is_urgent'             => $prev['is_urgent'] ?? 0,
                ':uid'                   => $user['id'],
                ':id'                    => $item['record_id']
            ]);

            // Mark this history record as undone, can now be redone
            $h_upd = $pdo->prepare("
                UPDATE reconciliation_history 
                SET is_undone = 1, can_undo = 0, can_redo = 1 
                WHERE id = ?
            ");
            $h_upd->execute([$history_id]);

            $pdo->commit();
            log_activity('UNDO_ACTION', "Super Admin undone action ID #{$history_id} on record #{$item['record_id']} (School #{$item['school_id']})");

            json_response([
                'success' => true,
                'message' => 'Action successfully undone. Previous state has been restored.',
                'school_id' => $item['school_id'],
                'session' => $item['session'],
                'date' => $item['record_date']
            ]);
            break;

        /**
         * Execute Redo Action
         * Re-applies the new_state
         */
        case 'redo':
            require_csrf();
            $history_id = (int)($_POST['history_id'] ?? 0);
            if (!$history_id) {
                json_response(['success' => false, 'message' => 'History ID is required.'], 400);
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM reconciliation_history WHERE id = ? FOR UPDATE");
            $stmt->execute([$history_id]);
            $item = $stmt->fetch();

            if (!$item) {
                $pdo->rollBack();
                json_response(['success' => false, 'message' => 'History record not found.'], 404);
            }

            if ($item['can_redo'] == 0 || $item['is_undone'] == 0) {
                $pdo->rollBack();
                json_response(['success' => false, 'message' => 'This action cannot be redone.'], 400);
            }

            $next = json_decode($item['new_state'], true);
            if (!$next || !is_array($next)) {
                $pdo->rollBack();
                json_response(['success' => false, 'message' => 'Invalid future state data.'], 500);
            }

            // Restore record fields from new_state
            $upd = $pdo->prepare("
                UPDATE reconciliation_records
                SET payment_status = :payment_status,
                    reconciliation_status = :reconciliation_status,
                    issue_reference = :issue_reference,
                    refund_status = :refund_status,
                    refund_reference = :refund_reference,
                    overall_remark = :overall_remark,
                    is_completed = :is_completed,
                    is_urgent = :is_urgent,
                    updated_by = :uid,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $upd->execute([
                ':payment_status'        => $next['payment_status'] ?? 'NONE',
                ':reconciliation_status' => $next['reconciliation_status'] ?? 'NONE',
                ':issue_reference'       => $next['issue_reference'] ?? null,
                ':refund_status'         => $next['refund_status'] ?? 'NONE',
                ':refund_reference'      => $next['refund_reference'] ?? null,
                ':overall_remark'        => $next['overall_remark'] ?? 'Pending',
                ':is_completed'          => $next['is_completed'] ?? 0,
                ':is_urgent'             => $next['is_urgent'] ?? 0,
                ':uid'                   => $user['id'],
                ':id'                    => $item['record_id']
            ]);

            // Mark this history record as active again
            $h_upd = $pdo->prepare("
                UPDATE reconciliation_history 
                SET is_undone = 0, can_undo = 1, can_redo = 0 
                WHERE id = ?
            ");
            $h_upd->execute([$history_id]);

            $pdo->commit();
            log_activity('REDO_ACTION', "Super Admin redone action ID #{$history_id} on record #{$item['record_id']} (School #{$item['school_id']})");

            json_response([
                'success' => true,
                'message' => 'Action successfully redone. State has been re-applied.',
                'school_id' => $item['school_id'],
                'session' => $item['session'],
                'date' => $item['record_date']
            ]);
            break;

        default:
            json_response(['success' => false, 'message' => 'Unknown Undo/Redo action.'], 404);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Undo/Redo API Error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Error processing operation: ' . $e->getMessage()], 500);
}
