-- Table for tracking patient maintenance visits
-- Links patients to services that require ongoing maintenance

CREATE TABLE IF NOT EXISTS `tbl_patient_maintenance` (
  `maintenance_id` INT NOT NULL AUTO_INCREMENT,
  `patient_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `related_appointment_id` INT NULL COMMENT 'The appointment that initiated this maintenance plan',
  `last_visit_date` DATE NULL,
  `recommended_next_visit` DATE NULL,
  `status` ENUM('pending', 'done', 'overdue', 'cancelled') DEFAULT 'pending',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`maintenance_id`),
  KEY `idx_patient_id` (`patient_id`),
  KEY `idx_service_id` (`service_id`),
  KEY `idx_appointment_id` (`related_appointment_id`),
  KEY `idx_status` (`status`),
  KEY `idx_next_visit` (`recommended_next_visit`),
  CONSTRAINT `fk_patient_maintenance_patient` FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients` (`patient_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_patient_maintenance_service` FOREIGN KEY (`service_id`) REFERENCES `tbl_service_catalog` (`service_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_patient_maintenance_appointment` FOREIGN KEY (`related_appointment_id`) REFERENCES `tbl_appointments` (`appointment_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

