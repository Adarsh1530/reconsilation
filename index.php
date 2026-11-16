<?php
/**
 * Login Gateway
 * School Reconciliation Management System
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

$error_message = '';
$timeout_message = isset($_GET['timeout']) ? 'Your session has expired due to inactivity. Please log in again.' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf_token($csrf)) {
        $error_message = 'Invalid security token. Please refresh and try again.';
    } elseif (empty($username) || empty($password)) {
        $error_message = 'Please provide both username and password.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("
                SELECT u.*, r.slug AS role_slug, r.name AS role_name 
                FROM users u
                JOIN roles r ON r.id = u.role_id
                WHERE u.username = ? AND u.is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Regenerate session id to prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['role_slug'] = $user['role_slug'];
                $_SESSION['role_name'] = $user['role_name'];
                $_SESSION['last_activity'] = time();

                // Load session timeout from settings
                $timeout_min = (int)get_setting('session_timeout_minutes', 60);
                $_SESSION['session_timeout'] = $timeout_min * 60;

                // Load user permissions into session
                refresh_user_permissions($user['role_id']);

                // Update last login timestamp
                $upd = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $upd->execute([$user['id']]);

                log_activity('LOGIN', 'User successfully logged in.');

                // Explicitly sync signed cookie before redirect
                recon_save_session_cookie();

                header("Location: dashboard.php");
                exit;
            } else {
                $error_message = 'Invalid username or password.';
                try {
                    log_activity('LOGIN_FAILED', "Failed login attempt for username: {$username}");
                } catch (Exception $ignored) {}
            }
        } catch (PDOException $e) {
            $error_message = 'Database connection error: ' . $e->getMessage() . '. Please verify database environment variables in Vercel settings.';
        } catch (Exception $e) {
            $error_message = 'An unexpected error occurred: ' . $e->getMessage();
        }
    }
}

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(circle at 10% 20%, rgb(241, 245, 249) 0%, rgb(226, 232, 240) 90%);
            padding: 20px;
        }
        .login-wrapper {
            width: 100%;
            max-width: 440px;
        }
        .login-card {
            background: #ffffff;
            border-radius: var(--radius-xl);
            box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.12);
            border: 1px solid var(--border-color);
            padding: 40px 36px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-logo {
            width: 54px;
            height: 54px;
            border-radius: var(--radius-lg);
            background: var(--blue-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin: 0 auto 16px;
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
        }
        .login-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }
        .login-subtitle {
            font-size: 13.5px;
            color: var(--text-muted);
            margin-top: 6px;
        }
        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1d4ed8;
            padding: 12px 16px;
            border-radius: var(--radius-md);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .credential-hints {
            margin-top: 24px;
            background: #f8fafc;
            border: 1px dashed var(--border-color);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            font-size: 12px;
            color: var(--text-muted);
        }
        .hint-title {
            font-weight: 700;
            color: #334155;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .hint-badges {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .hint-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            padding: 5px 10px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            cursor: pointer;
            transition: background 0.15s;
        }
        .hint-item:hover {
            background: #eff6ff;
            border-color: #93c5fd;
        }
        .hint-role {
            font-weight: 700;
            color: #1e3a8a;
        }
        .hint-creds {
            font-family: monospace;
            color: #475569;
        }
    </style>
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                    <path d="M6 12v5c3 3 9 3 12 0v-5"></path>
                </svg>
            </div>
            <h1 class="login-title">Sign In to ReconPortal</h1>
            <p class="login-subtitle">School Reconciliation Management System</p>
        </div>

        <?php if (!empty($timeout_message)): ?>
            <div class="alert-info"><?= e($timeout_message) ?></div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert-error">
                <span>⚠️</span>
                <span><?= e($error_message) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="index.php">
            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">

            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Enter username" required autofocus autocomplete="username">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter password" required autocomplete="current-password">
            </div>

            <div style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;">
                    <span>Access Dashboard</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                        <polyline points="12 5 19 12 12 19"></polyline>
                    </svg>
                </button>
            </div>
        </form>

        <div class="credential-hints">
            <div class="hint-title">
                <span>💡</span>
                <span>Click any account to fill demo credentials:</span>
            </div>
            <div class="hint-badges">
                <div class="hint-item" onclick="fillCreds('superadmin', 'Admin@123')">
                    <span class="hint-role">Super Admin</span>
                </div>
                <div class="hint-item" onclick="fillCreds('admin', 'Admin@123')">
                    <span class="hint-role">Admin</span>
                </div>
                <div class="hint-item" onclick="fillCreds('staff_user', 'Admin@123')">
                    <span class="hint-role">Staff User</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function fillCreds(u, p) {
    document.getElementById('username').value = u;
    document.getElementById('password').value = p;
    document.getElementById('password').focus();
}
</script>

</body>
</html>
