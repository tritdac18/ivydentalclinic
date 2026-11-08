<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';

try {
    $conn = getDBConnection();
    
    // Simple query to check statuses
    $result = $conn->query("SELECT payment_status, COUNT(*) as count FROM tbl_invoices GROUP BY payment_status");
    
    echo "<!DOCTYPE html><html><head><title>Simple DB Test</title></head><body>";
    echo "<h1>Invoice Status Check</h1>";
    
    if ($result) {
        echo "<table border='1'><tr><th>Status</th><th>Count</th></tr>";
        while ($row = $result->fetch_assoc()) {
            $status = $row['payment_status'] ?? 'NULL';
            echo "<tr><td>" . htmlspecialchars($status) . "</td><td>" . $row['count'] . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Error: " . $conn->error . "</p>";
    }
    
    $conn->close();
    echo "</body></html>";
} catch (Exception $e) {
    echo "<!DOCTYPE html><html><body><h1>Error</h1><pre>" . htmlspecialchars($e->getMessage()) . "</pre></body></html>";
}
?>
