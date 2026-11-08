<?php
/**
 * Diagnostic Script: Check PayMongo Payment Records
 * This script checks the actual payment records in the database
 */

require_once __DIR__ . '/config/db.php';

echo "=== PayMongo Payment Diagnostic ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $conn = getDBConnection();
    
    // Check for PayMongo payments (transaction_id starts with PM-LINK-)
    echo "--- PayMongo Payments (Last 10) ---\n";
    $stmt = $conn->prepare("
        SELECT 
            payment_id,
            appointment_id,
            patient_id,
            amount,
            payment_method,
            payment_status,
            transaction_id,
            payment_date,
            created_at
        FROM tbl_payments 
        WHERE transaction_id LIKE 'PM-LINK-%'
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "\n";
            echo "Payment ID: " . $row['payment_id'] . "\n";
            echo "  Appointment ID: " . $row['appointment_id'] . "\n";
            echo "  Amount: ₱" . number_format($row['amount'], 2) . "\n";
            echo "  Payment Method: " . ($row['payment_method'] ?? 'NULL') . "\n";
            echo "  Payment Status: " . $row['payment_status'] . "\n";
            echo "  Transaction ID: " . $row['transaction_id'] . "\n";
            echo "  Payment Date: " . ($row['payment_date'] ?? 'NULL') . "\n";
            echo "  Created At: " . $row['created_at'] . "\n";
            
            // Check appointment status
            $apt_stmt = $conn->prepare("SELECT status FROM tbl_appointments WHERE appointment_id = ?");
            $apt_stmt->bind_param("i", $row['appointment_id']);
            $apt_stmt->execute();
            $apt_result = $apt_stmt->get_result();
            if ($apt_result->num_rows > 0) {
                $apt_row = $apt_result->fetch_assoc();
                echo "  Appointment Status: " . $apt_row['status'] . "\n";
            }
            $apt_stmt->close();
        }
    } else {
        echo "No PayMongo payments found.\n";
    }
    $stmt->close();
    
    // Check for pending payments
    echo "\n\n--- All Pending Payments (Last 10) ---\n";
    $stmt2 = $conn->prepare("
        SELECT 
            payment_id,
            appointment_id,
            amount,
            payment_method,
            transaction_id,
            created_at
        FROM tbl_payments 
        WHERE payment_status = 'Pending'
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    
    if ($result2->num_rows > 0) {
        while ($row = $result2->fetch_assoc()) {
            echo "\n";
            echo "Payment ID: " . $row['payment_id'] . "\n";
            echo "  Appointment ID: " . $row['appointment_id'] . "\n";
            echo "  Amount: ₱" . number_format($row['amount'], 2) . "\n";
            echo "  Payment Method: " . ($row['payment_method'] ?? 'NULL') . "\n";
            echo "  Transaction ID: " . ($row['transaction_id'] ?? 'NULL') . "\n";
            echo "  Created At: " . $row['created_at'] . "\n";
        }
    } else {
        echo "No pending payments found.\n";
    }
    $stmt2->close();
    
    // Check table structure
    echo "\n\n--- tbl_payments Table Structure ---\n";
    $columns = $conn->query("SHOW COLUMNS FROM tbl_payments");
    while ($col = $columns->fetch_assoc()) {
        echo $col['Field'] . " - " . $col['Type'] . " - " . $col['Null'] . " - " . $col['Key'] . " - " . ($col['Default'] ?? 'NULL') . "\n";
    }
    
    $conn->close();
    
    echo "\n=== Diagnostic Complete ===\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("PayMongo Diagnostic Error: " . $e->getMessage());
}
