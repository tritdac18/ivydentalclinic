-- DEPRECATED: This script is no longer needed
-- Discount information should now be stored in tbl_discounts table
-- Use CREATE_tbl_discounts_new.sql instead

-- Add verification tracking fields for discount IDs
-- These track which staff member verified the discount and when
-- NOTE: Only run this if columns don't already exist

-- Check if columns exist first
SET @pwd_verified_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tbl_patients' 
    AND COLUMN_NAME = 'pwd_verified_by_staff_id'
);

SET @senior_verified_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tbl_patients' 
    AND COLUMN_NAME = 'senior_citizen_verified_by_staff_id'
);

-- Only add columns if they don't exist
SET @sql_add_pwd = IF(@pwd_verified_exists = 0, 
    'ALTER TABLE `tbl_patients`
    ADD COLUMN `pwd_verified_by_staff_id` INT DEFAULT NULL COMMENT ''Staff ID who verified PWD ID'' AFTER `pwd_id_number`,
    ADD COLUMN `pwd_verified_at` TIMESTAMP NULL DEFAULT NULL COMMENT ''When PWD ID was verified'' AFTER `pwd_verified_by_staff_id`,
    ADD FOREIGN KEY (`pwd_verified_by_staff_id`) REFERENCES `tbl_staff`(`staff_id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "PWD verification columns already exist"');

SET @sql_add_senior = IF(@senior_verified_exists = 0,
    'ALTER TABLE `tbl_patients`
    ADD COLUMN `senior_citizen_verified_by_staff_id` INT DEFAULT NULL COMMENT ''Staff ID who verified Senior Citizen ID'' AFTER `senior_citizen_id_number`,
    ADD COLUMN `senior_citizen_verified_at` TIMESTAMP NULL DEFAULT NULL COMMENT ''When Senior Citizen ID was verified'' AFTER `senior_citizen_verified_by_staff_id`,
    ADD FOREIGN KEY (`senior_citizen_verified_by_staff_id`) REFERENCES `tbl_staff`(`staff_id`) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT "Senior Citizen verification columns already exist"');

PREPARE stmt FROM @sql_add_pwd;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

PREPARE stmt FROM @sql_add_senior;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

