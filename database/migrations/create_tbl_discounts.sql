-- Create tbl_discounts table
CREATE TABLE IF NOT EXISTS `tbl_discounts` (
    `discount_id` INT NOT NULL AUTO_INCREMENT,
    `patient_id` INT NOT NULL,
    `discount_type` ENUM('PWD', 'Senior Citizen', 'Other') NOT NULL,
    `id_number` VARCHAR(100) NOT NULL,
    `is_verified` TINYINT(1) DEFAULT 0,
    `verified_by_staff_id` INT DEFAULT NULL,
    `verified_at` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`discount_id`),
    INDEX `idx_patient_id` (`patient_id`),
    INDEX `idx_discount_type` (`discount_type`),
    CONSTRAINT `fk_discount_patient`
        FOREIGN KEY (`patient_id`) 
        REFERENCES `tbl_patients`(`patient_id`) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
    CONSTRAINT `fk_discount_verifier`
        FOREIGN KEY (`verified_by_staff_id`) 
        REFERENCES `tbl_staff`(`staff_id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
