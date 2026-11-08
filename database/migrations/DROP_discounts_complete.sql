-- Complete script to drop all discount columns and their foreign keys
-- This version tries multiple approaches to handle foreign keys

-- Method 1: Try to drop columns directly first (MySQL will tell us which FKs exist)
-- If this works, you're done! If not, proceed to Method 2

-- Step 1: Attempt direct column removal
-- This will fail if foreign keys exist, but will show us the constraint names
ALTER TABLE `tbl_patients`
DROP COLUMN IF EXISTS `pwd_id_number`,
DROP COLUMN IF EXISTS `pwd_verified_by_staff_id`,
DROP COLUMN IF EXISTS `pwd_verified_at`,
DROP COLUMN IF EXISTS `senior_citizen_id_number`,
DROP COLUMN IF EXISTS `senior_citizen_verified_by_staff_id`,
DROP COLUMN IF EXISTS `senior_citizen_verified_at`;

-- If Step 1 succeeded, you're done! Skip to verification below.

-- Method 2: If Step 1 failed, find and drop foreign keys manually
-- First, get constraint names:
SHOW CREATE TABLE `tbl_patients`\G

-- Look for lines containing:
-- CONSTRAINT `name_here` FOREIGN KEY (`pwd_verified_by_staff_id`) ...
-- CONSTRAINT `name_here` FOREIGN KEY (`senior_citizen_verified_by_staff_id`) ...

-- Then drop them (replace with actual names from SHOW CREATE TABLE):
-- ALTER TABLE `tbl_patients` DROP FOREIGN KEY `constraint_name_here`;

-- After dropping foreign keys, run Step 1 again

-- Verification: Check if columns are gone
DESCRIBE `tbl_patients`;

