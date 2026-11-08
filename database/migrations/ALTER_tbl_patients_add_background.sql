-- Add blood_type and gender columns to existing tbl_patients table
-- Run this if you already have tbl_patients table and want to add these columns

ALTER TABLE `tbl_patients` 
ADD COLUMN `blood_type` ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown') DEFAULT 'Unknown' AFTER `password`,
ADD COLUMN `gender` ENUM('Male', 'Female', 'Other', 'Prefer not to say') DEFAULT 'Prefer not to say' AFTER `blood_type`;

