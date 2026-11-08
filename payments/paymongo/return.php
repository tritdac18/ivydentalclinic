<?php
/**
 * Payment Verification Page
 * Automatically verifies payment when patient returns from PayMongo
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/services/payment_service.php';

requireLogin();

$patient_id = $_SESSION['user_id'] ?? 0;
$verified = false;
$message = '';

if ($patient_id > 0) {
    try {
        $conn = getDBConnection();
        $paymentService = new PaymentService();
        
        // Find pending payment for this patient
        $stmt = $conn->prepare("
            SELECT payment_id, appointment_id, patient_id, amount, transaction_id
            FROM tbl_payments 
            WHERE patient_id = ?
            AND transaction_id LIKE 'PM-LINK-%'
            AND payment_status = 'Pending'
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $payment = $result->fetch_assoc();
            
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
                
                // Update appointment
                $update_apt = $conn->prepare("
                    UPDATE tbl_appointments 
                    SET status = 'Scheduled' 
                    WHERE appointment_id = ?
                ");
                $update_apt->bind_param("i", $payment['appointment_id']);
                $update_apt->execute();
                $update_apt->close();
                
                $verified = true;
                $message = "Payment verified successfully! Your appointment is now scheduled.";
                $_SESSION['booking_success'] = $message;
            } else {
                $message = "Payment verification pending. Please wait a moment and refresh.";
            }
        } else {
            $message = "No pending payment found.";
        }
        
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        $message = "Error verifying payment: " . $e->getMessage();
        error_log("Payment verification error: " . $e->getMessage());
    }
}

// Redirect to appointments page after 3 seconds
header("refresh:3;url=appointments.php");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verifying Payment...</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
        }
        .success { color: #28a745; }
        .pending { color: #ffc107; }
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #136dec;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($verified): ?>
            <div class="success">
                <h2>✅ Payment Verified!</h2>
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php else: ?>
            <div class="pending">
                <div class="spinner"></div>
                <h2>Verifying Payment...</h2>
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>
        
        <p style="margin-top: 20px; color: #666;">
            Redirecting to your appointments...
        </p>
    </div>
</body>
</html>
