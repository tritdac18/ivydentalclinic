-- Create tbl_appointments table (without branches)
CREATE TABLE IF NOT EXISTS `tbl_appointments` (
    `appointment_id` INT NOT NULL AUTO_INCREMENT,
    `patient_id` INT NOT NULL,
    `dentist_id` INT NOT NULL,
    `scheduled_datetime` DATETIME NOT NULL,
    `duration_minutes` INT DEFAULT 60,
    `service_type` VARCHAR(100),
    `price` DECIMAL(10, 2),
    `service_id` INT,
    `triage_urgency` ENUM('Low', 'Medium', 'High', 'Emergency') DEFAULT 'Low',
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

-- Note: If you have tbl_service_catalog table, you can add this foreign key:
-- ALTER TABLE `tbl_appointments`
-- ADD CONSTRAINT `fk_appointments_service_id` 
--     FOREIGN KEY (`service_id`) 
--     REFERENCES `tbl_service_catalog`(`service_id`) 
--     ON DELETE RESTRICT 
--     ON UPDATE CASCADE;

