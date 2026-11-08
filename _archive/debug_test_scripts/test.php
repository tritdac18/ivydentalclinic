<?php
// Simple test file to check if PHP is working
echo "PHP is working!<br>";
echo "PHP Version: " . phpversion() . "<br>";

// Test if config directory exists
if (file_exists('config/db.php')) {
    echo "config/db.php exists<br>";
} else {
    echo "ERROR: config/db.php does NOT exist<br>";
}

// Test if homepage exists
if (file_exists('homepage.php')) {
    echo "homepage.php exists<br>";
} else {
    echo "ERROR: homepage.php does NOT exist<br>";
}

// Test database connection (without dying)
echo "<br>Testing database connection...<br>";
try {
    require_once 'config/db.php';
    echo "Database config loaded successfully<br>";
    
    // Test MySQLi connection
    $conn = getDBConnection();
    if ($conn) {
        echo "MySQLi connection: SUCCESS<br>";
        $conn->close();
    }
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "<br>";
}

// Test session
echo "<br>Session status: ";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "ACTIVE<br>";
} else {
    echo "NOT ACTIVE<br>";
}
?>

