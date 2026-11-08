-- ============================================
-- Fix Duplicate Key Name 'username' Error
-- ============================================
-- Use this if you're getting "#1061 - Duplicate key name 'username'"
-- This script will remove duplicate indexes and recreate them properly

-- Step 1: Check existing indexes on tbl_patients
-- Run this to see what indexes exist:
SELECT INDEX_NAME, COLUMN_NAME 
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'tbl_patients' 
AND COLUMN_NAME = 'username';

-- Step 2: Drop existing username index from tbl_patients (if it exists)
-- Uncomment and run if you see a duplicate index:
-- DROP INDEX `username` ON `tbl_patients`;
-- OR
-- DROP INDEX `idx_username` ON `tbl_patients`;
-- OR
-- DROP INDEX `idx_username_patients` ON `tbl_patients`;

-- Step 3: Add unique index with a specific name
ALTER TABLE `tbl_patients` 
ADD UNIQUE INDEX `idx_username_patients` (`username`);

-- Step 4: Check existing indexes on tbl_staff
SELECT INDEX_NAME, COLUMN_NAME 
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'tbl_staff' 
AND COLUMN_NAME = 'username';

-- Step 5: Drop existing username index from tbl_staff (if it exists)
-- Uncomment and run if you see a duplicate index:
-- DROP INDEX `username` ON `tbl_staff`;
-- OR
-- DROP INDEX `idx_username` ON `tbl_staff`;
-- OR
-- DROP INDEX `idx_username_staff` ON `tbl_staff`;

-- Step 6: Add unique index with a specific name
ALTER TABLE `tbl_staff` 
ADD UNIQUE INDEX `idx_username_staff` (`username`);

