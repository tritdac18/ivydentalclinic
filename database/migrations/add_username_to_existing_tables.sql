-- ============================================
-- Add Username to EXISTING Tables (Preserves Data)
-- ============================================
-- Use this if your tables already exist and have data
-- This script checks for existing columns/indexes before adding

-- ============================================
-- For tbl_patients
-- ============================================

-- Step 1: Check if username column exists, if not add it
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tbl_patients' 
    AND COLUMN_NAME = 'username'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `tbl_patients` ADD COLUMN `username` VARCHAR(50) AFTER `patient_id`;',
    'SELECT "Username column already exists in tbl_patients" AS message;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Check if username index exists, if not add it
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tbl_patients' 
    AND INDEX_NAME = 'username'
);

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `tbl_patients` ADD UNIQUE INDEX `username` (`username`);',
    'SELECT "Username index already exists in tbl_patients" AS message;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: Populate username for existing records
UPDATE `tbl_patients` 
SET `username` = CONCAT('patient_', `patient_id`) 
WHERE `username` IS NULL OR `username` = '';

-- Step 4: Make username NOT NULL after populating
ALTER TABLE `tbl_patients` 
MODIFY `username` VARCHAR(50) NOT NULL;

-- ============================================
-- For tbl_staff
-- ============================================

-- Step 1: Check if username column exists, if not add it
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tbl_staff' 
    AND COLUMN_NAME = 'username'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `tbl_staff` ADD COLUMN `username` VARCHAR(50) AFTER `staff_id`;',
    'SELECT "Username column already exists in tbl_staff" AS message;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Check if username index exists, if not add it
SET @idx_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tbl_staff' 
    AND INDEX_NAME = 'username'
);

SET @sql = IF(@idx_exists = 0,
    'ALTER TABLE `tbl_staff` ADD UNIQUE INDEX `username` (`username`);',
    'SELECT "Username index already exists in tbl_staff" AS message;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: Populate username for existing records
UPDATE `tbl_staff` 
SET `username` = CONCAT('staff_', `staff_id`) 
WHERE `username` IS NULL OR `username` = '';

-- Step 4: Make username NOT NULL after populating
ALTER TABLE `tbl_staff` 
MODIFY `username` VARCHAR(50) NOT NULL;

