-- Fix tbl_staff to allow NULL passwords for staff activation
-- This allows staff accounts to be created without passwords initially
-- Password will be set during activation

-- Step 1: Modify password column to allow NULL
ALTER TABLE `tbl_staff` 
MODIFY COLUMN `password` VARCHAR(255) NULL;

-- Note: Application-level validation in login.php ensures
-- staff cannot log in without a password (checks if password is not empty)

