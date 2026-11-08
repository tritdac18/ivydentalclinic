<?php
require_once 'config/db.php';
require_once 'config/security.php';

// Set security headers and check login
requireLogin();

// Security: Verify user still exists in database and fetch fresh data
$conn = getDBConnection();
$user_info = null;
$user_type = $_SESSION['user_type'] ?? 'patient';
$user_role = $_SESSION['user_role'] ?? '';

// Allow Admin, Dentist, Receptionist, and Patients to access payments page (Manager should not access)
// Staff (Admin/Dentist/Receptionist) see all invoices, Patients see only their own
if ($user_type === 'staff' && ($user_role !== 'Admin' && $user_role !== 'Dentist' && $user_role !== 'Receptionist')) {
    // Staff but not Admin/Dentist/Receptionist - redirect to landing page
    header("Location: landing.php");
    exit();
}

try {
    if ($user_type === 'patient') {
        // Fetch patient data
        $stmt = $conn->prepare("SELECT patient_id, first_name, last_name, email FROM tbl_patients WHERE patient_id = ? LIMIT 1");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user_info = $result->fetch_assoc();
            $user_info['user_id'] = $user_info['patient_id'];
        } else {
            session_destroy();
            header("Location: login.php");
            exit();
        }
        $stmt->close();
    } else {
        // Fetch staff data from database using prepared statement
        $stmt = $conn->prepare("SELECT staff_id, first_name, last_name, email, role FROM tbl_staff WHERE staff_id = ? LIMIT 1");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user_info = $result->fetch_assoc();
            $user_info['user_id'] = $user_info['staff_id'];
        } else {
            // User not found in database - session invalid, logout
            session_destroy();
            header("Location: login.php");
            exit();
        }
        $stmt->close();
    }
} catch (Exception $e) {
    // Database error - logout for security
    session_destroy();
    header("Location: login.php");
    exit();
}

// If user not found, redirect to login
if (!$user_info) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Set user variables from fresh database data
$user_name = $user_info['first_name'] . ' ' . $user_info['last_name'];
$user_email = $user_info['email'] ?? '';

// For staff
if ($user_type === 'staff') {
    $admin_name = $user_name;
    $admin_email = $user_email;
    $admin_role = $user_info['role'] ?? 'Manager';
    $staff_id = $user_info['staff_id'];
} else {
    // For patients
    $patient_id = $user_info['patient_id'];
    $staff_id = null; // Not applicable for patients
    $admin_name = $user_name; // Set for header display
    $admin_role = 'Patient'; // Set for header display
}

// Handle payment creation
$action_message = '';

// Check for success message from redirect
if (isset($_SESSION['payment_success_message'])) {
    $action_message = $_SESSION['payment_success_message'];
    unset($_SESSION['payment_success_message']);
}

// Check for error message from redirect
if (isset($_SESSION['payment_error_message'])) {
    $action_message = $_SESSION['payment_error_message'];
    unset($_SESSION['payment_error_message']);
}

