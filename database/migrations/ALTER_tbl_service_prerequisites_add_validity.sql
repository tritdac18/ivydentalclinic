-- Add validity_period_days column to service prerequisites table
-- This defines how many days a prerequisite appointment remains valid
-- Default: 30 days for consultation-based prerequisites

ALTER TABLE `tbl_service_prerequisites`
ADD COLUMN `validity_period_days` INT DEFAULT 30 COMMENT 'Number of days the prerequisite appointment remains valid. NULL means no expiration.';

-- Update existing records to have 30 days validity for consultation prerequisites
UPDATE `tbl_service_prerequisites` sp
INNER JOIN `tbl_service_catalog` sc ON sp.required_service_id = sc.service_id
SET sp.validity_period_days = 30
WHERE sc.service_name LIKE '%Consultation%' OR sc.service_name LIKE '%consultation%';

