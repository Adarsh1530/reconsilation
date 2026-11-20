<?php
/**
 * Vercel Serverless Function Router for PHP
 */

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestUri = rawurldecode($requestUri);

$baseDir = dirname(__DIR__);

// Handle root or login page
if ($requestUri === '/' || $requestUri === '' || $requestUri === '/index.php' || $requestUri === '/api/index.php') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    chdir($baseDir);
    require $baseDir . '/index.php';
    exit;
}

// Explicit static routes for reliable Vercel serverless bundling
switch ($requestUri) {
    case '/announcements':
    case '/announcements.php':
        $_SERVER['SCRIPT_NAME'] = '/announcements.php';
        $_SERVER['PHP_SELF'] = '/announcements.php';
        chdir($baseDir);
        require $baseDir . '/announcements.php';
        exit;
    case '/dashboard':
    case '/dashboard.php':
        $_SERVER['SCRIPT_NAME'] = '/dashboard.php';
        $_SERVER['PHP_SELF'] = '/dashboard.php';
        chdir($baseDir);
        require $baseDir . '/dashboard.php';
        exit;
    case '/completed_tasks':
    case '/completed_tasks.php':
        $_SERVER['SCRIPT_NAME'] = '/completed_tasks.php';
        $_SERVER['PHP_SELF'] = '/completed_tasks.php';
        chdir($baseDir);
        require $baseDir . '/completed_tasks.php';
        exit;
    case '/settings':
    case '/settings.php':
        $_SERVER['SCRIPT_NAME'] = '/settings.php';
        $_SERVER['PHP_SELF'] = '/settings.php';
        chdir($baseDir);
        require $baseDir . '/settings.php';
        exit;
    case '/users':
    case '/users.php':
        $_SERVER['SCRIPT_NAME'] = '/users.php';
        $_SERVER['PHP_SELF'] = '/users.php';
        chdir($baseDir);
        require $baseDir . '/users.php';
        exit;
    case '/schools':
    case '/schools.php':
        $_SERVER['SCRIPT_NAME'] = '/schools.php';
        $_SERVER['PHP_SELF'] = '/schools.php';
        chdir($baseDir);
        require $baseDir . '/schools.php';
        exit;
    case '/monthly_report':
    case '/monthly_report.php':
        $_SERVER['SCRIPT_NAME'] = '/monthly_report.php';
        $_SERVER['PHP_SELF'] = '/monthly_report.php';
        chdir($baseDir);
        require $baseDir . '/monthly_report.php';
        exit;
    case '/urgent_cases':
    case '/urgent_cases.php':
        $_SERVER['SCRIPT_NAME'] = '/urgent_cases.php';
        $_SERVER['PHP_SELF'] = '/urgent_cases.php';
        chdir($baseDir);
        require $baseDir . '/urgent_cases.php';
        exit;
    case '/history':
    case '/history.php':
        $_SERVER['SCRIPT_NAME'] = '/history.php';
        $_SERVER['PHP_SELF'] = '/history.php';
        chdir($baseDir);
        require $baseDir . '/history.php';
        exit;
    case '/privileges':
    case '/privileges.php':
        $_SERVER['SCRIPT_NAME'] = '/privileges.php';
        $_SERVER['PHP_SELF'] = '/privileges.php';
        chdir($baseDir);
        require $baseDir . '/privileges.php';
        exit;
    case '/logout':
    case '/logout.php':
        $_SERVER['SCRIPT_NAME'] = '/logout.php';
        $_SERVER['PHP_SELF'] = '/logout.php';
        chdir($baseDir);
        require $baseDir . '/logout.php';
        exit;
    case '/scheduler':
    case '/scheduler.php':
    case '/api/scheduler':
    case '/api/scheduler.php':
        $_SERVER['SCRIPT_NAME'] = '/api/scheduler.php';
        $_SERVER['PHP_SELF'] = '/api/scheduler.php';
        chdir($baseDir . '/api');
        require $baseDir . '/api/scheduler.php';
        exit;
}

// Check for direct file (excluding this router itself)
$targetFile = realpath($baseDir . $requestUri);
if ($targetFile && $targetFile !== __FILE__ && strpos($targetFile, $baseDir) === 0 && is_file($targetFile)) {
    $_SERVER['SCRIPT_NAME'] = $requestUri;
    $_SERVER['PHP_SELF'] = $requestUri;
    chdir(dirname($targetFile));
    require $targetFile;
    exit;
}

// Check with .php extension
$phpFile = realpath($baseDir . $requestUri . '.php');
if ($phpFile && $phpFile !== __FILE__ && strpos($phpFile, $baseDir) === 0 && is_file($phpFile)) {
    $_SERVER['SCRIPT_NAME'] = $requestUri . '.php';
    $_SERVER['PHP_SELF'] = $requestUri . '.php';
    chdir(dirname($phpFile));
    require $phpFile;
    exit;
}

// Fallback to index.php
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
chdir($baseDir);
require $baseDir . '/index.php';
