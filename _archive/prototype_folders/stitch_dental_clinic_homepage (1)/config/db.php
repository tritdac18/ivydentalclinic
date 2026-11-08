<?php
// Database configuration
// Using your remote database (infinityfree.com)
define('DB_HOST', 'sql100.infinityfree.com');
define('DB_USER', 'if0_40566949');
define('DB_PASS', 'UEF4FGvVMD28');
define('DB_NAME', 'if0_40566949_trisha_db');

// Create database connection
function getDBConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

