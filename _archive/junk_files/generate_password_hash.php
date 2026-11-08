<?php
// Quick script to generate password hashes for staff members
// Run this in your browser or via command line: php generate_password_hash.php

echo "Password Hash Generator for Staff/Dentist Accounts\n";
echo "==================================================\n\n";

// Generate hash for dentist password
$dentist_password = 'dentist123';
$dentist_hash = password_hash($dentist_password, PASSWORD_DEFAULT);
echo "Dentist Password: $dentist_password\n";
echo "Hash: $dentist_hash\n\n";

// Generate hash for admin password
$admin_password = 'admin123';
$admin_hash = password_hash($admin_password, PASSWORD_DEFAULT);
echo "Admin Password: $admin_password\n";
echo "Hash: $admin_hash\n\n";

// SQL INSERT statements with the generated hashes
echo "SQL INSERT Statements:\n";
echo "======================\n\n";

echo "-- Dentist 1\n";
echo "INSERT INTO `tbl_staff` (`first_name`, `last_name`, `email`, `password`, `role`) \n";
echo "VALUES ('Maria', 'Santos', 'maria.santos@ivydental.com', '$dentist_hash', 'Dentist');\n\n";

echo "-- Dentist 2\n";
echo "INSERT INTO `tbl_staff` (`first_name`, `last_name`, `email`, `password`, `role`) \n";
echo "VALUES ('John', 'Cruz', 'john.cruz@ivydental.com', '$dentist_hash', 'Dentist');\n\n";

echo "-- Admin\n";
echo "INSERT INTO `tbl_staff` (`first_name`, `last_name`, `email`, `password`, `role`) \n";
echo "VALUES ('Admin', 'User', 'admin@ivydental.com', '$admin_hash', 'Admin');\n\n";

?>

