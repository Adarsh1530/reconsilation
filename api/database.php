<?php
/**
 * Database Management API
 * Super Admin Only: Backup, Restore, and Clear Data
 * School Reconciliation Management System
 */

require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';

require_role(ROLE_SUPER_ADMIN, true);

$action = $_GET['action'] ?? '';
$user = current_user();

const CONFIRMATION_PASSWORD = 'reconsilation';

try {
    $pdo = getDBConnection();

    switch ($action) {
        /**
         * 1. Download Complete Database Backup (.sql)
         */
        case 'download_backup':
            $dump = generate_database_dump($pdo);
            $filename = 'school_recon_backup_' . date('Y-m-d_H-i-s') . '.sql';

            log_activity('DB_BACKUP_DOWNLOAD', "Super Admin '{$user['username']}' downloaded complete database backup.");

            header('Content-Type: application/sql; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($dump));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            echo $dump;
            exit;

        /**
         * 2. Restore Database from Uploaded Backup File (.sql)
         */
        case 'restore':
            require_csrf();

            $password = trim($_POST['password'] ?? '');
            if ($password !== CONFIRMATION_PASSWORD) {
                json_response([
                    'success' => false, 
                    'message' => 'Invalid confirmation password. Password must be: ' . CONFIRMATION_PASSWORD
                ], 403);
            }

            if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                json_response([
                    'success' => false, 
                    'message' => 'Please select a valid .sql backup file to restore.'
                ], 400);
            }

            $tmpFile = $_FILES['backup_file']['tmp_name'];
            $fileSize = $_FILES['backup_file']['size'];

            if ($fileSize <= 0 || $fileSize > 50 * 1024 * 1024) { // max 50MB
                json_response(['success' => false, 'message' => 'Backup file size must be between 1 byte and 50MB.'], 400);
            }

            $sqlContent = file_get_contents($tmpFile);
            if (empty($sqlContent)) {
                json_response(['success' => false, 'message' => 'The uploaded backup file is empty.'], 400);
            }

            // Execute restore
            execute_sql_restore($pdo, $sqlContent);

            log_activity('DB_RESTORE', "Super Admin '{$user['username']}' restored database from backup file: " . $_FILES['backup_file']['name']);

            json_response([
                'success' => true,
                'message' => 'Database backup has been successfully restored! All records and tables updated.'
            ]);
            break;

        /**
         * 3. Clear Database Records
         */
        case 'clear':
            require_csrf();

            $password = trim($_POST['password'] ?? '');
            if ($password !== CONFIRMATION_PASSWORD) {
                json_response([
                    'success' => false, 
                    'message' => 'Invalid confirmation password. Password must be: ' . CONFIRMATION_PASSWORD
                ], 403);
            }

            $scope = $_POST['scope'] ?? 'recon_only';

            $pdo->beginTransaction();
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

            // Clear all reconciliation records, task records, urgent cases, and history
            $pdo->exec("TRUNCATE TABLE reconciliation_history;");
            $pdo->exec("TRUNCATE TABLE urgent_cases;");
            $pdo->exec("TRUNCATE TABLE reconciliation_records;");

            $msg = 'Database records (reconciliation tasks, history, and urgent cases) have been permanently cleared.';
            $act = 'DB_CLEAR_DATA';

            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
            $pdo->commit();

            log_activity($act, "Super Admin '{$user['username']}' executed data clearance: {$scope}");

            json_response([
                'success' => true,
                'message' => $msg
            ]);
            break;

        default:
            json_response(['success' => false, 'message' => 'Invalid Database Management action.'], 400);
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Database Management API Error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Database operation error: ' . $e->getMessage()], 500);
}

/**
 * Helper to generate complete SQL dump of all database tables
 */
function generate_database_dump(PDO $pdo) {
    $tables = [
        'roles',
        'permissions',
        'role_permissions',
        'users',
        'schools',
        'reconciliation_records',
        'reconciliation_history',
        'urgent_cases',
        'system_settings',
        'system_notifications',
        'activity_logs'
    ];

    $dump = "-- ========================================================\n";
    $dump .= "-- School Reconciliation Management System\n";
    $dump .= "-- Complete Database Backup Dump\n";
    $dump .= "-- Date & Time: " . date('Y-m-d H:i:s') . "\n";
    $dump .= "-- Generated By: Super Admin\n";
    $dump .= "-- Database: " . DB_NAME . "\n";
    $dump .= "-- ========================================================\n\n";
    $dump .= "SET NAMES utf8mb4;\n";
    $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $table) {
        try {
            $check = $pdo->query("SHOW TABLES LIKE '{$table}'")->fetch();
            if (!$check) continue;

            $dump .= "-- --------------------------------------------------------\n";
            $dump .= "-- Structure and Data for table `{$table}`\n";
            $dump .= "-- --------------------------------------------------------\n\n";
            $dump .= "DROP TABLE IF EXISTS `{$table}`;\n\n";

            $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
            if (!empty($createRow[1])) {
                $dump .= $createRow[1] . ";\n\n";
            }

            // Fetch and dump rows
            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $colNames = implode('`, `', array_map('addslashes', $columns));

                $dump .= "INSERT INTO `{$table}` (`{$colNames}`) VALUES\n";
                $valRows = [];
                foreach ($rows as $row) {
                    $escapedVals = [];
                    foreach ($row as $val) {
                        if ($val === null) {
                            $escapedVals[] = "NULL";
                        } else {
                            $escapedVals[] = $pdo->quote($val);
                        }
                    }
                    $valRows[] = "(" . implode(', ', $escapedVals) . ")";
                }
                $dump .= implode(",\n", $valRows) . ";\n\n";
            }
        } catch (Exception $e) {
            error_log("Dump generation error on table {$table}: " . $e->getMessage());
        }
    }

    $dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    $dump .= "-- ========================================================\n";
    $dump .= "-- End of Database Backup\n";
    $dump .= "-- ========================================================\n";

    return $dump;
}

/**
 * Helper to safely execute multi-query SQL restore
 */
function execute_sql_restore(PDO $pdo, $sqlContent) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

    $queries = [];
    $lines = explode("\n", $sqlContent);
    $currentQuery = "";

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0) {
            continue;
        }
        $currentQuery .= $line . "\n";
        if (substr($trimmed, -1) === ';') {
            $queries[] = $currentQuery;
            $currentQuery = "";
        }
    }
    if (!empty(trim($currentQuery))) {
        $queries[] = $currentQuery;
    }

    foreach ($queries as $q) {
        $q = trim($q);
        if (!empty($q)) {
            $pdo->exec($q);
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
}
