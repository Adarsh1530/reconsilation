<?php
/**
 * School Management API Endpoint
 * Dedicated for Super Admin / School Managers
 */

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();
require_permission('manage_schools', true);

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$pdo = getDBConnection();

try {
    switch ($action) {
        /**
         * List all schools
         */
        case 'list':
            $status = $_GET['status'] ?? '';
            $sql = "SELECT * FROM schools WHERE 1=1";
            $params = [];
            if (!empty($status)) {
                $sql .= " AND status = ?";
                $params[] = $status;
            }
            $sql .= " ORDER BY sort_order ASC, id ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $schools = $stmt->fetchAll();
            json_response(['success' => true, 'data' => $schools]);
            break;

        /**
         * Get single school details
         */
        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
            $stmt->execute([$id]);
            $school = $stmt->fetch();
            if (!$school) {
                json_response(['success' => false, 'message' => 'School not found.'], 404);
            }
            json_response(['success' => true, 'data' => $school]);
            break;

        /**
         * Create new school (Only requires School Name & ERP Login Settings)
         */
        case 'create':
            require_csrf();
            $name = trim($_POST['name'] ?? '');
            $erp_url = trim($_POST['erp_url'] ?? '');
            $erp_username = trim($_POST['erp_username'] ?? '');
            $erp_password = trim($_POST['erp_password'] ?? '');

            if (empty($name)) {
                json_response(['success' => false, 'message' => 'School name is required.'], 400);
            }

            // Auto-generate code from name
            $words = preg_split('/\s+/', $name);
            $codePrefix = '';
            foreach ($words as $w) {
                if (!empty($w)) $codePrefix .= strtoupper($w[0]);
            }
            if (empty($codePrefix)) $codePrefix = 'SCH';
            $code = 'SCH-' . substr($codePrefix, 0, 5) . rand(10, 99);

            // Auto-derive ERP login url from domain if erp_url provided
            $erp_login_url = '';
            if (!empty($erp_url)) {
                try {
                    $parsed = parse_url($erp_url);
                    if (!empty($parsed['scheme']) && !empty($parsed['host'])) {
                        $erp_login_url = $parsed['scheme'] . '://' . $parsed['host'] . '/index.php/user/login';
                    }
                } catch (Exception $e) {}
            }

            // Auto-determine next sort order
            $order_stmt = $pdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM schools");
            $next_order = (int)$order_stmt->fetchColumn();

            $ins = $pdo->prepare("
                INSERT INTO schools (code, name, erp_url, erp_login_url, erp_username, erp_password, status, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, 'active', ?)
            ");
            $ins->execute([$code, $name, $erp_url, $erp_login_url, $erp_username, $erp_password, $next_order]);
            $new_id = $pdo->lastInsertId();

            log_activity('SCHOOL_CREATED', "Created new school '{$name}' (ID: {$new_id})");

            json_response(['success' => true, 'school_id' => (int)$new_id, 'message' => "School '{$name}' created successfully."]);
            break;

        /**
         * Edit school (Only requires School Name & ERP Login Settings)
         */
        case 'edit':
            require_csrf();
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $erp_url = trim($_POST['erp_url'] ?? '');
            $erp_username = trim($_POST['erp_username'] ?? '');
            $erp_password = trim($_POST['erp_password'] ?? '');

            if (!$id || empty($name)) {
                json_response(['success' => false, 'message' => 'School ID and name are required.'], 400);
            }

            // Auto-derive ERP login url from domain
            $erp_login_url = '';
            if (!empty($erp_url)) {
                try {
                    $parsed = parse_url($erp_url);
                    if (!empty($parsed['scheme']) && !empty($parsed['host'])) {
                        $erp_login_url = $parsed['scheme'] . '://' . $parsed['host'] . '/index.php/user/login';
                    }
                } catch (Exception $e) {}
            }

            $upd = $pdo->prepare("
                UPDATE schools 
                SET name = ?, erp_url = ?, erp_login_url = ?, erp_username = ?, erp_password = ?
                WHERE id = ?
            ");
            $upd->execute([$name, $erp_url, $erp_login_url, $erp_username, $erp_password, $id]);

            log_activity('SCHOOL_UPDATED', "Updated school '{$name}' (ID: {$id})");

            json_response(['success' => true, 'message' => "School '{$name}' updated successfully."]);
            break;

        /**
         * Reorder schools (Move Up / Down or set new order)
         */
        case 'reorder':
            require_csrf();
            $id = (int)($_POST['id'] ?? 0);
            $direction = $_POST['direction'] ?? ''; // 'up' or 'down'

            if (!$id || !in_array($direction, ['up', 'down'])) {
                json_response(['success' => false, 'message' => 'Valid school ID and direction are required.'], 400);
            }

            $allSchools = $pdo->query("SELECT id, sort_order FROM schools ORDER BY sort_order ASC, id ASC")->fetchAll();
            $currentIndex = -1;
            foreach ($allSchools as $idx => $s) {
                if ((int)$s['id'] === $id) {
                    $currentIndex = $idx;
                    break;
                }
            }

            if ($currentIndex === -1) {
                json_response(['success' => false, 'message' => 'School not found.'], 404);
            }

            $targetIndex = ($direction === 'up') ? $currentIndex - 1 : $currentIndex + 1;
            if ($targetIndex < 0 || $targetIndex >= count($allSchools)) {
                json_response(['success' => true, 'message' => 'School is already at the limit.']);
            }

            // Swap sort_order between currentIndex and targetIndex
            $pdo->beginTransaction();
            $currentSchool = $allSchools[$currentIndex];
            $targetSchool = $allSchools[$targetIndex];

            // Re-normalize all schools sort orders to sequential values
            $tempOrder = [];
            foreach ($allSchools as $idx => $s) {
                $tempOrder[$idx] = (int)$s['id'];
            }
            // Swap in array
            $temp = $tempOrder[$currentIndex];
            $tempOrder[$currentIndex] = $tempOrder[$targetIndex];
            $tempOrder[$targetIndex] = $temp;

            // Save new sequential sort orders
            $upd = $pdo->prepare("UPDATE schools SET sort_order = ? WHERE id = ?");
            foreach ($tempOrder as $newIdx => $sid) {
                $upd->execute([$newIdx + 1, $sid]);
            }
            $pdo->commit();

            log_activity('SCHOOL_REORDERED', "Moved school #{$id} {$direction}");

            json_response(['success' => true, 'message' => 'School display order updated successfully.']);
            break;

        /**
         * Delete school (Cascades associated records due to foreign keys)
         */
        case 'delete':
            require_csrf();
            $id = (int)($_POST['id'] ?? 0);
            if (!$id) {
                json_response(['success' => false, 'message' => 'School ID is required.'], 400);
            }

            $stmt = $pdo->prepare("SELECT name, code FROM schools WHERE id = ?");
            $stmt->execute([$id]);
            $school = $stmt->fetch();
            if (!$school) {
                json_response(['success' => false, 'message' => 'School not found.'], 404);
            }

            $del = $pdo->prepare("DELETE FROM schools WHERE id = ?");
            $del->execute([$id]);

            log_activity('SCHOOL_DELETED', "Deleted school '{$school['name']}' ({$school['code']}) ID: {$id}");

            json_response(['success' => true, 'message' => "School '{$school['name']}' was deleted successfully."]);
            break;

        default:
            json_response(['success' => false, 'message' => 'Unknown School action.'], 404);
            break;
    }
} catch (Exception $e) {
    error_log("School API Error: " . $e->getMessage());
    json_response(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}
