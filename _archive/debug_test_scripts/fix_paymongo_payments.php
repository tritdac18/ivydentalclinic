<?php
/**
 * Fix PayMongo Payments
 * Updates payment_method for PayMongo payments and verifies them
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/services/payment_service.php';
require_once __DIR__ . '/services/email_service.php';

echo "=== Fix PayMongo Payments Script ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $conn = getDBConnection();
    $paymentService = new PaymentService();
    
    // Find ALL pending PayMongo payments (transaction_id starts with PM-LINK-)
    $stmt = $conn->prepare("
        SELECT payment_id, appointment_id, patient_id, amount, transaction_id, payment_status, payment_method
        FROM tbl_payments 
        WHERE transaction_id LIKE 'PM-LINK-%'
        AND payment_status = 'Pending'
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $paymongoPayments = [];
    while ($row = $result->fetch_assoc()) {
        $paymongoPayments[] = $row;
    }
    $stmt->close();
    
    echo "Found " . count($paymongoPayments) . " pending PayMongo payment(s)\n\n";
    
    $updatedCount = 0;
    $verifiedCount = 0;
    
    foreach ($paymongoPayments as $payment) {
        echo "=== Processing Payment ID: " . $payment['payment_id'] . " ===\n";
        echo "  Appointment ID: " . $payment['appointment_id'] . "\n";
        echo "  Transaction ID: " . $payment['transaction_id'] . "\n";
        echo "  Current Status: " . $payment['payment_status'] . "\n";
        echo "  Payment Method: " . ($payment['payment_method'] ?? 'NULL') . "\n";
        
        // Extract link_id from transaction_id
        $linkId = str_replace('PM-LINK-', '', $payment['transaction_id']);
        $linkId = str_replace('link_', '', $linkId);
        
        echo "  → Extracted Link ID: " . $linkId . "\n";
        echo "  → Verifying with PayMongo API...\n";
        
        // Verify payment with PayMongo
        $linkStatus = $paymentService->getPaymentLinkStatus($linkId);
        
        if ($linkStatus['success'] && $linkStatus['is_paid']) {
            echo "  ✓ Payment VERIFIED via PayMongo API (Status: " . $linkStatus['status'] . ")\n";
            
            // Update payment status to Completed
            $update_status_stmt = $conn->prepare("
                UPDATE tbl_payments 
                SET payment_status = 'Completed', payment_date = NOW()
                WHERE payment_id = ?
            ");
            $update_status_stmt->bind_param("i", $payment['payment_id']);
            $update_status_stmt->execute();
            $update_status_stmt->close();
            
            echo "  ✓ Payment status updated to Completed\n";
            
            // Update appointment status to Scheduled
            $update_appointment = $conn->prepare("
                UPDATE tbl_appointments 
                SET status = 'Scheduled' 
                WHERE appointment_id = ?
            ");
            $update_appointment->bind_param("i", $payment['appointment_id']);
            $appointment_update_result = $update_appointment->execute();
            
            if ($appointment_update_result) {
                $affected_rows = $conn->affected_rows;
                echo "  ✓ Appointment updated to Scheduled (affected rows: " . $affected_rows . ")\n";
                
                // Verify the update
                $verify_stmt = $conn->prepare("SELECT status FROM tbl_appointments WHERE appointment_id = ?");
                $verify_stmt->bind_param("i", $payment['appointment_id']);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                if ($verify_result->num_rows > 0) {
                    $verify_row = $verify_result->fetch_assoc();
                    echo "  ✓ Verified: Appointment status is now '" . $verify_row['status'] . "'\n";
                }
                $verify_stmt->close();
                
                // Send email notification
                $email_stmt = $conn->prepare("
                    SELECT p.email, s.service_name, pay.amount
                    FROM tbl_patients p
                    INNER JOIN tbl_appointments a ON a.patient_id = p.patient_id
                    LEFT JOIN tbl_service_catalog s ON a.service_id = s.service_id
                    INNER JOIN tbl_payments pay ON pay.appointment_id = a.appointment_id
                    WHERE a.appointment_id = ?
                    ORDER BY pay.payment_date DESC
                    LIMIT 1
                ");
                $email_stmt->bind_param("i", $payment['appointment_id']);
                $email_stmt->execute();
                $email_result = $email_stmt->get_result();
                
                if ($email_result->num_rows > 0) {
                    $patient_data = $email_result->fetch_assoc();
                    try {
                        $emailService = new EmailService();
                        $emailService->sendPaymentConfirmationEmail(
                            $patient_data['email'],
                            $patient_data['amount'],
                            $payment['appointment_id'],
                            $patient_data['service_name'] ?? 'Appointment'
                        );
                        echo "  ✓ Confirmation email sent\n";
                    } catch (Exception $e) {
                        echo "  ✗ Failed to send email: " . $e->getMessage() . "\n";
                    }
                }
                $email_stmt->close();
                
                $verifiedCount++;
            } else {
                echo "  ✗ Failed to update appointment: " . $conn->error . "\n";
            }
            $update_appointment->close();
        } else {
            echo "  ✗ Payment NOT verified (Status: " . ($linkStatus['status'] ?? 'unknown') . ")\n";
            echo "  → Payment remains Pending until verified\n";
        }
        
        $updatedCount++;
        echo "\n";
    }
    
    $conn->close();
    
    echo "=== Summary ===\n";
    echo "Payments updated: " . $updatedCount . "\n";
    echo "Payments verified and appointments updated: " . $verifiedCount . "\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Fix PayMongo Payments Error: " . $e->getMessage());
}

