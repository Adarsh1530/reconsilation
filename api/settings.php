<?php
/**
 * System Settings API Endpoint
 * Dedicated for Super Admin
 */

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_permission('manage_system_rules', true);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = getDBConnection();

try {
    switch ($action) {
        case 'get_all':
            $stmt = $pdo->query("SELECT setting_key, setting_value, setting_group, description FROM system_settings");
            $settings = $stmt->fetchAll();
            json_response(['success' => true, 'data' => $settings]);
            break;

        case 'save':
            require_csrf();
            $settings = $_POST['settings'] ?? [];
            if (!is_array($settings)) {
                json_response(['success' => false, 'message' => 'Settings data must be an object or array.'], 400);
            }

            $pdo->beginTransaction();
            foreach ($settings as $key => $val) {
                update_setting($key, trim($val));
            }
            $pdo->commit();

            log_activity('SETTINGS_UPDATED', 'Super Admin updated system settings & session rules.');
            json_response(['success' => true, 'message' => 'System settings saved successfully.']);
            break;

        default:
            json_response(['success' => false, 'message' => 'Unknown Settings action.'], 404);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Settings API Error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
