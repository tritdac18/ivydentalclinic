-- ============================================
-- Insert Sample Dentist/Staff with Username
-- Database: db_ivydental
-- ============================================
-- This creates sample dentists that can be used for appointment booking
-- Includes username and password fields

USE `db_ivydental`;

-- Note: Make sure tbl_branches table has at least one branch first!
-- Check branches: SELECT branch_id, name FROM tbl_branches;

-- Sample Dentist 1: Dr. Maria Santos
-- Password hash for 'password123' (you can change this)
INSERT INTO `tbl_staff` (
    `username`,
    `first_name`, 
    `last_name`, 
    `email`,
    `password`,
    `role`, 
    `primary_branch_id`,
    `license_number`, 
    `phone_number`
) VALUES (
    'dentist_maria',
    'Maria', 
    'Santos', 
    'maria.santos@ivydental.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password123
    'Dentist',
    1,  -- primary_branch_id (adjust to match your branch_id)
    'DENT-2024-001',
    '+63 912 345 6789'
) ON DUPLICATE KEY UPDATE username = username;

-- Sample Dentist 2: Dr. John Cruz
INSERT INTO `tbl_staff` (
    `username`,
    `first_name`, 
    `last_name`, 
    `email`,
    `password`,
    `role`, 
    `primary_branch_id`,
    `license_number`, 
    `phone_number`
) VALUES (
    'dentist_john',
    'John', 
    'Cruz', 
    'john.cruz@ivydental.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password123
    'Dentist',
    1,  -- primary_branch_id (adjust to match your branch_id)
    'DENT-2024-002',
    '+63 912 345 6790'
) ON DUPLICATE KEY UPDATE username = username;

-- Sample Dentist 3: Dr. Sarah Johnson
INSERT INTO `tbl_staff` (
    `username`,
    `first_name`, 
    `last_name`, 
    `email`,
    `password`,
    `role`, 
    `primary_branch_id`,
    `license_number`, 
    `phone_number`
) VALUES (
    'dentist_sarah',
    'Sarah', 
    'Johnson', 
    'sarah.johnson@ivydental.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password123
    'Dentist',
    2,  -- primary_branch_id (adjust to match your branch_id)
    'DENT-2024-003',
    '+63 912 345 6791'
) ON DUPLICATE KEY UPDATE username = username;

-- Sample Admin (can also be used as dentist for appointments)
INSERT INTO `tbl_staff` (
    `username`,
    `first_name`, 
    `last_name`, 
    `email`,
    `password`,
    `role`, 
    `primary_branch_id`,
    `phone_number`
) VALUES (
    'admin_user',
    'Admin', 
    'User', 
    'admin@ivydental.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password123
    'Admin',
    1,  -- primary_branch_id (adjust to match your branch_id)
    '+63 912 345 6792'
) ON DUPLICATE KEY UPDATE username = username;

-- ============================================
-- Verify the inserts
-- ============================================
SELECT 
    staff_id,
    username,
    first_name,
    last_name,
    email,
    role,
    primary_branch_id
FROM `tbl_staff`
ORDER BY staff_id;

-- ============================================
-- Notes:
-- ============================================
-- 1. Default password for all staff: 'password123'
-- 2. primary_branch_id must reference an existing branch_id in tbl_branches
--    - Check your branch IDs: SELECT branch_id, name FROM tbl_branches;
--    - Update the primary_branch_id values above to match your actual branch IDs
-- 3. staff_id will be auto-generated (auto increment)
-- 4. After inserting, these staff members will appear in the dentist dropdown on booking page