// Handle discount validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'validate_discount') {
    $patient_id = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : 0;
    $discount_type = isset($_POST['discount_type']) ? trim($_POST['discount_type']) : '';
    $discount_id_number = isset($_POST['discount_id_number']) ? trim($_POST['discount_id_number']) : '';
    
    if ($patient_id > 0 && !empty($discount_type) && !empty($discount_id_number)) {
        // Validate discount ID number length
        // PWD ID: typically 8-12 alphanumeric characters
        // Senior Citizen ID: typically 8-12 alphanumeric characters
        $discount_id_clean = preg_replace('/[^A-Za-z0-9]/', '', $discount_id_number); // Remove non-alphanumeric
        $id_length = strlen($discount_id_clean);
        
        if ($id_length < 8) {
            $action_message = "Error: Discount ID number is too short. Please enter a valid ID number (minimum 8 characters).";
        } elseif ($id_length > 12) {
            $action_message = "Error: Discount ID number is too long. Please enter a valid ID number (maximum 12 characters).";
        } else {
        try {
            // Map discount type
            $discount_type_mapped = ($discount_type === 'pwd') ? 'PWD' : (($discount_type === 'senior') ? 'Senior Citizen' : 'Other');
            
            // Check if patient already has this discount type registered (active)
            $check_existing = $conn->prepare("
                SELECT discount_id, id_number FROM tbl_discounts 
                WHERE patient_id = ? 
                AND discount_type = ? 
                AND is_active = 1
                LIMIT 1
            ");
            
            $check_existing->bind_param("is", $patient_id, $discount_type_mapped);
            $check_existing->execute();
            $existing_result = $check_existing->get_result();
            $existing_discount = $existing_result->fetch_assoc();
            
            if ($existing_result->num_rows > 0) {
                // Update existing discount ID
                $update_stmt = $conn->prepare("
                    UPDATE tbl_discounts 
                    SET id_number = ?, verified_by_staff_id = ?, verified_at = NOW()
                    WHERE discount_id = ?
                ");
                $discount_id = $existing_discount['discount_id'];
                $update_stmt->bind_param("sii", $discount_id_clean, $staff_id, $discount_id);
                
                if ($update_stmt->execute()) {
                    $_SESSION['payment_success_message'] = "Discount ID successfully updated for patient.";
                    $update_stmt->close();
                } else {
                    $action_message = "Error: Failed to update discount ID: " . $conn->error;
                }
            } else {
                // Insert new discount into tbl_discounts
                $insert_stmt = $conn->prepare("
                    INSERT INTO tbl_discounts 
                    (patient_id, discount_type, id_number, discount_percentage, is_verified, verified_by_staff_id, verified_at, is_active) 
                    VALUES (?, ?, ?, 20.00, 1, ?, NOW(), 1)
                ");
                $insert_stmt->bind_param("issi", $patient_id, $discount_type_mapped, $discount_id_clean, $staff_id);
                
                if ($insert_stmt->execute()) {
                    $_SESSION['payment_success_message'] = "Discount ID successfully registered for patient.";
                    $insert_stmt->close();
                } else {
                    $action_message = "Error: Failed to register discount ID: " . $conn->error;
                }
            }
            $check_existing->close();
        } catch (Exception $e) {
            $action_message = "Error: " . $e->getMessage();
            }
        }
    } else {
        $action_message = "Error: Please fill in all required fields.";
    }
    
    // Redirect to prevent form resubmission
    if (!empty($action_message)) {
        $_SESSION['payment_error_message'] = $action_message;
    }
    header("Location: payments.php");
    exit();
}

// Handle invoice payments (downpayments are now automatically processed, no verification needed)
// Now supports processing multiple invoices at once
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'process_payment') {
    // Get invoice IDs - can be single or multiple
    $invoice_ids = [];
    if (isset($_POST['invoice_ids']) && is_array($_POST['invoice_ids'])) {
        // Multiple invoices
        $invoice_ids = array_map('intval', $_POST['invoice_ids']);
        $invoice_ids = array_filter($invoice_ids, function($id) { return $id > 0; });
    } elseif (isset($_POST['invoice_id'])) {
        // Single invoice (backward compatibility)
        $invoice_id = intval($_POST['invoice_id']);
        if ($invoice_id > 0) {
            $invoice_ids = [$invoice_id];
        }
    }
    
    if (empty($invoice_ids)) {
        $action_message = "Error: No invoices selected for payment.";
    } else {
        $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'Cash';
        // Use current date/time in Asia/Manila timezone
        date_default_timezone_set('Asia/Manila');
        $transaction_date = isset($_POST['transaction_date']) ? $_POST['transaction_date'] : date('Y-m-d H:i:s');
        
        // If PayMongo is selected, redirect to PayMongo payment link creation
        if ($payment_method === 'PayMongo') {
            require_once __DIR__ . '/services/payment_service.php';
            require_once __DIR__ . '/config/paymongo_config.php';
            
            try {
                $conn = getDBConnection();
                $paymentService = new PaymentService();
                
                // Calculate total amount for all selected invoices
                $total_amount = 0;
                $invoice_details = [];
                
                foreach ($invoice_ids as $invoice_id) {
                    $invoice_stmt = $conn->prepare("SELECT invoice_id, patient_id, total_amount, appointment_id FROM tbl_invoices WHERE invoice_id = ? AND payment_status != 'Paid' LIMIT 1");
                    $invoice_stmt->bind_param("i", $invoice_id);
                    $invoice_stmt->execute();
                    $invoice_result = $invoice_stmt->get_result();
                    
                    if ($invoice_row = $invoice_result->fetch_assoc()) {
                        // Verify patient owns this invoice (for patients only)
                        if ($user_type === 'patient' && isset($patient_id) && $invoice_row['patient_id'] != $patient_id) {
                            $invoice_stmt->close();
                            throw new Exception("You are not authorized to process payment for this invoice.");
                        }
                        
                        $total_amount += floatval($invoice_row['total_amount']);
                        $invoice_details[] = $invoice_row;
                    }
                    $invoice_stmt->close();
                }
                
                if (empty($invoice_details)) {
                    throw new Exception("No valid invoices found");
                }
                
                $paymongo_patient_id = $invoice_details[0]['patient_id'];
                $description = count($invoice_details) > 1 
                    ? "Payment for " . count($invoice_details) . " invoices" 
                    : "Invoice Payment #" . $invoice_details[0]['invoice_id'];
                
                // Create PayMongo payment link
                $metadata = [
                    'invoice_ids' => implode(',', $invoice_ids),
                    'patient_id' => $paymongo_patient_id,
                    'payment_type' => 'Invoice Payment'
                ];
                
                $result = $paymentService->createPaymentLink($total_amount, $description, $metadata);
                
                if ($result['success']) {
                    $link_id = str_replace('link_', '', $result['link_id']);
                    $transaction_id = 'PM-LINK-' . $link_id;
                    
                    // Create pending payment record for each invoice
                    foreach ($invoice_details as $invoice) {
                        $invoice_id = $invoice['invoice_id'];
                        $amount = floatval($invoice['total_amount']);
                        $invoice_patient_id = $invoice['patient_id'];
                        $invoice_appointment_id = isset($invoice['appointment_id']) && $invoice['appointment_id'] > 0 ? $invoice['appointment_id'] : null;
                        
                        // Use current date/time in Asia/Manila timezone
                        date_default_timezone_set('Asia/Manila');
                        $created_at = date('Y-m-d H:i:s');
                        
                        // Ensure all required fields are set
                        if (empty($invoice_patient_id)) {
                            error_log("ERROR: Missing patient_id for invoice #{$invoice_id}");
                            continue;
                        }
                        
                        $insert_stmt = $conn->prepare("INSERT INTO tbl_payments (payment_type, invoice_id, appointment_id, patient_id, amount, payment_method, transaction_id, payment_status, created_at) VALUES ('Invoice Payment', ?, ?, ?, ?, 'E-Wallet', ?, 'Pending', ?)");
                        $insert_stmt->bind_param("iiidss", $invoice_id, $invoice_appointment_id, $invoice_patient_id, $amount, $transaction_id, $created_at);
                        
                        if (!$insert_stmt->execute()) {
                            error_log("ERROR: Failed to insert payment for invoice #{$invoice_id}: " . $insert_stmt->error);
                        } else {
                            error_log("SUCCESS: Created payment record - Invoice: {$invoice_id}, Patient: {$invoice_patient_id}, Appointment: " . ($invoice_appointment_id ?? 'NULL'));
                        }
                        $insert_stmt->close();
                    }
                    
                    $conn->close();
                    
                    // Redirect to PayMongo checkout
                    header("Location: " . $result['checkout_url']);
                    exit();
                } else {
                    throw new Exception($result['error'] ?? 'Failed to create payment link');
                }
                
            } catch (Exception $e) {
                error_log("PayMongo invoice payment error: " . $e->getMessage());
                $_SESSION['payment_error_message'] = "Error creating PayMongo payment: " . $e->getMessage();
                header("Location: payments.php");
                exit();
            }
        }
        
        
        try {
            // Check if tbl_payments exists
            $table_check = $conn->query("SHOW TABLES LIKE 'tbl_payments'");
            if ($table_check && $table_check->num_rows > 0) {
                // Check if unified table structure exists
                $col_check = $conn->query("SHOW COLUMNS FROM tbl_payments LIKE 'payment_type'");
                $has_payment_type = $col_check && $col_check->num_rows > 0;
                
                $processed_count = 0;
                $failed_invoices = [];
                $appointment_ids_updated = [];
                
                // Process each invoice
                foreach ($invoice_ids as $invoice_id) {
                    // Get invoice details
                    $invoice_stmt = $conn->prepare("SELECT invoice_id, patient_id, total_amount, appointment_id FROM tbl_invoices WHERE invoice_id = ? AND payment_status != 'Paid' LIMIT 1");
                    $invoice_stmt->bind_param("i", $invoice_id);
                    $invoice_stmt->execute();
                    $invoice_result = $invoice_stmt->get_result();
                    
                    if ($invoice_row = $invoice_result->fetch_assoc()) {
                        $amount = floatval($invoice_row['total_amount']);
                        $patient_id = $invoice_row['patient_id'];
                        $appointment_id = $invoice_row['appointment_id'];
                        
                        if ($has_payment_type) {
                            // Use unified payments table (payment_type, appointment_id, patient_id)
                            // Always set a non-null transaction_id for tracking (even for Cash)
                            $transaction_id = ($payment_method === 'PayMongo')
                                ? ('PM-INV-' . $invoice_id . '-' . date('YmdHis'))
                                : ('CASH-INV-' . $invoice_id . '-' . date('YmdHis'));
                            
                            $insert_stmt = $conn->prepare("
                                INSERT INTO tbl_payments 
                                (payment_type, invoice_id, appointment_id, patient_id, amount, payment_method, transaction_id, payment_date, payment_status) 
                                VALUES ('Invoice Payment', ?, ?, ?, ?, ?, ?, ?, 'Completed')
                            ");
                            $insert_stmt->bind_param("iiidsss", $invoice_id, $appointment_id, $patient_id, $amount, $payment_method, $transaction_id, $transaction_date);
                        } else {
                            // Use old table structure (legacy fallback)
                            $insert_stmt = $conn->prepare("INSERT INTO tbl_payments (invoice_id, amount, payment_method, transaction_date) VALUES (?, ?, ?, ?)");
                            $insert_stmt->bind_param("idss", $invoice_id, $amount, $payment_method, $transaction_date);
                        }
                        
                        if ($insert_stmt->execute()) {
                            // Update invoice status to 'Paid'
                            $update_invoice_stmt = $conn->prepare("UPDATE tbl_invoices SET payment_status = 'Paid' WHERE invoice_id = ?");
                            $update_invoice_stmt->bind_param("i", $invoice_id);
                            $update_invoice_stmt->execute();
                            $update_invoice_stmt->close();
                            
                            // Track appointment IDs for status update
                            if ($appointment_id) {
                                $appointment_ids_updated[] = $appointment_id;
                            }
                            
                            $processed_count++;
                            $insert_stmt->close();
                        } else {
                            $failed_invoices[] = $invoice_id;
                            $insert_stmt->close();
                        }
                    } else {
                        $failed_invoices[] = $invoice_id;
                    }
                    $invoice_stmt->close();
                }
                
                // Update appointment statuses to Completed
                if (!empty($appointment_ids_updated)) {
                    $appointment_ids_str = implode(',', array_map('intval', $appointment_ids_updated));
                    $update_appointments_stmt = $conn->prepare("UPDATE tbl_appointments SET status = 'Completed' WHERE appointment_id IN ($appointment_ids_str)");
                    $update_appointments_stmt->execute();
                    $update_appointments_stmt->close();
                    
                    // Create/update maintenance records for completed appointments
                    require_once __DIR__ . '/services/maintenance_service.php';
                    foreach ($appointment_ids_updated as $appointment_id) {
                        MaintenanceService::handleAppointmentCompletion($appointment_id);
                    }
                }
                
                // Note: Discount validation is now handled separately via the discount validation modal
                // Discounts are automatically applied based on patient's registered discount IDs in the invoice breakdown
                
                // Store success message
                if ($processed_count > 0) {
                    $message = "Payment processed successfully! " . $processed_count . " invoice(s) marked as Paid.";
                    if (!empty($appointment_ids_updated)) {
                        $message .= " " . count($appointment_ids_updated) . " appointment(s) updated to Completed.";
                    }
                    if (!empty($failed_invoices)) {
                        $message .= " Warning: " . count($failed_invoices) . " invoice(s) could not be processed.";
                    }
                    $_SESSION['payment_success_message'] = $message;
                } else {
                    $action_message = "Error: No invoices could be processed.";
                }
                
                header("Location: payments.php");
                exit();
            } else {
                $action_message = "Error: Payments table not found. Please run the create_tbl_payments.sql script.";
            }
        } catch (Exception $e) {
            $action_message = "Error: " . $e->getMessage();
        }
    }
}

// Fetch unpaid invoices
$unpaid_invoices = [];

try {
    // Check if tables exist
    $invoice_table_check = $conn->query("SHOW TABLES LIKE 'tbl_invoices'");
    if ($invoice_table_check && $invoice_table_check->num_rows > 0) {
        // Fetch unpaid invoices with patient and appointment details (including discount IDs and downpayment info)
        // Include 'Unpaid', 'Partial', and also check for NULL status (treat as unpaid)
        // For patients: only show their own invoices
        // For staff: show all unpaid invoices
        
        if ($user_type === 'patient' && isset($patient_id)) {
            $query = "
                SELECT 
                    i.invoice_id,
                    i.patient_id,
                    i.appointment_id,
                    i.total_amount,
                    i.payment_status,
                    i.generated_at,
                    p.first_name as patient_first_name,
                    p.last_name as patient_last_name,
                    p.email as patient_email,
                    MAX(CASE WHEN d_pwd.discount_type = 'PWD' AND d_pwd.is_active = 1 AND d_pwd.is_verified = 1 THEN d_pwd.id_number ELSE NULL END) as pwd_id_number,
                    MAX(CASE WHEN d_senior.discount_type = 'Senior Citizen' AND d_senior.is_active = 1 AND d_senior.is_verified = 1 THEN d_senior.id_number ELSE NULL END) as senior_citizen_id_number,
                    a.scheduled_datetime,
                    a.price as appointment_price,
                    sv.service_name,
                    sv.base_price as service_price,
                    COALESCE(SUM(CASE WHEN dp.payment_type = 'Downpayment' AND dp.payment_status = 'Completed' THEN dp.amount ELSE 0 END), 0) as total_downpayment
                FROM tbl_invoices i
                LEFT JOIN tbl_patients p ON i.patient_id = p.patient_id
                LEFT JOIN tbl_appointments a ON i.appointment_id = a.appointment_id
                LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
                LEFT JOIN tbl_payments dp ON i.appointment_id = dp.appointment_id AND dp.payment_type = 'Downpayment' AND dp.payment_status = 'Completed'
                LEFT JOIN tbl_discounts d_pwd ON p.patient_id = d_pwd.patient_id AND d_pwd.discount_type = 'PWD' AND d_pwd.is_active = 1 AND d_pwd.is_verified = 1
                LEFT JOIN tbl_discounts d_senior ON p.patient_id = d_senior.patient_id AND d_senior.discount_type = 'Senior Citizen' AND d_senior.is_active = 1 AND d_senior.is_verified = 1
                WHERE (i.payment_status != 'Paid' OR i.payment_status IS NULL) AND i.patient_id = ?
                GROUP BY i.invoice_id, i.patient_id, i.appointment_id, i.total_amount, i.payment_status, i.generated_at,
                         p.first_name, p.last_name, p.email,
                         a.scheduled_datetime, a.price, sv.service_name, sv.base_price
                ORDER BY i.generated_at DESC
                LIMIT 100
            ";
            
            $invoice_stmt = $conn->prepare($query);
            $invoice_stmt->bind_param("i", $patient_id);
        } else {
            $query = "
                SELECT 
                    i.invoice_id,
                    i.patient_id,
                    i.appointment_id,
                    i.total_amount,
                    i.payment_status,
                    i.generated_at,
                    p.first_name as patient_first_name,
                    p.last_name as patient_last_name,
                    p.email as patient_email,
                    MAX(CASE WHEN d_pwd.discount_type = 'PWD' AND d_pwd.is_active = 1 AND d_pwd.is_verified = 1 THEN d_pwd.id_number ELSE NULL END) as pwd_id_number,
                    MAX(CASE WHEN d_senior.discount_type = 'Senior Citizen' AND d_senior.is_active = 1 AND d_senior.is_verified = 1 THEN d_senior.id_number ELSE NULL END) as senior_citizen_id_number,
                    a.scheduled_datetime,
                    a.price as appointment_price,
                    sv.service_name,
                    sv.base_price as service_price,
                    COALESCE(SUM(CASE WHEN dp.payment_type = 'Downpayment' AND dp.payment_status = 'Completed' THEN dp.amount ELSE 0 END), 0) as total_downpayment
                FROM tbl_invoices i
                LEFT JOIN tbl_patients p ON i.patient_id = p.patient_id
                LEFT JOIN tbl_appointments a ON i.appointment_id = a.appointment_id
                LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
                LEFT JOIN tbl_payments dp ON i.appointment_id = dp.appointment_id AND dp.payment_type = 'Downpayment' AND dp.payment_status = 'Completed'
                LEFT JOIN tbl_discounts d_pwd ON p.patient_id = d_pwd.patient_id AND d_pwd.discount_type = 'PWD' AND d_pwd.is_active = 1 AND d_pwd.is_verified = 1
                LEFT JOIN tbl_discounts d_senior ON p.patient_id = d_senior.patient_id AND d_senior.discount_type = 'Senior Citizen' AND d_senior.is_active = 1 AND d_senior.is_verified = 1
                WHERE (i.payment_status != 'Paid' OR i.payment_status IS NULL)
                GROUP BY i.invoice_id, i.patient_id, i.appointment_id, i.total_amount, i.payment_status, i.generated_at,
                         p.first_name, p.last_name, p.email,
                         a.scheduled_datetime, a.price, sv.service_name, sv.base_price
                ORDER BY i.generated_at DESC
                LIMIT 100
            ";
            
            $invoice_stmt = $conn->prepare($query);
        }
        
        if ($invoice_stmt) {
            $invoice_stmt->execute();
            $invoice_result = $invoice_stmt->get_result();
            
            while ($row = $invoice_result->fetch_assoc()) {
                $unpaid_invoices[] = $row;
            }
            $invoice_stmt->close();
        }
    }
    
    // Fetch staff members for validator dropdown
    $staff_members = [];
    $staff_stmt = $conn->prepare("SELECT staff_id, first_name, last_name, role FROM tbl_staff ORDER BY first_name, last_name");
    $staff_stmt->execute();
    $staff_result = $staff_stmt->get_result();
    while ($row = $staff_result->fetch_assoc()) {
        $staff_members[] = $row;
    }
    $staff_stmt->close();
    
    // Fetch all patients for discount validation dropdown
    $patients_list = [];
    try {
        // Fetch all patients with their active verified discounts
        $patients_stmt = $conn->prepare("
            SELECT 
                p.patient_id, 
                p.first_name, 
                p.last_name, 
                p.email,
                MAX(CASE WHEN d.discount_type = 'PWD' AND d.is_active = 1 AND d.is_verified = 1 THEN d.id_number ELSE NULL END) as pwd_id_number,
                MAX(CASE WHEN d.discount_type = 'Senior Citizen' AND d.is_active = 1 AND d.is_verified = 1 THEN d.id_number ELSE NULL END) as senior_citizen_id_number
            FROM tbl_patients p
            LEFT JOIN tbl_discounts d ON p.patient_id = d.patient_id AND d.is_active = 1 AND d.is_verified = 1
            GROUP BY p.patient_id, p.first_name, p.last_name, p.email
            ORDER BY p.first_name, p.last_name
        ");
        
        if ($patients_stmt) {
            $patients_stmt->execute();
            $patients_result = $patients_stmt->get_result();
            
            while ($row = $patients_result->fetch_assoc()) {
                $patients_list[] = $row;
            }
            $patients_stmt->close();
        } else {
            error_log("Failed to prepare patient query: " . $conn->error);
        }
    } catch (Exception $e) {
        // If there's an error fetching patients, log it and continue with empty list
        error_log("Error fetching patients: " . $e->getMessage());
        if (empty($action_message)) {
            $action_message = "Warning: Could not fetch patient list. Please check database connection.";
        }
    }
    
    $conn->close();
} catch (Exception $e) {
    $action_message = "Error fetching data: " . $e->getMessage();
}

// Output cache prevention meta
outputCachePreventionMeta();
?>
<?php
$page_title = 'Payments';
$user_name  = $user_name ?? $admin_name ?? '';
include ROOT_PATH . '/views/layout/header.php';
?>
<div class="flex min-h-screen">
    <?php include ROOT_PATH . '/views/layout/sidebar_staff.php'; ?>
    <div class="flex-1 flex flex-col lg:ml-64">
        <?php include ROOT_PATH . '/views/layout/topbar.php'; ?>
<main class="flex-grow w-full max-w-[1400px] mx-auto px-6 lg:px-10 py-6 lg:py-10">
<?php if ($action_message): ?>
<div class="mb-4">
<?php if (strpos($action_message, 'Error:') === 0): ?>
<div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-lg">
<p class="text-sm font-medium"><?php echo htmlspecialchars($action_message); ?></p>
</div>
<?php else: ?>
<div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 px-4 py-3 rounded-lg">
<p class="text-sm font-medium"><?php echo htmlspecialchars($action_message); ?></p>
</div>
<?php endif; ?>
</div>
<?php endif; ?>
<div class="flex flex-wrap gap-2 pb-6 items-center justify-between">
<div class="flex flex-wrap gap-2 items-center">
<a class="text-[#4c6c9a] dark:text-slate-400 text-sm font-medium hover:text-primary" href="admin_dashboard.php">Dashboard</a>
<span class="text-[#4c6c9a] dark:text-slate-400 text-sm font-medium">/</span>
<a class="text-[#4c6c9a] dark:text-slate-400 text-sm font-medium hover:text-primary" href="payments.php">Payments</a>
<span class="text-[#4c6c9a] dark:text-slate-400 text-sm font-medium">/</span>
<span class="text-[#0d131b] dark:text-white text-sm font-medium">New Transaction</span>
</div>
<?php if ($user_type === 'staff'): ?>
<button onclick="openDiscountModal()" class="flex items-center gap-2 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg text-sm font-medium transition-colors">
<span class="material-symbols-outlined text-base">verified</span>
Validate Discount ID
</button>
<?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
<!-- Left Column: Payment Form -->
<div class="lg:col-span-7 flex flex-col gap-6">
<!-- Section: Select Invoice -->
<div class="bg-white dark:bg-[#1a2634] rounded-xl border border-[#e7ecf3] dark:border-slate-700 shadow-sm p-6">
<div class="flex items-center gap-3 mb-4">
<span class="material-symbols-outlined text-primary bg-primary/10 p-2 rounded-lg">receipt_long</span>
<h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Transaction Context</h3>
</div>
<div class="grid grid-cols-1 gap-4">
<label class="flex flex-col flex-1">
<p class="text-[#0d131b] dark:text-slate-200 text-sm font-medium leading-normal pb-2">Search Invoice ID or Patient Name <span class="text-red-500">*</span></p>
<p class="text-xs text-[#4c6c9a] dark:text-slate-400 mb-2">You can select multiple invoices to pay at once</p>
<?php if ($user_type === 'patient'): ?>
<div class="mb-2 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
<p class="text-xs text-amber-800 dark:text-amber-300 font-medium flex items-center gap-2">
<span class="material-symbols-outlined text-base">info</span>
<strong>Note:</strong> Please proceed to the counter to get your invoice before proceeding with payment.
</p>
</div>
<?php endif; ?>
<div class="relative">
<span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#4c6c9a]">
<span class="material-symbols-outlined text-[20px]">search</span>
</span>
<input type="text" id="invoiceSearch" class="w-full rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white pl-11 pr-4 h-12 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Type invoice ID or patient name..." autocomplete="off"/>
<div id="invoiceDropdown" class="absolute z-50 w-full mt-1 bg-white dark:bg-[#1a2634] border border-[#cfd9e7] dark:border-slate-600 rounded-lg shadow-lg max-h-60 overflow-y-auto hidden">
<!-- Invoice options will be populated here -->
</div>
</div>
</label>
<div id="selectedInvoices" class="mt-3 space-y-2 hidden">
<p class="text-xs font-semibold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wide">Selected Invoices:</p>
<div id="selectedInvoicesList" class="space-y-2">
<!-- Selected invoices will be displayed here -->
</div>
</div>
</div>
</div>
<?php if ($user_type === 'staff'): ?>
<!-- Section: Payment Details (Staff Only) -->
<div class="bg-white dark:bg-[#1a2634] rounded-xl border border-[#e7ecf3] dark:border-slate-700 shadow-sm p-6">
<div class="flex items-center gap-3 mb-6">
<span class="material-symbols-outlined text-primary bg-primary/10 p-2 rounded-lg">credit_card</span>
<h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Payment Details</h3>
</div>
<div class="grid grid-cols-1 gap-6">
<div>
<p class="text-xs font-semibold uppercase tracking-wider text-[#4c6c9a] mb-4">Payment Method <span class="text-red-500">*</span></p>
<div class="flex gap-4">
<label class="flex-1 cursor-pointer">
<input type="radio" name="payment_method" value="Cash" class="peer sr-only" checked/>
<div class="flex flex-col items-center justify-center p-4 rounded-xl border-2 border-[#e7ecf3] dark:border-slate-600 bg-slate-50 dark:bg-slate-800 peer-checked:border-primary peer-checked:bg-primary/5 transition-all hover:bg-slate-100 dark:hover:bg-slate-700">
<span class="material-symbols-outlined text-2xl mb-2 text-[#4c6c9a] peer-checked:text-primary">payments</span>
<span class="font-medium text-sm text-[#0d131b] dark:text-white peer-checked:text-primary">Cash</span>
</div>
</label>
<label class="flex-1 cursor-pointer">
<input type="radio" name="payment_method" value="PayMongo" class="peer sr-only"/>
<div class="flex flex-col items-center justify-center p-4 rounded-xl border-2 border-[#e7ecf3] dark:border-slate-600 bg-slate-50 dark:bg-slate-800 peer-checked:border-primary peer-checked:bg-primary/5 transition-all hover:bg-slate-100 dark:hover:bg-slate-700">
<span class="material-symbols-outlined text-2xl mb-2 text-[#4c6c9a] peer-checked:text-primary">account_balance_wallet</span>
<span class="font-medium text-sm text-[#0d131b] dark:text-white peer-checked:text-primary">PayMongo</span>
</div>
</label>
</div>
</div>
<!-- Cash Fields -->
<div id="cashFields" class="flex flex-col gap-4 pt-4 border-t border-dashed border-[#e7ecf3] dark:border-slate-700">
<p class="text-xs font-semibold uppercase tracking-wider text-[#4c6c9a] mb-4">Cash Payment Details</p>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<label class="flex flex-col flex-1">
<p class="text-[#0d131b] dark:text-slate-200 text-sm font-medium leading-normal pb-2">Amount Received <span class="text-red-500">*</span></p>
<input type="number" step="0.01" id="amountReceived" class="w-full rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white h-12 px-4 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="0.00"/>
</label>
<label class="flex flex-col flex-1">
<p class="text-[#0d131b] dark:text-slate-200 text-sm font-medium leading-normal pb-2">Change</p>
<input type="number" step="0.01" id="changeAmount" readonly class="w-full rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-slate-100 dark:bg-slate-800 text-[#0d131b] dark:text-white h-12 px-4 text-sm" placeholder="0.00"/>
</label>
</div>
</div>
<!-- PayMongo Fields - Only show info note, no cash details -->
<div id="paymongoFields" class="hidden flex-col gap-4 pt-4 border-t border-dashed border-[#e7ecf3] dark:border-slate-700">
<div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
<p class="text-sm text-blue-700 dark:text-blue-400 flex items-start gap-2">
<span class="material-symbols-outlined text-base mt-0.5">info</span>
<span>Clicking "Confirm Payment" will redirect to PayMongo checkout page.</span>
</p>
</div>
</div>
<label class="flex flex-col flex-1 mt-2">
<p class="text-[#0d131b] dark:text-slate-200 text-sm font-medium leading-normal pb-2">Notes (Optional)</p>
<textarea name="notes" class="w-full rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white p-4 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Add any remarks here..." rows="3"></textarea>
</label>
</div>
</div>
<?php else: ?>
<!-- Section: Pay Through PayMongo (Patient Only) -->
<div class="bg-white dark:bg-[#1a2634] rounded-xl border border-[#e7ecf3] dark:border-slate-700 shadow-sm p-6">
<div class="flex items-center gap-3 mb-4">
<span class="material-symbols-outlined text-primary bg-primary/10 p-2 rounded-lg">account_balance_wallet</span>
<h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Pay with PayMongo</h3>
</div>
<p class="text-sm text-[#4c6c9a] dark:text-slate-400 mb-6">Process your invoices through PayMongo.</p>

<div class="space-y-4">
<div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
<p class="text-sm text-blue-700 dark:text-blue-400 flex items-start gap-2">
<span class="material-symbols-outlined text-base mt-0.5">info</span>
<span>Cash transactions are processed at the counter.</span>
</p>
</div>
<div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
<p class="text-sm text-amber-700 dark:text-amber-400 flex items-start gap-2">
<span class="material-symbols-outlined text-base mt-0.5">verified</span>
<span>Discounts should be verified at the counter.</span>
</p>
</div>
</div>
</div>
<?php endif; ?>
</div>
<!-- Right Column: Invoice Preview -->
<div class="lg:col-span-5 flex flex-col h-full">
<div class="bg-white dark:bg-[#1a2634] rounded-xl border border-[#e7ecf3] dark:border-slate-700 shadow-sm overflow-hidden flex flex-col h-full max-h-[calc(100vh-140px)] sticky top-24">
<div class="p-6 border-b border-[#e7ecf3] dark:border-slate-700 bg-slate-50 dark:bg-[#1f2d3d] flex justify-between items-start">
<div>
<h3 class="text-sm font-bold text-[#4c6c9a] uppercase tracking-wide">Invoice Details</h3>
<p id="invoiceIdDisplay" class="text-2xl font-bold text-[#0d131b] dark:text-white mt-1">#---</p>
</div>
<span id="invoiceStatusBadge" class="px-3 py-1 rounded-full bg-yellow-100 text-yellow-800 text-xs font-bold border border-yellow-200 hidden">Pending Payment</span>
</div>
<div class="p-6 flex-1 overflow-y-auto">
<div id="invoicePreviewContent" class="text-center text-[#4c6c9a] dark:text-slate-400 py-8">
<p>Select an invoice to view details</p>
</div>
</div>
<div class="p-6 bg-slate-50 dark:bg-[#1f2d3d] border-t border-[#e7ecf3] dark:border-slate-700">
<form method="POST" action="" id="paymentForm">
<input type="hidden" name="action" value="process_payment">
<div id="invoiceIdsContainer">
<!-- Invoice IDs will be added here as hidden inputs -->
</div>
<input type="hidden" name="amount" id="formAmount" required>
<input type="hidden" name="payment_method" id="formPaymentMethod" value="<?php echo ($user_type === 'patient') ? 'PayMongo' : 'Cash'; ?>" required>
<input type="hidden" name="transaction_date" value="<?php date_default_timezone_set('Asia/Manila'); echo date('Y-m-d H:i:s'); ?>">
<button type="submit" class="w-full flex items-center justify-center gap-2 bg-primary hover:bg-blue-600 text-white font-bold py-3.5 px-4 rounded-lg transition-colors shadow-sm shadow-blue-200 dark:shadow-none mb-3 disabled:opacity-50 disabled:cursor-not-allowed" id="confirmPaymentBtn" disabled>
<span class="material-symbols-outlined">check_circle</span>
Confirm Payment (<span id="selectedCount">0</span>)
</button>
<button type="button" class="w-full bg-white dark:bg-transparent hover:bg-slate-50 dark:hover:bg-slate-800 text-[#4c6c9a] dark:text-slate-400 font-medium py-3 px-4 rounded-lg border border-[#e7ecf3] dark:border-slate-600 transition-colors" onclick="resetForm()">
Cancel Transaction
</button>
</form>
</div>
</div>
</div>
</div>
<!-- Discount Validation Modal -->
<div id="discountModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" onclick="if(event.target === this) closeDiscountModal();">
<div class="bg-white dark:bg-[#1a2634] rounded-xl shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation();">
<div class="p-6 border-b border-[#e7ecf3] dark:border-slate-700">
<div class="flex items-center justify-between">
<h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Validate Discount ID</h3>
<button type="button" onclick="closeDiscountModal()" class="text-[#4c6c9a] hover:text-[#0d131b] dark:text-slate-400 dark:hover:text-white">
<span class="material-symbols-outlined">close</span>
</button>
</div>
</div>
<form method="POST" action="" class="p-6">
<input type="hidden" name="action" value="validate_discount">
<div class="space-y-4">
<div>
<label class="block text-[#0d131b] dark:text-slate-200 text-sm font-medium leading-normal mb-2">
Select Patient <span class="text-red-500">*</span>
</label>
<select name="patient_id" id="discountPatientSelect" class="w-full rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white h-10 px-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" required onchange="updateDiscountPatientInfo(); updateDiscountIdField(); updateDiscountButtonText();">
<option value="">-- Select Patient --</option>
<?php if (!empty($patients_list)): ?>
<?php foreach ($patients_list as $patient): ?>
<option value="<?php echo $patient['patient_id']; ?>" data-pwd="<?php echo htmlspecialchars($patient['pwd_id_number'] ?? ''); ?>" data-senior="<?php echo htmlspecialchars($patient['senior_citizen_id_number'] ?? ''); ?>">
<?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
</option>
<?php endforeach; ?>
<?php else: ?>
<option value="" disabled>No patients found</option>
<?php endif; ?>
</select>
</div>
<div id="existingDiscountInfo" class="hidden p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
<p class="text-xs text-blue-800 dark:text-blue-300" id="existingDiscountText"></p>
</div>
<div>
<label class="block text-[#0d131b] dark:text-slate-200 text-sm font-medium leading-normal mb-2">
Discount Type <span class="text-red-500">*</span>
</label>
<select name="discount_type" id="discountTypeSelect" class="w-full rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white h-10 px-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" required onchange="updateDiscountIdField(); updateDiscountPatientInfo(); updateDiscountButtonText();">
<option value="">-- Select Discount Type --</option>
<option value="pwd">PWD (20% Discount)</option>
<option value="senior">Senior Citizen (20% Discount)</option>
</select>
</div>
<div id="discountIdContainer" class="hidden">
<label class="block text-[#0d131b] dark:text-slate-200 text-sm font-medium leading-normal mb-2">
<span id="discountIdLabel">Discount ID Number</span> <span class="text-red-500">*</span>
</label>
<input type="text" name="discount_id_number" id="discountIdNumber" maxlength="12" class="w-full rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white h-10 px-3 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Enter ID number (8-12 characters)" required/>
<div id="discountIdError" class="hidden mt-1 text-xs text-red-600 dark:text-red-400"></div>
</div>
<div class="flex gap-3 pt-4">
<button type="submit" class="flex-1 bg-primary hover:bg-blue-600 text-white font-bold py-3 px-4 rounded-lg transition-colors">
Save Discount ID
</button>
<button type="button" onclick="closeDiscountModal()" class="flex-1 bg-white dark:bg-transparent hover:bg-slate-50 dark:hover:bg-slate-800 text-[#4c6c9a] dark:text-slate-400 font-medium py-3 px-4 rounded-lg border border-[#e7ecf3] dark:border-slate-600 transition-colors">
Cancel
</button>
</div>
</div>
</form>
</div>
</div>
</main>
        </main><!-- /.main -->
    </div><!-- /flex-1 -->
</div><!-- /flex min-h-screen -->
<?php ob_start(); ?>
<script>
// SAFE SCRIPT BLOCK: UI Functions (Robust against PHP data injection errors)
function confirmLogout(event) {
    event.preventDefault();
    const modal = document.getElementById('confirmModal');
    const content = document.getElementById('confirmModalContent');
    if (modal && content) {
        content.innerHTML = `
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Log Out?</h3>
                <button onclick="closeConfirmModal()" class="text-[#4c6c9a] hover:text-[#0d131b] dark:text-slate-400 dark:hover:text-white">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <p class="text-sm text-[#4c6c9a] dark:text-slate-400 mb-4">Are you sure you want to log out?</p>
            <div class="flex gap-2">
                <button type="button" onclick="closeConfirmModal()" class="flex-1 rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white py-2 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    No
                </button>
                <a href="logout.php" class="flex-1 rounded-lg bg-red-600 text-white py-2 text-sm font-semibold hover:bg-red-700 transition-colors text-center">
                    Yes
                </a>
            </div>
        `;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeConfirmModal(event) {
    if (event && event.target !== event.currentTarget) return;
    const modal = document.getElementById('confirmModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Discount modal functions - must be in global scope for onclick handlers
window.openDiscountModal = function() {
    console.log('ðŸ”µ openDiscountModal called');
    try {
        const modal = document.getElementById('discountModal');
        console.log('ðŸ”µ Modal element:', modal);
        if (modal) {
            modal.classList.remove('hidden');
            console.log('âœ… Modal opened successfully');
            // Reset form
            const patientSelect = document.getElementById('discountPatientSelect');
            const typeSelect = document.getElementById('discountTypeSelect');
            const idInput = document.getElementById('discountIdNumber');
            const idContainer = document.getElementById('discountIdContainer');
            const existingInfo = document.getElementById('existingDiscountInfo');
            
            if (patientSelect) patientSelect.value = '';
            if (typeSelect) typeSelect.value = '';
            if (idInput) idInput.value = '';
            if (idContainer) idContainer.classList.add('hidden');
            if (existingInfo) existingInfo.classList.add('hidden');
        } else {
            console.error('âŒ Discount modal element not found!');
            console.error('Available modals:', document.querySelectorAll('[id*="modal"]'));
            alert('Discount modal not found. Please refresh the page.');
        }
    } catch (error) {
        console.error('âŒ Error opening discount modal:', error);
        console.error('Error stack:', error.stack);
        alert('Error opening discount validation form. Please refresh the page.');
    }
};

window.closeDiscountModal = function() {
    try {
        const modal = document.getElementById('discountModal');
        if (modal) {
            modal.classList.add('hidden');
        }
    } catch (error) {
        console.error('Error closing discount modal:', error);
    }
};

window.updateDiscountPatientInfo = function() {
    console.log('Updating patient info');
    const patientSelect = document.getElementById('discountPatientSelect');
    const discountTypeSelect = document.getElementById('discountTypeSelect');
    const existingInfo = document.getElementById('existingDiscountInfo');
    const existingText = document.getElementById('existingDiscountText');
    const discountIdInput = document.getElementById('discountIdNumber');
    const discountIdContainer = document.getElementById('discountIdContainer');
    
    if (!patientSelect || !existingInfo || !existingText) return;
    
    const selectedOption = patientSelect.options[patientSelect.selectedIndex];
    const discountType = discountTypeSelect ? discountTypeSelect.value : '';
    
    // Clear previous error state
    existingInfo.classList.remove('bg-red-50', 'dark:bg-red-900/20', 'border-red-200', 'dark:border-red-800');
    existingInfo.classList.remove('bg-blue-50', 'dark:bg-blue-900/20', 'border-blue-200', 'dark:border-blue-800');
    existingText.classList.remove('text-red-800', 'dark:text-red-300');
    existingText.classList.remove('text-blue-800', 'dark:text-blue-300');
    
    if (selectedOption && selectedOption.value) {
        const pwdId = selectedOption.getAttribute('data-pwd') || '';
        const seniorId = selectedOption.getAttribute('data-senior') || '';
        
        // Check if discount type is selected and patient already has that type
        if (discountType === 'pwd' && pwdId) {
            // Patient already has PWD ID - allow editing
            existingInfo.classList.remove('hidden');
            existingInfo.classList.add('bg-blue-50', 'dark:bg-blue-900/20', 'border-blue-200', 'dark:border-blue-800');
            existingText.classList.add('text-blue-800', 'dark:text-blue-300');
            existingText.textContent = 'â„¹ï¸ This patient already has a PWD ID registered: ' + pwdId + '. You can update it below.';
            if (discountIdInput) {
                discountIdInput.value = pwdId;
                discountIdInput.disabled = false;
                discountIdInput.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'cursor-not-allowed');
            }
            if (discountIdContainer) discountIdContainer.classList.remove('hidden');
        } else if (discountType === 'senior' && seniorId) {
            // Patient already has Senior Citizen ID - allow editing
            existingInfo.classList.remove('hidden');
            existingInfo.classList.add('bg-blue-50', 'dark:bg-blue-900/20', 'border-blue-200', 'dark:border-blue-800');
            existingText.classList.add('text-blue-800', 'dark:text-blue-300');
            existingText.textContent = 'â„¹ï¸ This patient already has a Senior Citizen ID registered: ' + seniorId + '. You can update it below.';
            if (discountIdInput) {
                discountIdInput.value = seniorId;
                discountIdInput.disabled = false;
                discountIdInput.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'cursor-not-allowed');
            }
            if (discountIdContainer) discountIdContainer.classList.remove('hidden');
        } else if (pwdId || seniorId) {
            // Show existing IDs as info (not error)
            existingInfo.classList.remove('hidden');
            existingInfo.classList.add('bg-blue-50', 'dark:bg-blue-900/20', 'border-blue-200', 'dark:border-blue-800');
            existingText.classList.add('text-blue-800', 'dark:text-blue-300');
            let infoText = 'Existing discount ID(s): ';
            const discounts = [];
            if (pwdId) discounts.push('PWD: ' + pwdId);
            if (seniorId) discounts.push('Senior Citizen: ' + seniorId);
            infoText += discounts.join(', ');
            existingText.textContent = infoText;
            if (discountIdInput) discountIdInput.disabled = false;
        } else {
            existingInfo.classList.add('hidden');
            if (discountIdInput) discountIdInput.disabled = false;
        }
    } else {
        existingInfo.classList.add('hidden');
        if (discountIdInput) discountIdInput.disabled = false;
    }
    
    // Also update discount ID field when patient changes
    if (discountType) {
        updateDiscountIdField();
    }
};

window.updateDiscountIdField = function() {
    console.log('Updating discount ID field');
    const discountTypeSelect = document.getElementById('discountTypeSelect');
    const discountIdContainer = document.getElementById('discountIdContainer');
    const discountIdLabel = document.getElementById('discountIdLabel');
    const discountIdInput = document.getElementById('discountIdNumber');
    const patientSelect = document.getElementById('discountPatientSelect');
    const existingInfo = document.getElementById('existingDiscountInfo');
    const existingText = document.getElementById('existingDiscountText');
    
    if (!discountTypeSelect || !discountIdContainer) return;
    
    const discountType = discountTypeSelect.value;
    const selectedOption = patientSelect ? patientSelect.options[patientSelect.selectedIndex] : null;
    
    // Clear previous error/info state
    if (existingInfo) {
        existingInfo.classList.remove('bg-red-50', 'dark:bg-red-900/20', 'border-red-200', 'dark:border-red-800');
        existingInfo.classList.remove('bg-blue-50', 'dark:bg-blue-900/20', 'border-blue-200', 'dark:border-blue-800');
    }
    if (existingText) {
        existingText.classList.remove('text-red-800', 'dark:text-red-300');
        existingText.classList.remove('text-blue-800', 'dark:text-blue-300');
    }
    
    if (discountType === 'pwd') {
        discountIdContainer.classList.remove('hidden');
        if (discountIdLabel) discountIdLabel.textContent = 'PWD ID Number';
        if (discountIdInput) {
            discountIdInput.disabled = false;
            // Check if patient already has PWD ID
            if (selectedOption && selectedOption.value) {
                const existingPwd = selectedOption.getAttribute('data-pwd') || '';
                if (existingPwd) {
                    // Patient already has PWD ID - allow editing
                    if (existingInfo) {
                        existingInfo.classList.remove('hidden');
                        existingInfo.classList.add('bg-blue-50', 'dark:bg-blue-900/20', 'border-blue-200', 'dark:border-blue-800');
                    }
                    if (existingText) {
                        existingText.classList.add('text-blue-800', 'dark:text-blue-300');
                        existingText.textContent = 'â„¹ï¸ This patient already has a PWD ID registered: ' + existingPwd + '. You can update it below.';
                    }
                    discountIdInput.value = existingPwd;
                    discountIdInput.disabled = false;
                    discountIdInput.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'cursor-not-allowed');
                } else {
                    // No existing PWD ID - allow input
                    discountIdInput.value = '';
                    discountIdInput.readOnly = false;
                    discountIdInput.disabled = false;
                    discountIdInput.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'cursor-not-allowed');
                    if (existingInfo) existingInfo.classList.add('hidden');
                }
            } else {
                // No patient selected
                discountIdInput.value = '';
                discountIdInput.readOnly = false;
                discountIdInput.disabled = false;
                discountIdInput.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'cursor-not-allowed');
                if (existingInfo) existingInfo.classList.add('hidden');
            }
        }
    } else if (discountType === 'senior') {
        discountIdContainer.classList.remove('hidden');
        if (discountIdLabel) discountIdLabel.textContent = 'Senior Citizen ID Number';
        if (discountIdInput) {
            discountIdInput.disabled = false;
            // Check if patient already has Senior Citizen ID
            if (selectedOption && selectedOption.value) {
                const existingSenior = selectedOption.getAttribute('data-senior') || '';
                if (existingSenior) {
                    // Patient already has Senior Citizen ID - allow editing
                    if (existingInfo) {
                        existingInfo.classList.remove('hidden');
                        existingInfo.classList.add('bg-blue-50', 'dark:bg-blue-900/20', 'border-blue-200', 'dark:border-blue-800');
                    }
                    if (existingText) {
                        existingText.classList.add('text-blue-800', 'dark:text-blue-300');
                        existingText.textContent = 'â„¹ï¸ This patient already has a Senior Citizen ID registered: ' + existingSenior + '. You can update it below.';
                    }
                    discountIdInput.value = existingSenior;
                    discountIdInput.disabled = false;
                    discountIdInput.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'cursor-not-allowed');
                } else {
                    // No existing Senior Citizen ID - allow input
                    discountIdInput.value = '';
                    discountIdInput.readOnly = false;
                    discountIdInput.disabled = false;
                    discountIdInput.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'cursor-not-allowed');
                    if (existingInfo) existingInfo.classList.add('hidden');
                }
            } else {
                // No patient selected
                discountIdInput.value = '';
                discountIdInput.readOnly = false;
                discountIdInput.disabled = false;
                discountIdInput.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'cursor-not-allowed');
                if (existingInfo) existingInfo.classList.add('hidden');
            }
        }
    } else {
        discountIdContainer.classList.add('hidden');
        if (discountIdInput) {
            discountIdInput.value = '';
            discountIdInput.readOnly = false;
            discountIdInput.disabled = false;
            discountIdInput.classList.remove('bg-slate-100', 'dark:bg-slate-800', 'cursor-not-allowed');
        }
        if (existingInfo) existingInfo.classList.add('hidden');
    }
    
    // Add real-time validation for discount ID when field is visible
    if (discountIdInput && !discountIdContainer.classList.contains('hidden')) {
        discountIdInput.addEventListener('input', validateDiscountId);
        discountIdInput.addEventListener('blur', validateDiscountId);
    }
    
    // Update button text based on whether editing existing discount
    updateDiscountButtonText();
};

