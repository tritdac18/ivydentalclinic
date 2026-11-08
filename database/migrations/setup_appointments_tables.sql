-- ============================================
-- Setup Tables for Appointment Booking System
-- ============================================

-- Step 1: Create tbl_branches table (if it doesn't exist)
CREATE TABLE IF NOT EXISTS `tbl_branches` (
    `branch_id` INT NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `address` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`branch_id`),
    INDEX `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Insert default branches (only if table is empty)
INSERT IGNORE INTO `tbl_branches` (`branch_id`, `name`, `address`) VALUES
(1, 'Baliwag', '123 Main St, Bulacan'),
(2, 'San Miguel', '456 Center Rd'),
(3, 'Sta. Maria', '789 North Ave');

-- Step 3: Create tbl_appointments table
CREATE TABLE IF NOT EXISTS `tbl_appointments` (
    `appointment_id` INT NOT NULL AUTO_INCREMENT,
    `patient_id` INT NOT NULL,
    `dentist_id` INT NOT NULL,
    `branch_id` INT NOT NULL,
    `scheduled_datetime` DATETIME NOT NULL,
    `duration_minutes` INT DEFAULT 60,
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
-- Notes:
-- ============================================
-- 1. Make sure tbl_patients and tbl_staff tables exist before running this
-- 2. The foreign keys will ensure data integrity
-- 3. Default status is 'Pending' for new appointments
-- 4. Default duration is 60 minutes
-- 5. Default triage_urgency is 'Low'

