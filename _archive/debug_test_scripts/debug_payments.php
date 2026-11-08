<?php
/**
 * Debug PayMongo Payments
 * This script checks the database to see what's actually stored
 */

require_once __DIR__ . '/config/db.php';

echo "=== PayMongo Payment Debug Script ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $conn = getDBConnection();
    
    // Check all appointments
    echo "=== ALL APPOINTMENTS ===\n";
    $appt_stmt = $conn->query("
        SELECT appointment_id, patient_id, status, scheduled_datetime, price, created_at
        FROM tbl_appointments
        ORDER BY created_at DESC
        LIMIT 10
    ");
    
    $all_appointments = [];
    while ($row = $appt_stmt->fetch_assoc()) {
        $all_appointments[] = $row;
        echo "Appointment ID: " . $row['appointment_id'] . "\n";
        echo "  Patient ID: " . $row['patient_id'] . "\n";
        echo "  Status: " . $row['status'] . "\n";
        echo "  Scheduled: " . $row['scheduled_datetime'] . "\n";
        echo "  Price: " . $row['price'] . "\n";
        echo "  Created: " . $row['created_at'] . "\n";
        echo "\n";
    }
    
    echo "Total appointments found: " . count($all_appointments) . "\n\n";
    
    // Check all payments
    echo "=== ALL PAYMENTS ===\n";
    $payments_stmt = $conn->query("
        SELECT payment_id, appointment_id, patient_id, payment_type, payment_method, 
               payment_status, amount, transaction_id, payment_date, created_at
        FROM tbl_payments
        ORDER BY created_at DESC
        LIMIT 20
    ");
    
    $all_payments = [];
    while ($row = $payments_stmt->fetch_assoc()) {
        $all_payments[] = $row;
        echo "Payment ID: " . $row['payment_id'] . "\n";
        echo "  Appointment ID: " . $row['appointment_id'] . "\n";
        echo "  Patient ID: " . $row['patient_id'] . "\n";
        echo "  Payment Type: " . ($row['payment_type'] ?? 'NULL') . "\n";
        echo "  Payment Method: " . ($row['payment_method'] ?? 'NULL') . "\n";
        echo "  Payment Status: " . ($row['payment_status'] ?? 'NULL') . "\n";
        echo "  Amount: " . $row['amount'] . "\n";
        echo "  Transaction ID: " . ($row['transaction_id'] ?? 'NULL') . "\n";
        echo "  Payment Date: " . ($row['payment_date'] ?? 'NULL') . "\n";
        echo "  Created: " . ($row['created_at'] ?? 'NULL') . "\n";
        echo "\n";
    }
    
    echo "Total payments found: " . count($all_payments) . "\n\n";
    
    // Check pending appointments
    echo "=== PENDING APPOINTMENTS ===\n";
    $pending_stmt = $conn->query("
        SELECT a.appointment_id, a.patient_id, a.status, a.scheduled_datetime, a.price,
               p.payment_id, p.payment_method, p.payment_status, p.transaction_id
        FROM tbl_appointments a
        LEFT JOIN tbl_payments p ON a.appointment_id = p.appointment_id
        WHERE a.status = 'Pending'
        ORDER BY a.created_at DESC
    ");
    
    $pending_appointments = [];
    while ($row = $pending_stmt->fetch_assoc()) {
        $pending_appointments[] = $row;
        echo "Appointment ID: " . $row['appointment_id'] . "\n";
        echo "  Patient ID: " . $row['patient_id'] . "\n";
        echo "  Status: " . $row['status'] . "\n";
        echo "  Scheduled: " . $row['scheduled_datetime'] . "\n";
        echo "  Price: " . $row['price'] . "\n";
        echo "  Payment ID: " . ($row['payment_id'] ?? 'NULL') . "\n";
        echo "  Payment Method: " . ($row['payment_method'] ?? 'NULL') . "\n";
        echo "  Payment Status: " . ($row['payment_status'] ?? 'NULL') . "\n";
        echo "  Transaction ID: " . ($row['transaction_id'] ?? 'NULL') . "\n";
        echo "\n";
    }
    
    echo "Total pending appointments: " . count($pending_appointments) . "\n\n";
    
    // Check PayMongo payments specifically
    echo "=== PAYMONGO PAYMENTS ===\n";
    $paymongo_stmt = $conn->query("
        SELECT payment_id, appointment_id, patient_id, payment_type, payment_method,
               payment_status, amount, transaction_id, payment_date
        FROM tbl_payments
        WHERE payment_method = 'PayMongo'
        ORDER BY created_at DESC
    ");
    
    $paymongo_payments = [];
    while ($row = $paymongo_stmt->fetch_assoc()) {
        $paymongo_payments[] = $row;
        echo "Payment ID: " . $row['payment_id'] . "\n";
        echo "  Appointment ID: " . $row['appointment_id'] . "\n";
        echo "  Payment Status: " . $row['payment_status'] . "\n";
        echo "  Transaction ID: " . $row['transaction_id'] . "\n";
        echo "  Amount: " . $row['amount'] . "\n";
        echo "\n";
    }
    
    echo "Total PayMongo payments: " . count($paymongo_payments) . "\n\n";
    
    // Check table structure
    echo "=== TABLE STRUCTURE ===\n";
    $columns_stmt = $conn->query("SHOW COLUMNS FROM tbl_payments");
    echo "tbl_payments columns:\n";
    while ($col = $columns_stmt->fetch_assoc()) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    echo "\n";
    
    $columns_stmt = $conn->query("SHOW COLUMNS FROM tbl_appointments");
    echo "tbl_appointments columns:\n";
    while ($col = $columns_stmt->fetch_assoc()) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    echo "\n";
    
    $conn->close();
    
    echo "=== Summary ===\n";
    echo "Total appointments: " . count($all_appointments) . "\n";
    echo "Total payments: " . count($all_payments) . "\n";
    echo "Pending appointments: " . count($pending_appointments) . "\n";
    echo "PayMongo payments: " . count($paymongo_payments) . "\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Payment Debug Error: " . $e->getMessage());
}
