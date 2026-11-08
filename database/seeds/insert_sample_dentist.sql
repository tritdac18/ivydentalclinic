-- ============================================
-- Insert Sample Dentist/Staff into tbl_staff
-- ============================================
-- This creates a sample dentist that can be used for appointment booking
-- Based on tbl_staff data dictionary structure

-- Note: Make sure tbl_branches table has at least one branch first!
-- The primary_branch_id should reference an existing branch_id in tbl_branches

-- Sample Dentist 1: Dr. Maria Santos
-- Assumes branch_id = 1 exists (Baliwag branch from setup)
INSERT INTO `tbl_staff` (
    `primary_branch_id`, 
    `first_name`, 
    `last_name`, 
    `role`, 
    `license_number`, 
    `email`, 
    `phone_number`
) 
VALUES (
    1,  -- primary_branch_id (FK to tbl_branches - adjust to match your branch_id)
    'Maria', 
    'Santos', 
    'Dentist',  -- role (ENUM: Dentist, Admin, etc.)
    'DENT-2024-001',  -- license_number
    'maria.santos@ivydental.com', 
    '+63 912 345 6789'  -- phone_number
);

-- Sample Dentist 2: Dr. John Cruz
INSERT INTO `tbl_staff` (
    `primary_branch_id`, 
    `first_name`, 
    `last_name`, 
    `role`, 
    `license_number`, 
    `email`, 
    `phone_number`
) 
VALUES (
    1,  -- primary_branch_id (adjust to match your branch_id)
    'John', 
    'Cruz', 
    'Dentist', 
    'DENT-2024-002', 
    'john.cruz@ivydental.com', 
    '+63 912 345 6790'
);

-- Sample Admin (can also be used as dentist for appointments)
INSERT INTO `tbl_staff` (
    `primary_branch_id`, 
    `first_name`, 
    `last_name`, 
    `role`, 
    `license_number`, 
    `email`, 
    `phone_number`
) 
VALUES (
    1,  -- primary_branch_id (adjust to match your branch_id)
    'Admin', 
    'User', 
    'Admin',  -- role
    NULL,  -- license_number (Admin may not have a license)
    'admin@ivydental.com', 
    '+63 912 345 6791'
);

-- ============================================
-- Notes:
-- ============================================
-- 1. primary_branch_id must reference an existing branch_id in tbl_branches
--    - Check your branch IDs first: SELECT branch_id, branch_name FROM tbl_branches;
--    - Update the primary_branch_id values above to match your actual branch IDs
-- 2. staff_id will be auto-generated (auto increment, primary key)
-- 3. role must be a valid ENUM value (e.g., 'Dentist', 'Admin', etc.)
-- 4. license_number is for professional licenses (can be NULL for Admin)
-- 5. After inserting, these staff members will appear in the dentist dropdown on booking page
-- 6. Note: If your tbl_staff table has a password column (not shown in data dictionary),
--    you may need to add it separately or update existing staff records

-- ============================================
-- Verify the inserts:
-- ============================================
-- Run this to see all staff members:
-- SELECT staff_id, first_name, last_name, email, role FROM tbl_staff;

