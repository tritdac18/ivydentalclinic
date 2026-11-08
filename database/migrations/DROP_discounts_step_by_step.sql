-- Step-by-step script to drop discount columns
-- Run each step separately and check for errors

-- STEP 1: Show table structure to find foreign key names
SHOW CREATE TABLE `tbl_patients`;

-- Copy the constraint names from the output (look for CONSTRAINT `name` FOREIGN KEY)
-- They'll be something like: tbl_patients_ibfk_1, tbl_patients_ibfk_2, etc.

-- STEP 2: Drop foreign key for pwd_verified_by_staff_id
-- Replace 'CONSTRAINT_NAME_1' with the actual name from Step 1
ALTER TABLE `tbl_patients` DROP FOREIGN KEY `CONSTRAINT_NAME_1`;

-- STEP 3: Drop foreign key for senior_citizen_verified_by_staff_id  
-- Replace 'CONSTRAINT_NAME_2' with the actual name from Step 1
ALTER TABLE `tbl_patients` DROP FOREIGN KEY `CONSTRAINT_NAME_2`;

-- STEP 4: Now drop all discount columns
ALTER TABLE `tbl_patients`
DROP COLUMN `pwd_id_number`,
DROP COLUMN `pwd_verified_by_staff_id`,
DROP COLUMN `pwd_verified_at`,
DROP COLUMN `senior_citizen_id_number`,
DROP COLUMN `senior_citizen_verified_by_staff_id`,
DROP COLUMN `senior_citizen_verified_at`;

-- STEP 5: Verify
DESCRIBE `tbl_patients`;

