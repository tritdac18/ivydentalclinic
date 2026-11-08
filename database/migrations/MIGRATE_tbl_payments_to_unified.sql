-- ============================================
-- MIGRATE tbl_payments to Unified Schema
-- This adds the missing columns needed for PayMongo
-- ============================================

USE `if0_40821147_db_ivydental`;

-- Add missing columns to tbl_payments
ALTER TABLE `tbl_payments`
    ADD COLUMN `payment_type` ENUM('Downpayment', 'Invoice Payment', 'Full Payment', 'Refund') DEFAULT 'Invoice Payment' AFTER `payment_id`,
    ADD COLUMN `appointment_id` INT DEFAULT NULL AFTER `payment_type`,
    ADD COLUMN `patient_id` INT DEFAULT NULL AFTER `invoice_id`,
    ADD COLUMN `transaction_id` VARCHAR(255) DEFAULT NULL AFTER `payment_method`,
    ADD COLUMN `payment_status` ENUM('Pending', 'Completed', 'Failed', 'Refunded', 'Cancelled', 'Pending Verification') DEFAULT 'Pending Verification' AFTER `transaction_id`,
    ADD COLUMN `payment_date` DATETIME DEFAULT NULL AFTER `payment_status`,
    ADD COLUMN `notes` TEXT DEFAULT NULL AFTER `is_reconciled`;

-- Update payment_method ENUM to include more options
ALTER TABLE `tbl_payments`
    MODIFY COLUMN `payment_method` ENUM('Cash', 'E-Wallet', 'Online', 'Credit Card', 'Debit Card', 'GCash', 'PayMongo') DEFAULT 'Cash';

-- Add indexes for new columns
ALTER TABLE `tbl_payments`
    ADD INDEX `idx_appointment_id` (`appointment_id`),
    ADD INDEX `idx_patient_id` (`patient_id`),
    ADD INDEX `idx_payment_type` (`payment_type`),
    ADD INDEX `idx_payment_status` (`payment_status`),
    ADD INDEX `idx_transaction_id` (`transaction_id`),
    ADD INDEX `idx_payment_date` (`payment_date`);

-- Add foreign key constraints (if tables exist)
ALTER TABLE `tbl_payments`
    ADD CONSTRAINT `fk_payment_appointment`
        FOREIGN KEY (`appointment_id`) 
        REFERENCES `tbl_appointments`(`appointment_id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE,
    ADD CONSTRAINT `fk_payment_patient`
        FOREIGN KEY (`patient_id`) 
        REFERENCES `tbl_patients`(`patient_id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE;

-- Migrate existing data
-- Set payment_type for existing invoice payments
UPDATE `tbl_payments` 
SET `payment_type` = 'Invoice Payment' 
WHERE `invoice_id` IS NOT NULL;

-- Set default payment_status for existing records
UPDATE `tbl_payments` 
SET `payment_status` = 'Completed' 
WHERE `payment_status` IS NULL;

-- Set payment_date from transaction_date for existing records
UPDATE `tbl_payments` 
SET `payment_date` = `transaction_date` 
WHERE `payment_date` IS NULL AND `transaction_date` IS NOT NULL;

SELECT 'Migration completed successfully! tbl_payments now has unified schema.' AS message;
