<?php
/**
 * User Management API Endpoint
 * Dedicated for Super Admin
 */

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_permission('manage_users', true);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = getDBConnection();
$current = current_user();

try {
    switch ($action) {
        /**
         * List all users
         */
        case 'list':
            $stmt = $pdo->query("
                SELECT 
                    u.id, 
                    u.username, 
                    u.full_name,
                    u.is_active, 
                    u.last_login, 
                    u.created_at,
                    r.id AS role_id, 
                    r.name AS role_name, 
                    r.slug AS role_slug
                FROM users u
                JOIN roles r ON r.id = u.role_id
                ORDER BY u.id ASC
            ");
            $users = $stmt->fetchAll();
            json_response(['success' => true, 'data' => $users]);
            break;

        /**
         * Get single user details
         */
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT u.id, u.username, u.full_name, u.role_id, u.is_active, u.created_at, u.last_login, r.name AS role_name, r.slug AS role_slug
                FROM users u
                JOIN roles r ON r.id = u.role_id
                WHERE u.id = ?
            ");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            if (!$user) {
                json_response(['success' => false, 'message' => 'User not found.'], 404);
            }
            json_response(['success' => true, 'data' => $user]);
            break;

        /**
         * Create new user account (Only requires Username, Password, Role)
         */
        case 'create':
            require_csrf();
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role_id = (int)($_POST['role_id'] ?? 0);
            $full_name = trim($_POST['full_name'] ?? '');

            if (empty($username) || empty($password) || !$role_id) {
                json_response(['success' => false, 'message' => 'Username, password, and role are required.'], 400);
            }

            if (strlen($username) < 3) {
                json_response(['success' => false, 'message' => 'Username must be at least 3 characters long.'], 400);
            }

            if (strlen($password) < 6) {
                json_response(['success' => false, 'message' => 'Password must be at least 6 characters long.'], 400);
            }

            // Check duplicate username
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                json_response(['success' => false, 'message' => 'Username already exists. Please choose a different username.'], 409);
            }

            // Verify role exists
            $r_stmt = $pdo->prepare("SELECT id, name FROM roles WHERE id = ?");
            $r_stmt->execute([$role_id]);
            $role = $r_stmt->fetch();
            if (!$role) {
                json_response(['success' => false, 'message' => 'Selected role is invalid.'], 400);
            }

            // Hash password with bcrypt
            $password_hash = password_hash($password, PASSWORD_BCRYPT);

            $ins = $pdo->prepare("
                INSERT INTO users (username, password_hash, role_id, full_name, is_active)
                VALUES (?, ?, ?, ?, 1)
            ");
            $ins->execute([$username, $password_hash, $role_id, !empty($full_name) ? $full_name : $username]);
            $new_user_id = $pdo->lastInsertId();

            log_activity('USER_CREATED', "Created user '{$username}' with role '{$role['name']}' (ID: {$new_user_id})");

            json_response(['success' => true, 'message' => "User '{$username}' created successfully."]);
            break;

        /**
         * Edit user account
         */
        case 'edit':
            require_csrf();
            $id = (int)($_POST['id'] ?? 0);
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $role_id = (int)($_POST['role_id'] ?? 0);
            $full_name = trim($_POST['full_name'] ?? '');
            $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;

            if (!$id || empty($username) || !$role_id) {
                json_response(['success' => false, 'message' => 'User ID, username, and role are required.'], 400);
            }

            // Check duplicate username for another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $id]);
            if ($stmt->fetch()) {
                json_response(['success' => false, 'message' => 'Username already taken by another account.'], 409);
            }

            // If updating self, cannot deactivate self
            if ($id === (int)$current['id'] && $is_active === 0) {
                json_response(['success' => false, 'message' => 'You cannot deactivate your own account.'], 400);
            }

            if (!empty($password)) {
                if (strlen($password) < 6) {
                    json_response(['success' => false, 'message' => 'Password must be at least 6 characters long.'], 400);
                }
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $upd = $pdo->prepare("
                    UPDATE users 
                    SET username = ?, password_hash = ?, role_id = ?, full_name = ?, is_active = ?
                    WHERE id = ?
                ");
                $upd->execute([$username, $password_hash, $role_id, $full_name, $is_active, $id]);
            } else {
                $upd = $pdo->prepare("
                    UPDATE users 
                    SET username = ?, role_id = ?, full_name = ?, is_active = ?
                    WHERE id = ?
                ");
                $upd->execute([$username, $role_id, $full_name, $is_active, $id]);
            }

            log_activity('USER_UPDATED', "Updated user '{$username}' (ID: {$id})");

            json_response(['success' => true, 'message' => "User '{$username}' updated successfully."]);
            break;

        /**
         * Delete user account
         */
        case 'delete':
            require_csrf();
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                json_response(['success' => false, 'message' => 'User ID is required.'], 400);
            }

            // Prevent self-deletion
            if ($id === (int)$current['id']) {
                json_response(['success' => false, 'message' => 'You cannot delete your own logged-in account.'], 400);
            }

            $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $del_user = $stmt->fetch();

            if (!$del_user) {
                json_response(['success' => false, 'message' => 'User not found.'], 404);
            }

            $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $del->execute([$id]);

            log_activity('USER_DELETED', "Deleted user account '{$del_user['username']}' (ID: {$id})");

            json_response(['success' => true, 'message' => "User '{$del_user['username']}' has been permanently deleted."]);
            break;

        default:
            json_response(['success' => false, 'message' => 'Unknown User Management action.'], 404);
            break;
    }
} catch (Exception $e) {
    error_log("User Management API Error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
