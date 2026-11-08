-- ============================================
-- Add Username Column to tbl_patients and tbl_staff
-- ============================================
-- This script handles both new and existing tables

-- Step 1: Drop existing tables if you want a fresh start
-- WARNING: This will delete all data! Uncomment only if you want to start fresh
-- DROP TABLE IF EXISTS `tbl_appointments`;
-- DROP TABLE IF EXISTS `tbl_patients`;
-- DROP TABLE IF EXISTS `tbl_staff`;

-- Step 2: Create or recreate tbl_patients with username column
DROP TABLE IF EXISTS `tbl_patients`;

CREATE TABLE `tbl_patients` (
    `patient_id` INT NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `dob` DATE,
    `primary_phone` VARCHAR(20),
    `email` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`patient_id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`),
    INDEX `idx_email` (`email`),
    INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Create or recreate tbl_staff with username column
DROP TABLE IF EXISTS `tbl_staff`;

CREATE TABLE `tbl_staff` (
    `staff_id` INT NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('Manager', 'Dentist', 'Receptionist', 'Hygienist') NOT NULL DEFAULT 'Receptionist',
    `primary_branch_id` INT,
    `license_number` VARCHAR(50),
    `phone_number` VARCHAR(20),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`staff_id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`),
    INDEX `idx_email` (`email`),
    INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
