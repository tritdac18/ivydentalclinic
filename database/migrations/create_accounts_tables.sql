-- Table for staff activation tokens
CREATE TABLE IF NOT EXISTS `tbl_staff_activation_tokens` (
  `token_id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `activation_token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `activation_token` (`activation_token`),
  KEY `staff_id` (`staff_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `tbl_staff_activation_tokens_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `tbl_staff` (`staff_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for clinic closure dates
CREATE TABLE IF NOT EXISTS `tbl_clinic_closures` (
  `closure_id` int(11) NOT NULL AUTO_INCREMENT,
  `closure_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`closure_id`),
  UNIQUE KEY `closure_date` (`closure_date`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `tbl_clinic_closures_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `tbl_staff` (`staff_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table for dentist schedules
CREATE TABLE IF NOT EXISTS `tbl_dentist_schedules` (
  `schedule_id` int(11) NOT NULL AUTO_INCREMENT,
  `dentist_id` int(11) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  KEY `dentist_id` (`dentist_id`),
  KEY `day_of_week` (`day_of_week`),
  CONSTRAINT `tbl_dentist_schedules_ibfk_1` FOREIGN KEY (`dentist_id`) REFERENCES `tbl_staff` (`staff_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

