-- ============================================
-- SAFE: Add Username Column to Existing Tables
-- Database: db_ivydental
-- ============================================
-- This script adds username column WITHOUT dropping tables
-- Use this if you have existing data you want to keep

-- Make sure you're using the correct database
USE `db_ivydental`;

-- Check and add username column to tbl_patients (if it doesn't exist)
SET @dbname = 'db_ivydental';
SET @tablename = "tbl_patients";
SET @columnname = "username";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  "SELECT 'Column username already exists in tbl_patients' AS result;",
  CONCAT("ALTER TABLE `", @tablename, "` ADD COLUMN `", @columnname, "` VARCHAR(50) AFTER `patient_id`;")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add unique constraint to username in tbl_patients (if index doesn't exist)
SET @indexname = "idx_username_patients";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (INDEX_NAME = @indexname)
  ) > 0,
  "SELECT 'Index idx_username_patients already exists' AS result;",
  CONCAT("ALTER TABLE `", @tablename, "` ADD UNIQUE INDEX `", @indexname, "` (`username`);")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check and add username column to tbl_staff (if it doesn't exist)
SET @tablename = "tbl_staff";
SET @columnname = "username";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  "SELECT 'Column username already exists in tbl_staff' AS result;",
  CONCAT("ALTER TABLE `", @tablename, "` ADD COLUMN `", @columnname, "` VARCHAR(50) AFTER `staff_id`;")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add unique constraint to username in tbl_staff (if index doesn't exist)
SET @indexname = "idx_username_staff";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (INDEX_NAME = @indexname)
  ) > 0,
  "SELECT 'Index idx_username_staff already exists' AS result;",
  CONCAT("ALTER TABLE `", @tablename, "` ADD UNIQUE INDEX `", @indexname, "` (`username`);")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================
-- Populate username for existing records
-- ============================================
-- Generate default usernames for existing patients (only if username is NULL or empty)
UPDATE `tbl_patients` 
SET `username` = CONCAT('patient_', `patient_id`) 
WHERE `username` IS NULL OR `username` = '';

-- Generate default usernames for existing staff (only if username is NULL or empty)
UPDATE `tbl_staff` 
SET `username` = CONCAT('staff_', `staff_id`) 
WHERE `username` IS NULL OR `username` = '';

-- ============================================
-- Make username NOT NULL after populating
-- ============================================
-- Only modify if column exists and is nullable
SET @tablename = "tbl_patients";
SET @columnname = "username";
SET @preparedStatement = (SELECT IF(
  (
    SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
      AND (IS_NULLABLE = 'YES')
  ) > 0,
  CONCAT("ALTER TABLE `", @tablename, "` MODIFY `", @columnname, "` VARCHAR(50) NOT NULL;"),
  "SELECT 'Username column is already NOT NULL or does not exist' AS result;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @tablename = "tbl_staff";
SET @preparedStatement = (SELECT IF(
  (
    SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
      AND (IS_NULLABLE = 'YES')
  ) > 0,
  CONCAT("ALTER TABLE `", @tablename, "` MODIFY `", @columnname, "` VARCHAR(50) NOT NULL;"),
  "SELECT 'Username column is already NOT NULL or does not exist' AS result;"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

