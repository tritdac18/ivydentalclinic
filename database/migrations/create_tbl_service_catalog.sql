

CREATE TABLE IF NOT EXISTS `tbl_service_catalog` (
    `service_id` INT NOT NULL AUTO_INCREMENT,
    `service_name` VARCHAR(150) NOT NULL,
    `base_price` DECIMAL(10, 2) NOT NULL,
    `default_duration_minutes` INT DEFAULT 60 COMMENT 'Default duration for this service in minutes',
    `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Whether this service is currently available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`service_id`),
    INDEX `idx_service_name` (`service_name`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO `tbl_service_catalog` (`service_id`, `service_name`, `base_price`, `default_duration_minutes`) VALUES
(1, 'General Checkup', 500.00, 30),
(2, 'Cleaning', 800.00, 45),
(3, 'Extraction', 1000.00, 30),
(4, 'Consultation', 300.00, 20),
(5, 'Root Canal', 5000.00, 120),
(6, 'Teeth Whitening', 3500.00, 90);

-- ============================================
-- Verify the table
-- ============================================
SELECT 'tbl_service_catalog table created successfully!' AS message;
SELECT * FROM `tbl_service_catalog` ORDER BY `service_id`;

