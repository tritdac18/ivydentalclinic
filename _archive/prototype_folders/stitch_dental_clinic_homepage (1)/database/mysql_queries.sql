-- MySQL Queries for Dental Clinic Database
-- This file contains all the SQL queries used in the PHP backend

-- ============================================
-- PATIENT REGISTRATION QUERIES
-- ============================================

-- Check if email already exists (used in registration)
SELECT patient_id 
FROM tbl_patients 
WHERE email = 'user@example.com';

-- Insert new patient (used in registration)
INSERT INTO tbl_patients (first_name, last_name, dob, primary_phone, email, password) 
VALUES ('John', 'Doe', '1990-01-15', '(555) 123-4567', 'john.doe@example.com', '$2y$10$hashedpasswordhere');

-- ============================================
-- LOGIN QUERIES
-- ============================================

-- Patient login - Get patient by email
SELECT patient_id, first_name, last_name, email, password 
FROM tbl_patients 
WHERE email = 'user@example.com';

-- Staff login - Get staff by email
SELECT staff_id, first_name, last_name, email, password, role 
FROM tbl_staff 
WHERE email = 'staff@example.com';

-- ============================================
-- USER INFORMATION QUERIES
-- ============================================

-- Get patient details by ID
SELECT patient_id, first_name, last_name, dob, primary_phone, email, medical_history_summary, created_at
FROM tbl_patients 
WHERE patient_id = 1;

-- Get staff details by ID
SELECT staff_id, primary_branch_id, first_name, last_name, role, license_number, email, phone_number, created_at
FROM tbl_staff 
WHERE staff_id = 1;

-- Get staff with branch information
SELECT s.staff_id, s.first_name, s.last_name, s.role, s.email, 
       b.name AS branch_name, b.address AS branch_address
FROM tbl_staff s
LEFT JOIN tbl_branches b ON s.primary_branch_id = b.branch_id
WHERE s.staff_id = 1;

-- ============================================
-- BRANCH QUERIES
-- ============================================

-- Get all active branches
SELECT branch_id, name, address, contact_number 
FROM tbl_branches 
WHERE is_active = 1;

-- Get branch by ID
SELECT branch_id, name, address, contact_number, is_active 
FROM tbl_branches 
WHERE branch_id = 1;

-- ============================================
-- ADMIN QUERIES (for future use)
-- ============================================

-- Get all patients
SELECT patient_id, first_name, last_name, email, primary_phone, dob, created_at
FROM tbl_patients 
ORDER BY created_at DESC;

-- Get all staff members
SELECT staff_id, first_name, last_name, email, role, phone_number, created_at
FROM tbl_staff 
ORDER BY created_at DESC;

-- Get staff by branch
SELECT staff_id, first_name, last_name, role, email, phone_number
FROM tbl_staff 
WHERE primary_branch_id = 1;

-- Count total patients
SELECT COUNT(*) AS total_patients 
FROM tbl_patients;

-- Count total staff
SELECT COUNT(*) AS total_staff 
FROM tbl_staff;

-- Count patients by registration date (last 30 days)
SELECT DATE(created_at) AS registration_date, COUNT(*) AS count
FROM tbl_patients
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY registration_date DESC;

-- ============================================
-- UPDATE QUERIES (for future profile editing)
-- ============================================

-- Update patient information
UPDATE tbl_patients 
SET first_name = 'John', 
    last_name = 'Smith', 
    primary_phone = '(555) 999-8888',
    email = 'newemail@example.com'
WHERE patient_id = 1;

-- Update patient password
UPDATE tbl_patients 
SET password = '$2y$10$newhashedpassword'
WHERE patient_id = 1;

-- Update staff information
UPDATE tbl_staff 
SET first_name = 'Jane', 
    last_name = 'Doe', 
    phone_number = '(555) 777-6666',
    primary_branch_id = 2
WHERE staff_id = 1;

-- ============================================
-- DELETE QUERIES (for admin use)
-- ============================================

-- Soft delete patient (set email to NULL to prevent login, but keep record)
UPDATE tbl_patients 
SET email = NULL 
WHERE patient_id = 1;

-- Delete patient (permanent - use with caution)
DELETE FROM tbl_patients 
WHERE patient_id = 1;

-- ============================================
-- SEARCH QUERIES
-- ============================================

-- Search patients by name
SELECT patient_id, first_name, last_name, email, primary_phone
FROM tbl_patients 
WHERE first_name LIKE '%John%' OR last_name LIKE '%Doe%'
ORDER BY last_name, first_name;

-- Search patients by email
SELECT patient_id, first_name, last_name, email, primary_phone
FROM tbl_patients 
WHERE email LIKE '%example%'
ORDER BY last_name, first_name;

-- Search staff by role
SELECT staff_id, first_name, last_name, email, role, phone_number
FROM tbl_staff 
WHERE role = 'Dentist'
ORDER BY last_name, first_name;

-- ============================================
-- UTILITY QUERIES
-- ============================================

-- Verify email exists (for password reset - future feature)
SELECT patient_id, email 
FROM tbl_patients 
WHERE email = 'user@example.com';

SELECT staff_id, email 
FROM tbl_staff 
WHERE email = 'staff@example.com';

-- Get user type by email (check if patient or staff)
SELECT 'patient' AS user_type, patient_id AS user_id, first_name, last_name, email
FROM tbl_patients 
WHERE email = 'user@example.com'
UNION ALL
SELECT 'staff' AS user_type, staff_id AS user_id, first_name, last_name, email
FROM tbl_staff 
WHERE email = 'user@example.com';

-- ============================================
-- INDEXES (already created in schema, shown for reference)
-- ============================================

-- CREATE INDEX idx_patient_email ON tbl_patients(email);
-- CREATE INDEX idx_staff_email ON tbl_staff(email);
-- CREATE INDEX idx_staff_branch ON tbl_staff(primary_branch_id);

-- ============================================
-- NOTES
-- ============================================
-- 1. All passwords should be hashed using PHP's password_hash() function
-- 2. Always use prepared statements to prevent SQL injection
-- 3. Email fields are UNIQUE, so duplicate emails will cause errors
-- 4. Date format for dob: YYYY-MM-DD (MySQL DATE format)
-- 5. Phone numbers are stored as VARCHAR to allow various formats
-- 6. Foreign key constraints ensure data integrity

