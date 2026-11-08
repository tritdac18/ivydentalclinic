<?php
/**
 * Script to create an admin user in tbl_staff
 * Run this once via browser or command line: php create_admin_user.php
 */

require_once 'config/db.php';

// Admin user details
$username = 'admin';
$first_name = 'Admin';
$last_name = 'User';
$email = 'admin@ivydental.com';
$password = 'admin123'; // Change this to your desired password
$role = 'Admin';
$phone_number = '+63 912 345 6789';

// Hash the password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    $conn = getDBConnection();
    
    // Check if admin user already exists
    $check_stmt = $conn->prepare("SELECT staff_id FROM tbl_staff WHERE username = ? OR email = ? LIMIT 1");
    $check_stmt->bind_param("ss", $username, $email);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "Error: Admin user with username '$username' or email '$email' already exists.\n";
        echo "To update the password, use this SQL statement:\n\n";
        echo "UPDATE `tbl_staff` SET `password` = '$hashed_password' WHERE `username` = '$username';\n";
    } else {
        // Insert new admin user
        $insert_stmt = $conn->prepare("INSERT INTO tbl_staff (username, first_name, last_name, email, password, role, phone_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("sssssss", $username, $first_name, $last_name, $email, $hashed_password, $role, $phone_number);
        
        if ($insert_stmt->execute()) {
            echo "✓ Admin user created successfully!\n\n";
            echo "Login credentials:\n";
            echo "  Username: $username\n";
            echo "  Email: $email\n";
            echo "  Password: $password\n";
            echo "  Role: $role\n\n";
        } else {
            echo "Error: Failed to create admin user.\n";
            echo "Error: " . $insert_stmt->error . "\n";
        }
        $insert_stmt->close();
    }
    
    $check_stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Also output the SQL statement for manual use
echo "\n--- Alternative: Manual SQL Statement ---\n";
echo "If you prefer to run SQL directly, use this statement:\n\n";
echo "INSERT INTO `tbl_staff` (`username`, `first_name`, `last_name`, `email`, `password`, `role`, `phone_number`) VALUES\n";
echo "('$username', '$first_name', '$last_name', '$email', '$hashed_password', '$role', '$phone_number');\n";
?>

