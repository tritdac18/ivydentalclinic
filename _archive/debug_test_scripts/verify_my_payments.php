<?php
/**
 * Quick Payment Verifier
 * Checks and updates pending PayMongo payments for the logged-in patient
 * Call this via AJAX from appointments page
 */

header('Content-Type: application/json');

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/services/payment_service.php';

requireLogin();

$patient_id = $_SESSION['user_id'] ?? 0;
$response = ['success' => false, 'message' => '', 'verified' => 0];

if ($patient_id > 0) {
    try {
        $conn = getDBConnection();
        $paymentService = new PaymentService();
        
        // Find ALL pending PayMongo payments for this patient (downpayments AND invoices)
        $stmt = $conn->prepare("
            SELECT payment_id, appointment_id, invoice_id, patient_id, amount, transaction_id, payment_type
            FROM tbl_payments 
            WHERE patient_id = ?
            AND transaction_id LIKE 'PM-LINK-%'
            AND payment_status = 'Pending'
            ORDER BY created_at DESC
        ");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $payments = [];
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        $stmt->close();
        
        $verified = 0;
        foreach ($payments as $payment) {
            // Extract link ID
            $linkId = str_replace('PM-LINK-', '', $payment['transaction_id']);
            $linkId = str_replace('link_', '', $linkId);
            
            // Verify with PayMongo
            $linkStatus = $paymentService->getPaymentLinkStatus($linkId);
            
            if ($linkStatus['success'] && $linkStatus['is_paid']) {
                // Update payment
                $update_stmt = $conn->prepare("
                    UPDATE tbl_payments 
                    SET payment_status = 'Completed', payment_date = NOW()
                    WHERE payment_id = ?
                ");
                $update_stmt->bind_param("i", $payment['payment_id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Update appointment OR invoice based on payment type
                if ($payment['payment_type'] === 'Downpayment' && $payment['appointment_id']) {
                    // Update appointment status for downpayments
                    $update_apt = $conn->prepare("
                        UPDATE tbl_appointments 
                        SET status = 'Scheduled' 
                        WHERE appointment_id = ?
                    ");
                    $update_apt->bind_param("i", $payment['appointment_id']);
                    $update_apt->execute();
                    $update_apt->close();
                } elseif ($payment['payment_type'] === 'Invoice Payment' && $payment['invoice_id']) {
                    // Update invoice status for invoice payments
                    // First, calculate total paid for this invoice
                    $total_stmt = $conn->prepare("
                        SELECT 
                            i.total_amount,
                            COALESCE(SUM(CASE WHEN p.payment_status = 'Completed' THEN p.amount ELSE 0 END), 0) as total_paid
                        FROM tbl_invoices i
                        LEFT JOIN tbl_payments p ON i.invoice_id = p.invoice_id
                        WHERE i.invoice_id = ?
                        GROUP BY i.invoice_id
                    ");
                    $total_stmt->bind_param("i", $payment['invoice_id']);
                    $total_stmt->execute();
                    $total_result = $total_stmt->get_result();
                    $invoice_data = $total_result->fetch_assoc();
                    $total_stmt->close();
                    
                    if ($invoice_data) {
                        $total_amount = floatval($invoice_data['total_amount']);
                        $total_paid = floatval($invoice_data['total_paid']);
                        
                        // Check if fully paid
                        if ($total_paid >= ($total_amount - 0.01)) {
                            $update_invoice = $conn->prepare("
                                UPDATE tbl_invoices 
                                SET payment_status = 'Paid' 
                                WHERE invoice_id = ?
                            ");
                        } else {
                            $update_invoice = $conn->prepare("
                                UPDATE tbl_invoices 
                                SET payment_status = 'Partial' 
                                WHERE invoice_id = ?
                                AND payment_status != 'Paid'
                            ");
                        }
                        $update_invoice->bind_param("i", $payment['invoice_id']);
                        $update_invoice->execute();
                        $update_invoice->close();
                    }
                    
                    // Also update appointment to Completed if it exists
                    if ($payment['appointment_id']) {
                        $update_apt = $conn->prepare("
                            UPDATE tbl_appointments 
                            SET status = 'Completed' 
                            WHERE appointment_id = ?
                        ");
                        $update_apt->bind_param("i", $payment['appointment_id']);
                        $update_apt->execute();
                        $update_apt->close();
                    }
                }
                
                $verified++;
            }
        }
        
        $conn->close();
        
        if ($verified > 0) {
            $response['success'] = true;
            $response['verified'] = $verified;
            $response['message'] = "$verified payment(s) verified successfully!";
        } else if (count($payments) > 0) {
            $response['message'] = "Payment verification pending. Please complete payment on PayMongo first.";
        } else {
            $response['message'] = "No pending payments found.";
        }
        
    } catch (Exception $e) {
        $response['message'] = "Error: " . $e->getMessage();
        error_log("Quick verify error: " . $e->getMessage());
    }
} else {
    $response['message'] = "Not logged in";
}

echo json_encode($response);
