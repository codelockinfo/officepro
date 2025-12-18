-- Migration: Add Timer Sessions Table
-- This migration adds support for work timer sessions that track individual work periods

CREATE TABLE IF NOT EXISTS `timer_sessions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `attendance_id` INT UNSIGNED NOT NULL,
    `start_time` TIMESTAMP NOT NULL,
    `stop_time` TIMESTAMP NULL,
    `end_time` TIMESTAMP NULL,
    `duration_seconds` INT DEFAULT 0,
    `status` ENUM('running', 'stopped', 'ended') DEFAULT 'running',
    `regular_hours` DECIMAL(5,2) DEFAULT 0.00,
    `overtime_hours` DECIMAL(5,2) DEFAULT 0.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_attendance` (`attendance_id`),
    INDEX `idx_user_date` (`company_id`, `user_id`, `start_time`),
    INDEX `idx_status` (`status`),
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`attendance_id`) REFERENCES `attendance`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

