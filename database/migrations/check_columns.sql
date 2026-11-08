-- Check if service_type and price columns exist in tbl_appointments
-- Run this to see the current structure of the table

DESCRIBE `tbl_appointments`;

-- Or check specific columns:
SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE, 
    COLUMN_DEFAULT
FROM 
    INFORMATION_SCHEMA.COLUMNS
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'tbl_appointments'
    AND COLUMN_NAME IN ('service_type', 'price');

