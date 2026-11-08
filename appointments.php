<?php
require_once 'config/db.php';
require_once 'config/security.php';
require_once 'functions/dentist_availability.php';

// Set security headers and check login
requireLogin();

// Get user type and role from session
$user_type = $_SESSION['user_type'] ?? 'patient';
$user_role = $_SESSION['user_role'] ?? '';

// Handle AJAX patient search for walk-in appointments
if (isset($_GET['action']) && $_GET['action'] === 'search_patients' && isset($_GET['query'])) {
    header('Content-Type: application/json');
    $query = trim($_GET['query']);
    
    if (strlen($query) < 2) {
        echo json_encode(['patients' => []]);
        exit();
    }
    
    try {
        $conn = getDBConnection();
        $search_term = '%' . $query . '%';
        
        $search_stmt = $conn->prepare("
            SELECT patient_id, first_name, last_name, email, primary_phone
            FROM tbl_patients
            WHERE first_name LIKE ? 
               OR last_name LIKE ? 
               OR email LIKE ?
               OR CONCAT(first_name, ' ', last_name) LIKE ?
               OR CONCAT(last_name, ', ', first_name) LIKE ?
            ORDER BY last_name, first_name
            LIMIT 20
        ");
        $search_stmt->bind_param("sssss", $search_term, $search_term, $search_term, $search_term, $search_term);
        $search_stmt->execute();
        $search_result = $search_stmt->get_result();
        
        $patients = [];
        while ($row = $search_result->fetch_assoc()) {
            $patients[] = $row;
        }
        $search_stmt->close();
        $conn->close();
        
        echo json_encode(['patients' => $patients]);
        exit();
    } catch (Exception $e) {
        echo json_encode(['patients' => [], 'error' => $e->getMessage()]);
        exit();
    }
}

// Handle AJAX prerequisite check for existing patients
if (isset($_GET['action']) && $_GET['action'] === 'check_prerequisites' && isset($_GET['patient_id']) && isset($_GET['service_id'])) {
    header('Content-Type: application/json');
    $patient_id = intval($_GET['patient_id']);
    $service_id = intval($_GET['service_id']);
    $has_valid_prerequisite = false;
    
    try {
        $conn = getDBConnection();
        
        // First, check if this is a maintenance service and if patient already has an active maintenance plan
        $skip_prerequisites = false;
        $service_maintenance_check = $conn->prepare("
            SELECT maintenance_period_months, maintenance_interval
            FROM tbl_service_catalog
            WHERE service_id = ?
        ");
        $service_maintenance_check->bind_param("i", $service_id);
        $service_maintenance_check->execute();
        $service_maintenance_result = $service_maintenance_check->get_result();
        $service_has_maintenance = false;
        if ($service_maintenance_result->num_rows > 0) {
            $service_maintenance_row = $service_maintenance_result->fetch_assoc();
            $service_has_maintenance = !empty($service_maintenance_row['maintenance_period_months']) && !empty($service_maintenance_row['maintenance_interval']);
        }
        $service_maintenance_check->close();
        
        // If service has maintenance plan, check if patient already has an active maintenance record
        if ($service_has_maintenance) {
            $maintenance_table_check = $conn->query("SHOW TABLES LIKE 'tbl_patient_maintenance'");
            if ($maintenance_table_check && $maintenance_table_check->num_rows > 0) {
                $active_maintenance_check = $conn->prepare("
                    SELECT maintenance_id
                    FROM tbl_patient_maintenance
                    WHERE patient_id = ?
                    AND service_id = ?
                    AND status != 'cancelled'
                    LIMIT 1
                ");
                $active_maintenance_check->bind_param("ii", $patient_id, $service_id);
                $active_maintenance_check->execute();
                $active_maintenance_result = $active_maintenance_check->get_result();
                
                if ($active_maintenance_result->num_rows > 0) {
                    // Patient already has an active maintenance plan for this service
                    // Skip prerequisite checking for subsequent maintenance appointments
                    $skip_prerequisites = true;
                    $has_valid_prerequisite = true; // Allow proceeding
                }
                $active_maintenance_check->close();
            }
        }
        
        // Only check prerequisites if not skipping (i.e., first appointment or non-maintenance service)
        if (!$skip_prerequisites) {
            // Get required prerequisites for this service
            $prereq_check = $conn->prepare("
                SELECT sp.required_service_id, 
                       COALESCE(sp.validity_period_days, 30) as validity_period_days
                FROM tbl_service_prerequisites sp
                WHERE sp.service_id = ? AND sp.is_mandatory = 1
            ");
            $prereq_check->bind_param("i", $service_id);
            $prereq_check->execute();
            $prereq_result = $prereq_check->get_result();
            
            $required_services = [];
            while ($prereq_row = $prereq_result->fetch_assoc()) {
                $required_services[] = $prereq_row;
            }
            $prereq_check->close();
            
            // If no prerequisites required, patient can proceed
            if (empty($required_services)) {
                $has_valid_prerequisite = true;
            } else {
                // Check if patient has completed all required prerequisites
                $all_prerequisites_met = true;
                foreach ($required_services as $req_service) {
                    $required_service_id = $req_service['required_service_id'];
                    $validity_days = $req_service['validity_period_days'];
                    
                    // Check if patient has completed this prerequisite within validity period
                    if ($validity_days > 0) {
                        $validity_date = date('Y-m-d H:i:s', strtotime("-{$validity_days} days"));
                        $completed_check = $conn->prepare("
                            SELECT a.appointment_id
                            FROM tbl_appointments a
                            WHERE a.patient_id = ?
                            AND a.service_id = ?
                            AND a.status = 'Completed'
                            AND a.scheduled_datetime >= ?
                            LIMIT 1
                        ");
                        $completed_check->bind_param("iis", $patient_id, $required_service_id, $validity_date);
                    } else {
                        // No validity period - check if ever completed
                        $completed_check = $conn->prepare("
                            SELECT a.appointment_id
                            FROM tbl_appointments a
                            WHERE a.patient_id = ?
                            AND a.service_id = ?
                            AND a.status = 'Completed'
                            LIMIT 1
                        ");
                        $completed_check->bind_param("ii", $patient_id, $required_service_id);
                    }
                    $completed_check->execute();
                    $completed_result = $completed_check->get_result();
                    
                    if ($completed_result->num_rows === 0) {
                        $all_prerequisites_met = false;
                        break;
                    }
                    $completed_check->close();
                }
                
                $has_valid_prerequisite = $all_prerequisites_met;
            }
        } // Close if (!$skip_prerequisites)
        
        $conn->close();
        echo json_encode(['has_valid_prerequisite' => $has_valid_prerequisite]);
        exit();
    } catch (Exception $e) {
        echo json_encode(['has_valid_prerequisite' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// Handle appointment actions (reschedule only - cancel is disabled)
$action_message = '';

// Get user info first to determine dentist status
$conn = getDBConnection();
$user_info = null;

try {
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    if ($user_type === 'patient') {
        $stmt = $conn->prepare("SELECT patient_id, first_name, last_name, email FROM tbl_patients WHERE patient_id = ? LIMIT 1");
        if (!$stmt) {
            throw new Exception("Failed to prepare patient query: " . $conn->error);
        }
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        if ($stmt->error) {
            throw new Exception("Failed to execute patient query: " . $stmt->error);
        }
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows === 1) {
            $user_info = $result->fetch_assoc();
        } else {
            session_destroy();
            header("Location: login.php");
            exit();
        }
        $stmt->close();
    } elseif ($user_type === 'staff') {
        // Admin/Staff access
        $stmt = $conn->prepare("SELECT staff_id, first_name, last_name, email, role FROM tbl_staff WHERE staff_id = ? LIMIT 1");
        if (!$stmt) {
            throw new Exception("Failed to prepare staff query: " . $conn->error);
        }
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        if ($stmt->error) {
            throw new Exception("Failed to execute staff query: " . $stmt->error);
        }
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows === 1) {
            $user_info = $result->fetch_assoc();
            // Update session role from database
            if (isset($user_info['role'])) {
                $_SESSION['user_role'] = $user_info['role'];
                $user_role = $user_info['role'];
            }
        } else {
            session_destroy();
            header("Location: login.php");
            exit();
        }
        $stmt->close();
    } else {
        // Invalid user type - should not happen if session is valid
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
    // Define admin flags after user_info is fetched
    // Treat both Admin and Manager as admin-level staff
    $is_admin = ($user_type === 'staff' && ($user_role === 'Manager' || $user_role === 'Admin' || $user_role === 'Dentist' || $user_role === 'Receptionist'));
    $is_admin_only = ($user_type === 'staff' && ($user_role === 'Manager' || $user_role === 'Admin'));
    $is_receptionist = ($user_type === 'staff' && $user_role === 'Receptionist');
    $is_dentist = ($user_type === 'staff' && $user_role === 'Dentist');
    // $is_manager is true only for Manager role (not Admin), used to hide Manager-only features like Walk-in
    $is_manager = ($user_type === 'staff' && $user_role === 'Manager');
    $dentist_id = $is_dentist ? $user_info['staff_id'] : null;
    
    $conn->close();
} catch (Exception $e) {
    // Handle error
    if ($conn) $conn->close();
    // Initialize variables even on error to prevent undefined variable errors
    $is_dentist = false;
    $dentist_id = null;
    $is_admin = false;
    $is_admin_only = false;
    $is_receptionist = false;
    $is_manager = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle clinic closure dates (Manager only)
    if (isset($_POST['action']) && $_POST['action'] === 'add_closure_date' && $user_role === 'Manager') {
        $closure_date = $_POST['closure_date'] ?? '';
        $reason = trim($_POST['reason'] ?? '');
        
        if (!empty($closure_date)) {
            try {
                $conn = getDBConnection();
                
                // Check if there are any appointments scheduled on this date
                $check_stmt = $conn->prepare("
                    SELECT COUNT(*) as appointment_count 
                    FROM tbl_appointments 
                    WHERE DATE(scheduled_datetime) = ? 
                    AND status NOT IN ('cancelled', 'completed')
                ");
                $check_stmt->bind_param("s", $closure_date);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $check_row = $check_result->fetch_assoc();
                $check_stmt->close();
                
                if ($check_row['appointment_count'] > 0) {
                    $action_message = "Cannot set closure date: There are " . $check_row['appointment_count'] . " appointment(s) scheduled on this date. Please reschedule or cancel those appointments first.";
                    $conn->close();
                } else {
                    $stmt = $conn->prepare("INSERT INTO tbl_clinic_closures (closure_date, reason, created_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE reason = ?, created_by = ?");
                    $stmt->bind_param("ssiss", $closure_date, $reason, $_SESSION['user_id'], $reason, $_SESSION['user_id']);
                    if ($stmt->execute()) {
                        $stmt->close();
                        $conn->close();
                        $_SESSION['action_message'] = "Closure date added successfully.";
                        header("Location: appointments.php");
                        exit();
                    } else {
                        $action_message = "Failed to add closure date: " . $stmt->error;
                    }
                    $stmt->close();
                    $conn->close();
                }
            } catch (Exception $e) {
                $action_message = "Error: " . $e->getMessage();
            }
        }
    }
    
    // Handle remove closure date (Manager only)
    if (isset($_POST['action']) && $_POST['action'] === 'remove_closure_date' && $user_role === 'Manager') {
        $closure_id = intval($_POST['closure_id'] ?? 0);
        if ($closure_id > 0) {
            try {
                $conn = getDBConnection();
                $stmt = $conn->prepare("DELETE FROM tbl_clinic_closures WHERE closure_id = ?");
                $stmt->bind_param("i", $closure_id);
                if ($stmt->execute()) {
                    $stmt->close();
                    $conn->close();
                    $_SESSION['action_message'] = "Closure date removed successfully.";
                    header("Location: appointments.php");
                    exit();
                }
                $stmt->close();
                $conn->close();
            } catch (Exception $e) {
                $action_message = "Error: " . $e->getMessage();
            }
        }
    }
    
    // Handle dentist schedule (Manager only)
    if (isset($_POST['action']) && $_POST['action'] === 'save_dentist_schedule' && $user_role === 'Manager') {
        $dentist_id = intval($_POST['dentist_id'] ?? 0);
        $schedules = $_POST['schedules'] ?? [];
        
        if ($dentist_id > 0 && is_array($schedules)) {
            try {
                $conn = getDBConnection();
                // Delete existing schedules for this dentist (set is_active = 0 if column exists, otherwise delete)
                $col_check = $conn->query("SHOW COLUMNS FROM tbl_dentist_schedules LIKE 'is_active'");
                $has_is_active = $col_check && $col_check->num_rows > 0;
                
                if ($has_is_active) {
                    $delete_stmt = $conn->prepare("UPDATE tbl_dentist_schedules SET is_active = 0 WHERE dentist_id = ?");
                } else {
                $delete_stmt = $conn->prepare("DELETE FROM tbl_dentist_schedules WHERE dentist_id = ?");
                }
                $delete_stmt->bind_param("i", $dentist_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                // Insert new schedules - only process enabled schedules
                if ($has_is_active) {
                    $insert_stmt = $conn->prepare("INSERT INTO tbl_dentist_schedules (dentist_id, day_of_week, start_time, end_time, is_active) VALUES (?, ?, ?, ?, 1)");
                } else {
                $insert_stmt = $conn->prepare("INSERT INTO tbl_dentist_schedules (dentist_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)");
                }
                
                foreach ($schedules as $schedule) {
                    // Only process if enabled is set and day, start_time, end_time are present
                    if (isset($schedule['enabled']) && $schedule['enabled'] == '1' && 
                        isset($schedule['day']) && isset($schedule['start_time']) && isset($schedule['end_time'])) {
                        $day = intval($schedule['day']);
                        $start_time = trim($schedule['start_time']);
                        $end_time = trim($schedule['end_time']);
                        
                        // Validate day (0-6)
                        if ($day >= 0 && $day <= 6 && !empty($start_time) && !empty($end_time)) {
                            // Ensure time format includes seconds
                            if (strlen($start_time) == 5) $start_time .= ':00';
                            if (strlen($end_time) == 5) $end_time .= ':00';
                            
                        $insert_stmt->bind_param("iiss", $dentist_id, $day, $start_time, $end_time);
                        $insert_stmt->execute();
                        }
                    }
                }
                $insert_stmt->close();
                $conn->close();
                $action_message = "Dentist schedule saved successfully.";
            } catch (Exception $e) {
                error_log("Error saving dentist schedule: " . $e->getMessage());
                $action_message = "Error saving schedule: " . htmlspecialchars($e->getMessage());
            }
        } else {
            $action_message = "Invalid dentist ID or schedule data.";
        }
    }
    
    // Handle walk-in cash payment (Staff/Admin only)
    if (isset($_POST['action']) && $_POST['action'] === 'process_walkin_cash' && ($user_type === 'staff')) {
        $appointment_id = intval($_POST['appointment_id'] ?? 0);
        $amount_received = floatval($_POST['amount_received'] ?? 0);
        $downpayment_amount = floatval($_POST['downpayment_amount'] ?? 0);
        
        if ($appointment_id > 0 && $amount_received > 0 && $downpayment_amount > 0) {
            try {
                $conn = getDBConnection();
                
                // Verify appointment exists
                $verify_stmt = $conn->prepare("SELECT appointment_id, patient_id, price FROM tbl_appointments WHERE appointment_id = ? LIMIT 1");
                $verify_stmt->bind_param("i", $appointment_id);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                
                if ($verify_result->num_rows === 1) {
                    $appointment = $verify_result->fetch_assoc();
                    $patient_id = $appointment['patient_id'];
                    
                    // Calculate change
                    $change = $amount_received - $downpayment_amount;
                    
                    if ($change >= 0) {
                        // Insert cash payment record
                        $table_check = $conn->query("SHOW COLUMNS FROM tbl_payments LIKE 'payment_type'");
                        $has_payment_type = $table_check && $table_check->num_rows > 0;
                        
                        if ($has_payment_type) {
                            // Use unified payments table
                            $payment_stmt = $conn->prepare("
                                INSERT INTO tbl_payments 
                                (patient_id, appointment_id, payment_type, amount, payment_method, payment_status, payment_date, transaction_id, staff_validator_id) 
                                VALUES (?, ?, 'Downpayment', ?, 'Cash', 'Completed', NOW(), ?, ?)
                            ");
                            $transaction_id = 'CASH-' . date('YmdHis') . '-' . $appointment_id;
                            $payment_stmt->bind_param("iidsi", $patient_id, $appointment_id, $downpayment_amount, $transaction_id, $_SESSION['user_id']);
                        } else {
                            // Fallback to old table
                            $payment_stmt = $conn->prepare("
                                INSERT INTO tbl_appointment_downpayments 
                                (appointment_id, amount, payment_method, payment_status, transaction_id, staff_validator_id) 
                                VALUES (?, ?, 'Cash', 'Completed', ?, ?)
                            ");
                            $transaction_id = 'CASH-' . date('YmdHis') . '-' . $appointment_id;
                            $payment_stmt->bind_param("idsi", $appointment_id, $downpayment_amount, $transaction_id, $_SESSION['user_id']);
                        }
                        
                        if ($payment_stmt->execute()) {
                            // Update appointment status to 'Scheduled'
                            $update_stmt = $conn->prepare("UPDATE tbl_appointments SET status = 'Scheduled' WHERE appointment_id = ?");
                            $update_stmt->bind_param("i", $appointment_id);
                            $update_stmt->execute();
                            $update_stmt->close();
                            
                            $payment_stmt->close();
                            $conn->close();
                            
                            // Clear session
                            unset($_SESSION['walkin_pending_appointment']);
                            
                            $_SESSION['action_message'] = "Walk-in appointment created and payment processed successfully!";
                            header("Location: appointments.php");
                            exit();
                        } else {
                            $errors[] = "Failed to process payment: " . $conn->error;
                        }
                        $payment_stmt->close();
                    } else {
                        $errors[] = "Amount received is less than downpayment amount.";
                    }
                } else {
                    $errors[] = "Appointment not found.";
                }
                $verify_stmt->close();
                $conn->close();
            } catch (Exception $e) {
                $action_message = "Error: " . $e->getMessage();
            }
        } else {
            $action_message = "Invalid payment details.";
        }
    }
    
    
    // Handle walk-in appointment booking (Staff/Admin only)
    if (isset($_POST['action']) && $_POST['action'] === 'book_walkin' && ($user_type === 'staff')) {
        $registration_type = $_POST['registration_type'] ?? '';
        $patient_id = intval($_POST['patient_id'] ?? 0);
        $service_id = intval($_POST['service_id'] ?? 0);
        $scheduled_date = trim($_POST['scheduled_date'] ?? '');
        $scheduled_time = trim($_POST['scheduled_time'] ?? '');
        
        // For new patients
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $email = trim(strtolower($_POST['email'] ?? ''));
        $phone = trim($_POST['phone'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        
        $errors = [];
        
        // Validation
        if ($registration_type === 'existing' && $patient_id <= 0) {
            $errors[] = "Please select a patient.";
        }
        
        if ($registration_type === 'new') {
            if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || empty($dob)) {
                $errors[] = "All patient information fields are required.";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email address format. Please use a valid email (e.g., name@example.com).";
            } else {
                // Validate phone number format (7-20 digits, may include +, -, (), or spaces)
                $phone_cleaned = preg_replace('/[\D]/', '', $phone);
                if (strlen($phone_cleaned) < 7 || strlen($phone_cleaned) > 20) {
                    $errors[] = "Invalid phone number format. Please enter 7-20 digits (may include +, -, (), or spaces).";
                }
            }
        }
        
        if ($service_id <= 0) {
            $errors[] = "Please select a service.";
        }
        
        if (empty($scheduled_date) || empty($scheduled_time)) {
            $errors[] = "Please select date and time.";
        }
        
        // Validate past date/time
        if (!empty($scheduled_date) && !empty($scheduled_time)) {
            $scheduled_datetime_check = new DateTime($scheduled_date . ' ' . $scheduled_time);
            $now = new DateTime();
            if ($scheduled_datetime_check < $now) {
                $errors[] = "Cannot book appointments in the past.";
            }
        }
        
        if (empty($errors)) {
            try {
                $conn = getDBConnection();
                
                // Get or create patient
                if ($registration_type === 'new') {
                    // Check if patient exists
                    $check_stmt = $conn->prepare("SELECT patient_id FROM tbl_patients WHERE email = ? LIMIT 1");
                    $check_stmt->bind_param("s", $email);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        $existing = $check_result->fetch_assoc();
                        $patient_id = $existing['patient_id'];
                    } else {
                        // Create new patient
                        $gender = $_POST['gender'] ?? 'Prefer not to say';
                        $blood_type = $_POST['blood_type'] ?? 'Unknown';
                        $allergies = isset($_POST['allergies']) ? $_POST['allergies'] : [];
                        
                        // Validate gender
                        $valid_genders = ['Male', 'Female', 'Other', 'Prefer not to say'];
                        if (!in_array($gender, $valid_genders)) {
                            $gender = 'Prefer not to say';
                        }
                        
                        // Validate blood type
                        $valid_blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'];
                        if (!in_array($blood_type, $valid_blood_types)) {
                            $blood_type = 'Unknown';
                        }
                        
                        // Generate a unique username from first and last name
                        $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $first_name . $last_name));
                        $username = $base_username;
                        $counter = 1;
                        
                        while (true) {
                            $u_stmt = $conn->prepare("SELECT patient_id FROM tbl_patients WHERE username = ? LIMIT 1");
                            $u_stmt->bind_param("s", $username);
                            $u_stmt->execute();
                            if ($u_stmt->get_result()->num_rows === 0) {
                                $u_stmt->close();
                                break;
                            }
                            $u_stmt->close();
                            $username = $base_username . $counter++;
                        }
                        
                        // Set a default password hash
                        $default_pass = password_hash('Patient123!', PASSWORD_DEFAULT);
                        
                        $insert_stmt = $conn->prepare("INSERT INTO tbl_patients (username, first_name, last_name, email, primary_phone, dob, gender, blood_type, password, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                        $insert_stmt->bind_param("sssssssss", $username, $first_name, $last_name, $email, $phone, $dob, $gender, $blood_type, $default_pass);
                        if ($insert_stmt->execute()) {
                            $patient_id = $conn->insert_id;
                            
                            // Insert allergies if any
                            if (!empty($allergies) && is_array($allergies)) {
                                $allergy_stmt = $conn->prepare("INSERT INTO tbl_patient_allergies (patient_id, allergy_name) VALUES (?, ?)");
                                foreach ($allergies as $allergy) {
                                    $allergy_trimmed = trim($allergy);
                                    if (!empty($allergy_trimmed)) {
                                        $allergy_stmt->bind_param("is", $patient_id, $allergy_trimmed);
                                        $allergy_stmt->execute();
                                    }
                                }
                                $allergy_stmt->close();
                            }
                        } else {
                            $errors[] = "Failed to create patient record: " . $conn->error;
                        }
                        $insert_stmt->close();
                    }
                    $check_stmt->close();
                }
                
                if ($patient_id > 0 && empty($errors)) {
                    // Combine date and time
                    $scheduled_datetime = $scheduled_date . ' ' . $scheduled_time . ':00';
                    
                    // Get dentist ID from form (optional - system can select if not provided)
                    $dentist_id = intval($_POST['dentist_id'] ?? 0);
                    // Note: If dentist_id is 0, system will auto-select an available dentist later
                    
                    // Get service details
                    $service_stmt = $conn->prepare("SELECT service_id, service_name, base_price, default_duration_minutes FROM tbl_service_catalog WHERE service_id = ? AND is_active = 1 LIMIT 1");
                    $service_stmt->bind_param("i", $service_id);
                    $service_stmt->execute();
                    $service_result = $service_stmt->get_result();
                    $service_details = $service_result->fetch_assoc();
                    $service_stmt->close();
                    
                    if (!$service_details) {
                        $errors[] = "Service not found or inactive.";
                    }
                    
                    // STRICT: Check if dentist can perform this service (specialization check)
                    if ($dentist_id > 0 && $service_details && empty($errors)) {
                        // Build dentist-services map for service specialization checking
                        $dentist_services_map = [];
                        try {
                            // Get all service specializations mapping
                            $service_specializations = [];
                            $spec_table_check = $conn->query("SHOW TABLES LIKE 'tbl_service_specializations'");
                            if ($spec_table_check && $spec_table_check->num_rows > 0) {
                                $service_spec_stmt = $conn->prepare("SELECT service_id, specialization_id FROM tbl_service_specializations");
                                if ($service_spec_stmt) {
                                    $service_spec_stmt->execute();
                                    $res = $service_spec_stmt->get_result();
                                    while ($row = $res->fetch_assoc()) {
                                        $sid = $row['service_id'];
                                        if (!isset($service_specializations[$sid])) {
                                            $service_specializations[$sid] = [];
                                        }
                                        $service_specializations[$sid][] = $row['specialization_id'];
                                    }
                                    $service_spec_stmt->close();
                                }
                            }
                            
                            // Get all staff specializations mapping
                            $staff_specializations = [];
                            $staff_spec_table_check = $conn->query("SHOW TABLES LIKE 'tbl_staff_specializations'");
                            if ($staff_spec_table_check && $staff_spec_table_check->num_rows > 0) {
                                $staff_spec_stmt = $conn->prepare("SELECT staff_id, specialization_id FROM tbl_staff_specializations");
                                if ($staff_spec_stmt) {
                                    $staff_spec_stmt->execute();
                                    $res = $staff_spec_stmt->get_result();
                                    while ($row = $res->fetch_assoc()) {
                                        $sid = $row['staff_id'];
                                        if (!isset($staff_specializations[$sid])) {
                                            $staff_specializations[$sid] = [];
                                        }
                                        $staff_specializations[$sid][] = $row['specialization_id'];
                                    }
                                    $staff_spec_stmt->close();
                                }
                            }
                            
                            // Get all active services
                            $all_services = [];
                            $s_stmt = $conn->prepare("SELECT service_id FROM tbl_service_catalog WHERE is_active = 1");
                            if ($s_stmt) {
                                $s_stmt->execute();
                                $res = $s_stmt->get_result();
                                while($row = $res->fetch_assoc()) $all_services[] = $row['service_id'];
                                $s_stmt->close();
                            }
                            
                            // Map permissions for the selected dentist
                            $my_specs = isset($staff_specializations[$dentist_id]) ? $staff_specializations[$dentist_id] : [];
                            foreach ($all_services as $sid) {
                                $req_specs = isset($service_specializations[$sid]) ? $service_specializations[$sid] : [];
                                $can_do = false;
                                if (empty($req_specs)) {
                                    // Service has no required specializations - any dentist can do it
                                    $can_do = true;
                                } else {
                                    // Service has required specializations - check if dentist has at least one matching
                                    foreach ($req_specs as $rs) {
                                        if (in_array($rs, $my_specs)) {
                                            $can_do = true;
                                            break;
                                        }
                                    }
                                }
                                if ($can_do) {
                                    $dentist_services_map[$dentist_id][] = $sid;
                                }
                            }
                        } catch (Exception $e) {
                            error_log("Error building dentist services map: " . $e->getMessage());
                        }
                        
                        // Check if dentist can perform this service
                        $can_perform = false;
                        
                        if (isset($dentist_services_map[$dentist_id])) {
                            if (is_array($dentist_services_map[$dentist_id]) && in_array($service_id, $dentist_services_map[$dentist_id])) {
                                $can_perform = true;
                            }
                        }
                        
                        // If not found in map, check if service has no required specializations
                        // If service has no required specializations, any dentist can perform it
                        if (!$can_perform) {
                            $service_spec_check = $conn->prepare("SELECT COUNT(*) as count FROM tbl_service_specializations WHERE service_id = ?");
                            if ($service_spec_check) {
                                $service_spec_check->bind_param("i", $service_id);
                                $service_spec_check->execute();
                                $spec_result = $service_spec_check->get_result();
                                $spec_row = $spec_result->fetch_assoc();
                                if ($spec_row['count'] == 0) {
                                    // Service has no required specializations - any dentist can do it
                                    $can_perform = true;
                                }
                                $service_spec_check->close();
                            }
                        }
                        
                        if (!$can_perform) {
                            $errors[] = "Selected dentist does not specialize in this service. Please select a different dentist or service.";
                        }
                    }
                    
                    // STRICT: Check dentist availability BEFORE proceeding (with actual service duration)
                    if ($dentist_id > 0 && $service_details && empty($errors)) {
                        require_once 'functions/dentist_availability.php';
                        $actual_duration = intval($service_details['default_duration_minutes']);
                        $availability_check = checkDentistFullAvailability($dentist_id, $scheduled_datetime, $actual_duration);
                        
                        if (!$availability_check['available']) {
                            $errors[] = "Dentist is not available: " . $availability_check['reason'];
                        }
                    }
                    
                    if ($service_details) {
                        // Check prerequisites for both existing and new patients
                        if ($registration_type === 'existing' || $registration_type === 'new') {
                            // First, check if this is a maintenance service and if patient already has an active maintenance plan
                            $skip_prerequisites = false;
                            $service_maintenance_check = $conn->prepare("
                                SELECT maintenance_period_months, maintenance_interval
                                FROM tbl_service_catalog
                                WHERE service_id = ?
                            ");
                            $service_maintenance_check->bind_param("i", $service_id);
                            $service_maintenance_check->execute();
                            $service_maintenance_result = $service_maintenance_check->get_result();
                            $service_has_maintenance = false;
                            if ($service_maintenance_result->num_rows > 0) {
                                $service_maintenance_row = $service_maintenance_result->fetch_assoc();
                                $service_has_maintenance = !empty($service_maintenance_row['maintenance_period_months']) && !empty($service_maintenance_row['maintenance_interval']);
                            }
                            $service_maintenance_check->close();
                            
                            // If service has maintenance plan and patient is existing, check if they have active maintenance record
                            if ($service_has_maintenance && $registration_type === 'existing' && $patient_id) {
                                $maintenance_table_check = $conn->query("SHOW TABLES LIKE 'tbl_patient_maintenance'");
                                if ($maintenance_table_check && $maintenance_table_check->num_rows > 0) {
                                    $active_maintenance_check = $conn->prepare("
                                        SELECT maintenance_id
                                        FROM tbl_patient_maintenance
                                        WHERE patient_id = ?
                                        AND service_id = ?
                                        AND status != 'cancelled'
                                        LIMIT 1
                                    ");
                                    $active_maintenance_check->bind_param("ii", $patient_id, $service_id);
                                    $active_maintenance_check->execute();
                                    $active_maintenance_result = $active_maintenance_check->get_result();
                                    
                                    if ($active_maintenance_result->num_rows > 0) {
                                        // Patient already has an active maintenance plan for this service
                                        // Skip prerequisite checking for subsequent maintenance appointments
                                        $skip_prerequisites = true;
                                    }
                                    $active_maintenance_check->close();
                                }
                            }
                            
                            // Only check prerequisites if not skipping (i.e., first appointment or non-maintenance service)
                            if (!$skip_prerequisites) {
                                $prereq_check = $conn->prepare("
                                    SELECT sp.required_service_id, 
                                           sc.service_name as required_service_name,
                                           COALESCE(sp.validity_period_days, 30) as validity_period_days
                                    FROM tbl_service_prerequisites sp
                                    INNER JOIN tbl_service_catalog sc ON sp.required_service_id = sc.service_id
                                    WHERE sp.service_id = ? AND sp.is_mandatory = 1
                                ");
                                $prereq_check->bind_param("i", $service_id);
                                $prereq_check->execute();
                                $prereq_result = $prereq_check->get_result();
                                
                                $required_services = [];
                                while ($prereq_row = $prereq_result->fetch_assoc()) {
                                    $required_services[] = $prereq_row;
                                }
                                $prereq_check->close();
                                
                                // If prerequisites exist, check if patient has completed them within validity period
                                if (!empty($required_services)) {
                                    // For new patients, prerequisites cannot be checked (they have no history)
                                    // So we allow them to proceed but show a warning
                                    if ($registration_type === 'new') {
                                    // New patients can proceed, but prerequisites will be noted
                                    // No error blocking, just informational
                                } else {
                                    // For existing patients, check if they have completed prerequisites
                                    $has_valid_prerequisite = false;
                                    $missing_prerequisites = [];
                                    $expired_prerequisites = [];
                                    
                                    foreach ($required_services as $req_service) {
                                        $required_service_id = $req_service['required_service_id'];
                                        $validity_days = $req_service['validity_period_days'];
                                        
                                        // Check if patient has completed this prerequisite within validity period
                                        if ($validity_days > 0) {
                                            $validity_date = date('Y-m-d H:i:s', strtotime("-{$validity_days} days"));
                                            $completed_check = $conn->prepare("
                                                SELECT a.appointment_id, a.scheduled_datetime, a.status
                                                FROM tbl_appointments a
                                                WHERE a.patient_id = ?
                                                AND a.service_id = ?
                                                AND a.status = 'Completed'
                                                AND a.scheduled_datetime >= ?
                                                ORDER BY a.scheduled_datetime DESC
                                                LIMIT 1
                                            ");
                                            $completed_check->bind_param("iis", $patient_id, $required_service_id, $validity_date);
                                        } else {
                                            // No validity period - check if ever completed
                                            $completed_check = $conn->prepare("
                                                SELECT a.appointment_id, a.scheduled_datetime, a.status
                                                FROM tbl_appointments a
                                                WHERE a.patient_id = ?
                                                AND a.service_id = ?
                                                AND a.status = 'Completed'
                                                ORDER BY a.scheduled_datetime DESC
                                                LIMIT 1
                                            ");
                                            $completed_check->bind_param("ii", $patient_id, $required_service_id);
                                        }
                                        $completed_check->execute();
                                        $completed_result = $completed_check->get_result();
                                        
                                        if ($completed_result->num_rows > 0) {
                                            $has_valid_prerequisite = true;
                                        } else {
                                            // Check if there's an expired one
                                            $expired_check = $conn->prepare("
                                                SELECT a.appointment_id, a.scheduled_datetime
                                                FROM tbl_appointments a
                                                WHERE a.patient_id = ?
                                                AND a.service_id = ?
                                                AND a.status = 'Completed'
                                                ORDER BY a.scheduled_datetime DESC
                                                LIMIT 1
                                            ");
                                            $expired_check->bind_param("ii", $patient_id, $required_service_id);
                                            $expired_check->execute();
                                            $expired_result = $expired_check->get_result();
                                            
                                            if ($expired_result->num_rows > 0) {
                                                $expired_prerequisites[] = $req_service['required_service_name'];
                                            } else {
                                                $missing_prerequisites[] = $req_service['required_service_name'];
                                            }
                                            $expired_check->close();
                                        }
                                        $completed_check->close();
                                    }
                                    
                                    if (!$has_valid_prerequisite) {
                                        $required_names = array_column($required_services, 'required_service_name');
                                        if (!empty($expired_prerequisites)) {
                                            $errors[] = "Patient's " . implode(' or ', $expired_prerequisites) . " appointment has expired (must be within the last " . $required_services[0]['validity_period_days'] . " days). Please schedule a new " . $required_names[0] . " before booking " . ($service_details['service_name'] ?? 'this service') . ".";
                                        } else {
                                            $errors[] = "Patient must complete a " . implode(' or ', $required_names) . " appointment before booking " . ($service_details['service_name'] ?? 'this service') . ". Please book a " . $required_names[0] . " first.";
                                        }
                                    }
                                }
                            }
                            } // Close if (!$skip_prerequisites)
                        }
                        
                        // Only proceed if no prerequisite errors
                        if (empty($errors)) {
                            // Get selected dentist from form
                            $dentist_id = intval($_POST['dentist_id'] ?? 0);
                            
                            // If no dentist selected, find first available
                            if ($dentist_id <= 0) {
                            $dentist_stmt = $conn->prepare("SELECT staff_id FROM tbl_staff WHERE role IN ('Dentist', 'Admin') ORDER BY staff_id ASC LIMIT 1");
                            $dentist_stmt->execute();
                            $dentist_result = $dentist_stmt->get_result();
                            if ($dentist_result->num_rows > 0) {
                                $dentist_row = $dentist_result->fetch_assoc();
                                $dentist_id = $dentist_row['staff_id'];
                            }
                            $dentist_stmt->close();
                            } else {
                                // Verify selected dentist exists
                                $verify_stmt = $conn->prepare("SELECT staff_id FROM tbl_staff WHERE staff_id = ? AND role IN ('Dentist', 'Admin') LIMIT 1");
                                $verify_stmt->bind_param("i", $dentist_id);
                                $verify_stmt->execute();
                                if ($verify_stmt->get_result()->num_rows === 0) {
                                    $dentist_id = 0;
                                }
                                $verify_stmt->close();
                            }
                            
                            if ($dentist_id) {
                                $service_price = $service_details['base_price'];
                                $duration = $service_details['default_duration_minutes'];
                                
                                // STRICT: Check dentist availability before creating appointment
                                require_once 'functions/dentist_availability.php';
                                $availability_check = checkDentistFullAvailability($dentist_id, $scheduled_datetime, $duration);
                                
                                if (!$availability_check['available']) {
                                    $errors[] = "Cannot book appointment: " . $availability_check['reason'];
                                }
                                
                                // Only create appointment if dentist is available
                                if (empty($errors)) {
                                // Calculate downpayment (35% of service price)
                                $downpayment_amount = $service_price * 0.35;
                                
                                // Create appointment with status 'Pending' (waiting for downpayment)
                                $appt_stmt = $conn->prepare("INSERT INTO tbl_appointments (patient_id, dentist_id, service_id, scheduled_datetime, duration_minutes, status, price) VALUES (?, ?, ?, ?, ?, 'Pending', ?)");
                                $appt_stmt->bind_param("iiisid", $patient_id, $dentist_id, $service_id, $scheduled_datetime, $duration, $service_price);
                                
                                if ($appt_stmt->execute()) {
                                    $appointment_id = $conn->insert_id;
                                    $appt_stmt->close();
                                    
                                    // Store appointment details in session for payment modal
                                    $_SESSION['walkin_pending_appointment'] = [
                                        'appointment_id' => $appointment_id,
                                        'service_name' => $service_details['service_name'] ?? 'Service',
                                        'service_price' => $service_price,
                                        'downpayment_amount' => $downpayment_amount,
                                        'scheduled_datetime' => $scheduled_datetime,
                                        'duration_minutes' => $duration,
                                        'patient_id' => $patient_id
                                    ];
                                    
                                    $conn->close();
                                    
                                    // Redirect to show payment modal
                                    header("Location: appointments.php?walkin_payment=1");
                                    exit();
                                } else {
                                    $errors[] = "Failed to create appointment: " . $conn->error;
                                }
                                $appt_stmt->close();
                                } // Close if (empty($errors)) for appointment creation
                            } else {
                                $errors[] = "No dentist available.";
                            }
                        } // Close if (empty($errors)) for prerequisites
                    } else {
                        $errors[] = "Service not found.";
                    }
                } // Close if ($patient_id > 0 && empty($errors))
                
                if (!empty($errors)) {
                    $action_message = implode("<br>", $errors);
                }
                if (isset($conn)) {
                $conn->close();
                }
            } catch (Exception $e) {
                $action_message = "Error: " . $e->getMessage();
            }
        } else {
            $action_message = implode("<br>", $errors);
        }
    }
    
    if (isset($_POST['action']) && isset($_POST['appointment_id'])) {
        $appointment_id = intval($_POST['appointment_id']);
        $action = $_POST['action'];
        
        try {
            $conn = getDBConnection();
            
            // Cancel action is disabled - no one can cancel appointments
            if ($action === 'cancel') {
                $action_message = "Cancelling appointments is not allowed. Please contact the clinic for assistance.";
            } elseif ($action === 'reschedule') {
                // Only patients can reschedule, and only if it's at least 1 day before
                if ($user_type === 'patient') {
                    // Get existing appointment date
                    $check_stmt = $conn->prepare("SELECT scheduled_datetime, patient_id FROM tbl_appointments WHERE appointment_id = ? LIMIT 1");
                    $check_stmt->bind_param("i", $appointment_id);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows === 1) {
                        $existing_appt = $check_result->fetch_assoc();
                        
                        // Verify patient owns this appointment
                        if ($existing_appt['patient_id'] == $_SESSION['user_id']) {
                            $existing_datetime = new DateTime($existing_appt['scheduled_datetime']);
                            $now = new DateTime();
                            $days_until_appointment = ($existing_datetime->getTimestamp() - $now->getTimestamp()) / 86400;
                            
                            if ($days_until_appointment < 1) {
                                $action_message = "You can only reschedule appointments at least 1 day before the scheduled date.";
                            } elseif (isset($_POST['new_datetime']) && isset($_POST['new_duration'])) {
                                $new_datetime = $_POST['new_datetime'];
                                $new_duration = intval($_POST['new_duration']);
                                
                                // Check if downpayment exists to maintain status
                                $dp_check = $conn->prepare("SELECT payment_status FROM tbl_appointment_downpayments WHERE appointment_id = ? AND payment_status = 'Completed' LIMIT 1");
                                $dp_check->bind_param("i", $appointment_id);
                                $dp_check->execute();
                                $dp_result = $dp_check->get_result();
                                $has_paid = $dp_result->num_rows > 0;
                                $dp_check->close();
                                
                                $new_status = $has_paid ? 'Scheduled' : 'Pending';
                                
                                $update_stmt = $conn->prepare("UPDATE tbl_appointments SET scheduled_datetime = ?, duration_minutes = ?, status = ? WHERE appointment_id = ?");
                                $update_stmt->bind_param("sisi", $new_datetime, $new_duration, $new_status, $appointment_id);
                                if ($update_stmt->execute()) {
                                    $action_message = "Appointment rescheduled successfully.";
                                } else {
                                    $action_message = "Failed to reschedule appointment.";
                                }
                                $update_stmt->close();
                            }
                        } else {
                            $action_message = "You are not authorized to reschedule this appointment.";
                        }
                    } else {
                        $action_message = "Appointment not found.";
                    }
                    $check_stmt->close();
                } else {
                    $action_message = "Only patients can reschedule appointments.";
                }
            }
            
            $conn->close();
        } catch (Exception $e) {
            $action_message = "Error: " . $e->getMessage();
        }
    }
    }
    
    // Get current month/year from URL or use current date
    $current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
    $current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
    
    // Validate month/year
    if ($current_month < 1 || $current_month > 12) $current_month = date('n');
    if ($current_year < 2020 || $current_year > 2100) $current_year = date('Y');
    
    // Fetch appointments - all for admins, patient-specific for patients
    $all_appointments = [];

try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }
    
    // Build query - check which columns exist first
    $columns_to_select = "a.appointment_id, a.scheduled_datetime, a.duration_minutes, a.status";
    
    // Try to add optional columns if they exist
    $column_check = $conn->query("SHOW COLUMNS FROM tbl_appointments LIKE 'price'");
    if ($column_check && $column_check->num_rows > 0) {
        $columns_to_select .= ", a.price";
    }
    
    $column_check = $conn->query("SHOW COLUMNS FROM tbl_appointments LIKE 'service_type'");
    if ($column_check && $column_check->num_rows > 0) {
        $columns_to_select .= ", a.service_type";
    }
    
    $column_check = $conn->query("SHOW COLUMNS FROM tbl_appointments LIKE 'service_id'");
    if ($column_check && $column_check->num_rows > 0) {
        $columns_to_select .= ", a.service_id";
    }
    
    // Check if tbl_service_catalog exists
    $service_catalog_exists = false;
    $service_catalog_check = $conn->query("SHOW TABLES LIKE 'tbl_service_catalog'");
    if ($service_catalog_check && $service_catalog_check->num_rows > 0) {
        $service_catalog_exists = true;
    }
    
    if ($is_admin || $is_receptionist) {
        // Admin/Receptionist sees ALL appointments, Dentist sees only their appointments
        if ($is_dentist && $dentist_id) {
            // Dentist sees only appointments assigned to them
        if ($service_catalog_exists) {
                $query = "
                    SELECT 
                        $columns_to_select,
                        s.first_name as dentist_first_name,
                        s.last_name as dentist_last_name,
                        p.first_name as patient_first_name,
                        p.last_name as patient_last_name,
                        p.patient_id,
                        sv.service_name as catalog_service_name
                    FROM tbl_appointments a
                    INNER JOIN tbl_staff s ON a.dentist_id = s.staff_id
                    INNER JOIN tbl_patients p ON a.patient_id = p.patient_id
                    LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
                    WHERE a.dentist_id = ?
                    ORDER BY a.scheduled_datetime ASC
                ";
            } else {
                $query = "
                    SELECT 
                        $columns_to_select,
                        s.first_name as dentist_first_name,
                        s.last_name as dentist_last_name,
                        p.first_name as patient_first_name,
                        p.last_name as patient_last_name,
                        p.patient_id
                    FROM tbl_appointments a
                    INNER JOIN tbl_staff s ON a.dentist_id = s.staff_id
                    INNER JOIN tbl_patients p ON a.patient_id = p.patient_id
                    WHERE a.dentist_id = ?
                    ORDER BY a.scheduled_datetime ASC
                ";
            }
            
            $appointment_stmt = $conn->prepare($query);
            
            if (!$appointment_stmt) {
                throw new Exception("Failed to prepare dentist appointment query: " . ($conn->error ?? 'Unknown error'));
            }
            
            $appointment_stmt->bind_param("i", $dentist_id);
            $appointment_stmt->execute();
            
            if ($appointment_stmt->error) {
                throw new Exception("Failed to execute dentist appointment query: " . $appointment_stmt->error);
            }
        } else {
            // Admin/Receptionist sees ALL appointments
            if ($service_catalog_exists) {
            $query = "
                SELECT 
                    $columns_to_select,
                    s.first_name as dentist_first_name,
                    s.last_name as dentist_last_name,
                    p.first_name as patient_first_name,
                    p.last_name as patient_last_name,
                    p.patient_id,
                    sv.service_name as catalog_service_name
                FROM tbl_appointments a
                INNER JOIN tbl_staff s ON a.dentist_id = s.staff_id
                INNER JOIN tbl_patients p ON a.patient_id = p.patient_id
                LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
                ORDER BY a.scheduled_datetime ASC
            ";
        } else {
            $query = "
                SELECT 
                    $columns_to_select,
                    s.first_name as dentist_first_name,
                    s.last_name as dentist_last_name,
                    p.first_name as patient_first_name,
                    p.last_name as patient_last_name,
                    p.patient_id
                FROM tbl_appointments a
                INNER JOIN tbl_staff s ON a.dentist_id = s.staff_id
                INNER JOIN tbl_patients p ON a.patient_id = p.patient_id
                ORDER BY a.scheduled_datetime ASC
            ";
        }
        
        $appointment_stmt = $conn->prepare($query);
        
        if (!$appointment_stmt) {
            throw new Exception("Failed to prepare admin appointment query: " . ($conn->error ?? 'Unknown error'));
        }
        
        $appointment_stmt->execute();
        
        if ($appointment_stmt->error) {
            throw new Exception("Failed to execute admin appointment query: " . $appointment_stmt->error);
            }
        }
    } else {
        // Patient sees only their appointments
        // Filter out Pending appointments without completed downpayment to prevent bypass
        $table_check = $conn->query("SHOW TABLES LIKE 'tbl_payments'");
        $has_payment_type = false;
        if ($table_check && $table_check->num_rows > 0) {
            $col_check = $conn->query("SHOW COLUMNS FROM tbl_payments LIKE 'payment_type'");
            $has_payment_type = $col_check && $col_check->num_rows > 0;
        }
        
        if ($service_catalog_exists) {
            if ($has_payment_type) {
                // Check if tbl_invoices exists
                $invoice_table_check = $conn->query("SHOW TABLES LIKE 'tbl_invoices'");
                $has_invoices = $invoice_table_check && $invoice_table_check->num_rows > 0;
                
                if ($has_invoices) {
                    $query = "
                        SELECT 
                            $columns_to_select,
                            s.first_name as dentist_first_name,
                            s.last_name as dentist_last_name,
                            sv.service_name as catalog_service_name
                        FROM tbl_appointments a
                        INNER JOIN tbl_staff s ON a.dentist_id = s.staff_id
                        LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
                        LEFT JOIN tbl_payments p ON a.appointment_id = p.appointment_id 
                            AND p.payment_type = 'Downpayment' 
                            AND p.payment_status = 'Completed'
                        LEFT JOIN tbl_invoices i ON a.appointment_id = i.appointment_id
                            AND i.payment_status = 'Paid'
                        WHERE a.patient_id = ?
                        AND (
                            a.status = 'Scheduled'
                            OR a.status = 'Completed'
                            OR (a.status = 'Pending' AND p.payment_id IS NOT NULL)
                            OR i.invoice_id IS NOT NULL
                        )
                        ORDER BY a.scheduled_datetime ASC
                    ";
                } else {
                    $query = "
                        SELECT 
                            $columns_to_select,
                            s.first_name as dentist_first_name,
                            s.last_name as dentist_last_name,
                            sv.service_name as catalog_service_name
                        FROM tbl_appointments a
                        INNER JOIN tbl_staff s ON a.dentist_id = s.staff_id
                        LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
                        LEFT JOIN tbl_payments p ON a.appointment_id = p.appointment_id 
                            AND p.payment_type = 'Downpayment' 
                            AND p.payment_status = 'Completed'
                        WHERE a.patient_id = ?
                        AND (
                            a.status = 'Scheduled'
                            OR a.status = 'Completed'
                            OR (a.status = 'Pending' AND p.payment_id IS NOT NULL)
                        )
                        ORDER BY a.scheduled_datetime ASC
                    ";
                }
            } else {
                $query = "
                    SELECT 
                        $columns_to_select,
                        s.first_name as dentist_first_name,
                        s.last_name as dentist_last_name,
                        sv.service_name as catalog_service_name
                    FROM tbl_appointments a
                    INNER JOIN tbl_staff s ON a.dentist_id = s.staff_id
                    LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
                    LEFT JOIN tbl_appointment_downpayments dp ON a.appointment_id = dp.appointment_id 
                        AND dp.payment_status = 'Completed'
                    WHERE a.patient_id = ?
                    AND (
                        a.status = 'Scheduled'
                        OR a.status = 'Completed'
                        OR (a.status = 'Pending' AND dp.downpayment_id IS NOT NULL)
                    )
                    ORDER BY a.scheduled_datetime ASC
                ";
            }
        } else {
            if ($has_payment_type) {
                // Check if tbl_invoices exists
                $invoice_table_check = $conn->query("SHOW TABLES LIKE 'tbl_invoices'");
                $has_invoices = $invoice_table_check && $invoice_table_check->num_rows > 0;
                
                if ($has_invoices) {
                    $query = "
                        SELECT 
                            $columns_to_select,
                            s.first_name as dentist_first_name,
                            s.last_name as dentist_last_name
                        FROM tbl_appointments a
                        INNER JOIN tbl_staff s ON a.dentist_id = s.staff_id
                        LEFT JOIN tbl_payments p ON a.appointment_id = p.appointment_id 
                            AND p.payment_type = 'Downpayment' 
                            AND p.payment_status = 'Completed'
                        LEFT JOIN tbl_invoices i ON a.appointment_id = i.appointment_id
                            AND i.payment_status = 'Paid'
                        WHERE a.patient_id = ?
                        AND (
                            a.status = 'Scheduled'
                            OR a.status = 'Completed'
                            OR (a.status = 'Pending' AND p.payment_id IS NOT NULL)
                            OR i.invoice_id IS NOT NULL
                        )
                        ORDER BY a.scheduled_datetime ASC
                    ";
                } else {
                    $query = "
                        SELECT 
                            $columns_to_select,
                            s.first_name as dentist_first_name,
                            s.last_name as dentist_last_name
                        FROM tbl_appointments a
                        INNER JOIN tbl_staff s ON a.dentist_id = s.staff_id
                        LEFT JOIN tbl_payments p ON a.appointment_id = p.appointment_id 
                            AND p.payment_type = 'Downpayment' 
                            AND p.payment_status = 'Completed'
                        WHERE a.patient_id = ?
                        AND (
                            a.status = 'Scheduled'
                            OR a.status = 'Completed'
                            OR (a.status = 'Pending' AND p.payment_id IS NOT NULL)
                        )
                        ORDER BY a.scheduled_datetime ASC
                    ";
                }
            } else {
                $query = "
        SELECT 
                        $columns_to_select,
            s.first_name as dentist_first_name,
            s.last_name as dentist_last_name
        FROM tbl_appointments a
                    INNER JOIN tbl_staff s ON a.dentist_id = s.staff_id
                    LEFT JOIN tbl_appointment_downpayments dp ON a.appointment_id = dp.appointment_id 
                        AND dp.payment_status = 'Completed'
        WHERE a.patient_id = ?
                    AND (
                        a.status = 'Scheduled'
                        OR a.status = 'Completed'
                        OR (a.status = 'Pending' AND dp.downpayment_id IS NOT NULL)
                    )
        ORDER BY a.scheduled_datetime ASC
                ";
            }
        }
        
        $appointment_stmt = $conn->prepare($query);
        
        if (!$appointment_stmt) {
            throw new Exception("Failed to prepare patient appointment query: " . ($conn->error ?? 'Unknown error'));
        }
        
    $appointment_stmt->bind_param("i", $user_info['patient_id']);
    $appointment_stmt->execute();
        
        if ($appointment_stmt->error) {
            throw new Exception("Failed to execute patient appointment query: " . $appointment_stmt->error);
        }
    }
    
    // Get result
    $appointment_result = $appointment_stmt->get_result();
    
    if ($appointment_result) {
    while ($row = $appointment_result->fetch_assoc()) {
            // Set service_name - prioritize catalog service name, then service_type, then default
            if (isset($row['catalog_service_name']) && !empty($row['catalog_service_name'])) {
                $row['service_name'] = $row['catalog_service_name'];
            } elseif (isset($row['service_type']) && !empty($row['service_type'])) {
                $row['service_name'] = $row['service_type'];
            } else {
                $row['service_name'] = 'Appointment';
            }
            
            // Remove catalog_service_name from row to avoid confusion
            if (isset($row['catalog_service_name'])) {
                unset($row['catalog_service_name']);
            }
            
            // Ensure price is set
            if (!isset($row['price'])) {
                $row['price'] = null;
            }
            
        $all_appointments[] = $row;
        }
    }
    $appointment_stmt->close();
    
    // Organize appointments by date for calendar display - exclude cancelled appointments and Pending without downpayment
    $appointments_by_date = [];
    foreach ($all_appointments as $apt) {
        // Include scheduled, confirmed, completed, and pending (with downpayment) appointments in calendar
        // Pending appointments without downpayment are already filtered out in the query
        if (in_array(strtolower($apt['status']), ['scheduled', 'confirmed', 'completed', 'pending'])) {
        $date = date('Y-m-d', strtotime($apt['scheduled_datetime']));
        if (!isset($appointments_by_date[$date])) {
            $appointments_by_date[$date] = [];
        }
        $appointments_by_date[$date][] = $apt;
        }
    }
    
    // Get next appointment
    $next_appointment = null;
    $today = date('Y-m-d H:i:s');
    foreach ($all_appointments as $apt) {
        if ($apt['scheduled_datetime'] >= $today && in_array($apt['status'], ['Pending', 'Scheduled', 'Confirmed'])) {
            $next_appointment = $apt;
            break;
        }
    }
    
    // Count total visits (completed appointments)
    $total_visits = 0;
    foreach ($all_appointments as $apt) {
        if (strtolower($apt['status']) === 'completed') {
            $total_visits++;
        }
    }
    
    // Fetch dentists list for selection - EXACT COPY from book_appointment.php (OUTSIDE conditional)
    $dentists_list = [];
    $dentist_services = []; // Map dentist_id => array of service_ids they can perform
    
    // DEBUG: Log that we're attempting to fetch dentists
    error_log("DEBUG appointments.php: Starting dentist fetch...");
    
    try {
        $dentist_stmt = $conn->prepare("SELECT staff_id, first_name, last_name FROM tbl_staff WHERE role IN ('Dentist', 'Admin') ORDER BY last_name, first_name");
        if ($dentist_stmt) {
            if ($dentist_stmt->execute()) {
                $dentist_result = $dentist_stmt->get_result();
                $count = 0;
                while ($row = $dentist_result->fetch_assoc()) {
                    $dentists_list[] = $row;
                    $dentist_services[$row['staff_id']] = []; // Initialize empty array
                    $count++;
                }
                error_log("DEBUG appointments.php: Successfully fetched {$count} dentists");
                $dentist_stmt->close();
            } else {
                error_log("ERROR appointments.php: Failed to execute dentist query: " . $dentist_stmt->error);
            }
        } else {
            error_log("ERROR appointments.php: Failed to prepare dentist query: " . $conn->error);
        }
    } catch (Exception $e) {
        error_log("CRITICAL ERROR appointments.php fetching dentists: " . $e->getMessage());
    }
    
    // DEBUG: Log final count
    error_log("DEBUG appointments.php: Final dentists_list count: " . count($dentists_list));
    
    // Fetch dentist-service relationships (specializations)
    // Check multiple possible table structures:
    // 1. tbl_service_specializations + tbl_staff_specializations (new system)
    // 2. tbl_dentist_services (old system)
    $spec_table_check = $conn->query("SHOW TABLES LIKE 'tbl_service_specializations'");
    $staff_spec_table_check = $conn->query("SHOW TABLES LIKE 'tbl_staff_specializations'");
    
    if ($spec_table_check && $spec_table_check->num_rows > 0 && 
        $staff_spec_table_check && $staff_spec_table_check->num_rows > 0) {
        // New system: Use specializations
        // Get all staff specializations
        $staff_spec_stmt = $conn->prepare("SELECT staff_id, specialization_id FROM tbl_staff_specializations");
        if ($staff_spec_stmt) {
            $staff_spec_stmt->execute();
            $staff_spec_result = $staff_spec_stmt->get_result();
            $staff_specializations = [];
            while ($row = $staff_spec_result->fetch_assoc()) {
                $staff_id = $row['staff_id'];
                $spec_id = $row['specialization_id'];
                if (!isset($staff_specializations[$staff_id])) {
                    $staff_specializations[$staff_id] = [];
                }
                $staff_specializations[$staff_id][] = $spec_id;
            }
            $staff_spec_stmt->close();
            
            // Get all service specializations
            $service_spec_stmt = $conn->prepare("SELECT service_id, specialization_id FROM tbl_service_specializations");
            if ($service_spec_stmt) {
                $service_spec_stmt->execute();
                $service_spec_result = $service_spec_stmt->get_result();
                $service_specializations = [];
                while ($row = $service_spec_result->fetch_assoc()) {
                    $service_id = $row['service_id'];
                    $spec_id = $row['specialization_id'];
                    if (!isset($service_specializations[$service_id])) {
                        $service_specializations[$service_id] = [];
                    }
                    $service_specializations[$service_id][] = $spec_id;
                }
                $service_spec_stmt->close();
                
                // Get all active services to check
                $all_services_check = [];
                $all_services_stmt = $conn->prepare("SELECT service_id FROM tbl_service_catalog WHERE is_active = 1");
                if ($all_services_stmt) {
                    $all_services_stmt->execute();
                    $all_services_result = $all_services_stmt->get_result();
                    while ($row = $all_services_result->fetch_assoc()) {
                        $all_services_check[] = $row['service_id'];
                    }
                    $all_services_stmt->close();
                }
                
                // Build dentist_services array: for each dentist, find which services they can perform
                foreach ($staff_specializations as $staff_id => $staff_specs) {
                    $dentist_services[$staff_id] = [];
                    foreach ($all_services_check as $service_id) {
                        $service_specs = isset($service_specializations[$service_id]) ? $service_specializations[$service_id] : [];
                        
                        // If service has no required specializations, any dentist can perform it
                        if (empty($service_specs)) {
                            $dentist_services[$staff_id][] = $service_id;
                        } else {
                            // Service has required specializations - check if dentist has at least one matching
                            $can_perform = false;
                            foreach ($service_specs as $required_spec) {
                                if (in_array($required_spec, $staff_specs)) {
                                    $can_perform = true;
                                    break;
                                }
                            }
                            if ($can_perform) {
                                $dentist_services[$staff_id][] = $service_id;
                            }
                        }
                    }
                }
                
                // Also handle dentists that might not have any specializations
                foreach ($dentists_list as $dentist) {
                    $staff_id = $dentist['staff_id'];
                    if (!isset($dentist_services[$staff_id])) {
                        // Dentist has no specializations - can only perform services with no required specializations
                        $dentist_services[$staff_id] = [];
                        foreach ($all_services_check as $service_id) {
                            $service_specs = isset($service_specializations[$service_id]) ? $service_specializations[$service_id] : [];
                            if (empty($service_specs)) {
                                $dentist_services[$staff_id][] = $service_id;
                            }
                        }
                    }
                }
            }
        }
    } else {
        // Fallback: Check for old tbl_dentist_services table
        $table_check = $conn->query("SHOW TABLES LIKE 'tbl_dentist_services'");
        if ($table_check && $table_check->num_rows > 0) {
            $ds_stmt = $conn->prepare("SELECT dentist_id, service_id FROM tbl_dentist_services WHERE is_active = 1");
            if ($ds_stmt) {
                $ds_stmt->execute();
                $ds_result = $ds_stmt->get_result();
                while ($ds_row = $ds_result->fetch_assoc()) {
                    $dentist_id = $ds_row['dentist_id'];
                    $service_id = $ds_row['service_id'];
                    if (!isset($dentist_services[$dentist_id])) {
                        $dentist_services[$dentist_id] = [];
                    }
                    $dentist_services[$dentist_id][] = $service_id;
                }
                $ds_stmt->close();
            }
        } else {
            // If no specialization table exists, all dentists can perform all services (backward compatibility)
            foreach ($dentists_list as $dentist) {
                $dentist_services[$dentist['staff_id']] = 'all'; // Special marker for "all services"
            }
        }
    }
    
    // Fetch existing patients and services for walk-in booking (Staff/Admin only)
    $existing_patients = [];
    $services = [];
    $service_prerequisites = [];
    // Fetch for all staff roles (Admin, Manager, Dentist, Receptionist)
    // $is_admin already includes Receptionist, Dentist, Manager, and Admin, but make it explicit
    // Ensure Receptionist can access walk-in booking
    if ($is_admin || $is_receptionist || ($user_type === 'staff' && $user_role === 'Receptionist')) {
        try {
            // Fetch all patients
            $patient_stmt = $conn->prepare("SELECT patient_id, first_name, last_name, email FROM tbl_patients ORDER BY last_name, first_name");
            $patient_stmt->execute();
            $patient_result = $patient_stmt->get_result();
            while ($row = $patient_result->fetch_assoc()) {
                $existing_patients[] = $row;
            }
            $patient_stmt->close();
            
            // Fetch active services
            $service_stmt = $conn->prepare("SELECT service_id, service_name, base_price, default_duration_minutes FROM tbl_service_catalog WHERE is_active = 1 ORDER BY service_name");
            $service_stmt->execute();
            $service_result = $service_stmt->get_result();
            while ($row = $service_result->fetch_assoc()) {
                $services[] = $row;
            }
            $service_stmt->close();
            
            // Fetch service prerequisites
            $prereq_table_check = $conn->query("SHOW TABLES LIKE 'tbl_service_prerequisites'");
            if ($prereq_table_check && $prereq_table_check->num_rows > 0) {
                $prereq_stmt = $conn->prepare("
                    SELECT sp.service_id, 
                           sp.required_service_id,
                           sc.service_name as required_service_name,
                           COALESCE(sp.validity_period_days, 30) as validity_period_days,
                           sp.is_mandatory
                    FROM tbl_service_prerequisites sp
                    INNER JOIN tbl_service_catalog sc ON sp.required_service_id = sc.service_id
                    WHERE sp.is_mandatory = 1
                ");
                if ($prereq_stmt) {
                    $prereq_stmt->execute();
                    $prereq_result = $prereq_stmt->get_result();
                    while ($prereq_row = $prereq_result->fetch_assoc()) {
                        $service_id = $prereq_row['service_id'];
                        if (!isset($service_prerequisites[$service_id])) {
                            $service_prerequisites[$service_id] = [];
                        }
                        $service_prerequisites[$service_id][] = [
                            'required_service_id' => $prereq_row['required_service_id'],
                            'required_service_name' => $prereq_row['required_service_name'],
                            'validity_period_days' => $prereq_row['validity_period_days']
                        ];
                    }
                    $prereq_stmt->close();
                }
            }
            
            // Fetch services with maintenance plans
            $services_with_maintenance = [];
            $service_maintenance_stmt = $conn->prepare("
                SELECT service_id
                FROM tbl_service_catalog
                WHERE maintenance_period_months IS NOT NULL
                AND maintenance_interval IS NOT NULL
            ");
            if ($service_maintenance_stmt) {
                $service_maintenance_stmt->execute();
                $service_maintenance_result = $service_maintenance_stmt->get_result();
                while ($svc_maint_row = $service_maintenance_result->fetch_assoc()) {
                    $services_with_maintenance[] = intval($svc_maint_row['service_id']);
                }
                $service_maintenance_stmt->close();
            }
        } catch (Exception $e) {
            error_log("Error fetching patients/services: " . $e->getMessage());
        }
    }
    
    // Get upcoming appointments for sidebar - only show 'Scheduled' status
    $upcoming_appointments = [];
    foreach ($all_appointments as $apt) {
        if ($apt['scheduled_datetime'] >= $today && strtolower($apt['status']) === 'scheduled') {
            $upcoming_appointments[] = $apt;
        }
    }
    usort($upcoming_appointments, function($a, $b) {
        return strtotime($a['scheduled_datetime']) - strtotime($b['scheduled_datetime']);
    });
    
    // Get completed appointments for sidebar
    $completed_appointments = [];
    foreach ($all_appointments as $apt) {
        if (strtolower($apt['status']) === 'completed') {
            $completed_appointments[] = $apt;
        }
    }
    usort($completed_appointments, function($a, $b) {
        return strtotime($b['scheduled_datetime']) - strtotime($a['scheduled_datetime']);
    });
    
    // Get cancelled appointments for sidebar
    $cancelled_appointments = [];
    foreach ($all_appointments as $apt) {
        if (strtolower($apt['status']) === 'cancelled') {
            $cancelled_appointments[] = $apt;
        }
    }
    usort($cancelled_appointments, function($a, $b) {
        return strtotime($b['scheduled_datetime']) - strtotime($a['scheduled_datetime']);
    });
    
    // Fetch closure dates (for all users to display in calendar)
    $closure_dates = [];
    $closure_dates_map = []; // Map for quick lookup: date => reason
    try {
        $closure_stmt = $conn->prepare("SELECT closure_id, closure_date, reason FROM tbl_clinic_closures WHERE closure_date >= CURDATE() ORDER BY closure_date ASC");
        if ($closure_stmt) {
            $closure_stmt->execute();
            $closure_result = $closure_stmt->get_result();
            while ($row = $closure_result->fetch_assoc()) {
                $closure_dates[] = $row;
                $closure_dates_map[$row['closure_date']] = $row['reason'];
            }
            $closure_stmt->close();
        }
    } catch (Exception $e) {
        // Tables might not exist yet, ignore error
        error_log("Error fetching closure dates: " . $e->getMessage());
    }
    
    // Fetch dentist schedules (Admin only)
    $dentist_schedules = [];
    $dentists_list = [];
    
    if ($is_admin_only) {
        try {
            // Fetch dentists
            $dentist_stmt = $conn->prepare("SELECT staff_id, first_name, last_name FROM tbl_staff WHERE role = 'Dentist' ORDER BY last_name, first_name");
            if ($dentist_stmt) {
                $dentist_stmt->execute();
                $dentist_result = $dentist_stmt->get_result();
                while ($row = $dentist_result->fetch_assoc()) {
                    $dentists_list[] = $row;
                }
                $dentist_stmt->close();
            }
            
            // Fetch dentist schedules
            if (!empty($dentists_list)) {
                $schedule_stmt = $conn->prepare("SELECT schedule_id, dentist_id, day_of_week, start_time, end_time FROM tbl_dentist_schedules WHERE is_active = 1 ORDER BY dentist_id, day_of_week");
                if ($schedule_stmt) {
                    $schedule_stmt->execute();
                    $schedule_result = $schedule_stmt->get_result();
                    while ($row = $schedule_result->fetch_assoc()) {
                        if (!isset($dentist_schedules[$row['dentist_id']])) {
                            $dentist_schedules[$row['dentist_id']] = [];
                        }
                        $dentist_schedules[$row['dentist_id']][] = $row;
                    }
                    $schedule_stmt->close();
                }
            }
        } catch (Exception $e) {
            // Tables might not exist yet, ignore error
            error_log("Error fetching dentist schedules: " . $e->getMessage());
        }
    }
    
    $conn->close();
} catch (Exception $e) {
    // Log error for debugging
    error_log("Appointments page error: " . $e->getMessage());
    
    // Display error message for debugging (remove in production)
    if (empty($action_message)) {
        $action_message = "Error loading appointments: " . htmlspecialchars($e->getMessage());
    }
    
    $all_appointments = [];
    $appointments_by_date = [];
    $next_appointment = null;
    $total_visits = 0;
    $upcoming_appointments = [];
    $completed_appointments = [];
    $cancelled_appointments = [];
    // Initialize variables that might be used later
    if (!isset($current_month)) {
        $current_month = date('n');
    }
    if (!isset($current_year)) {
        $current_year = date('Y');
    }
}

// Check if user_info was set, if not redirect to login
if (!$user_info) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Initialize variables that might not be set if exception occurred
if (!isset($current_month)) {
    $current_month = date('n');
}
if (!isset($current_year)) {
    $current_year = date('Y');
}
if (!isset($filter)) {
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';
}

// Set user variables
$user_name = $user_info['first_name'] . ' ' . $user_info['last_name'];
$user_first_name = $user_info['first_name'];
$user_id = $is_admin ? $user_info['staff_id'] : $user_info['patient_id'];

// Generate calendar
$first_day = mktime(0, 0, 0, $current_month, 1, $current_year);
$days_in_month = date('t', $first_day);
$day_of_week = date('w', $first_day); // 0 = Sunday, 6 = Saturday
$month_name = date('F', $first_day);

// Calculate previous and next month
$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $current_month + 1;
$next_year = $current_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Get filter from URL
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';
?>
<?php
$page_title = 'Appointments';
include ROOT_PATH . '/views/layout/header.php';
?>
<div class="flex min-h-screen">
    <?php include ROOT_PATH . '/views/layout/sidebar_staff.php'; ?>
    <div class="flex-1 flex flex-col lg:ml-64">
        <?php include ROOT_PATH . '/views/layout/topbar.php'; ?>
<main class="flex-grow w-full max-w-7xl mx-auto px-4 md:px-8 py-8">
<?php 
// Check for action message from session
if (isset($_SESSION['action_message'])) {
    $action_message = $_SESSION['action_message'];
    unset($_SESSION['action_message']);
}
if (isset($action_message) && !empty($action_message)): ?>
<div class="max-w-6xl mx-auto mb-4">
<div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-400">
<p class="text-sm font-medium"><?php echo htmlspecialchars($action_message); ?></p>
</div>
</div>
<?php endif; ?>
<?php if ($user_type === 'patient'): ?>
<div class="flex flex-col gap-3 mb-6">
    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
        <div class="flex items-start gap-3">
            <span class="material-symbols-outlined text-blue-600 dark:text-blue-400 text-xl">info</span>
            <div class="flex-1">
                <p class="text-sm font-semibold text-blue-800 dark:text-blue-300 mb-1">Rescheduling Information</p>
                <p class="text-xs text-blue-700 dark:text-blue-400">You can reschedule your appointment by clicking on it in the calendar below. Rescheduling must be done at least 1 day before your scheduled appointment date.</p>
            </div>
        </div>
    </div>
    <div class="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
        <div class="flex items-start gap-3">
            <span class="material-symbols-outlined text-red-600 dark:text-red-400 text-xl">warning</span>
            <div class="flex-1">
                <p class="text-sm font-semibold text-red-800 dark:text-red-300 mb-1">Important: Missed Appointments</p>
                <p class="text-xs text-red-700 dark:text-red-400">If an appointment is not attended, it will be marked as MISSED. Missed appointments are non-negotiable, non-refundable, and cannot be rescheduled.</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<div class="flex flex-col lg:flex-row gap-6 mb-8 items-start lg:items-end justify-between">
<div class="flex flex-col gap-2 flex-1">
<h1 class="text-[#0d131b] dark:text-white text-3xl font-black leading-tight tracking-[-0.033em]"><?php echo $is_admin ? 'All Appointments' : 'My Calendar'; ?></h1>
<p class="text-[#4c6c9a] dark:text-slate-400 text-base font-normal"><?php echo $is_admin ? 'View and manage all patient appointments' : 'Manage your upcoming visits and view dental history'; ?></p>
</div>
<div class="flex gap-3 flex-wrap">
<?php if ($is_admin_only): ?>
<button onclick="openClosureModal()" class="cursor-pointer items-center justify-center overflow-hidden rounded-xl h-12 px-4 bg-amber-500 hover:bg-amber-600 transition-colors text-white text-base font-bold tracking-[0.015em] shadow-sm flex whitespace-nowrap">
<span class="material-symbols-outlined mr-2">event_busy</span>
<span>Closure Dates</span>
</button>
<button onclick="openDentistScheduleModal()" class="cursor-pointer items-center justify-center overflow-hidden rounded-xl h-12 px-4 bg-blue-500 hover:bg-blue-600 transition-colors text-white text-base font-bold tracking-[0.015em] shadow-sm flex whitespace-nowrap">
<span class="material-symbols-outlined mr-2">schedule</span>
<span>Dentist Schedules</span>
</button>
<?php endif; ?>
<?php if (($is_admin && !$is_manager && !$is_dentist) || $is_receptionist): ?>
<button onclick="openWalkInModal()" class="cursor-pointer items-center justify-center overflow-hidden rounded-xl h-12 px-4 bg-primary hover:bg-primary-dark transition-colors text-white text-base font-bold tracking-[0.015em] shadow-sm flex whitespace-nowrap">
<span class="material-symbols-outlined mr-2">person_add</span>
<span>Walk-In Booking</span>
</button>
<?php endif; ?>
<?php if ($user_type === 'patient'): ?>
<a href="book_appointment.php" class="cursor-pointer items-center justify-center overflow-hidden rounded-xl h-12 px-4 bg-[#e7ecf3] hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 transition-colors text-[#0d131b] dark:text-white text-base font-bold tracking-[0.015em] border border-[#cfd9e7] dark:border-slate-600 shadow-sm flex whitespace-nowrap">
<span class="material-symbols-outlined mr-2">add</span>
<span>New Booking</span>
</a>
<?php endif; ?>
</div>
<div class="flex flex-wrap gap-4 w-full lg:w-auto">
<div class="flex flex-1 lg:flex-none min-w-[180px] flex-col gap-1 rounded-xl p-4 bg-white dark:bg-[#1a2634] border border-[#cfd9e7] dark:border-slate-700 shadow-sm">
<div class="flex items-center gap-2 text-[#4c6c9a] dark:text-slate-400">
<span class="material-symbols-outlined text-primary text-xl">event_upcoming</span>
<p class="text-sm font-medium">Next Appointment</p>
</div>
<?php if ($next_appointment): 
    $next_date = new DateTime($next_appointment['scheduled_datetime']);
?>
<p class="text-[#0d131b] dark:text-white text-xl font-bold"><?php echo $next_date->format('M d, h:i A'); ?></p>
<?php else: ?>
<p class="text-[#0d131b] dark:text-white text-xl font-bold">No upcoming</p>
<?php endif; ?>
</div>
<div class="flex flex-1 lg:flex-none min-w-[140px] flex-col gap-1 rounded-xl p-4 bg-white dark:bg-[#1a2634] border border-[#cfd9e7] dark:border-slate-700 shadow-sm">
<div class="flex items-center gap-2 text-[#4c6c9a] dark:text-slate-400">
<span class="material-symbols-outlined text-ivy-green text-xl">history</span>
<p class="text-sm font-medium">Total Visits</p>
</div>
<p class="text-[#0d131b] dark:text-white text-xl font-bold"><?php echo $total_visits; ?></p>
</div>
</div>
</div>
<div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
<div class="xl:col-span-8 flex flex-col gap-6">
<div class="flex items-center justify-between bg-white dark:bg-[#1a2634] p-4 rounded-t-xl border-b border-[#e7ecf3] dark:border-slate-700">
<div class="flex items-center gap-4">
<h2 class="text-xl font-bold text-[#0d131b] dark:text-white flex items-center gap-2">
                                <?php echo $month_name . ' ' . $current_year; ?>
                            </h2>
<div class="flex gap-1">
<a href="appointments.php?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>&filter=<?php echo $filter; ?>" class="size-8 flex items-center justify-center hover:bg-slate-100 dark:hover:bg-slate-700 rounded-full transition-colors">
<span class="material-symbols-outlined text-sm">chevron_left</span>
</a>
<a href="appointments.php?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>&filter=<?php echo $filter; ?>" class="size-8 flex items-center justify-center hover:bg-slate-100 dark:hover:bg-slate-700 rounded-full transition-colors">
<span class="material-symbols-outlined text-sm">chevron_right</span>
</a>
</div>
</div>
<div class="hidden sm:flex bg-[#f6f7f8] dark:bg-slate-800 rounded-lg p-1">
<button class="px-3 py-1 bg-white dark:bg-slate-600 shadow-sm rounded-md text-xs font-bold text-[#0d131b] dark:text-white">Month</button>
</div>
</div>
<div class="bg-white dark:bg-[#1a2634] rounded-b-xl border border-t-0 border-[#e7ecf3] dark:border-slate-700 overflow-hidden shadow-sm">
<div class="grid grid-cols-7 border-b border-[#e7ecf3] dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 sticky top-0 z-10">
<div class="py-3 text-center text-xs font-semibold text-[#4c6c9a] uppercase tracking-wider">Sun</div>
<div class="py-3 text-center text-xs font-semibold text-[#4c6c9a] uppercase tracking-wider">Mon</div>
<div class="py-3 text-center text-xs font-semibold text-[#4c6c9a] uppercase tracking-wider">Tue</div>
<div class="py-3 text-center text-xs font-semibold text-[#4c6c9a] uppercase tracking-wider">Wed</div>
<div class="py-3 text-center text-xs font-semibold text-[#4c6c9a] uppercase tracking-wider">Thu</div>
<div class="py-3 text-center text-xs font-semibold text-[#4c6c9a] uppercase tracking-wider">Fri</div>
<div class="py-3 text-center text-xs font-semibold text-[#4c6c9a] uppercase tracking-wider">Sat</div>
</div>
<div class="overflow-y-auto max-h-[600px]">
<div class="grid grid-cols-7 auto-rows-[minmax(100px,1fr)] text-sm">
<?php
// Days from previous month
$prev_month_days = date('t', mktime(0, 0, 0, $prev_month, 1, $prev_year));
$start_day = $prev_month_days - $day_of_week + 1;
for ($i = $start_day; $i <= $prev_month_days; $i++) {
    echo '<div class="bg-slate-50/50 dark:bg-slate-800/20 border-r border-b border-[#e7ecf3] dark:border-slate-700 p-2 text-slate-300 dark:text-slate-600">' . $i . '</div>';
}

// Current month days
$today_date = date('Y-m-d');
for ($day = 1; $day <= $days_in_month; $day++) {
    $current_date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
    $is_today = ($current_date === $today_date);
    $has_appointments = isset($appointments_by_date[$current_date]);
    $day_appointments = $has_appointments ? $appointments_by_date[$current_date] : [];
    $is_closed = isset($closure_dates_map[$current_date]);
    $closure_reason = $is_closed ? $closure_dates_map[$current_date] : '';
    
    // Filter appointments by status for display (cancelled appointments are not shown in calendar)
    // Only show 'Scheduled' status in upcoming appointments
    $upcoming_apts = array_filter($day_appointments, function($apt) {
        return strtolower($apt['status']) === 'scheduled';
    });
    $completed_apts = array_filter($day_appointments, function($apt) {
        return strtolower($apt['status']) === 'completed';
    });
    
    $cell_class = "border-r border-b border-[#e7ecf3] dark:border-slate-700 p-2 transition-colors relative";
    if ($is_closed) {
        $cell_class .= " bg-red-50/50 dark:bg-red-900/10 opacity-75 cursor-not-allowed";
    } else {
        $cell_class .= " hover:bg-slate-50 dark:hover:bg-slate-800";
    }
    if ($is_today && !$is_closed) {
        $cell_class .= " bg-blue-50/50 dark:bg-blue-900/10 ring-inset ring-2 ring-primary/20";
    }
    
    echo '<div class="' . $cell_class . '">';
    if ($is_today && !$is_closed) {
        echo '<span class="font-bold text-primary">' . $day . '</span>';
    } elseif ($is_closed) {
        echo '<span class="font-medium text-red-600 dark:text-red-400 line-through">' . $day . '</span>';
    } else {
        echo '<span class="font-medium text-slate-700 dark:text-slate-300">' . $day . '</span>';
    }
    
    // Display closure notice (show first, before appointments)
    if ($is_closed) {
        echo '<div class="mt-2 w-full bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded px-2 py-1.5 border border-red-200 dark:border-red-800">';
        echo '<p class="text-xs font-bold truncate">CLOSED</p>';
        if (!empty($closure_reason)) {
            echo '<p class="text-[10px] opacity-90 truncate">' . htmlspecialchars($closure_reason) . '</p>';
        } else {
            echo '<p class="text-[10px] opacity-90 truncate">Clinic Closed</p>';
        }
        echo '</div>';
    }
    
    // Display appointments with status-based color coding (only if not closed)
    if (!$is_closed) {
        // Check for missed appointments (Scheduled status but past end time)
        $now = new DateTime();
        foreach ($upcoming_apts as $key => $apt) {
            $apt_end_time = new DateTime($apt['scheduled_datetime']);
            $apt_end_time->modify('+' . ($apt['duration_minutes'] ?? 60) . ' minutes');
            if ($apt['status'] === 'Scheduled' && $apt_end_time < $now) {
                // Mark as Missed
                try {
                    $missed_conn = getDBConnection();
                    $missed_stmt = $missed_conn->prepare("UPDATE tbl_appointments SET status = 'Missed' WHERE appointment_id = ?");
                    $missed_stmt->bind_param("i", $apt['appointment_id']);
                    $missed_stmt->execute();
                    $missed_stmt->close();
                    $missed_conn->close();
                    $upcoming_apts[$key]['status'] = 'Missed';
                } catch (Exception $e) {
                    // Ignore error, continue
                }
            }
        }
    
    foreach ($upcoming_apts as $apt) {
        $apt_time = date('h:i A', strtotime($apt['scheduled_datetime']));
        // Get service name - check catalog_service_name first, then service_name, then service_type
        $service = 'Appointment';
        if (isset($apt['catalog_service_name']) && !empty($apt['catalog_service_name'])) {
            $service = htmlspecialchars($apt['catalog_service_name']);
        } elseif (isset($apt['service_name']) && !empty($apt['service_name'])) {
            $service = htmlspecialchars($apt['service_name']);
        } elseif (isset($apt['service_type']) && !empty($apt['service_type'])) {
            $service = htmlspecialchars($apt['service_type']);
        }
        $status = strtolower($apt['status']);
        
        // Status-based color coding: Scheduled = Blue, Appointing = Orange, Missed = Red, Proceed to Payment = Violet, Completed = Green
        if ($status === 'scheduled' || $status === 'confirmed' || $status === 'pending') {
            $apt_color = 'bg-blue-500';
        } elseif ($status === 'appointing') {
            $apt_color = 'bg-orange-500';
        } elseif ($status === 'missed') {
            $apt_color = 'bg-red-500';
        } elseif ($status === 'proceed to payment') {
            $apt_color = 'bg-violet-500';
        } elseif ($status === 'completed') {
            $apt_color = 'bg-green-500';
        } else {
            $apt_color = 'bg-gray-500';
        }
        
        // Prepare appointment data for modal
        $apt_data = [
            'id' => $apt['appointment_id'],
            'datetime' => $apt['scheduled_datetime'],
            'time' => $apt_time,
            'service' => $service,
            'status' => $apt['status'],
            'duration' => $apt['duration_minutes'] ?? 60,
            'dentist' => isset($apt['dentist_first_name']) ? 'Dr. ' . ($apt['dentist_first_name'] . ' ' . $apt['dentist_last_name']) : 'TBD',
            'patient' => $is_admin && isset($apt['patient_first_name']) ? ($apt['patient_first_name'] . ' ' . $apt['patient_last_name']) : null,
            'price' => isset($apt['price']) ? number_format($apt['price'], 2) : 'N/A'
        ];
        $apt_json = htmlspecialchars(json_encode($apt_data), ENT_QUOTES, 'UTF-8');
        
        echo '<div class="mt-2 w-full ' . $apt_color . ' text-white rounded px-2 py-1.5 shadow-md transform hover:scale-[1.02] transition-transform cursor-pointer" data-apt=\'' . $apt_json . '\' onclick="showAppointmentDetails(JSON.parse(this.dataset.apt))">';
        echo '<p class="text-xs font-bold truncate">' . $apt_time . '</p>';
        echo '<p class="text-[10px] opacity-90 truncate">' . $service . '</p>';
        if ($is_admin && isset($apt['patient_first_name'])) {
            echo '<p class="text-[10px] opacity-75 truncate">' . htmlspecialchars($apt['patient_first_name'] . ' ' . $apt['patient_last_name']) . '</p>';
        }
        echo '</div>';
    }
    
    foreach ($completed_apts as $apt) {
        $apt_time = date('h:i A', strtotime($apt['scheduled_datetime']));
        // Get service name - check catalog_service_name first, then service_name, then service_type
        $service = 'Appointment';
        if (isset($apt['catalog_service_name']) && !empty($apt['catalog_service_name'])) {
            $service = htmlspecialchars($apt['catalog_service_name']);
        } elseif (isset($apt['service_name']) && !empty($apt['service_name'])) {
            $service = htmlspecialchars($apt['service_name']);
        } elseif (isset($apt['service_type']) && !empty($apt['service_type'])) {
            $service = htmlspecialchars($apt['service_type']);
        }
        
        $apt_data = [
            'id' => $apt['appointment_id'],
            'datetime' => $apt['scheduled_datetime'],
            'time' => $apt_time,
            'service' => $service,
            'status' => $apt['status'],
            'duration' => $apt['duration_minutes'] ?? 60,
            'dentist' => isset($apt['dentist_first_name']) ? 'Dr. ' . ($apt['dentist_first_name'] . ' ' . $apt['dentist_last_name']) : 'TBD',
            'patient' => $is_admin && isset($apt['patient_first_name']) ? ($apt['patient_first_name'] . ' ' . $apt['patient_last_name']) : null,
            'price' => isset($apt['price']) ? number_format($apt['price'], 2) : 'N/A'
        ];
        $apt_json = htmlspecialchars(json_encode($apt_data), ENT_QUOTES, 'UTF-8');
        
        echo '<div class="mt-2 w-full bg-green-500 text-white rounded px-2 py-1.5 shadow-md transform hover:scale-[1.02] transition-transform cursor-pointer" data-apt=\'' . $apt_json . '\' onclick="showAppointmentDetails(JSON.parse(this.dataset.apt))">';
        echo '<p class="text-xs font-bold truncate">' . $apt_time . '</p>';
        echo '<p class="text-[10px] opacity-90 truncate">' . $service . '</p>';
        if ($is_admin && isset($apt['patient_first_name'])) {
            echo '<p class="text-[10px] opacity-75 truncate">' . htmlspecialchars($apt['patient_first_name'] . ' ' . $apt['patient_last_name']) . '</p>';
        }
        echo '</div>';
        }
    }
    
    // Cancelled appointments are not displayed in calendar (they remain in detailed view only)
    
    echo '</div>';
}

// Days from next month
$total_cells = $day_of_week + $days_in_month;
$remaining_cells = 42 - $total_cells; // 6 rows * 7 days = 42
if ($remaining_cells > 0) {
    for ($day = 1; $day <= $remaining_cells; $day++) {
        echo '<div class="bg-slate-50/50 dark:bg-slate-800/20 border-r border-b border-[#e7ecf3] dark:border-slate-700 p-2 text-slate-300 dark:text-slate-600">' . $day . '</div>';
    }
}
?>
</div>
</div>
</div>
</div>
<div class="xl:col-span-4 flex flex-col gap-6">
<div class="relative">
<input type="text" id="appointmentSearch" placeholder="Search appointments..." autocomplete="off" aria-label="Search appointments" class="w-full rounded-xl h-12 px-4 pl-12 bg-white dark:bg-[#1a2634] border border-[#cfd9e7] dark:border-slate-600 text-[#0d131b] dark:text-white text-sm font-medium focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
<span class="material-symbols-outlined absolute left-4 top-1/2 transform -translate-y-1/2 text-[#4c6c9a] dark:text-slate-400 text-xl">search</span>
</div>
<div class="flex flex-wrap gap-2 pb-2">
<a href="appointments.php?month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>&filter=upcoming" class="flex h-8 items-center justify-center gap-x-2 rounded-full <?php echo $filter === 'upcoming' ? 'bg-primary text-white' : 'bg-white dark:bg-[#1a2634] border border-[#cfd9e7] dark:border-slate-600 hover:border-primary text-[#0d131b] dark:text-slate-300'; ?> px-4 <?php echo $filter === 'upcoming' ? 'shadow-sm' : ''; ?> transition-colors">
<span class="size-2 rounded-full bg-white"></span>
<p class="text-xs font-bold uppercase tracking-wide">Upcoming</p>
</a>
<a href="appointments.php?month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>&filter=completed" class="flex h-8 items-center justify-center gap-x-2 rounded-full <?php echo $filter === 'completed' ? 'bg-ivy-green text-white' : 'bg-white dark:bg-[#1a2634] border border-[#cfd9e7] dark:border-slate-600 hover:border-ivy-green text-[#0d131b] dark:text-slate-300'; ?> px-4 transition-colors">
<span class="size-2 rounded-full <?php echo $filter === 'completed' ? 'bg-white' : 'bg-ivy-green'; ?>"></span>
<p class="text-xs font-medium">Completed</p>
</a>
<?php if ($is_dentist): ?>
<a href="appointments.php?month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>&filter=history" class="flex h-8 items-center justify-center gap-x-2 rounded-full <?php echo $filter === 'history' ? 'bg-primary text-white' : 'bg-white dark:bg-[#1a2634] border border-[#cfd9e7] dark:border-slate-600 hover:border-primary text-[#0d131b] dark:text-slate-300'; ?> px-4 transition-colors">
<span class="size-2 rounded-full <?php echo $filter === 'history' ? 'bg-white' : 'bg-primary'; ?>"></span>
<p class="text-xs font-medium">History</p>
</a>
<?php endif; ?>
</div>
<div class="flex flex-col gap-4">
<h3 class="text-sm font-bold text-[#4c6c9a] uppercase tracking-wider mb-1">Detailed View</h3>
<div class="overflow-y-auto max-h-[600px] pr-2">
<?php
$display_appointments = [];
if ($filter === 'upcoming') {
    // Include Scheduled, Confirmed, and Pending appointments in upcoming
    $upcoming_filtered = array_filter($all_appointments, function($apt) use ($today) {
        $status = strtolower($apt['status']);
        return in_array($status, ['scheduled', 'confirmed', 'pending']) && 
               $apt['scheduled_datetime'] >= $today;
    });
    usort($upcoming_filtered, function($a, $b) {
        return strtotime($a['scheduled_datetime']) - strtotime($b['scheduled_datetime']);
    });
    $display_appointments = array_slice($upcoming_filtered, 0, 5);
} elseif ($filter === 'completed') {
    $display_appointments = array_slice($completed_appointments, 0, 5);
} elseif ($filter === 'history' && $is_dentist) {
    // Appointment history for dentist - show all appointments assigned to them
    $history_filtered = array_filter($all_appointments, function($apt) {
        return true; // Show all appointments for dentist history
    });
    usort($history_filtered, function($a, $b) {
        return strtotime($b['scheduled_datetime']) - strtotime($a['scheduled_datetime']); // Most recent first
    });
    $display_appointments = $history_filtered; // Show all, not limited to 5
}

if (empty($display_appointments)) {
    echo '<div class="rounded-xl p-5 bg-white dark:bg-[#1a2634] border border-[#cfd9e7] dark:border-slate-700">';
    echo '<p class="text-[#4c6c9a] text-sm text-center">No ' . htmlspecialchars($filter) . ' appointments</p>';
    echo '</div>';
} else {
    // For history filter, show pagination
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $per_page = 10;
    $total_appointments = count($display_appointments);
    $total_pages = ceil($total_appointments / $per_page);
    $offset = ($page - 1) * $per_page;
    
    if ($filter === 'history' && $is_dentist) {
        $display_appointments = array_slice($display_appointments, $offset, $per_page);
    }
    
    foreach ($display_appointments as $apt) {
        $apt_date = new DateTime($apt['scheduled_datetime']);
        $dentist_name = trim(($apt['dentist_first_name'] ?? '') . ' ' . ($apt['dentist_last_name'] ?? ''));
        if (empty(trim($dentist_name))) {
            $dentist_name = 'TBD';
        } else {
            $dentist_name = 'Dr. ' . $dentist_name;
        }
        
        $status = strtolower($apt['status']);
        $border_color = 'border-l-primary';
        $bg_color = '';
        $status_badge = '';
        
        if ($status === 'completed') {
            $border_color = 'border-l-ivy-green dark:border-l-ivy-green';
            $status_badge = '<span class="bg-ivy-green/10 text-ivy-green text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wide">Completed</span>';
        } elseif ($status === 'cancelled') {
            $border_color = 'border-l-slate-400 dark:border-l-slate-400';
            $bg_color = 'bg-slate-50 dark:bg-slate-800 opacity-75';
            $status_badge = '<span class="bg-slate-200 dark:bg-slate-600 text-slate-500 dark:text-slate-300 text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wide">Cancelled</span>';
        } else {
            // Show "Upcoming" for Scheduled, Confirmed, and Pending statuses
            if (in_array($status, ['scheduled', 'confirmed', 'pending'])) {
                $status_badge = '<span class="bg-primary/10 text-primary text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wide">Upcoming</span>';
        } else {
            $status_badge = '<span class="bg-primary/10 text-primary text-[10px] px-2 py-0.5 rounded-full font-bold uppercase tracking-wide">' . htmlspecialchars(ucfirst($status)) . '</span>';
        }
        }
        
        // For admin view, show patient name
        $patient_name_display = '';
        if ($is_admin && isset($apt['patient_first_name'])) {
            $patient_name_display = htmlspecialchars($apt['patient_first_name'] . ' ' . $apt['patient_last_name']);
        }
        
        // Get service name for display and search
        $service_display = 'Appointment';
        if (isset($apt['catalog_service_name']) && !empty($apt['catalog_service_name'])) {
            $service_display = $apt['catalog_service_name'];
        } elseif (isset($apt['service_name']) && !empty($apt['service_name'])) {
            $service_display = $apt['service_name'];
        } elseif (isset($apt['service_type']) && !empty($apt['service_type'])) {
            $service_display = $apt['service_type'];
        }
        
        // Prepare searchable text for filtering
        $searchable_text = strtolower(
            $service_display . ' ' .
            $dentist_name . ' ' .
            $apt_date->format('M d Y h:i A') . ' ' .
            ($patient_name_display ?? '') . ' ' .
            $status
        );
        
        echo '<div class="appointment-card group relative flex flex-col gap-3 rounded-xl p-5 bg-white dark:bg-[#1a2634] border border-l-4 ' . $border_color . ' dark:border-slate-700 shadow-sm hover:shadow-md transition-all ' . $bg_color . '" data-search-text="' . htmlspecialchars($searchable_text, ENT_QUOTES, 'UTF-8') . '">';
        echo '<div class="flex justify-between items-start">';
        echo '<div>';
        echo '<p class="text-[#0d131b] dark:text-white text-lg font-bold' . ($status === 'cancelled' ? ' line-through decoration-slate-400' : '') . '">' . htmlspecialchars($service_display) . '</p>';
        if ($is_admin && !empty($patient_name_display)) {
            echo '<p class="text-[#4c6c9a] text-sm font-medium">Patient: ' . $patient_name_display . '</p>';
        }
        echo '<p class="text-[#4c6c9a] text-sm">' . htmlspecialchars($dentist_name) . '</p>';
        echo '</div>';
        if ($status === 'completed' || $status === 'cancelled') {
            echo $status_badge;
        } else {
            echo '<span class="material-symbols-outlined text-primary bg-primary/10 rounded-full p-1 text-sm">calendar_month</span>';
        }
        echo '</div>';
        echo '<div class="flex flex-col gap-2 mt-1">';
        echo '<div class="flex items-center gap-2 text-[#0d131b] dark:text-slate-200 text-sm font-medium">';
        echo '<span class="material-symbols-outlined text-base">schedule</span>';
        echo $apt_date->format('M d, Y â€¢ h:i A');
        echo '</div>';
            echo '</div>';
        if ($status === 'completed') {
            echo '<div class="mt-1 pt-3 border-t border-[#e7ecf3] dark:border-slate-700">';
            echo '<a class="flex items-center gap-1 text-primary text-xs font-bold hover:underline" href="#">';
            echo '<span class="material-symbols-outlined text-sm">description</span>';
            echo 'View Visit Summary';
            echo '</a>';
            echo '</div>';
        }
        echo '</div>';
    }
    
    // Pagination for history
    if ($filter === 'history' && $is_dentist && $total_pages > 1) {
        echo '<div class="mt-4 flex items-center justify-center gap-2">';
        if ($page > 1) {
            echo '<a href="appointments.php?month=' . $current_month . '&year=' . $current_year . '&filter=history&page=' . ($page - 1) . '" class="px-3 py-1 rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Previous</a>';
        }
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i === $page) {
                echo '<span class="px-3 py-1 rounded-lg bg-primary text-white">' . $i . '</span>';
            } else {
                echo '<a href="appointments.php?month=' . $current_month . '&year=' . $current_year . '&filter=history&page=' . $i . '" class="px-3 py-1 rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">' . $i . '</a>';
            }
        }
        if ($page < $total_pages) {
            echo '<a href="appointments.php?month=' . $current_month . '&year=' . $current_year . '&filter=history&page=' . ($page + 1) . '" class="px-3 py-1 rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">Next</a>';
        }
        echo '</div>';
    }
}
?>
</div>
</div>
</div>
</div>
</div>
</main>
        </main><!-- /.main -->
    </div><!-- /flex-1 -->
</div><!-- /flex min-h-screen -->
<?php ob_start(); ?>

<!-- Appointment Details Modal -->
<div id="appointmentModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" onclick="closeAppointmentModal(event)">
<div class="bg-white dark:bg-[#1a2634] rounded-xl shadow-xl max-w-md w-full p-6" onclick="event.stopPropagation()" id="appointmentModalInner">
<div class="flex items-center justify-between mb-4">
<h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Appointment Details</h3>
<button onclick="closeAppointmentModal()" class="text-[#4c6c9a] hover:text-[#0d131b] dark:text-slate-400 dark:hover:text-white">
<span class="material-symbols-outlined">close</span>
</button>
</div>
<div id="appointmentModalContent" class="space-y-4">
<!-- Content will be populated by JavaScript -->
</div>
</div>
</div>

<script>
// Store all appointments globally for reschedule modal access
const allAppointments = <?php echo json_encode(array_map(function($apt) {
    return [
        'id' => $apt['appointment_id'],
        'datetime' => $apt['scheduled_datetime'],
        'duration' => $apt['duration_minutes'] ?? 60
    ];
}, $all_appointments)); ?>;

// Search functionality for appointments
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('appointmentSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            const appointmentCards = document.querySelectorAll('.appointment-card');
            
            if (searchTerm === '') {
                // Show all appointments when search is empty
                appointmentCards.forEach(card => {
                    card.style.display = 'flex';
                });
            } else {
                // Filter appointments based on search term
                appointmentCards.forEach(card => {
                    const searchText = card.getAttribute('data-search-text') || '';
                    if (searchText.includes(searchTerm)) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }
        });
    }
});

function showAppointmentDetails(apt) {
    const modal = document.getElementById('appointmentModal');
    const content = document.getElementById('appointmentModalContent');
    
    const date = new Date(apt.datetime);
    const dateStr = date.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    
    let statusBadge = '';
    const statusLower = apt.status.toLowerCase();
    if (statusLower === 'pending') {
        statusBadge = '<span class="bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400 px-2 py-1 rounded-full text-xs font-semibold">Pending</span>';
    } else if (statusLower === 'scheduled' || statusLower === 'confirmed') {
        statusBadge = '<span class="bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 px-2 py-1 rounded-full text-xs font-semibold">Scheduled</span>';
    } else if (statusLower === 'completed') {
        statusBadge = '<span class="bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 px-2 py-1 rounded-full text-xs font-semibold">Completed</span>';
    } else if (statusLower === 'missed') {
        statusBadge = '<span class="bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 px-2 py-1 rounded-full text-xs font-semibold">Missed</span>';
    } else if (statusLower === 'cancelled') {
        statusBadge = '<span class="bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 px-2 py-1 rounded-full text-xs font-semibold">Cancelled</span>';
    }
    
    // Only patients can edit/reschedule - admins cannot reschedule
    const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
    const canEdit = !isAdmin && (apt.status === 'Confirmed' || apt.status === 'Scheduled' || apt.status === 'Pending') && apt.status !== 'Missed';
    
    content.innerHTML = `
        <div class="space-y-3">
            <div>
                <p class="text-xs text-[#4c6c9a] dark:text-slate-400 font-medium">Service</p>
                <p class="text-base font-bold text-[#0d131b] dark:text-white">${apt.service}</p>
            </div>
            <div>
                <p class="text-xs text-[#4c6c9a] dark:text-slate-400 font-medium">Date & Time</p>
                <p class="text-base font-semibold text-[#0d131b] dark:text-white">${dateStr}</p>
                <p class="text-sm text-[#4c6c9a] dark:text-slate-400">${apt.time}</p>
            </div>
            ${apt.patient ? `
            <div>
                <p class="text-xs text-[#4c6c9a] dark:text-slate-400 font-medium">Patient</p>
                <p class="text-base font-semibold text-[#0d131b] dark:text-white">${apt.patient}</p>
            </div>
            ` : ''}
            <div>
                <p class="text-xs text-[#4c6c9a] dark:text-slate-400 font-medium">Dentist</p>
                <p class="text-base font-semibold text-[#0d131b] dark:text-white">${apt.dentist}</p>
            </div>
            <div>
                <p class="text-xs text-[#4c6c9a] dark:text-slate-400 font-medium">Duration</p>
                <p class="text-base font-semibold text-[#0d131b] dark:text-white">${apt.duration} minutes</p>
            </div>
            <div>
                <p class="text-xs text-[#4c6c9a] dark:text-slate-400 font-medium">Status</p>
                <div class="mt-1">${statusBadge}</div>
            </div>
            ${apt.price !== 'N/A' ? `
            <div>
                <p class="text-xs text-[#4c6c9a] dark:text-slate-400 font-medium">Price</p>
                <p class="text-base font-bold text-[#0d131b] dark:text-white">â‚±${apt.price}</p>
            </div>
            ` : ''}
        </div>
        ${canEdit ? `
        <div class="flex gap-2 pt-4 border-t border-[#e7ecf3] dark:border-slate-700">
            <a href="book_appointment.php?edit=${apt.id}" class="w-full rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white py-2 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors text-center">
                Reschedule
            </a>
        </div>
        ` : ''}
        ${apt.status === 'Missed' ? `
        <div class="pt-4 border-t border-[#e7ecf3] dark:border-slate-700">
            <p class="text-sm text-red-600 dark:text-red-400 font-medium text-center">This appointment cannot be rescheduled.</p>
        </div>
        ` : ''}
    `;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}


function closeAppointmentModal(event) {
    if (event && event.target !== event.currentTarget) return;
    const modal = document.getElementById('appointmentModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAppointmentModal();
        closeConfirmModal();
    }
});

// Reschedule now redirects to booking page - function removed

// Cancel function removed - appointments cannot be cancelled by any user

function closeConfirmModal(event) {
    if (event && event.target !== event.currentTarget) return;
    const modal = document.getElementById('confirmModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>

<!-- Confirmation Modal -->
<div id="confirmModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" onclick="closeConfirmModal(event)">
<div class="bg-white dark:bg-[#1a2634] rounded-xl shadow-xl max-w-md w-full p-6" onclick="event.stopPropagation()">
<div id="confirmModalContent">
<!-- Content will be populated by JavaScript -->
</div>
</div>
</div>

<script>
// Logout confirmation
function confirmLogout(event) {
    event.preventDefault();
    const modal = document.getElementById('confirmModal');
    const content = document.getElementById('confirmModalContent');
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

// AUTOMATIC PAYMENT VERIFICATION
// Runs automatically when page loads - no user action needed
<?php if ($user_type === 'patient'): ?>
(function() {
    // Check if patient has pending PayMongo payments and auto-verify them
    fetch('verify_my_payments.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.verified > 0) {
                // Payments were verified! Reload page to show updated status
                console.log('âœ… Auto-verified ' + data.verified + ' payment(s)');
                setTimeout(() => window.location.reload(), 1000);
            }
        })
        .catch(error => {
            console.log('Payment verification check completed');
        });
})();
<?php endif; ?>
</script>

<?php if ($is_admin_only): ?>
<!-- Clinic Closure Dates Modal -->
<div id="closureModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" onclick="closeClosureModal(event)">
<div class="bg-white dark:bg-[#1a2634] rounded-xl shadow-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
<div class="flex items-center justify-between mb-4">
<h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Manage Clinic Closure Dates</h3>
<button onclick="closeClosureModal()" class="text-[#4c6c9a] hover:text-[#0d131b] dark:text-slate-400 dark:hover:text-white">
<span class="material-symbols-outlined">close</span>
</button>
</div>
<form method="POST" action="" class="mb-6">
<input type="hidden" name="action" value="add_closure_date">
<div class="flex gap-3 mb-4">
<div class="flex-1">
<label for="closure_date" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Select Date</label>
<input type="date" name="closure_date" id="closure_date" required min="<?php echo date('Y-m-d'); ?>" autocomplete="off" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20"/>
</div>
<div class="flex-1">
<label for="closure_reason" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Reason (Optional)</label>
<input type="text" name="reason" id="closure_reason" autocomplete="off" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="e.g., Holiday"/>
</div>
<div class="flex items-end">
<button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-blue-600 transition-colors h-10">
Add Closure
</button>
</div>
</div>
</form>
<div class="space-y-2">
<h4 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">Upcoming Closure Dates:</h4>
<?php if (empty($closure_dates)): ?>
<p class="text-sm text-slate-500 dark:text-slate-400">No closure dates scheduled.</p>
<?php else: ?>
<?php foreach ($closure_dates as $closure): ?>
<div class="flex items-center justify-between p-3 bg-slate-50 dark:bg-slate-800 rounded-lg">
<div>
<p class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo date('F d, Y', strtotime($closure['closure_date'])); ?></p>
<?php if (!empty($closure['reason'])): ?>
<p class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($closure['reason']); ?></p>
<?php endif; ?>
</div>
<form method="POST" action="" class="inline">
<input type="hidden" name="action" value="remove_closure_date">
<input type="hidden" name="closure_id" value="<?php echo $closure['closure_id']; ?>">
<button type="submit" onclick="return confirm('Remove this closure date?')" class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300">
<span class="material-symbols-outlined text-lg">delete</span>
</button>
</form>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
</div>
</div>

<!-- Dentist Schedule Modal -->
<div id="dentistScheduleModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" onclick="closeDentistScheduleModal(event)">
<div class="bg-white dark:bg-[#1a2634] rounded-xl shadow-xl max-w-4xl w-full p-6 max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
<div class="flex items-center justify-between mb-4">
<h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Manage Dentist Schedules</h3>
<button type="button" onclick="closeDentistScheduleModal(event)" class="text-[#4c6c9a] hover:text-[#0d131b] dark:text-slate-400 dark:hover:text-white">
<span class="material-symbols-outlined">close</span>
</button>
</div>
<?php if (empty($dentists_list)): ?>
<p class="text-sm text-slate-500 dark:text-slate-400">No dentists found.</p>
<?php else: ?>
<div class="mb-6">
<label for="dentistScheduleSelect" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Select Dentist *</label>
<select id="dentistScheduleSelect" autocomplete="off" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
<option value="">-- Select Dentist --</option>
<?php foreach ($dentists_list as $dentist): ?>
<option value="<?php echo $dentist['staff_id']; ?>" data-name="<?php echo htmlspecialchars($dentist['first_name'] . ' ' . $dentist['last_name']); ?>"><?php echo htmlspecialchars('Dr. ' . $dentist['first_name'] . ' ' . $dentist['last_name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div id="dentistScheduleFormContainer" class="hidden">
<form method="POST" action="" id="dentistScheduleForm">
<input type="hidden" name="action" value="save_dentist_schedule">
<input type="hidden" name="dentist_id" id="schedule_dentist_id" value="">
<div class="mb-4">
<h4 class="text-base font-semibold text-slate-900 dark:text-white mb-4" id="selectedDentistName"></h4>
<div id="scheduleDaysContainer" class="space-y-3">
<?php
$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
for ($i = 0; $i < 7; $i++):
?>
<div class="flex items-center gap-3 p-3 bg-slate-50 dark:bg-slate-800 rounded-lg schedule-day-row" data-day="<?php echo $i; ?>">
<div class="w-24">
<label class="flex items-center gap-2" for="schedule_enabled_<?php echo $i; ?>">
<input type="checkbox" name="schedules[<?php echo $i; ?>][enabled]" id="schedule_enabled_<?php echo $i; ?>" value="1" class="schedule-checkbox" data-day="<?php echo $i; ?>">
<span class="text-sm font-medium text-slate-700 dark:text-slate-300"><?php echo $days[$i]; ?></span>
</label>
<input type="hidden" name="schedules[<?php echo $i; ?>][day]" value="<?php echo $i; ?>">
</div>
<div class="flex-1 flex gap-2">
<input type="time" name="schedules[<?php echo $i; ?>][start_time]" id="schedule_start_<?php echo $i; ?>" value="09:00" autocomplete="off" class="schedule-time flex-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2 text-sm" disabled>
<span class="text-slate-500 dark:text-slate-400 self-center">to</span>
<input type="time" name="schedules[<?php echo $i; ?>][end_time]" id="schedule_end_<?php echo $i; ?>" value="17:00" autocomplete="off" class="schedule-time flex-1 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-3 py-2 text-sm" disabled>
</div>
</div>
<?php endfor; ?>
</div>
<button type="submit" class="mt-4 px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-blue-600 transition-colors">
Save Schedule
</button>
</form>
</div>
<?php endif; ?>
</div>
</div>

<script>
function openClosureModal() {
    document.getElementById('closureModal').classList.remove('hidden');
    document.getElementById('closureModal').classList.add('flex');
}

function closeClosureModal(event) {
    if (event && event.target !== event.currentTarget) return;
    const modal = document.getElementById('closureModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function openDentistScheduleModal() {
    document.getElementById('dentistScheduleModal').classList.remove('hidden');
    document.getElementById('dentistScheduleModal').classList.add('flex');
}

function closeDentistScheduleModal(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    const modal = document.getElementById('dentistScheduleModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        // Reset form
        const select = document.getElementById('dentistScheduleSelect');
        if (select) select.value = '';
        const container = document.getElementById('dentistScheduleFormContainer');
        if (container) container.classList.add('hidden');
    }
}

// Dentist schedule data from PHP
const dentistSchedulesData = <?php echo json_encode($dentist_schedules); ?>;
const dentistServicesData = <?php echo json_encode($dentist_services ?? []); ?>;

// Function to load dentist schedule
function loadDentistSchedule(dentistId) {
    const container = document.getElementById('dentistScheduleFormContainer');
    const form = document.getElementById('dentistScheduleForm');
    const dentistIdInput = document.getElementById('schedule_dentist_id');
    const dentistNameEl = document.getElementById('selectedDentistName');
    const select = document.getElementById('dentistScheduleSelect');
    
    if (!container || !form || !dentistIdInput) return;
    
    dentistIdInput.value = dentistId;
    const selectedOption = select.options[select.selectedIndex];
    if (selectedOption) {
        dentistNameEl.textContent = selectedOption.textContent;
    }
    
    // Get existing schedule for this dentist (handle both string and number keys)
    const dentistIdKey = String(dentistId);
    const existingSchedule = dentistSchedulesData[dentistIdKey] || dentistSchedulesData[dentistId] || [];
    
    // Create a map of day_of_week to schedule
    const scheduleMap = {};
    existingSchedule.forEach(sched => {
        scheduleMap[sched.day_of_week] = sched;
    });
    
    // Update form with existing schedule
    const scheduleRows = container.querySelectorAll('.schedule-day-row');
    scheduleRows.forEach(row => {
        const day = parseInt(row.dataset.day);
        const checkbox = row.querySelector('.schedule-checkbox');
        const startTime = row.querySelector('input[name*="[start_time]"]');
        const endTime = row.querySelector('input[name*="[end_time]"]');
        
        if (scheduleMap[day]) {
            checkbox.checked = true;
            startTime.value = scheduleMap[day].start_time.substring(0, 5); // Remove seconds
            endTime.value = scheduleMap[day].end_time.substring(0, 5);
            startTime.disabled = false;
            endTime.disabled = false;
        } else {
            checkbox.checked = false;
            startTime.value = '09:00';
            endTime.value = '17:00';
            startTime.disabled = true;
            endTime.disabled = true;
        }
    });
    
    container.classList.remove('hidden');
}

// Enable/disable time inputs based on checkbox
document.addEventListener('DOMContentLoaded', function() {
    const dentistSelect = document.getElementById('dentistScheduleSelect');
    if (dentistSelect) {
        dentistSelect.addEventListener('change', function() {
            const dentistId = this.value;
            if (dentistId) {
                loadDentistSchedule(dentistId);
            } else {
                const container = document.getElementById('dentistScheduleFormContainer');
                if (container) container.classList.add('hidden');
            }
        });
    }
    
    // Enable/disable time inputs based on checkbox (using event delegation)
    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('schedule-checkbox')) {
            const container = e.target.closest('.schedule-day-row');
            const timeInputs = container.querySelectorAll('.schedule-time');
            timeInputs.forEach(input => {
                input.disabled = !e.target.checked;
            });
        }
    });
    
    // Filter out unchecked schedules before submit
    const scheduleForm = document.getElementById('dentistScheduleForm');
    if (scheduleForm) {
        scheduleForm.addEventListener('submit', function(e) {
            // Collect only checked schedules
            const checkedSchedules = [];
            const checkboxes = this.querySelectorAll('.schedule-checkbox:checked');
            checkboxes.forEach(checkbox => {
                const day = checkbox.dataset.day;
                const container = checkbox.closest('.schedule-day-row');
                const startTimeInput = container.querySelector('input[name*="[start_time]"]');
                const endTimeInput = container.querySelector('input[name*="[end_time]"]');
                
                if (startTimeInput && endTimeInput) {
                    const startTime = startTimeInput.value;
                    const endTime = endTimeInput.value;
                if (startTime && endTime) {
                        checkedSchedules.push({
                        day: day,
                        start_time: startTime,
                        end_time: endTime
                    });
                    }
                }
            });
            
            // Remove all existing schedule inputs
            this.querySelectorAll('input[name^="schedules"]').forEach(input => {
                    input.remove();
            });
            
            // Add only checked schedules with proper format
            checkedSchedules.forEach((sched, index) => {
                // Add enabled flag
                const enabledInput = document.createElement('input');
                enabledInput.type = 'hidden';
                enabledInput.name = `schedules[${index}][enabled]`;
                enabledInput.value = '1';
                this.appendChild(enabledInput);
                
                // Add day
                const dayInput = document.createElement('input');
                dayInput.type = 'hidden';
                dayInput.name = `schedules[${index}][day]`;
                dayInput.value = sched.day;
                this.appendChild(dayInput);
                
                // Add start_time
                const startInput = document.createElement('input');
                startInput.type = 'hidden';
                startInput.name = `schedules[${index}][start_time]`;
                startInput.value = sched.start_time;
                this.appendChild(startInput);
                
                // Add end_time
                const endInput = document.createElement('input');
                endInput.type = 'hidden';
                endInput.name = `schedules[${index}][end_time]`;
                endInput.value = sched.end_time;
                this.appendChild(endInput);
            });
        });
    }
});
</script>
<?php endif; ?>

<?php if (($is_admin && !$is_manager) || $is_receptionist): ?>
<!-- Walk-In Appointment Modal -->
<div id="walkInModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" onclick="closeWalkInModal(event)">
<div class="bg-white dark:bg-[#1a2634] rounded-xl shadow-xl max-w-3xl w-full p-6 max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
<div class="flex items-center justify-between mb-4">
<h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Walk-In Appointment Booking</h3>
<button type="button" onclick="closeWalkInModal(event)" class="text-[#4c6c9a] hover:text-[#0d131b] dark:text-slate-400 dark:hover:text-white">
<span class="material-symbols-outlined">close</span>
</button>
</div>
<?php if (!empty($action_message) && strpos($action_message, 'Walk-in') !== false): ?>
<div class="mb-4 p-3 rounded-lg <?php echo strpos($action_message, 'successfully') !== false ? 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400' : 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400'; ?>">
<?php echo $action_message; ?>
</div>
<?php endif; ?>
<div class="mb-6">
<h4 class="text-sm font-semibold text-[#4c6c9a] dark:text-slate-400 mb-3">Select Registration Type</h4>
<div class="grid grid-cols-2 gap-4">
<button type="button" onclick="setWalkInType('existing')" id="walkin-existing-btn" class="p-4 border-2 border-[#e7ecf3] dark:border-slate-700 rounded-lg hover:border-primary transition-colors text-left">
<div class="flex items-center gap-3">
<span class="material-symbols-outlined text-3xl text-primary">person_search</span>
<div>
<h5 class="font-bold text-[#0d131b] dark:text-white">Existing Account</h5>
<p class="text-xs text-[#4c6c9a] dark:text-slate-400">Select from existing patients</p>
</div>
</div>
</button>
<button type="button" onclick="setWalkInType('new')" id="walkin-new-btn" class="p-4 border-2 border-[#e7ecf3] dark:border-slate-700 rounded-lg hover:border-primary transition-colors text-left">
<div class="flex items-center gap-3">
<span class="material-symbols-outlined text-3xl text-primary">person_add</span>
<div>
<h5 class="font-bold text-[#0d131b] dark:text-white">New Patient</h5>
<p class="text-xs text-[#4c6c9a] dark:text-slate-400">Register new patient</p>
</div>
</div>
</button>
</div>
</div>
<form method="POST" action="" id="walkInForm">
<input type="hidden" name="action" value="book_walkin">
<input type="hidden" name="registration_type" id="walkin_registration_type" value="">
<div id="walkin-existing-section" class="hidden">
<div class="mb-4">
<label for="walkin_patient_search" class="block text-sm font-medium text-[#4c6c9a] dark:text-slate-400 mb-2">Search Patient *</label>
<div class="relative">
<input type="text" id="walkin_patient_search" autocomplete="off" placeholder="Type patient name or email to search..." class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4" />
<input type="hidden" name="patient_id" id="walkin_patient_id" required />
<div id="walkin_patient_results" class="absolute z-50 w-full mt-1 bg-white dark:bg-[#1a2634] border border-[#e7ecf3] dark:border-slate-700 rounded-lg shadow-lg max-h-60 overflow-y-auto hidden" style="top: 100%;">
</div>
</div>
<div id="walkin_selected_patient" class="mt-2 p-2 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg hidden">
<div class="flex items-center justify-between">
<div>
<p class="text-sm font-medium text-green-800 dark:text-green-300" id="walkin_selected_patient_name"></p>
<p class="text-xs text-green-600 dark:text-green-400" id="walkin_selected_patient_email"></p>
</div>
<button type="button" onclick="clearWalkInPatientSelection()" class="text-green-600 dark:text-green-400 hover:text-green-800 dark:hover:text-green-300">
<span class="material-symbols-outlined text-sm">close</span>
</button>
</div>
</div>
</div>
</div>
<div id="walkin-new-section" class="hidden">
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
<div>
<label class="block text-sm font-medium text-[#4c6c9a] dark:text-slate-400 mb-2">First Name *</label>
<input type="text" name="first_name" id="walkin_first_name" class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4"/>
</div>
<div>
<label class="block text-sm font-medium text-[#4c6c9a] dark:text-slate-400 mb-2">Last Name *</label>
<input type="text" name="last_name" id="walkin_last_name" class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4"/>
</div>
<div>
<label class="block text-sm font-medium text-[#4c6c9a] dark:text-slate-400 mb-2">Email *</label>
<input type="email" name="email" id="walkin_email" required pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}" class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4"/>
<p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Enter a valid email address (e.g., name@example.com)</p>
</div>
<div>
<label class="block text-sm font-medium text-[#4c6c9a] dark:text-slate-400 mb-2">Phone *</label>
<input type="tel" name="phone" id="walkin_phone" required pattern="[0-9+\-() ]{7,20}" class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4" placeholder="e.g., +63 912 345 6789 or 09123456789"/>
<p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Enter a valid phone number (7-20 digits, may include +, -, (), or spaces)</p>
</div>
<div>
<label class="block text-sm font-medium text-[#4c6c9a] dark:text-slate-400 mb-2">Date of Birth *</label>
<input type="date" name="dob" id="walkin_dob" class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4"/>
</div>
<div>
<label class="block text-sm font-medium text-[#4c6c9a] dark:text-slate-400 mb-2">Gender *</label>
<select name="gender" id="walkin_gender" required class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4">
<option value="Prefer not to say">Prefer not to say</option>
<option value="Male">Male</option>
<option value="Female">Female</option>
<option value="Other">Other</option>
</select>
</div>
<div>
<label class="block text-sm font-medium text-[#4c6c9a] dark:text-slate-400 mb-2">Blood Type</label>
<select name="blood_type" id="walkin_blood_type" class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4">
<option value="Unknown">Unknown</option>
<option value="A+">A+</option>
<option value="A-">A-</option>
<option value="B+">B+</option>
<option value="B-">B-</option>
<option value="AB+">AB+</option>
<option value="AB-">AB-</option>
<option value="O+">O+</option>
<option value="O-">O-</option>
</select>
</div>
</div>
<div class="mb-4">
<label for="walkin_allergy_0" class="block text-sm font-medium text-[#4c6c9a] dark:text-slate-400 mb-2">Allergies (Optional)</label>
<div id="walkin-allergies-container">
<div class="flex gap-2 mb-2">
<input type="text" name="allergies[]" id="walkin_allergy_0" placeholder="Enter allergy (e.g., Peanuts)" autocomplete="off" class="flex-1 rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4"/>
<button type="button" onclick="addWalkInAllergyField()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors">
<span class="material-symbols-outlined text-sm">add</span>
</button>
</div>
</div>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
<div>
<label for="walkin_dentist_id" class="block text-sm font-medium text-[#4c6c9a] dark:text-slate-400 mb-2">Dentist *</label>
<select name="dentist_id" id="walkin_dentist_id" autocomplete="off" class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4">
<option value="">Let system select dentist (recommended)</option>
<?php 
// DEBUG: Log what we have when rendering dropdown
error_log("DEBUG appointments.php (render): dentists_list is " . (isset($dentists_list) ? "set with count: " . count($dentists_list) : "NOT SET"));

// Debug: Check if dentists_list is set and has data
if (isset($dentists_list) && !empty($dentists_list)): 
    foreach ($dentists_list as $dentist): 
?>
<option value="<?php echo $dentist['staff_id']; ?>"><?php echo htmlspecialchars('Dr. ' . $dentist['first_name'] . ' ' . $dentist['last_name']); ?></option>
<?php 
    endforeach; 
else: 
    // Debug output
    error_log("DEBUG appointments.php (render): dentists_list is " . (isset($dentists_list) ? "set but empty (count: " . count($dentists_list) . ")" : "NOT SET"));
    // Try to fetch directly here as fallback
    try {
        $fallback_conn = getDBConnection();
        $fallback_stmt = $fallback_conn->prepare("SELECT staff_id, first_name, last_name FROM tbl_staff WHERE role IN ('Dentist', 'Admin') ORDER BY last_name, first_name");
        if ($fallback_stmt && $fallback_stmt->execute()) {
            $fallback_result = $fallback_stmt->get_result();
            $fallback_count = 0;
            while ($fallback_row = $fallback_result->fetch_assoc()) {
                $fallback_count++;
                echo '<option value="' . $fallback_row['staff_id'] . '">' . htmlspecialchars('Dr. ' . $fallback_row['first_name'] . ' ' . $fallback_row['last_name']) . '</option>';
            }
            error_log("DEBUG appointments.php (fallback): Found {$fallback_count} dentists in fallback query");
            $fallback_stmt->close();
            $fallback_conn->close();
        } else {
            error_log("ERROR appointments.php (fallback): Fallback query failed");
            echo '<option value="" disabled>No dentists available (Check error logs)</option>';
        }
    } catch (Exception $e) {
        error_log("ERROR appointments.php (fallback): " . $e->getMessage());
        echo '<option value="" disabled>No dentists available (Error: ' . htmlspecialchars($e->getMessage()) . ')</option>';
    }
endif; 
?>
</select>
</div>
<div>
<label class="block text-sm font-medium text-[#4c6c9a] dark:text-slate-400 mb-2">Service *</label>
<select name="service_id" id="walkin_service_id" required class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4">
<option value="">-- Select Service --</option>
<?php foreach ($services as $service): ?>
<option value="<?php echo $service['service_id']; ?>" data-price="<?php echo $service['base_price']; ?>" data-duration="<?php echo $service['default_duration_minutes']; ?>"><?php echo htmlspecialchars($service['service_name'] . ' - â‚±' . number_format($service['base_price'], 2)); ?></option>
<?php endforeach; ?>
</select>
</div>
</div>
<!-- Dentist Availability Status -->
<div id="walkinDentistAvailabilityStatus" class="hidden mb-4"></div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
<div>
<label for="walkin_scheduled_date" class="block text-sm font-medium text-[#4c6c9a] dark:text-slate-400 mb-2">Date *</label>
<input type="date" name="scheduled_date" id="walkin_scheduled_date" required min="<?php echo date('Y-m-d'); ?>" autocomplete="off" class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4" onchange="checkWalkInDentistAvailability()"/>
</div>
<div>
<label for="walkin_scheduled_time" class="block text-sm font-medium text-[#4c6c9a] dark:text-slate-400 mb-2">Time *</label>
<select name="scheduled_time" id="walkin_scheduled_time" required autocomplete="off" class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4" onchange="checkWalkInDentistAvailability()">
<option value="">-- Select Time --</option>
<?php
// Generate time slots from 9:00 AM to 5:00 PM in 30-minute intervals
$start_hour = 9;
$end_hour = 17;
for ($hour = $start_hour; $hour <= $end_hour; $hour++) {
    for ($minute = 0; $minute < 60; $minute += 30) {
        if ($hour == $end_hour && $minute > 0) break; // Stop at 5:00 PM
        $time_24 = sprintf('%02d:%02d:00', $hour, $minute);
        $time_12 = date('g:i A', strtotime($time_24));
        echo '<option value="' . sprintf('%02d:%02d', $hour, $minute) . '">' . $time_12 . '</option>';
    }
}
?>
</select>
</div>
</div>

<!-- Prerequisite Warning for Walk-In -->
<div id="walkin-prerequisite-blocking" class="hidden mb-4 p-4 bg-red-50 dark:bg-red-900/20 border-2 border-red-300 dark:border-red-700 rounded-lg">
<div class="flex items-start gap-3">
<span class="material-symbols-outlined text-red-600 dark:text-red-400 text-xl">warning</span>
<div class="flex-1">
<p class="text-sm font-semibold text-red-800 dark:text-red-300 mb-1">Prerequisite Required</p>
<p class="text-xs text-red-700 dark:text-red-400 mb-3" id="walkin-prerequisite-message">This service requires prerequisites. Please select a patient to check their prerequisite status.</p>
<div id="walkin-prerequisite-actions" class="flex gap-2 mt-2"></div>
</div>
</div>
</div>

<div class="flex gap-4">
<button type="button" id="walkinProceedBtn" onclick="proceedToWalkInPayment()" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 py-2 text-sm font-medium text-white shadow-md shadow-blue-500/20 hover:bg-primary-dark transition-all disabled:opacity-50 disabled:cursor-not-allowed">
<span class="material-symbols-outlined text-[20px]">payment</span>
<span>Proceed to Downpayment</span>
</button>
<button type="button" onclick="closeWalkInModal()" class="inline-flex items-center justify-center gap-2 rounded-lg border border-[#e7ecf3] dark:border-slate-700 px-6 py-2 text-sm font-medium text-[#4c6c9a] dark:text-slate-400 hover:bg-[#f0f4f8] dark:hover:bg-slate-800 transition-all">
<span>Cancel</span>
</button>
</div>
</form>
</div>
</div>

<script>
let walkInType = '';

// Functions are already defined in head section, but ensure they're available here too
if (typeof window.openWalkInModal === 'undefined') {
    window.openWalkInModal = function() {
    const modal = document.getElementById('walkInModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
            if (typeof walkInType !== 'undefined') {
        walkInType = '';
            }
            if (typeof resetWalkInForm === 'function') {
        resetWalkInForm();
    }
}
    };
}

if (typeof window.closeWalkInModal === 'undefined') {
    window.closeWalkInModal = function(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
    const modal = document.getElementById('walkInModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
            if (typeof walkInType !== 'undefined') {
        walkInType = '';
            }
            if (typeof resetWalkInForm === 'function') {
        resetWalkInForm();
    }
        }
    };
}

// setWalkInType is already defined in the head section, no need to redefine

function resetWalkInForm() {
    // Clear patient search
    const patientSearch = document.getElementById('walkin_patient_search');
    const patientIdInput = document.getElementById('walkin_patient_id');
    const patientResultsDiv = document.getElementById('walkin_patient_results');
    const selectedPatientDiv = document.getElementById('walkin_selected_patient');
    
    if (patientSearch) patientSearch.value = '';
    if (patientIdInput) patientIdInput.value = '';
    if (patientResultsDiv) patientResultsDiv.classList.add('hidden');
    if (selectedPatientDiv) selectedPatientDiv.classList.add('hidden');
    
    // Clear other form fields
    const form = document.getElementById('walkInForm');
    if (form) {
        form.reset();
    }
    
    const registrationTypeInput = document.getElementById('walkin_registration_type');
    if (registrationTypeInput) {
        registrationTypeInput.value = '';
    }
    
    const existingSection = document.getElementById('walkin-existing-section');
    const newSection = document.getElementById('walkin-new-section');
    if (existingSection) {
        existingSection.classList.add('hidden');
    }
    if (newSection) {
        newSection.classList.add('hidden');
    }
    
    const existingBtn = document.getElementById('walkin-existing-btn');
    const newBtn = document.getElementById('walkin-new-btn');
    if (existingBtn) {
        existingBtn.classList.remove('border-primary', 'bg-primary/5');
        existingBtn.classList.add('border-[#e7ecf3]', 'dark:border-slate-700');
    }
    if (newBtn) {
        newBtn.classList.remove('border-primary', 'bg-primary/5');
        newBtn.classList.add('border-[#e7ecf3]', 'dark:border-slate-700');
    }
    
    // Clear availability status
    const availabilityStatus = document.getElementById('walkinDentistAvailabilityStatus');
    if (availabilityStatus) {
        availabilityStatus.classList.add('hidden');
        availabilityStatus.innerHTML = '';
    }
    
    // Reset proceed button
    const proceedBtn = document.getElementById('walkinProceedBtn');
    if (proceedBtn) {
        proceedBtn.disabled = true;
        proceedBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
    
    // Clear prerequisite warning
    const prerequisiteDiv = document.getElementById('walkin-prerequisite-blocking');
    if (prerequisiteDiv) {
        prerequisiteDiv.classList.add('hidden');
    }
    
    // Reset walkInType variable
    if (typeof walkInType !== 'undefined') {
        walkInType = '';
    }
    
    // Clear availability state
    if (window.dentistAvailabilityState) {
        window.dentistAvailabilityState = null;
    }
    // Reset allergies container
    const allergiesContainer = document.getElementById('walkin-allergies-container');
    if (allergiesContainer) {
        allergiesContainer.innerHTML = '<div class="flex gap-2 mb-2"><input type="text" name="allergies[]" id="walkin_allergy_0" placeholder="Enter allergy (e.g., Peanuts)" autocomplete="off" aria-label="Allergy" class="flex-1 rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4"/><button type="button" onclick="addWalkInAllergyField()" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors"><span class="material-symbols-outlined text-sm">add</span></button></div>';
    }
    
    // Ensure all services are enabled when form is reset (no dentist selected by default)
    const serviceSelect = document.getElementById('walkin_service_id');
    if (serviceSelect) {
        Array.from(serviceSelect.options).forEach(option => {
            if (option.value) {
                option.disabled = false;
                option.style.display = '';
            }
        });
    }
}

let walkinAllergyCount = 0;
function addWalkInAllergyField() {
    walkinAllergyCount++;
    const container = document.getElementById('walkin-allergies-container');
    if (container) {
        const div = document.createElement('div');
        div.className = 'flex gap-2 mb-2';
        div.innerHTML = '<input type="text" name="allergies[]" id="walkin_allergy_' + walkinAllergyCount + '" placeholder="Enter allergy (e.g., Latex)" autocomplete="off" aria-label="Allergy" class="flex-1 rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4"/><button type="button" onclick="this.parentElement.remove()" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors" aria-label="Remove allergy"><span class="material-symbols-outlined text-sm">close</span></button>';
        container.appendChild(div);
    }
}

// Function to proceed to payment is now defined in head section above

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeWalkInModal();
    }
});

// Service prerequisites data from PHP
const walkinServicePrerequisites = <?php echo json_encode($service_prerequisites ?? []); ?>;
// Services with maintenance plans
const walkinServicesWithMaintenance = <?php echo json_encode($services_with_maintenance ?? []); ?>;
// Service options data for prerequisite switching
const walkinServiceOptions = <?php 
    $service_options = [];
    foreach ($services as $service) {
        $service_options[$service['service_id']] = [
            'id' => $service['service_id'],
            'name' => $service['service_name'],
            'price' => $service['base_price']
        ];
    }
    echo json_encode($service_options);
?>;

// Global flag to track if prerequisites are blocking
window.walkinPrerequisitesBlocking = false;

// Function to check prerequisites when service is selected
function checkWalkInPrerequisites() {
    const serviceSelect = document.getElementById('walkin_service_id');
    const registrationType = document.getElementById('walkin_registration_type').value;
    const patientId = document.getElementById('walkin_patient_id') ? document.getElementById('walkin_patient_id').value : '';
    
    if (!serviceSelect || !serviceSelect.value) {
        // If no service selected, reset blocking flag and hide prerequisite warning
        window.walkinPrerequisitesBlocking = false;
        const prerequisiteDiv = document.getElementById('walkin-prerequisite-blocking');
        if (prerequisiteDiv) {
            prerequisiteDiv.classList.add('hidden');
        }
        return;
    }
    
    const serviceId = parseInt(serviceSelect.value);
    const prerequisiteDiv = document.getElementById('walkin-prerequisite-blocking');
    
    if (!prerequisiteDiv) return;
    
    // Get proceed button
    const proceedBtn = document.getElementById('walkinProceedBtn');
    
    // Reset blocking flag first
    window.walkinPrerequisitesBlocking = false;
    
    // Check if service has prerequisites - check both string and number keys
    // Also check if walkinServicePrerequisites is defined and not null
    let prereqs = null;
    if (walkinServicePrerequisites) {
        prereqs = walkinServicePrerequisites[serviceId] || walkinServicePrerequisites[String(serviceId)] || null;
    }
    
    // Only consider it has prerequisites if prereqs is a non-empty array
    const hasPrerequisites = prereqs && Array.isArray(prereqs) && prereqs.length > 0;
    
    if (hasPrerequisites) {
        prerequisiteDiv.classList.remove('hidden');
        const msgEl = document.getElementById('walkin-prerequisite-message');
        const actionsEl = document.getElementById('walkin-prerequisite-actions');
        
        if (msgEl && actionsEl) {
            const prereqNames = prereqs.map(p => p.required_service_name).join(' or ');
            
            if (registrationType === 'new') {
                // For new patients, prerequisites are always required (no maintenance history)
                window.walkinPrerequisitesBlocking = true;
                msgEl.textContent = 'This service requires prerequisites: ' + prereqNames + '. As a new patient, you must book the prerequisite service first. Please switch to a prerequisite service to proceed.';
                
                // Block proceed button for new patients if prerequisites are required - CANNOT proceed with downpayment
                if (proceedBtn) {
                    proceedBtn.disabled = true;
                    proceedBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
                
                // Add buttons to change to prerequisite services
                actionsEl.innerHTML = '';
                prereqs.forEach(prereq => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'text-xs bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg font-semibold transition-colors flex items-center gap-1';
                    btn.innerHTML = '<span class="material-symbols-outlined text-sm">swap_horiz</span> Switch to ' + prereq.required_service_name;
                    btn.onclick = function() {
                        // Find and select the prerequisite service
                        const serviceSelect = document.getElementById('walkin_service_id');
                        if (serviceSelect) {
                            // Find option with matching service_id
                            let found = false;
                            for (let i = 0; i < serviceSelect.options.length; i++) {
                                if (serviceSelect.options[i].value == prereq.required_service_id) {
                                    serviceSelect.value = prereq.required_service_id;
                                    serviceSelect.dispatchEvent(new Event('change'));
                                    found = true;
                                    break;
                                }
                            }
                            if (!found) {
                                alert('Prerequisite service not found in the service list.');
                            }
                        }
                    };
                    actionsEl.appendChild(btn);
                });
            } else if (registrationType === 'existing') {
                // For existing patients, check if they have valid prerequisites via AJAX
                if (patientId) {
                    // Show loading message
                    msgEl.textContent = 'Checking prerequisites...';
                    actionsEl.innerHTML = '';
                    
                    // Block button initially
                    if (proceedBtn) {
                        proceedBtn.disabled = true;
                        proceedBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    }
                    
                    // Check prerequisites via AJAX (this will also check for maintenance)
                    fetch('appointments.php?action=check_prerequisites&patient_id=' + patientId + '&service_id=' + serviceId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.has_valid_prerequisite) {
                                // Patient has valid prerequisites OR has active maintenance - enable button
                                window.walkinPrerequisitesBlocking = false;
                                msgEl.textContent = 'Prerequisites verified. You can proceed with booking.';
                                prerequisiteDiv.classList.add('hidden');
                                actionsEl.innerHTML = '';
                                if (proceedBtn) {
                                    // Don't force enable - let updateProceedButtonState handle it
                                    if (typeof updateProceedButtonState === 'function') {
                                        updateProceedButtonState();
                                    }
                                }
                            } else {
                                // Patient lacks prerequisites - block button - CANNOT proceed with downpayment
                                window.walkinPrerequisitesBlocking = true;
                                msgEl.textContent = 'This service requires prerequisites: ' + prereqNames + '. Prerequisites are missing or expired. Please switch to a prerequisite service to proceed.';
                                
                                // Block proceed button
                                if (proceedBtn) {
                                    proceedBtn.disabled = true;
                                    proceedBtn.classList.add('opacity-50', 'cursor-not-allowed');
                                }
                                
                                // Add buttons to change to prerequisite services
                                actionsEl.innerHTML = '';
                                prereqs.forEach(prereq => {
                                    const btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'text-xs bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg font-semibold transition-colors flex items-center gap-1';
                                    btn.innerHTML = '<span class="material-symbols-outlined text-sm">swap_horiz</span> Switch to ' + prereq.required_service_name;
                                    btn.onclick = function() {
                                        // Find and select the prerequisite service
                                        const serviceSelect = document.getElementById('walkin_service_id');
                                        if (serviceSelect) {
                                            // Find option with matching service_id
                                            let found = false;
                                            for (let i = 0; i < serviceSelect.options.length; i++) {
                                                if (serviceSelect.options[i].value == prereq.required_service_id) {
                                                    serviceSelect.value = prereq.required_service_id;
                                                    serviceSelect.dispatchEvent(new Event('change'));
                                                    found = true;
                                                    break;
                                                }
                                            }
                                            if (!found) {
                                                alert('Prerequisite service not found in the service list.');
                                            }
                                        }
                                    };
                                    actionsEl.appendChild(btn);
                                });
                                
                                // Keep button disabled - CANNOT proceed with downpayment
                                window.walkinPrerequisitesBlocking = true;
                                if (proceedBtn) {
                                    proceedBtn.disabled = true;
                                    proceedBtn.classList.add('opacity-50', 'cursor-not-allowed');
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error checking prerequisites:', error);
                            // On error, block button to be safe
                            msgEl.textContent = 'This service requires prerequisites: ' + prereqNames + '. Error checking prerequisites. Please switch to a prerequisite service to proceed.';
                            actionsEl.innerHTML = '';
                            prereqs.forEach(prereq => {
                                const btn = document.createElement('button');
                                btn.type = 'button';
                                btn.className = 'text-xs bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg font-semibold transition-colors flex items-center gap-1';
                                btn.innerHTML = '<span class="material-symbols-outlined text-sm">swap_horiz</span> Switch to ' + prereq.required_service_name;
                                btn.onclick = function() {
                                    const serviceSelect = document.getElementById('walkin_service_id');
                                    if (serviceSelect) {
                                        for (let i = 0; i < serviceSelect.options.length; i++) {
                                            if (serviceSelect.options[i].value == prereq.required_service_id) {
                                                serviceSelect.value = prereq.required_service_id;
                                                serviceSelect.dispatchEvent(new Event('change'));
                                                break;
                                            }
                                        }
                                    }
                                };
                                actionsEl.appendChild(btn);
                            });
                            if (proceedBtn) {
                                proceedBtn.disabled = true;
                                proceedBtn.classList.add('opacity-50', 'cursor-not-allowed');
                            }
                        });
                } else {
                    msgEl.textContent = 'Please select a patient to check prerequisites.';
                    actionsEl.innerHTML = '';
                    // Block button if no patient selected
                    if (proceedBtn) {
                        proceedBtn.disabled = true;
                        proceedBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    }
                }
            } else {
                // No registration type selected
                if (proceedBtn) {
                    proceedBtn.disabled = true;
                    proceedBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
            }
        }
    } else {
        // Service has NO prerequisites - hide warning and reset blocking flag
        prerequisiteDiv.classList.add('hidden');
        window.walkinPrerequisitesBlocking = false;
        // Don't force enable/disable button here - let updateProceedButtonState handle it
        // Just ensure the blocking flag is reset
    }
}

// Add event listeners
document.addEventListener('DOMContentLoaded', function() {
    const serviceSelect = document.getElementById('walkin_service_id');
    const patientSelect = document.getElementById('walkin_patient_id');
    const registrationTypeInput = document.getElementById('walkin_registration_type');
    const dateInput = document.getElementById('walkin_scheduled_date');
    const timeSelect = document.getElementById('walkin_scheduled_time');
    
    // Function to update button state based on form completion
    function updateProceedButtonState() {
        const proceedBtn = document.getElementById('walkinProceedBtn');
        if (!proceedBtn) return;
        
        // FIRST CHECK: If prerequisites are blocking, disable button immediately - CANNOT proceed with downpayment
        if (window.walkinPrerequisitesBlocking) {
            proceedBtn.disabled = true;
            proceedBtn.classList.add('opacity-50', 'cursor-not-allowed');
            return;
        }
        
        const registrationType = registrationTypeInput ? registrationTypeInput.value : '';
        const serviceId = serviceSelect ? serviceSelect.value : '';
        const scheduledDate = dateInput ? dateInput.value : '';
        const scheduledTime = timeSelect ? timeSelect.value : '';
        const dentistId = document.getElementById('walkin_dentist_id') ? document.getElementById('walkin_dentist_id').value : '';
        
        // Basic validation - disable if form is incomplete
        // Note: dentistId is optional (system can auto-select)
        if (!registrationType || !serviceId || !scheduledDate || !scheduledTime) {
            proceedBtn.disabled = true;
            proceedBtn.classList.add('opacity-50', 'cursor-not-allowed');
            return;
        }
        
        // Validate date/time - block past appointments
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const selectedDate = new Date(scheduledDate);
        selectedDate.setHours(0, 0, 0, 0);
        
        if (selectedDate < today) {
            proceedBtn.disabled = true;
            proceedBtn.classList.add('opacity-50', 'cursor-not-allowed');
            return;
        }
        
        // If same day, check time
        if (selectedDate.getTime() === today.getTime() && scheduledTime) {
            const now = new Date();
            const [hours, minutes] = scheduledTime.split(':');
            const selectedDateTime = new Date();
            selectedDateTime.setHours(parseInt(hours), parseInt(minutes), 0, 0);
            
            if (selectedDateTime < now) {
                proceedBtn.disabled = true;
                proceedBtn.classList.add('opacity-50', 'cursor-not-allowed');
                return;
            }
        }
        
        // Additional validation for existing/new patient fields
        if (registrationType === 'existing') {
            const patientId = document.getElementById('walkin_patient_id') ? document.getElementById('walkin_patient_id').value : '';
            if (!patientId) {
                proceedBtn.disabled = true;
                proceedBtn.classList.add('opacity-50', 'cursor-not-allowed');
                return;
            }
        } else if (registrationType === 'new') {
            const firstName = document.getElementById('walkin_first_name') ? document.getElementById('walkin_first_name').value : '';
            const lastName = document.getElementById('walkin_last_name') ? document.getElementById('walkin_last_name').value : '';
            const email = document.getElementById('walkin_email') ? document.getElementById('walkin_email').value : '';
            const phone = document.getElementById('walkin_phone') ? document.getElementById('walkin_phone').value : '';
            const dob = document.getElementById('walkin_dob') ? document.getElementById('walkin_dob').value : '';
            if (!firstName || !lastName || !email || !phone || !dob) {
                proceedBtn.disabled = true;
                proceedBtn.classList.add('opacity-50', 'cursor-not-allowed');
                return;
            }
            
            // Validate email format
            const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            if (!emailPattern.test(email)) {
                proceedBtn.disabled = true;
                proceedBtn.classList.add('opacity-50', 'cursor-not-allowed');
                return;
            }
            
            // Validate phone format
            const phonePattern = /^[\d+\-() ]{7,20}$/;
            const phoneDigitsOnly = phone.replace(/[\D]/g, '');
            if (!phonePattern.test(phone) || phoneDigitsOnly.length < 7 || phoneDigitsOnly.length > 20) {
                proceedBtn.disabled = true;
                proceedBtn.classList.add('opacity-50', 'cursor-not-allowed');
                return;
            }
        }
        
        // If form is complete, check prerequisites (which will handle button state)
        // But also ensure button is enabled if no prerequisites exist
        checkWalkInPrerequisites();
        
        // Double-check: if prerequisites check didn't disable the button, ensure it's enabled
        // (This handles the case where checkWalkInPrerequisites hasn't run yet or returned early)
        // BUT: Also check availability state if dentist is selected
        setTimeout(function() {
            const proceedBtn = document.getElementById('walkinProceedBtn');
            if (!proceedBtn) return;
            
            // Check if prerequisites are blocking (use global flag)
            const prerequisitesBlocking = window.walkinPrerequisitesBlocking || false;
            
            // Check availability ONLY if dentist is selected (if no dentist, system will auto-select)
            let availabilityBlocking = false;
            if (dentistId) {
                // If availability state doesn't exist yet, block button
                if (!window.dentistAvailabilityState) {
                    availabilityBlocking = true;
                } else if (!window.dentistAvailabilityState.available) {
                    availabilityBlocking = true;
                }
            }
            // If no dentist selected, don't block based on availability (system will auto-select)
            
            // Enable button only if no prerequisites blocking AND no availability issues (if dentist selected)
            if (!prerequisitesBlocking && !availabilityBlocking) {
                proceedBtn.disabled = false;
                proceedBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                proceedBtn.disabled = true;
                proceedBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }, 100);
    }
    
    // Function to filter services based on selected dentist specialization
    function filterServicesByDentist() {
        const dentistSelect = document.getElementById('walkin_dentist_id');
        const serviceSelect = document.getElementById('walkin_service_id');
        
        if (!dentistSelect || !serviceSelect) return;
        
        const selectedDentistId = parseInt(dentistSelect.value);
        
        // If no dentist selected (system will select), show all services
        if (!selectedDentistId || selectedDentistId === 0) {
            Array.from(serviceSelect.options).forEach(option => {
                if (option.value) {
                    option.disabled = false;
                    option.style.display = '';
                }
            });
            return;
        }
        
        // Get dentist's specializations
        const dentistServices = window.dentistServicesData || {};
        const dentistServiceIds = dentistServices[selectedDentistId];
        
        // If no specialization data, show all services (backward compatibility)
        if (!dentistServiceIds) {
            Array.from(serviceSelect.options).forEach(option => {
                if (option.value) {
                    option.disabled = false;
                    option.style.display = '';
                }
            });
            return;
        }
        
        // Filter services
        Array.from(serviceSelect.options).forEach(option => {
            if (!option.value) return; // Skip empty option
            
            const serviceId = parseInt(option.value);
            let shouldShow = false;
            
            if (dentistServiceIds === 'all') {
                // Dentist can perform all services
                shouldShow = true;
            } else if (Array.isArray(dentistServiceIds) && dentistServiceIds.includes(serviceId)) {
                // Dentist has this service in specialization
                shouldShow = true;
            }
            
            if (shouldShow) {
                option.disabled = false;
                option.style.display = '';
            } else {
                option.disabled = true;
                option.style.display = 'none';
            }
        });
        
        // If current selected service is not available, clear it
        if (serviceSelect.value) {
            const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
            if (selectedOption && selectedOption.disabled) {
                serviceSelect.value = '';
                if (typeof validateDentistServiceCompatibility === 'function') {
                    validateDentistServiceCompatibility();
                }
            }
        }
    }
    
    // Function to validate dentist-service compatibility (specialization check)
    // This function now only clears old errors - actual validation is done by availability check
    // which properly handles services with no required specializations
    function validateDentistServiceCompatibility() {
        const dentistSelect = document.getElementById('walkin_dentist_id');
        const serviceSelect = document.getElementById('walkin_service_id');
        const availabilityStatus = document.getElementById('walkinDentistAvailabilityStatus');
        
        if (!dentistSelect || !serviceSelect || !availabilityStatus) return true;
        
        const selectedDentistId = parseInt(dentistSelect.value);
        const selectedServiceId = parseInt(serviceSelect.value);
        
        // If no dentist or no service selected, clear any previous compatibility errors
        if (!selectedDentistId || !selectedServiceId) {
            const currentHTML = availabilityStatus.innerHTML;
            if (currentHTML.includes('Service Incompatible')) {
                availabilityStatus.classList.add('hidden');
                availabilityStatus.innerHTML = '';
            }
            return true;
        }
        
        // Don't show compatibility error here - let the availability check handle it
        // The availability check properly handles:
        // 1. Services with no required specializations (any dentist can do it)
        // 2. Services with required specializations (dentist must have matching spec)
        // Just clear any old compatibility errors
        const currentHTML = availabilityStatus.innerHTML;
        if (currentHTML.includes('Service Incompatible')) {
            availabilityStatus.classList.add('hidden');
            availabilityStatus.innerHTML = '';
        }
        
        return true;
    }
    
    const dentistSelect = document.getElementById('walkin_dentist_id');
    if (dentistSelect) {
        dentistSelect.addEventListener('change', function() {
            updateProceedButtonState();
            
            // Filter services based on dentist specialization
            filterServicesByDentist();
            
            // First validate service specialization
            const isValid = validateDentistServiceCompatibility();
            
            // Clear availability state when dentist changes
            window.dentistAvailabilityState = null;
            
            // If dentist is selected, check availability if all fields are filled
            if (this.value) {
                const dateInput = document.getElementById('walkin_scheduled_date');
                const timeSelect = document.getElementById('walkin_scheduled_time');
                const serviceSelect = document.getElementById('walkin_service_id');
                
                if (isValid && dateInput && dateInput.value && timeSelect && timeSelect.value && serviceSelect && serviceSelect.value) {
                    // All fields filled - check availability immediately
                    checkWalkInDentistAvailability();
                } else {
                    // Show message to fill remaining fields
                    const availabilityStatus = document.getElementById('walkinDentistAvailabilityStatus');
                    if (availabilityStatus) {
                        availabilityStatus.classList.remove('hidden');
                        availabilityStatus.innerHTML = '<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3"><p class="text-sm text-blue-800 dark:text-blue-300">Dentist selected. Please select date, time, and service to check availability.</p></div>';
                    }
                }
            } else {
                // No dentist selected - clear availability status
                const availabilityStatus = document.getElementById('walkinDentistAvailabilityStatus');
                if (availabilityStatus) {
                    availabilityStatus.classList.add('hidden');
                    availabilityStatus.innerHTML = '';
                }
            }
            
            // Update button state
            updateProceedButtonState();
        });
    }
    
    // checkWalkInDentistAvailability is already defined in the head section, no need to redefine
    
    if (serviceSelect) {
        serviceSelect.addEventListener('change', function() {
            // Reset prerequisite blocking flag when service changes
            window.walkinPrerequisitesBlocking = false;
            
            // Only validate compatibility if dentist is also selected
            const dentistSelect = document.getElementById('walkin_dentist_id');
            if (dentistSelect && dentistSelect.value) {
                validateDentistServiceCompatibility();
            } else {
                // Clear any previous compatibility errors if no dentist selected
                const availabilityStatus = document.getElementById('walkinDentistAvailabilityStatus');
                if (availabilityStatus) {
                    const currentHTML = availabilityStatus.innerHTML;
                    if (currentHTML.includes('Service Incompatible')) {
                        availabilityStatus.classList.add('hidden');
                        availabilityStatus.innerHTML = '';
                    }
                }
            }
            
            // Check prerequisites (will reset flag if service has no prerequisites)
            checkWalkInPrerequisites();
            updateProceedButtonState();
            
            // If dentist, date, and time are selected, check availability
            const dateInput = document.getElementById('walkin_scheduled_date');
            const timeSelect = document.getElementById('walkin_scheduled_time');
            
            if (dentistSelect && dentistSelect.value && dateInput && dateInput.value && timeSelect && timeSelect.value && this.value) {
                checkWalkInDentistAvailability();
            }
        });
    }
    if (patientSelect) {
        patientSelect.addEventListener('change', function() {
            checkWalkInPrerequisites();
            updateProceedButtonState();
        });
    }
    // Function to update time options based on selected date and dentist schedule
    function updateTimeOptions() {
        const dateInput = document.getElementById('walkin_scheduled_date');
        const timeSelect = document.getElementById('walkin_scheduled_time');
        const dentistSelect = document.getElementById('walkin_dentist_id');
        
        if (!dateInput || !timeSelect) return;
        
        const selectedDate = dateInput.value;
        const selectedDentist = dentistSelect ? dentistSelect.value : '';
        const today = new Date().toISOString().split('T')[0];
        const now = new Date();
        const currentHour = now.getHours();
        const currentMinute = now.getMinutes();
        
        // If dentist is selected and date is selected, check availability to get schedule and occupied times
        if (selectedDentist && selectedDate) {
            // This will be handled by checkWalkInDentistAvailability which updates occupied times
            // For now, just handle past time blocking
        }
        
        // If today is selected, disable past time slots
        if (selectedDate === today) {
            Array.from(timeSelect.options).forEach(option => {
                if (option.value) {
                    const [hours, minutes] = option.value.split(':');
                    const optionHour = parseInt(hours);
                    const optionMinute = parseInt(minutes);
                    
                    if (optionHour < currentHour || (optionHour === currentHour && optionMinute < currentMinute)) {
                        option.disabled = true;
                        option.style.display = 'none';
                    } else {
                        // Check if this time is occupied (from availability check)
                        if (window.occupiedTimes && window.occupiedTimes.includes(option.value)) {
                            option.disabled = true;
                            option.style.color = '#9ca3af';
                        } else {
                            option.disabled = false;
                            option.style.color = '';
                        }
                        option.style.display = '';
                    }
                }
            });
        } else {
            // For future dates, check occupied times but don't block past times
            Array.from(timeSelect.options).forEach(option => {
                if (option.value) {
                    // Check if this time is occupied (from availability check)
                    if (window.occupiedTimes && window.occupiedTimes.includes(option.value)) {
                        option.disabled = true;
                        option.style.color = '#9ca3af';
                    } else {
                        option.disabled = false;
                        option.style.color = '';
                    }
                    option.style.display = '';
                }
            });
        }
    }
    
    if (dateInput) {
        dateInput.addEventListener('change', function() {
            updateTimeOptions();
            updateProceedButtonState();
            // Check availability when date changes (if dentist and time are selected)
            const dentistSelect = document.getElementById('walkin_dentist_id');
            const timeSelect = document.getElementById('walkin_scheduled_time');
            if (dentistSelect && dentistSelect.value && timeSelect && timeSelect.value) {
                checkWalkInDentistAvailability();
            }
        });
    }
    if (timeSelect) {
        timeSelect.addEventListener('change', updateProceedButtonState);
    }
    
    // Initialize time options on page load
    if (dateInput && dateInput.value) {
        updateTimeOptions();
    }
    
    // setWalkInType is already defined on window with all necessary functionality
    // No need to override - it already calls checkWalkInPrerequisites and updateProceedButtonState
    
    // Check prerequisites on page load if service is already selected
    if (serviceSelect && serviceSelect.value) {
        checkWalkInPrerequisites();
    }
    
    // Initialize button state on page load
    updateProceedButtonState();
    
    // Also check button state periodically to ensure it's correct
    // This handles cases where prerequisites check completes after form validation
    setTimeout(function() {
        updateProceedButtonState();
    }, 500);
});
</script>

<?php
// Check if payment modal should be shown
$walkin_payment_required = isset($_GET['walkin_payment']) && isset($_SESSION['walkin_pending_appointment']);
$walkin_pending_appointment = $_SESSION['walkin_pending_appointment'] ?? null;
?>

<?php if ($walkin_payment_required && $walkin_pending_appointment): ?>
<!-- Walk-In Payment Modal -->
<div id="walkinPaymentModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" style="display: flex !important; visibility: visible !important;">
<div class="bg-white dark:bg-[#1a2634] rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto">
<div class="flex items-center justify-between mb-4">
<h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Payment Required</h3>
<button onclick="closeWalkInPaymentModal()" class="text-[#4c6c9a] hover:text-[#0d131b] dark:text-slate-400 dark:hover:text-white">
<span class="material-symbols-outlined">close</span>
</button>
</div>

<div class="mb-4">
<p class="text-sm text-[#4c6c9a] dark:text-slate-400 mb-4">
To confirm the walk-in appointment, please process the downpayment of <strong class="text-primary">â‚±<?php echo number_format($walkin_pending_appointment['downpayment_amount'], 2); ?></strong> (35% of total service price).
</p>

<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 mb-4">
<p class="text-xs text-blue-800 dark:text-blue-300 flex items-start gap-2">
<span class="material-symbols-outlined text-base mt-0.5">verified</span>
<span><strong>Subject to Discount Verification:</strong> This downpayment is charged at the standard rate. If the patient has a valid PWD or Senior Citizen ID, please verify it. Any discount will be applied to the total treatment cost, and overpayments will be credited to the remaining balance.</span>
</p>
</div>

<div class="space-y-2 text-sm mb-4">
<div class="flex justify-between">
<span class="text-[#4c6c9a] dark:text-slate-400">Service:</span>
<span class="font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($walkin_pending_appointment['service_name']); ?></span>
</div>
<div class="flex justify-between">
<span class="text-[#4c6c9a] dark:text-slate-400">Total Price:</span>
<span class="font-medium text-[#0d131b] dark:text-white">â‚±<?php echo number_format($walkin_pending_appointment['service_price'], 2); ?></span>
</div>
<div class="flex justify-between border-t border-[#e7ecf3] dark:border-slate-700 pt-2">
<span class="text-[#4c6c9a] dark:text-slate-400">Downpayment (35%):</span>
<span class="font-bold text-primary text-lg">â‚±<?php echo number_format($walkin_pending_appointment['downpayment_amount'], 2); ?></span>
</div>
</div>
</div>

<div class="mb-4">
<p class="text-xs font-semibold uppercase tracking-wider text-[#4c6c9a] mb-3">Payment Method <span class="text-red-500">*</span></p>
<div class="flex gap-4 mb-4">
<label class="flex-1 cursor-pointer">
<input type="radio" name="walkin_payment_method" value="Cash" id="walkin_payment_cash" class="peer sr-only" checked/>
<div class="flex flex-col items-center justify-center p-4 rounded-xl border-2 border-[#e7ecf3] dark:border-slate-600 bg-slate-50 dark:bg-slate-800 peer-checked:border-primary peer-checked:bg-primary/5 transition-all hover:bg-slate-100 dark:hover:bg-slate-700">
<span class="material-symbols-outlined text-2xl mb-2 text-[#4c6c9a] peer-checked:text-primary">payments</span>
<span class="font-medium text-sm text-[#0d131b] dark:text-white peer-checked:text-primary">Cash</span>
</div>
</label>
<label class="flex-1 cursor-pointer">
<input type="radio" name="walkin_payment_method" value="PayMongo" id="walkin_payment_paymongo" class="peer sr-only"/>
<div class="flex flex-col items-center justify-center p-4 rounded-xl border-2 border-[#e7ecf3] dark:border-slate-600 bg-slate-50 dark:bg-slate-800 peer-checked:border-primary peer-checked:bg-primary/5 transition-all hover:bg-slate-100 dark:hover:bg-slate-700">
<span class="material-symbols-outlined text-2xl mb-2 text-[#4c6c9a] peer-checked:text-primary">account_balance_wallet</span>
<span class="font-medium text-sm text-[#0d131b] dark:text-white peer-checked:text-primary">PayMongo</span>
</div>
</label>
</div>

<!-- Cash Payment Fields -->
<div id="walkinCashFields" class="flex flex-col gap-4 pt-4 border-t border-dashed border-[#e7ecf3] dark:border-slate-700">
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<label class="flex flex-col flex-1">
<p class="text-[#0d131b] dark:text-slate-200 text-sm font-medium leading-normal pb-2">Amount Received <span class="text-red-500">*</span></p>
<input type="number" step="0.01" id="walkinAmountReceived" required class="w-full rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white h-12 px-4 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="0.00"/>
</label>
<label class="flex flex-col flex-1">
<p class="text-[#0d131b] dark:text-slate-200 text-sm font-medium leading-normal pb-2">Change</p>
<input type="number" step="0.01" id="walkinChangeAmount" readonly class="w-full rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-slate-100 dark:bg-slate-800 text-[#0d131b] dark:text-white h-12 px-4 text-sm" placeholder="0.00"/>
</label>
</div>
</div>

<!-- PayMongo Fields -->
<div id="walkinPayMongoFields" class="hidden flex-col gap-4 pt-4 border-t border-dashed border-[#e7ecf3] dark:border-slate-700">
<div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
<p class="text-sm text-blue-700 dark:text-blue-400 flex items-start gap-2">
<span class="material-symbols-outlined text-base mt-0.5">info</span>
<span>Clicking "Process Payment" will redirect to PayMongo checkout page.</span>
</p>
</div>
</div>
</div>

<form method="POST" action="" id="walkinPaymentForm">
<input type="hidden" name="action" value="process_walkin_cash">
<input type="hidden" name="appointment_id" value="<?php echo $walkin_pending_appointment['appointment_id']; ?>">
<input type="hidden" name="amount" value="<?php echo $walkin_pending_appointment['downpayment_amount']; ?>">
<input type="hidden" name="downpayment_amount" value="<?php echo $walkin_pending_appointment['downpayment_amount']; ?>">
<input type="hidden" name="amount_received" id="walkinFormAmountReceived" value="">

<div class="flex gap-3">
<button type="button" onclick="closeWalkInPaymentModal()" class="flex-1 rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white py-2 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
Cancel
</button>
<button type="submit" id="walkinProcessPaymentBtn" class="flex-1 rounded-lg bg-primary text-white py-2 text-sm font-semibold hover:bg-primary-dark transition-colors disabled:opacity-50 disabled:cursor-not-allowed" disabled>
Process Payment
</button>
</div>
</form>
</div>
</div>

<script>
function closeWalkInPaymentModal() {
    const modal = document.getElementById('walkinPaymentModal');
    if (modal) {
        modal.style.display = 'none';
        modal.classList.add('hidden');
    }
}

// Initialize payment modal immediately (not waiting for DOMContentLoaded)
(function initWalkInPaymentModal() {
    const paymentMethodRadios = document.querySelectorAll('input[name="walkin_payment_method"]');
    const cashFields = document.getElementById('walkinCashFields');
    const paymongoFields = document.getElementById('walkinPayMongoFields');
    const amountReceived = document.getElementById('walkinAmountReceived');
    const changeAmount = document.getElementById('walkinChangeAmount');
    const formAmountReceived = document.getElementById('walkinFormAmountReceived');
    const paymentForm = document.getElementById('walkinPaymentForm');
    const processBtn = document.getElementById('walkinProcessPaymentBtn');
    const downpaymentAmount = <?php echo $walkin_pending_appointment['downpayment_amount']; ?>;
    
    // Wait for DOM to be ready before initializing
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initWalkInPaymentModal);
        return;
    }
    
    if (!paymentForm || !processBtn) {
        // Try again after a short delay
        setTimeout(initWalkInPaymentModal, 100);
        return;
    }
    
    // Function to toggle payment method
    function togglePaymentMethod(method) {
        if (method === 'Cash') {
            // Show cash fields
            if (cashFields) {
                cashFields.classList.remove('hidden');
                cashFields.style.display = 'flex';
            }
            // Hide PayMongo fields
            if (paymongoFields) {
                paymongoFields.classList.add('hidden');
                paymongoFields.style.display = 'none';
            }
            // Set form for cash payment
            if (paymentForm) {
                const actionInput = paymentForm.querySelector('input[name="action"]');
                if (actionInput) actionInput.value = 'process_walkin_cash';
                paymentForm.action = '';
                paymentForm.method = 'POST';
            }
            // Add required attribute for Cash
            if (amountReceived) {
                amountReceived.setAttribute('required', 'required');
            }
            // Re-check amount received to enable/disable button
            if (amountReceived && processBtn) {
                const received = parseFloat(amountReceived.value) || 0;
                if (received >= downpaymentAmount && received > 0) {
                    processBtn.disabled = false;
                    processBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                } else {
                    processBtn.disabled = true;
                    processBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
            }
        } else if (method === 'PayMongo') {
            // Hide cash fields completely
            if (cashFields) {
                cashFields.classList.add('hidden');
                cashFields.style.display = 'none';
            }
            // Show PayMongo fields
            if (paymongoFields) {
                paymongoFields.classList.remove('hidden');
                paymongoFields.style.display = 'flex';
            }
            // Set form for PayMongo payment
            if (paymentForm) {
                const actionInput = paymentForm.querySelector('input[name="action"]');
                if (actionInput) actionInput.value = '';
                paymentForm.action = 'process_downpayment_paymongo.php';
                paymentForm.method = 'POST';
            }
            // Remove required attribute from amount received for PayMongo
            if (amountReceived) {
                amountReceived.removeAttribute('required');
                amountReceived.value = ''; // Clear the value
            }
            // Clear the hidden amount_received field for PayMongo
            if (formAmountReceived) {
                formAmountReceived.value = '';
            }
            // Enable button immediately for PayMongo
            if (processBtn) {
                processBtn.disabled = false;
                processBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }
    }
    
    // Add event listeners to payment method radios
    if (paymentMethodRadios.length > 0) {
        paymentMethodRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                togglePaymentMethod(this.value);
            });
        });
    }
    
    // Calculate change for cash payments only
    if (amountReceived && changeAmount && formAmountReceived && processBtn) {
        amountReceived.addEventListener('input', function() {
            // Check if Cash payment is selected
            const selectedPaymentMethod = document.querySelector('input[name="walkin_payment_method"]:checked');
            if (selectedPaymentMethod && selectedPaymentMethod.value !== 'Cash') {
                return; // Don't validate amount for PayMongo
            }
            
            const received = parseFloat(this.value) || 0;
            const change = received - downpaymentAmount;
            changeAmount.value = change >= 0 ? change.toFixed(2) : '0.00';
            formAmountReceived.value = received.toFixed(2);
            
            // Enable/disable submit button only for Cash payments
            if (received >= downpaymentAmount && received > 0) {
                processBtn.disabled = false;
                processBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                processBtn.disabled = true;
                processBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        });
    }
    
    // Initialize based on selected payment method
    const initialPaymentMethod = document.querySelector('input[name="walkin_payment_method"]:checked');
    if (initialPaymentMethod) {
        togglePaymentMethod(initialPaymentMethod.value);
    } else {
        // Default to Cash
        togglePaymentMethod('Cash');
    }
    
    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeWalkInPaymentModal();
        }
    });
})();
</script>
<?php endif; ?>

<!-- Prerequisite Warning for Walk-In -->
<div id="walkin-prerequisite-blocking" class="hidden mb-4 p-4 bg-red-50 dark:bg-red-900/20 border-2 border-red-300 dark:border-red-700 rounded-lg">
<div class="flex items-start gap-3">
<span class="material-symbols-outlined text-red-600 dark:text-red-400 text-xl">warning</span>
<div class="flex-1">
<p class="text-sm font-semibold text-red-800 dark:text-red-300 mb-1">Prerequisite Required</p>
<p class="text-xs text-red-700 dark:text-red-400 mb-3" id="walkin-prerequisite-message"></p>
</div>
</div>
</div>


<?php endif; // close if-admin-receptionist walk-in block ?>
<?php $extra_scripts = ob_get_clean(); include ROOT_PATH . '/views/layout/footer.php'; ?>
