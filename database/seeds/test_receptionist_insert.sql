-- Test Receptionist Account Insert
-- Password: receptionist123 (hashed with bcrypt)
-- Username: receptionist
-- Email: receptionist@ivydental.com

INSERT INTO `tbl_staff` (
    `username`, 
    `first_name`, 
    `last_name`, 
    `email`, 
    `password`, 
    `role`, 
    `phone_number`,
    `created_at`, 
    `updated_at`
) VALUES (
    'receptionist',
    'Jane',
    'Smith',
    'receptionist@ivydental.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: receptionist123
    'Receptionist',
    '+63 912 345 6789',
    NOW(),
    NOW()
);

