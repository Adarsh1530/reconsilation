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
