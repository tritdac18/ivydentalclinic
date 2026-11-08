<?php
require_once 'config/db.php';
require_once 'config/security.php';

// Set security headers and check login
requireLogin();

$user_type = $_SESSION['user_type'] ?? 'patient';
$user_role = $_SESSION['user_role'] ?? '';
// Treat both Admin and Manager as admin-level staff
$is_admin = ($user_type === 'staff' && ($user_role === 'Manager' || $user_role === 'Admin' || $user_role === 'Dentist' || $user_role === 'Receptionist'));
$is_admin_only = ($user_type === 'staff' && ($user_role === 'Manager' || $user_role === 'Admin'));
$is_receptionist = ($user_type === 'staff' && $user_role === 'Receptionist');
$is_dentist = ($user_type === 'staff' && $user_role === 'Dentist');
    $is_manager = ($user_type === 'staff' && $user_role === 'Manager');
$edit_mode = isset($_GET['edit']) && $_GET['edit'] === '1';
$viewing_patient_id = null;
$viewing_staff_id = null;
$is_viewing_patient = false;
$is_viewing_staff = false;

// Check if admin or dentist is viewing a patient's profile
if (($is_admin_only || $is_dentist) && isset($_GET['patient_id'])) {
    $viewing_patient_id = intval($_GET['patient_id']);
    if ($viewing_patient_id > 0) {
        $is_viewing_patient = true;
    }
}

// Check if admin is viewing a staff member's profile
if ($is_admin_only && isset($_GET['staff_id'])) {
    $viewing_staff_id = intval($_GET['staff_id']);
    if ($viewing_staff_id > 0) {
        $is_viewing_staff = true;
    }
}

$errors = [];
$success_message = '';

