-- Create tbl_patient_allergies table
CREATE TABLE IF NOT EXISTS `tbl_patient_allergies` (
    `allergy_id` INT NOT NULL AUTO_INCREMENT,
    `patient_id` INT NOT NULL,
    `allergy_name` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`allergy_id`),
    FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients`(`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_patient_id` (`patient_id`),
    INDEX `idx_allergy_name` (`allergy_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

