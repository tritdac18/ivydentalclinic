-- Check what dentists/staff exist in your database
-- Run this to see available staff members

SELECT staff_id, first_name, last_name, role, email 
FROM tbl_staff 
WHERE role IN ('Dentist', 'Admin')
ORDER BY staff_id;

-- If this returns no rows, you need to add staff members first
-- Example: Insert a test dentist
-- INSERT INTO tbl_staff (first_name, last_name, email, password, role) 
-- VALUES ('John', 'Doe', 'dentist@example.com', '$2y$10$...', 'Dentist');

-- Check what branches exist
SELECT branch_id, branch_name, address 
FROM tbl_branches 
ORDER BY branch_id;

-- If no branches exist, run the setup_appointments_tables.sql file first

