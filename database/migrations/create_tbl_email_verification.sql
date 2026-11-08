-- Table to store email verification codes
-- Codes expire after 15 minutes
CREATE TABLE IF NOT EXISTS `tbl_email_verification` (
    `verification_id` INT NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `verification_code` VARCHAR(6) NOT NULL COMMENT '6-digit code',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL COMMENT 'Code expires 15 minutes after creation',
    `verified_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When code was verified',
    `is_used` TINYINT(1) DEFAULT 0 COMMENT '1 if code has been used',
    PRIMARY KEY (`verification_id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_code` (`verification_code`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

