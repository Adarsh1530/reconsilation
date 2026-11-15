<?php
/**
 * ERP Automated Login & Direct Link Gateway
 * School Reconciliation Management System
 * Automatically logs in and lands directly on the Fees Collection Online Payment link:
 * - Blue Mount: https://bluemount.amserp.in/index.php/core/feescollection/onlinepayment
 * - BVB Poochatty: https://bvbpoochatty.amserp.in/index.php/core/feescollection/onlinepayment
 */

require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$school_id = (int)($_GET['school_id'] ?? 0);
if (!$school_id) {
    die("Error: Missing school identification parameter.");
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT id, code, name, erp_url, erp_login_url, erp_username, erp_password FROM schools WHERE id = ?");
$stmt->execute([$school_id]);
$school = $stmt->fetch();

if (!$school) {
    die("Error: School record not found.");
}

$target_url = $school['erp_url'];
$login_url = !empty($school['erp_login_url']) ? $school['erp_login_url'] : '';
if (empty($login_url) && !empty($target_url)) {
    $parsed = parse_url($target_url);
    $login_url = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . '/index.php/user/login';
}

$username = $school['erp_username'];
$password = $school['erp_password'];

log_activity('ERP_DIRECT_LAUNCH', "Auto-launched ERP Online Payment gateway for {$school['name']}");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connecting to <?= e($school['name']) ?> Online Payment...</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=JetBrains+Mono:wght@600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background: radial-gradient(circle at 15% 15%, #1e1b4b 0%, #0f172a 100%);
            color: #ffffff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .gateway-card {
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 24px;
            padding: 44px 38px;
            max-width: 580px;
            width: 100%;
            text-align: center;
            box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.6);
            position: relative;
            overflow: hidden;
        }
        .gateway-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 5px;
            background: linear-gradient(90deg, #6366f1, #3b82f6, #10b981);
        }
        .school-badge {
            display: inline-block;
            background: rgba(99, 102, 241, 0.25);
            border: 1px solid rgba(99, 102, 241, 0.45);
            color: #a5b4fc;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 14px;
            border-radius: 9999px;
            margin-bottom: 16px;
            letter-spacing: 0.05em;
        }
        .school-title {
            font-size: 26px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 6px;
            letter-spacing: -0.02em;
        }
        .portal-subtext {
            font-size: 14px;
            color: #94a3b8;
            margin-bottom: 24px;
        }
        .target-url-box {
            background: rgba(15, 23, 42, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 24px;
            text-align: left;
        }
        .target-url-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #94a3b8;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .target-url-link {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12.5px;
            color: #38bdf8;
            word-break: break-all;
            display: block;
            text-decoration: none;
            font-weight: 600;
        }
        .spinner-wrapper {
            position: relative;
            width: 72px;
            height: 72px;
            margin: 0 auto 24px;
        }
        .spinner {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top-color: #6366f1;
            animation: spin 0.9s cubic-bezier(0.55, 0.15, 0.45, 0.85) infinite;
        }
        .spinner-icon {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            font-size: 26px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.35);
            color: #34d399;
            font-size: 13.5px;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 9999px;
            margin-bottom: 24px;
        }
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 10px #10b981;
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.85); }
        }
        .credentials-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            justify-content: space-around;
            gap: 12px;
            flex-wrap: wrap;
        }
        .cred-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12.5px;
        }
        .cred-label {
            color: #94a3b8;
            font-weight: 600;
        }
        .cred-val {
            font-family: 'JetBrains Mono', monospace;
            background: rgba(255, 255, 255, 0.1);
            padding: 3px 8px;
            border-radius: 6px;
            color: #f8fafc;
            font-weight: 700;
        }
        .btn-copy {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #94a3b8;
            font-size: 11px;
            padding: 2px 7px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-copy:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }
        .action-buttons-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .btn-direct-submit {
            background: linear-gradient(135deg, #4f46e5 0%, #2563eb 100%);
            color: white;
            font-size: 14.5px;
            font-weight: 700;
            padding: 13px 24px;
            border-radius: 12px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            width: 100%;
        }
        .btn-direct-submit:hover {
            background: linear-gradient(135deg, #4338ca 0%, #1d4ed8 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(79, 70, 229, 0.5);
        }
        .btn-direct-url {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #e2e8f0;
            font-size: 13px;
            font-weight: 600;
            padding: 10px 18px;
            border-radius: 10px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: background 0.2s;
        }
        .btn-direct-url:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }
    </style>
</head>
<body>

<div class="gateway-card">
    <div class="school-badge"><?= e($school['code']) ?></div>
    <h1 class="school-title"><?= e($school['name']) ?></h1>
    <p class="portal-subtext">Direct ERP Fees Collection Portal Gateway</p>

    <div class="target-url-box">
        <div class="target-url-label">🎯 Destination Target Link</div>
        <a href="<?= e($target_url) ?>" class="target-url-link" id="directTargetLink">
            <?= e($target_url) ?>
        </a>
    </div>

    <div class="spinner-wrapper">
        <div class="spinner"></div>
        <div class="spinner-icon">🔐</div>
    </div>

    <div class="status-pill">
        <span class="status-dot"></span>
        <span id="statusLabel">Authenticating and loading Online Payment...</span>
    </div>

    <div class="credentials-card">
        <div class="cred-item">
            <span class="cred-label">User:</span>
            <span class="cred-val" id="valUser"><?= e($username) ?></span>
            <button class="btn-copy" onclick="copyText('<?= e($username) ?>', this)">Copy</button>
        </div>
        <div class="cred-item">
            <span class="cred-label">Pass:</span>
            <span class="cred-val" id="valPass">••••••••</span>
            <button class="btn-copy" onclick="copyText('<?= e($password) ?>', this)">Copy</button>
        </div>
    </div>

    <!-- Hidden iframe pre-visits target URL to register returnUrl in AMS ERP session -->
    <iframe id="prepIframe" src="<?= e($target_url) ?>" style="position: absolute; width: 1px; height: 1px; left: -9999px; opacity: 0.01;" onload="onPrepLoaded()"></iframe>

    <!-- Auto-login form submitting to ERP login endpoint -->
    <form id="erpLoginForm" action="<?= e($login_url) ?>" method="POST" target="_self">
        <input type="hidden" name="UserLogin[username]" value="<?= e($username) ?>">
        <input type="hidden" name="UserLogin[password]" value="<?= e($password) ?>">
        <input type="hidden" name="yt0" value="Sign in ">
    </form>

    <div class="action-buttons-group">
        <button type="button" class="btn-direct-submit" id="btnAutoLogin" onclick="submitLoginNow()">
            <span>🚀 Click here if not redirected automatically</span>
        </button>
        <a href="<?= e($target_url) ?>" class="btn-direct-url" onclick="copyPasswordOnDirectClick('<?= e($password) ?>')">
            <span>🔗 Open <?= e($target_url) ?> Directly</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                <polyline points="15 3 21 3 21 9"></polyline>
                <line x1="10" y1="14" x2="21" y2="3"></line>
            </svg>
        </a>
    </div>
</div>

<script>
const password = <?= json_encode($password) ?>;
let autoSubmitted = false;

function submitLoginNow() {
    if (autoSubmitted) return;
    autoSubmitted = true;

    if (navigator.clipboard) {
        navigator.clipboard.writeText(password).catch(() => {});
    }

    const statusEl = document.getElementById('statusLabel');
    if (statusEl) {
        statusEl.textContent = 'Authenticated! Landing on Online Payment...';
    }

    document.getElementById('erpLoginForm').submit();
}

function onPrepLoaded() {
    // Iframe has pre-visited target URL, session returnUrl is set in AMS ERP
    setTimeout(submitLoginNow, 350);
}

// Fallback timer: submit login form after 1.4s regardless of iframe state
setTimeout(submitLoginNow, 1400);

function copyPasswordOnDirectClick(pw) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(pw).catch(() => {});
    }
}

function copyText(text, btn) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(() => {
            const orig = btn.textContent;
            btn.textContent = 'Copied!';
            btn.style.borderColor = '#10b981';
            btn.style.color = '#34d399';
            setTimeout(() => {
                btn.textContent = orig;
                btn.style.borderColor = '';
                btn.style.color = '';
            }, 1500);
        });
    }
}
</script>

</body>
</html>
