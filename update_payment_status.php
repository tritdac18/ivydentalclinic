<?php
/**
 * Update Payment Status
 * Manually update a payment status (for testing/debugging)
 */

header('Content-Type: application/json');

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/services/payment_service.php';

$response = ['success' => false, 'error' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['payment_id'])) {
    $payment_id = intval($_GET['payment_id'] ?? $_POST['payment_id'] ?? 0);
    $status = $_GET['status'] ?? $_POST['status'] ?? 'Completed';
    
    if ($payment_id > 0) {
        try {
            $conn = getDBConnection();
            
            // Get payment details
            $stmt = $conn->prepare("
                SELECT payment_id, payment_type, invoice_id, appointment_id, transaction_id, payment_status
                FROM tbl_payments
                WHERE payment_id = ?
            ");
            $stmt->bind_param("i", $payment_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $payment = $result->fetch_assoc();
            $stmt->close();
            
            if (!$payment) {
                $response['error'] = 'Payment not found';
                echo json_encode($response);
                exit;
            }
            
            // Update payment status
            $update_stmt = $conn->prepare("
                UPDATE tbl_payments 
                SET payment_status = ?, payment_date = NOW()
                WHERE payment_id = ?
            ");
            $update_stmt->bind_param("si", $status, $payment_id);
            $update_result = $update_stmt->execute();
            $affected_rows = $conn->affected_rows;
            $update_stmt->close();
            
            if ($update_result && $affected_rows > 0) {
                // Update related records based on payment type
                if ($payment['payment_type'] === 'Downpayment' && $payment['appointment_id']) {
                    $update_apt = $conn->prepare("
                        UPDATE tbl_appointments 
                        SET status = 'Scheduled' 
                        WHERE appointment_id = ?
                    ");
                    $update_apt->bind_param("i", $payment['appointment_id']);
                    $update_apt->execute();
                    $update_apt->close();
                } elseif ($payment['payment_type'] === 'Invoice Payment' && $payment['invoice_id']) {
                    // Calculate total paid
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
                }
                
                $response['success'] = true;
                $response['message'] = 'Payment updated successfully';
                $response['affected_rows'] = $affected_rows;
            } else {
                $response['error'] = 'Update failed: ' . ($conn->error ?? 'Unknown error');
            }
            
            $conn->close();
        } catch (Exception $e) {
            $response['error'] = $e->getMessage();
        }
    } else {
        $response['error'] = 'Invalid payment ID';
    }
} else {
    $response['error'] = 'Invalid request method';
}

echo json_encode($response);
?>

