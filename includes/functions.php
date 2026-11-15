<?php
/**
 * Shared Helper Functions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

/**
 * Escape HTML output for XSS prevention
 */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Return JSON response and terminate
 */
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo json_encode($data);
    exit;
}

/**
 * Log an activity to activity_logs
 */
function log_activity($action, $details = '') {
    try {
        $pdo = getDBConnection();
        $user = current_user();
        $user_id = $user ? $user['id'] : null;
        $username = $user ? $user['username'] : 'system';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, username, action, details, ip_address)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $username, $action, is_array($details) ? json_encode($details) : $details, $ip]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Retrieve system setting by key
 */
function get_setting($key, $default = '') {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return ($val !== false) ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Update system setting
 */
function update_setting($key, $value) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO system_settings (setting_key, setting_value)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Calculate Overview Summary for a given date and session
 * Returns total_schools, pending_count, completed_count
 */
function get_reconciliation_summary($date, $session) {
    $pdo = getDBConnection();
    
    // Total active schools
    $stmt = $pdo->query("SELECT COUNT(*) FROM schools WHERE status = 'active'");
    $total_schools = (int)$stmt->fetchColumn();

    // Completed records for this date and session
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM reconciliation_records r
        JOIN schools s ON s.id = r.school_id
        WHERE s.status = 'active'
          AND r.record_date = ? 
          AND r.session = ? 
          AND r.is_completed = 1
    ");
    $stmt->execute([$date, $session]);
    $completed_count = (int)$stmt->fetchColumn();

    $pending_count = max(0, $total_schools - $completed_count);

    return [
        'total_schools'   => $total_schools,
        'pending_count'   => $pending_count,
        'completed_count' => $completed_count,
    ];
}
