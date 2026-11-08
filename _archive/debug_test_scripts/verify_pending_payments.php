<?php
/**
 * Verify Pending PayMongo Payments
 * This script checks all pending PayMongo payments and verifies their status
 * Run this manually or via cron to ensure payments are validated
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/services/payment_service.php';
require_once __DIR__ . '/services/email_service.php';

echo "=== PayMongo Payment Verification Script ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $conn = getDBConnection();
    $paymentService = new PaymentService();
    
    // Get all pending PayMongo payments
    // Check for payments with PM-LINK transaction IDs (PayMongo payments)
    $stmt = $conn->prepare("
        SELECT payment_id, appointment_id, patient_id, amount, transaction_id, payment_date, payment_method, payment_status
        FROM tbl_payments 
        WHERE (
            transaction_id LIKE 'PM-LINK-%'
            OR transaction_id LIKE '%link_%'
        )
        AND payment_status = 'Pending'
        ORDER BY payment_date DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pendingPayments = [];
    while ($row = $result->fetch_assoc()) {
        $pendingPayments[] = $row;
    }
    $stmt->close();
    
    echo "Found " . count($pendingPayments) . " pending PayMongo payment(s)\n\n";
    
    // Also check appointments without payments
    $appt_stmt = $conn->prepare("
        SELECT a.appointment_id, a.patient_id, a.status, a.price, a.scheduled_datetime
        FROM tbl_appointments a
        LEFT JOIN tbl_payments p ON a.appointment_id = p.appointment_id
        WHERE a.status = 'Pending'
        AND (p.payment_id IS NULL OR p.payment_status != 'Completed')
        ORDER BY a.created_at DESC
    ");
    $appt_stmt->execute();
    $appt_result = $appt_stmt->get_result();
    
    $appointmentsWithoutPayments = [];
    while ($row = $appt_result->fetch_assoc()) {
        $appointmentsWithoutPayments[] = $row;
    }
    $appt_stmt->close();
    
    if (count($appointmentsWithoutPayments) > 0) {
        echo "WARNING: Found " . count($appointmentsWithoutPayments) . " pending appointment(s) without completed payments:\n";
        foreach ($appointmentsWithoutPayments as $apt) {
            echo "  - Appointment ID: " . $apt['appointment_id'] . ", Patient ID: " . $apt['patient_id'] . ", Price: " . $apt['price'] . "\n";
        }
        echo "\n";
    }
    
    $verifiedCount = 0;
    $failedCount = 0;
    
    foreach ($pendingPayments as $payment) {
        echo "Checking Payment ID: " . $payment['payment_id'] . "\n";
        echo "  Appointment ID: " . $payment['appointment_id'] . "\n";
        echo "  Transaction ID: " . $payment['transaction_id'] . "\n";
        
        // Extract link_id from transaction_id
        $linkId = str_replace('PM-LINK-', '', $payment['transaction_id']);
        $linkId = str_replace('link_', '', $linkId);
        
        echo "  Link ID: " . $linkId . "\n";
        
        // Verify payment status with PayMongo
        $linkStatus = $paymentService->getPaymentLinkStatus($linkId);
        
        if ($linkStatus['success'] && $linkStatus['is_paid']) {
            echo "  ✓ Payment VERIFIED via PayMongo API\n";
            
            // Update payment status
            $update_stmt = $conn->prepare("
                UPDATE tbl_payments 
                SET payment_status = 'Completed', payment_date = NOW()
                WHERE payment_id = ?
            ");
            $update_stmt->bind_param("i", $payment['payment_id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Update appointment status
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
            } else {
                echo "  ✗ Failed to update appointment: " . $conn->error . "\n";
            }
            $update_appointment->close();
            
            echo "  ✓ Payment and appointment updated\n";
            
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
            echo "  ✗ Payment NOT verified (Status: " . ($linkStatus['status'] ?? 'unknown') . ")\n";
            $failedCount++;
        }
        
        echo "\n";
    }
    
    $conn->close();
    
    echo "=== Summary ===\n";
    echo "Verified: " . $verifiedCount . "\n";
    echo "Still Pending: " . $failedCount . "\n";
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Payment Verification Error: " . $e->getMessage());
}

