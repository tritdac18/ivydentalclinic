<?php
/**
 * Resend Verification Code
 */

session_start();
require_once 'config/db.php';
require_once 'services/email_service.php';

$email = $_GET['email'] ?? $_SESSION['pending_email'] ?? '';
$message = '';
$error = '';

if (empty($email)) {
    header("Location: register.php");
    exit();
}

try {
    $emailService = new EmailService();
    $code = $emailService->generateVerificationCode();
    $emailService->saveVerificationCode($email, $code);
    
    // If this is a pending registration, update the code in pending_registrations table
    if (isset($_SESSION['pending_registration'])) {
        $conn = getDBConnection();
        
        // Check if new structure (has patient_id) or old structure
        $column_check = $conn->query("SHOW COLUMNS FROM tbl_pending_registrations LIKE 'patient_id'");
        if ($column_check && $column_check->num_rows > 0) {
            // New structure: find patient_id from email
            $patient_stmt = $conn->prepare("SELECT patient_id FROM tbl_patients WHERE email = ? AND status = 'pending_verification' LIMIT 1");
            $patient_stmt->bind_param("s", $email);
            $patient_stmt->execute();
            $patient_result = $patient_stmt->get_result();
            if ($patient_result->num_rows > 0) {
                $patient = $patient_result->fetch_assoc();
                $update_stmt = $conn->prepare("UPDATE tbl_pending_registrations SET verification_code = ? WHERE patient_id = ? AND verified_at IS NULL");
                $update_stmt->bind_param("si", $code, $patient['patient_id']);
            } else {
                $update_stmt = null;
            }
            $patient_stmt->close();
        } else {
            // Old structure: use email
            $update_stmt = $conn->prepare("UPDATE tbl_pending_registrations SET verification_code = ? WHERE email = ? AND verified_at IS NULL");
            $update_stmt->bind_param("ss", $code, $email);
        }
        
        if ($update_stmt) {
            $update_stmt->execute();
            $update_stmt->close();
        }
        $conn->close();
    }
    
    if ($emailService->sendVerificationEmail($email, $code)) {
        $message = "Verification code has been resent to your email.";
    } else {
        $error = "Failed to send verification email. Please try again later.";
    }
} catch (Exception $e) {
    $error = "An error occurred. Please try again later.";
    error_log('Resend verification error: ' . $e->getMessage());
}

$redirect_url = "verify_email.php?email=" . urlencode($email);
if (isset($_GET['registration']) || isset($_SESSION['pending_registration'])) {
    $redirect_url .= "&registration=1";
}
if (!empty($message)) {
    $redirect_url .= "&message=" . urlencode($message);
}
if (!empty($error)) {
    $redirect_url .= "&error=" . urlencode($error);
}

header("Location: " . $redirect_url);
exit();

