-- ============================================
-- SIMPLE: Add Username Column (Manual Check Required)
-- ============================================
-- Run this script section by section if you get duplicate key errors
-- Check if columns/indexes exist first, then run only what you need

-- Step 1: Check if username column exists in tbl_patients
-- Run this query first to check:
-- SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_patients' AND COLUMN_NAME = 'username';

-- If username column doesn't exist, run this:
ALTER TABLE `tbl_patients` 
ADD COLUMN `username` VARCHAR(50) AFTER `patient_id`;

-- Step 2: Check if username index exists in tbl_patients
-- Run this query first to check:
-- SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS 
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_patients' AND INDEX_NAME = 'idx_username_patients';

-- If index doesn't exist, run this:
ALTER TABLE `tbl_patients` 
ADD UNIQUE INDEX `idx_username_patients` (`username`);

-- Step 3: Check if username column exists in tbl_staff
-- Run this query first to check:
-- SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_staff' AND COLUMN_NAME = 'username';

-- If username column doesn't exist, run this:
ALTER TABLE `tbl_staff` 
ADD COLUMN `username` VARCHAR(50) AFTER `staff_id`;

-- Step 4: Check if username index exists in tbl_staff
-- Run this query first to check:
-- SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS 
-- WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_staff' AND INDEX_NAME = 'idx_username_staff';

-- If index doesn't exist, run this:
ALTER TABLE `tbl_staff` 
ADD UNIQUE INDEX `idx_username_staff` (`username`);

-- Step 5: Populate username for existing records
UPDATE `tbl_patients` 
SET `username` = CONCAT('patient_', `patient_id`) 
WHERE `username` IS NULL OR `username` = '';

UPDATE `tbl_staff` 
SET `username` = CONCAT('staff_', `staff_id`) 
WHERE `username` IS NULL OR `username` = '';

-- Step 6: Make username NOT NULL (only after populating)
ALTER TABLE `tbl_patients` 
MODIFY `username` VARCHAR(50) NOT NULL;

ALTER TABLE `tbl_staff` 
MODIFY `username` VARCHAR(50) NOT NULL;

