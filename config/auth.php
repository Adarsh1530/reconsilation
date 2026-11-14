<?php
/**
 * Authentication and Authorization Helper Functions
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/constants.php';

/**
 * Check if a user is currently logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require user to be logged in, redirect to login page if not
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: index.php");
        exit;
    }

    // Check session timeout if configured
    $timeout = isset($_SESSION['session_timeout']) ? $_SESSION['session_timeout'] : 3600;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        header("Location: index.php?timeout=1");
        exit;
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Get current logged in user details
 */
function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_id'],
        'username'  => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'] ?? $_SESSION['username'],
        'role_id'   => $_SESSION['role_id'],
        'role_slug' => $_SESSION['role_slug'],
        'role_name' => $_SESSION['role_name']
    ];
}

/**
 * Check if the user has a specific permission
 */
function has_permission($permission_slug) {
    if (!is_logged_in()) {
        return false;
    }
    
    // Super Admin has all permissions
    if ($_SESSION['role_slug'] === ROLE_SUPER_ADMIN) {
        return true;
    }

    // Check cached session permissions
    if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        return in_array($permission_slug, $_SESSION['permissions']);
    }

    // Fallback: Query database
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT p.slug 
        FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        WHERE rp.role_id = ? AND p.slug = ?
    ");
    $stmt->execute([$_SESSION['role_id'], $permission_slug]);
    return (bool)$stmt->fetch();
}

/**
 * Require a specific permission; throw error or 403 response if missing
 */
function require_permission($permission_slug, $is_api = false) {
    require_login();
    if (!has_permission($permission_slug)) {
        if ($is_api) {
            header('Content-Type: application/json', true, 403);
            echo json_encode(['success' => false, 'message' => 'Permission denied: ' . $permission_slug]);
            exit;
        } else {
            http_response_code(403);
            die("Access Denied: You do not have permission to perform this action.");
        }
    }
}

/**
 * Check if user has one of the specified roles
 */
function has_role($roles) {
    if (!is_logged_in()) return false;
    if (is_string($roles)) $roles = [$roles];
    return in_array($_SESSION['role_slug'], $roles);
}

/**
 * Require a specific role
 */
function require_role($roles, $is_api = false) {
    require_login();
    if (!has_role($roles)) {
        if ($is_api) {
            header('Content-Type: application/json', true, 403);
            echo json_encode(['success' => false, 'message' => 'Unauthorized role access.']);
            exit;
        } else {
            http_response_code(403);
            die("Access Denied: You do not have sufficient role privileges.");
        }
    }
}

/**
 * CSRF Protection
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf_token($token)) {
        header('Content-Type: application/json', true, 403);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired security token (CSRF). Please refresh the page.']);
        exit;
    }
}

/**
 * Refresh user permissions into session
 */
function refresh_user_permissions($role_id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT p.slug 
        FROM permissions p
        INNER JOIN role_permissions rp ON p.id = rp.permission_id
        WHERE rp.role_id = ?
    ");
    $stmt->execute([$role_id]);
    $_SESSION['permissions'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
