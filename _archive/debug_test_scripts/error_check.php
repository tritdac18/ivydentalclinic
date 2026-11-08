<?php
// Error checking page - remove after fixing issues
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Website Diagnostic Check</h1>";

echo "<h2>1. PHP Status</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Session Support: " . (function_exists('session_start') ? 'Yes' : 'No') . "<br>";

echo "<h2>2. File Check</h2>";
$files = ['index.php', 'homepage.php', 'login.php', 'register.php', 'config/db.php', 'landing.php', 'admin_dashboard.php'];
foreach ($files as $file) {
    $exists = file_exists($file);
    echo "$file: " . ($exists ? "✓ EXISTS" : "✗ MISSING") . "<br>";
}

echo "<h2>3. Session Test</h2>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "INACTIVE") . "<br>";
$_SESSION['test'] = 'working';
echo "Session Test Value: " . ($_SESSION['test'] ?? 'FAILED') . "<br>";

echo "<h2>4. Database Config Test</h2>";
if (file_exists('config/db.php')) {
    try {
        require_once 'config/db.php';
        echo "✓ config/db.php loaded<br>";
        
        // Test MySQLi
        if (function_exists('getDBConnection')) {
            try {
                $conn = getDBConnection();
                if ($conn && !$conn->connect_error) {
                    echo "✓ MySQLi Connection: SUCCESS<br>";
                    $conn->close();
                } else {
                    echo "✗ MySQLi Connection: FAILED - " . ($conn->connect_error ?? 'Unknown error') . "<br>";
                }
            } catch (Exception $e) {
                echo "✗ MySQLi Connection Error: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "✗ getDBConnection() function not found<br>";
        }
    } catch (Exception $e) {
        echo "✗ Error loading config/db.php: " . $e->getMessage() . "<br>";
    }
} else {
    echo "✗ config/db.php not found<br>";
}

echo "<h2>5. Directory Permissions</h2>";
echo "Config directory writable: " . (is_writable('config') ? 'Yes' : 'No') . "<br>";
echo "Root directory writable: " . (is_writable('.') ? 'Yes' : 'No') . "<br>";

echo "<h2>6. Test Links</h2>";
echo "<a href='index.php'>index.php</a><br>";
echo "<a href='homepage.php'>homepage.php</a><br>";
echo "<a href='login.php'>login.php</a><br>";
echo "<a href='register.php'>register.php</a><br>";

echo "<hr>";
echo "<p><strong>If you see errors above, those are the issues preventing your website from working.</strong></p>";
?>

