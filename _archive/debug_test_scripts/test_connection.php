<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Connection Test</h2>";
echo "<style>body{font-family:monospace;font-size:12px;} .error{color:red;} .success{color:green;}</style>";

try {
    require_once 'config/db.php';
    echo "<span class='success'>✅ config/db.php loaded</span><br>";
    
    $conn = getDBConnection();
    if ($conn && !$conn->connect_error) {
        echo "<span class='success'>✅ Database connection successful</span><br>";
        $conn->close();
    } else {
        echo "<span class='error'>❌ Database connection failed</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    echo "<strong>File:</strong> " . $e->getFile() . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
}

echo "<br><h3>Testing a simple page include:</h3>";
ob_start();
try {
    include 'index.php';
    $output = ob_get_clean();
    if (empty($output)) {
        echo "<span class='error'>❌ index.php returned blank output</span><br>";
    } else {
        echo "<span class='success'>✅ index.php loaded (output length: " . strlen($output) . " bytes)</span><br>";
    }
} catch (Throwable $e) {
    ob_end_clean();
    echo "<span class='error'>❌ Fatal Error in index.php:</span><br>";
    echo htmlspecialchars($e->getMessage()) . "<br>";
    echo "<strong>Line:</strong> " . $e->getLine() . "<br>";
}
?>

