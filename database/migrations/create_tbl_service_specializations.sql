-- Junction table for service ↔ specialization (many-to-many)
-- Services can require multiple specializations
-- Staff must have at least one of the required specializations to perform the service

CREATE TABLE IF NOT EXISTS `tbl_service_specializations` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `service_id` INT NOT NULL,
  `specialization_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_spec_unique` (`service_id`, `specialization_id`),
  KEY `idx_service` (`service_id`),
  KEY `idx_specialization` (`specialization_id`),
  CONSTRAINT `fk_service_specializations_service` FOREIGN KEY (`service_id`) REFERENCES `tbl_service_catalog` (`service_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_service_specializations_spec` FOREIGN KEY (`specialization_id`) REFERENCES `tbl_specializations` (`specialization_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

