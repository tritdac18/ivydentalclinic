<?php
// Simple test - no dependencies
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP is working!<br>";
echo "PHP Version: " . phpversion() . "<br>";

// Check if mysqli is available
if (extension_loaded('mysqli')) {
    echo "✅ MySQLi extension is loaded<br>";
} else {
    echo "❌ MySQLi extension is NOT loaded<br>";
}

// Try to load config
echo "<br>Trying to load config/db.php...<br>";
if (file_exists('config/db.php')) {
    echo "✅ config/db.php exists<br>";
    try {
        require_once 'config/db.php';
        echo "✅ config/db.php loaded successfully<br>";
        
        // Try connection
        if (function_exists('getDBConnection')) {
            echo "✅ getDBConnection() function exists<br>";
            try {
                $conn = getDBConnection();
                echo "✅ Database connection successful!<br>";
                $conn->close();
            } catch (Exception $e) {
                echo "❌ Database connection failed: " . htmlspecialchars($e->getMessage()) . "<br>";
            }
        } else {
            echo "❌ getDBConnection() function not found<br>";
        }
    } catch (Throwable $e) {
        echo "❌ Error loading config/db.php: " . htmlspecialchars($e->getMessage()) . "<br>";
        echo "File: " . $e->getFile() . "<br>";
        echo "Line: " . $e->getLine() . "<br>";
    }
} else {
    echo "❌ config/db.php not found<br>";
}
?>

