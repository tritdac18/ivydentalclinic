<?php
/**
 * Process Downpayment via PayMongo
 * Creates payment intent and redirects to PayMongo payment page
 */

require_once 'config/db.php';
require_once 'config/security.php';
require_once 'services/payment_service.php';

// Set security headers and check login
requireLogin();

// Allow patients and staff/admin (for walk-in appointments)
$user_type = $_SESSION['user_type'] ?? 'patient';
$user_role = $_SESSION['user_role'] ?? '';
$is_staff = ($user_type === 'staff' || $user_role === 'Admin' || $user_role === 'Receptionist' || $user_role === 'Dentist');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = intval($_POST['appointment_id'] ?? 0);
    $amount = floatval($_POST['amount'] ?? 0);
    
    // Validation
    if ($appointment_id <= 0 || $amount <= 0) {
        $errors[] = "Invalid appointment or amount.";
    } else {
        try {
            $conn = getDBConnection();
            
            // Verify appointment belongs to patient
            $verify_stmt = $conn->prepare("
                SELECT a.appointment_id, a.patient_id, a.price, s.service_name 
                FROM tbl_appointments a
                LEFT JOIN tbl_service_catalog s ON a.service_id = s.service_id
                WHERE a.appointment_id = ? LIMIT 1
            ");
            $verify_stmt->bind_param("i", $appointment_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            
            if ($verify_result->num_rows === 1) {
                $appointment = $verify_result->fetch_assoc();
                
                // Verify patient owns this appointment OR user is staff/admin (for walk-in appointments)
                $patient_owns_appointment = ($appointment['patient_id'] == $_SESSION['user_id']);
                if ($patient_owns_appointment || $is_staff) {
                    // Create payment intent via PayMongo
                    $paymentService = new PaymentService();
                    
                    $description = "Downpayment for " . ($appointment['service_name'] ?? 'Appointment') . " - Appointment #" . $appointment_id;
                    $metadata = [
                        'appointment_id' => $appointment_id,
                        'patient_id' => $appointment['patient_id'],
                        'payment_type' => 'downpayment',
                        'amount' => $amount
                    ];
                    
                    // Create payment link (simpler redirect flow)
                    error_log('PayMongo: Creating payment link for appointment #' . $appointment_id . ', amount: ' . $amount);
                    $paymentLink = $paymentService->createPaymentLink($amount, $description, $metadata);
                    error_log('PayMongo: Payment link response: ' . json_encode($paymentLink));
                    
                    if ($paymentLink['success']) {
                        // Extract link_id from PayMongo response (format: link_xxxxx)
                        $link_id = $paymentLink['link_id'];
                        // Remove 'link_' prefix if present for cleaner storage
                        $link_id_clean = str_replace('link_', '', $link_id);
                        
                        // Store payment link ID in database for tracking
                        // Schema updated: payment_type, appointment_id, patient_id, transaction_id
                        $insert_stmt = $conn->prepare("
                            INSERT INTO tbl_payments 
                            (payment_type, appointment_id, patient_id, amount, payment_method, transaction_id, payment_status, payment_date)
                            VALUES ('Downpayment', ?, ?, ?, 'E-Wallet', ?, 'Pending', NOW())
                        ");
                        // Store both formats for better matching
                        $transaction_id = 'PM-LINK-' . $link_id_clean;
                        $insert_stmt->bind_param("iids", $appointment_id, $appointment['patient_id'], $amount, $transaction_id);
                        
                        if (!$insert_stmt->execute()) {
                            error_log('PayMongo: INSERT FAILED - ' . $insert_stmt->error);
                            throw new Exception('Failed to create payment record: ' . $insert_stmt->error);
                        }
                        
                        $payment_id = $conn->insert_id;
                        $insert_stmt->close();
                        
                        if ($payment_id <= 0) {
                            error_log('PayMongo: INSERT succeeded but payment_id is 0');
                            throw new Exception('Failed to get payment ID');
                        }
                        
                        error_log('PayMongo: Created payment record. Payment ID: ' . $payment_id . ', Link ID: ' . $link_id . ', Transaction ID: ' . $transaction_id);
                        
                        $verify_stmt->close();
                        $conn->close();
                        
                        // Store payment link info in session for return page processing
                        $_SESSION['pending_payment_link'] = [
                            'link_id' => $link_id,
                            'link_id_clean' => $link_id_clean,
                            'appointment_id' => $appointment_id,
                            'amount' => $amount,
                            'payment_type' => 'downpayment',
                            'payment_id' => $payment_id
                        ];
                        
                        // Redirect to PayMongo checkout page
                        header("Location: " . $paymentLink['checkout_url']);
                        exit();
                    } else {
                        $error_msg = $paymentLink['error'] ?? 'Unknown error';
                        $errors[] = "Failed to create payment link: " . $error_msg;
                        error_log('PayMongo: Failed to create payment link - ' . $error_msg);
                    }
                } else {
                    $errors[] = "You are not authorized to process payment for this appointment.";
                }
            } else {
                $errors[] = "Appointment not found.";
            }
            
            $verify_stmt->close();
            $conn->close();
        } catch (Exception $e) {
            $errors[] = "Error processing payment: " . $e->getMessage();
            error_log("PayMongo Downpayment Error: " . $e->getMessage());
        }
    }
}

// If there are errors, redirect back
if (!empty($errors)) {
    $_SESSION['booking_errors'] = $errors;
    header("Location: book_appointment.php?payment_required=1");
    exit();
}

