<?php
/**
 * Logout Handler
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    try {
        log_activity('LOGOUT', 'User signed out securely.');
    } catch (Exception $e) {
        // Ignore DB log failures during logout
    }
}

recon_destroy_session();

header("Location: index.php");
exit;
