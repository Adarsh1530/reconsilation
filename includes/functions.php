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

/**
 * Ensure the table for tracking Unique ID warning prompt counts exists
 */
function ensure_warning_tracking_table(PDO $pdo) {
    static $created = false;
    if ($created) return;
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `recon_unique_id_warnings` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `unique_id` VARCHAR(150) NOT NULL,
                `record_date` DATE NOT NULL,
                `school_id` INT NOT NULL,
                `session` ENUM('AM', 'PM') NOT NULL,
                `warning_count` INT NOT NULL DEFAULT 1,
                `is_bypassed` TINYINT(1) NOT NULL DEFAULT 0,
                `last_prompted_at` DATETIME NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_uid_date_session` (`unique_id`, `record_date`, `session`)
            ) ENGINE=InnoDB;
        ");
        $created = true;
    } catch (Exception $e) {
        error_log("Failed to ensure recon_unique_id_warnings table: " . $e->getMessage());
    }
}

/**
 * Get active scheduled session window ('AM' or 'PM') based on system configuration
 */
function get_current_scheduled_session($timestamp = null) {
    $time = $timestamp ? date('H:i', $timestamp) : date('H:i');
    $pm_start = get_setting('pm_start_time', '14:00');
    if (empty($pm_start)) $pm_start = '14:00';
    
    // If current time is earlier than the evening start time, it's Morning (AM)
    return ($time < $pm_start) ? SESSION_AM : SESSION_PM;
}

/**
 * Check if a session matches the current scheduled window
 */
function is_session_matched($targetSession, $timestamp = null) {
    if (!in_array($targetSession, [SESSION_AM, SESSION_PM])) {
        return false;
    }
    return $targetSession === get_current_scheduled_session($timestamp);
}

/**
 * Verify if Morning session of target date already has a valid completed or matching record
 * Prevents re-flagging discrepancies during Evening runs if Morning session is already valid.
 */
function check_morning_session_validation(PDO $pdo, $school_id, $record_date, $unique_id = null) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, uc.status AS urgent_case_status
            FROM reconciliation_records r
            LEFT JOIN urgent_cases uc ON uc.record_id = r.id
            WHERE r.school_id = :school_id
              AND r.record_date = :record_date
              AND r.session = 'AM'
            LIMIT 1
        ");
        $stmt->execute([
            ':school_id' => $school_id,
            ':record_date' => $record_date
        ]);
        $morningRec = $stmt->fetch();
        if (!$morningRec) {
            return false;
        }

        // 1. If morning task was already completed or resolved
        if ((int)$morningRec['is_completed'] === 1) {
            return true;
        }
        if (in_array($morningRec['payment_status'], ['PAYMENT_SUCCESS', 'NO_PAYMENT'])) {
            return true;
        }
        if ($morningRec['urgent_case_status'] === 'Resolved') {
            return true;
        }

        // 2. Check if all three ID fields match across the session
        if (!empty($unique_id)) {
            $uClean = trim((string)$unique_id);
            if ($morningRec['issue_reference'] === $uClean && (int)$morningRec['is_completed'] === 1) {
                return true;
            }
            if ($morningRec['refund_reference'] === $uClean) {
                return true;
            }
            if ($morningRec['duplicate_receipt_no'] === $uClean) {
                return true;
            }
        }

        return false;
    } catch (Exception $e) {
        error_log("check_morning_session_validation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Track warning prompt count per Unique ID per date and enforce throttling (max 1–2 times)
 * Returns array: ['should_prompt' => bool, 'warning_count' => int, 'is_capped' => bool]
 */
function track_and_check_warning_prompt(PDO $pdo, $unique_id, $record_date, $school_id, $session) {
    ensure_warning_tracking_table($pdo);
    $unique_id = trim((string)$unique_id);
    if (empty($unique_id)) {
        return ['should_prompt' => false, 'warning_count' => 0, 'is_capped' => false];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, warning_count, is_bypassed 
            FROM recon_unique_id_warnings 
            WHERE unique_id = ? AND record_date = ? AND session = ?
            LIMIT 1
        ");
        $stmt->execute([$unique_id, $record_date, $session]);
        $row = $stmt->fetch();

        if (!$row) {
            // First occurrence: record and permit prompt
            $ins = $pdo->prepare("
                INSERT INTO recon_unique_id_warnings (unique_id, record_date, school_id, session, warning_count, is_bypassed, last_prompted_at)
                VALUES (?, ?, ?, ?, 1, 0, NOW())
            ");
            $ins->execute([$unique_id, $record_date, $school_id, $session]);
            return ['should_prompt' => true, 'warning_count' => 1, 'is_capped' => false];
        }

        $currentCount = (int)$row['warning_count'];
        if ($currentCount < MAX_UNIQUE_ID_WARNING_PROMPTS) {
            // Second occurrence: increment and permit prompt, now reaching cap
            $newCount = $currentCount + 1;
            $upd = $pdo->prepare("
                UPDATE recon_unique_id_warnings 
                SET warning_count = ?, is_bypassed = 0, last_prompted_at = NOW()
                WHERE id = ?
            ");
            $upd->execute([$newCount, $row['id']]);
            return ['should_prompt' => true, 'warning_count' => $newCount, 'is_capped' => true];
        }

        // Exceeded threshold (>= 2): SUPPRESS prompt!
        $upd = $pdo->prepare("
            UPDATE recon_unique_id_warnings 
            SET is_bypassed = 1, last_prompted_at = NOW()
            WHERE id = ?
        ");
        $upd->execute([$row['id']]);
        return ['should_prompt' => false, 'warning_count' => $currentCount, 'is_capped' => true];
    } catch (Exception $e) {
        error_log("track_and_check_warning_prompt error: " . $e->getMessage());
        return ['should_prompt' => true, 'warning_count' => 1, 'is_capped' => false];
    }
}
