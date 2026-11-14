<?php
/**
 * Authentication and Authorization Helper Functions
 * Includes Serverless Stateless Session & CSRF Synchronization
 */

if (ob_get_level() === 0) {
    ob_start();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/constants.php';

/**
 * Secret key for signing session & CSRF cookies (Serverless persistence)
 */
define('RECON_APP_SECRET', getenv('APP_SECRET') ?: 'recon_secret_sig_key_2026_987413620984712');

/**
 * Restore session data from signed cookie if native serverless session was lost
 */
function recon_restore_signed_session() {
    $secret = RECON_APP_SECRET;

    if (!empty($_COOKIE['RECON_SESSION'])) {
        $parts = explode('.', $_COOKIE['RECON_SESSION'], 2);
        if (count($parts) === 2) {
            $json = base64_decode($parts[0], true);
            if ($json !== false) {
                $expectedSig = hash_hmac('sha256', $json, $secret);
                if (hash_equals($expectedSig, $parts[1])) {
                    $payload = json_decode($json, true);
                    if (is_array($payload)) {
                        foreach ($payload as $k => $v) {
                            if (!isset($_SESSION[$k])) {
                                $_SESSION[$k] = $v;
                            }
                        }
                    }
                }
            }
        }
    }

    // Restore or initialize CSRF token
    if (empty($_SESSION['csrf_token'])) {
        if (!empty($_COOKIE['RECON_CSRF'])) {
            $_SESSION['csrf_token'] = $_COOKIE['RECON_CSRF'];
        } else {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }
}

/**
 * Save current session into signed cookie for serverless persistence
 */
function recon_save_session_cookie() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $secret = RECON_APP_SECRET;
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if (!empty($_SESSION) && (isset($_SESSION['user_id']) || isset($_SESSION['csrf_token']))) {
        $json = json_encode($_SESSION);
        $sig = hash_hmac('sha256', $json, $secret);
        $cookieValue = base64_encode($json) . '.' . $sig;

        if (!headers_sent()) {
            setcookie('RECON_SESSION', $cookieValue, [
                'expires'  => time() + 86400 * 7,
                'path'     => '/',
                'secure'   => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    } elseif (isset($_COOKIE['RECON_SESSION']) && !headers_sent()) {
        setcookie('RECON_SESSION', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    if (!empty($_SESSION['csrf_token']) && !headers_sent()) {
        setcookie('RECON_CSRF', $_SESSION['csrf_token'], [
            'expires'  => time() + 86400 * 7,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
    }
}

/**
 * Destroy session and clear persistence cookies
 */
function recon_destroy_session() {
    $_SESSION = [];
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if (!headers_sent()) {
        setcookie('RECON_SESSION', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        setcookie('RECON_CSRF', '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

// Restore session from cookie immediately
recon_restore_signed_session();

// Ensure cookies are saved at end of request execution
register_shutdown_function('recon_save_session_cookie');

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
        recon_destroy_session();
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
    if (isset($_SESSION['role_slug']) && $_SESSION['role_slug'] === ROLE_SUPER_ADMIN) {
        return true;
    }

    // Check cached session permissions
    if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        return in_array($permission_slug, $_SESSION['permissions']);
    }

    // Fallback: Query database
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            SELECT p.slug 
            FROM permissions p
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ? AND p.slug = ?
        ");
        $stmt->execute([$_SESSION['role_id'], $permission_slug]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
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
    return isset($_SESSION['role_slug']) && in_array($_SESSION['role_slug'], $roles);
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
        if (!empty($_COOKIE['RECON_CSRF'])) {
            $_SESSION['csrf_token'] = $_COOKIE['RECON_CSRF'];
        } else {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if (!headers_sent() && (empty($_COOKIE['RECON_CSRF']) || $_COOKIE['RECON_CSRF'] !== $_SESSION['csrf_token'])) {
        setcookie('RECON_CSRF', $_SESSION['csrf_token'], [
            'expires'  => time() + 86400 * 7,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (empty($token) || !is_string($token)) {
        return false;
    }

    // Match against current session token
    if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        return true;
    }

    // Match against fallback cookie token (critical for serverless / multi-instance environments)
    if (!empty($_COOKIE['RECON_CSRF']) && hash_equals($_COOKIE['RECON_CSRF'], $token)) {
        $_SESSION['csrf_token'] = $token;
        return true;
    }

    return false;
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
