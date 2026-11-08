-- Create tbl_staff table (without branches)
CREATE TABLE IF NOT EXISTS `tbl_staff` (
    `staff_id` INT NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('Manager', 'Dentist', 'Receptionist') NOT NULL,
    `license_number` VARCHAR(50),
    `phone_number` VARCHAR(20),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`staff_id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`),
    INDEX `idx_email` (`email`),
    INDEX `idx_username` (`username`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

