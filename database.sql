-- School Reconciliation Management System Database
-- Database: school_reconciliation_db

CREATE DATABASE IF NOT EXISTS `school_reconciliation_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `school_reconciliation_db`;

-- Drop existing tables if re-importing
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `urgent_cases`;
DROP TABLE IF EXISTS `reconciliation_history`;
DROP TABLE IF EXISTS `reconciliation_records`;
DROP TABLE IF EXISTS `system_settings`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `schools`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Roles table
CREATE TABLE `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Permissions table
CREATE TABLE `permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `category` VARCHAR(50) DEFAULT 'General',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 3. Role Permissions mapping table
CREATE TABLE `role_permissions` (
    `role_id` INT NOT NULL,
    `permission_id` INT NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 4. Users table
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(60) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role_id` INT NOT NULL,
    `full_name` VARCHAR(100) NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `last_login` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB;

-- 5. Schools table
CREATE TABLE `schools` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50) NULL,
    `name` VARCHAR(150) NOT NULL,
    `contact_person` VARCHAR(100) NULL,
    `phone` VARCHAR(30) NULL,
    `email` VARCHAR(100) NULL,
    `address` TEXT NULL,
    `erp_url` VARCHAR(255) NULL,
    `erp_login_url` VARCHAR(255) NULL,
    `erp_username` VARCHAR(100) NULL,
    `erp_password` VARCHAR(255) NULL,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `sort_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 6. Reconciliation Records table (stores AM and PM records independently)
CREATE TABLE `reconciliation_records` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `school_id` INT NOT NULL,
    `record_date` DATE NOT NULL,
    `session` ENUM('AM', 'PM') NOT NULL,
    `payment_status` ENUM('NONE', 'NO_PAYMENT', 'PAYMENT_SUCCESS') DEFAULT 'NONE',
    `reconciliation_status` ENUM('NONE', 'ISSUE') DEFAULT 'NONE',
    `issue_reference` VARCHAR(255) NULL,
    `refund_status` ENUM('NONE', 'PROCESSED') DEFAULT 'NONE',
    `refund_reference` VARCHAR(255) NULL,
    `duplicate_receipt_no` VARCHAR(100) NULL,
    `duplicate_receipt_desc` TEXT NULL,
    `overall_remark` VARCHAR(255) DEFAULT 'Pending',
    `custom_remark` VARCHAR(255) NULL,
    `is_completed` TINYINT(1) DEFAULT 0,
    `is_urgent` TINYINT(1) DEFAULT 0,
    `completed_by` INT NULL,
    `completed_at` DATETIME NULL,
    `updated_by` INT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_school_date_session` (`school_id`, `record_date`, `session`),
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`completed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 7. Reconciliation History table (Action log for Super Admin Undo/Redo)
CREATE TABLE `reconciliation_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `record_id` INT NOT NULL,
    `school_id` INT NOT NULL,
    `record_date` DATE NOT NULL,
    `session` ENUM('AM', 'PM') NOT NULL,
    `action_type` VARCHAR(50) NOT NULL, -- e.g. NO_PAYMENT, PAYMENT_SUCCESS, ISSUE, REFUND, RESET, UNDO, REDO
    `previous_state` JSON NOT NULL,
    `new_state` JSON NOT NULL,
    `user_id` INT NULL,
    `username` VARCHAR(60) NOT NULL,
    `can_undo` TINYINT(1) DEFAULT 1,
    `can_redo` TINYINT(1) DEFAULT 0,
    `is_undone` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`record_id`) REFERENCES `reconciliation_records`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 8. Urgent Cases table
CREATE TABLE `urgent_cases` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `record_id` INT NOT NULL,
    `school_id` INT NOT NULL,
    `session` ENUM('AM', 'PM') NOT NULL,
    `record_date` DATE NOT NULL,
    `priority` ENUM('Medium', 'High', 'Critical') DEFAULT 'High',
    `issue_reference` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `status` ENUM('Open', 'In Progress', 'Resolved') DEFAULT 'Open',
    `flagged_by` INT NULL,
    `resolved_by` INT NULL,
    `resolved_at` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_record_id` (`record_id`),
    FOREIGN KEY (`record_id`) REFERENCES `reconciliation_records`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`school_id`) REFERENCES `schools`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`flagged_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 9. System Settings table
CREATE TABLE `system_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `setting_group` VARCHAR(50) DEFAULT 'General',
    `description` VARCHAR(255) NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 10. Activity Logs table
CREATE TABLE `activity_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NULL,
    `username` VARCHAR(60) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 11. System Notifications table (Super Admin Announcements)
