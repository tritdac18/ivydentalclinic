-- Table to store pending registrations (waiting for email verification)
-- Registration data is stored here until email is verified
CREATE TABLE IF NOT EXISTS `tbl_pending_registrations` (
    `pending_id` INT NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `dob` DATE NOT NULL,
    `primary_phone` VARCHAR(20) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL COMMENT 'Hashed password',
    `blood_type` VARCHAR(10) DEFAULT 'Unknown',
    `gender` VARCHAR(50) DEFAULT 'Prefer not to say',
    `allergies` TEXT DEFAULT NULL COMMENT 'JSON array of allergies',
    `parent_name` VARCHAR(100) DEFAULT NULL,
    `parent_relationship` VARCHAR(50) DEFAULT NULL,
    `parent_phone` VARCHAR(20) DEFAULT NULL,
    `parent_email` VARCHAR(255) DEFAULT NULL,
    `parent_consent_agreed` TINYINT(1) DEFAULT 0,
    `verification_code` VARCHAR(6) NOT NULL COMMENT '6-digit verification code',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NOT NULL COMMENT 'Registration expires 24 hours after creation',
    `verified_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When email was verified',
    PRIMARY KEY (`pending_id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_verification_code` (`verification_code`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

