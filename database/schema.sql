-- Multi-Tenant Employee Attendance & Leave Management System
-- Database Schema

-- Drop existing tables if they exist (for fresh install)
DROP TABLE IF EXISTS `audit_log`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `tasks`;
DROP TABLE IF EXISTS `saved_credentials`;
DROP TABLE IF EXISTS `company_settings`;
DROP TABLE IF EXISTS `holidays`;
DROP TABLE IF EXISTS `leave_balances`;
DROP TABLE IF EXISTS `leaves`;
DROP TABLE IF EXISTS `attendance`;
DROP TABLE IF EXISTS `invitations`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `departments`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `companies`;
DROP TABLE IF EXISTS `system_settings`;

-- System-level tables (no company_id)

CREATE TABLE `system_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
    `id` VARCHAR(128) PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `session_data` TEXT,
    `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Core multi-tenancy tables

CREATE TABLE `companies` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_name` VARCHAR(255) NOT NULL,
    `company_email` VARCHAR(255) NOT NULL UNIQUE,
    `phone` VARCHAR(50),
    `address` TEXT,
    `logo` VARCHAR(255),
    `subscription_status` ENUM('active', 'suspended', 'trial') DEFAULT 'active',
    `owner_id` INT UNSIGNED,
    `settings` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`subscription_status`),
    INDEX `idx_owner` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `profile_image` VARCHAR(255) NOT NULL,
    `role` ENUM('system_admin', 'company_owner', 'manager', 'employee') DEFAULT 'employee',
    `department_id` INT UNSIGNED,
    `status` ENUM('active', 'pending', 'suspended') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_email_per_company` (`company_id`, `email`),
    INDEX `idx_company` (`company_id`),
    INDEX `idx_role` (`role`),
    INDEX `idx_status` (`status`),
    INDEX `idx_department` (`department_id`),
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `invitations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `role` ENUM('manager', 'employee') DEFAULT 'employee',
    `invited_by` INT UNSIGNED NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `status` ENUM('pending', 'accepted', 'expired', 'cancelled') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_company` (`company_id`),
    INDEX `idx_token` (`token`),
    INDEX `idx_status` (`status`),
    INDEX `idx_expires` (`expires_at`),
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`invited_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Company-scoped tables

CREATE TABLE `departments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `manager_id` INT UNSIGNED,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_company` (`company_id`),
    INDEX `idx_manager` (`manager_id`),
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`manager_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `attendance` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `check_in` TIMESTAMP NOT NULL,
    `check_out` TIMESTAMP NULL,
    `date` DATE NOT NULL,
    `status` ENUM('in', 'out') DEFAULT 'in',
    `regular_hours` DECIMAL(5,2) DEFAULT 0.00,
    `overtime_hours` DECIMAL(5,2) DEFAULT 0.00,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_company_user` (`company_id`, `user_id`),
    INDEX `idx_company_date` (`company_id`, `date`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `leaves` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `leave_type` ENUM('paid_leave', 'sick_leave', 'casual_leave', 'work_from_home') NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `days_count` INT NOT NULL,
    `reason` TEXT NOT NULL,
    `attachment` VARCHAR(255),
    `status` ENUM('pending', 'approved', 'declined') DEFAULT 'pending',
    `approved_by` INT UNSIGNED,
    `comments` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_company_user` (`company_id`, `user_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_dates` (`start_date`, `end_date`),
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `leave_balances` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `year` INT NOT NULL,
    `paid_leave` INT DEFAULT 20,
    `sick_leave` INT DEFAULT 10,
    `casual_leave` INT DEFAULT 5,
    `wfh_days` INT DEFAULT 12,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_year` (`company_id`, `user_id`, `year`),
    INDEX `idx_company_user` (`company_id`, `user_id`),
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `holidays` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `date` DATE NOT NULL,
    `recurring` BOOLEAN DEFAULT FALSE,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_company_date` (`company_id`, `date`),
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `company_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_company_setting` (`company_id`, `setting_key`),
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `saved_credentials` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `website_name` VARCHAR(255) NOT NULL,
    `website_url` VARCHAR(500),
    `username` VARCHAR(255),
    `password` VARCHAR(255),
    `notes` TEXT,
    `is_shared` BOOLEAN DEFAULT FALSE,
    `shared_with` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_company_user` (`company_id`, `user_id`),
    INDEX `idx_shared` (`is_shared`),
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tasks` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `assigned_to` INT UNSIGNED NOT NULL,
    `title` VARCHAR(500) NOT NULL,
    `description` TEXT,
    `due_date` DATE,
    `status` ENUM('todo', 'in_progress', 'done') DEFAULT 'todo',
    `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL,
    INDEX `idx_company_assigned` (`company_id`, `assigned_to`),
    INDEX `idx_company_creator` (`company_id`, `created_by`),
    INDEX `idx_status` (`status`),
    INDEX `idx_due_date` (`due_date`),
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `notifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `message` TEXT NOT NULL,
    `link` VARCHAR(500),
    `read_status` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_company_user` (`company_id`, `user_id`),
    INDEX `idx_read_status` (`read_status`),
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `admin_id` INT UNSIGNED NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `target_table` VARCHAR(100),
    `target_id` INT UNSIGNED,
    `details` JSON,
    `ip_address` VARCHAR(45),
    `user_agent` VARCHAR(500),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_company` (`company_id`),
    INDEX `idx_admin` (`admin_id`),
    INDEX `idx_action` (`action`),
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('app_version', '1.0.0'),
('maintenance_mode', '0'),
('registration_enabled', '1');


