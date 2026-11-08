-- Remove overpayment_credit column from tbl_payments
-- Run this ONLY after confirming it's not needed (use CHECK_staff_validator_and_overpayment_usage.sql)

-- This column doesn't have foreign keys or indexes, so it can be dropped directly
ALTER TABLE `tbl_payments` DROP COLUMN IF EXISTS `overpayment_credit`;

