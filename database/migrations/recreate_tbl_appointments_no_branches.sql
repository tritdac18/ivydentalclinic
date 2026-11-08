-- ============================================
-- Recreate tbl_appointments Table (Without Branches)
-- Database: db_ivydental
-- ============================================
-- This script recreates the tbl_appointments table without branch_id
-- Make sure tbl_patients and tbl_staff exist first

USE `db_ivydental`;

-- Drop the table if it exists (WARNING: This will delete all appointment data!)
DROP TABLE IF EXISTS `tbl_appointments`;

-- Create tbl_appointments table (without branch_id)
CREATE TABLE `tbl_appointments` (
    `appointment_id` INT NOT NULL AUTO_INCREMENT,
    `patient_id` INT NOT NULL,
    `dentist_id` INT NOT NULL,
    `scheduled_datetime` DATETIME NOT NULL,
    `duration_minutes` INT DEFAULT 60,
    `service_type` VARCHAR(100),
    `price` DECIMAL(10, 2),
    `service_id` INT,
    `status` ENUM('Pending', 'Scheduled', 'Confirmed', 'Completed', 'Cancelled', 'No-Show') NOT NULL DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`appointment_id`),
    FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients`(`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`dentist_id`) REFERENCES `tbl_staff`(`staff_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_patient_id` (`patient_id`),
    INDEX `idx_dentist_id` (`dentist_id`),
    INDEX `idx_scheduled_datetime` (`scheduled_datetime`),
    INDEX `idx_status` (`status`),
    INDEX `idx_service_id` (`service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key for service_id if tbl_service_catalog exists
-- Uncomment the following if you have tbl_service_catalog table:
-- ALTER TABLE `tbl_appointments`
-- ADD CONSTRAINT `fk_appointments_service_id` 
--     FOREIGN KEY (`service_id`) 
--     REFERENCES `tbl_service_catalog`(`service_id`) 
--     ON DELETE RESTRICT 
--     ON UPDATE CASCADE;

-- ============================================
-- Verify table creation
-- ============================================
SELECT 'tbl_appointments table created successfully (without branches)!' AS message;
DESCRIBE `tbl_appointments`;



