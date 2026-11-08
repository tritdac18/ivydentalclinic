<?php
/**
 * Check Payment Status - Quick diagnostic
 * Run this to see the current state of PayMongo payments
 */

// Simple connection without mysqli (use file_get_contents with API)
$host = 'sql305.infinityfree.com';
$db = 'if0_40821147_db_ivydental';
$user = 'if0_40821147';
$pass = 'NO3wynWJFLEzrh';

echo "=== PayMongo Payment Status Check ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// Try to connect
try {
    $conn = new mysqli($host, $user, $pass, $db);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "✅ Database connected\n\n";
    
    // First, check the table structure
    echo "--- Table Structure: tbl_payments ---\n";
    $columns = $conn->query("SHOW COLUMNS FROM tbl_payments");
    $columnNames = [];
    while ($col = $columns->fetch_assoc()) {
        $columnNames[] = $col['Field'];
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    echo "\n";
    
    // Build query based on available columns
    $hasAppointmentId = in_array('appointment_id', $columnNames);
    $hasInvoiceId = in_array('invoice_id', $columnNames);
    
    // Check PayMongo payments
    echo "--- Recent PayMongo Payments ---\n";
    
    if ($hasAppointmentId) {
        $query = "
            SELECT 
                p.payment_id,
                p.appointment_id,
                p.amount,
                p.payment_method,
                p.payment_status,
                p.transaction_id,
                p.created_at,
                a.status as appointment_status
            FROM tbl_payments p
            LEFT JOIN tbl_appointments a ON p.appointment_id = a.appointment_id
            WHERE p.transaction_id LIKE 'PM-LINK-%'
            ORDER BY p.created_at DESC
            LIMIT 5
        ";
    } else if ($hasInvoiceId) {
        $query = "
            SELECT 
                p.payment_id,
                p.invoice_id,
                p.amount,
                p.payment_method,
                p.payment_status,
                p.transaction_id,
                p.created_at
            FROM tbl_payments p
            WHERE p.transaction_id LIKE 'PM-LINK-%'
            ORDER BY p.created_at DESC
            LIMIT 5
        ";
    } else {
        $query = "
            SELECT *
            FROM tbl_payments
            WHERE transaction_id LIKE 'PM-LINK-%'
            ORDER BY created_at DESC
            LIMIT 5
        ";
    }
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "\nPayment ID: " . $row['payment_id'] . "\n";
            if (isset($row['appointment_id'])) {
                echo "  Appointment: #" . $row['appointment_id'] . "\n";
            }
            if (isset($row['invoice_id'])) {
                echo "  Invoice: #" . $row['invoice_id'] . "\n";
            }
            echo "  Amount: ₱" . number_format($row['amount'], 2) . "\n";
            echo "  Method: " . ($row['payment_method'] ?? 'NULL') . "\n";
            echo "  Payment Status: " . $row['payment_status'] . "\n";
            if (isset($row['appointment_status'])) {
                echo "  Appointment Status: " . $row['appointment_status'] . "\n";
            }
            echo "  Transaction ID: " . $row['transaction_id'] . "\n";
            echo "  Created: " . $row['created_at'] . "\n";
        }
    } else {
        echo "No PayMongo payments found.\n";
    }
    
    // Check all pending payments
    echo "\n\n--- All Pending Payments ---\n";
    $result2 = $conn->query("
        SELECT payment_id, amount, payment_method, transaction_id, created_at
        FROM tbl_payments
        WHERE payment_status = 'Pending'
        ORDER BY created_at DESC
        LIMIT 5
    ");
    
    if ($result2 && $result2->num_rows > 0) {
        while ($row = $result2->fetch_assoc()) {
            echo "\nPayment ID: " . $row['payment_id'] . "\n";
            echo "  Amount: ₱" . number_format($row['amount'], 2) . "\n";
            echo "  Method: " . ($row['payment_method'] ?? 'NULL') . "\n";
            echo "  Transaction ID: " . ($row['transaction_id'] ?? 'NULL') . "\n";
            echo "  Created: " . $row['created_at'] . "\n";
        }
    } else {
        echo "No pending payments found.\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n=== Check Complete ===\n";
