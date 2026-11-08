-- Add missing columns to tbl_payments if they don't exist
-- We use a stored procedure to check existence before adding to avoid errors

DROP PROCEDURE IF EXISTS UpgradePaymentSchema;

DELIMITER //

CREATE PROCEDURE UpgradePaymentSchema()
BEGIN
    -- 1. Ensure payment_type exists
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'tbl_payments' 
        AND COLUMN_NAME = 'payment_type'
    ) THEN
        ALTER TABLE tbl_payments ADD COLUMN `payment_type` ENUM('Downpayment', 'Invoice Payment', 'Other') DEFAULT 'Other' AFTER `payment_id`;
    END IF;

    -- 2. Ensure appointment_id exists
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'tbl_payments' 
        AND COLUMN_NAME = 'appointment_id'
    ) THEN
        ALTER TABLE tbl_payments ADD COLUMN `appointment_id` INT DEFAULT NULL AFTER `invoice_id`;
        -- Add index and FK if needed
        ALTER TABLE tbl_payments ADD INDEX `idx_appointment_id` (`appointment_id`);
    END IF;

    -- 3. Ensure patient_id exists
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'tbl_payments' 
        AND COLUMN_NAME = 'patient_id'
    ) THEN
        ALTER TABLE tbl_payments ADD COLUMN `patient_id` INT DEFAULT NULL AFTER `appointment_id`;
        ALTER TABLE tbl_payments ADD INDEX `idx_patient_id` (`patient_id`);
    END IF;

    -- 4. Ensure transaction_id exists (for PayMongo)
    IF NOT EXISTS (
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'tbl_payments' 
        AND COLUMN_NAME = 'transaction_id'
    ) THEN
        ALTER TABLE tbl_payments ADD COLUMN `transaction_id` VARCHAR(100) DEFAULT NULL AFTER `external_txn_id`;
        ALTER TABLE tbl_payments ADD INDEX `idx_transaction_id` (`transaction_id`);
    END IF;
    
    -- 5. Drop is_reconciled if it exists
    IF EXISTS (
        SELECT * FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'tbl_payments' 
        AND COLUMN_NAME = 'is_reconciled'
    ) THEN
        ALTER TABLE tbl_payments DROP COLUMN `is_reconciled`;
    END IF;

END//

DELIMITER ;

CALL UpgradePaymentSchema();
DROP PROCEDURE UpgradePaymentSchema;
