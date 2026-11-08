<?php
/**
 * Simple webhook logger - logs ALL incoming requests
 * This will help us see if PayMongo is calling the webhook at all
 */

// Ensure log directory exists
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

$logFile = $logDir . '/paymongo_webhook_incoming.log';
$timestamp = date('Y-m-d H:i:s');
$method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$headers = getallheaders();
$payload = file_get_contents('php://input');

$logEntry = "\n=== WEBHOOK CALL $timestamp ===\n";
$logEntry .= "Method: $method\n";
$logEntry .= "Headers: " . json_encode($headers, JSON_PRETTY_PRINT) . "\n";
$logEntry .= "Payload: $payload\n";
$logEntry .= "=== END ===\n";

// Create log file if it doesn't exist
@file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

// Now continue with normal webhook processing
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/paymongo_config.php';
require_once __DIR__ . '/../../services/payment_service.php';
require_once __DIR__ . '/../../services/email_service.php';

// Get raw POST data
$payload = @file_get_contents('php://input');

// PayMongo sends signature in X-Paymongo-Signature header
$signature = $_SERVER['HTTP_X_PAYMONGO_SIGNATURE'] ?? $_SERVER['X-Paymongo-Signature'] ?? '';

// Verify webhook signature
if (!empty(PAYMONGO_WEBHOOK_SECRET) && PAYMONGO_WEBHOOK_SECRET !== 'whsec_xxxxxxxxxxxxxxxxxxxxx') {
    $paymentService = new PaymentService();
    if (!$paymentService->verifyWebhookSignature($payload, $signature)) {
        http_response_code(401);
        error_log('PayMongo Webhook: Invalid signature');
        file_put_contents($logFile, "❌ SIGNATURE VERIFICATION FAILED\n", FILE_APPEND);
        exit('Invalid signature');
    }
    file_put_contents($logFile, "✅ Signature verified\n", FILE_APPEND);
} else {
    error_log('PayMongo Webhook: Webhook secret not configured - skipping signature verification');
    file_put_contents($logFile, "⚠️ No signature verification (secret not configured)\n", FILE_APPEND);
}

// Parse webhook data
$data = json_decode($payload, true);

if (!isset($data['data']['type'])) {
    http_response_code(400);
    file_put_contents($logFile, "❌ Invalid webhook data format\n", FILE_APPEND);
    exit('Invalid webhook data');
}

$eventType = $data['data']['type'];
$eventData = $data['data']['attributes'];

// Log webhook event
error_log('PayMongo Webhook: ' . $eventType . ' - ' . json_encode($eventData));
file_put_contents($logFile, "Event Type: $eventType\n", FILE_APPEND);

// Handle ALL payment-related events from PayMongo
// PayMongo can send: link.paid, payment.paid, payment.succeeded, etc.
$isPaymentEvent = (
    $eventType === 'link.paid' || 
    $eventType === 'link.payment.paid' || 
    $eventType === 'payment.paid' ||
    $eventType === 'payment.succeeded' ||
    strpos($eventType, 'paid') !== false ||
    strpos($eventType, 'payment') !== false
);

file_put_contents($logFile, "Checking if event is payment-related: $eventType -> " . ($isPaymentEvent ? 'YES' : 'NO') . "\n", FILE_APPEND);

