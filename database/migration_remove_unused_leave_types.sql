-- Migration: Remove Unused Leave Types
-- This migration removes sick_leave, casual_leave, and wfh_days columns
-- and updates leave_type ENUM to only allow paid_leave

-- Step 1: Remove unused columns from leave_balances table
ALTER TABLE `leave_balances` 
DROP COLUMN IF EXISTS `sick_leave`,
DROP COLUMN IF EXISTS `casual_leave`,
DROP COLUMN IF EXISTS `wfh_days`;

-- Step 2: Update leaves table to only allow paid_leave type
-- First, update any existing records that aren't paid_leave
UPDATE `leaves` SET `leave_type` = 'paid_leave' 
WHERE `leave_type` IN ('sick_leave', 'casual_leave', 'work_from_home');

-- Step 3: Modify the ENUM to only have paid_leave
ALTER TABLE `leaves` 
MODIFY COLUMN `leave_type` ENUM('paid_leave') NOT NULL DEFAULT 'paid_leave';

