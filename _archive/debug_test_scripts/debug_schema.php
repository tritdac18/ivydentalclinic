<?php
require_once 'config/db.php';

$conn = getDBConnection();

$tables = ['tbl_payments', 'tbl_invoices', 'tbl_patients', 'tbl_appointments'];

foreach ($tables as $table) {
    echo "<h1>Table: $table</h1>";
    $result = $conn->query("DESCRIBE $table");
    if ($result) {
        echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $val) {
                echo "<td>" . htmlspecialchars($val) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "Error describing $table: " . $conn->error;
    }
    echo "<hr>";
}
?>
