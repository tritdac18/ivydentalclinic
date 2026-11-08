-- Optional: Add service_type and price columns to tbl_appointments
-- Run this if you want to store service information in the database
-- This script checks if columns exist before adding them

-- Check and add service_type column if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = 'tbl_appointments';
SET @columnname = 'service_type';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column service_type already exists.' AS result;",
  CONCAT("ALTER TABLE `", @tablename, "` ADD COLUMN `", @columnname, "` VARCHAR(100) DEFAULT NULL AFTER `status`;")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check and add price column if it doesn't exist
SET @columnname = 'price';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  "SELECT 'Column price already exists.' AS result;",
  CONCAT("ALTER TABLE `", @tablename, "` ADD COLUMN `", @columnname, "` DECIMAL(10, 2) DEFAULT 0.00 AFTER `service_type`;")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add index if it doesn't exist
SET @indexname = 'idx_service_type';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = @indexname)
  ) > 0,
  "SELECT 'Index idx_service_type already exists.' AS result;",
  CONCAT("ALTER TABLE `", @tablename, "` ADD INDEX `", @indexname, "` (`service_type`);")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Note: After adding these columns, you'll need to update book_appointment.php
-- to include service_type and price in the INSERT statement

