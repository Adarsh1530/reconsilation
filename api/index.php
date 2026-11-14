<?php
/**
 * Vercel Serverless Function Router for PHP
 */

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestUri = rawurldecode($requestUri);

// Root or index
if ($requestUri === '/' || $requestUri === '' || $requestUri === '/index.php') {
    chdir(__DIR__ . '/..');
    require __DIR__ . '/../index.php';
    exit;
}

// Check for direct file
$baseDir = realpath(__DIR__ . '/..');
$targetFile = realpath($baseDir . $requestUri);

// Ensure path traversal protection
if ($targetFile && strpos($targetFile, $baseDir) === 0 && is_file($targetFile)) {
    chdir(dirname($targetFile));
    require $targetFile;
    exit;
}

// Check with .php extension
$phpFile = realpath($baseDir . $requestUri . '.php');
if ($phpFile && strpos($phpFile, $baseDir) === 0 && is_file($phpFile)) {
    chdir(dirname($phpFile));
    require $phpFile;
    exit;
}

// Fallback to index.php
chdir(__DIR__ . '/..');
require __DIR__ . '/../index.php';
