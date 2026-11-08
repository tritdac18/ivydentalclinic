<?php
/**
 * Verify Appointments with PayMongo
 * This script checks pending appointments and verifies their payment status
 * It will create payment records if missing and verify with PayMongo API
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/services/payment_service.php';
require_once __DIR__ . '/services/email_service.php';

// Check if called via AJAX (for automatic verification)
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
$is_ajax = $is_ajax || (isset($_GET['ajax']) && $_GET['ajax'] == '1');

if ($is_ajax) {
    header('Content-Type: application/json');
} else {
    echo "=== PayMongo Appointment Verification Script ===\n";
    echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";
}

try {
    $conn = getDBConnection();
    $paymentService = new PaymentService();
    
    // Get all PayMongo payments that need verification
    // Include Pending payments AND payments that are paid on PayMongo but not marked Completed
    $stmt = $conn->prepare("
        SELECT 
            payment_id, 
            appointment_id, 
            invoice_id, 
            patient_id, 
            amount, 
            transaction_id, 
            payment_type,
            payment_status,
            payment_method
        FROM tbl_payments 
        WHERE payment_method = 'E-Wallet'
        AND transaction_id LIKE 'PM-LINK-%'
        AND payment_status != 'Completed'
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $pendingPayments = [];
    while ($row = $result->fetch_assoc()) {
        $pendingPayments[] = $row;
    }
    $stmt->close();
    
    if (!$is_ajax) {
        echo "Found " . count($pendingPayments) . " pending PayMongo payment(s)\n\n";
    }
    
    $verifiedCount = 0;
    $updatedCount = 0;
    $failedCount = 0;
    
    foreach ($pendingPayments as $payment) {
        if (!$is_ajax) {
            echo "=== Checking Payment ID: " . $payment['payment_id'] . " ===\n";
            echo "  Payment Type: " . $payment['payment_type'] . "\n";
            echo "  Appointment ID: " . ($payment['appointment_id'] ?? 'NULL') . "\n";
            echo "  Invoice ID: " . ($payment['invoice_id'] ?? 'NULL') . "\n";
            echo "  Patient ID: " . $payment['patient_id'] . "\n";
            echo "  Amount: " . $payment['amount'] . "\n";
            echo "  Transaction ID: " . $payment['transaction_id'] . "\n";
        }
        
        // Extract link_id from transaction_id
        // Handle both formats: PM-LINK-xxxxx and PM-LINK-link_xxxxx
        $transaction_id = $payment['transaction_id'];
        $linkId = str_replace('PM-LINK-', '', $transaction_id);
        $linkId = str_replace('link_', '', $linkId);
        
        // Also try the original transaction_id if it contains link_ directly
        if (strpos($transaction_id, 'link_') !== false) {
            $linkId = str_replace('PM-LINK-', '', $transaction_id);
        }
        
        if (!$is_ajax) {
            echo "  → Transaction ID: " . $transaction_id . "\n";
            echo "  → Extracted Link ID: " . $linkId . "\n";
            echo "  → Verifying with PayMongo...\n";
        }
        
        // Verify with PayMongo - try multiple formats
        $linkStatus = null;
        $tried_formats = [];
        
        // Try 1: Direct link ID (without link_ prefix)
        $tried_formats[] = $linkId;
        $linkStatus = $paymentService->getPaymentLinkStatus($linkId);
        
        // Try 2: With link_ prefix if first attempt failed
        if (!$linkStatus || !$linkStatus['success']) {
            $linkIdWithPrefix = 'link_' . $linkId;
            $tried_formats[] = $linkIdWithPrefix;
            $linkStatus = $paymentService->getPaymentLinkStatus($linkIdWithPrefix);
        }
        
        // Try 3: Original transaction_id if it's a direct link ID
        if ((!$linkStatus || !$linkStatus['success']) && strpos($transaction_id, 'link_') !== false) {
            $tried_formats[] = $transaction_id;
            $linkStatus = $paymentService->getPaymentLinkStatus($transaction_id);
        }
        
        if (!$is_ajax && count($tried_formats) > 1) {
            echo "  → Tried formats: " . implode(', ', $tried_formats) . "\n";
        }
        
        if ($linkStatus && $linkStatus['success'] && $linkStatus['is_paid']) {
            if (!$is_ajax) {
                echo "  ✓ Payment VERIFIED via PayMongo API (Status: " . ($linkStatus['status'] ?? 'paid') . ")\n";
                echo "  → Current DB status: " . $payment['payment_status'] . "\n";
            }
            
            // Only update if not already Completed
            if ($payment['payment_status'] === 'Completed') {
                if (!$is_ajax) {
                    echo "  ℹ️ Payment already marked as Completed, skipping update\n";
                }
                $updatedCount++;
                continue;
            }
            
            // Update payment status
            $update_stmt = $conn->prepare("
                UPDATE tbl_payments 
                SET payment_status = 'Completed', payment_date = NOW()
                WHERE payment_id = ?
                AND payment_status != 'Completed'
            ");
            $update_stmt->bind_param("i", $payment['payment_id']);
            $update_result = $update_stmt->execute();
            $affected_rows = $conn->affected_rows;
            $update_stmt->close();
            
            if (!$update_result) {
                if (!$is_ajax) {
                    echo "  ✗ Failed to update payment status. Error: " . ($conn->error ?? 'Unknown') . "\n";
                }
                error_log("Failed to update payment #{$payment['payment_id']}: " . ($conn->error ?? 'Unknown'));
                $failedCount++;
                continue;
            }
            
            if ($affected_rows == 0) {
                if (!$is_ajax) {
                    echo "  ℹ️ Payment may have been updated by another process (no rows affected)\n";
                }
                // Still count as updated since it's already Completed
                $updatedCount++;
                continue;
            }
            
            if (!$is_ajax) {
                echo "  ✓ Payment status updated to Completed (affected rows: $affected_rows)\n";
            }
            
            // Update based on payment type
            if ($payment['payment_type'] === 'Downpayment' && $payment['appointment_id']) {
                // Update appointment status for downpayments
                $update_appointment = $conn->prepare("
                    UPDATE tbl_appointments 
                    SET status = 'Scheduled' 
                    WHERE appointment_id = ?
                ");
                $update_appointment->bind_param("i", $payment['appointment_id']);
                $update_appointment->execute();
                $update_appointment->close();
                
                if (!$is_ajax) {
                    echo "  ✓ Appointment updated to Scheduled\n";
                }
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
                        if (!$is_ajax) {
                            echo "  ✓ Invoice fully paid, updating status to Paid\n";
                        }
                    } else {
                        $update_invoice = $conn->prepare("
                            UPDATE tbl_invoices 
                            SET payment_status = 'Partial' 
                            WHERE invoice_id = ?
                            AND payment_status != 'Paid'
                        ");
                        if (!$is_ajax) {
                            echo "  ✓ Invoice partially paid, updating status to Partial\n";
                        }
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
            
            $verifiedCount++;
            $updatedCount++;
        } else {
            if (!$is_ajax) {
                echo "  ✗ Payment NOT verified (Status: " . ($linkStatus['status'] ?? 'unknown') . ")\n";
            }
            $failedCount++;
        }
        
        if (!$is_ajax) {
            echo "\n";
        }
    }
    
    $conn->close();
    
    if ($is_ajax) {
        // Return JSON response for AJAX calls
        echo json_encode([
            'success' => true,
            'verified' => $verifiedCount,
            'updated' => $updatedCount,
            'failed' => $failedCount,
            'total_checked' => count($pendingPayments)
        ]);
    } else {
        echo "=== Summary ===\n";
        echo "Total pending payments checked: " . count($pendingPayments) . "\n";
        echo "Verified and updated: " . $verifiedCount . "\n";
        echo "Failed/Not paid: " . $failedCount . "\n";
        echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
    }
    
} catch (Exception $e) {
    if ($is_ajax) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    } else {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
    error_log("Appointment Verification Error: " . $e->getMessage());
}

