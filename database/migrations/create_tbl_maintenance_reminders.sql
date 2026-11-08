-- Table for tracking maintenance reminder emails sent to patients
-- This prevents duplicate reminders and tracks reminder history

CREATE TABLE IF NOT EXISTS `tbl_maintenance_reminders` (
  `reminder_id` INT NOT NULL AUTO_INCREMENT,
  `maintenance_id` INT NOT NULL,
  `patient_id` INT NOT NULL,
  `reminder_type` ENUM('first', 'second', 'overdue') NOT NULL COMMENT 'Type of reminder: first (7-10 days before), second (3-5 days before), overdue (7 days after)',
  `scheduled_send_date` DATE NOT NULL COMMENT 'The date when this reminder should be sent',
  `sent_at` DATETIME NULL COMMENT 'When the reminder was actually sent',
  `email_sent` TINYINT(1) DEFAULT 0 COMMENT 'Whether the email was successfully sent',
  `email_error` TEXT NULL COMMENT 'Error message if email sending failed',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reminder_id`),
  KEY `idx_maintenance_id` (`maintenance_id`),
  KEY `idx_patient_id` (`patient_id`),
  KEY `idx_scheduled_send_date` (`scheduled_send_date`),
  KEY `idx_email_sent` (`email_sent`),
  CONSTRAINT `fk_reminder_maintenance` FOREIGN KEY (`maintenance_id`) REFERENCES `tbl_patient_maintenance` (`maintenance_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reminder_patient` FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients` (`patient_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

