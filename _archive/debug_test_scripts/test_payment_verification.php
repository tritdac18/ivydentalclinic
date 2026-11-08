<?php
/**
 * Test Payment Verification
 * This script helps debug why payments aren't being verified
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/services/payment_service.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Verification Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #007bff; color: white; }
    </style>
</head>
<body>
    <h1>🔍 Payment Verification Test</h1>
    
    <?php
    $conn = getDBConnection();
    $paymentService = new PaymentService();
    
    // 1. Find all pending PayMongo payments
    echo '<div class="section">';
    echo '<h2>1. All Pending PayMongo Payments</h2>';
    
    $stmt = $conn->prepare("
        SELECT 
            payment_id,
            payment_type,
            invoice_id,
            appointment_id,
            patient_id,
            amount,
            transaction_id,
            payment_status,
            created_at
        FROM tbl_payments
        WHERE payment_method = 'E-Wallet'
        AND payment_status = 'Pending'
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    $stmt->close();
    
    if (empty($payments)) {
        echo '<p class="info">ℹ️ No pending E-Wallet payments found</p>';
    } else {
        echo '<p class="warning">⚠️ Found ' . count($payments) . ' pending payment(s)</p>';
        echo '<table>';
        echo '<tr><th>Payment ID</th><th>Type</th><th>Invoice ID</th><th>Amount</th><th>Transaction ID</th><th>Created</th><th>Test Verification</th></tr>';
        
        foreach ($payments as $payment) {
            $transaction_id = $payment['transaction_id'];
            $is_pm_link = (strpos($transaction_id, 'PM-LINK-') === 0);
            
            echo '<tr>';
            echo '<td>' . htmlspecialchars($payment['payment_id']) . '</td>';
            echo '<td>' . htmlspecialchars($payment['payment_type']) . '</td>';
            echo '<td>' . ($payment['invoice_id'] ? htmlspecialchars($payment['invoice_id']) : 'N/A') . '</td>';
            echo '<td>₱' . number_format($payment['amount'], 2) . '</td>';
            echo '<td>' . htmlspecialchars($transaction_id) . '</td>';
            echo '<td>' . htmlspecialchars($payment['created_at']) . '</td>';
            echo '<td>';
            
            if ($is_pm_link) {
                // Extract link ID
                $linkId = str_replace('PM-LINK-', '', $transaction_id);
                $linkId = str_replace('link_', '', $linkId);
                
                echo '<strong>Link ID:</strong> ' . htmlspecialchars($linkId) . '<br>';
                
                // Test verification
                try {
                    $linkStatus = $paymentService->getPaymentLinkStatus($linkId);
                    
                    if ($linkStatus['success']) {
                        echo '<span class="success">✅ API Call Success</span><br>';
                        echo '<strong>Link Status:</strong> ' . htmlspecialchars($linkStatus['status']) . '<br>';
                        echo '<strong>Archived:</strong> ' . ($linkStatus['archived'] ? 'Yes' : 'No') . '<br>';
                        if (isset($linkStatus['has_payments'])) {
                            echo '<strong>Has Payments:</strong> ' . ($linkStatus['has_payments'] ? 'Yes' : 'No') . '<br>';
                            echo '<strong>Payment Paid:</strong> ' . (isset($linkStatus['payment_paid']) && $linkStatus['payment_paid'] ? '<span class="success">YES</span>' : '<span class="error">NO</span>') . '<br>';
                        }
                        echo '<strong>Is Paid:</strong> ' . ($linkStatus['is_paid'] ? '<span class="success">YES</span>' : '<span class="error">NO</span>') . '<br>';
                        echo '<strong>Amount:</strong> ₱' . number_format($linkStatus['amount'], 2) . '<br>';
                        
                        if ($linkStatus['is_paid']) {
                            echo '<br><span class="success">✅ PAYMENT IS PAID - Should be updated!</span>';
                            
                            // Check current database status
                            $check_stmt = $conn->prepare("SELECT payment_status FROM tbl_payments WHERE payment_id = ?");
                            $check_stmt->bind_param("i", $payment['payment_id']);
                            $check_stmt->execute();
                            $check_result = $check_stmt->get_result();
                            $db_payment = $check_result->fetch_assoc();
                            $check_stmt->close();
                            
                            if ($db_payment) {
                                $db_status = $db_payment['payment_status'];
                                if ($db_status === 'Completed') {
                                    echo '<br><span class="info">ℹ️ Database already shows: Completed</span>';
                                } else {
                                    echo '<br><span class="error">❌ Database shows: ' . htmlspecialchars($db_status) . ' (should be Completed!)</span>';
                                    echo '<br><button onclick="updatePayment(' . $payment['payment_id'] . ')" style="padding: 5px 10px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; margin-top: 5px;">Update Now</button>';
                                }
                            }
                        } else {
                            echo '<br><span class="warning">⚠️ Payment not yet paid on PayMongo</span>';
                            if (isset($linkStatus['has_payments']) && $linkStatus['has_payments'] && !isset($linkStatus['payment_paid'])) {
                                echo '<br><span class="info">ℹ️ Link has payments but none are marked as paid</span>';
                            }
                        }
                    } else {
                        echo '<span class="error">❌ API Call Failed</span>';
                        if (isset($linkStatus['error'])) {
                            echo '<br><strong>Error:</strong> ' . htmlspecialchars($linkStatus['error']);
                        }
                    }
                } catch (Exception $e) {
                    echo '<span class="error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</span>';
                }
            } else {
                echo '<span class="warning">⚠️ Not a PM-LINK transaction</span>';
            }
            
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    echo '</div>';
    
    // 2. Test specific transaction ID
    echo '<div class="section">';
    echo '<h2>2. Test Specific Transaction ID</h2>';
    echo '<form method="GET" action="">';
    echo '<input type="text" name="test_tx" placeholder="PM-LINK-xxxxx" style="padding: 8px; width: 300px;">';
    echo '<button type="submit" style="padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Test</button>';
    echo '</form>';
    
    if (isset($_GET['test_tx']) && !empty($_GET['test_tx'])) {
        $test_tx = $_GET['test_tx'];
        echo '<h3>Testing: ' . htmlspecialchars($test_tx) . '</h3>';
        
        // Find payment
        $stmt = $conn->prepare("
            SELECT * FROM tbl_payments WHERE transaction_id = ?
        ");
        $stmt->bind_param("s", $test_tx);
        $stmt->execute();
        $result = $stmt->get_result();
        $payment = $result->fetch_assoc();
        $stmt->close();
        
        if ($payment) {
            echo '<p class="success">✅ Payment found in database</p>';
            echo '<pre>' . print_r($payment, true) . '</pre>';
            
            // Extract and test link ID
            $linkId = str_replace('PM-LINK-', '', $test_tx);
            $linkId = str_replace('link_', '', $linkId);
            
            echo '<p><strong>Extracted Link ID:</strong> ' . htmlspecialchars($linkId) . '</p>';
            
            try {
                $linkStatus = $paymentService->getPaymentLinkStatus($linkId);
                echo '<h4>PayMongo API Response:</h4>';
                echo '<pre>' . print_r($linkStatus, true) . '</pre>';
                
                if ($linkStatus['success'] && $linkStatus['is_paid']) {
                    echo '<p class="success">✅ Payment is PAID on PayMongo - should be updated!</p>';
                } else {
                    echo '<p class="warning">⚠️ Payment not paid yet on PayMongo</p>';
                }
            } catch (Exception $e) {
                echo '<p class="error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
        } else {
            echo '<p class="error">❌ Payment not found in database</p>';
        }
    }
    echo '</div>';
    
    // 3. Manual verification trigger
    echo '<div class="section">';
    echo '<h2>3. Manual Verification</h2>';
    echo '<p>Click the button below to manually trigger verification for all pending payments:</p>';
    echo '<form method="POST" action="">';
    echo '<input type="hidden" name="action" value="verify_all">';
    echo '<button type="submit" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">🔍 Verify All Pending Payments</button>';
    echo '</form>';
    
    if (isset($_POST['action']) && $_POST['action'] === 'verify_all') {
        echo '<h3>Verification Results:</h3>';
        
        $stmt = $conn->prepare("
            SELECT 
                payment_id,
                payment_type,
                invoice_id,
                appointment_id,
                transaction_id,
                amount
            FROM tbl_payments
            WHERE payment_method = 'E-Wallet'
            AND transaction_id LIKE 'PM-LINK-%'
            AND payment_status = 'Pending'
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $pending = [];
        while ($row = $result->fetch_assoc()) {
            $pending[] = $row;
        }
        $stmt->close();
        
        $verified = 0;
        $failed = 0;
        
        foreach ($pending as $payment) {
            $linkId = str_replace('PM-LINK-', '', $payment['transaction_id']);
            $linkId = str_replace('link_', '', $linkId);
            
            echo '<p><strong>Payment #' . $payment['payment_id'] . ':</strong> Link ID = ' . htmlspecialchars($linkId) . '</p>';
            
            try {
                $linkStatus = $paymentService->getPaymentLinkStatus($linkId);
                
                if ($linkStatus['success'] && $linkStatus['is_paid']) {
                    // Update payment
                    $update_stmt = $conn->prepare("
                        UPDATE tbl_payments 
                        SET payment_status = 'Completed', payment_date = NOW()
                        WHERE payment_id = ?
                    ");
                    $update_stmt->bind_param("i", $payment['payment_id']);
                    $update_result = $update_stmt->execute();
                    $affected_rows = $conn->affected_rows;
                    $update_stmt->close();
                    
                    if ($update_result && $affected_rows > 0) {
                        // Update invoice/appointment based on payment type
                        if ($payment['payment_type'] === 'Invoice Payment' && $payment['invoice_id']) {
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
                        
                        echo '<p class="success">✅ Payment #' . $payment['payment_id'] . ' verified and updated! (affected rows: ' . $affected_rows . ')</p>';
                        $verified++;
                    } else {
                        echo '<p class="error">❌ Payment #' . $payment['payment_id'] . ' verification succeeded but database update failed! Error: ' . ($conn->error ?? 'Unknown') . '</p>';
                        $failed++;
                    }
                } else {
                    echo '<p class="warning">⚠️ Payment #' . $payment['payment_id'] . ' not paid yet (Status: ' . ($linkStatus['status'] ?? 'unknown') . ', Is Paid: ' . ($linkStatus['is_paid'] ? 'yes' : 'no') . ')</p>';
                    $failed++;
                }
            } catch (Exception $e) {
                echo '<p class="error">❌ Error verifying payment #' . $payment['payment_id'] . ': ' . htmlspecialchars($e->getMessage()) . '</p>';
                $failed++;
            }
        }
        
        echo '<hr>';
        echo '<p><strong>Summary:</strong> Verified: ' . $verified . ', Failed/Not Paid: ' . $failed . '</p>';
        
        if ($verified > 0) {
            echo '<p class="success">✅ ' . $verified . ' payment(s) were verified and updated!</p>';
            echo '<p><a href="?refresh=1">Refresh page to see updated status</a></p>';
        }
    }
    echo '</div>';
    
    $conn->close();
    ?>
    
    <script>
    function updatePayment(paymentId) {
        if (!confirm('Update payment #' + paymentId + ' to Completed status and update related invoice/appointment?')) {
            return;
        }
        
        // Show loading
        const btn = event.target;
        const originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Updating...';
        
        fetch('update_payment_status.php?payment_id=' + paymentId + '&status=Completed', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Payment updated successfully! Reloading page...');
                location.reload();
            } else {
                alert('❌ Error: ' + (data.error || 'Unknown error'));
                btn.disabled = false;
                btn.textContent = originalText;
            }
        })
        .catch(error => {
            alert('❌ Error: ' + error);
            btn.disabled = false;
            btn.textContent = originalText;
        });
    }
    </script>
</body>
</html>

