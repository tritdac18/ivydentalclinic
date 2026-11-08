-- Remove discount columns from tbl_patients
-- This version doesn't use information_schema (for shared hosting)
-- Run this script step by step, commenting out steps that fail

-- Step 1: Try to drop foreign key constraints
-- Replace 'CONSTRAINT_NAME_HERE' with actual constraint names
-- Common names: tbl_patients_ibfk_1, tbl_patients_ibfk_2, fk_patient_pwd_verifier, fk_patient_senior_verifier
-- If you don't know the names, try running DESCRIBE tbl_patients first to see the structure

-- Uncomment and modify these lines with your actual constraint names:
-- ALTER TABLE `tbl_patients` DROP FOREIGN KEY `tbl_patients_ibfk_1`;
-- ALTER TABLE `tbl_patients` DROP FOREIGN KEY `tbl_patients_ibfk_2`;

-- Alternative: If you get "Unknown foreign key" errors, the constraints might not exist
-- In that case, skip to Step 2

-- Step 2: Remove all discount columns
-- Run this even if Step 1 failed - MySQL will tell you which constraints need to be dropped first
ALTER TABLE `tbl_patients`
DROP COLUMN IF EXISTS `pwd_id_number`,
DROP COLUMN IF EXISTS `pwd_verified_by_staff_id`,
DROP COLUMN IF EXISTS `pwd_verified_at`,
DROP COLUMN IF EXISTS `senior_citizen_id_number`,
DROP COLUMN IF EXISTS `senior_citizen_verified_by_staff_id`,
DROP COLUMN IF EXISTS `senior_citizen_verified_at`;

-- Step 3: Verify columns are removed
DESCRIBE `tbl_patients`;

-- If Step 2 fails with a foreign key error, note the constraint name from the error message
-- Then add it to Step 1 and try again

