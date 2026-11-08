-- ============================================
-- Create tbl_payments Table
-- Database: db_ivydental
-- ============================================
-- This script creates the tbl_payments table based on the data dictionary

USE `db_ivydental`;

-- Create tbl_payments table
CREATE TABLE IF NOT EXISTS `tbl_payments` (
    `payment_id` INT NOT NULL AUTO_INCREMENT,
    `invoice_id` INT DEFAULT NULL,
    `amount` DECIMAL(10, 2) DEFAULT NULL,
    `payment_method` ENUM('Cash', 'E-Wallet') DEFAULT NULL,
    `transaction_date` DATETIME DEFAULT NULL,
    `external_txn_id` VARCHAR(100) DEFAULT NULL,
    `processor_name` VARCHAR(50) DEFAULT NULL,
    `staff_validator_id` INT DEFAULT NULL,
    `is_reconciled` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`payment_id`),
    INDEX `idx_invoice_id` (`invoice_id`),
    INDEX `idx_staff_validator_id` (`staff_validator_id`),
    INDEX `idx_transaction_date` (`transaction_date`),
    INDEX `idx_is_reconciled` (`is_reconciled`),
    CONSTRAINT `fk_payment_invoice`
        FOREIGN KEY (`invoice_id`) 
        REFERENCES `tbl_invoices`(`invoice_id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE,
    CONSTRAINT `fk_payment_staff`
        FOREIGN KEY (`staff_validator_id`) 
        REFERENCES `tbl_staff`(`staff_id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Verify table creation
-- ============================================
SELECT 'tbl_payments table created successfully!' AS message;

