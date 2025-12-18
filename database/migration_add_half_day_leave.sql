-- Migration: Add Half Day Leave Support
-- This migration adds support for half-day leaves by:
-- 1. Changing days_count from INT to DECIMAL to support 0.5 days
-- 2. Adding leave_duration column to track full_day or half_day
-- 3. Adding half_day_period column to track morning or afternoon for half days
-- 4. Updating leave_balances columns to DECIMAL to support half day deductions

-- Step 1: Alter days_count column to support decimal values
ALTER TABLE `leaves` 
MODIFY COLUMN `days_count` DECIMAL(3,1) NOT NULL;

-- Step 2: Add leave_duration column
ALTER TABLE `leaves` 
ADD COLUMN `leave_duration` ENUM('full_day', 'half_day') DEFAULT 'full_day' AFTER `leave_type`;

-- Step 3: Add half_day_period column (nullable, only used for half days)
ALTER TABLE `leaves` 
ADD COLUMN `half_day_period` ENUM('morning', 'afternoon') NULL AFTER `leave_duration`;

-- Update existing records to have full_day as default
UPDATE `leaves` SET `leave_duration` = 'full_day' WHERE `leave_duration` IS NULL;

-- Step 4: Update leave_balances columns to support decimal values
ALTER TABLE `leave_balances` 
MODIFY COLUMN `paid_leave` DECIMAL(5,1) DEFAULT 20.0,
MODIFY COLUMN `sick_leave` DECIMAL(5,1) DEFAULT 10.0,
MODIFY COLUMN `casual_leave` DECIMAL(5,1) DEFAULT 5.0,
MODIFY COLUMN `wfh_days` DECIMAL(5,1) DEFAULT 12.0;

