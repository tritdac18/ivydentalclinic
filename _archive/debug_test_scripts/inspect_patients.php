<?php
// inspect_patients.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config/db.php';

$conn = getDBConnection();

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h3>Columns in tbl_patients:</h3>";
$result = $conn->query("DESCRIBE tbl_patients");

if ($result) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $key => $value) {
            echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error describing table: " . $conn->error;
}
?>
