<?php
/**
 * Application Constants
 */

define('APP_NAME', 'School Reconciliation Management System');
define('APP_VERSION', '1.0.0');

// Sessions
define('SESSION_AM', 'AM');
define('SESSION_PM', 'PM');
define('SESSION_AM_LABEL', 'Morning Session');
define('SESSION_PM_LABEL', 'Evening Session');

// Roles
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_ADMIN', 'admin');
define('ROLE_USER', 'user');

// Payment Statuses
define('PAYMENT_NONE', 'NONE');
define('PAYMENT_NO_PAYMENT', 'NO_PAYMENT');
define('PAYMENT_SUCCESS', 'PAYMENT_SUCCESS');

// Reconciliation Statuses
define('RECON_NONE', 'NONE');
define('RECON_ISSUE', 'ISSUE');

// Refund Statuses
define('REFUND_NONE', 'NONE');
define('REFUND_PROCESSED', 'PROCESSED');

// Default Warning Threshold for Issue Ref
define('DEFAULT_REF_WARNING_LEN', 10);
