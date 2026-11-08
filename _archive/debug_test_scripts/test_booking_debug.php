<?php
// Debug script to test appointment booking
require_once 'config/db.php';

echo "<h2>Appointment Booking Debug Test</h2>";

// Test database connection
$conn = getDBConnection();
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "<p>✓ Database connection successful</p>";

// Check if tables exist
$tables = ['tbl_patients', 'tbl_staff', 'tbl_branches', 'tbl_appointments'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "<p>✓ Table '$table' exists</p>";
        
        // Show table structure
        $desc = $conn->query("DESCRIBE $table");
        echo "<details><summary>Structure of $table</summary><pre>";
        while ($row = $desc->fetch_assoc()) {
            echo $row['Field'] . " - " . $row['Type'] . " - " . $row['Null'] . " - " . $row['Key'] . "\n";
        }
        echo "</pre></details>";
    } else {
        echo "<p>✗ Table '$table' DOES NOT EXIST</p>";
    }
}

// Check if there are any patients
$result = $conn->query("SELECT COUNT(*) as count FROM tbl_patients");
$row = $result->fetch_assoc();
echo "<p>Patients in database: " . $row['count'] . "</p>";

// Check if there are any staff
$result = $conn->query("SELECT COUNT(*) as count FROM tbl_staff");
$row = $result->fetch_assoc();
echo "<p>Staff in database: " . $row['count'] . "</p>";

// Check if there are any branches
$result = $conn->query("SELECT COUNT(*) as count FROM tbl_branches");
$row = $result->fetch_assoc();
echo "<p>Branches in database: " . $row['count'] . "</p>";

// Test a sample INSERT query structure
echo "<h3>Testing INSERT Query Structure</h3>";
$test_patient_id = 1;
$test_dentist_id = 1;
$test_branch_id = 1;
$test_datetime = date('Y-m-d H:i:s');
$test_duration = 60;
$test_urgency = 'Low';
$test_status = 'Pending';

$stmt = $conn->prepare("INSERT INTO tbl_appointments (patient_id, dentist_id, branch_id, scheduled_datetime, duration_minutes, triage_urgency, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
if ($stmt) {
    echo "<p>✓ Prepared statement created successfully</p>";
    $stmt->bind_param("iiisiss", $test_patient_id, $test_dentist_id, $test_branch_id, $test_datetime, $test_duration, $test_urgency, $test_status);
    echo "<p>✓ Parameters bound successfully</p>";
    
    // Don't actually execute, just test the structure
    echo "<p>Query structure is valid. Parameters: patient_id=$test_patient_id, dentist_id=$test_dentist_id, branch_id=$test_branch_id, datetime=$test_datetime, duration=$test_duration, urgency=$test_urgency, status=$test_status</p>";
    $stmt->close();
} else {
    echo "<p>✗ Failed to prepare statement: " . $conn->error . "</p>";
}

$conn->close();
?>

