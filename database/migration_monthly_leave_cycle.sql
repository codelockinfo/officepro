-- Migration: Add Monthly Leave Cycle Support
-- This migration adds:
-- 1. paid_leave_allocation setting to company_settings
-- 2. Updates leave_balances to support monthly cycle (adds month column)

-- Step 1: Add paid_leave_allocation to company_settings (if not exists)
INSERT IGNORE INTO `company_settings` (`company_id`, `setting_key`, `setting_value`, `created_at`, `updated_at`)
SELECT `id`, 'paid_leave_allocation', '12', NOW(), NOW()
FROM `companies`
WHERE NOT EXISTS (
    SELECT 1 FROM `company_settings` 
    WHERE `company_settings`.`company_id` = `companies`.`id` 
    AND `company_settings`.`setting_key` = 'paid_leave_allocation'
);

-- Step 2: Add month column to leave_balances for monthly tracking
ALTER TABLE `leave_balances` 
ADD COLUMN `month` INT NULL AFTER `year`,
ADD INDEX `idx_company_user_month` (`company_id`, `user_id`, `year`, `month`);

-- Note: For existing data, you may want to set month = 1 (January) for all existing records
-- UPDATE `leave_balances` SET `month` = 1 WHERE `month` IS NULL;

