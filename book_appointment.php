<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';
require_once 'config/security.php';

// Include dentist availability functions
$dentist_availability_path = __DIR__ . '/functions/dentist_availability.php';
if (!file_exists($dentist_availability_path)) {
    die('Error: dentist_availability.php file not found at: ' . $dentist_availability_path);
}

// Try to include the file and catch any errors
try {
    require_once $dentist_availability_path;
} catch (Throwable $e) {
    die('Error loading dentist_availability.php: ' . htmlspecialchars($e->getMessage()) . ' at line ' . $e->getLine());
}

// Verify required functions exist
if (!function_exists('checkDentistFullAvailability')) {
    // Check if any functions from the file were loaded
    $all_functions = get_defined_functions();
    $user_functions = $all_functions['user'];
    $dentist_functions = array_filter($user_functions, function($func) {
        return stripos($func, 'dentist') !== false || stripos($func, 'Dentist') !== false;
    });
    
    // Check if file is readable
    $is_readable = is_readable($dentist_availability_path);
    
    // Try to get file contents to check if it's valid PHP
    $file_contents = @file_get_contents($dentist_availability_path);
    $has_function = $file_contents && (stripos($file_contents, 'function checkDentistFullAvailabilityDetailed') !== false);
    
    // Also check for variations of the function name
    $has_function_variations = [
        'checkDentistFullAvailabilityDetailed' => stripos($file_contents, 'checkDentistFullAvailabilityDetailed') !== false,
        'checkdentistfullavailabilitydetailed' => stripos($file_contents, 'checkdentistfullavailabilitydetailed') !== false,
        'CheckDentistFullAvailabilityDetailed' => stripos($file_contents, 'CheckDentistFullAvailabilityDetailed') !== false,
    ];
    
    // Extract a sample of the file around where the function should be
    $sample_start = stripos($file_contents, 'function checkDentistFullAvailability') !== false 
        ? stripos($file_contents, 'function checkDentistFullAvailability') 
        : (stripos($file_contents, 'checkDentistFullAvailability') !== false 
            ? stripos($file_contents, 'checkDentistFullAvailability') 
            : null);
    $file_sample = $sample_start !== null 
        ? substr($file_contents, max(0, $sample_start - 50), 200) 
        : 'Function name not found in file';
    
    // Expected file size (local version with checkDentistFullAvailabilityDetailed)
    $expected_file_size = 27835; // Local file size
    $actual_file_size = $file_contents ? strlen($file_contents) : 0;
    $size_match = abs($actual_file_size - $expected_file_size) < 1000; // Allow 1KB difference
    
    die('Error: Required function checkDentistFullAvailability not found. Please check dentist_availability.php is properly included.');
}

// Set security headers and check login
requireLogin();

// Initialize variables
$conn = getDBConnection();
$user_info = null;
$user_type = $_SESSION['user_type'] ?? 'patient';
$patient_data = null;
$edit_appointment_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$edit_appointment_data = null;
$services = [];
$errors = [];