// Update discount submit button text based on whether editing existing discount
function updateDiscountButtonText() {
    const submitBtn = document.getElementById('discountSubmitBtn');
    const patientSelect = document.getElementById('discountPatientSelect');
    const discountTypeSelect = document.getElementById('discountTypeSelect');
    
    if (!submitBtn || !patientSelect || !discountTypeSelect) return;
    
    const selectedOption = patientSelect.options[patientSelect.selectedIndex];
    const discountType = discountTypeSelect.value;
    
    if (selectedOption && selectedOption.value && discountType) {
        const pwdId = selectedOption.getAttribute('data-pwd') || '';
        const seniorId = selectedOption.getAttribute('data-senior') || '';
        
        if ((discountType === 'pwd' && pwdId) || (discountType === 'senior' && seniorId)) {
            submitBtn.textContent = 'Update Discount ID';
        } else {
            submitBtn.textContent = 'Save Discount ID';
        }
    } else {
        submitBtn.textContent = 'Save Discount ID';
    }
}

// Validate discount ID number length
function validateDiscountId() {
    const discountIdInput = document.getElementById('discountIdNumber');
    const discountIdError = document.getElementById('discountIdError');
    
    if (!discountIdInput || !discountIdError) return;
    
    const value = discountIdInput.value.trim();
    // Remove non-alphanumeric characters for validation
    const cleanValue = value.replace(/[^A-Za-z0-9]/g, '');
    const length = cleanValue.length;
    
    if (value === '') {
        discountIdError.classList.add('hidden');
        discountIdInput.classList.remove('border-red-500', 'dark:border-red-500');
        return;
    }
    
    if (length < 8) {
        discountIdError.textContent = 'Discount ID is too short. Minimum 8 characters required.';
        discountIdError.classList.remove('hidden');
        discountIdInput.classList.add('border-red-500', 'dark:border-red-500');
    } else if (length > 12) {
        discountIdError.textContent = 'Discount ID is too long. Maximum 12 characters allowed.';
        discountIdError.classList.remove('hidden');
        discountIdInput.classList.add('border-red-500', 'dark:border-red-500');
    } else {
        discountIdError.classList.add('hidden');
        discountIdInput.classList.remove('border-red-500', 'dark:border-red-500');
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeConfirmModal();
        closeDiscountModal();
    }
});
</script>
<script>
// Immediate element check (before DOMContentLoaded)
console.log('=== IMMEDIATE ELEMENT CHECK (before DOMContentLoaded) ===');
console.log('invoiceSearch:', document.getElementById('invoiceSearch'));
console.log('invoiceDropdown:', document.getElementById('invoiceDropdown'));
console.log('discountModal:', document.getElementById('discountModal'));

