-- Quick script: Drop discount columns (run Step 1 first to get constraint names)

-- STEP 1: Get constraint names (run this first!)
SHOW CREATE TABLE `tbl_patients`\G

-- STEP 2: Copy constraint names from Step 1 output and paste them below
-- Then uncomment these lines and replace CONSTRAINT_NAME with actual names:

-- ALTER TABLE `tbl_patients` DROP FOREIGN KEY `CONSTRAINT_NAME_1`;
-- ALTER TABLE `tbl_patients` DROP FOREIGN KEY `CONSTRAINT_NAME_2`;

-- STEP 3: Drop columns (run this after Step 2)
ALTER TABLE `tbl_patients`
DROP COLUMN `pwd_id_number`,
DROP COLUMN `pwd_verified_by_staff_id`,
DROP COLUMN `pwd_verified_at`,
DROP COLUMN `senior_citizen_id_number`,
DROP COLUMN `senior_citizen_verified_by_staff_id`,
DROP COLUMN `senior_citizen_verified_at`;

