-- Add maintenance period and interval fields to service catalog
-- These fields define the treatment plan requirements for services

ALTER TABLE `tbl_service_catalog`
ADD COLUMN `maintenance_period_months` INT NULL COMMENT 'How long the service treatment plan lasts (in months). NULL means no maintenance required.',
ADD COLUMN `maintenance_interval` ENUM('weekly', 'biweekly', 'monthly', 'bimonthly', 'quarterly', 'semiannually', 'annually') NULL COMMENT 'How often patients should return for maintenance visits. NULL means no maintenance interval.',
ADD INDEX `idx_maintenance_period` (`maintenance_period_months`);