// Invoice data from PHP
// Invoice data from PHP
const allInvoices = <?php echo json_encode($unpaid_invoices, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PARTIAL_OUTPUT_ON_ERROR); ?> || [];

// Debug: Log invoices data (remove in production)
console.log('=== PAYMENTS PAGE DEBUG ===');
console.log('allInvoices type:', typeof allInvoices);
console.log('allInvoices is array:', Array.isArray(allInvoices));

if (typeof allInvoices === 'undefined' || !Array.isArray(allInvoices)) {
    console.error('âŒ ERROR: allInvoices is not properly initialized!');
    console.error('Type:', typeof allInvoices);
    console.error('Value:', allInvoices);
    console.error('JSON encoding may have failed. Check PHP errors.');
} else {
    console.log('âœ… SUCCESS: Invoices loaded:', allInvoices.length);
    if (allInvoices.length === 0) {
        console.warn('âš ï¸ WARNING: No unpaid invoices found. Check database query.');
        console.warn('Possible reasons:');
        console.warn('1. No invoices with status Unpaid/Partial/NULL');
        console.warn('2. Database query failed');
        console.warn('3. JOIN conditions not matching');
    } else {
        console.log('âœ… Sample invoice (first one):', allInvoices[0]);
        console.log('âœ… Invoice IDs:', allInvoices.map(inv => inv.invoice_id));
        console.log('âœ… Patient names:', allInvoices.map(inv => (inv.patient_first_name || '') + ' ' + (inv.patient_last_name || '')));
    }
}

