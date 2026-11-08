<?php
require_once 'config/db.php';

$conn = getDBConnection();

$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

$sqlScript = "-- Database Schema Backup\n";
$sqlScript .= "-- Generated at: " . date('Y-m-d H:i:s') . "\n\n";

foreach ($tables as $table) {
    try {
        $result = $conn->query("SHOW CREATE TABLE `$table`");
        if ($row = $result->fetch_assoc()) {
            $sqlScript .= "-- Table structure for table `$table`\n";
            $sqlScript .= "DROP TABLE IF EXISTS `$table`;\n";
            $sqlScript .= $row['Create Table'] . ";\n\n";
        }
    } catch (Exception $e) {
        $sqlScript .= "-- Error backing up $table: " . $e->getMessage() . "\n\n";
    }
}

file_put_contents('backup_current_schema.sql', $sqlScript);
echo "Backup created at backup_current_schema.sql";
?>
