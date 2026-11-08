-- Create tbl_branches table (if it doesn't exist)
-- This table is referenced by tbl_appointments
CREATE TABLE IF NOT EXISTS `tbl_branches` (
    `branch_id` INT NOT NULL AUTO_INCREMENT,
    `branch_name` VARCHAR(100) NOT NULL,
    `address` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20) DEFAULT NULL,
    `email` VARCHAR(100) DEFAULT NULL,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`branch_id`),
    INDEX `idx_branch_name` (`branch_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default branches (optional - only if table is empty)
INSERT INTO `tbl_branches` (`branch_name`, `address`) VALUES
('Baliwag', '123 Main St, Bulacan'),
('San Miguel', '456 Center Rd'),
('Sta. Maria', '789 North Ave')
ON DUPLICATE KEY UPDATE `branch_name`=`branch_name`;

