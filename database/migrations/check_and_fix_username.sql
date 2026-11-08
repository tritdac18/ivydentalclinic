-- ============================================
-- Check Current State and Fix Username Issues
-- ============================================
-- Run this first to see what's already in your database

-- Check if username column exists in tbl_patients
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN 'Username column EXISTS in tbl_patients'
        ELSE 'Username column DOES NOT EXIST in tbl_patients'
    END AS status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'tbl_patients' 
AND COLUMN_NAME = 'username';

-- Check all indexes on username in tbl_patients
SELECT 
    INDEX_NAME,
    COLUMN_NAME,
    NON_UNIQUE,
    'tbl_patients' AS table_name
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'tbl_patients' 
AND COLUMN_NAME = 'username';

-- Check if username column exists in tbl_staff
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN 'Username column EXISTS in tbl_staff'
        ELSE 'Username column DOES NOT EXIST in tbl_staff'
    END AS status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'tbl_staff' 
AND COLUMN_NAME = 'username';

-- Check all indexes on username in tbl_staff
SELECT 
    INDEX_NAME,
    COLUMN_NAME,
    NON_UNIQUE,
    'tbl_staff' AS table_name
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'tbl_staff' 
AND COLUMN_NAME = 'username';

-- ============================================
-- If you see duplicate indexes, run these to fix:
-- ============================================

-- Remove duplicate indexes from tbl_patients (uncomment as needed)
-- DROP INDEX `username` ON `tbl_patients`;
-- DROP INDEX `idx_username` ON `tbl_patients`;

-- Remove duplicate indexes from tbl_staff (uncomment as needed)
-- DROP INDEX `username` ON `tbl_staff`;
-- DROP INDEX `idx_username` ON `tbl_staff`;

-- Then add the correct unique index:
-- ALTER TABLE `tbl_patients` ADD UNIQUE INDEX `username` (`username`);
-- ALTER TABLE `tbl_staff` ADD UNIQUE INDEX `username` (`username`);