try {
    // If editing an existing appointment, fetch its data
    if ($edit_appointment_id > 0) {
        $edit_stmt = $conn->prepare("
            SELECT 
                a.appointment_id,
                a.scheduled_datetime,
                a.service_id,
                a.duration_minutes,
                a.status,
                a.dentist_id,
                p.patient_id,
                p.first_name,
                p.last_name,
                p.email,
                p.primary_phone
            FROM tbl_appointments a
            INNER JOIN tbl_patients p ON a.patient_id = p.patient_id
            WHERE a.appointment_id = ? AND a.status IN ('Confirmed', 'Scheduled', 'Pending')
            LIMIT 1
        ");
        
        if ($edit_stmt) {
            $edit_stmt->bind_param("i", $edit_appointment_id);
            $edit_stmt->execute();
            $edit_result = $edit_stmt->get_result();
            
            if ($edit_result->num_rows === 1) {
                $edit_appointment_data = $edit_result->fetch_assoc();
                // Verify user owns this appointment or is admin
                if ($user_type === 'patient' && $edit_appointment_data['patient_id'] != $_SESSION['user_id']) {
                    $edit_appointment_data = null;
                    $edit_appointment_id = 0;
                }
                // Block editing if appointment status is Missed
                if (isset($edit_appointment_data['status']) && strtolower($edit_appointment_data['status']) === 'missed') {
                    $_SESSION['action_message'] = "Missed appointments cannot be rescheduled.";
                    $edit_appointment_data = null;
                    $edit_appointment_id = 0;
                }
            } else {
                $edit_appointment_id = 0;
            }
            $edit_stmt->close();
        }
    }
    
    // Fetch patient data for logged-in patients
    if ($user_type === 'patient') {
        $stmt = $conn->prepare("SELECT patient_id, first_name, last_name, email, primary_phone FROM tbl_patients WHERE patient_id = ? LIMIT 1");
        if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $patient_data = $result->fetch_assoc();
        }
        $stmt->close();
        }
    }
    
    // Get services from database
    $table_check = $conn->query("SHOW TABLES LIKE 'tbl_service_catalog'");
    if ($table_check && $table_check->num_rows > 0) {
        $service_stmt = $conn->prepare("SELECT service_id, service_name, base_price, default_duration_minutes FROM tbl_service_catalog WHERE is_active = 1 ORDER BY service_name");
        if ($service_stmt) {
            $service_stmt->execute();
            $service_result = $service_stmt->get_result();
            while ($row = $service_result->fetch_assoc()) {
                $services[] = $row;
            }
            $service_stmt->close();
        }
    }
    
    // Fallback services if table doesn't exist
    if (empty($services)) {
        $services = [
            ['service_id' => 1, 'service_name' => 'General Checkup', 'base_price' => 500.00, 'default_duration_minutes' => 30],
            ['service_id' => 2, 'service_name' => 'Cleaning', 'base_price' => 800.00, 'default_duration_minutes' => 45],
            ['service_id' => 3, 'service_name' => 'Extraction', 'base_price' => 1000.00, 'default_duration_minutes' => 30],
            ['service_id' => 4, 'service_name' => 'Consultation', 'base_price' => 300.00, 'default_duration_minutes' => 20],
            ['service_id' => 5, 'service_name' => 'Root Canal', 'base_price' => 5000.00, 'default_duration_minutes' => 120],
            ['service_id' => 6, 'service_name' => 'Teeth Whitening', 'base_price' => 3500.00, 'default_duration_minutes' => 90]
        ];
    }
    
    // Fetch service prerequisites with validity periods for JavaScript
    $service_prerequisites = [];
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
    
    // Fetch patient's completed prerequisites for real-time validation (if logged in as patient)
    $patient_completed_prerequisites = [];
    $patient_active_maintenance = []; // Services with active maintenance plans
    if ($user_type === 'patient' && isset($patient_data) && !empty($patient_data['patient_id'])) {
        $patient_id = $patient_data['patient_id'];
        $completed_prereq_stmt = $conn->prepare("
            SELECT a.service_id, a.scheduled_datetime, a.status
            FROM tbl_appointments a
            WHERE a.patient_id = ?
            AND a.status = 'Completed'
            ORDER BY a.scheduled_datetime DESC
        ");
        if ($completed_prereq_stmt) {
            $completed_prereq_stmt->bind_param("i", $patient_id);
            $completed_prereq_stmt->execute();
            $completed_prereq_result = $completed_prereq_stmt->get_result();
            while ($completed_row = $completed_prereq_result->fetch_assoc()) {
                $patient_completed_prerequisites[] = [
                    'service_id' => $completed_row['service_id'],
                    'scheduled_datetime' => $completed_row['scheduled_datetime'],
                    'status' => $completed_row['status']
                ];
            }
            $completed_prereq_stmt->close();
        }
        
        // Fetch patient's active maintenance records
        $maintenance_table_check = $conn->query("SHOW TABLES LIKE 'tbl_patient_maintenance'");
        if ($maintenance_table_check && $maintenance_table_check->num_rows > 0) {
            $maintenance_stmt = $conn->prepare("
                SELECT service_id
                FROM tbl_patient_maintenance
                WHERE patient_id = ?
                AND status != 'cancelled'
            ");
            if ($maintenance_stmt) {
                $maintenance_stmt->bind_param("i", $patient_id);
                $maintenance_stmt->execute();
                $maintenance_result = $maintenance_stmt->get_result();
                while ($maint_row = $maintenance_result->fetch_assoc()) {
                    $patient_active_maintenance[] = intval($maint_row['service_id']);
                }
                $maintenance_stmt->close();
            }
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
    
    // Fetch closure dates to prevent booking on closed dates
    $closure_dates = [];
    $closure_dates_map = [];
    try {
        $closure_stmt = $conn->prepare("SELECT closure_date, reason FROM tbl_clinic_closures WHERE closure_date >= CURDATE() ORDER BY closure_date ASC");
        if ($closure_stmt) {
            $closure_stmt->execute();
            $closure_result = $closure_stmt->get_result();
            while ($row = $closure_result->fetch_assoc()) {
                $closure_dates[] = $row['closure_date'];
                $closure_dates_map[$row['closure_date']] = $row['reason'] ?? '';
            }
            $closure_stmt->close();
        }
    } catch (Exception $e) {
        // Table might not exist yet, ignore error
        error_log("Error fetching closure dates: " . $e->getMessage());
    }
    
    // Fetch dentists list for selection
    $dentists = [];
    try {
        $dentist_stmt = $conn->prepare("SELECT staff_id, first_name, last_name FROM tbl_staff WHERE role IN ('Dentist', 'Admin') ORDER BY last_name, first_name");
        if ($dentist_stmt) {
            $dentist_stmt->execute();
            $dentist_result = $dentist_stmt->get_result();
            while ($row = $dentist_result->fetch_assoc()) {
                $dentists[] = $row;
            }
            $dentist_stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error fetching dentists: " . $e->getMessage());
    }
    
} catch (Exception $e) {
    error_log("Page initialization error: " . $e->getMessage());
    $errors[] = "Error loading data. Please try again.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Get form data
    $scheduled_date = trim($_POST['scheduled_date'] ?? '');
    $scheduled_time = trim($_POST['scheduled_time'] ?? '');
    $service_id = intval($_POST['service_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');
    $selected_dentist_id = isset($_POST['dentist_id']) && $_POST['dentist_id'] !== '' ? intval($_POST['dentist_id']) : null; // null means system decides
    
    // Get patient info
    if ($user_type === 'patient' && isset($patient_data) && !empty($patient_data)) {
        $first_name = $patient_data['first_name'] ?? '';
        $last_name = $patient_data['last_name'] ?? '';
        $phone = $patient_data['primary_phone'] ?? '';
        $email = $patient_data['email'] ?? '';
    } else {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    }
    
    // Validation
    if (empty($service_id) || $service_id <= 0) {
        $errors[] = "Please select a service.";
    }
    
    if (empty($scheduled_date)) {
        $errors[] = "Please select a date.";
    }
    
    if (empty($scheduled_time)) {
        $errors[] = "Please select a time.";
    }
    
    // Only validate first_name, last_name, and email for non-logged-in patients
    if ($user_type !== 'patient' || !isset($patient_data) || empty($patient_data)) {
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email address is required.";
        }
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required.";
    }
    
    // Combine date and time
    $scheduled_datetime = null;
    if (empty($errors) && !empty($scheduled_date) && !empty($scheduled_time)) {
        $scheduled_datetime = $scheduled_date . ' ' . $scheduled_time . ':00';
        
        $datetime_obj = DateTime::createFromFormat('Y-m-d H:i:s', $scheduled_datetime);
        if (!$datetime_obj || $datetime_obj->format('Y-m-d H:i:s') !== $scheduled_datetime) {
            $errors[] = "Invalid date or time format.";
            $scheduled_datetime = null;
        }
        
        // Strict validation: cannot book appointments in the past (even by a minute)
        $now = new DateTime();
        // Add 1 minute buffer to account for any processing delay
        $now->modify('+1 minute');
        if ($datetime_obj && $datetime_obj <= $now) {
            $errors[] = "Cannot book appointments in the past. Please select a future date and time.";
        }
        
        // Check if the selected date is a closure date
        if (empty($errors) && $scheduled_date) {
            try {
                $conn = getDBConnection();
                $closure_check = $conn->prepare("SELECT closure_date, reason FROM tbl_clinic_closures WHERE closure_date = ? LIMIT 1");
                if ($closure_check) {
                    $closure_check->bind_param("s", $scheduled_date);
                    $closure_check->execute();
                    $closure_result = $closure_check->get_result();
                    if ($closure_result->num_rows > 0) {
                        $closure_info = $closure_result->fetch_assoc();
                        $reason_text = !empty($closure_info['reason']) ? ' (' . $closure_info['reason'] . ')' : '';
                        $errors[] = "The clinic is closed on " . date('F d, Y', strtotime($scheduled_date)) . $reason_text . ". Please select a different date.";
                    }
                    $closure_check->close();
                }
                $conn->close();
            } catch (Exception $e) {
                // If table doesn't exist, ignore the check
                error_log("Error checking closure dates: " . $e->getMessage());
            }
        }
    }
    
    // Get service details
    $service_details = null;
    $duration_minutes = 60;
    $service_price = 0.00;
    
    if ($service_id > 0 && empty($errors)) {
        $found = false;
        foreach ($services as $service) {
            if ($service['service_id'] == $service_id) {
                $service_details = $service;
                $duration_minutes = $service['default_duration_minutes'];
                $service_price = $service['base_price'];
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $errors[] = "Selected service is not available.";
        }
    }
    
    // Process appointment
    if (empty($errors) && $scheduled_datetime !== null) {
        try {
            $conn = getDBConnection();
            
            // Get or create patient
            $patient_id = null;
            if ($user_type === 'patient' && isset($patient_data)) {
                $patient_id = $patient_data['patient_id'];
            } else {
                $check_stmt = $conn->prepare("SELECT patient_id FROM tbl_patients WHERE email = ? LIMIT 1");
                $check_stmt->bind_param("s", $email);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows === 1) {
                    $existing_patient = $check_result->fetch_assoc();
                    $patient_id = $existing_patient['patient_id'];
                } else {
                    $insert_patient_stmt = $conn->prepare("INSERT INTO tbl_patients (first_name, last_name, email, primary_phone) VALUES (?, ?, ?, ?)");
                    $insert_patient_stmt->bind_param("ssss", $first_name, $last_name, $email, $phone);
                    if ($insert_patient_stmt->execute()) {
                        $patient_id = $conn->insert_id;
                    } else {
                        $errors[] = "Failed to create patient record: " . $conn->error;
                    }
                    $insert_patient_stmt->close();
                }
                $check_stmt->close();
            }
            
            // Check prerequisites AFTER we have patient_id
            if ($patient_id && $service_id > 0 && empty($errors) && $service_details) {
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
                        }
                        $active_maintenance_check->close();
                    }
                }
                
                // Only check prerequisites if not skipping (i.e., first appointment or non-maintenance service)
                if (!$skip_prerequisites) {
                    // Check if this service has prerequisites
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
                            // No expiration - just check if completed
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
                            $errors[] = "Your " . implode(' or ', $expired_prerequisites) . " appointment has expired (must be within the last 30 days). Please schedule a new " . $required_names[0] . " before booking " . ($service_details['service_name'] ?? 'this service') . ".";
                        } else {
                            $errors[] = "You must complete a " . implode(' or ', $required_names) . " appointment before booking " . ($service_details['service_name'] ?? 'this service') . ". Please book a " . $required_names[0] . " first.";
                        }
                    }
                }
            } // Close if (!$skip_prerequisites)
            }
            
            if ($patient_id) {
                $is_editing = isset($_POST['edit_appointment_id']) && intval($_POST['edit_appointment_id']) > 0;
                $edit_appointment_id = $is_editing ? intval($_POST['edit_appointment_id']) : 0;
                
                // Find available dentist
                $dentist_id = null;
                
                // If user selected a specific dentist, validate their availability
                if ($selected_dentist_id !== null && $selected_dentist_id > 0) {
                    // Build dentist-services map for service specialization checking
                    $dentist_services = [];
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
                        $my_specs = isset($staff_specializations[$selected_dentist_id]) ? $staff_specializations[$selected_dentist_id] : [];
                        foreach ($all_services as $sid) {
                            $req_specs = isset($service_specializations[$sid]) ? $service_specializations[$sid] : [];
                            $can_do = false;
                            if (empty($req_specs)) {
                                $can_do = true;
                            } else {
                                foreach ($req_specs as $rs) {
                                    if (in_array($rs, $my_specs)) {
                                        $can_do = true;
                                        break;
                                    }
                                }
                            }
                            if ($can_do) {
                                $dentist_services[$selected_dentist_id][] = $sid;
                            }
                        }
                    } catch (Exception $e) {
                        error_log("Error building dentist services map: " . $e->getMessage());
                    }
                    
                    // Check if dentist can perform this service (specialization check)
                    $can_perform = false;
                    if (isset($dentist_services[$selected_dentist_id])) {
                        if (is_array($dentist_services[$selected_dentist_id]) && in_array($service_id, $dentist_services[$selected_dentist_id])) {
                            $can_perform = true;
                        }
                    }
                    
                    // If not found in map, check if service has no required specializations
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
                    } else {
                        // Check if dentist is available (schedule + no conflicts)
                        $availability_check = checkDentistFullAvailability($selected_dentist_id, $scheduled_datetime, $duration_minutes, $edit_appointment_id > 0 ? $edit_appointment_id : null);
                        
                        if (!$availability_check['available']) {
                            $errors[] = "Selected dentist is not available: " . $availability_check['reason'];
                        } else {
                            $dentist_id = $selected_dentist_id;
                        }
                    }
                } else {
                    // System decides - find any available dentist
                    if ($is_editing && $edit_appointment_id > 0) {
                        $dentist_query = $conn->prepare("SELECT staff_id FROM tbl_staff WHERE role IN ('Dentist', 'Admin') ORDER BY staff_id ASC");
                        if ($dentist_query) {
                            $dentist_query->execute();
                            $dentist_result = $dentist_query->get_result();
                            
                            while ($dentist_row = $dentist_result->fetch_assoc()) {
                                // Check if dentist can perform this service first
                                $can_perform = true;
                                if ($service_id > 0) {
                                    // Check service specialization
                                    $service_spec_check = $conn->prepare("SELECT COUNT(*) as count FROM tbl_service_specializations WHERE service_id = ?");
                                    if ($service_spec_check) {
                                        $service_spec_check->bind_param("i", $service_id);
                                        $service_spec_check->execute();
                                        $spec_result = $service_spec_check->get_result();
                                        $spec_row = $spec_result->fetch_assoc();
                                        if ($spec_row['count'] > 0) {
                                            // Service has required specializations - check if dentist has them
                                            $dentist_spec_check = $conn->prepare("
                                                SELECT COUNT(*) as count 
                                                FROM tbl_staff_specializations ss
                                                INNER JOIN tbl_service_specializations svs ON ss.specialization_id = svs.specialization_id
                                                WHERE ss.staff_id = ? AND svs.service_id = ?
                                            ");
                                            if ($dentist_spec_check) {
                                                $dentist_spec_check->bind_param("ii", $dentist_row['staff_id'], $service_id);
                                                $dentist_spec_check->execute();
                                                $dentist_spec_result = $dentist_spec_check->get_result();
                                                $dentist_spec_row = $dentist_spec_result->fetch_assoc();
                                                $can_perform = ($dentist_spec_row['count'] > 0);
                                                $dentist_spec_check->close();
                                            }
                                        }
                                        $service_spec_check->close();
                                    }
                                }
                                
                                if ($can_perform) {
                                    $availability_check = checkDentistFullAvailability($dentist_row['staff_id'], $scheduled_datetime, $duration_minutes, $edit_appointment_id);
                                    if ($availability_check['available']) {
                                        $dentist_id = $dentist_row['staff_id'];
                                        break;
                                    }
                                }
                            }
                            $dentist_query->close();
                        }
                    } else {
                        // Find available dentist using the function (which checks both schedule and conflicts)
                        $dentist_query = $conn->prepare("SELECT staff_id FROM tbl_staff WHERE role IN ('Dentist', 'Admin') ORDER BY staff_id ASC");
                        if ($dentist_query) {
                            $dentist_query->execute();
                            $dentist_result = $dentist_query->get_result();
                            
                            while ($dentist_row = $dentist_result->fetch_assoc()) {
                                // Check if dentist can perform this service first
                                $can_perform = true;
                                if ($service_id > 0) {
                                    // Check service specialization
                                    $service_spec_check = $conn->prepare("SELECT COUNT(*) as count FROM tbl_service_specializations WHERE service_id = ?");
                                    if ($service_spec_check) {
                                        $service_spec_check->bind_param("i", $service_id);
                                        $service_spec_check->execute();
                                        $spec_result = $service_spec_check->get_result();
                                        $spec_row = $spec_result->fetch_assoc();
                                        if ($spec_row['count'] > 0) {
                                            // Service has required specializations - check if dentist has them
                                            $dentist_spec_check = $conn->prepare("
                                                SELECT COUNT(*) as count 
                                                FROM tbl_staff_specializations ss
                                                INNER JOIN tbl_service_specializations svs ON ss.specialization_id = svs.specialization_id
                                                WHERE ss.staff_id = ? AND svs.service_id = ?
                                            ");
                                            if ($dentist_spec_check) {
                                                $dentist_spec_check->bind_param("ii", $dentist_row['staff_id'], $service_id);
                                                $dentist_spec_check->execute();
                                                $dentist_spec_result = $dentist_spec_check->get_result();
                                                $dentist_spec_row = $dentist_spec_result->fetch_assoc();
                                                $can_perform = ($dentist_spec_row['count'] > 0);
                                                $dentist_spec_check->close();
                                            }
                                        }
                                        $service_spec_check->close();
                                    }
                                }
                                
                                if ($can_perform) {
                                    $availability_check = checkDentistFullAvailability($dentist_row['staff_id'], $scheduled_datetime, $duration_minutes);
                                    if ($availability_check['available']) {
                                        $dentist_id = $dentist_row['staff_id'];
                                        break;
                                    }
                                }
                            }
                            $dentist_query->close();
                        }
                    }
                }
                
                // Fallback dentist
                if (!$dentist_id) {
                    $fallback_dentist = $conn->prepare("SELECT staff_id FROM tbl_staff WHERE role IN ('Dentist', 'Admin') LIMIT 1");
                    if ($fallback_dentist) {
                        $fallback_dentist->execute();
                        $fallback_result = $fallback_dentist->get_result();
                        if ($fallback_result->num_rows > 0) {
                            $dentist_row = $fallback_result->fetch_assoc();
                            $dentist_id = $dentist_row['staff_id'];
                        }
                        $fallback_dentist->close();
                    }
                }
                
                if (!$dentist_id) {
                    $errors[] = "No dentist available. Please contact the clinic.";
                    } else {
                    $status = 'Pending';
                    
                    if ($is_editing && $edit_appointment_id > 0) {
                        // Check if rescheduling is allowed (must be at least 1 day before appointment)
                        $existing_appt_stmt = $conn->prepare("SELECT scheduled_datetime FROM tbl_appointments WHERE appointment_id = ? LIMIT 1");
                        $existing_appt_stmt->bind_param("i", $edit_appointment_id);
                        $existing_appt_stmt->execute();
                        $existing_result = $existing_appt_stmt->get_result();
                        $existing_appt = $existing_result->fetch_assoc();
                        $existing_appt_stmt->close();
                        
                        if ($existing_appt) {
                            $existing_datetime = new DateTime($existing_appt['scheduled_datetime']);
                            $now = new DateTime();
                            $days_until_existing = ($existing_datetime->getTimestamp() - $now->getTimestamp()) / 86400;
                            
                            if ($days_until_existing < 1) {
                                $errors[] = "You can only reschedule appointments at least 1 day before the scheduled date.";
                            } else {
                                // Check if downpayment exists to determine status (using unified payments table)
                                $table_check = $conn->query("SHOW COLUMNS FROM tbl_payments LIKE 'payment_type'");
                                $has_payment_type = $table_check && $table_check->num_rows > 0;
                                
                                if ($has_payment_type) {
                                    // Use unified payments table
                                    $downpayment_check = $conn->prepare("SELECT payment_id FROM tbl_payments WHERE payment_type = 'Downpayment' AND appointment_id = ? AND payment_status = 'Completed' LIMIT 1");
                                } else {
                                    // Fallback to old table
                                    $downpayment_check = $conn->prepare("SELECT downpayment_id FROM tbl_appointment_downpayments WHERE appointment_id = ? AND payment_status = 'Completed' LIMIT 1");
                                }
                                $downpayment_check->bind_param("i", $edit_appointment_id);
                                $downpayment_check->execute();
                                $dp_result = $downpayment_check->get_result();
                                $has_paid_downpayment = $dp_result->num_rows > 0;
                                $downpayment_check->close();
                                
                                $status = $has_paid_downpayment ? 'Scheduled' : 'Pending';
                                
                                // Update appointment
                                $appointment_stmt = $conn->prepare("
                                    UPDATE tbl_appointments 
                                    SET dentist_id = ?, service_id = ?, scheduled_datetime = ?, 
                                        duration_minutes = ?, price = ?, status = ? 
                                    WHERE appointment_id = ?
                                ");
                                $appointment_stmt->bind_param("iisidsi", $dentist_id, $service_id, $scheduled_datetime, $duration_minutes, $service_price, $status, $edit_appointment_id);
                        
                        if ($appointment_stmt->execute()) {
                            $appointment_stmt->close();
                            $conn->close();
                            
                                    $_SESSION['booking_success'] = "Appointment rescheduled successfully!";
                                    header("Location: appointments.php");
                            exit();
                        } else {
                                    $errors[] = "Failed to update appointment: " . $appointment_stmt->error;
                                    $appointment_stmt->close();
                                }
                            }
                        }
                    } else {
                        // Calculate downpayment (35% of service price)
                        $downpayment_amount = $service_price * 0.35;
                        
                        // Insert new appointment with status 'Pending' (waiting for downpayment)
                        $appointment_stmt = $conn->prepare("
                            INSERT INTO tbl_appointments 
                            (patient_id, dentist_id, service_id, scheduled_datetime, duration_minutes, status, price) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $appointment_stmt->bind_param("iiisisd", $patient_id, $dentist_id, $service_id, $scheduled_datetime, $duration_minutes, $status, $service_price);
                        
                        if ($appointment_stmt->execute()) {
                            $appointment_id = $conn->insert_id;
                            $appointment_stmt->close();
                            
                            // Store appointment details in session for downpayment popup
                            $_SESSION['pending_appointment'] = [
                                'appointment_id' => $appointment_id,
                                'service_name' => $service_details['service_name'] ?? 'Service',
                                'service_price' => $service_price,
                                'downpayment_amount' => $downpayment_amount,
                                'scheduled_datetime' => $scheduled_datetime,
                                'duration_minutes' => $duration_minutes
                            ];
                            
                            $conn->close();
                            
                            // Redirect to show downpayment popup
                            header("Location: book_appointment.php?payment_required=1");
                            exit();
                        } else {
                            $errors[] = "Failed to create appointment: " . $appointment_stmt->error;
                            $appointment_stmt->close();
                        }
                    }
                }
            }
            
            if (isset($conn)) {
                $conn->close();
            }
        } catch (Exception $e) {
            error_log("Appointment processing error: " . $e->getMessage());
            $errors[] = "Database error: " . $e->getMessage();
            if (isset($conn)) {
                $conn->close();
            }
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['booking_errors'] = $errors;
        $_SESSION['booking_data'] = $_POST;
    }
}

// Get form data from session if available
$form_data = $_SESSION['booking_data'] ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SESSION['booking_data'])) {
    unset($_SESSION['booking_data']);
}

