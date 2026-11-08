-- Simple version: Add service_type and price columns to tbl_appointments
-- Only run this if the columns don't already exist
-- If you get "Duplicate column name" error, the columns already exist - that's fine!

-- Add service_type column (only if it doesn't exist)
ALTER TABLE `tbl_appointments` 
ADD COLUMN IF NOT EXISTS `service_type` VARCHAR(100) DEFAULT NULL AFTER `status`;

-- Add price column (only if it doesn't exist)
ALTER TABLE `tbl_appointments` 
ADD COLUMN IF NOT EXISTS `price` DECIMAL(10, 2) DEFAULT 0.00 AFTER `service_type`;

-- Add index (only if it doesn't exist)
-- Note: MySQL doesn't support "IF NOT EXISTS" for indexes, so this might error if index exists
-- That's okay - just ignore the error if it says the index already exists
CREATE INDEX `idx_service_type` ON `tbl_appointments` (`service_type`);

-- Note: After adding these columns, you'll need to update book_appointment.php
-- to include service_type and price in the INSERT statement

