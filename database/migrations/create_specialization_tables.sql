-- Table for specialization definitions
CREATE TABLE IF NOT EXISTS `tbl_specializations` (
  `specialization_id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`specialization_id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Junction table for staff ↔ specialization (many-to-many)
CREATE TABLE IF NOT EXISTS `tbl_staff_specializations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `staff_id` INT NOT NULL,
  `specialization_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_spec_unique` (`staff_id`, `specialization_id`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_spec` (`specialization_id`),
  CONSTRAINT `fk_staff_specializations_staff` FOREIGN KEY (`staff_id`) REFERENCES `tbl_staff` (`staff_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_staff_specializations_spec` FOREIGN KEY (`specialization_id`) REFERENCES `tbl_specializations` (`specialization_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

