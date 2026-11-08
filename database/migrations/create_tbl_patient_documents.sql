-- Create table for patient documents
-- Stores documents like parent consent (files may be uploaded later)
-- Note: PWD and Senior Citizen discounts are stored as ID numbers in tbl_patients table
CREATE TABLE IF NOT EXISTS `tbl_patient_documents` (
    `document_id` INT NOT NULL AUTO_INCREMENT,
    `patient_id` INT NOT NULL,
    `document_type` ENUM('Parent Consent') NOT NULL,
    `file_name` VARCHAR(255) DEFAULT NULL,
    `file_path` VARCHAR(500) DEFAULT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `uploaded_by` INT DEFAULT NULL COMMENT 'Staff ID who uploaded the document',
    `notes` TEXT DEFAULT NULL,
    PRIMARY KEY (`document_id`),
    FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients`(`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX `idx_patient_id` (`patient_id`),
    INDEX `idx_document_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

