-- ============================================
-- Add service_id to tbl_appointments Table
-- Database: db_ivydental
-- ============================================
-- This script adds service_id foreign key to tbl_appointments
-- and migrates existing service_type data to service_id

-- Step 1: Add service_id column (nullable first for migration)
ALTER TABLE `tbl_appointments` 
ADD COLUMN `service_id` INT NULL AFTER `branch_id`,
ADD INDEX `idx_service_id` (`service_id`);

-- Step 2: Migrate existing service_type to service_id
-- Map service_type strings to service_id from tbl_service_catalog
UPDATE `tbl_appointments` a
INNER JOIN `tbl_service_catalog` s ON 
    CASE 
        WHEN a.service_type = 'General Checkup' THEN s.service_name = 'General Checkup'
        WHEN a.service_type = 'Cleaning' THEN s.service_name = 'Cleaning'
        WHEN a.service_type = 'Extraction' THEN s.service_name = 'Extraction'
        WHEN a.service_type = 'Consultation' THEN s.service_name = 'Consultation'
        WHEN a.service_type = 'Root Canal' THEN s.service_name = 'Root Canal'
        WHEN a.service_type = 'Teeth Whitening' THEN s.service_name = 'Teeth Whitening'
        ELSE FALSE
    END
SET a.service_id = s.service_id
WHERE a.service_type IS NOT NULL;

-- Step 3: Make service_id NOT NULL and add foreign key constraint
-- First, set any NULL service_id to a default (use Consultation as default)
UPDATE `tbl_appointments` 
SET `service_id` = (SELECT service_id FROM tbl_service_catalog WHERE service_name = 'Consultation' LIMIT 1)
WHERE `service_id` IS NULL;

-- Now make it NOT NULL and add foreign key
ALTER TABLE `tbl_appointments`
MODIFY COLUMN `service_id` INT NOT NULL,
ADD CONSTRAINT `fk_appointments_service_id` 
    FOREIGN KEY (`service_id`) 
    REFERENCES `tbl_service_catalog`(`service_id`) 
    ON DELETE RESTRICT 
    ON UPDATE CASCADE;

-- Step 4: (Optional) Remove service_type column if you want to fully migrate
-- Uncomment the line below if you want to remove the old service_type column
-- ALTER TABLE `tbl_appointments` DROP COLUMN `service_type`;

-- ============================================
-- Verify the changes
-- ============================================
SELECT 'service_id column added successfully!' AS message;
SELECT 
    a.appointment_id,
    a.service_id,
    s.service_name,
    a.service_type AS old_service_type,
    a.price
FROM `tbl_appointments` a
INNER JOIN `tbl_service_catalog` s ON a.service_id = s.service_id
LIMIT 10;

