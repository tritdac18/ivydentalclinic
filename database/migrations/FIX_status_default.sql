-- Fix the default status value to prevent bypassing verification
-- Change default from 'active' to 'pending_verification'
-- This ensures that even if status is not explicitly set, it defaults to pending

ALTER TABLE `tbl_patients` 
MODIFY COLUMN `status` ENUM('pending_verification', 'active', 'inactive') DEFAULT 'pending_verification';

