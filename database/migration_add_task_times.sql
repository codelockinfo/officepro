-- Migration: Add start_time and end_time fields to tasks table
-- Date: 2025-01-XX

ALTER TABLE `tasks` 
ADD COLUMN IF NOT EXISTS `start_time` DATETIME NULL AFTER `due_date`,
ADD COLUMN IF NOT EXISTS `end_time` DATETIME NULL AFTER `start_time`;

-- Add indexes for better query performance
ALTER TABLE `tasks` 
ADD INDEX IF NOT EXISTS `idx_start_time` (`start_time`),
ADD INDEX IF NOT EXISTS `idx_end_time` (`end_time`);

