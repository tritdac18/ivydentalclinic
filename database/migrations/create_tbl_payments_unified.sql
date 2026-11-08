-- ============================================
-- Unified Payments Table
-- Consolidates downpayments and regular payments
-- ============================================

-- Create unified tbl_payments table
CREATE TABLE IF NOT EXISTS `tbl_payments` (
    `payment_id` INT NOT NULL AUTO_INCREMENT,
    `payment_type` ENUM('Downpayment', 'Invoice Payment', 'Full Payment', 'Refund') DEFAULT 'Invoice Payment',
    `appointment_id` INT DEFAULT NULL,
    `invoice_id` INT DEFAULT NULL,
    `patient_id` INT DEFAULT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `payment_method` ENUM('Cash', 'E-Wallet', 'Online', 'Credit Card', 'Debit Card', 'GCash') DEFAULT 'GCash',
    `transaction_id` VARCHAR(255) DEFAULT NULL,
    `payment_reference_number` VARCHAR(100) DEFAULT NULL,
    `payer_phone_number` VARCHAR(20) DEFAULT NULL,
    `payer_account_name` VARCHAR(100) DEFAULT NULL,
    `payment_status` ENUM('Pending', 'Completed', 'Failed', 'Refunded', 'Cancelled', 'Pending Verification') DEFAULT 'Pending Verification',
    `payment_date` DATETIME DEFAULT NULL,
    `external_txn_id` VARCHAR(100) DEFAULT NULL,
    `processor_name` VARCHAR(50) DEFAULT NULL,
    `staff_validator_id` INT DEFAULT NULL,
    `is_reconciled` TINYINT(1) DEFAULT 0,
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`payment_id`),
    INDEX `idx_appointment_id` (`appointment_id`),
    INDEX `idx_invoice_id` (`invoice_id`),
    INDEX `idx_patient_id` (`patient_id`),
    INDEX `idx_payment_type` (`payment_type`),
    INDEX `idx_payment_status` (`payment_status`),
    INDEX `idx_transaction_id` (`transaction_id`),
    INDEX `idx_payment_date` (`payment_date`),
    INDEX `idx_staff_validator_id` (`staff_validator_id`),
    INDEX `idx_is_reconciled` (`is_reconciled`),
    CONSTRAINT `fk_payment_appointment`
        FOREIGN KEY (`appointment_id`) 
        REFERENCES `tbl_appointments`(`appointment_id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE,
    CONSTRAINT `fk_payment_invoice`
        FOREIGN KEY (`invoice_id`) 
        REFERENCES `tbl_invoices`(`invoice_id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE,
    CONSTRAINT `fk_payment_patient`
        FOREIGN KEY (`patient_id`) 
        REFERENCES `tbl_patients`(`patient_id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE,
    CONSTRAINT `fk_payment_staff`
        FOREIGN KEY (`staff_validator_id`)
        REFERENCES `tbl_staff`(`staff_id`)
        ON DELETE SET NULL
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Note: CHECK constraint for ensuring at least one reference exists is commented out
-- because it requires MySQL 8.0.16+. For older versions, enforce this at application level.
-- If using MySQL 8.0.16+, you can add this constraint:
-- ALTER TABLE `tbl_payments`
-- ADD CONSTRAINT `chk_payment_reference` 
--     CHECK (
--         (`appointment_id` IS NOT NULL) OR 
--         (`invoice_id` IS NOT NULL) OR 
--         (`patient_id` IS NOT NULL)
--     );

-- ============================================
-- Migration Notes:
-- ============================================
-- If you have existing data:
-- 1. Migrate from tbl_appointment_downpayments:
--    INSERT INTO tbl_payments (payment_type, appointment_id, amount, payment_method, transaction_id, payment_status, payment_date, created_at, updated_at)
--    SELECT 'Downpayment', appointment_id, amount, payment_method, transaction_id, payment_status, payment_date, created_at, updated_at
--    FROM tbl_appointment_downpayments;
--
-- 2. Update existing tbl_payments to set payment_type:
--    UPDATE tbl_payments SET payment_type = 'Invoice Payment' WHERE invoice_id IS NOT NULL;
--
-- 3. After migration, drop the old table:
--    DROP TABLE IF EXISTS tbl_appointment_downpayments;