// Global variables - accessible from anywhere
let selectedInvoices; // Map of invoice_id -> invoice object (initialized in DOMContentLoaded)

// Logout confirmation
// Payment method toggle
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded');
    
    const paymentMethodRadios = document.querySelectorAll('input[name="payment_method"]');
    const cashFields = document.getElementById('cashFields');
    const paymongoFields = document.getElementById('paymongoFields');
    const formPaymentMethod = document.getElementById('formPaymentMethod');
    
    if (paymentMethodRadios.length > 0) {
        paymentMethodRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'Cash') {
                    if (cashFields) cashFields.classList.remove('hidden');
                    if (paymongoFields) paymongoFields.classList.add('hidden');
                    if (formPaymentMethod) formPaymentMethod.value = 'Cash';
                    // Clear amount received for manual entry
                    const amountReceivedField = document.getElementById('amountReceived');
                    if (amountReceivedField) {
                        amountReceivedField.value = '';
                    }
                } else if (this.value === 'PayMongo') {
                    if (cashFields) cashFields.classList.add('hidden');
                    if (paymongoFields) paymongoFields.classList.remove('hidden');
                    if (formPaymentMethod) formPaymentMethod.value = 'PayMongo';
                    // For PayMongo, no additional fields needed
                }
            });
        });
    }
    
    // Invoice search functionality - now supports multiple selection
    // Try to get elements with retry mechanism
    let invoiceSearch = document.getElementById('invoiceSearch');
    let invoiceDropdown = document.getElementById('invoiceDropdown');
    
    console.log('ðŸ” Initial element check:');
    console.log('  invoiceSearch:', invoiceSearch ? 'FOUND' : 'NOT FOUND');
    console.log('  invoiceDropdown:', invoiceDropdown ? 'FOUND' : 'NOT FOUND');
    
    // If elements not found, wait a bit and try again (in case of slow rendering)
    if (!invoiceSearch || !invoiceDropdown) {
        console.warn('âš ï¸ Elements not found immediately, retrying in 100ms...');
        setTimeout(() => {
            invoiceSearch = document.getElementById('invoiceSearch');
            invoiceDropdown = document.getElementById('invoiceDropdown');
            console.log('ðŸ” Retry element check:');
            console.log('  invoiceSearch:', invoiceSearch ? 'FOUND' : 'NOT FOUND');
            console.log('  invoiceDropdown:', invoiceDropdown ? 'FOUND' : 'NOT FOUND');
            
            if (!invoiceSearch || !invoiceDropdown) {
                console.error('âŒ CRITICAL ERROR: invoiceSearch or invoiceDropdown still not found after retry!');
                console.error('invoiceSearch:', invoiceSearch);
                console.error('invoiceDropdown:', invoiceDropdown);
                console.error('Available elements with "invoice" in ID:');
                const allElements = document.querySelectorAll('[id*="invoice"]');
                console.log('Found', allElements.length, 'elements with "invoice" in ID:');
                allElements.forEach(el => console.log('  -', el.id, el.tagName, el));
                console.error('ðŸ’¡ Check if the HTML elements exist in the page source');
                return; // Exit early if elements still not found
            } else {
                console.log('âœ… Elements found on retry, setting up search...');
                setupInvoiceSearch(); // Call setup function
            }
        }, 100);
    } else {
        console.log('âœ… Elements found immediately, setting up search...');
        setupInvoiceSearch(); // Call setup function immediately
    }
    
    function setupInvoiceSearch() {
        // Get elements again inside function to ensure they're in scope
        const invoiceSearch = document.getElementById('invoiceSearch');
        const invoiceDropdown = document.getElementById('invoiceDropdown');
        const invoiceIdsContainer = document.getElementById('invoiceIdsContainer');
        const selectedInvoicesDiv = document.getElementById('selectedInvoices');
        const selectedInvoicesList = document.getElementById('selectedInvoicesList');
        const invoicePreviewContent = document.getElementById('invoicePreviewContent');
        const invoiceIdDisplay = document.getElementById('invoiceIdDisplay');
        const invoiceStatusBadge = document.getElementById('invoiceStatusBadge');
        const confirmPaymentBtn = document.getElementById('confirmPaymentBtn');
        const selectedCountSpan = document.getElementById('selectedCount');
        const amountReceived = document.getElementById('amountReceived');
        const changeAmount = document.getElementById('changeAmount');
        const formAmount = document.getElementById('formAmount');
        
        // Initialize selected invoices Map (assign to global variable declared at top)
        selectedInvoices = new Map(); // Map of invoice_id -> invoice object
        
        console.log('âœ… All DOM elements found, setting up search...');
        console.log('âœ… invoiceSearch element:', invoiceSearch);
        console.log('âœ… invoiceDropdown element:', invoiceDropdown);
    
        if (invoiceSearch && invoiceDropdown) {
        // Helper function to display invoice options in dropdown
        // Defined as function declaration for hoisting and scope safety
        function displayInvoiceOptions(invoices) {
            console.log('ðŸ“‹ displayInvoiceOptions called with', invoices.length, 'invoices');
            const invoiceDropdown = document.getElementById('invoiceDropdown');
            
            if (!invoiceDropdown) {
                console.error('âŒ ERROR: invoiceDropdown element not found in displayInvoiceOptions!');
                return;
            }
            
            invoiceDropdown.innerHTML = '';
            
            let createdCount = 0;
            invoices.forEach((inv, index) => {
                try {
                    let invoiceIdStr = '';
                    if (inv.invoice_id) {
                        try {
                            const invoiceYear = inv.generated_at ? new Date(inv.generated_at).getFullYear() : '';
                            const padding = String(inv.invoice_id).padStart(3, '0');
                            invoiceIdStr = invoiceYear ? `INV-${invoiceYear}-${padding}` : `INV-${padding}`;
                        } catch (dateError) {
                            invoiceIdStr = 'INV-' + String(inv.invoice_id).padStart(3, '0');
                        }
                    } else {
                        return; // Skip if missing invoice_id
                    }
                    
                    const firstName = (inv.patient_first_name || '').toString();
                    const lastName = (inv.patient_last_name || '').toString();
                    const patientName = (firstName + ' ' + lastName).trim() || 'Unknown Patient';
                    const amount = parseFloat(inv.total_amount || 0).toFixed(2);
                    
                    const option = document.createElement('div');
                    option.className = 'px-4 py-2 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer text-sm text-[#0d131b] dark:text-white flex items-center gap-2';
                    
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary';
                    checkbox.checked = selectedInvoices && selectedInvoices.has(inv.invoice_id);
                    
                    const label = document.createElement('span');
                    label.textContent = invoiceIdStr + ' - ' + patientName + ' (â‚±' + amount + ')';
                    
                    option.appendChild(checkbox);
                    option.appendChild(label);
                    
                    // Prevent label click from checking twice if label wraps checkbox
                    option.addEventListener('click', function(e) {
                         if (e.target !== checkbox) {
                             e.preventDefault();
                             checkbox.checked = !checkbox.checked;
                             window.toggleInvoiceSelection(inv, checkbox.checked);
                         }
                    });
                     // Also handle direct checkbox click
                    checkbox.addEventListener('change', function(e) {
                         window.toggleInvoiceSelection(inv, checkbox.checked);
                    });
                    
                    invoiceDropdown.appendChild(option);
                    createdCount++;
                } catch (error) {
                    console.error('âŒ Error creating invoice option:', error);
                }
            });
            
            invoiceDropdown.classList.remove('hidden');
        }

        console.log('Setting up search listener');
        
        // Direct function to show dropdown content
        const updateDropdown = function(searchTerm = '') {
            // Check if allInvoices is available and is an array
            if (typeof allInvoices === 'undefined' || !Array.isArray(allInvoices)) {
                console.error('âŒ ERROR: allInvoices is not available or not an array!');
                invoiceDropdown.innerHTML = '<div class="px-4 py-3 text-sm text-red-500">Error loading invoices. Please refresh.</div>';
                invoiceDropdown.classList.remove('hidden');
                return;
            }
            
            // Filter invoices (exclude already selected ones)
            const available = allInvoices.filter(inv => {
                // Skip if already selected
                if (selectedInvoices && selectedInvoices.has(inv.invoice_id)) {
                    return false;
                }
                
                // If search term is empty, show all non-selected
                if (searchTerm === '') {
                    return true;
                }
                
                try {
                    // Handle null/undefined patient names
                    const firstName = (inv.patient_first_name || '').toString();
                    const lastName = (inv.patient_last_name || '').toString();
                    const patientName = (firstName + ' ' + lastName).trim().toLowerCase();
                    
                    // Build invoice ID string
                    let invoiceIdStr = '';
                    if (inv.invoice_id) {
                        // Simple ID check for search
                        invoiceIdStr = String(inv.invoice_id);
                        // Also check formatted ID
                        const invoiceYear = inv.generated_at ? new Date(inv.generated_at).getFullYear() : '';
                        const formattedId = 'INV-' + (invoiceYear ? invoiceYear + '-' : '') + String(inv.invoice_id).padStart(3, '0');
                        
                        if (formattedId.toLowerCase().includes(searchTerm)) return true;
                    }
                    
                    const searchText = (invoiceIdStr.toLowerCase() + ' ' + patientName).trim();
                    return searchText.includes(searchTerm);
                } catch (error) {
                    console.error('Error filtering invoice:', error);
                    return false;
                }
            });
            
            if (available.length > 0) {
                displayInvoiceOptions(available);
            } else {
                if (searchTerm === '') {
                    invoiceDropdown.innerHTML = '<div class="px-4 py-3 text-sm text-[#4c6c9a] dark:text-slate-400">No unpaid invoices found.</div>';
                } else {
                    invoiceDropdown.innerHTML = '<div class="px-4 py-3 text-sm text-[#4c6c9a] dark:text-slate-400">No matching invoices found.</div>';
                }
                invoiceDropdown.classList.remove('hidden');
            }
        };

        invoiceSearch.addEventListener('focus', function(e) {
            console.log('ðŸ” Focus event triggered');
            updateDropdown(this.value.toLowerCase().trim());
        });

        invoiceSearch.addEventListener('click', function(e) {
             console.log('ðŸ” Click event triggered');
             updateDropdown(this.value.toLowerCase().trim());
        });

        invoiceSearch.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            console.log('ðŸ” Search input:', searchTerm);
            updateDropdown(searchTerm);
        });
        

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (invoiceSearch && invoiceDropdown && !invoiceSearch.contains(e.target) && !invoiceDropdown.contains(e.target)) {
                    invoiceDropdown.classList.add('hidden');
                }
            });
        } else {
            console.error('âŒ invoiceSearch or invoiceDropdown element is null, cannot setup search');
            console.error('invoiceSearch:', invoiceSearch);
            console.error('invoiceDropdown:', invoiceDropdown);
        }
    }
    
    // Toggle invoice selection (add/remove from selected list)
    window.toggleInvoiceSelection = function(inv, isSelected) {
        console.log('ðŸ”„ toggleInvoiceSelection called:', {
            invoice_id: inv.invoice_id,
            patient: inv.patient_first_name + ' ' + inv.patient_last_name,
            isSelected: isSelected
        });
        
        if (!selectedInvoices) {
            console.error('âŒ selectedInvoices Map is not initialized!');
            return;
        }
        
        if (isSelected) {
            selectedInvoices.set(inv.invoice_id, inv);
            console.log('âœ… Added invoice to selection. Total selected:', selectedInvoices.size);
        } else {
            selectedInvoices.delete(inv.invoice_id);
            console.log('âœ… Removed invoice from selection. Total selected:', selectedInvoices.size);
        }
        
        console.log('ðŸ“‹ Calling update functions...');
        if (typeof window.updateSelectedInvoicesDisplay === 'function') {
            window.updateSelectedInvoicesDisplay();
        } else {
            console.error('âŒ updateSelectedInvoicesDisplay is not defined!');
        }
        
        if (typeof window.updateInvoicePreview === 'function') {
            window.updateInvoicePreview();
        } else {
            console.error('âŒ updateInvoicePreview is not defined!');
        }
        
        if (typeof window.updateFormInputs === 'function') {
            window.updateFormInputs();
        } else {
            console.error('âŒ updateFormInputs is not defined!');
        }
    };
    
    // Update selected invoices display
    window.updateSelectedInvoicesDisplay = function() {
        // Get elements fresh each time to avoid scope issues
        const selectedInvoicesDiv = document.getElementById('selectedInvoices');
        const selectedInvoicesList = document.getElementById('selectedInvoicesList');
        
        if (!selectedInvoicesDiv || !selectedInvoicesList) {
            console.error('âŒ Required elements not found:', {
                selectedInvoicesDiv: !!selectedInvoicesDiv,
                selectedInvoicesList: !!selectedInvoicesList
            });
            return;
        }
        
        if (selectedInvoices.size === 0) {
            selectedInvoicesDiv.classList.add('hidden');
            return;
        }
        
        selectedInvoicesDiv.classList.remove('hidden');
        selectedInvoicesList.innerHTML = '';
        
        let totalAmount = 0;
        selectedInvoices.forEach((inv, invoiceId) => {
            const invoiceIdStr = 'INV-' + new Date(inv.generated_at).getFullYear() + '-' + String(inv.invoice_id).padStart(3, '0');
            const patientName = inv.patient_first_name + ' ' + inv.patient_last_name;
            const amount = parseFloat(inv.total_amount) || 0;
            totalAmount += amount;
            
            const item = document.createElement('div');
            item.className = 'flex items-center justify-between p-2 bg-slate-50 dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700';
            item.innerHTML = `
                <div class="flex items-center gap-2">
                    <button onclick="removeInvoice(${invoiceId})" class="text-red-500 hover:text-red-700" title="Remove">
                        <span class="material-symbols-outlined text-sm">close</span>
                    </button>
                    <span class="text-sm font-medium text-[#0d131b] dark:text-white">${invoiceIdStr}</span>
                    <span class="text-xs text-[#4c6c9a]">${patientName}</span>
                </div>
                <span class="text-sm font-bold text-primary">â‚±${amount.toFixed(2)}</span>
            `;
            selectedInvoicesList.appendChild(item);
        });
        
        // Update total
        const totalItem = document.createElement('div');
        totalItem.className = 'mt-2 pt-2 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between';
        totalItem.innerHTML = `
            <span class="text-sm font-bold text-[#0d131b] dark:text-white">Total:</span>
            <span class="text-lg font-bold text-primary">â‚±${totalAmount.toFixed(2)}</span>
        `;
        selectedInvoicesList.appendChild(totalItem);
    };
    
    // Update transaction breakdown in real-time
    window.updateTransactionBreakdown = function() {
        if (selectedInvoices.size === 0) return;
        
        const firstInvoice = Array.from(selectedInvoices.values())[0];
        const discountTypeSelect = document.getElementById('discountType');
        const pwdIdInput = document.getElementById('pwdIdNumber');
        const seniorIdInput = document.getElementById('seniorCitizenIdNumber');
        
        // Get discount type from dropdown
        const discountType = discountTypeSelect ? discountTypeSelect.value : '';
        
        // Calculate totals
        let originalTotal = 0;
        let totalDownpayment = 0;
        selectedInvoices.forEach((inv) => {
            const appointmentPrice = parseFloat(inv.appointment_price) || parseFloat(inv.service_price) || parseFloat(inv.total_amount) || 0;
            originalTotal += appointmentPrice;
            totalDownpayment += parseFloat(inv.total_downpayment) || 0;
        });
        
        // Automatically apply discount if patient has registered discount ID
        let discountPercentage = 0;
        let discountTypeName = '';
        
        if (firstInvoice.pwd_id_number) {
            // Auto-apply PWD discount if patient has registered PWD ID
            discountPercentage = 0.20;
            discountTypeName = 'PWD';
        } else if (firstInvoice.senior_citizen_id_number) {
            // Auto-apply Senior Citizen discount if patient has registered Senior Citizen ID
            discountPercentage = 0.20;
            discountTypeName = 'Senior Citizen';
        }
        
        // Calculate remaining balance before discount
        const balanceBeforeDiscount = Math.max(0, originalTotal - totalDownpayment);
        
        // Apply discount to remaining balance (not original total)
        const discountAmount = balanceBeforeDiscount * discountPercentage;
        const remainingBalance = Math.max(0, balanceBeforeDiscount - discountAmount);
        const totalPaid = totalDownpayment;
        
        // Update breakdown display
        const breakdownHtml = `
            <div class="mt-4 pt-4 border-t border-[#e7ecf3] dark:border-slate-700">
                <p class="text-xs font-semibold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wide mb-3">Transaction Breakdown</p>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-[#4c6c9a] dark:text-slate-400">Total Amount:</span>
                        <span class="text-[#0d131b] dark:text-white font-medium">â‚± ${originalTotal.toFixed(2)}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#4c6c9a] dark:text-slate-400">Downpayment Paid:</span>
                        <span class="text-[#0d131b] dark:text-white font-medium">â‚± ${totalDownpayment.toFixed(2)}</span>
                    </div>
                    <div class="flex justify-between border-t border-[#e7ecf3] dark:border-slate-700 pt-2">
                        <span class="text-[#0d131b] dark:text-white font-semibold">Balance Before Discount:</span>
                        <span class="text-[#0d131b] dark:text-white font-bold">â‚± ${balanceBeforeDiscount.toFixed(2)}</span>
                    </div>
                    ${discountPercentage > 0 && discountAmount > 0 ? `
                    <div class="flex justify-between text-green-600 dark:text-green-400">
                        <span>${discountTypeName} Discount (${(discountPercentage * 100).toFixed(0)}%):</span>
                        <span class="font-medium">-â‚± ${discountAmount.toFixed(2)}</span>
                    </div>
                    ` : `
                    <div class="flex justify-between">
                        <span class="text-[#4c6c9a] dark:text-slate-400">Discounts Applied:</span>
                        <span class="text-[#0d131b] dark:text-white font-medium">â‚± 0.00</span>
                    </div>
                    `}
                    <div class="flex justify-between border-t border-[#e7ecf3] dark:border-slate-700 pt-2">
                        <span class="text-[#0d131b] dark:text-white font-semibold">Remaining Balance to Pay:</span>
                        <span class="text-primary font-bold text-lg">â‚± ${remainingBalance.toFixed(2)}</span>
                    </div>
                </div>
            </div>
            <div id="paymentDetailsSection" class="mt-4 pt-4 border-t border-[#e7ecf3] dark:border-slate-700">
                <p class="text-xs font-semibold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wide mb-3">Payment Details</p>
                <div class="space-y-2 text-sm" id="paymentDetailsContent">
                    <div class="flex justify-between">
                        <span class="text-[#4c6c9a] dark:text-slate-400">Payment Method:</span>
                        <span class="text-[#0d131b] dark:text-white font-medium" id="paymentMethodDisplay">Cash</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#4c6c9a] dark:text-slate-400">Amount Received:</span>
                        <span class="text-[#0d131b] dark:text-white font-medium" id="amountReceivedDisplay">â‚± 0.00</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#4c6c9a] dark:text-slate-400">Change:</span>
                        <span class="text-[#0d131b] dark:text-white font-medium" id="changeAmountDisplay">â‚± 0.00</span>
                    </div>
                </div>
            </div>
        `;
        
        // Insert or update breakdown in invoice preview
        let breakdownDiv = document.getElementById('transactionBreakdown');
        if (breakdownDiv) {
            breakdownDiv.innerHTML = breakdownHtml;
        } else {
            // If breakdown div doesn't exist yet, it will be created in updateInvoicePreview
            // Store breakdown HTML to insert later
            window.pendingBreakdownHtml = breakdownHtml;
        }
        
        // Update form amount with remaining balance
        const formAmount = document.getElementById('formAmount');
        if (formAmount) {
            formAmount.value = remainingBalance.toFixed(2);
        }
        
        // Don't auto-fill amount received - let user enter it manually
    };
    
    // Update invoice preview (shows combined view for multiple invoices)
    window.updateInvoicePreview = function() {
        if (!invoicePreviewContent || !invoiceIdDisplay) {
            console.error('âŒ Required elements not found for invoice preview');
            return;
        }
        
        if (selectedInvoices.size === 0) {
            invoicePreviewContent.innerHTML = '<p class="text-center text-[#4c6c9a] dark:text-slate-400 py-8">Select invoice(s) to view details</p>';
            invoiceIdDisplay.textContent = '#---';
            invoiceStatusBadge.classList.add('hidden');
            confirmPaymentBtn.disabled = true;
            // Hide discount section
            const discountSection = document.getElementById('discountVerificationSection');
            if (discountSection) discountSection.classList.add('hidden');
            return;
        }
        
        let totalAmount = 0;
        let patientNames = new Set();
        let invoiceIds = [];
        
        selectedInvoices.forEach((inv) => {
            const invoiceIdStr = 'INV-' + new Date(inv.generated_at).getFullYear() + '-' + String(inv.invoice_id).padStart(3, '0');
            invoiceIds.push(invoiceIdStr);
            patientNames.add(inv.patient_first_name + ' ' + inv.patient_last_name);
            totalAmount += parseFloat(inv.total_amount) || 0;
        });
        
        invoiceIdDisplay.textContent = selectedInvoices.size === 1 ? '#' + invoiceIds[0] : `#${selectedInvoices.size} Invoices`;
        invoiceStatusBadge.classList.remove('hidden');
        
        // Don't auto-fill amount received - let user enter it manually
        // Only set the form amount (hidden field) to the total
        formAmount.value = totalAmount.toFixed(2);
        
        // Build preview content
        let servicesHtml = '';
        selectedInvoices.forEach((inv) => {
            const serviceName = inv.service_name || 'Appointment';
            const totalAmount = parseFloat(inv.total_amount) || 0;
            const servicePrice = parseFloat(inv.service_price) || parseFloat(inv.appointment_price) || 0;
            const additionalCharge = totalAmount - servicePrice;
            
            servicesHtml += `
                <div class="space-y-1 py-2">
                    <div class="flex justify-between items-center text-sm">
                    <span class="text-[#0d131b] dark:text-slate-200">${serviceName}</span>
                        <span class="text-[#0d131b] dark:text-slate-200 font-medium">â‚± ${servicePrice.toFixed(2)}</span>
                    </div>
                    ${additionalCharge > 0 ? `
                    <div class="flex justify-between items-center text-xs pl-4 text-[#4c6c9a] dark:text-slate-400">
                        <span>Additional Charges:</span>
                        <span class="font-medium">â‚± ${additionalCharge.toFixed(2)}</span>
                    </div>
                    ` : ''}
                    <div class="flex justify-between items-center text-sm pt-1 border-t border-[#e7ecf3] dark:border-slate-700">
                        <span class="text-[#0d131b] dark:text-slate-200 font-semibold">Subtotal:</span>
                        <span class="text-[#0d131b] dark:text-slate-200 font-bold">â‚± ${totalAmount.toFixed(2)}</span>
                    </div>
                </div>
            `;
        });
        
        invoicePreviewContent.innerHTML = `
            <div class="flex items-center gap-3 mb-4 pb-4 border-b border-[#e7ecf3] dark:border-slate-700">
                <div class="size-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-primary font-bold">
                    ${selectedInvoices.size > 1 ? selectedInvoices.size : (selectedInvoices.values().next().value.patient_first_name.charAt(0) + selectedInvoices.values().next().value.patient_last_name.charAt(0)).toUpperCase()}
                </div>
                <div>
                    <p class="text-sm font-semibold text-[#0d131b] dark:text-white">${Array.from(patientNames).join(', ')}</p>
                    <p class="text-xs text-[#4c6c9a]">${selectedInvoices.size} ${selectedInvoices.size === 1 ? 'Service' : 'Services'}</p>
                </div>
            </div>
            ${servicesHtml}
            <div id="transactionBreakdown">
                <!-- Transaction breakdown will be inserted here by updateTransactionBreakdown() -->
            </div>
        `;
        
        // Update breakdown (will auto-apply discount if patient has one)
        window.updateTransactionBreakdown();
        
        // Update payment details display
        setTimeout(() => {
            window.updatePaymentDetailsDisplay();
        }, 100);
        
        // Enable confirm button
        const confirmPaymentBtn = document.getElementById('confirmPaymentBtn');
        if (confirmPaymentBtn) {
            confirmPaymentBtn.disabled = false;
        }
    };
    
    // Update form hidden inputs
    window.updateFormInputs = function() {
        if (!invoiceIdsContainer) {
            console.error('âŒ invoiceIdsContainer not found');
            return;
        }
        
        invoiceIdsContainer.innerHTML = '';
        selectedInvoices.forEach((inv, invoiceId) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'invoice_ids[]';
            input.value = invoiceId;
            invoiceIdsContainer.appendChild(input);
        });
        
        // Update selected count - get element each time to avoid scope issues
        const selectedCountSpan = document.getElementById('selectedCount');
        if (selectedCountSpan) {
            selectedCountSpan.textContent = selectedInvoices.size;
        }
        
        // Calculate total amount
        let totalAmount = 0;
        selectedInvoices.forEach((inv) => {
            totalAmount += parseFloat(inv.total_amount) || 0;
        });
        const formAmount = document.getElementById('formAmount');
        if (formAmount) {
            formAmount.value = totalAmount.toFixed(2);
        }
    };
    
    // Display invoice details (kept for backward compatibility, but now uses updateInvoicePreview)
    window.displayInvoiceDetails = function(inv) {
        window.toggleInvoiceSelection(inv, true);
    };
    
    // Calculate change for cash payments (for multiple invoices) and update display
    if (amountReceived) {
        amountReceived.addEventListener('input', function() {
            // Get remaining balance from breakdown (after discount)
            const remainingBalanceText = document.querySelector('#transactionBreakdown .text-primary.font-bold.text-lg');
            let totalDue = 0;
            if (remainingBalanceText) {
                const balanceText = remainingBalanceText.textContent.replace(/[â‚±,\s]/g, '');
                totalDue = parseFloat(balanceText) || 0;
            } else {
                // Fallback: calculate from selected invoices
                selectedInvoices.forEach((inv) => {
                    totalDue += parseFloat(inv.total_amount) || 0;
                });
            }
            
            const received = parseFloat(this.value) || 0;
            const change = received - totalDue;
            const changeValue = change >= 0 ? change.toFixed(2) : '0.00';
            changeAmount.value = changeValue;
            formAmount.value = totalDue.toFixed(2);
            
            // Update payment details display in invoice preview
            window.updatePaymentDetailsDisplay();
        });
    }
    
    // Update payment method display when changed (reuse paymentMethodRadios from above)
    paymentMethodRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            window.updatePaymentDetailsDisplay();
        });
    });
    
    // Function to update payment details display in invoice preview
    window.updatePaymentDetailsDisplay = function() {
        const paymentMethodDisplay = document.getElementById('paymentMethodDisplay');
        const amountReceivedDisplay = document.getElementById('amountReceivedDisplay');
        const changeAmountDisplay = document.getElementById('changeAmountDisplay');
        const paymentDetailsSection = document.getElementById('paymentDetailsSection');
        
        if (!paymentDetailsSection) return;
        
        // Get selected payment method
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
        const paymentMethod = selectedMethod ? selectedMethod.value : 'Cash';
        
        // Get amount received and change
        const amountReceivedValue = amountReceived ? parseFloat(amountReceived.value) || 0 : 0;
        const changeValue = changeAmount ? parseFloat(changeAmount.value) || 0 : 0;
        
        // Update displays
        if (paymentMethodDisplay) {
            paymentMethodDisplay.textContent = paymentMethod;
        }
        if (amountReceivedDisplay) {
            amountReceivedDisplay.textContent = 'â‚± ' + amountReceivedValue.toFixed(2);
        }
        if (changeAmountDisplay) {
            changeAmountDisplay.textContent = 'â‚± ' + changeValue.toFixed(2);
            // Highlight change if positive
            if (changeValue > 0) {
                changeAmountDisplay.classList.add('text-green-600', 'dark:text-green-400', 'font-semibold');
            } else {
                changeAmountDisplay.classList.remove('text-green-600', 'dark:text-green-400', 'font-semibold');
            }
            // Show/hide change row based on payment method
            const changeRow = changeAmountDisplay.closest('.flex.justify-between');
            if (changeRow) {
                changeRow.style.display = paymentMethod === 'Cash' ? 'flex' : 'none';
            }
        }
        
        // Show payment details section if invoice is selected
        if (selectedInvoices.size > 0) {
            paymentDetailsSection.classList.remove('hidden');
        }
    };
    
    
    // Form validation
    const paymentForm = document.getElementById('paymentForm');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            const paymentMethod = selectedMethod ? selectedMethod.value : 'Cash';
            
            // Check if invoices are selected
            if (!selectedInvoices || selectedInvoices.size === 0) {
                e.preventDefault();
                alert('Please select at least one invoice.');
                return false;
            }
            
            // Only validate amount for Cash payment method
            if (paymentMethod === 'Cash') {
                const amountReceivedField = document.getElementById('amountReceived');
                if (!amountReceivedField || !amountReceivedField.value || parseFloat(amountReceivedField.value) <= 0) {
                    e.preventDefault();
                    alert('Please enter a valid amount for cash payment.');
                    return false;
                }
            }
            
            // PayMongo doesn't need amount validation - it will be handled by PayMongo
        });
    }

});

