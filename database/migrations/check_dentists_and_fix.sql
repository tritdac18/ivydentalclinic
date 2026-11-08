-- ============================================
-- Check Dentists/Staff and Fix Foreign Key Issues
-- Database: db_ivydental
-- ============================================
-- This script helps diagnose and fix the foreign key constraint error

-- Step 1: Check what staff/dentists exist in tbl_staff
SELECT 
    'Current Staff/Dentists in Database:' AS info,
    staff_id,
    username,
    first_name,
    last_name,
    email,
    role
FROM `tbl_staff`
ORDER BY staff_id;

-- Step 2: Check if there are any appointments trying to reference non-existent staff
SELECT 
    'Appointments with invalid dentist_id:' AS info,
    a.appointment_id,
    a.dentist_id,
    a.patient_id,
    a.scheduled_datetime
FROM `tbl_appointments` a
LEFT JOIN `tbl_staff` s ON a.dentist_id = s.staff_id
WHERE s.staff_id IS NULL;

-- Step 3: Check if there are any appointments trying to reference non-existent patients
SELECT 
    'Appointments with invalid patient_id:' AS info,
    a.appointment_id,
    a.patient_id,
    a.dentist_id,
    a.scheduled_datetime
FROM `tbl_appointments` a
LEFT JOIN `tbl_patients` p ON a.patient_id = p.patient_id
WHERE p.patient_id IS NULL;

-- Step 4: Check if there are any appointments trying to reference non-existent branches
SELECT 
    'Appointments with invalid branch_id:' AS info,
    a.appointment_id,
    a.branch_id,
    a.scheduled_datetime
FROM `tbl_appointments` a
LEFT JOIN `tbl_branches` b ON a.branch_id = b.branch_id
WHERE b.branch_id IS NULL;

-- ============================================
-- If no staff exist, insert sample dentists
-- ============================================
-- Uncomment and run the INSERT statements below if you need sample dentists

-- Insert sample dentist (Admin)
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
    'admin_dentist',
    'John',
    'Smith',
    'admin@ivydental.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'Admin',
    1,
    '(555) 111-0001'
) ON DUPLICATE KEY UPDATE username = username;

-- Insert sample dentist (Dentist)
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
    'dentist1',
    'Sarah',
    'Johnson',
    'sarah.johnson@ivydental.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'Dentist',
    1,
    'DENT-12345',
    '(555) 111-0002'
) ON DUPLICATE KEY UPDATE username = username;

-- Insert another sample dentist
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
    'dentist2',
    'Michael',
    'Chen',
    'michael.chen@ivydental.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'Dentist',
    2,
    'DENT-67890',
    '(555) 111-0003'
) ON DUPLICATE KEY UPDATE username = username;

-- ============================================
-- Verify staff were inserted
-- ============================================
SELECT 
    'Staff after insert:' AS info,
    staff_id,
    username,
    first_name,
    last_name,
    role
FROM `tbl_staff`
ORDER BY staff_id;

