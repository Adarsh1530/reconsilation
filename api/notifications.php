<?php
/**
 * Super Admin Notifications API
 * School Reconciliation Management System
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

$user = current_user();
$pdo = getDBConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        /**
         * Fetch active notifications (accessible to all authenticated users)
         */
        case 'get_active':
            $stmt = $pdo->prepare("
                SELECT n.*, u.username AS author_name, u.full_name AS author_full_name
                FROM system_notifications n
                JOIN users u ON n.created_by = u.id
                WHERE n.is_active = 1
                ORDER BY n.created_at DESC
                LIMIT 10
            ");
            $stmt->execute();
            $notifications = $stmt->fetchAll();
            json_response(['success' => true, 'data' => $notifications]);
            break;

        /**
         * Fetch all notifications (Super Admin only)
         */
        case 'get_all':
            require_role(ROLE_SUPER_ADMIN, true);
            $stmt = $pdo->prepare("
                SELECT n.*, u.username AS author_name, u.full_name AS author_full_name
                FROM system_notifications n
                JOIN users u ON n.created_by = u.id
                ORDER BY n.created_at DESC
            ");
            $stmt->execute();
            $notifications = $stmt->fetchAll();
            json_response(['success' => true, 'data' => $notifications]);
            break;

        /**
         * Publish a new notification (Super Admin only)
         */
        case 'create':
            require_csrf();
            require_role(ROLE_SUPER_ADMIN, true);

            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $type = $_POST['type'] ?? 'info';

            if (!in_array($type, ['info', 'warning', 'urgent', 'success'])) {
                $type = 'info';
            }

            if (empty($title) || empty($message)) {
                json_response(['success' => false, 'message' => 'Please provide both title and announcement message.'], 400);
            }

            $stmt = $pdo->prepare("
                INSERT INTO system_notifications (title, message, type, is_active, created_by)
                VALUES (?, ?, ?, 1, ?)
            ");
            $stmt->execute([$title, $message, $type, $user['id']]);
            $newId = $pdo->lastInsertId();

            log_activity('NOTIFICATION_PUBLISHED', "Super Admin published notification #{$newId}: {$title}");

            json_response([
                'success' => true,
                'message' => 'Notification published successfully to all users.',
                'id' => $newId
            ]);
            break;

        /**
         * Toggle active / inactive status (Super Admin only)
         */
        case 'toggle_status':
            require_csrf();
            require_role(ROLE_SUPER_ADMIN, true);

            $id = (int)($_POST['id'] ?? 0);
            $isActive = (int)($_POST['is_active'] ?? 0);

            if (!$id) {
                json_response(['success' => false, 'message' => 'Notification ID is required.'], 400);
            }

            $stmt = $pdo->prepare("UPDATE system_notifications SET is_active = ? WHERE id = ?");
            $stmt->execute([$isActive ? 1 : 0, $id]);

            log_activity('NOTIFICATION_STATUS_CHANGED', "Super Admin updated notification #{$id} status to {$isActive}");

            json_response(['success' => true, 'message' => 'Notification status updated.']);
            break;

        /**
         * Delete notification (Super Admin only)
         */
        case 'delete':
            require_csrf();
            require_role(ROLE_SUPER_ADMIN, true);

            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                json_response(['success' => false, 'message' => 'Notification ID is required.'], 400);
            }

            $stmt = $pdo->prepare("DELETE FROM system_notifications WHERE id = ?");
            $stmt->execute([$id]);

            log_activity('NOTIFICATION_DELETED', "Super Admin deleted notification #{$id}");

            json_response(['success' => true, 'message' => 'Notification deleted successfully.']);
            break;

        default:
            json_response(['success' => false, 'message' => 'Invalid action.'], 400);
    }
} catch (Exception $e) {
    json_response(['success' => false, 'message' => 'Server error: ' . $e->getMessage()], 500);
}
