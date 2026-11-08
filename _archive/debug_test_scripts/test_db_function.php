<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing getDBConnection() Function</h2>";
echo "<style>body{font-family:monospace;font-size:12px;} .error{color:red;} .success{color:green;}</style>";

// Test 1: Check if file exists
echo "<h3>Test 1: File Existence</h3>";
if (file_exists('config/db.php')) {
    echo "<span class='success'>✅ config/db.php exists</span><br>";
} else {
    echo "<span class='error'>❌ config/db.php NOT found</span><br>";
    die();
}

// Test 2: Try to include the file
echo "<h3>Test 2: Loading config/db.php</h3>";
ob_start();
try {
    require_once 'config/db.php';
    $output = ob_get_clean();
    if (!empty($output)) {
        echo "<span class='error'>❌ Output detected when loading config/db.php:</span><br>";
        echo "<pre>" . htmlspecialchars($output) . "</pre>";
    } else {
        echo "<span class='success'>✅ config/db.php loaded (no output)</span><br>";
    }
} catch (Throwable $e) {
    ob_end_clean();
    echo "<span class='error'>❌ Fatal Error loading config/db.php:</span><br>";
    echo "Type: " . get_class($e) . "<br>";
    echo "Message: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
    die();
}

// Test 3: Check if function exists
echo "<h3>Test 3: Function Existence</h3>";
if (function_exists('getDBConnection')) {
    echo "<span class='success'>✅ getDBConnection() function exists</span><br>";
} else {
    echo "<span class='error'>❌ getDBConnection() function NOT found</span><br>";
    echo "<br>Available functions with 'db' in name:<br>";
    $functions = get_defined_functions();
    foreach ($functions['user'] as $func) {
        if (stripos($func, 'db') !== false) {
            echo "- $func<br>";
        }
    }
    die();
}

// Test 4: Try to call the function
echo "<h3>Test 4: Calling getDBConnection()</h3>";
try {
    $conn = getDBConnection();
    if ($conn && !$conn->connect_error) {
        echo "<span class='success'>✅ Database connection successful!</span><br>";
        echo "Server info: " . $conn->server_info . "<br>";
        $conn->close();
    } else {
        echo "<span class='error'>❌ Connection failed: " . ($conn->connect_error ?? 'Unknown error') . "</span><br>";
    }
} catch (Throwable $e) {
    echo "<span class='error'>❌ Error calling getDBConnection():</span><br>";
    echo "Type: " . get_class($e) . "<br>";
    echo "Message: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}

echo "<br><h3>✅ All tests completed!</h3>";
?>

