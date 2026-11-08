<?php
// Debug script to test appointments query
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'config/security.php';

echo "<h1>Appointments Debug Test</h1>";

try {
    $conn = getDBConnection();
    
    if (!$conn) {
        die("Database connection failed");
    }
    
    echo "<p>✓ Database connected</p>";
    
    // Check if tables exist
    $tables = ['tbl_appointments', 'tbl_staff', 'tbl_patients'];
    foreach ($tables as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check && $check->num_rows > 0) {
            echo "<p>✓ Table $table exists</p>";
        } else {
            echo "<p>✗ Table $table does NOT exist</p>";
        }
    }
    
    // Check columns in tbl_appointments
    echo "<h2>Columns in tbl_appointments:</h2>";
    $columns = $conn->query("SHOW COLUMNS FROM tbl_appointments");
    if ($columns) {
        echo "<ul>";
        while ($col = $columns->fetch_assoc()) {
            echo "<li>" . $col['Field'] . " (" . $col['Type'] . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>Error: " . $conn->error . "</p>";
    }
    
    // Try a simple query
    echo "<h2>Testing Simple Query:</h2>";
    $test_query = "SELECT COUNT(*) as count FROM tbl_appointments";
    $result = $conn->query($test_query);
    if ($result) {
        $row = $result->fetch_assoc();
        echo "<p>✓ Found " . $row['count'] . " appointments</p>";
    } else {
        echo "<p>✗ Query failed: " . $conn->error . "</p>";
    }
    
    // Try query with joins
    echo "<h2>Testing Query with Joins:</h2>";
    $test_query = "
        SELECT 
            a.appointment_id,
            a.scheduled_datetime,
            a.duration_minutes,
            a.status,
            s.first_name as dentist_first_name,
            s.last_name as dentist_last_name
        FROM tbl_appointments a
        INNER JOIN tbl_staff s ON a.dentist_id = s.staff_id
        LIMIT 1
    ";
    $result = $conn->query($test_query);
    if ($result) {
        echo "<p>✓ Join query works</p>";
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo "<pre>" . print_r($row, true) . "</pre>";
        }
    } else {
        echo "<p>✗ Join query failed: " . $conn->error . "</p>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color:red;'>ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

