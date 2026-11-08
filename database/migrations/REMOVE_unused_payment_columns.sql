-- Remove unused columns from tbl_payments table
-- Based on user requirements

-- Step 1: Remove unused columns
ALTER TABLE `tbl_payments`
DROP COLUMN IF EXISTS `payment_reference_number`,
DROP COLUMN IF EXISTS `payer_phone_number`,
DROP COLUMN IF EXISTS `payer_account_name`,
DROP COLUMN IF EXISTS `external_txn_id`,
DROP COLUMN IF EXISTS `processor_name`,
DROP COLUMN IF EXISTS `is_reconciled`;

-- Step 2: Remove indexes that are no longer needed
-- Note: idx_is_reconciled can be dropped since we're removing the column
ALTER TABLE `tbl_payments`
DROP INDEX IF EXISTS `idx_is_reconciled`;

-- Step 3: Handle staff_validator_id (if you want to remove it)
-- First, drop the foreign key constraint, then the index, then the column
-- Uncomment the following lines if you want to remove staff_validator_id:

-- Step 3a: Find and drop the foreign key constraint
-- First, find the constraint name (run this query to find it):
-- SELECT CONSTRAINT_NAME 
-- FROM information_schema.KEY_COLUMN_USAGE 
-- WHERE TABLE_SCHEMA = DATABASE() 
-- AND TABLE_NAME = 'tbl_payments' 
-- AND COLUMN_NAME = 'staff_validator_id' 
-- AND REFERENCED_TABLE_NAME IS NOT NULL;

-- Step 3b: Drop the foreign key (replace 'fk_payment_staff' with actual constraint name if different)
-- ALTER TABLE `tbl_payments` DROP FOREIGN KEY `fk_payment_staff`;

-- Step 3c: Drop the index
-- ALTER TABLE `tbl_payments` DROP INDEX IF EXISTS `idx_staff_validator_id`;

-- Step 3d: Drop the column
-- ALTER TABLE `tbl_payments` DROP COLUMN IF EXISTS `staff_validator_id`;

-- Step 4: Handle overpayment_credit (if you want to remove it)
-- This column doesn't have foreign keys, so it can be dropped directly:
-- ALTER TABLE `tbl_payments` DROP COLUMN IF EXISTS `overpayment_credit`;

-- Note: staff_validator_id and overpayment_credit are kept for now.
-- Run CHECK_staff_validator_and_overpayment_usage.sql first to verify they're not needed.

