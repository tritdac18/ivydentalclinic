<?php
require_once 'config/db.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

function runSQLFile($conn, $file) {
    echo "<h3>Processing $file...</h3>";
    $sql = file_get_contents($file);
    if (!$sql) {
        echo "<p style='color:red'>Error reading file.</p>";
        return;
    }

    // Split by semicolon for basic execution, but handle delimiters if needed
    // Simple split won't work well with DELIMITER, so we'll use multi_query if possible
    
    if ($conn->multi_query($sql)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->more_results() && $conn->next_result());
        echo "<p style='color:green'>Success!</p>";
    } else {
        echo "<p style='color:red'>Error: " . $conn->error . "</p>";
    }
}

$conn = getDBConnection();

echo "<h1>Applying Database Updates</h1>";

runSQLFile($conn, 'create_tbl_discounts.sql');
runSQLFile($conn, 'update_payment_schema.sql');

echo "<p>Done.</p>";
?>