// Remove invoice from selection - globally accessible for onclick handlers
function removeInvoice(invoiceId) {
    if (!selectedInvoices) {
        console.error('selectedInvoices is not initialized');
        return;
    }
    selectedInvoices.delete(invoiceId);
    window.updateSelectedInvoicesDisplay();
    window.updateInvoicePreview();
    window.updateFormInputs();
    // Refresh search dropdown
    const invoiceSearch = document.getElementById('invoiceSearch');
    if (invoiceSearch && invoiceSearch.value) {
        invoiceSearch.dispatchEvent(new Event('input'));
    }
}

function resetForm() {
    // Clear selected invoices
    if (typeof selectedInvoices !== 'undefined') {
        selectedInvoices.clear();
    }
    if (typeof window.updateSelectedInvoicesDisplay !== 'undefined') {
        window.updateSelectedInvoicesDisplay();
    }
    if (typeof window.updateInvoicePreview !== 'undefined') {
        window.updateInvoicePreview();
    }
    if (typeof window.updateFormInputs !== 'undefined') {
        window.updateFormInputs();
    }
    
    document.getElementById('invoiceSearch').value = '';
    document.getElementById('amountReceived').value = '';
    document.getElementById('changeAmount').value = '';
    document.querySelector('input[name="payment_method"][value="Cash"]').checked = true;
    document.getElementById('cashFields').classList.remove('hidden');
    document.getElementById('ewalletFields').classList.add('hidden');
}
</script>

<!-- Confirmation Modal for Logout -->
<div id="confirmModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" onclick="closeConfirmModal(event)">
<div class="bg-white dark:bg-[#1a2634] rounded-xl shadow-xl max-w-md w-full p-6" onclick="event.stopPropagation()">
<div id="confirmModalContent">
<!-- Content will be populated by JavaScript -->
</div>
<?php $extra_scripts = ob_get_clean(); include ROOT_PATH . '/views/layout/footer.php'; ?>
