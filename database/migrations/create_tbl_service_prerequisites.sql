-- Create table for service prerequisites
-- This table defines which services require prerequisite check-ups or exams
CREATE TABLE IF NOT EXISTS `tbl_service_prerequisites` (
    `prerequisite_id` INT NOT NULL AUTO_INCREMENT,
    `service_id` INT NOT NULL,
    `required_service_id` INT NOT NULL,
    `is_mandatory` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`prerequisite_id`),
    FOREIGN KEY (`service_id`) REFERENCES `tbl_service_catalog`(`service_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (`required_service_id`) REFERENCES `tbl_service_catalog`(`service_id`) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY `uq_service_prerequisite` (`service_id`, `required_service_id`),
    INDEX `idx_service_id` (`service_id`),
    INDEX `idx_required_service_id` (`required_service_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Example: Brace attachments require check-ups beforehand
-- INSERT INTO tbl_service_prerequisites (service_id, required_service_id, is_mandatory) 
-- SELECT s1.service_id, s2.service_id, 1
-- FROM tbl_service_catalog s1, tbl_service_catalog s2
-- WHERE s1.service_name LIKE '%Brace%' OR s1.service_name LIKE '%Attachment%'
-- AND s2.service_name LIKE '%Check-up%' OR s2.service_name LIKE '%Consultation%';

