<?php
/**
 * Privilege Management API Endpoint
 * Dedicated for Super Admin
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

try {
    switch ($action) {
        /**
         * Fetch full privilege matrix (roles, permissions, and active role-permission links)
         */
        case 'matrix':
            $roles = $pdo->query("SELECT id, name, slug, description FROM roles ORDER BY id ASC")->fetchAll();
            $permissions = $pdo->query("SELECT id, name, slug, category, description FROM permissions ORDER BY category ASC, id ASC")->fetchAll();
            
            $rp_stmt = $pdo->query("SELECT role_id, permission_id FROM role_permissions");
            $role_perms = [];
            while ($row = $rp_stmt->fetch()) {
                $role_perms[$row['role_id']][] = (int)$row['permission_id'];
            }

            json_response([
                'success' => true,
                'data' => [
                    'roles' => $roles,
                    'permissions' => $permissions,
                    'role_permissions' => $role_perms
                ]
            ]);
            break;

        /**
         * Toggle a specific permission for a role
         */
        case 'toggle':
            require_csrf();
            $role_id = (int)($_POST['role_id'] ?? 0);
            $permission_id = (int)($_POST['permission_id'] ?? 0);
            $enable = isset($_POST['enable']) ? (bool)$_POST['enable'] : false;

            if (!$role_id || !$permission_id) {
                json_response(['success' => false, 'message' => 'Role ID and Permission ID are required.'], 400);
            }

            // Check if Super Admin role (role_id = 1) is being restricted
            if ($role_id === 1 && !$enable) {
                json_response(['success' => false, 'message' => 'Super Admin permissions cannot be revoked.'], 403);
            }

            if ($enable) {
                $ins = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                $ins->execute([$role_id, $permission_id]);
                $msg = 'Permission granted to role.';
            } else {
                $del = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?");
                $del->execute([$role_id, $permission_id]);
                $msg = 'Permission revoked from role.';
            }

            log_activity('PRIVILEGE_TOGGLED', "Role #{$role_id} permission #{$permission_id} set to " . ($enable ? 'ENABLED' : 'DISABLED'));

            json_response(['success' => true, 'message' => $msg]);
            break;

        /**
         * Save full permissions for a role in one batch
         */
        case 'save_role_permissions':
            require_csrf();
            $role_id = (int)($_POST['role_id'] ?? 0);
            $permission_ids = $_POST['permission_ids'] ?? [];

            if (!$role_id) {
                json_response(['success' => false, 'message' => 'Role ID is required.'], 400);
            }

            if ($role_id === 1) {
                json_response(['success' => false, 'message' => 'Super Admin always retains all permissions.'], 403);
            }

            $pdo->beginTransaction();
            $del = $pdo->prepare("DELETE FROM role_permissions WHERE role_id = ?");
            $del->execute([$role_id]);

            if (is_array($permission_ids) && count($permission_ids) > 0) {
                $ins = $pdo->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                foreach ($permission_ids as $pid) {
                    $ins->execute([$role_id, (int)$pid]);
                }
            }

            $pdo->commit();
            log_activity('PRIVILEGES_BATCH_UPDATE', "Batch updated permissions for Role #{$role_id}");

            json_response(['success' => true, 'message' => 'Role permissions updated successfully.']);
            break;

        default:
            json_response(['success' => false, 'message' => 'Unknown Privilege action.'], 404);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Privilege API Error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
