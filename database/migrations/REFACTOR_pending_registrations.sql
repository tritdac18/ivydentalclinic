-- ============================================
-- REFACTORED: Better Design for Pending Registrations
-- ============================================
-- Instead of duplicating all patient data, we:
-- 1. Add a 'status' field to tbl_patients (pending_verification, active, inactive)
-- 2. Create patient record immediately but mark as 'pending_verification'
-- 3. Use tbl_pending_registrations only for verification metadata (foreign key to patient)
-- 4. When verified, just update status to 'active' and delete pending record

-- Step 1: Add status column to tbl_patients
ALTER TABLE `tbl_patients` 
ADD COLUMN `status` ENUM('pending_verification', 'active', 'inactive') DEFAULT 'active' AFTER `updated_at`,
ADD INDEX `idx_status` (`status`);

-- Step 2: Create simplified tbl_pending_registrations (only verification metadata)
-- This table links to tbl_patients via foreign key
DROP TABLE IF EXISTS `tbl_pending_registrations`;

CREATE TABLE IF NOT EXISTS `tbl_pending_registrations` (
    `pending_id` INT NOT NULL AUTO_INCREMENT,
    `patient_id` INT NOT NULL COMMENT 'Foreign key to tbl_patients',
    `verification_code` VARCHAR(6) NOT NULL COMMENT '6-digit verification code',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL COMMENT 'Registration expires 24 hours after creation',
    `verified_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When email was verified',
    PRIMARY KEY (`pending_id`),
    FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients`(`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_patient_id` (`patient_id`),
    INDEX `idx_verification_code` (`verification_code`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Update existing patients to 'active' status (if any exist)
UPDATE `tbl_patients` SET `status` = 'active' WHERE `status` IS NULL OR `status` = '';

-- Step 4: Clean up any orphaned pending registrations (if old table exists)
-- This is safe to run even if old table doesn't exist
-- Note: You may want to manually migrate any pending data before running this

