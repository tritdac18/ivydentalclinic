<?php
require_once 'config/db.php';
require_once 'config/security.php';

// Set security headers and check login
requireLogin();

header('Content-Type: application/json');

// Get invoice_id from query parameter
$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;

if ($invoice_id <= 0) {
    echo json_encode(['error' => 'Invalid invoice ID']);
    exit();
}

// Verify user is a patient and owns this invoice
$user_type = $_SESSION['user_type'] ?? 'patient';
$user_id = $_SESSION['user_id'] ?? 0;

try {
    $conn = getDBConnection();
    
    // Verify invoice belongs to the logged-in patient
    if ($user_type === 'patient') {
        $verify_stmt = $conn->prepare("SELECT patient_id FROM tbl_invoices WHERE invoice_id = ? AND patient_id = ? LIMIT 1");
        $verify_stmt->bind_param("ii", $invoice_id, $user_id);
        $verify_stmt->execute();
        $verify_result = $verify_stmt->get_result();
        
        if ($verify_result->num_rows === 0) {
            $verify_stmt->close();
            $conn->close();
            echo json_encode(['error' => 'Invoice not found or access denied']);
            exit();
        }
        $verify_stmt->close();
    }
    
    // Fetch payment information
    $payments = [];
    $table_check = $conn->query("SHOW TABLES LIKE 'tbl_payments'");
    if ($table_check && $table_check->num_rows > 0) {
        // Check if payment_date column exists (unified table) or transaction_date (old table)
        $col_check = $conn->query("SHOW COLUMNS FROM tbl_payments LIKE 'payment_date'");
        $has_payment_date = $col_check && $col_check->num_rows > 0;
        $date_column = $has_payment_date ? 'payment_date' : 'transaction_date';
        
        $payment_stmt = $conn->prepare("
            SELECT 
                p.payment_id,
                p.amount,
                p.payment_method,
                p.{$date_column} as transaction_date,
                p.payment_date,
                p.transaction_id
            FROM tbl_payments p
            WHERE p.invoice_id = ?
            ORDER BY p.{$date_column} DESC
        ");
        $payment_stmt->bind_param("i", $invoice_id);
        $payment_stmt->execute();
        $payment_result = $payment_stmt->get_result();
        
        while ($row = $payment_result->fetch_assoc()) {
            $payments[] = $row;
        }
        $payment_stmt->close();
    }
    
    $conn->close();
    
    echo json_encode(['payments' => $payments]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

