-- ============================================
-- Recreate tbl_appointments Table
-- Database: db_ivydental
-- ============================================
-- This script recreates the tbl_appointments table
-- Make sure tbl_patients, tbl_staff, and tbl_branches exist first

USE `db_ivydental`;

-- Drop the table if it exists (WARNING: This will delete all appointment data!)
DROP TABLE IF EXISTS `tbl_appointments`;

-- Create tbl_appointments table (with service_type and price columns)
CREATE TABLE `tbl_appointments` (
    `appointment_id` INT NOT NULL AUTO_INCREMENT,
    `patient_id` INT NOT NULL,
    `dentist_id` INT NOT NULL,
    `branch_id` INT NOT NULL,
    `scheduled_datetime` DATETIME NOT NULL,
    `duration_minutes` INT DEFAULT 60,
    `service_type` VARCHAR(100),
    `price` DECIMAL(10, 2),
    `triage_urgency` ENUM('Low', 'Medium', 'High', 'Emergency') DEFAULT 'Low',
    `status` ENUM('Pending', 'Scheduled', 'Confirmed', 'Completed', 'Cancelled', 'No-Show') NOT NULL DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`appointment_id`),
    FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients`(`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`dentist_id`) REFERENCES `tbl_staff`(`staff_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (`branch_id`) REFERENCES `tbl_branches`(`branch_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_patient_id` (`patient_id`),
    INDEX `idx_dentist_id` (`dentist_id`),
    INDEX `idx_branch_id` (`branch_id`),
    INDEX `idx_scheduled_datetime` (`scheduled_datetime`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Verify table creation
-- ============================================
SELECT 'tbl_appointments table created successfully!' AS message;
DESCRIBE `tbl_appointments`;