// Prevent editing when viewing another patient's or staff's profile (admin view mode)
if (($is_viewing_patient || $is_viewing_staff) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $errors[] = "You cannot edit profiles in view mode.";
    $success_message = '';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
// Handle form submission for profile update
    $username = trim($_POST['username'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    
    // Patient-specific fields
    $dob = trim($_POST['dob'] ?? '');
    $phone = trim($_POST['primary_phone'] ?? '');
    $blood_type = trim($_POST['blood_type'] ?? 'Unknown');
    $gender = trim($_POST['gender'] ?? 'Prefer not to say');
    $allergies = isset($_POST['allergies']) ? array_filter(array_map('trim', $_POST['allergies'])) : [];
    
    // Staff-specific fields
    $phone_number = trim($_POST['phone_number'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    
    // Validation
    // Username is not editable, so skip validation
    
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }
    
    if ($user_type === 'patient') {
        if (empty($dob)) {
            $errors[] = "Date of birth is required.";
        } else {
            // Convert date format if needed (MM/DD/YYYY to YYYY-MM-DD)
            $date_parts = explode('/', $dob);
            if (count($date_parts) === 3) {
                $month = str_pad($date_parts[0], 2, '0', STR_PAD_LEFT);
                $day = str_pad($date_parts[1], 2, '0', STR_PAD_LEFT);
                $year = $date_parts[2];
                $dob = $year . '-' . $month . '-' . $day;
            }
            
            // Validate the date
            $date_obj = DateTime::createFromFormat('Y-m-d', $dob);
            if (!$date_obj || $date_obj->format('Y-m-d') !== $dob) {
                $errors[] = "Invalid date format.";
            }
        }
        
        if (empty($phone)) {
            $errors[] = "Phone number is required.";
        }
    } else {
        // Staff validation
        if (empty($phone_number)) {
            $errors[] = "Phone number is required.";
        }
        
        // Dentist-specific validation
        if ($is_dentist && empty($license_number)) {
            $errors[] = "License number is required for dentists.";
        }
        
        // Validate educational background (at least 1 required)
        if (isset($_POST['education']) && is_array($_POST['education'])) {
            $valid_education_count = 0;
            foreach ($_POST['education'] as $edu) {
                if (!empty($edu['institution_name']) && !empty($edu['degree'])) {
                    $valid_education_count++;
                }
            }
            if ($valid_education_count === 0) {
                $errors[] = "At least one educational background entry is required (institution name and degree).";
            }
        } else {
            $errors[] = "At least one educational background entry is required.";
        }
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email address is required.";
    }
    
    // Check if username/email is already taken
    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            
            if ($user_type === 'patient') {
                // Username is not editable, skip username checks
                
                // Check email
                if (empty($errors)) {
                    $check_stmt = $conn->prepare("SELECT patient_id FROM tbl_patients WHERE email = ? AND patient_id != ? LIMIT 1");
                    $check_stmt->bind_param("si", $email, $_SESSION['user_id']);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        $errors[] = "This email address is already registered by another user.";
                    }
                    $check_stmt->close();
                }
                
                // Update patient information if no errors
                if (empty($errors)) {
                    // Validate blood type and gender
                    $valid_blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'];
                    if (!in_array($blood_type, $valid_blood_types)) {
                        $blood_type = 'Unknown';
                    }
                    
                    $valid_genders = ['Male', 'Female', 'Other', 'Prefer not to say'];
                    if (!in_array($gender, $valid_genders)) {
                        $gender = 'Prefer not to say';
                    }
                    
                    $update_stmt = $conn->prepare("UPDATE tbl_patients SET first_name = ?, last_name = ?, dob = ?, primary_phone = ?, email = ?, blood_type = ?, gender = ? WHERE patient_id = ?");
                    $update_stmt->bind_param("sssssssi", $first_name, $last_name, $dob, $phone, $email, $blood_type, $gender, $_SESSION['user_id']);
                    
                    if ($update_stmt->execute()) {
                        // Update allergies
                        $delete_allergies = $conn->prepare("DELETE FROM tbl_patient_allergies WHERE patient_id = ?");
                        $delete_allergies->bind_param("i", $_SESSION['user_id']);
                        $delete_allergies->execute();
                        $delete_allergies->close();
                        
                        if (!empty($allergies)) {
                            $allergy_stmt = $conn->prepare("INSERT INTO tbl_patient_allergies (patient_id, allergy_name) VALUES (?, ?)");
                            foreach ($allergies as $allergy) {
                                if (!empty($allergy)) {
                                    $allergy_stmt->bind_param("is", $_SESSION['user_id'], $allergy);
                                    $allergy_stmt->execute();
                                }
                            }
                            $allergy_stmt->close();
                        }
                        
                        $success_message = "Profile updated successfully!";
                        $edit_mode = false;
                    } else {
                        $errors[] = "Failed to update profile. Please try again.";
                    }
                    $update_stmt->close();
                }
            } else {
                // Staff update
                // Username is not editable, skip username checks
                
                // Check email
                if (empty($errors)) {
                    $check_stmt = $conn->prepare("SELECT staff_id FROM tbl_staff WHERE email = ? AND staff_id != ? LIMIT 1");
                    $check_stmt->bind_param("si", $email, $_SESSION['user_id']);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        $errors[] = "This email address is already registered by another user.";
                    }
                    $check_stmt->close();
                }
                
                // Update staff information if no errors
                if (empty($errors)) {
                    // Build update query based on available fields (username is not editable)
                    $update_fields = "first_name = ?, last_name = ?, email = ?, phone_number = ?";
                    $update_params = [$first_name, $last_name, $email, $phone_number];
                    $update_types = "ssss";
                    
                    if ($license_number !== '') {
                        $update_fields .= ", license_number = ?";
                        $update_params[] = $license_number;
                        $update_types .= "s";
                    }
                    
                    $update_params[] = $_SESSION['user_id'];
                    $update_types .= "i";
                    
                    $update_stmt = $conn->prepare("UPDATE tbl_staff SET $update_fields WHERE staff_id = ?");
                    $update_stmt->bind_param($update_types, ...$update_params);
                    
                    if ($update_stmt->execute()) {
                        // Handle educational background
                        if (isset($_POST['education']) && is_array($_POST['education'])) {
                            // Delete existing education entries
                            $delete_edu = $conn->prepare("DELETE FROM tbl_staff_education WHERE staff_id = ?");
                            $delete_edu->bind_param("i", $_SESSION['user_id']);
                            $delete_edu->execute();
                            $delete_edu->close();
                            
                            // Insert new education entries
                            $edu_stmt = $conn->prepare("INSERT INTO tbl_staff_education (staff_id, institution_name, degree, field_of_study, start_date, end_date, is_completed) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            foreach ($_POST['education'] as $edu) {
                                if (!empty($edu['institution_name']) && !empty($edu['degree'])) {
                                    $institution = trim($edu['institution_name']);
                                    $degree = trim($edu['degree']);
                                    $field = !empty($edu['field_of_study']) ? trim($edu['field_of_study']) : null;
                                    $start_date = !empty($edu['start_date']) ? trim($edu['start_date']) : null;
                                    $end_date = !empty($edu['end_date']) ? trim($edu['end_date']) : null;
                                    $is_completed = isset($edu['is_completed']) ? 1 : 0;
                                    
                                    $edu_stmt->bind_param("isssssi", $_SESSION['user_id'], $institution, $degree, $field, $start_date, $end_date, $is_completed);
                                    $edu_stmt->execute();
                                }
                            }
                            $edu_stmt->close();
                        }
                        
                        // Handle specializations (for dentists only) - REQUIRED
                        if ($is_dentist) {
                            if (!isset($_POST['specializations']) || !is_array($_POST['specializations']) || count(array_filter($_POST['specializations'])) === 0) {
                                $errors[] = "At least one specialization is required for dentists.";
                            } else {
                                // Delete existing specializations
                                $delete_spec = $conn->prepare("DELETE FROM tbl_staff_specializations WHERE staff_id = ?");
                                $delete_spec->bind_param("i", $_SESSION['user_id']);
                                $delete_spec->execute();
                                $delete_spec->close();
                                
                                // Insert new specializations
                                $spec_stmt = $conn->prepare("INSERT INTO tbl_staff_specializations (staff_id, specialization_id) VALUES (?, ?)");
                                foreach ($_POST['specializations'] as $spec_id) {
                                    $spec_id = intval($spec_id);
                                    if ($spec_id > 0) {
                                        $spec_stmt->bind_param("ii", $_SESSION['user_id'], $spec_id);
                                        $spec_stmt->execute();
                                    }
                                }
                                $spec_stmt->close();
                            }
                        }
                        
                        $success_message = "Profile updated successfully!";
                        $edit_mode = false;
                    } else {
                        $errors[] = "Failed to update profile. Please try again.";
                    }
                    $update_stmt->close();
                }
            }
            
            $conn->close();
        } catch (Exception $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

// Get user info
$conn = getDBConnection();
$user_info = null;

try {
    // Determine which patient ID to fetch
    $target_patient_id = null;
    $target_staff_id = null;
    
    if ($is_viewing_patient) {
        // Admin or Dentist viewing a patient's profile
        $target_patient_id = $viewing_patient_id;
        
        // If dentist, verify they have access to this patient
        if ($is_dentist && !$is_admin_only) {
            $dentist_id = $_SESSION['user_id'];
            $verify_stmt = $conn->prepare("
                SELECT COUNT(*) as count
                FROM tbl_appointments a
                WHERE a.patient_id = ? AND a.dentist_id = ?
                LIMIT 1
            ");
            $verify_stmt->bind_param("ii", $target_patient_id, $dentist_id);
            $verify_stmt->execute();
            $verify_result = $verify_stmt->get_result();
            $verify_data = $verify_result->fetch_assoc();
            $verify_stmt->close();
            
            if ($verify_data['count'] == 0) {
                // Dentist doesn't have access to this patient
                $conn->close();
                header("Location: dentist_patients.php");
                exit();
            }
        }
    } elseif ($is_viewing_staff) {
        // Admin viewing a staff member's profile
        $target_staff_id = $viewing_staff_id;
    } elseif ($user_type === 'patient') {
        // Patient viewing their own profile
        $target_patient_id = $_SESSION['user_id'];
    }
    
    if ($target_patient_id !== null) {
        // Fetch all patient data from database
        $stmt = $conn->prepare("SELECT patient_id, username, first_name, last_name, dob, primary_phone, email, blood_type, gender FROM tbl_patients WHERE patient_id = ? LIMIT 1");
        $stmt->bind_param("i", $target_patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user_info = $result->fetch_assoc();
            
            // Set defaults if not set
            $user_info['blood_type'] = $user_info['blood_type'] ?? 'Unknown';
            $user_info['gender'] = $user_info['gender'] ?? 'Prefer not to say';
            
            // Fetch patient discounts from tbl_discounts
            $user_info['discounts'] = [];
            $discount_stmt = $conn->prepare("
                SELECT discount_id, discount_type, id_number, discount_percentage, is_verified, verified_at, is_active
                FROM tbl_discounts 
                WHERE patient_id = ? AND is_active = 1
                ORDER BY discount_type
            ");
            $discount_stmt->bind_param("i", $target_patient_id);
            $discount_stmt->execute();
            $discount_result = $discount_stmt->get_result();
            while ($discount_row = $discount_result->fetch_assoc()) {
                $user_info['discounts'][] = $discount_row;
            }
            $discount_stmt->close();
            
            // Set discount IDs for backward compatibility
            $user_info['pwd_id_number'] = null;
            $user_info['senior_citizen_id_number'] = null;
            foreach ($user_info['discounts'] as $discount) {
                if ($discount['discount_type'] === 'PWD' && $discount['is_verified'] == 1) {
                    $user_info['pwd_id_number'] = $discount['id_number'];
                } elseif ($discount['discount_type'] === 'Senior Citizen' && $discount['is_verified'] == 1) {
                    $user_info['senior_citizen_id_number'] = $discount['id_number'];
                }
            }
            
            // Fetch patient allergies
            $allergy_stmt = $conn->prepare("SELECT allergy_name FROM tbl_patient_allergies WHERE patient_id = ? ORDER BY allergy_name");
            $allergy_stmt->bind_param("i", $target_patient_id);
            $allergy_stmt->execute();
            $allergy_result = $allergy_stmt->get_result();
            $user_info['allergies'] = [];
            while ($row = $allergy_result->fetch_assoc()) {
                $user_info['allergies'][] = $row['allergy_name'];
            }
            $allergy_stmt->close();
            
            // Fetch patient documents (files uploaded by dentists)
            $user_info['patient_documents'] = [];
            $doc_stmt = $conn->prepare("
                SELECT d.document_id, d.document_type, d.file_name, d.file_path, d.uploaded_at, d.notes,
                       s.first_name as uploader_first, s.last_name as uploader_last
                FROM tbl_patient_documents d
                LEFT JOIN tbl_staff s ON d.uploaded_by = s.staff_id
                WHERE d.patient_id = ?
                ORDER BY d.uploaded_at DESC
            ");
            $doc_stmt->bind_param("i", $target_patient_id);
            $doc_stmt->execute();
            $doc_result = $doc_stmt->get_result();
            while ($row = $doc_result->fetch_assoc()) {
                $user_info['patient_documents'][] = $row;
            }
            $doc_stmt->close();
            
            // Fetch parent consent documents (if user is a minor)
            $user_info['parent_consent'] = null;
            if (!empty($user_info['dob'])) {
                $dob_date = new DateTime($user_info['dob']);
                $today = new DateTime();
                $age = $today->diff($dob_date)->y;
                
                if ($age < 18) {
                    // Check if documents table exists
                    $table_check = $conn->query("SHOW TABLES LIKE 'tbl_patient_documents'");
                    if ($table_check && $table_check->num_rows > 0) {
                        $doc_stmt = $conn->prepare("SELECT document_id, document_type, file_name, file_path, uploaded_at, notes FROM tbl_patient_documents WHERE patient_id = ? AND document_type = 'Parent Consent' ORDER BY uploaded_at DESC LIMIT 1");
                        $doc_stmt->bind_param("i", $target_patient_id);
                        $doc_stmt->execute();
                        $doc_result = $doc_stmt->get_result();
                        if ($doc_result->num_rows > 0) {
                            $user_info['parent_consent'] = $doc_result->fetch_assoc();
                        }
                        $doc_stmt->close();
                    }
                }
            }
        } else {
            session_destroy();
            header("Location: login.php");
            exit();
        }
        $stmt->close();
    } elseif ($target_staff_id !== null) {
        // Admin viewing a staff member's profile
        $stmt = $conn->prepare("SELECT staff_id, username, first_name, last_name, email, role, phone_number, license_number FROM tbl_staff WHERE staff_id = ? LIMIT 1");
        $stmt->bind_param("i", $target_staff_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user_info = $result->fetch_assoc();
            // Don't update session role when viewing another staff member
        } else {
            // Staff member not found
            header("Location: accounts.php");
            exit();
        }
        $stmt->close();
    } elseif (!$is_viewing_patient && !$is_viewing_staff) {
        // Fetch staff data from database (only if not viewing a patient or staff)
        $stmt = $conn->prepare("SELECT staff_id, username, first_name, last_name, email, role, phone_number, license_number FROM tbl_staff WHERE staff_id = ? LIMIT 1");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user_info = $result->fetch_assoc();
            $_SESSION['user_role'] = $user_info['role'];
            $user_role = $user_info['role'];
            // Update admin flags after fetching user info
            $is_admin = ($user_type === 'staff' && ($user_role === 'Manager' || $user_role === 'Admin' || $user_role === 'Dentist' || $user_role === 'Receptionist'));
            $is_admin_only = ($user_type === 'staff' && ($user_role === 'Manager' || $user_role === 'Admin'));
            $is_receptionist = ($user_type === 'staff' && $user_role === 'Receptionist');
            $is_manager = ($user_type === 'staff' && $user_role === 'Manager');
        } else {
            session_destroy();
            header("Location: login.php");
            exit();
        }
        $stmt->close();
    } else {
        // Admin viewing patient but patient not found
        header("Location: accounts.php");
        exit();
    }
    
    $conn->close();
} catch (Exception $e) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Set user variables
if ($is_viewing_patient) {
    // Admin/Dentist viewing patient profile
    $viewed_patient_name = $user_info['first_name'] . ' ' . $user_info['last_name'];
    $user_first_name = $user_info['first_name'];
    $user_id = $user_info['patient_id'];
    // Get logged-in user info for display in header
    $admin_conn = getDBConnection();
    $admin_stmt = $admin_conn->prepare("SELECT first_name, last_name, role FROM tbl_staff WHERE staff_id = ? LIMIT 1");
    $admin_stmt->bind_param("i", $_SESSION['user_id']);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    if ($admin_result->num_rows === 1) {
        $admin_info = $admin_result->fetch_assoc();
        $admin_name = $admin_info['first_name'] . ' ' . $admin_info['last_name'];
        $user_name = $admin_name; // Use logged-in user's name for header
        $viewing_user_role = $admin_info['role'];
    }
    $admin_stmt->close();
    $admin_conn->close();
} elseif ($is_viewing_staff) {
    // Admin viewing staff profile
    $viewed_staff_name = $user_info['first_name'] . ' ' . $user_info['last_name'];
    $user_first_name = $user_info['first_name'];
    $user_id = $user_info['staff_id'];
    // Get logged-in user info for display in header
    $admin_conn = getDBConnection();
    $admin_stmt = $admin_conn->prepare("SELECT first_name, last_name, role FROM tbl_staff WHERE staff_id = ? LIMIT 1");
    $admin_stmt->bind_param("i", $_SESSION['user_id']);
    $admin_stmt->execute();
    $admin_result = $admin_stmt->get_result();
    if ($admin_result->num_rows === 1) {
        $admin_info = $admin_result->fetch_assoc();
        $admin_name = $admin_info['first_name'] . ' ' . $admin_info['last_name'];
        $user_name = $admin_name; // Use logged-in user's name for header
        $viewing_user_role = $admin_info['role'];
    }
    $admin_stmt->close();
    $admin_conn->close();
} else {
    $user_name = $user_info['first_name'] . ' ' . $user_info['last_name'];
    $user_first_name = $user_info['first_name'];
    $user_id = $user_type === 'patient' ? $user_info['patient_id'] : $user_info['staff_id'];
}

// Format date of birth (patients only)
$dob_formatted = '';
if (($user_type === 'patient' || $is_viewing_patient) && !empty($user_info['dob'])) {
    $dob_date = new DateTime($user_info['dob']);
    $dob_formatted = $dob_date->format('F d, Y');
}

// Fetch staff educational background and specializations
$user_info['education'] = [];
$user_info['specializations'] = [];
$all_specializations = [];

if ($user_type === 'staff' && !$is_viewing_patient) {
    $staff_id = $is_viewing_staff ? $viewing_staff_id : $_SESSION['user_id'];
    
    try {
        $conn = getDBConnection();
        
        // Fetch educational background
        $edu_stmt = $conn->prepare("SELECT institution_name, degree, field_of_study, start_date, end_date, is_completed FROM tbl_staff_education WHERE staff_id = ? ORDER BY start_date DESC");
        $edu_stmt->bind_param("i", $staff_id);
        $edu_stmt->execute();
        $edu_result = $edu_stmt->get_result();
        while ($row = $edu_result->fetch_assoc()) {
            $user_info['education'][] = $row;
        }
        $edu_stmt->close();
        
        // Fetch dentist specializations
        if ($is_dentist || ($is_viewing_staff && $user_info['role'] === 'Dentist')) {
            $spec_stmt = $conn->prepare("SELECT specialization_id FROM tbl_staff_specializations WHERE staff_id = ?");
            $spec_stmt->bind_param("i", $staff_id);
            $spec_stmt->execute();
            $spec_result = $spec_stmt->get_result();
            while ($row = $spec_result->fetch_assoc()) {
                $user_info['specializations'][] = $row['specialization_id'];
            }
            $spec_stmt->close();
            
            // Fetch all available specializations
            $all_spec_stmt = $conn->prepare("SELECT specialization_id, name, description FROM tbl_specializations WHERE is_active = 1 ORDER BY name");
            $all_spec_stmt->execute();
            $all_spec_result = $all_spec_stmt->get_result();
            while ($row = $all_spec_result->fetch_assoc()) {
                $all_specializations[] = $row;
            }
            $all_spec_stmt->close();
        }
        
        $conn->close();
    } catch (Exception $e) {
        // Tables might not exist yet, ignore error
        error_log("Error fetching education/specializations: " . $e->getMessage());
    }
}
?>
<?php
$page_title = 'Profile';
include ROOT_PATH . '/views/layout/header.php';
?>
<div class="flex min-h-screen">
    <?php include ROOT_PATH . (($user_type === 'patient') ? '/views/layout/sidebar_patient.php' : '/views/layout/sidebar_staff.php'); ?>
    <div class="flex-1 flex flex-col lg:ml-64">
        <?php include ROOT_PATH . '/views/layout/topbar.php'; ?>
<main class="flex-grow w-full max-w-7xl mx-auto px-4 md:px-8 py-8">
<?php if ($is_viewing_patient || $is_viewing_staff): ?>
<div class="mb-6 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg dark:bg-blue-900/20 dark:border-blue-800 dark:text-blue-400">
<div class="flex items-center justify-between">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined">visibility</span>
<p class="text-sm font-medium">Viewing <?php echo $is_viewing_patient ? 'Patient' : 'Staff'; ?> Profile - Read Only Mode</p>
</div>
<a href="<?php echo $is_dentist && !$is_admin_only ? 'dentist_patients.php' : 'accounts.php'; ?>" class="inline-flex items-center gap-2 text-sm font-medium text-blue-700 dark:text-blue-400 hover:text-blue-900 dark:hover:text-blue-300">
<span class="material-symbols-outlined text-base">arrow_back</span>
<span>Back to <?php echo $is_dentist && !$is_admin_only ? 'Patients' : 'Accounts'; ?></span>
</a>
</div>
</div>
<?php endif; ?>
<div class="flex flex-col gap-8">
<div class="flex flex-col gap-4">
<div class="flex items-center gap-2 text-sm">
<a class="font-medium text-[#4c6c9a] hover:text-primary dark:text-slate-400 dark:hover:text-blue-300" href="<?php echo ($is_viewing_patient && $is_dentist && !$is_admin_only) ? 'dentist_patients.php' : (($is_viewing_patient || $is_viewing_staff) ? 'accounts.php' : 'landing.php'); ?>"><?php echo ($is_viewing_patient && $is_dentist && !$is_admin_only) ? 'Patients' : (($is_viewing_patient || $is_viewing_staff) ? 'Accounts' : 'Home'); ?></a>
<span class="text-[#4c6c9a] dark:text-slate-600">/</span>
<span class="font-medium text-[#0d131b] dark:text-white">Profile</span>
</div>
<div class="flex flex-wrap items-end justify-between gap-4">
<div>
                <h1 class="text-3xl font-bold tracking-tight text-[#0d131b] dark:text-white"><?php 
                    if ($is_viewing_patient) echo 'Patient';
                    elseif ($is_viewing_staff) echo 'Staff';
                    elseif ($user_type === 'patient') echo 'Customer';
                    elseif ($is_dentist) echo 'Dentist';
                    elseif ($user_role === 'Receptionist') echo 'Staff';
                    elseif ($user_role === 'Manager') echo 'Manager';
                    else echo 'Admin';
                ?> Profile</h1>
<p class="mt-2 text-[#4c6c9a] dark:text-slate-400"><?php echo ($is_viewing_patient || $is_viewing_staff) ? 'View ' . ($is_viewing_patient ? 'patient' : 'staff') . ' information and history.' : 'Manage your personal information' . ($user_type === 'patient' ? ' and view your history' : '') . '.'; ?></p>
</div>
<div class="flex gap-3">
<?php if ($is_viewing_patient || $is_viewing_staff): ?>
<a href="accounts.php" class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-200 dark:bg-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-white hover:bg-slate-300 dark:hover:bg-slate-600 transition-all">
<span class="material-symbols-outlined text-[20px]">arrow_back</span>
<span>Back to Accounts</span>
</a>
<?php elseif ($edit_mode): ?>
<a href="profile.php<?php echo $is_viewing_patient ? '?patient_id=' . $viewing_patient_id : ($is_viewing_staff ? '?staff_id=' . $viewing_staff_id : ''); ?>" class="inline-flex items-center justify-center gap-2 rounded-lg bg-slate-200 dark:bg-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-white hover:bg-slate-300 dark:hover:bg-slate-600 transition-all">
<span class="material-symbols-outlined text-[20px]">close</span>
<span>Cancel</span>
</a>
<?php else: ?>
<a href="profile.php?edit=1<?php echo $is_viewing_patient ? '&patient_id=' . $viewing_patient_id : ($is_viewing_staff ? '&staff_id=' . $viewing_staff_id : ''); ?>" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-md shadow-blue-500/20 hover:bg-primary-dark transition-all">
<span class="material-symbols-outlined text-[20px]">edit</span>
<span>Edit Profile</span>
</a>
<?php endif; ?>
</div>
</div>
</div>
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
<div class="lg:col-span-2 flex flex-col gap-6">
<div class="rounded-xl border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 shadow-sm">
<div class="flex items-center justify-between border-b border-[#e7ecf3] px-6 py-4 dark:border-slate-700">
<div class="flex items-center gap-3">
<div class="rounded-full bg-primary/10 p-2 text-primary dark:bg-primary/20 dark:text-blue-300">
<span class="material-symbols-outlined">badge</span>
</div>
<h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Personal Information</h3>
</div>
</div>
<div class="p-6">
<?php
// Show success/error messages
if (!empty($success_message)) {
    echo '<div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-400">';
    echo '<p class="text-sm font-medium">' . htmlspecialchars($success_message) . '</p>';
    echo '</div>';
}
if (!empty($errors)) {
    echo '<div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-400">';
    foreach ($errors as $error) {
        echo '<p class="text-sm">' . htmlspecialchars($error) . '</p>';
    }
    echo '</div>';
}

// Format DOB for input field (MM/DD/YYYY) - patients only
$dob_input = '';
if ($user_type === 'patient' && !empty($user_info['dob'])) {
    $dob_date = new DateTime($user_info['dob']);
    $dob_input = $dob_date->format('m/d/Y');
}
?>
<?php if ($edit_mode && !$is_viewing_patient && !$is_viewing_staff): ?>
<form method="POST" action="profile.php">
<input type="hidden" name="update_profile" value="1">
<div class="grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-2">
<div class="col-span-1 sm:col-span-2">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Username</label>
<input type="text" name="username" value="<?php echo htmlspecialchars($user_info['username'] ?? ''); ?>" readonly class="w-full rounded-lg border border-[#e7ecf3] bg-slate-50 dark:bg-slate-800 dark:border-slate-700 text-[#0d131b] dark:text-white h-10 px-4 transition-all cursor-not-allowed" placeholder="Username"/>
<p class="text-xs text-[#4c6c9a] dark:text-slate-400 mt-1">Username cannot be changed</p>
</div>
<div class="col-span-1">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">First Name</label>
<input type="text" name="first_name" value="<?php echo htmlspecialchars($user_info['first_name'] ?? ''); ?>" required class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 transition-all" placeholder="First Name"/>
</div>
<div class="col-span-1">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Last Name</label>
<input type="text" name="last_name" value="<?php echo htmlspecialchars($user_info['last_name'] ?? ''); ?>" required class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 transition-all" placeholder="Last Name"/>
</div>
<?php if ($user_type === 'patient' || $is_viewing_patient): ?>
<div class="col-span-1 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Date of Birth</label>
<div class="relative">
<input type="text" name="dob" id="dob" value="<?php echo htmlspecialchars($dob_input); ?>" required class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 pr-10 transition-all" placeholder="MM/DD/YYYY"/>
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-[#4c6c9a] text-[20px] pointer-events-none">calendar_today</span>
</div>
</div>
<div class="col-span-1 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Phone Number</label>
<div class="relative">
<input type="tel" name="primary_phone" value="<?php echo htmlspecialchars($user_info['primary_phone'] ?? ''); ?>" required class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 pr-10 transition-all" placeholder="(+63) 900-000-0000"/>
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-[#4c6c9a] text-[20px] pointer-events-none">call</span>
</div>
</div>
<div class="col-span-1 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Blood Type</label>
<select name="blood_type" class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 transition-all">
<option value="Unknown" <?php echo (($user_info['blood_type'] ?? 'Unknown') === 'Unknown') ? 'selected' : ''; ?>>Unknown</option>
<option value="A+" <?php echo (($user_info['blood_type'] ?? '') === 'A+') ? 'selected' : ''; ?>>A+</option>
<option value="A-" <?php echo (($user_info['blood_type'] ?? '') === 'A-') ? 'selected' : ''; ?>>A-</option>
<option value="B+" <?php echo (($user_info['blood_type'] ?? '') === 'B+') ? 'selected' : ''; ?>>B+</option>
<option value="B-" <?php echo (($user_info['blood_type'] ?? '') === 'B-') ? 'selected' : ''; ?>>B-</option>
<option value="AB+" <?php echo (($user_info['blood_type'] ?? '') === 'AB+') ? 'selected' : ''; ?>>AB+</option>
<option value="AB-" <?php echo (($user_info['blood_type'] ?? '') === 'AB-') ? 'selected' : ''; ?>>AB-</option>
<option value="O+" <?php echo (($user_info['blood_type'] ?? '') === 'O+') ? 'selected' : ''; ?>>O+</option>
<option value="O-" <?php echo (($user_info['blood_type'] ?? '') === 'O-') ? 'selected' : ''; ?>>O-</option>
</select>
</div>
<div class="col-span-1 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Gender</label>
<select name="gender" class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 transition-all">
<option value="Prefer not to say" <?php echo (($user_info['gender'] ?? 'Prefer not to say') === 'Prefer not to say') ? 'selected' : ''; ?>>Prefer not to say</option>
<option value="Male" <?php echo (($user_info['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
<option value="Female" <?php echo (($user_info['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
<option value="Other" <?php echo (($user_info['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
</select>
</div>
<div class="col-span-1 sm:col-span-2 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Allergies</label>
<div id="allergies-container" class="space-y-2">
<?php
$existing_allergies = $user_info['allergies'] ?? [];
if (empty($existing_allergies)) {
    $existing_allergies = [''];
}
foreach ($existing_allergies as $index => $allergy): ?>
<div class="flex gap-2 items-center">
<input class="flex-1 rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 transition-all" placeholder="e.g., Penicillin, Latex, Peanuts" type="text" name="allergies[]" value="<?php echo htmlspecialchars($allergy); ?>"/>
<?php if ($index > 0 || !empty($allergy)): ?>
<button type="button" onclick="removeAllergy(this)" class="rounded-lg border border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/30 h-10 w-10 flex items-center justify-center transition-colors">
<span class="material-symbols-outlined text-[18px]">close</span>
</button>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<button type="button" onclick="addAllergyField()" class="mt-2 text-sm text-primary hover:underline flex items-center gap-1">
<span class="material-symbols-outlined text-[16px]">add</span>
<span>Add another allergy</span>
</button>
</div>
<?php else: ?>
<div class="col-span-1 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Phone Number</label>
<div class="relative">
<input type="tel" name="phone_number" value="<?php echo htmlspecialchars($user_info['phone_number'] ?? ''); ?>" required class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 pr-10 transition-all" placeholder="(+63) 900-000-0000"/>
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-[#4c6c9a] text-[20px] pointer-events-none">call</span>
</div>
</div>
<div class="col-span-1 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">License Number <?php if ($is_dentist): ?><span class="text-red-500">*</span><?php endif; ?></label>
<input type="text" name="license_number" value="<?php echo htmlspecialchars($user_info['license_number'] ?? ''); ?>" <?php if ($is_dentist): ?>required<?php endif; ?> class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 transition-all" placeholder="License Number"/>
</div>
<?php if ($is_dentist): ?>
<div class="col-span-1 sm:col-span-2 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-2 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Specializations <span class="text-red-500">*</span></label>
<p class="text-xs text-[#4c6c9a] dark:text-slate-400 mb-3">Select one or more specializations (you can select multiple). At least one specialization is required.</p>
<?php if (!empty($all_specializations)): ?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-3">
<?php foreach ($all_specializations as $spec): ?>
<div class="flex items-start gap-2">
<input type="checkbox" name="specializations[]" value="<?php echo $spec['specialization_id']; ?>" id="spec_<?php echo $spec['specialization_id']; ?>" class="mt-1 rounded border-slate-200 dark:border-slate-600 text-primary focus:ring-primary" <?php echo in_array($spec['specialization_id'], $user_info['specializations'] ?? []) ? 'checked' : ''; ?>>
<label for="spec_<?php echo $spec['specialization_id']; ?>" class="text-sm text-[#0d131b] dark:text-white cursor-pointer">
<?php echo htmlspecialchars($spec['name']); ?>
<?php if (!empty($spec['description'])): ?>
<p class="text-xs text-[#4c6c9a] dark:text-slate-400 mt-0.5"><?php echo htmlspecialchars($spec['description']); ?></p>
<?php endif; ?>
</label>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3">
<p class="text-sm text-yellow-700 dark:text-yellow-400">No specializations available. Please contact administrator to add specializations.</p>
</div>
<?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
<?php if ($user_type === 'staff' && !$is_viewing_patient && !$is_viewing_staff): ?>
<div class="col-span-1 sm:col-span-2 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-2 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Educational Background <span class="text-red-500">*</span></label>
<p class="text-xs text-[#4c6c9a] dark:text-slate-400 mb-3">At least one educational background entry is required.</p>
<div id="education-container" class="space-y-4">
<?php
$existing_education = $user_info['education'] ?? [];
if (empty($existing_education)) {
    $existing_education = [['institution_name' => '', 'degree' => '', 'field_of_study' => '', 'start_date' => '', 'end_date' => '', 'is_completed' => 1]];
}
foreach ($existing_education as $index => $edu): ?>
<div class="education-entry border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50 dark:bg-slate-800/50">
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div class="md:col-span-2">
<label class="mb-1 block text-xs font-medium text-[#4c6c9a] dark:text-slate-400">Institution Name <span class="text-red-500">*</span></label>
<input type="text" name="education[<?php echo $index; ?>][institution_name]" value="<?php echo htmlspecialchars($edu['institution_name'] ?? ''); ?>" required class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 transition-all" placeholder="University Name"/>
</div>
<div class="md:col-span-2">
<label class="mb-1 block text-xs font-medium text-[#4c6c9a] dark:text-slate-400">Degree <span class="text-red-500">*</span></label>
<input type="text" name="education[<?php echo $index; ?>][degree]" value="<?php echo htmlspecialchars($edu['degree'] ?? ''); ?>" required class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 transition-all" placeholder="e.g., Bachelor of Science in Dentistry"/>
</div>
<div>
<label class="mb-1 block text-xs font-medium text-[#4c6c9a] dark:text-slate-400">Field of Study</label>
<input type="text" name="education[<?php echo $index; ?>][field_of_study]" value="<?php echo htmlspecialchars($edu['field_of_study'] ?? ''); ?>" class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 transition-all" placeholder="Optional"/>
</div>
<div>
<label class="mb-1 block text-xs font-medium text-[#4c6c9a] dark:text-slate-400">Start Date</label>
<input type="date" name="education[<?php echo $index; ?>][start_date]" value="<?php echo htmlspecialchars($edu['start_date'] ?? ''); ?>" class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 transition-all"/>
</div>
<div>
<label class="mb-1 block text-xs font-medium text-[#4c6c9a] dark:text-slate-400">End Date</label>
<input type="date" name="education[<?php echo $index; ?>][end_date]" value="<?php echo htmlspecialchars($edu['end_date'] ?? ''); ?>" class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 transition-all"/>
</div>
<div class="md:col-span-2 flex items-center gap-2">
<input type="checkbox" name="education[<?php echo $index; ?>][is_completed]" value="1" id="edu_completed_<?php echo $index; ?>" class="rounded border-slate-200 dark:border-slate-600 text-primary focus:ring-primary" <?php echo (!isset($edu['is_completed']) || $edu['is_completed']) ? 'checked' : ''; ?>>
<label for="edu_completed_<?php echo $index; ?>" class="text-xs text-[#4c6c9a] dark:text-slate-400">Completed</label>
</div>
<?php if ($index > 0 || (!empty($edu['institution_name']) && !empty($edu['degree']))): ?>
<div class="md:col-span-2 flex justify-end">
<button type="button" onclick="removeEducation(this)" class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 text-sm font-medium">Remove</button>
</div>
<?php endif; ?>
</div>
</div>
<?php endforeach; ?>
</div>
<button type="button" onclick="addEducationField()" class="mt-3 text-sm text-primary hover:underline flex items-center gap-1">
<span class="material-symbols-outlined text-[18px]">add</span>
<span>Add Another Education Entry</span>
</button>
</div>
<?php endif; ?>
<div class="col-span-1 sm:col-span-2 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Email Address</label>
<div class="relative">
<input type="email" name="email" value="<?php echo htmlspecialchars($user_info['email'] ?? ''); ?>" required class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 pr-10 transition-all" placeholder="email@example.com"/>
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-[#4c6c9a] text-[20px] pointer-events-none">mail</span>
</div>
</div>
<?php if ($user_type === 'staff'): ?>
<div class="col-span-1 sm:col-span-2 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Role</label>
<div class="text-base font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($user_info['role'] ?? 'N/A'); ?></div>
<p class="text-xs text-[#4c6c9a] dark:text-slate-400 mt-1">Role cannot be changed</p>
</div>
<?php endif; ?>
<div class="col-span-1 sm:col-span-2 pt-4">
<button type="submit" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-6 py-2 text-sm font-medium text-white shadow-md shadow-blue-500/20 hover:bg-primary-dark transition-all">
<span class="material-symbols-outlined text-[20px]">save</span>
<span>Save Changes</span>
</button>
</div>
</div>
</form>
<?php else: ?>
<div class="grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-2">
<div class="col-span-1 sm:col-span-2">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Username</label>
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-sm text-slate-400">person</span>
<div class="text-base font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($user_info['username'] ?? 'N/A'); ?></div>
</div>
</div>
<div class="col-span-1">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">First Name</label>
<div class="text-base font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($user_info['first_name'] ?? 'N/A'); ?></div>
</div>
<div class="col-span-1">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Last Name</label>
<div class="text-base font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($user_info['last_name'] ?? 'N/A'); ?></div>
</div>
<?php if (($user_type === 'patient' || $is_viewing_patient) && !$is_viewing_staff): ?>
<div class="col-span-1 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Date of Birth</label>
<div class="text-base font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($dob_formatted ?: 'N/A'); ?></div>
</div>
<div class="col-span-1 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Phone Number</label>
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-sm text-slate-400">call</span>
<span class="text-base font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($user_info['primary_phone'] ?? 'N/A'); ?></span>
</div>
</div>
<div class="col-span-1 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Blood Type</label>
<div class="text-base font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($user_info['blood_type'] ?? 'Unknown'); ?></div>
</div>
<div class="col-span-1 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Gender</label>
<div class="text-base font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($user_info['gender'] ?? 'Prefer not to say'); ?></div>
</div>
<div class="col-span-1 sm:col-span-2 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Allergies</label>
<?php if (!empty($user_info['allergies'])): ?>
<div class="flex flex-wrap gap-2">
<?php foreach ($user_info['allergies'] as $allergy): ?>
<span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 text-sm font-medium border border-red-200 dark:border-red-800">
<span class="material-symbols-outlined text-[16px]">warning</span>
<?php echo htmlspecialchars($allergy); ?>
</span>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="text-base text-[#4c6c9a] dark:text-slate-400">No known allergies</div>
<?php endif; ?>
</div>
<?php elseif ($user_type === 'staff' || $is_viewing_staff): ?>
<div class="col-span-1 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Phone Number</label>
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-sm text-slate-400">call</span>
<span class="text-base font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($user_info['phone_number'] ?? 'N/A'); ?></span>
</div>
</div>
<div class="col-span-1 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">License Number</label>
<div class="text-base font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($user_info['license_number'] ?? 'N/A'); ?></div>
</div>
<?php if ($is_dentist || ($is_viewing_staff && $user_info['role'] === 'Dentist')): ?>
<div class="col-span-1 sm:col-span-2 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-2 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Specializations</label>
<?php
// Fetch specialization names
$spec_names = [];
if (!empty($user_info['specializations'])) {
    try {
        $spec_conn = getDBConnection();
        $spec_ids = implode(',', array_map('intval', $user_info['specializations']));
        $spec_query = $spec_conn->query("SELECT name FROM tbl_specializations WHERE specialization_id IN ($spec_ids) ORDER BY name");
        while ($row = $spec_query->fetch_assoc()) {
            $spec_names[] = $row['name'];
        }
        $spec_conn->close();
    } catch (Exception $e) {
        // Ignore error
    }
}
?>
<?php if (!empty($spec_names)): ?>
<div class="flex flex-wrap gap-2">
<?php foreach ($spec_names as $spec_name): ?>
<span class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-primary/10 text-primary text-sm font-medium border border-primary/20">
<span class="material-symbols-outlined text-[16px]">badge</span>
<?php echo htmlspecialchars($spec_name); ?>
</span>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="text-base text-[#4c6c9a] dark:text-slate-400">No specializations selected</div>
<?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
<?php if (($user_type === 'staff' || $is_viewing_staff) && !$is_viewing_patient): ?>
<div class="col-span-1 sm:col-span-2 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-2 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Educational Background</label>
<?php if (!empty($user_info['education'])): ?>
<div class="space-y-3">
<?php foreach ($user_info['education'] as $edu): ?>
<div class="border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50 dark:bg-slate-800/50">
<div class="grid grid-cols-1 md:grid-cols-2 gap-3">
<div class="md:col-span-2">
<p class="text-xs text-[#4c6c9a] dark:text-slate-400 mb-1">Institution</p>
<p class="text-sm font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($edu['institution_name']); ?></p>
</div>
<div class="md:col-span-2">
<p class="text-xs text-[#4c6c9a] dark:text-slate-400 mb-1">Degree</p>
<p class="text-sm font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($edu['degree']); ?></p>
</div>
<?php if (!empty($edu['field_of_study'])): ?>
<div>
<p class="text-xs text-[#4c6c9a] dark:text-slate-400 mb-1">Field of Study</p>
<p class="text-sm font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($edu['field_of_study']); ?></p>
</div>
<?php endif; ?>
<?php if (!empty($edu['start_date'])): ?>
<div>
<p class="text-xs text-[#4c6c9a] dark:text-slate-400 mb-1">Start Date</p>
<p class="text-sm font-medium text-[#0d131b] dark:text-white"><?php echo date('M Y', strtotime($edu['start_date'])); ?></p>
</div>
<?php endif; ?>
<?php if (!empty($edu['end_date'])): ?>
<div>
<p class="text-xs text-[#4c6c9a] dark:text-slate-400 mb-1">End Date</p>
<p class="text-sm font-medium text-[#0d131b] dark:text-white"><?php echo date('M Y', strtotime($edu['end_date'])); ?></p>
</div>
<?php endif; ?>
<div class="md:col-span-2">
<p class="text-xs text-[#4c6c9a] dark:text-slate-400 mb-1">Status</p>
<p class="text-sm font-medium <?php echo $edu['is_completed'] ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400'; ?>">
<?php echo $edu['is_completed'] ? 'Completed' : 'In Progress'; ?>
</p>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<p class="text-sm text-[#4c6c9a] dark:text-slate-400 italic">No educational background recorded.</p>
<?php endif; ?>
</div>
<?php endif; ?>
<?php if ($user_type === 'staff' || $is_viewing_staff): ?>
<div class="col-span-1 sm:col-span-2 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Role</label>
<div class="text-base font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($user_info['role'] ?? 'N/A'); ?></div>
</div>
<?php endif; ?>
<div class="col-span-1 sm:col-span-2 border-t border-slate-100 pt-4 dark:border-slate-700/50">
<label class="mb-1 block text-sm font-medium text-[#4c6c9a] dark:text-slate-400">Email Address</label>
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-sm text-slate-400">mail</span>
<span class="text-base font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($user_info['email'] ?? 'N/A'); ?></span>
</div>
</div>
</div>
<?php endif; ?>
</div>
</div>
</div>
<?php if (($user_type === 'patient' || $is_viewing_patient) && !$is_viewing_staff): ?>
<div class="lg:col-span-1 flex flex-col gap-6">
<!-- Related Documents Section -->
<div class="flex h-full flex-col rounded-xl border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 shadow-sm">
<div class="flex items-center justify-between border-b border-[#e7ecf3] px-6 py-4 dark:border-slate-700">
<div class="flex items-center gap-3">
<div class="rounded-full bg-primary/10 p-2 text-primary dark:bg-primary/20 dark:text-blue-300">
<span class="material-symbols-outlined">description</span>
</div>
<h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Related Documents</h3>
</div>
</div>
<div class="p-6">
<?php
$has_documents = false;
$documents = [];

// Check for discounts from tbl_discounts
if (!empty($user_info['discounts'])) {
    foreach ($user_info['discounts'] as $discount) {
        if ($discount['is_verified'] == 1 && $discount['is_active'] == 1) {
            $has_documents = true;
            $icon = 'badge';
            $color = 'text-purple-600 dark:text-purple-400';
            $bg_color = 'bg-purple-50 dark:bg-purple-900/20';
            $border_color = 'border-purple-200 dark:border-purple-800';
            
            if ($discount['discount_type'] === 'Senior Citizen') {
                $icon = 'elderly';
                $color = 'text-blue-600 dark:text-blue-400';
                $bg_color = 'bg-blue-50 dark:bg-blue-900/20';
                $border_color = 'border-blue-200 dark:border-blue-800';
            }
            
            $documents[] = [
                'type' => $discount['discount_type'] . ' ID',
                'id_number' => $discount['id_number'],
                'verified_at' => $discount['verified_at'] ?? null,
                'icon' => $icon,
                'color' => $color,
                'bg_color' => $bg_color,
                'border_color' => $border_color
            ];
        }
    }
}

// Check for Parent Consent (only if user is a minor)
if (!empty($user_info['parent_consent'])) {
    $has_documents = true;
    $consent_date = !empty($user_info['parent_consent']['uploaded_at']) 
        ? date('F d, Y', strtotime($user_info['parent_consent']['uploaded_at']))
        : 'N/A';
    $documents[] = [
        'type' => 'Parent Consent',
        'id_number' => null,
        'date' => $consent_date,
        'notes' => $user_info['parent_consent']['notes'] ?? null,
        'icon' => 'verified_user',
        'color' => 'text-green-600 dark:text-green-400',
        'bg_color' => 'bg-green-50 dark:bg-green-900/20',
        'border_color' => 'border-green-200 dark:border-green-800'
    ];
}

if ($has_documents):
    foreach ($documents as $doc):
?>
<div class="mb-4 last:mb-0 rounded-lg border <?php echo $doc['border_color']; ?> <?php echo $doc['bg_color']; ?> p-4">
<div class="flex items-start gap-3">
<div class="rounded-full <?php echo $doc['bg_color']; ?> p-2 <?php echo $doc['color']; ?>">
<span class="material-symbols-outlined text-[20px]"><?php echo htmlspecialchars($doc['icon']); ?></span>
</div>
<div class="flex-1">
<h4 class="text-sm font-bold text-[#0d131b] dark:text-white mb-1"><?php echo htmlspecialchars($doc['type']); ?></h4>
<?php if (!empty($doc['id_number'])): ?>
<p class="text-xs text-[#4c6c9a] dark:text-slate-400 mb-1">ID Number:</p>
<p class="text-sm font-medium <?php echo $doc['color']; ?>"><?php echo htmlspecialchars($doc['id_number']); ?></p>
<?php endif; ?>
<?php if (!empty($doc['date'])): ?>
<p class="text-xs text-[#4c6c9a] dark:text-slate-400 mb-1">Date:</p>
<p class="text-sm font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($doc['date']); ?></p>
<?php endif; ?>
<?php if (!empty($doc['notes'])): ?>
<div class="mt-2 pt-2 border-t <?php echo $doc['border_color']; ?>">
<p class="text-xs text-[#4c6c9a] dark:text-slate-400 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($doc['notes'])); ?></p>
</div>
<?php endif; ?>
<?php if ($doc['type'] === 'Parent Consent'): ?>
<div class="mt-3 pt-3 border-t <?php echo $doc['border_color']; ?>">
<?php
// Determine patient_id for the download link
$download_patient_id = $user_info['patient_id'] ?? $_SESSION['user_id'];
$download_url = $user_type === 'patient' 
    ? 'download_parent_consent_pdf.php' 
    : 'download_parent_consent_pdf.php?patient_id=' . intval($download_patient_id);
?>
<a href="<?php echo htmlspecialchars($download_url); ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary hover:bg-primary-dark text-white text-sm font-semibold transition-colors">
<span class="material-symbols-outlined text-base">download</span>
Download Parent Consent PDF
</a>
</div>
<?php endif; ?>
</div>
</div>
</div>
<?php
    endforeach;
else:
?>
<div class="flex flex-col items-center justify-center py-8 text-center">
<div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-50 dark:bg-slate-800">
<span class="material-symbols-outlined text-[32px] text-slate-300 dark:text-slate-600">description</span>
</div>
<p class="text-sm text-[#4c6c9a] dark:text-slate-400">No related documents on file</p>
</div>
<?php endif; ?>
</div>
</div>

<!-- Medical History Section -->
<div class="flex h-full flex-col rounded-xl border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 shadow-sm">
<div class="flex items-center justify-between border-b border-[#e7ecf3] px-6 py-4 dark:border-slate-700">
<div class="flex items-center gap-3">
<div class="rounded-full bg-ivy-green/10 p-2 text-ivy-green dark:bg-ivy-green/20 dark:text-ivy-green">
<span class="material-symbols-outlined">history</span>
</div>
<h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Medical History</h3>
</div>
</div>
<div class="flex-1 p-6 overflow-y-auto">
<?php 
$patient_documents = isset($user_info['patient_documents']) ? $user_info['patient_documents'] : [];
// Filter out Parent Consent documents from Medical History section
$medical_documents = array_filter($patient_documents, function($doc) {
    return $doc['document_type'] !== 'Parent Consent';
});
if (!empty($medical_documents)): 
?>
<div class="space-y-3">
<?php foreach ($medical_documents as $doc): ?>
<div class="rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 p-4 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
<div class="flex items-start justify-between gap-4">
<div class="flex-1">
<div class="flex items-center gap-2 mb-2">
<span class="material-symbols-outlined text-primary text-lg">description</span>
<h4 class="text-sm font-bold text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($doc['document_type']); ?></h4>
</div>
<p class="text-sm text-[#4c6c9a] dark:text-slate-400 mb-1"><?php echo htmlspecialchars($doc['file_name']); ?></p>
<?php if ($doc['notes']): ?>
<p class="text-xs text-[#4c6c9a] dark:text-slate-400 mb-2"><?php echo htmlspecialchars($doc['notes']); ?></p>
<?php endif; ?>
<div class="flex items-center gap-4 text-xs text-[#4c6c9a] dark:text-slate-400">
<?php if ($doc['uploader_first'] && $doc['uploader_last']): ?>
<span>Uploaded by: <?php echo htmlspecialchars($doc['uploader_first'] . ' ' . $doc['uploader_last']); ?></span>
<?php endif; ?>
<?php 
$upload_date = new DateTime($doc['uploaded_at']);
echo '<span>Date: ' . $upload_date->format('M d, Y') . '</span>';
?>
</div>
</div>
<div class="flex-shrink-0">
<a href="download_patient_file.php?id=<?php echo $doc['document_id']; ?>" class="inline-flex items-center gap-2 px-3 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors">
<span class="material-symbols-outlined text-base">download</span>
Download
</a>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div class="flex flex-col items-center justify-center py-8 text-center">
<div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-slate-50 dark:bg-slate-800">
<span class="material-symbols-outlined text-[32px] text-slate-300 dark:text-slate-600">folder</span>
</div>
<p class="text-sm text-[#4c6c9a] dark:text-slate-400">No medical files uploaded yet</p>
<p class="text-xs text-[#4c6c9a] dark:text-slate-400 mt-1">Your dentist will upload files here as needed</p>
</div>
<?php endif; ?>
</div>
</div>
</div>
<?php endif; ?>
</div>
</div>
</main>
        </main><!-- /.main -->
    </div><!-- /flex-1 -->
</div><!-- /flex min-h-screen -->
<?php ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script>
<?php if ($edit_mode): ?>
flatpickr("#dob", {
    dateFormat: "m/d/Y",
    maxDate: "today",
    defaultDate: "<?php echo htmlspecialchars($dob_input); ?>"
});
<?php endif; ?>

// Logout confirmation
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

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeConfirmModal();
    }
});

// Allergy field management
function addAllergyField() {
    const container = document.getElementById('allergies-container');
    const newField = document.createElement('div');
    newField.className = 'flex gap-2 items-center';
    newField.innerHTML = `
        <input class="flex-1 rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 transition-all" placeholder="e.g., Penicillin, Latex, Peanuts" type="text" name="allergies[]" value=""/>
        <button type="button" onclick="removeAllergy(this)" class="rounded-lg border border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/30 h-10 w-10 flex items-center justify-center transition-colors">
            <span class="material-symbols-outlined text-[18px]">close</span>
        </button>
    `;
    container.appendChild(newField);
}

function removeAllergy(button) {
    const row = button.closest('.flex.gap-2');
    if (row) {
        row.remove();
    }
}

function addEducationField() {
    const container = document.getElementById('education-container');
    if (!container) return;
    const index = container.children.length;
    const newField = document.createElement('div');
    newField.className = 'education-entry border border-slate-200 dark:border-slate-700 rounded-lg p-4 bg-slate-50 dark:bg-slate-800/50';
    newField.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-medium text-[#4c6c9a] dark:text-slate-400">Institution Name <span class="text-red-500">*</span></label>
                <input type="text" name="education[${index}][institution_name]" required class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 transition-all" placeholder="University Name"/>
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-medium text-[#4c6c9a] dark:text-slate-400">Degree <span class="text-red-500">*</span></label>
                <input type="text" name="education[${index}][degree]" required class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 transition-all" placeholder="e.g., Bachelor of Science in Dentistry"/>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[#4c6c9a] dark:text-slate-400">Field of Study</label>
                <input type="text" name="education[${index}][field_of_study]" class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 transition-all" placeholder="Optional"/>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[#4c6c9a] dark:text-slate-400">Start Date</label>
                <input type="date" name="education[${index}][start_date]" class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 transition-all"/>
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-[#4c6c9a] dark:text-slate-400">End Date</label>
                <input type="date" name="education[${index}][end_date]" class="w-full rounded-lg border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-10 px-4 transition-all"/>
            </div>
            <div class="md:col-span-2 flex items-center gap-2">
                <input type="checkbox" name="education[${index}][is_completed]" value="1" id="edu_completed_${index}" class="rounded border-slate-200 dark:border-slate-600 text-primary focus:ring-primary" checked>
                <label for="edu_completed_${index}" class="text-xs text-[#4c6c9a] dark:text-slate-400">Completed</label>
            </div>
            <div class="md:col-span-2 flex justify-end">
                <button type="button" onclick="removeEducation(this)" class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 text-sm font-medium">Remove</button>
            </div>
        </div>
    `;
    container.appendChild(newField);
}

function removeEducation(button) {
    const container = document.getElementById('education-container');
    if (!container) return;
    if (container.children.length > 1) {
        button.closest('.education-entry').remove();
    } else {
        alert('At least one educational background entry is required.');
    }
}
</script>

<!-- Flatpickr for date picker -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script>
<?php if ($edit_mode && $user_type === 'patient'): ?>
flatpickr("#dob", {
    dateFormat: "m/d/Y",
    maxDate: "today",
    defaultDate: "<?php echo htmlspecialchars($dob_input ?? ''); ?>"
});
<?php endif; ?>
</script>

<!-- Confirmation Modal for Logout -->
<div id="confirmModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" onclick="closeConfirmModal(event)">
<div class="bg-white dark:bg-[#1a2634] rounded-xl shadow-xl max-w-md w-full p-6" onclick="event.stopPropagation()">
<div id="confirmModalContent">
<!-- Content will be populated by JavaScript -->
</div>
</div>
<?php $extra_scripts = ob_get_clean(); include ROOT_PATH . '/views/layout/footer.php'; ?>
