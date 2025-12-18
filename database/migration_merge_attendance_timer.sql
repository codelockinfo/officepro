-- Migration: Merge Attendance and Timer Sessions
-- Simplifies to work with new start/stop timer functionality only
-- Removes check-in/check-out, merges functionality

-- Step 1: Add date column to timer_sessions if it doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'timer_sessions' 
    AND COLUMN_NAME = 'date');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `timer_sessions` ADD COLUMN `date` DATE NULL AFTER `user_id`',
    'SELECT "Column date already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Update existing timer_sessions to set date from start_time
UPDATE `timer_sessions` SET `date` = DATE(`start_time`) WHERE `date` IS NULL OR `date` = '0000-00-00';

-- Step 3: Make date NOT NULL after populating it
ALTER TABLE `timer_sessions` 
    MODIFY COLUMN `date` DATE NOT NULL;

-- Step 4: Add index on date if it doesn't exist
SET @idx_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'timer_sessions' 
    AND INDEX_NAME = 'idx_date');

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `timer_sessions` ADD INDEX `idx_date` (`date`)',
    'SELECT "Index idx_date already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 5: Make attendance_id nullable (remove requirement)
ALTER TABLE `timer_sessions` 
    MODIFY COLUMN `attendance_id` INT UNSIGNED NULL;

-- Step 6: Simplify attendance table - remove check_in/check_out/status, add is_present
-- Drop check_in column if exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'attendance' 
    AND COLUMN_NAME = 'check_in');
SET @sql = IF(@col_exists > 0, 'ALTER TABLE `attendance` DROP COLUMN `check_in`', 'SELECT "check_in column does not exist"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop check_out column if exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'attendance' 
    AND COLUMN_NAME = 'check_out');
SET @sql = IF(@col_exists > 0, 'ALTER TABLE `attendance` DROP COLUMN `check_out`', 'SELECT "check_out column does not exist"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop status column if exists
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'attendance' 
    AND COLUMN_NAME = 'status');
SET @sql = IF(@col_exists > 0, 'ALTER TABLE `attendance` DROP COLUMN `status`', 'SELECT "status column does not exist"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add is_present column if it doesn't exist
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'attendance' 
    AND COLUMN_NAME = 'is_present');
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `attendance` ADD COLUMN `is_present` TINYINT(1) DEFAULT 0 COMMENT ''1 if at least one timer session exists for this day''',
    'SELECT "is_present column already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 7: Update attendance records to set is_present based on timer sessions
UPDATE `attendance` a
SET a.`is_present` = 1
WHERE EXISTS (
    SELECT 1 FROM `timer_sessions` ts 
    WHERE ts.`company_id` = a.`company_id` 
    AND ts.`user_id` = a.`user_id` 
    AND ts.`date` = a.`date`
);

-- Step 8: Create attendance records for days with timer sessions but no attendance record
INSERT INTO `attendance` (`company_id`, `user_id`, `date`, `regular_hours`, `overtime_hours`, `is_present`, `created_at`)
SELECT DISTINCT 
    ts.`company_id`,
    ts.`user_id`,
    ts.`date`,
    0.00 as `regular_hours`,
    0.00 as `overtime_hours`,
    1 as `is_present`,
    MIN(ts.`created_at`) as `created_at`
FROM `timer_sessions` ts
WHERE NOT EXISTS (
    SELECT 1 FROM `attendance` a 
    WHERE a.`company_id` = ts.`company_id` 
    AND a.`user_id` = ts.`user_id` 
    AND a.`date` = ts.`date`
)
GROUP BY ts.`company_id`, ts.`user_id`, ts.`date`;
