<?php
require_once 'config/db.php';
$conn = getDBConnection();

echo "Checking Tables...\n";
$tables = ['tbl_service_catalog', 'tbl_service_prerequisites', 'tbl_audit_logs', 'tbl_appointments'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "Table '$table' EXISTS.\n";
        
        // Show count
        $count = $conn->query("SELECT COUNT(*) as c FROM $table")->fetch_assoc()['c'];
        echo "  - Rows: $count\n";
        
        // Show columns
        $cols = $conn->query("SHOW COLUMNS FROM $table");
        echo "  - Columns: ";
        while($row = $cols->fetch_assoc()) {
            echo $row['Field'] . " ";
        }
        echo "\n";
        
        // Show Content (first 5)
        if ($table == 'tbl_service_prerequisites' || $table == 'tbl_service_catalog') {
            echo "  - Content:\n";
            $data = $conn->query("SELECT * FROM $table LIMIT 5");
            while($row = $data->fetch_assoc()) {
                print_r($row);
            }
        }
    } else {
        echo "Table '$table' DOES NOT EXIST.\n";
    }
}
?>
