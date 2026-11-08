-- ============================================
-- Create tbl_invoices Table
-- Database: db_ivydental
-- ============================================
-- This script creates the tbl_invoices table based on the data dictionary

USE `db_ivydental`;

-- Create tbl_invoices table
CREATE TABLE IF NOT EXISTS `tbl_invoices` (
    `invoice_id` INT NOT NULL AUTO_INCREMENT,
    `patient_id` INT DEFAULT NULL,
    `appointment_id` INT DEFAULT NULL,
    `total_amount` DECIMAL(10, 2) DEFAULT NULL,
    `payment_status` ENUM('Unpaid', 'Paid', 'Partial', 'Collection') DEFAULT NULL,
    `generated_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`invoice_id`),
    INDEX `idx_patient_id` (`patient_id`),
    INDEX `idx_appointment_id` (`appointment_id`),
    INDEX `idx_payment_status` (`payment_status`),
    INDEX `idx_generated_at` (`generated_at`),
    CONSTRAINT `fk_invoice_patient`
        FOREIGN KEY (`patient_id`) 
        REFERENCES `tbl_patients`(`patient_id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE,
    CONSTRAINT `fk_invoice_appointment`
        FOREIGN KEY (`appointment_id`) 
        REFERENCES `tbl_appointments`(`appointment_id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Verify table creation
-- ============================================
SELECT 'tbl_invoices table created successfully!' AS message;

