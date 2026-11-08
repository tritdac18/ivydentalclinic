<?php
require_once 'config/db.php';
require_once 'config/security.php';

// Set security headers and check login
requireLogin();

$user_type = $_SESSION['user_type'] ?? 'patient';
$user_role = $_SESSION['user_role'] ?? '';
$is_admin = ($user_type === 'staff' && ($user_role === 'Admin' || $user_role === 'Dentist'));
$edit_mode = isset($_GET['edit']) && $_GET['edit'] === '1';
$errors = [];
$success_message = '';

// Handle form submission for profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
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
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email address is required.";
    }
    
    // Check if username/email is already taken
    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            
            if ($user_type === 'patient') {
                // Check email
                $check_stmt = $conn->prepare("SELECT patient_id FROM tbl_patients WHERE email = ? AND patient_id != ? LIMIT 1");
                $check_stmt->bind_param("si", $email, $_SESSION['user_id']);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $errors[] = "This email address is already registered by another user.";
                }
                $check_stmt->close();
                
                // Validate blood type and gender
                $valid_blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'];
                if (!in_array($blood_type, $valid_blood_types)) {
                    $blood_type = 'Unknown';
                }
                
                $valid_genders = ['Male', 'Female', 'Other', 'Prefer not to say'];
                if (!in_array($gender, $valid_genders)) {
                    $gender = 'Prefer not to say';
                }
                
                // Update patient information if no errors
                if (empty($errors)) {
                    $update_stmt = $conn->prepare("UPDATE tbl_patients SET first_name = ?, last_name = ?, dob = ?, primary_phone = ?, email = ?, blood_type = ?, gender = ? WHERE patient_id = ?");
                    $update_stmt->bind_param("sssssssi", $first_name, $last_name, $dob, $phone, $email, $blood_type, $gender, $_SESSION['user_id']);
                    
                    if ($update_stmt->execute()) {
                        // Delete existing allergies and insert new ones
                        $delete_allergies = $conn->prepare("DELETE FROM tbl_patient_allergies WHERE patient_id = ?");
                        $delete_allergies->bind_param("i", $_SESSION['user_id']);
                        $delete_allergies->execute();
                        $delete_allergies->close();
                        
                        // Insert new allergies
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
                $check_stmt = $conn->prepare("SELECT staff_id FROM tbl_staff WHERE email = ? AND staff_id != ? LIMIT 1");
                $check_stmt->bind_param("si", $email, $_SESSION['user_id']);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $errors[] = "This email address is already registered by another user.";
                }
                $check_stmt->close();
                
                // Update staff information if no errors
                if (empty($errors)) {
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
    if ($user_type === 'patient') {
        // Fetch all patient data from database
        $stmt = $conn->prepare("SELECT patient_id, username, first_name, last_name, dob, primary_phone, email, blood_type, gender FROM tbl_patients WHERE patient_id = ? LIMIT 1");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user_info = $result->fetch_assoc();
            
            // Set defaults if not set
            $user_info['blood_type'] = $user_info['blood_type'] ?? 'Unknown';
            $user_info['gender'] = $user_info['gender'] ?? 'Prefer not to say';
            
            // Fetch patient allergies
            $allergy_stmt = $conn->prepare("SELECT allergy_name FROM tbl_patient_allergies WHERE patient_id = ? ORDER BY allergy_name");
            $allergy_stmt->bind_param("i", $_SESSION['user_id']);
            $allergy_stmt->execute();
            $allergy_result = $allergy_stmt->get_result();
            $user_info['allergies'] = [];
            while ($row = $allergy_result->fetch_assoc()) {
                $user_info['allergies'][] = $row['allergy_name'];
            }
            $allergy_stmt->close();
            
            // Fetch patient documents
            $user_info['documents'] = [];
            $table_check = $conn->query("SHOW TABLES LIKE 'tbl_patient_documents'");
            if ($table_check && $table_check->num_rows > 0) {
                $doc_stmt = $conn->prepare("SELECT document_type, file_name, uploaded_at, notes FROM tbl_patient_documents WHERE patient_id = ? ORDER BY uploaded_at DESC");
                $doc_stmt->bind_param("i", $_SESSION['user_id']);
                $doc_stmt->execute();
                $doc_result = $doc_stmt->get_result();
                while ($row = $doc_result->fetch_assoc()) {
                    $user_info['documents'][] = $row;
                }
                $doc_stmt->close();
            }
        } else {
            session_destroy();
            header("Location: login.php");
            exit();
        }
        $stmt->close();
    } else {
        // Fetch staff data from database
        $stmt = $conn->prepare("SELECT staff_id, username, first_name, last_name, email, role, phone_number, license_number FROM tbl_staff WHERE staff_id = ? LIMIT 1");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user_info = $result->fetch_assoc();
            $_SESSION['user_role'] = $user_info['role'];
            $user_role = $user_info['role'];
        } else {
            session_destroy();
            header("Location: login.php");
            exit();
        }
        $stmt->close();
    }
    
    $conn->close();
} catch (Exception $e) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Set user variables
$user_name = $user_info['first_name'] . ' ' . $user_info['last_name'];
$user_first_name = $user_info['first_name'];
$user_id = $user_type === 'patient' ? $user_info['patient_id'] : $user_info['staff_id'];

// Format date of birth (patients only)
$dob_formatted = '';
if ($user_type === 'patient' && !empty($user_info['dob'])) {
    $dob_date = new DateTime($user_info['dob']);
    $dob_formatted = $dob_date->format('F d, Y');
}
?>
