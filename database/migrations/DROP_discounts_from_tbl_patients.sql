-- Drop discount columns and foreign keys from tbl_patients
-- IMPORTANT: Run Step 1 first to find the exact constraint names!

-- Step 1: Find foreign key constraint names
-- Run this query and copy the constraint names from the output:
SHOW CREATE TABLE `tbl_patients`;

-- Look for lines like:
-- CONSTRAINT `tbl_patients_ibfk_1` FOREIGN KEY (`pwd_verified_by_staff_id`) REFERENCES `tbl_staff`(`staff_id`)
-- CONSTRAINT `tbl_patients_ibfk_2` FOREIGN KEY (`senior_citizen_verified_by_staff_id`) REFERENCES `tbl_staff`(`staff_id`)

-- Step 2: Drop foreign key constraints
-- Replace the constraint names below with the ACTUAL names from Step 1
-- Common names are: tbl_patients_ibfk_1, tbl_patients_ibfk_2, etc.

-- EXAMPLE (replace with your actual constraint names):
-- ALTER TABLE `tbl_patients` DROP FOREIGN KEY `tbl_patients_ibfk_1`;
-- ALTER TABLE `tbl_patients` DROP FOREIGN KEY `tbl_patients_ibfk_2`;

-- Uncomment and modify these lines with your actual constraint names:
ALTER TABLE `tbl_patients` DROP FOREIGN KEY `CONSTRAINT_NAME_FOR_PWD`;
ALTER TABLE `tbl_patients` DROP FOREIGN KEY `CONSTRAINT_NAME_FOR_SENIOR`;

-- Step 3: After dropping foreign keys, drop all discount columns
ALTER TABLE `tbl_patients`
DROP COLUMN `pwd_id_number`,
DROP COLUMN `pwd_verified_by_staff_id`,
DROP COLUMN `pwd_verified_at`,
DROP COLUMN `senior_citizen_id_number`,
DROP COLUMN `senior_citizen_verified_by_staff_id`,
DROP COLUMN `senior_citizen_verified_at`;

-- Step 4: Verify columns are removed
DESCRIBE `tbl_patients`;