// Check if downpayment is required
$payment_required = isset($_GET['payment_required']) && isset($_SESSION['pending_appointment']);
$pending_appointment = $_SESSION['pending_appointment'] ?? null;

// Close any remaining connection
if (isset($conn) && $conn) {
    $conn->close();
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <?php if (function_exists('outputCachePreventionMeta')) outputCachePreventionMeta(); ?>
<title>General Booking - Ivy Dental Clinic</title>
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#136dec",
                        "primary-dark": "#0e52b5",
                        "accent-green": "#10b981",
                        "background-light": "#f6f7f8",
                        "background-dark": "#101822",
                        "surface-light": "#ffffff",
                        "surface-dark": "#1a2430",
                        "text-main-light": "#0d131b",
                        "text-main-dark": "#e2e8f0",
                        "text-sub-light": "#4c6c9a",
                        "text-sub-dark": "#94a3b8",
                        "border-light": "#e7ecf3",
                        "border-dark": "#2d3748",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
<style>
        body { font-family: 'Inter', sans-serif; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-text-main-light dark:text-text-main-dark min-h-screen flex flex-col font-display">
<header class="sticky top-0 z-50 bg-surface-light dark:bg-surface-dark border-b border-border-light dark:border-border-dark px-4 md:px-10 py-3 shadow-sm transition-colors duration-200">
<div class="max-w-[1440px] mx-auto w-full flex items-center justify-between">
<div class="flex items-center gap-3">
<div class="text-primary size-8">
<span class="material-symbols-outlined text-3xl">dentistry</span>
</div>
<h2 class="text-text-main-light dark:text-white text-lg font-bold tracking-tight">Ivy Dental Clinic</h2>
</div>
<div class="flex items-center gap-4">
<a href="landing.php" class="flex items-center gap-2 text-text-sub-light hover:text-primary transition-colors text-sm font-medium">
<span class="material-symbols-outlined text-xl">arrow_back</span>
<span>Go Back to Dashboard</span>
</a>
</div>
</div>
</header>

<main class="flex-1 w-full max-w-[1440px] mx-auto px-4 md:px-10 py-8 flex flex-col gap-6">
<div class="flex justify-between items-end mb-4">
<div>
                <h1 class="text-3xl md:text-4xl font-black text-text-main-light dark:text-white mb-2">
                    <?php echo ($edit_appointment_id > 0 ? 'Reschedule' : 'General Booking'); ?>
                </h1>
                <p class="text-text-sub-light dark:text-text-sub-dark text-lg">
                    <?php echo ($edit_appointment_id > 0 ? 'Update your appointment.' : 'Create a new appointment request.'); ?>
                </p>
</div>
</div>

        <?php if (isset($_SESSION['booking_errors'])): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-400">
            <?php foreach ($_SESSION['booking_errors'] as $error): ?>
                <p class="text-sm"><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
            <?php unset($_SESSION['booking_errors']); ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="book_appointment.php<?php echo ($edit_appointment_id > 0 ? '?edit=' . $edit_appointment_id : ''); ?>" id="bookingForm" onsubmit="return validateBookingForm(event)">
            <?php if ($edit_appointment_id > 0 && $edit_appointment_data): ?>
            <input type="hidden" name="edit_appointment_id" value="<?php echo $edit_appointment_id; ?>">
            <?php endif; ?>
            
<div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                <!-- Left Column - Date & Time -->
<div class="lg:col-span-8 flex flex-col gap-6">
<section class="bg-surface-light dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark overflow-hidden p-6 shadow-sm">
<h3 class="text-lg font-bold text-text-main-light dark:text-white mb-6 flex items-center gap-2">
<span class="flex items-center justify-center size-8 rounded-full bg-primary/10 text-primary text-sm font-bold">1</span>
                        Date &amp; Time
                    </h3>
<div class="flex flex-col md:flex-row gap-8">
                        <div class="flex-1">
                        <label for="scheduled_date" class="block text-sm font-medium text-text-sub-light dark:text-text-sub-dark mb-2">Select Date</label>
                                <input type="date" name="scheduled_date" id="scheduled_date" 
                                    class="w-full rounded-lg border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark text-text-main-light dark:text-white focus:ring-primary focus:border-primary text-sm p-3" 
                                    value="<?php 
                                        if (isset($form_data['scheduled_date'])) {
                                            echo htmlspecialchars($form_data['scheduled_date']);
                                        } elseif ($edit_appointment_data) {
                                            echo date('Y-m-d', strtotime($edit_appointment_data['scheduled_datetime']));
                                        }
                                    ?>" 
                                    min="<?php echo date('Y-m-d'); ?>" required/>
</div>
                        <div class="flex-1">
                        <label for="scheduled_time" class="block text-sm font-medium text-text-sub-light dark:text-text-sub-dark mb-2">Select Time</label>
                                <select name="scheduled_time" id="scheduled_time" 
                                    class="w-full rounded-lg border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark text-text-main-light dark:text-white focus:ring-primary focus:border-primary text-sm p-3" required>
<option value="">Select time</option>
<?php
                                    $selected_time = '';
                                    if (isset($form_data['scheduled_time'])) {
                                        $selected_time = $form_data['scheduled_time'];
                                    } elseif ($edit_appointment_data) {
                                        $selected_time = date('H:i', strtotime($edit_appointment_data['scheduled_datetime']));
                                    }
                                    
                                    $times = ['09:00', '09:30', '10:00', '10:30', '11:00', '11:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30', '16:00', '16:30'];
                                    $now = new DateTime();
                                    $today = $now->format('Y-m-d');
                                    $current_time = $now->format('H:i');
                                    $selected_date = isset($form_data['scheduled_date']) ? $form_data['scheduled_date'] : ($edit_appointment_data ? date('Y-m-d', strtotime($edit_appointment_data['scheduled_datetime'])) : '');
                                    
                                    foreach ($times as $time) {
                                        $selected = ($selected_time == $time) ? 'selected' : '';
                                        $hour = (int)substr($time, 0, 2);
                                        $ampm = $hour >= 12 ? 'PM' : 'AM';
                                        $display_hour = $hour > 12 ? $hour - 12 : ($hour == 0 ? 12 : $hour);
                                        $minutes = substr($time, 3, 2);
                                        
                                        // Strictly disable past times if selected date is today
                                        // If it's Tuesday 9:00am, disable all times before 9:00am
                                        $disabled = '';
                                        if ($selected_date === $today) {
                                            // Compare time strings directly (HH:MM format)
                                            if ($time <= $current_time) {
                                                $disabled = 'disabled';
                                            }
                                        }
                                        
                                        echo "<option value=\"{$time}\" {$selected} {$disabled} data-time=\"{$time}\">{$display_hour}:{$minutes} {$ampm}</option>";
}
?>
</select>
                            </div>
                        </div>
                        <div class="mt-6">
                        <label for="dentist_id" class="block text-sm font-medium text-text-sub-light dark:text-text-sub-dark mb-2">Select Dentist (Optional)</label>
                            <select name="dentist_id" id="dentist_id" 
                                class="w-full rounded-lg border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark text-text-main-light dark:text-white focus:ring-primary focus:border-primary text-sm p-3">
                                <option value="">Let system decide (recommended)</option>
                                <?php
                                if (!empty($dentists)) {
                                    $selected_dentist = $form_data['dentist_id'] ?? ($edit_appointment_data['dentist_id'] ?? '');
                                    foreach ($dentists as $dentist) {
                                        $selected = ($selected_dentist == $dentist['staff_id']) ? 'selected' : '';
                                        echo '<option value="' . $dentist['staff_id'] . '" ' . $selected . '>';
                                        echo htmlspecialchars($dentist['first_name'] . ' ' . $dentist['last_name']);
                                        echo '</option>';
                                    }
                                }
                                ?>
                            </select>
                            <p class="text-xs text-text-sub-light dark:text-text-sub-dark mt-2">
                                If you select a specific dentist, we'll check their schedule and availability. Otherwise, we'll automatically assign an available dentist.
                            </p>
                        </div>
                        <div id="dentist_availability_status" class="mt-4 hidden"></div>
                        <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <p class="text-sm text-blue-800 dark:text-blue-300 flex items-center gap-2">
                                <span class="material-symbols-outlined text-base">info</span>
                                <strong>Note:</strong> Past time slots are automatically disabled and cannot be selected. If you select a specific dentist, unavailable times will also be disabled.
                            </p>
</div>
</section>
</div>

                <!-- Right Column - Patient Details & Summary -->
<div class="lg:col-span-4 flex flex-col gap-6">
<div class="bg-surface-light dark:bg-surface-dark rounded-2xl border border-border-light dark:border-border-dark shadow-sm p-6 sticky top-24">
<h3 class="text-lg font-bold text-text-main-light dark:text-white mb-6">Patient Details</h3>
<div class="flex flex-col gap-4">
                            <?php
                            $is_logged_in_patient = ($user_type === 'patient' && isset($patient_data) && !empty($patient_data));
                            $readonly_class = $is_logged_in_patient ? 'bg-gray-100 dark:bg-gray-800 cursor-not-allowed opacity-75' : '';
                            $readonly_attr = $is_logged_in_patient ? 'readonly' : '';
                            ?>
                            
<div>
                                <label for="patient_first_name" class="block text-sm font-medium text-text-sub-light dark:text-text-sub-dark mb-1">
                                    First Name
                                    <?php if ($is_logged_in_patient): ?>
                                    <span class="text-xs text-text-sub-light dark:text-text-sub-dark ml-1">(from your account)</span>
                                    <?php endif; ?>
                                </label>
                                <input class="w-full rounded-lg border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark text-text-main-light dark:text-white focus:ring-primary focus:border-primary text-sm p-2 <?php echo $readonly_class; ?>" 
                                    placeholder="Enter first name" type="text" name="first_name" id="patient_first_name" autocomplete="given-name"
                                    value="<?php echo htmlspecialchars($form_data['first_name'] ?? ($patient_data['first_name'] ?? '')); ?>" 
                                    <?php echo $readonly_attr; ?> required/>
</div>
                            
<div>
                                <label for="patient_last_name" class="block text-sm font-medium text-text-sub-light dark:text-text-sub-dark mb-1">
                                    Last Name
                                    <?php if ($is_logged_in_patient): ?>
                                    <span class="text-xs text-text-sub-light dark:text-text-sub-dark ml-1">(from your account)</span>
                                    <?php endif; ?>
                                </label>
                                <input class="w-full rounded-lg border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark text-text-main-light dark:text-white focus:ring-primary focus:border-primary text-sm p-2 <?php echo $readonly_class; ?>" 
                                    placeholder="Enter last name" type="text" name="last_name" id="patient_last_name" autocomplete="family-name"
                                    value="<?php echo htmlspecialchars($form_data['last_name'] ?? ($patient_data['last_name'] ?? '')); ?>" 
                                    <?php echo $readonly_attr; ?> required/>
</div>
                            
<div>
                                <label for="patient_phone" class="block text-sm font-medium text-text-sub-light dark:text-text-sub-dark mb-1">
                                    Phone Number
                                    <?php if ($is_logged_in_patient): ?>
                                    <span class="text-xs text-text-sub-light dark:text-text-sub-dark ml-1">(from your account)</span>
                                    <?php endif; ?>
                                </label>
                                <input class="w-full rounded-lg border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark text-text-main-light dark:text-white focus:ring-primary focus:border-primary text-sm p-2 <?php echo $readonly_class; ?>" 
                                    placeholder="(+63) 900-000-0000" type="tel" name="phone" id="patient_phone" autocomplete="tel"
                                    value="<?php echo htmlspecialchars($form_data['phone'] ?? ($patient_data['primary_phone'] ?? '')); ?>" 
                                    <?php echo $readonly_attr; ?> required/>
</div>
                            
<div>
                                <label for="patient_email" class="block text-sm font-medium text-text-sub-light dark:text-text-sub-dark mb-1">
                                    Email Address
                                    <?php if ($is_logged_in_patient): ?>
                                    <span class="text-xs text-text-sub-light dark:text-text-sub-dark ml-1">(from your account)</span>
                                    <?php endif; ?>
                                </label>
                                <input class="w-full rounded-lg border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark text-text-main-light dark:text-white focus:ring-primary focus:border-primary text-sm p-2 <?php echo $readonly_class; ?>" 
                                    placeholder="patient@example.com" type="email" name="email" id="patient_email" autocomplete="email"
                                    value="<?php echo htmlspecialchars($form_data['email'] ?? ($patient_data['email'] ?? '')); ?>" 
                                    <?php echo $readonly_attr; ?> required/>
</div>
                            
<div>
                                <label for="service_id" class="block text-sm font-medium text-text-sub-light dark:text-text-sub-dark mb-1">Service</label>
                                <select name="service_id" id="service_id" 
                                    class="w-full rounded-lg border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark text-text-main-light dark:text-white focus:ring-primary focus:border-primary text-sm p-2" required>
<option value="">Select service</option>
                                    <?php
                                    if (!empty($services)) {
                                        $selected_service = $form_data['service_id'] ?? ($edit_appointment_data['service_id'] ?? '');
                                        foreach ($services as $service) {
                                            $selected = ($selected_service == $service['service_id']) ? 'selected' : '';
                                            echo '<option value="' . $service['service_id'] . '" data-price="' . $service['base_price'] . '" data-duration="' . $service['default_duration_minutes'] . '" ' . $selected . '>';
                                            echo htmlspecialchars($service['service_name']);
                                            echo '</option>';
                                        }
                                    }
                                    ?>
</select>
</div>
                            
<div id="service_info" class="bg-primary/5 border border-primary/20 rounded-lg p-3 hidden">
<div class="flex justify-between items-center text-sm">
<span class="text-text-sub-light dark:text-text-sub-dark">Duration:</span>
<span class="font-semibold text-text-main-light dark:text-white" id="service_duration">-</span>
</div>
<div class="flex justify-between items-center text-sm mt-1">
<span class="text-text-sub-light dark:text-text-sub-dark">Price:</span>
<span class="font-bold text-primary" id="service_price">-</span>
</div>
</div>
                            
                            <div id="prerequisite_services" class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3 hidden">
                                <div class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-amber-600 dark:text-amber-400 text-lg">info</span>
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-300 mb-1">Recommended Prerequisites</p>
                                        <p class="text-xs text-amber-700 dark:text-amber-400 mb-2">This service typically requires the following check-ups or exams beforehand:</p>
                                        <ul id="prerequisite_list" class="text-xs text-amber-800 dark:text-amber-300 space-y-1 list-disc list-inside mb-2">
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="prerequisite_blocking" class="bg-red-50 dark:bg-red-900/20 border-2 border-red-300 dark:border-red-700 rounded-lg p-4 hidden">
                                <div class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-red-600 dark:text-red-400 text-lg">warning</span>
                                    <div class="flex-1">
                                        <p class="text-sm font-semibold text-red-800 dark:text-red-300 mb-1" id="prerequisite_blocking_title">Prerequisite Required</p>
                                        <p class="text-xs text-red-700 dark:text-red-400 mb-3" id="prerequisite_blocking_message"></p>
                                        <button type="button" id="book_prerequisite_btn" class="text-xs bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg font-semibold transition-colors">
                                            Book Required Service
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 mt-2">
                                <p class="text-xs text-blue-800 dark:text-blue-300 flex items-start gap-2">
                                    <span class="material-symbols-outlined text-base mt-0.5">info</span>
                                    <span><strong>Note:</strong> All profile information should be related to the patient taking the check-ups. Please ensure the details match the person who will be attending the appointment.</span>
                                </p>
                            </div>
                            
                            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3 mt-2">
                                <p class="text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2">
                                    <span class="material-symbols-outlined text-base mt-0.5">info</span>
                                    <span><strong>Downpayment Notice:</strong> Downpayments are charged at the standard rate. Discounts (PWD/Senior Citizen) are applied only after in-person verification at the clinic. Any overpayment will be credited to the remaining balance.</span>
                                </p>
                            </div>
                            
<div>
<label for="notes" class="block text-sm font-medium text-text-sub-light dark:text-text-sub-dark mb-1">Notes (Optional)</label>
                                <textarea class="w-full rounded-lg border border-border-light dark:border-border-dark bg-background-light dark:bg-background-dark text-text-main-light dark:text-white focus:ring-primary focus:border-primary text-sm p-2" 
                                    rows="3" name="notes" id="notes" autocomplete="off" placeholder="Any special requests or concerns..."><?php echo htmlspecialchars($form_data['notes'] ?? ''); ?></textarea>
</div>
                            
<div class="h-px bg-border-light dark:bg-border-dark my-2"></div>
                            
<div class="space-y-2 mb-4">
<div class="flex justify-between items-center text-sm font-medium">
<span class="text-text-sub-light">Selected:</span>
<span class="text-primary font-bold" id="selected-summary">-</span>
</div>
<div class="flex justify-between items-center text-sm font-medium">
<span class="text-text-sub-light">Service:</span>
<span class="text-text-main-light dark:text-white font-semibold" id="summary-service">-</span>
</div>
<div class="flex justify-between items-center text-sm font-medium">
<span class="text-text-sub-light">Duration:</span>
<span class="text-text-main-light dark:text-white" id="summary-duration">-</span>
</div>
<div class="flex justify-between items-center text-sm font-medium">
<span class="text-text-sub-light">Total Price:</span>
<span class="text-primary font-bold text-lg" id="summary-price">₱0.00</span>
</div>
</div>
                            
<button class="w-full py-3 rounded-lg bg-primary hover:bg-primary-dark text-white font-bold transition-all shadow-md hover:shadow-lg flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed" type="submit" id="submitBookingBtn">
                                <?php echo ($edit_appointment_id > 0 ? 'Update Booking' : 'Confirm Booking'); ?>
                        </button>
</div>
</div>
</div>
</div>
</form>
</main>

<footer class="mt-auto border-t border-border-light dark:border-border-dark bg-white dark:bg-surface-dark py-8">
<div class="max-w-[1440px] mx-auto px-4 md:px-10 flex flex-col md:flex-row justify-between items-center gap-4">
<p class="text-text-sub-light dark:text-text-sub-dark text-sm">© 2023 Ivy Dental Clinic. All rights reserved.</p>
<div class="flex gap-6">
<a class="text-text-sub-light dark:text-text-sub-dark hover:text-primary text-sm" href="#">Privacy Policy</a>
<a class="text-text-sub-light dark:text-text-sub-dark hover:text-primary text-sm" href="#">Terms of Service</a>
<a class="text-text-sub-light dark:text-text-sub-dark hover:text-primary text-sm" href="#">Help Center</a>
</div>
</div>
</footer>

    <script>
        // Service prerequisites data from PHP
        const servicePrerequisites = <?php echo json_encode($service_prerequisites ?? []); ?>;
        // Patient's completed prerequisites for real-time validation
        const patientCompletedPrerequisites = <?php echo json_encode($patient_completed_prerequisites ?? []); ?>;
        // Services with maintenance plans
        const servicesWithMaintenance = <?php echo json_encode($services_with_maintenance ?? []); ?>;
        // Patient's active maintenance records (service IDs)
        const patientActiveMaintenance = <?php echo json_encode($patient_active_maintenance ?? []); ?>;
        
        // Global flag to track if prerequisites are blocking
        window.prerequisitesBlocking = false;
        
        // Function to check if a prerequisite is valid (within validity period)
        function isPrerequisiteValid(requiredServiceId, validityDays) {
            // If no patient data or empty array, prerequisite is NOT valid
            if (!patientCompletedPrerequisites || patientCompletedPrerequisites.length === 0) {
                return false;
            }
            
            const now = new Date();
            const validityDate = new Date(now);
            validityDate.setDate(validityDate.getDate() - (validityDays || 30));
            
            // Find completed appointments for this service
            const completedAppointments = patientCompletedPrerequisites.filter(function(apt) {
                // Use strict comparison and ensure service_id matches
                return parseInt(apt.service_id) === parseInt(requiredServiceId) && apt.status === 'Completed';
            });
            
            if (completedAppointments.length === 0) {
                return false;
            }
            
            // Check if any appointment is within validity period
            for (let i = 0; i < completedAppointments.length; i++) {
                const aptDate = new Date(completedAppointments[i].scheduled_datetime);
                if (aptDate >= validityDate) {
                    return true; // Found a valid prerequisite
                }
            }
            
            return false; // All prerequisites are expired
        }
        
        function updateServiceInfo() {
            const serviceSelect = document.getElementById('service_id');
            const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
            const serviceInfoDiv = document.getElementById('service_info');
            const prerequisiteDiv = document.getElementById('prerequisite_services');
            const prerequisiteBlockingDiv = document.getElementById('prerequisite_blocking');
            const prerequisiteList = document.getElementById('prerequisite_list');
            const blockingTitle = document.getElementById('prerequisite_blocking_title');
            const blockingMessage = document.getElementById('prerequisite_blocking_message');
            const bookPrereqBtn = document.getElementById('book_prerequisite_btn');
            const submitBtn = document.querySelector('button[type="submit"]');
            
            if (selectedOption && selectedOption.value) {
                const serviceId = parseInt(selectedOption.value);
                const duration = selectedOption.getAttribute('data-duration');
                const price = parseFloat(selectedOption.getAttribute('data-price'));
                
                document.getElementById('service_duration').textContent = duration + ' minutes';
                document.getElementById('service_price').textContent = '₱' + price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                serviceInfoDiv.classList.remove('hidden');
                
                // Check if this is a maintenance service and if patient has active maintenance
                // Convert to numbers for proper comparison (handle both string and number IDs)
                const isMaintenanceService = Array.isArray(servicesWithMaintenance) && servicesWithMaintenance.length > 0 && 
                    servicesWithMaintenance.some(id => parseInt(id) === serviceId);
                const hasActiveMaintenance = Array.isArray(patientActiveMaintenance) && patientActiveMaintenance.length > 0 && 
                    patientActiveMaintenance.some(id => parseInt(id) === serviceId);
                const skipPrerequisites = isMaintenanceService && hasActiveMaintenance;
                
                // Check prerequisites - try both string and number keys since JSON might convert keys
                const prereqKey = servicePrerequisites[serviceId] ? serviceId : (servicePrerequisites[String(serviceId)] ? String(serviceId) : null);
                if (!skipPrerequisites && prereqKey && servicePrerequisites[prereqKey] && servicePrerequisites[prereqKey].length > 0) {
                    // Prerequisites are required and not skipped - BLOCK booking
                    window.prerequisitesBlocking = true;
                    
                    // Any service that has mandatory prerequisites should NOT be bookable directly.
                    // Always show the prerequisite list and blocking message, and force the user
                    // to switch to the required service using the button.
                    prerequisiteList.innerHTML = '';
                    let firstRequiredServiceId = null;
                    let firstRequiredServiceName = null;
                    
                    servicePrerequisites[prereqKey].forEach(function(prereq) {
                        const li = document.createElement('li');
                        li.textContent = prereq.required_service_name;
                        prerequisiteList.appendChild(li);
                        
                        if (!firstRequiredServiceId) {
                            firstRequiredServiceId = prereq.required_service_id;
                            firstRequiredServiceName = prereq.required_service_name;
                        }
                    });
                    
                    // Always block booking for services with prerequisites
                    prerequisiteDiv.classList.remove('hidden');
                    prerequisiteBlockingDiv.classList.remove('hidden');
                    
                    blockingTitle.textContent = 'Prerequisite Required';
                    blockingMessage.textContent = 'You must book a ' + firstRequiredServiceName + ' appointment before booking this service. Please switch to the required service first.';
                    
                    // Disable submit button - CANNOT proceed with downpayment if prerequisites not met
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    }
                    
                    // Set up book prerequisite button
                    if (bookPrereqBtn) {
                        bookPrereqBtn.onclick = function() {
                            // Pre-select the required service
                            serviceSelect.value = firstRequiredServiceId;
                            updateServiceInfo();
                            // Scroll to top of form
                            serviceSelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        };
                    }
                } else {
                    // No prerequisites or prerequisites skipped due to active maintenance - hide all prerequisite messages
                    window.prerequisitesBlocking = false;
                    prerequisiteDiv.classList.add('hidden');
                    prerequisiteBlockingDiv.classList.add('hidden');
                    
                    // Update button state using centralized function
                    updateSubmitButtonState();
                }
            } else {
                serviceInfoDiv.classList.add('hidden');
                prerequisiteDiv.classList.add('hidden');
                prerequisiteBlockingDiv.classList.add('hidden');
                
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            }
            updateSummary();
        }

function updateSummary() {
    const date = document.getElementById('scheduled_date').value;
    const time = document.getElementById('scheduled_time').value;
            const serviceSelect = document.getElementById('service_id');
            const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
    
    let summary = '-';
            if (date && time) {
        const dateObj = new Date(date);
        const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const formattedDate = monthNames[dateObj.getMonth()] + ' ' + dateObj.getDate();
        const timeParts = time.split(':');
        const hour = parseInt(timeParts[0]);
        const minute = timeParts[1];
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
        const formattedTime = displayHour + ':' + minute + ' ' + ampm;
        
                summary = formattedDate + ', ' + formattedTime;
    }
    document.getElementById('selected-summary').textContent = summary;
    
            if (selectedOption && selectedOption.value) {
                const serviceName = selectedOption.textContent;
                const duration = selectedOption.getAttribute('data-duration');
                const price = parseFloat(selectedOption.getAttribute('data-price'));
                
                document.getElementById('summary-service').textContent = serviceName;
                document.getElementById('summary-duration').textContent = duration + ' minutes';
                document.getElementById('summary-price').textContent = '₱' + price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    } else {
        document.getElementById('summary-service').textContent = '-';
        document.getElementById('summary-duration').textContent = '-';
        document.getElementById('summary-price').textContent = '₱0.00';
    }
}

        // Closure dates data from PHP
        const closureDates = <?php echo json_encode($closure_dates_map); ?>;
        
        // Function to check if a date is a closure date
        function isClosureDate(date) {
            return closureDates.hasOwnProperty(date);
        }
        
        // Function to show closure date error
        function showClosureDateError(date) {
            const reason = closureDates[date] || '';
            const reasonText = reason ? ' (' + reason + ')' : '';
            const dateFormatted = new Date(date + 'T00:00:00').toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
            
            // Remove existing error message
            const existingError = document.getElementById('closure-date-error');
            if (existingError) {
                existingError.remove();
            }
            
            // Create error message
            const errorDiv = document.createElement('div');
            errorDiv.id = 'closure-date-error';
            errorDiv.className = 'mt-2 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg';
            errorDiv.innerHTML = '<p class="text-sm text-red-700 dark:text-red-400 font-medium">The clinic is closed on ' + dateFormatted + reasonText + '. Please select a different date.</p>';
            
            // Insert after date input
            const dateInput = document.getElementById('scheduled_date');
            dateInput.parentNode.insertBefore(errorDiv, dateInput.nextSibling);
            
            // Clear the date input
            dateInput.value = '';
            
            // Disable submit button
            const submitBtn = document.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }
        
        // Function to hide closure date error
        function hideClosureDateError() {
            const errorDiv = document.getElementById('closure-date-error');
            if (errorDiv) {
                errorDiv.remove();
            }
            
            // Re-enable submit button
            const submitBtn = document.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}

        // Function to check dentist availability via AJAX
        function checkDentistAvailability() {
            const dentistSelect = document.getElementById('dentist_id');
            const dateInput = document.getElementById('scheduled_date');
            const timeSelect = document.getElementById('scheduled_time');
            const serviceSelect = document.getElementById('service_id');
            const availabilityStatus = document.getElementById('dentist_availability_status');
            const submitBtn = document.querySelector('button[type="submit"]');
            
            const dentistId = dentistSelect.value;
            const selectedDate = dateInput.value;
            const selectedTime = timeSelect.value;
            const selectedService = serviceSelect.options[serviceSelect.selectedIndex];
            
            // Hide availability status if no dentist selected or missing info
            if (!dentistId || !selectedDate || !selectedTime || !selectedService || !selectedService.value) {
                if (availabilityStatus) {
                    availabilityStatus.classList.add('hidden');
                    availabilityStatus.innerHTML = '';
                }
                updateTimeSlots();
                // Disable submit button if dentist is selected but info is incomplete
                if (dentistId && submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                } else if (!dentistId && submitBtn) {
                    // If no dentist selected, enable button (system will auto-assign)
                    // But only if other required fields are filled AND prerequisites are not blocking
                    const dateInput = document.getElementById('scheduled_date');
                    const timeSelect = document.getElementById('scheduled_time');
                    const serviceSelect = document.getElementById('service_id');
                    if (dateInput && dateInput.value && timeSelect && timeSelect.value && 
                        serviceSelect && serviceSelect.value) {
                        if (window.prerequisitesBlocking) {
                            // Prerequisites are blocking - keep button disabled
                            submitBtn.disabled = true;
                            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                        } else {
                            submitBtn.disabled = false;
                            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                            // Reset button text if not in edit mode
                            const isEditMode = submitBtn.textContent.includes('Update');
                            if (!isEditMode) {
                                submitBtn.innerHTML = '<?php echo ($edit_appointment_id > 0 ? "Update Booking" : "Confirm Booking"); ?>';
                            }
                        }
                    }
                }
                return;
            }
            
            const duration = parseInt(selectedService.getAttribute('data-duration')) || 60;
            
            // Show loading state
            availabilityStatus.classList.remove('hidden');
            availabilityStatus.innerHTML = '<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3"><p class="text-sm text-blue-800 dark:text-blue-300">Checking availability...</p></div>';
            
            // Disable submit button while checking
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
            
            // Make AJAX request
            fetch(`check_dentist_availability.php?dentist_id=${dentistId}&date=${selectedDate}&time=${selectedTime}&duration=${duration}&service_id=${selectedService.value}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        availabilityStatus.innerHTML = '<div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3"><p class="text-sm text-red-800 dark:text-red-300">Error: ' + data.error + '</p></div>';
                        // Disable submit button on error
                        if (submitBtn) {
                            submitBtn.disabled = true;
                            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                        }
                        return;
                    }
                    
                    // Store availability state globally
                    window.dentistAvailabilityState = {
                        available: data.available,
                        day_available: data.day_available !== false,
                        time_available: data.time_available !== false,
                        service_available: data.service_available !== false
                    };
                    
                    if (data.available) {
                        availabilityStatus.innerHTML = '<div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-3"><p class="text-sm text-green-800 dark:text-green-300 flex items-center gap-2"><span class="material-symbols-outlined text-base">check_circle</span><strong>Available:</strong> ' + data.reason + '</p></div>';
                    } else {
                        availabilityStatus.innerHTML = '<div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3"><p class="text-sm text-red-800 dark:text-red-300 flex items-center gap-2"><span class="material-symbols-outlined text-base">cancel</span><strong>Not Available:</strong> ' + data.reason + '</p></div>';
                    }
                    
                    // Store occupied times for time slot filtering
                    window.occupiedTimes = data.occupied_times || [];
                    updateTimeSlots();
                    
                    // Update button state using centralized function
                    updateSubmitButtonState();
                })
                .catch(error => {
                    console.error('Error checking availability:', error);
                    availabilityStatus.innerHTML = '<div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3"><p class="text-sm text-yellow-800 dark:text-yellow-300">Unable to check availability. Please try again.</p></div>';
                    // Clear availability state on error
                    window.dentistAvailabilityState = { available: false };
                    updateTimeSlots();
                    // Update button state using centralized function
                    updateSubmitButtonState();
                });
        }
        
        // Function to update button state based on all conditions
        function updateSubmitButtonState() {
            const submitBtn = document.querySelector('button[type="submit"]');
            if (!submitBtn) return;
            
            const dateInput = document.getElementById('scheduled_date');
            const timeSelect = document.getElementById('scheduled_time');
            const serviceSelect = document.getElementById('service_id');
            const dentistSelect = document.getElementById('dentist_id');
            
            // Check if basic fields are filled
            const hasDate = dateInput && dateInput.value;
            const hasTime = timeSelect && timeSelect.value;
            const hasService = serviceSelect && serviceSelect.value;
            const hasDentist = dentistSelect && dentistSelect.value;
            
            // If prerequisites are blocking, always disable
            if (window.prerequisitesBlocking) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                return;
            }
            
            // If basic fields are not filled, disable
            if (!hasDate || !hasTime || !hasService) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                return;
            }
            
            // If dentist is selected, MUST check availability
            if (hasDentist) {
                // If availability hasn't been checked yet, disable and trigger check
                if (!window.dentistAvailabilityState) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    // Trigger availability check
                    checkDentistAvailability();
                    return;
                }
                
                // If dentist is not available, ALWAYS disable
                if (!window.dentistAvailabilityState.available) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    // Reset button text
                    const isEditMode = submitBtn.textContent.includes('Update');
                    if (!isEditMode) {
                        submitBtn.innerHTML = '<?php echo ($edit_appointment_id > 0 ? "Update Booking" : "Confirm Booking"); ?>';
                    }
                    return;
                }
                
                // Dentist is available - enable button
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                // Update button text to show "Proceed to Downpayment"
                const isEditMode = submitBtn.textContent.includes('Update');
                if (!isEditMode) {
                    submitBtn.innerHTML = '<span class="material-symbols-outlined">payment</span> Proceed to Downpayment';
                }
            } else {
                // No dentist selected - system will auto-assign, enable button
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }
        
        // Form validation function to prevent submission when dentist is not available or prerequisites are blocking
        function validateBookingForm(event) {
            // Check if prerequisites are blocking
            if (window.prerequisitesBlocking) {
                event.preventDefault();
                alert('Cannot proceed: Prerequisites are required for this service. Please complete the required prerequisite service first.');
                return false;
            }
            
            const dentistSelect = document.getElementById('dentist_id');
            const dentistId = dentistSelect ? dentistSelect.value : '';
            
            // If dentist is selected, check availability state
            if (dentistId) {
                if (!window.dentistAvailabilityState || !window.dentistAvailabilityState.available) {
                    event.preventDefault();
                    alert('Cannot proceed: Dentist is not available. Please check the availability status and select a different dentist, date, time, or service.');
                    return false;
                }
            }
            
            return true;
        }
        
        // Store availability state globally
        window.dentistAvailabilityState = null;
        
        // Function to update time slots based on selected date
        function updateTimeSlots() {
            const dateInput = document.getElementById('scheduled_date');
            const timeSelect = document.getElementById('scheduled_time');
            const dentistSelect = document.getElementById('dentist_id');
            const selectedDate = dateInput.value;
            const today = new Date().toISOString().split('T')[0];
            const now = new Date();
            const currentTime = now.toTimeString().slice(0, 5); // HH:MM format (e.g., "09:30")
            const selectedDentist = dentistSelect.value;
            
            // Check if selected date is a closure date
            if (selectedDate && isClosureDate(selectedDate)) {
                showClosureDateError(selectedDate);
                return;
            } else {
                hideClosureDateError();
            }
            
            // Get all time options (excluding the "Select time" option)
            const timeOptions = timeSelect.querySelectorAll('option[data-time]');
            
            timeOptions.forEach(function(option) {
                const timeValue = option.getAttribute('data-time');
                let shouldDisable = false;
                
                // If selected date is today, disable past times
                if (selectedDate === today) {
                    if (timeValue <= currentTime) {
                        shouldDisable = true;
                    }
                }
                
                // If a specific dentist is selected, disable occupied times
                if (selectedDentist && window.occupiedTimes && window.occupiedTimes.length > 0) {
                    if (window.occupiedTimes.includes(timeValue)) {
                        shouldDisable = true;
                    }
                }
                
                if (shouldDisable) {
                    option.disabled = true;
                    option.style.color = '#9ca3af'; // Gray out disabled options
                } else {
                    option.disabled = false;
                    option.style.color = '';
                }
            });
            
            // If currently selected time is now disabled, clear selection
            if (timeSelect.value) {
                const selectedOption = timeSelect.options[timeSelect.selectedIndex];
                if (selectedOption && selectedOption.disabled) {
                    timeSelect.value = '';
                    // Check availability again if dentist is selected
                    if (selectedDentist) {
                        checkDentistAvailability();
                    }
                }
            }
            
            updateSummary();
        }

document.getElementById('scheduled_date').addEventListener('change', function() {
    updateTimeSlots();
    updateSummary();
    // Reset availability state when date changes
    window.dentistAvailabilityState = null;
    // Check availability if dentist is selected
    const dentistSelect = document.getElementById('dentist_id');
    if (dentistSelect.value) {
        checkDentistAvailability();
    }
    // Update button state
    updateSubmitButtonState();
});
document.getElementById('scheduled_time').addEventListener('change', function() {
    updateSummary();
    // Reset availability state when time changes
    window.dentistAvailabilityState = null;
    // Check availability if dentist is selected
    const dentistSelect = document.getElementById('dentist_id');
    if (dentistSelect.value) {
        checkDentistAvailability();
    }
    // Update button state
    updateSubmitButtonState();
});
document.getElementById('service_id').addEventListener('change', function() {
    updateServiceInfo();
    // Reset availability state when service changes
    window.dentistAvailabilityState = null;
    // Check availability if dentist is selected
    const dentistSelect = document.getElementById('dentist_id');
    if (dentistSelect.value) {
        checkDentistAvailability();
    }
    // Update button state
    updateSubmitButtonState();
});
document.getElementById('dentist_id').addEventListener('change', function() {
    updateTimeSlots();
    
    // Reset availability state when dentist changes
    window.dentistAvailabilityState = null;
    
    // Check availability if date, time, and service are selected
    const dateInput = document.getElementById('scheduled_date').value;
    const timeInput = document.getElementById('scheduled_time').value;
    const serviceSelect = document.getElementById('service_id');
    
    if (this.value && dateInput && timeInput && serviceSelect.value) {
        checkDentistAvailability();
    } else {
        // Clear availability status if no dentist selected
        const availabilityStatus = document.getElementById('dentist_availability_status');
        if (availabilityStatus) {
            availabilityStatus.classList.add('hidden');
            availabilityStatus.innerHTML = '';
        }
        window.occupiedTimes = [];
        updateTimeSlots();
    }
    
    // Update button state
    updateSubmitButtonState();
});

// Initialize occupied times array
window.occupiedTimes = [];

// Initialize on page load
window.prerequisitesBlocking = false; // Initialize flag
updateTimeSlots();
updateServiceInfo();
updateSummary();

// Check availability on page load if all required fields are filled
document.addEventListener('DOMContentLoaded', function() {
    const dentistSelect = document.getElementById('dentist_id');
    const dateInput = document.getElementById('scheduled_date');
    const timeSelect = document.getElementById('scheduled_time');
    const serviceSelect = document.getElementById('service_id');
    const submitBtn = document.querySelector('button[type="submit"]');
    
    // If dentist is selected, check availability
    if (dentistSelect && dentistSelect.value && dateInput && dateInput.value && 
        timeSelect && timeSelect.value && serviceSelect && serviceSelect.value) {
        checkDentistAvailability();
    } else if (dentistSelect && dentistSelect.value && submitBtn) {
        // If dentist is selected but other fields are missing, disable button
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
    } else if (!dentistSelect.value && submitBtn) {
        // If no dentist selected, button should be enabled if other fields are filled
        // (system will auto-assign dentist) BUT only if prerequisites are not blocking
        if (dateInput && dateInput.value && timeSelect && timeSelect.value && 
            serviceSelect && serviceSelect.value) {
            if (window.prerequisitesBlocking) {
                // Prerequisites are blocking - keep button disabled
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            } else {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        }
    }
});
</script>

    <?php if ($payment_required && $pending_appointment): ?>
    <!-- Downpayment Modal - PayMongo Payment Required -->
    <div id="downpaymentModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" style="display: flex;">
        <div class="bg-white dark:bg-[#1a2634] rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-[#0d131b] dark:text-white">PayMongo Downpayment Required</h3>
                <span class="text-xs text-[#4c6c9a] dark:text-slate-400">Required</span>
            </div>
            
            <div class="mb-4">
                <p class="text-sm text-[#4c6c9a] dark:text-slate-400 mb-4">
                    To confirm your appointment, please pay the downpayment of <strong class="text-primary">₱<?php echo number_format($pending_appointment['downpayment_amount'], 2); ?></strong> (35% of total service price) via PayMongo.
                </p>
                
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 mb-4">
                    <p class="text-xs text-blue-800 dark:text-blue-300 flex items-start gap-2">
                        <span class="material-symbols-outlined text-base mt-0.5">verified</span>
                        <span><strong>Subject to Discount Verification:</strong> This downpayment is charged at the standard rate. If you have a valid PWD or Senior Citizen ID, please bring it to the clinic for verification. Any discount will be applied to your total treatment cost, and overpayments will be credited to your remaining balance.</span>
                    </p>
                </div>
                
                <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3 mb-4">
                    <p class="text-xs text-yellow-800 dark:text-yellow-300">
                        <strong>⚠️ Important:</strong> After downpayment, it is <strong>NON-REFUNDABLE</strong>.
                    </p>
                </div>
                
                <!-- PayMongo Payment Info -->
                <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-4 mb-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="material-symbols-outlined text-purple-600 dark:text-purple-400">account_balance_wallet</span>
                        <h4 class="font-bold text-purple-800 dark:text-purple-300">Pay via PayMongo</h4>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div>
                            <span class="text-purple-700 dark:text-purple-400 font-medium">Amount to Pay:</span>
                            <p class="text-purple-900 dark:text-purple-200 font-bold text-lg">₱<?php echo number_format($pending_appointment['downpayment_amount'], 2); ?></p>
                        </div>
                        <p class="text-xs text-purple-700 dark:text-purple-400 mt-3">
                            💳 Pay securely via PayMongo. You can use GCash, GrabPay, or Credit/Debit Cards.
                        </p>
                    </div>
                </div>
                
                <div class="space-y-2 text-sm mb-4">
                    <div class="flex justify-between">
                        <span class="text-[#4c6c9a] dark:text-slate-400">Service:</span>
                        <span class="font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($pending_appointment['service_name']); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-[#4c6c9a] dark:text-slate-400">Total Price:</span>
                        <span class="font-medium text-[#0d131b] dark:text-white">₱<?php echo number_format($pending_appointment['service_price'], 2); ?></span>
                    </div>
                    <div class="flex justify-between border-t border-[#e7ecf3] dark:border-slate-700 pt-2">
                        <span class="text-[#4c6c9a] dark:text-slate-400">Downpayment (35%):</span>
                        <span class="font-bold text-primary text-lg">₱<?php echo number_format($pending_appointment['downpayment_amount'], 2); ?></span>
                    </div>
                </div>
            </div>
            
            <form id="downpaymentForm" method="POST" action="process_downpayment_paymongo.php">
                <input type="hidden" name="appointment_id" value="<?php echo $pending_appointment['appointment_id']; ?>">
                <input type="hidden" name="amount" value="<?php echo $pending_appointment['downpayment_amount']; ?>">
                
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3 mb-4">
                    <p class="text-xs text-blue-800 dark:text-blue-300">
                        <strong>ℹ️ Note:</strong> You will be redirected to PayMongo's secure payment page. Your appointment will be confirmed automatically after successful payment.
                    </p>
                </div>
                
                <button type="submit" class="w-full rounded-lg bg-primary text-white py-3 text-sm font-semibold hover:bg-primary-dark transition-colors flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">payment</span>
                    Proceed to PayMongo
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>