if ($isPaymentEvent) {
    file_put_contents($logFile, "✅ Processing payment event: $eventType\n", FILE_APPEND);
    
    // PayMongo sends link ID in data.id for link events, or in data.attributes.link for payment events
    $linkId = $data['data']['id'] ?? null;
    
    // For payment events, the link might be in attributes
    if (!$linkId && isset($data['data']['attributes']['link'])) {
        $linkId = $data['data']['attributes']['link'];
        file_put_contents($logFile, "⚠️ Link ID found in attributes.link: $linkId\n", FILE_APPEND);
    }
    
    // Also check if it's a payment object with link reference
    if (!$linkId && isset($data['data']['attributes']['data']['attributes']['link'])) {
        $linkId = $data['data']['attributes']['data']['attributes']['link'];
        file_put_contents($logFile, "⚠️ Link ID found in nested attributes: $linkId\n", FILE_APPEND);
    }
    
    error_log('PayMongo Webhook: payment event received. Link ID: ' . ($linkId ?? 'NULL'));
    file_put_contents($logFile, "Link ID extracted: " . ($linkId ?? 'NULL') . "\n", FILE_APPEND);
    
    if ($linkId) {
        $conn = getDBConnection();
        $paymentService = new PaymentService();
        
        // Find payment by link ID - get ALL payments with this transaction_id (multiple invoices can share one link)
        $linkId_clean = str_replace('link_', '', $linkId);
        $transaction_id = 'PM-LINK-' . $linkId_clean;
        
        file_put_contents($logFile, "Link ID: $linkId, Clean: $linkId_clean, Transaction ID: $transaction_id\n", FILE_APPEND);
        
        // Try to get metadata from webhook event data first
        $metadata = $eventData['metadata'] ?? [];
        
        // If metadata not in webhook, fetch link details from PayMongo API to get remarks
        if (empty($metadata) || !isset($metadata['invoice_ids'])) {
            file_put_contents($logFile, "⚠️ Metadata not in webhook, fetching link details from PayMongo API...\n", FILE_APPEND);
            $linkStatus = $paymentService->getPaymentLinkStatus($linkId);
            
            if ($linkStatus['success'] && isset($linkStatus['remarks'])) {
                // Remarks is stored as JSON string
                $remarks = $linkStatus['remarks'] ?? null;
                if (is_string($remarks)) {
                    $metadata = json_decode($remarks, true) ?? [];
                    file_put_contents($logFile, "✅ Retrieved metadata from link remarks: " . json_encode($metadata) . "\n", FILE_APPEND);
                }
            } else {
                // Try to get remarks from webhook event data attributes
                $remarks = $eventData['remarks'] ?? null;
                if (is_string($remarks)) {
                    $metadata = json_decode($remarks, true) ?? [];
                    file_put_contents($logFile, "✅ Retrieved metadata from webhook remarks: " . json_encode($metadata) . "\n", FILE_APPEND);
                }
            }
        }
        
        $invoice_ids_meta = isset($metadata['invoice_ids']) ? explode(',', $metadata['invoice_ids']) : [];
        $patient_id_meta = isset($metadata['patient_id']) ? intval($metadata['patient_id']) : null;
        $payment_type_meta = $metadata['payment_type'] ?? 'Invoice Payment';
        
        file_put_contents($logFile, "Final Metadata: " . json_encode($metadata) . "\n", FILE_APPEND);
        file_put_contents($logFile, "Invoice IDs from metadata: " . json_encode($invoice_ids_meta) . "\n", FILE_APPEND);
        file_put_contents($logFile, "Patient ID from metadata: " . ($patient_id_meta ?? 'NULL') . "\n", FILE_APPEND);
        
        // Find ALL payments with this transaction_id (both Pending and Completed)
        // This handles cases where payments were already created as Completed from metadata
        $stmt = $conn->prepare("
            SELECT payment_id, appointment_id, invoice_id, patient_id, amount, transaction_id, payment_type, payment_status
            FROM tbl_payments 
            WHERE transaction_id = ?
        ");
        $stmt->bind_param("s", $transaction_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $payments = [];
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        $stmt->close();
        
        $pending_count = count(array_filter($payments, function($p) { return $p['payment_status'] === 'Pending'; }));
        $completed_count = count(array_filter($payments, function($p) { return $p['payment_status'] === 'Completed'; }));
        file_put_contents($logFile, "Found " . count($payments) . " payment(s) for transaction_id: $transaction_id (Pending: $pending_count, Completed: $completed_count)\n", FILE_APPEND);
        
        // If no payments found, try to create them from metadata or from invoice IDs
        if (empty($payments)) {
            file_put_contents($logFile, "⚠️ No payments found for transaction_id: $transaction_id\n", FILE_APPEND);
            
            // If we have invoice IDs from metadata, create payments
            if (!empty($invoice_ids_meta) && $patient_id_meta) {
                file_put_contents($logFile, "⚠️ No payments found, creating from metadata...\n", FILE_APPEND);
                
                // Get payment amount from webhook
                $amount = isset($eventData['amount']) ? floatval($eventData['amount']) / 100 : null; // PayMongo amounts are in cents
                
                // If we have invoice IDs, fetch invoice details
                if (!empty($invoice_ids_meta)) {
                foreach ($invoice_ids_meta as $invoice_id_str) {
                    $invoice_id = intval(trim($invoice_id_str));
                    if ($invoice_id <= 0) continue;
                    
                    // Get invoice details
                    $invoice_stmt = $conn->prepare("
                        SELECT invoice_id, patient_id, total_amount, appointment_id 
                        FROM tbl_invoices 
                        WHERE invoice_id = ?
                        LIMIT 1
                    ");
                    $invoice_stmt->bind_param("i", $invoice_id);
                    $invoice_stmt->execute();
                    $invoice_result = $invoice_stmt->get_result();
                    
                    if ($invoice_row = $invoice_result->fetch_assoc()) {
                        $invoice_amount = floatval($invoice_row['total_amount']);
                        $invoice_patient_id = $invoice_row['patient_id'];
                        $invoice_appointment_id = isset($invoice_row['appointment_id']) && $invoice_row['appointment_id'] > 0 ? $invoice_row['appointment_id'] : null;
                        
                        // Create payment record
                        date_default_timezone_set('Asia/Manila');
                        $created_at = date('Y-m-d H:i:s');
                        
                        $insert_stmt = $conn->prepare("
                            INSERT INTO tbl_payments 
                            (payment_type, invoice_id, appointment_id, patient_id, amount, payment_method, transaction_id, payment_status, created_at, payment_date) 
                            VALUES (?, ?, ?, ?, ?, 'E-Wallet', ?, 'Completed', ?, NOW())
                        ");
                        $insert_stmt->bind_param("siiids", $payment_type_meta, $invoice_id, $invoice_appointment_id, $invoice_patient_id, $invoice_amount, $transaction_id, $created_at);
                        
                        if ($insert_stmt->execute()) {
                            $new_payment_id = $conn->insert_id;
                            file_put_contents($logFile, "✅ Created payment #$new_payment_id from metadata - Invoice: $invoice_id, Patient: $invoice_patient_id, Appointment: " . ($invoice_appointment_id ?? 'NULL') . ", Amount: $invoice_amount\n", FILE_APPEND);
                            
                            // Add to payments array for processing (with payment_status set)
                            $payments[] = [
                                'payment_id' => $new_payment_id,
                                'invoice_id' => $invoice_id,
                                'appointment_id' => $invoice_appointment_id,
                                'patient_id' => $invoice_patient_id,
                                'payment_type' => $payment_type_meta,
                                'payment_status' => 'Completed',
                                'amount' => $invoice_amount
                            ];
                        } else {
                            file_put_contents($logFile, "❌ Failed to create payment for invoice #$invoice_id: " . $insert_stmt->error . "\n", FILE_APPEND);
                        }
                        $insert_stmt->close();
                    }
                    $invoice_stmt->close();
                }
                }
            } else {
                // If no metadata, try to find payments by searching for recent pending payments with similar amounts
                // This is a fallback in case transaction_id doesn't match
                file_put_contents($logFile, "⚠️ No metadata available, trying alternative search methods...\n", FILE_APPEND);
                
                // Get amount from webhook
                $webhook_amount = isset($eventData['amount']) ? floatval($eventData['amount']) / 100 : null;
                
                if ($webhook_amount) {
                    // Search for pending payments created in the last 24 hours with matching amount
                    $search_stmt = $conn->prepare("
                        SELECT payment_id, appointment_id, invoice_id, patient_id, amount, transaction_id, payment_type, payment_status
                        FROM tbl_payments 
                        WHERE payment_status = 'Pending'
                        AND payment_method = 'E-Wallet'
                        AND ABS(amount - ?) < 0.01
                        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                        ORDER BY created_at DESC
                        LIMIT 10
                    ");
                    $search_stmt->bind_param("d", $webhook_amount);
                    $search_stmt->execute();
                    $search_result = $search_stmt->get_result();
                    $found_payments = [];
                    while ($row = $search_result->fetch_assoc()) {
                        $found_payments[] = $row;
                    }
                    $search_stmt->close();
                    
                    if (!empty($found_payments)) {
                        file_put_contents($logFile, "⚠️ Found " . count($found_payments) . " potential payment(s) by amount matching (fallback method)\n", FILE_APPEND);
                        // Use the most recent one that matches the amount
                        $payments = [$found_payments[0]];
                        // Update transaction_id to match
                        if (!empty($payments[0]['payment_id'])) {
                            $update_tx_stmt = $conn->prepare("
                                UPDATE tbl_payments 
                                SET transaction_id = ?
                                WHERE payment_id = ?
                            ");
                            $update_tx_stmt->bind_param("si", $transaction_id, $payments[0]['payment_id']);
                            $update_tx_stmt->execute();
                            $update_tx_stmt->close();
                            $payments[0]['transaction_id'] = $transaction_id;
                            file_put_contents($logFile, "✅ Updated payment transaction_id to match webhook\n", FILE_APPEND);
                        }
                    }
                }
            }
        }
        
        if (!empty($payments)) {
            $processed_invoices = [];
            $processed_appointments = [];
            
            file_put_contents($logFile, "🔄 Starting to process " . count($payments) . " payment(s)...\n", FILE_APPEND);
            
            // Update ALL payments to Completed (only if they're not already Completed)
            foreach ($payments as $payment) {
                $current_status = $payment['payment_status'] ?? 'Pending';
                $payment_id = $payment['payment_id'] ?? null;
                
                if (!$payment_id) {
                    file_put_contents($logFile, "❌ Skipping payment - no payment_id\n", FILE_APPEND);
                    continue;
                }
                
                file_put_contents($logFile, "✅ Processing payment #$payment_id - Invoice: " . ($payment['invoice_id'] ?? 'NULL') . ", Patient: " . ($payment['patient_id'] ?? 'NULL') . ", Appointment: " . ($payment['appointment_id'] ?? 'NULL') . ", Current Status: $current_status\n", FILE_APPEND);
                
                // Only update if not already Completed
                if ($current_status !== 'Completed') {
                    $update_stmt = $conn->prepare("
                        UPDATE tbl_payments 
                        SET payment_status = 'Completed', payment_date = NOW()
                        WHERE payment_id = ?
                    ");
                    $update_stmt->bind_param("i", $payment_id);
                    $update_result = $update_stmt->execute();
                    $affected = $conn->affected_rows;
                    $update_stmt->close();
                    
                    if ($update_result && $affected > 0) {
                        file_put_contents($logFile, "✅ Payment #$payment_id updated to Completed (affected rows: $affected)\n", FILE_APPEND);
                    } else {
                        file_put_contents($logFile, "⚠️ Payment #$payment_id update failed or no rows affected. Error: " . ($conn->error ?? 'Unknown') . "\n", FILE_APPEND);
                    }
                } else {
                    file_put_contents($logFile, "ℹ️ Payment #$payment_id already marked as Completed, skipping update\n", FILE_APPEND);
                }
                
                // Track invoices and appointments for batch updates
                if ($payment['payment_type'] === 'Downpayment' && $payment['appointment_id']) {
                    $processed_appointments[] = $payment['appointment_id'];
                }
                
                if ($payment['payment_type'] === 'Invoice Payment' && $payment['invoice_id']) {
                    $processed_invoices[] = $payment['invoice_id'];
                }
            }
            
            // Update appointment statuses if it's a downpayment
            if (!empty($processed_appointments)) {
                foreach ($processed_appointments as $appointment_id) {
                    $update_apt = $conn->prepare("
                        UPDATE tbl_appointments 
                        SET status = 'Scheduled' 
                        WHERE appointment_id = ?
                    ");
                    $update_apt->bind_param("i", $appointment_id);
                    $update_apt->execute();
                    $update_apt->close();
                    file_put_contents($logFile, "✅ Appointment #$appointment_id updated to Scheduled (Downpayment)\n", FILE_APPEND);
                }
            }
            
            // Update invoice statuses if all payments are completed
            if (!empty($processed_invoices)) {
                foreach ($processed_invoices as $invoice_id) {
                    // Get invoice total amount
                    $invoice_total_stmt = $conn->prepare("
                        SELECT total_amount, payment_status
                        FROM tbl_invoices
                        WHERE invoice_id = ?
                        LIMIT 1
                    ");
                    $invoice_total_stmt->bind_param("i", $invoice_id);
                    $invoice_total_stmt->execute();
                    $invoice_total_result = $invoice_total_stmt->get_result();
                    $invoice_data = $invoice_total_result->fetch_assoc();
                    $invoice_total_stmt->close();
                    
                    if ($invoice_data) {
                        $invoice_total = floatval($invoice_data['total_amount']);
                        
                        // Calculate total amount paid for this invoice
                        $paid_total_stmt = $conn->prepare("
                            SELECT COALESCE(SUM(amount), 0) as total_paid
                            FROM tbl_payments
                            WHERE invoice_id = ?
                            AND payment_status = 'Completed'
                        ");
                        $paid_total_stmt->bind_param("i", $invoice_id);
                        $paid_total_stmt->execute();
                        $paid_total_result = $paid_total_stmt->get_result();
                        $paid_total_row = $paid_total_result->fetch_assoc();
                        $paid_total_stmt->close();
                        
                        $total_paid = floatval($paid_total_row['total_paid'] ?? 0);
                        
                        // Check if all payments are completed (no pending) AND total paid >= invoice amount
                        $check_invoice_stmt = $conn->prepare("
                            SELECT COUNT(*) as pending_count
                            FROM tbl_payments
                            WHERE invoice_id = ?
                            AND payment_status = 'Pending'
                        ");
                        $check_invoice_stmt->bind_param("i", $invoice_id);
                        $check_invoice_stmt->execute();
                        $check_invoice_result = $check_invoice_stmt->get_result();
                        $check_invoice_row = $check_invoice_result->fetch_assoc();
                        $check_invoice_stmt->close();
                        
                        $pending_count = intval($check_invoice_row['pending_count'] ?? 0);
                        
                        // Use a small tolerance for floating point comparison (0.01)
                        $tolerance = 0.01;
                        $is_fully_paid = ($total_paid >= ($invoice_total - $tolerance));
                        
                        file_put_contents($logFile, "Invoice #$invoice_id: Total=$invoice_total, Paid=$total_paid, Pending=$pending_count, IsFullyPaid=" . ($is_fully_paid ? 'YES' : 'NO') . "\n", FILE_APPEND);
                        
                        // Mark as Paid if no pending payments AND total paid >= invoice amount (with tolerance)
                        if ($pending_count == 0 && $is_fully_paid) {
                            $update_invoice_stmt = $conn->prepare("
                                UPDATE tbl_invoices 
                                SET payment_status = 'Paid'
                                WHERE invoice_id = ?
                            ");
                            $update_invoice_stmt->bind_param("i", $invoice_id);
                            $update_invoice_stmt->execute();
                            $affected_rows = $conn->affected_rows;
                            $update_invoice_stmt->close();
                            
                            if ($affected_rows > 0) {
                                file_put_contents($logFile, "✅ Invoice #$invoice_id marked as Paid (Total: $invoice_total, Paid: $total_paid, Affected rows: $affected_rows)\n", FILE_APPEND);
                            } else {
                                file_put_contents($logFile, "⚠️ Invoice #$invoice_id update query executed but no rows affected (may already be Paid)\n", FILE_APPEND);
                            }
                        } elseif ($pending_count == 0 && $total_paid > 0 && !$is_fully_paid) {
                            // Partial payment
                            $update_invoice_stmt = $conn->prepare("
                                UPDATE tbl_invoices 
                                SET payment_status = 'Partial'
                                WHERE invoice_id = ?
                                AND payment_status != 'Paid'
                            ");
                            $update_invoice_stmt->bind_param("i", $invoice_id);
                            $update_invoice_stmt->execute();
                            $affected_rows = $conn->affected_rows;
                            $update_invoice_stmt->close();
                            
                            if ($affected_rows > 0) {
                                file_put_contents($logFile, "✅ Invoice #$invoice_id marked as Partial (Total: $invoice_total, Paid: $total_paid, Affected rows: $affected_rows)\n", FILE_APPEND);
                            } else {
                                file_put_contents($logFile, "ℹ️ Invoice #$invoice_id already has correct status (Partial or Paid)\n", FILE_APPEND);
                            }
                        } else {
                            file_put_contents($logFile, "ℹ️ Invoice #$invoice_id status unchanged (Pending: $pending_count, Total: $invoice_total, Paid: $total_paid)\n", FILE_APPEND);
                        }
                    }
                }
            }
            
            http_response_code(200);
            echo json_encode(['success' => true, 'payments_processed' => count($payments)]);
        } else {
            file_put_contents($logFile, "❌ No payments found or created for transaction_id: $transaction_id\n", FILE_APPEND);
            http_response_code(404);
            echo json_encode(['error' => 'Payment not found and could not be created from metadata']);
        }
        $conn->close();
    } else {
        file_put_contents($logFile, "❌ ERROR: No link ID found in webhook data!\n", FILE_APPEND);
        file_put_contents($logFile, "Full webhook data structure: " . json_encode($data, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
        http_response_code(400);
        echo json_encode(['error' => 'Link ID not found in webhook data']);
    }
} else {
    file_put_contents($logFile, "⚠️ Event type not handled: $eventType\n", FILE_APPEND);
    file_put_contents($logFile, "Full event data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n", FILE_APPEND);
    
    // Still return 200 to acknowledge receipt (PayMongo will retry if we return error codes)
    http_response_code(200);
    echo json_encode(['message' => 'Event received but not processed', 'event_type' => $eventType]);
    
    file_put_contents($logFile, "⚠️ Webhook acknowledged but event type '$eventType' is not a recognized payment event\n", FILE_APPEND);
}
