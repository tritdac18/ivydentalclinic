-- Add discount verification tracking to payments table
ALTER TABLE `tbl_payments`
ADD COLUMN `is_subject_to_discount_verification` TINYINT(1) DEFAULT 0 COMMENT '1 if payment is subject to discount verification (downpayments at standard rate)' AFTER `is_reconciled`,
ADD COLUMN `discount_verified_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When discount was verified and applied' AFTER `is_subject_to_discount_verification`,
ADD COLUMN `discount_verified_by_staff_id` INT DEFAULT NULL COMMENT 'Staff who verified the discount' AFTER `discount_verified_at`,
ADD COLUMN `discount_amount` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Discount amount applied after verification' AFTER `discount_verified_by_staff_id`,
ADD COLUMN `overpayment_credit` DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Overpayment amount credited to patient account' AFTER `discount_amount`,
ADD INDEX `idx_discount_verification` (`is_subject_to_discount_verification`, `discount_verified_at`),
ADD FOREIGN KEY (`discount_verified_by_staff_id`) REFERENCES `tbl_staff`(`staff_id`) ON DELETE SET NULL ON UPDATE CASCADE;