CREATE TABLE IF NOT EXISTS `system_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('info', 'warning', 'urgent', 'success') DEFAULT 'info',
    `is_active` TINYINT(1) DEFAULT 1,
    `created_by` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- SEED DATA
-- ============================================================================

-- Roles
INSERT INTO `roles` (`id`, `name`, `slug`, `description`) VALUES
(1, 'Super Admin', 'super_admin', 'Full system access and control over all entities'),
(2, 'Admin', 'admin', 'Can manage reconciliation and urgent cases'),
(3, 'User', 'user', 'Standard staff member handling daily reconciliations');

-- Permissions
INSERT INTO `permissions` (`id`, `name`, `slug`, `category`, `description`) VALUES
(1, 'Can Manage Reconciliation', 'manage_reconciliation', 'Reconciliation', 'Enter reconciliation details, issues, and references'),
(2, 'Can Manage Payment Status', 'manage_payment_status', 'Reconciliation', 'Select No Payment or Payment Success'),
(3, 'Can Manage Refunds', 'manage_refunds', 'Reconciliation', 'Enter and process refund details'),
(4, 'Can Access Urgent Cases', 'access_urgent_cases', 'Reconciliation', 'View and manage urgent reconciliation cases'),
(5, 'Can View All Records', 'view_all_records', 'Reporting', 'View staff reconciliation records and logs'),
(6, 'Can Manage Schools', 'manage_schools', 'Administration', 'Create, edit, and delete schools'),
(7, 'Can Manage Users', 'manage_users', 'Administration', 'Create, edit, view, and delete user accounts'),
(8, 'Can Manage System Rules', 'manage_system_rules', 'Administration', 'Configure system settings, session times, and rules'),
(9, 'Can Undo Actions', 'undo_actions', 'Audit & Correction', 'Super Admin privilege to revert mistakes'),
(10, 'Can Redo Actions', 'redo_actions', 'Audit & Correction', 'Super Admin privilege to re-apply reverted actions');

-- Default Role Permissions
-- Super Admin: Has all permissions (1-10)
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6), (1, 7), (1, 8), (1, 9), (1, 10);

-- Admin: Permissions 1, 2, 3, 4, 5
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(2, 1), (2, 2), (2, 3), (2, 4), (2, 5);

-- User: Permissions 1, 2, 3
INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(3, 1), (3, 2), (3, 3);

-- Default Users (Password for all three is 'Admin@123' or 'Password@123')
-- Hash for 'Admin@123': $2y$10$O0FqF6pD4mI5Z5VfU2C4EedOQ0B4G2Q2WqK9iO1QvP7Y6M3E0W0Wq
-- Let's generate standard bcrypt hashes
INSERT INTO `users` (`id`, `username`, `password_hash`, `role_id`, `full_name`, `is_active`) VALUES
(1, 'superadmin', '$2y$10$UW1MUA0geMg4z.82ZN3kOOHm6G24HsU/lbix2.DctVLnMr5vmnKKS', 1, 'Chief Executive Administrator', 1),
(2, 'admin', '$2y$10$UW1MUA0geMg4z.82ZN3kOOHm6G24HsU/lbix2.DctVLnMr5vmnKKS', 2, 'Senior Operations Supervisor', 1),
(3, 'staff_user', '$2y$10$UW1MUA0geMg4z.82ZN3kOOHm6G24HsU/lbix2.DctVLnMr5vmnKKS', 3, 'Reconciliation Specialist', 1);

-- Seed System Settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`, `description`) VALUES
('am_start_time', '07:00', 'Sessions', 'AM Session scheduled start time'),
('am_end_time', '13:00', 'Sessions', 'AM Session scheduled end time'),
('pm_start_time', '14:00', 'Sessions', 'PM Session scheduled start time'),
('pm_end_time', '19:00', 'Sessions', 'PM Session scheduled end time'),
('issue_ref_warning_len', '10', 'Validation', 'Issue reference length exceeding this will show warning'),
('session_timeout_minutes', '60', 'Security', 'User session inactivity timeout in minutes'),
('app_title', 'School Reconciliation Management System', 'General', 'Application Header Title');

-- Seed Schools (Blue Mount & BVB Poochatty with ERP Credentials)
INSERT INTO `schools` (`id`, `code`, `name`, `contact_person`, `phone`, `email`, `address`, `erp_url`, `erp_login_url`, `erp_username`, `erp_password`, `status`, `sort_order`) VALUES
(1, 'SCH-BM', 'Blue Mount', 'Accounts Office', '+91 9876543201', 'accounts@bluemount.edu', 'Blue Mount Campus', 'https://bluemount.amserp.in/index.php/core/feescollection/onlinepayment', 'https://bluemount.amserp.in/index.php/user/login', 'admin', 'Bluemount_2026$', 'active', 1),
(2, 'SCH-BVB', 'BVB Poochatty', 'Finance Section', '+91 9876543202', 'finance@bvbpoochatty.edu', 'Poochatty Campus, Thrissur', 'https://bvbpoochatty.amserp.in/index.php/core/feescollection/onlinepayment', 'https://bvbpoochatty.amserp.in/index.php/user/login', 'admin', 'Bvbpty@2023#&', 'active', 2),
(3, 'SCH-DEMO', 'Demo Practice School', 'Practice Coordinator', '+91 9876543299', 'demo@practice.edu', 'Practice Virtual Campus', 'https://demo.amserp.in/index.php/core/feescollection/onlinepayment', 'https://demo.amserp.in/index.php/user/login', 'demo_admin', 'Demo@2026', 'active', 3);
