<?php
/**
 * PayMongo Payment Status Checker
 * Automatically checks and updates pending PayMongo payments
 * Run this via cron job every minute: * * * * * php /path/to/auto_verify_payments.php
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/services/payment_service.php';

echo "=== Auto Payment Verification ===\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $conn = getDBConnection();
    $paymentService = new PaymentService();
    
    // Find all pending PayMongo payments from the last 24 hours
    $stmt = $conn->prepare("
        SELECT payment_id, appointment_id, patient_id, amount, transaction_id
        FROM tbl_payments 
        WHERE transaction_id LIKE 'PM-LINK-%'
        AND payment_status = 'Pending'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    $stmt->close();
    
    echo "Found " . count($payments) . " pending payment(s) to check\n\n";
    
    $verified = 0;
    foreach ($payments as $payment) {
        echo "Checking Payment #" . $payment['payment_id'] . "...\n";
        
        // Extract link ID
        $linkId = str_replace('PM-LINK-', '', $payment['transaction_id']);
        $linkId = str_replace('link_', '', $linkId);
        
        // Verify with PayMongo
        $linkStatus = $paymentService->getPaymentLinkStatus($linkId);
        
        if ($linkStatus['success'] && $linkStatus['is_paid']) {
            echo "  ✅ PAID! Updating...\n";
            
            // Update payment
            $update_stmt = $conn->prepare("
                UPDATE tbl_payments 
                SET payment_status = 'Completed', payment_date = NOW()
                WHERE payment_id = ?
            ");
            $update_stmt->bind_param("i", $payment['payment_id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Update appointment
            $update_apt = $conn->prepare("
                UPDATE tbl_appointments 
                SET status = 'Scheduled' 
                WHERE appointment_id = ?
            ");
            $update_apt->bind_param("i", $payment['appointment_id']);
            $update_apt->execute();
            $update_apt->close();
            
            echo "  ✅ Updated!\n";
            $verified++;
        } else {
            echo "  ⏳ Still pending\n";
        }
    }
    
    $conn->close();
    
    echo "\n=== Complete ===\n";
    echo "Verified: $verified payment(s)\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Auto verify error: " . $e->getMessage());
}
