-- Add GCash payment fields to existing tbl_payments table
-- Run this if you already have tbl_payments table

ALTER TABLE `tbl_payments` 
ADD COLUMN `payment_reference_number` VARCHAR(100) DEFAULT NULL AFTER `transaction_id`,
ADD COLUMN `payer_phone_number` VARCHAR(20) DEFAULT NULL AFTER `payment_reference_number`,
ADD COLUMN `payer_account_name` VARCHAR(100) DEFAULT NULL AFTER `payer_phone_number`,
ADD COLUMN `payment_method` ENUM('Cash', 'E-Wallet', 'Online', 'Credit Card', 'Debit Card', 'GCash') DEFAULT 'GCash' AFTER `amount`,
MODIFY COLUMN `payment_status` ENUM('Pending', 'Completed', 'Failed', 'Refunded', 'Cancelled', 'Pending Verification') DEFAULT 'Pending Verification';

-- Add indexes for new fields
ALTER TABLE `tbl_payments`
ADD INDEX `idx_payment_reference` (`payment_reference_number`),
ADD INDEX `idx_payer_phone` (`payer_phone_number`);

