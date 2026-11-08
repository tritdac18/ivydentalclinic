<?php
session_start();
require_once 'config/db.php';
require_once 'services/email_service.php';

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and get form data (security: trim all inputs)
    $username = trim($_POST['username'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $dob = $_POST['dob'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? '')); // Convert to lowercase for consistency
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation - Check all required fields (no empty inputs)
    $errors = [];
    
    // Validate username (required, alphanumeric and underscore only, 3-20 characters)
    if (empty($username) || trim($username) === '') {
        $errors[] = "Username is required.";
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $errors[] = "Username must be between 3 and 20 characters.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores.";
    }
    
    // Validate first name (required, no empty after trim)
    if (empty($first_name) || trim($first_name) === '') {
        $errors[] = "First name is required.";
    }
    
    // Validate last name (required, no empty after trim)
    if (empty($last_name) || trim($last_name) === '') {
        $errors[] = "Last name is required.";
    }
    
    // Validate date of birth (required)
    $dob_formatted = '';
    $is_minor = false;
    $age_in_months = 0;
    if (empty($dob) || trim($dob) === '') {
        $errors[] = "Date of birth is required.";
    } else {
        // Handle both MM/DD/YYYY and YYYY-MM-DD formats
        $date_parts = explode('/', $dob);
        if (count($date_parts) === 3) {
            // MM/DD/YYYY format
            $month = str_pad($date_parts[0], 2, '0', STR_PAD_LEFT);
            $day = str_pad($date_parts[1], 2, '0', STR_PAD_LEFT);
            $year = $date_parts[2];
            $dob_formatted = $year . '-' . $month . '-' . $day;
            
            // Validate the date
            if (!checkdate((int)$month, (int)$day, (int)$year)) {
                $errors[] = "Invalid date of birth format.";
                $dob_formatted = ''; // Reset to prevent invalid date insertion
            }
        } else {
            // Try YYYY-MM-DD format (from date picker)
            $date_obj = DateTime::createFromFormat('Y-m-d', $dob);
            if (!$date_obj || $date_obj->format('Y-m-d') !== $dob) {
                $errors[] = "Invalid date format.";
            } else {
                $dob_formatted = $dob;
            }
        }
        
        // Calculate age and validate
        if (!empty($dob_formatted)) {
            $birth_date = new DateTime($dob_formatted);
            $today = new DateTime();
            $age_diff = $today->diff($birth_date);
            $age_in_months = ($age_diff->y * 12) + $age_diff->m;
            $age_in_years = $age_diff->y;
            
            // Reject patients below 12 months (infants)
            if ($age_in_months < 12) {
                $errors[] = "We do not accept patients below 12 months of age. Please contact the clinic directly for infant care.";
            }
            
            // Check if minor (under 18 years old)
            if ($age_in_years < 18) {
                $is_minor = true;
            }
        }
    }
    
    // Validate phone (required, no empty after trim, and proper length)
    if (empty($phone) || trim($phone) === '') {
        $errors[] = "Phone number is required.";
    } else {
        // Remove common formatting characters (spaces, dashes, parentheses, plus signs)
        $phone_clean = preg_replace('/[\s\-\(\)\+]/', '', $phone);
        $phone_length = strlen($phone_clean);
        
        // Philippines phone validation: mobile (11 digits starting with 09) or landline (7-8 digits)
        if (!preg_match('/^[0-9]+$/', $phone_clean)) {
            $errors[] = "Phone number should contain only numbers.";
        } elseif ($phone_length === 11) {
            // Mobile number: must start with 09
            if (!preg_match('/^09/', $phone_clean)) {
                $errors[] = "Mobile phone numbers must be 11 digits and start with 09 (e.g., 09171234567).";
            }
        } elseif ($phone_length >= 7 && $phone_length <= 8) {
            // Landline number: 7-8 digits
            // Valid landline format
        } else {
            $errors[] = "Phone number must be 11 digits for mobile (starting with 09) or 7-8 digits for landline.";
        }
    }
    
    // Validate email (required, format validation)
    if (empty($email) || trim($email) === '') {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    // Validate password (required, minimum length)
    if (empty($password) || trim($password) === '') {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    
    // Validate password confirmation
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    // Check if username/email already exists in BOTH patients and staff tables (prevent duplicate usernames)
    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            
            // Check username in patients table
            $stmt = $conn->prepare("SELECT patient_id FROM tbl_patients WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors[] = "This username is already taken. Please choose a different username.";
            }
            $stmt->close();
            
            // Check username in staff table
            if (empty($errors)) {
                $stmt = $conn->prepare("SELECT staff_id FROM tbl_staff WHERE username = ? LIMIT 1");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $errors[] = "This username is already taken. Please choose a different username.";
                }
                $stmt->close();
            }
            
            // Check email in patients table
            if (empty($errors)) {
                $stmt = $conn->prepare("SELECT patient_id FROM tbl_patients WHERE email = ? LIMIT 1");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $errors[] = "This email address is already registered. Please use a different email or login.";
                }
                $stmt->close();
            }
            
            // Check email in staff table
            if (empty($errors)) {
                $stmt = $conn->prepare("SELECT staff_id FROM tbl_staff WHERE email = ? LIMIT 1");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $errors[] = "This email address is already registered. Please use a different email or login.";
                }
                $stmt->close();
            }
            
            $conn->close();
        } catch (Exception $e) {
            // Database error - don't reveal details to user
            $errors[] = "An error occurred. Please try again later.";
            if (isset($conn)) $conn->close();
        }
    }
    
    // Get patient background data (optional fields)
    $blood_type = trim($_POST['blood_type'] ?? 'Unknown');
    $gender = trim($_POST['gender'] ?? 'Prefer not to say');
    $allergies = isset($_POST['allergies']) ? array_filter(array_map('trim', $_POST['allergies'])) : [];
    
    // Get parent consent data (required if minor)
    $parent_name = trim($_POST['parent_name'] ?? '');
    $parent_relationship = trim($_POST['parent_relationship'] ?? '');
    $parent_phone = trim($_POST['parent_phone'] ?? '');
    $parent_email = trim(strtolower($_POST['parent_email'] ?? ''));
    $parent_consent_agreed = isset($_POST['parent_consent_agreed']) && $_POST['parent_consent_agreed'] === '1';
    
    // Validate parent consent if minor
    if ($is_minor && empty($errors)) {
        if (empty($parent_name)) {
            $errors[] = "Parent/Guardian name is required for patients under 18 years old.";
        }
        if (empty($parent_relationship)) {
            $errors[] = "Relationship to patient is required.";
        }
        if (empty($parent_phone)) {
            $errors[] = "Parent/Guardian phone number is required.";
        } else {
            // Remove common formatting characters (spaces, dashes, parentheses, plus signs)
            $parent_phone_clean = preg_replace('/[\s\-\(\)\+]/', '', $parent_phone);
            $parent_phone_length = strlen($parent_phone_clean);
            
            // Philippines phone validation: mobile (11 digits starting with 09) or landline (7-8 digits)
            if (!preg_match('/^[0-9]+$/', $parent_phone_clean)) {
                $errors[] = "Parent/Guardian phone number should contain only numbers.";
            } elseif ($parent_phone_length === 11) {
                // Mobile number: must start with 09
                if (!preg_match('/^09/', $parent_phone_clean)) {
                    $errors[] = "Parent/Guardian mobile phone numbers must be 11 digits and start with 09 (e.g., 09171234567).";
                }
            } elseif ($parent_phone_length >= 7 && $parent_phone_length <= 8) {
                // Landline number: 7-8 digits
                // Valid landline format
            } else {
                $errors[] = "Parent/Guardian phone number must be 11 digits for mobile (starting with 09) or 7-8 digits for landline.";
            }
        }
        if (empty($parent_email) || !filter_var($parent_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid parent/guardian email address is required.";
        }
        if (!$parent_consent_agreed) {
            $errors[] = "Parent/Guardian consent is required for patients under 18 years old.";
        }
    }
    
    // Validate blood type
    $valid_blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'];
    if (!in_array($blood_type, $valid_blood_types)) {
        $blood_type = 'Unknown';
    }
    
    // Validate gender
    $valid_genders = ['Male', 'Female', 'Other', 'Prefer not to say'];
    if (!in_array($gender, $valid_genders)) {
        $gender = 'Prefer not to say';
    }
    
    // If no errors, create patient record with 'pending_verification' status and send verification email
    if (empty($errors)) {
        try {
            // Get a new database connection
            $conn = getDBConnection();
            
            // Hash password before storing (security: never store plain text passwords)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Check if status column exists
            $status_col_check = $conn->query("SHOW COLUMNS FROM tbl_patients LIKE 'status'");
            if (!$status_col_check || $status_col_check->num_rows === 0) {
                $errors[] = "Database is not properly configured for email verification. Please contact administrator.";
                error_log("CRITICAL: status column does not exist in tbl_patients - registration blocked");
                $conn->close();
            } else {
                // Track if we need to clean up on failure
                $patient_created = false;
                $pending_created = false;
                // Create patient record immediately with status='pending_verification'
                // This way we don't duplicate data - patient data lives in tbl_patients
                $patient_stmt = $conn->prepare("
                    INSERT INTO tbl_patients 
                    (username, first_name, last_name, dob, primary_phone, email, password, blood_type, gender, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_verification')
                ");
                $patient_stmt->bind_param("sssssssss", 
                    $username, $first_name, $last_name, $dob_formatted, $phone, $email, $hashed_password, 
                    $blood_type, $gender
                );
                
                if ($patient_stmt->execute()) {
                    $patient_id = $conn->insert_id;
                    $patient_stmt->close();
                    $patient_created = true;
                    
                    // Verify patient_id was retrieved
                    if (!$patient_id) {
                        throw new Exception("Failed to get patient ID after insertion");
                    }
                
                // Insert allergies if any (even for pending patients)
                if (!empty($allergies)) {
                    $allergy_stmt = $conn->prepare("INSERT INTO tbl_patient_allergies (patient_id, allergy_name) VALUES (?, ?)");
                    foreach ($allergies as $allergy) {
                        if (!empty($allergy)) {
                            $allergy_stmt->bind_param("is", $patient_id, $allergy);
                            $allergy_stmt->execute();
                        }
                    }
                    $allergy_stmt->close();
                }
                
                // Insert parent consent document if minor (even for pending patients)
                if ($is_minor && $parent_consent_agreed) {
                    $table_check = $conn->query("SHOW TABLES LIKE 'tbl_patient_documents'");
                    if ($table_check && $table_check->num_rows > 0) {
                        $consent_notes = "Parent/Guardian: {$parent_name} ({$parent_relationship})\nPhone: {$parent_phone}\nEmail: {$parent_email}\nConsent Date: " . date('Y-m-d H:i:s');
                        $doc_stmt = $conn->prepare("INSERT INTO tbl_patient_documents (patient_id, document_type, notes) VALUES (?, 'Parent Consent', ?)");
                        $doc_stmt->bind_param("is", $patient_id, $consent_notes);
                        $doc_stmt->execute();
                        $doc_stmt->close();
                    }
                }
                
                // Generate verification code
                $emailService = new EmailService();
                $verification_code = $emailService->generateVerificationCode();
                
                // Save verification code to database (for email verification table)
                $emailService->saveVerificationCode($email, $verification_code);
                
                // Store only verification metadata in tbl_pending_registrations (with foreign key to patient)
                $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                // Check if pending registrations table exists and has the new structure
                $table_check = $conn->query("SHOW TABLES LIKE 'tbl_pending_registrations'");
                if ($table_check && $table_check->num_rows > 0) {
                    // Check if table has patient_id column (new structure)
                    $column_check = $conn->query("SHOW COLUMNS FROM tbl_pending_registrations LIKE 'patient_id'");
                    if ($column_check && $column_check->num_rows > 0) {
                        // New structure confirmed - log for debugging
                        error_log("Using new structure for pending_registrations (patient_id exists)");
                        // New structure: only verification metadata
                        $pending_stmt = $conn->prepare("
                            INSERT INTO tbl_pending_registrations 
                            (patient_id, verification_code, expires_at) 
                            VALUES (?, ?, ?)
                        ");
                        $pending_stmt->bind_param("iss", $patient_id, $verification_code, $expires_at);
                    } else {
                        // Old structure: still has all patient data (backward compatibility)
                        error_log("Using old structure for pending_registrations (patient_id does not exist)");
                        $allergies_json = !empty($allergies) ? json_encode($allergies) : null;
                        $pending_stmt = $conn->prepare("
                            INSERT INTO tbl_pending_registrations 
                            (username, first_name, last_name, dob, primary_phone, email, password_hash, blood_type, gender, allergies, 
                             parent_name, parent_relationship, parent_phone, parent_email, parent_consent_agreed, verification_code, expires_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $pending_stmt->bind_param("ssssssssssssssiss", 
                            $username, $first_name, $last_name, $dob_formatted, $phone, $email, $hashed_password, 
                            $blood_type, $gender, $allergies_json,
                            $parent_name, $parent_relationship, $parent_phone, $parent_email, $parent_consent_agreed, 
                            $verification_code, $expires_at
                        );
                    }
                    
                    if ($pending_stmt->execute()) {
                        $pending_id = $conn->insert_id;
                        $pending_stmt->close();
                        $pending_created = true;
                        error_log("Pending registration created successfully: pending_id=$pending_id, patient_id=$patient_id, email=$email");
                        
                        // Send verification email
                        if ($emailService->sendVerificationEmail($email, $verification_code)) {
                            error_log("Verification email sent successfully to: $email");
                            // Clear sensitive data from memory
                            unset($password);
                            unset($confirm_password);
                            unset($hashed_password);
                            
                            // Close database resources
                            $conn->close();
                            
                            // Store email in session for verification page
                            $_SESSION['pending_email'] = $email;
                            $_SESSION['pending_registration'] = true;
                            
                            // Everything succeeded - redirect to verification page
                            header("Location: verify_email.php?email=" . urlencode($email) . "&registration=1");
                            exit();
                        } else {
                            // Email sending failed - clean up everything
                            $errors[] = "Failed to send verification email. Please try again or contact support.";
                            error_log("Email sending failed for patient_id $patient_id, email $email - cleaning up");
                            
                            // Delete pending registration first
                            if ($pending_created) {
                                $delete_pending = $conn->prepare("DELETE FROM tbl_pending_registrations WHERE patient_id = ?");
                                if ($delete_pending) {
                                    $delete_pending->bind_param("i", $patient_id);
                                    $delete_pending->execute();
                                    $delete_pending->close();
                                }
                            }
                            
                            // Delete patient record
                            if ($patient_created) {
                                $delete_patient = $conn->prepare("DELETE FROM tbl_patients WHERE patient_id = ?");
                                $delete_patient->bind_param("i", $patient_id);
                                $delete_patient->execute();
                                $delete_patient->close();
                            }
                            
                            $conn->close();
                        }
                    } else {
                        // Failed to create pending registration - clean up
                        $error_msg = $conn->error ?? "Unknown error";
                        error_log("Pending registration insert failed for patient_id $patient_id: " . $error_msg);
                        
                        // Delete patient record since pending registration failed
                        if ($patient_created) {
                            $delete_patient = $conn->prepare("DELETE FROM tbl_patients WHERE patient_id = ?");
                            $delete_patient->bind_param("i", $patient_id);
                            $delete_patient->execute();
                            $delete_patient->close();
                        }
                        $conn->close();
                        
                        $errors[] = "Registration failed. Please try again.";
                        throw new Exception("Failed to save pending registration: " . $error_msg);
                    }
                } else {
                    // Table doesn't exist - clean up
                    // Delete the patient record we just created
                    if ($patient_created) {
                        $delete_patient = $conn->prepare("DELETE FROM tbl_patients WHERE patient_id = ?");
                        $delete_patient->bind_param("i", $patient_id);
                        $delete_patient->execute();
                        $delete_patient->close();
                    }
                    $conn->close();
                    
                    $errors[] = "Email verification system is not properly configured. Please contact administrator.";
                    error_log("CRITICAL: tbl_pending_registrations table does not exist - registration blocked for email: $email");
                }
                } else {
                    throw new Exception("Failed to create patient record");
                }
            } // End of status column check else block
        } catch (Exception $e) {
            // Log error (in production, log to file, not display to user)
            $errors[] = "Registration failed. Please try again.";
            error_log("Registration error: " . $e->getMessage());
            
            // Close database resources on error
            if (isset($conn)) $conn->close();
        }
    }
    
    // If there are errors, store them and show form again
    if (!empty($errors)) {
        $_SESSION['registration_errors'] = $errors;
        $_SESSION['registration_data'] = $_POST; // Preserve form data
    }
}

// Get form data from session if available (for error display)
$form_data = $_SESSION['registration_data'] ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_SESSION['registration_data'])) {
    unset($_SESSION['registration_data']);
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Patient Registration - Ivy Dental Clinic</title>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#136dec",
                        "background-light": "#f6f7f8",
                        "background-dark": "#101822",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
<!-- Flatpickr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
        body { font-family: 'Inter', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: #e2e8f0;
            border-radius: 20px;
        }
        .dark .custom-scrollbar::-webkit-scrollbar-thumb {
            background-color: #334155;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-[#0d131b] dark:text-white h-screen overflow-hidden flex flex-col font-display">
<div class="flex flex-1 h-full w-full">
<!-- Left Section: Hero/Branding (Hidden on Mobile) -->
<div class="hidden lg:flex lg:w-[45%] xl:w-[50%] relative bg-blue-600 overflow-hidden">
<img alt="Dental Clinic Interior" class="absolute inset-0 h-full w-full object-cover mix-blend-overlay opacity-50" data-alt="Clean and modern dental clinic reception area with soft lighting" src="https://lh3.googleusercontent.com/aida-public/AB6AXuAFu41GrkM5xj6g7_fcvaurtCUCojdaRP9JiiZIX1nYUI9yfI3xxMDwqJfdkbBpFdO1UoaM-yMQZp1mrm2HrjpQOylBSw51owerIVXdMPWjg8_lNn4s7XAkRyqQR-y4HKJsbn3g9ACanCD2qGQltxY91cA8riad5wkuWU3YD-PMkAZThiIkIjNMWboWplWMdD32YNMFBKJBR4dIRlyPG2ure1r5PDvw9dsehjLtphsRYpL6AfbB6ZQiJMQp92tosevwiZJtmnbkz1qD"/>
<div class="absolute inset-0 bg-gradient-to-br from-primary/90 to-blue-900/90 mix-blend-multiply"></div>
<div class="relative z-10 flex flex-col justify-between p-16 h-full text-white">
<div>
<div class="flex items-center gap-3 mb-12">
<div class="size-10 bg-white rounded-lg flex items-center justify-center text-primary shadow-lg">
<span class="material-symbols-outlined text-2xl">dentistry</span>
</div>
<span class="text-2xl font-bold tracking-tight">Ivy Dental Clinic</span>
</div>
<h2 class="text-4xl md:text-5xl font-bold leading-tight mb-6">Start your journey to a perfect smile.</h2>
<p class="text-blue-100 text-lg max-w-md leading-relaxed">Join over 15,000 patients who trust us with their dental health. Comprehensive care for the whole family.</p>
</div>
<div class="bg-white/10 backdrop-blur-md rounded-2xl p-6 border border-white/10">
<div class="flex items-center gap-1 text-yellow-400 mb-3">
<span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1">star</span>
<span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1">star</span>
<span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1">star</span>
<span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1">star</span>
<span class="material-symbols-outlined text-sm" style="font-variation-settings: 'FILL' 1">star</span>
<span class="text-white text-sm font-medium ml-2">5.0 from 200+ reviews</span>
</div>
<p class="text-white/90 italic">"The registration process was seamless, and the staff made me feel comfortable immediately. Highly recommended!"</p>
<div class="flex items-center gap-3 mt-4">
<img alt="User" class="w-8 h-8 rounded-full object-cover border-2 border-white/50" data-alt="Portrait of Sarah Jenkins" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBWAcCQP0ePoWdQMvHC-tbgjc7QyXJ5f_TveAPbXb2Ncjv9XjPskeeB_uFJqdG9CUPS31feoMGN5Z3Up8v93w8UKsfgcVpWlyXCOOWBmn4QW6i1z6Xe6ncFwdcrVNKPj5W4KN6BYnRHRC22TXd9DQHewlu-oZaVVjF5o62luXRoC9ry5AShMC_LMlyBP_rXNTYOMOexaN2hfFcjPChTwLZw0GEOL0YLj4e7DlO44oLsVtVDkgM27z5P_RSUxHhEL73BuSX9lpjV1GK6"/>
<span class="text-sm font-medium">Sarah Jenkins, Patient</span>
</div>
</div>
</div>
</div>
<!-- Right Section: Form -->
<div class="flex-1 flex flex-col h-full bg-background-light dark:bg-background-dark relative">
<header class="lg:hidden flex items-center justify-between px-6 py-4 bg-white dark:bg-[#1a2634] border-b border-gray-200 dark:border-gray-800 shrink-0">
<div class="flex items-center gap-2 text-primary">
<div class="size-6">
<span class="material-symbols-outlined text-2xl">dentistry</span>
</div>
<span class="text-lg font-bold text-gray-900 dark:text-white">Ivy Dental</span>
</div>
</header>
<div class="flex-1 overflow-y-auto custom-scrollbar p-6 md:p-12 lg:p-16 flex flex-col items-center justify-center">
<div class="w-full max-w-[520px] mx-auto space-y-8">
<div class="text-center lg:text-left">
<h1 class="text-3xl font-black tracking-tight text-[#0d131b] dark:text-white mb-3">Patient Registration</h1>
<p class="text-[#4c6c9a] dark:text-gray-400 text-base mb-[20px]">Please enter your details below to create a new patient account.</p>
</div>

<?php
if (isset($_SESSION['registration_errors'])) {
    echo '<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-400">';
    foreach ($_SESSION['registration_errors'] as $error) {
        echo '<p class="text-sm">' . htmlspecialchars($error) . '</p>';
    }
    unset($_SESSION['registration_errors']);
    echo '</div>';
}
?>

<form class="space-y-6" method="POST" action="register.php">
<label class="block">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Username</span>
<div class="relative">
<input class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 transition-all" placeholder="johndoe123" type="text" name="username" value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>" required pattern="[a-zA-Z0-9_]{3,20}" title="Username must be 3-20 characters and contain only letters, numbers, and underscores"/>
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-[#4c6c9a] text-[20px] pointer-events-none">person</span>
</div>
<p class="mt-1 text-xs text-[#4c6c9a] dark:text-gray-400">3-20 characters, letters, numbers, and underscores only</p>
</label>
<div class="flex flex-col md:flex-row gap-5">
<label class="flex-1">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">First Name</span>
<input class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 transition-all" placeholder="Jane" type="text" name="first_name" value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>" required/>
</label>
<label class="flex-1">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Last Name</span>
<input class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 transition-all" placeholder="Doe" type="text" name="last_name" value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>" required/>
</label>
</div>
<div class="flex flex-col md:flex-row gap-5">
<label class="flex-1 relative">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Date of Birth</span>
<div class="relative">
<input class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 pr-10 transition-all" placeholder="MM/DD/YYYY" type="text" name="dob" id="dob" value="<?php echo htmlspecialchars($form_data['dob'] ?? ''); ?>" required/>
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-[#4c6c9a] text-[20px] pointer-events-none">calendar_today</span>
</div>
</label>
<label class="flex-1">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Phone Number</span>
<input class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 transition-all" placeholder="(555) 123-4567" type="tel" name="phone" value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>" required/>
</label>
</div>
<label class="block">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Email Address</span>
<div class="relative">
<input class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 pr-10 transition-all" placeholder="jane@example.com" type="email" name="email" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required/>
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-[#4c6c9a] text-[20px] pointer-events-none">mail</span>
</div>
</label>
<div class="flex flex-col md:flex-row gap-5">
<label class="flex-1">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Blood Type</span>
<select class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 transition-all" name="blood_type">
<option value="Unknown" <?php echo (($form_data['blood_type'] ?? 'Unknown') === 'Unknown') ? 'selected' : ''; ?>>Unknown</option>
<option value="A+" <?php echo (($form_data['blood_type'] ?? '') === 'A+') ? 'selected' : ''; ?>>A+</option>
<option value="A-" <?php echo (($form_data['blood_type'] ?? '') === 'A-') ? 'selected' : ''; ?>>A-</option>
<option value="B+" <?php echo (($form_data['blood_type'] ?? '') === 'B+') ? 'selected' : ''; ?>>B+</option>
<option value="B-" <?php echo (($form_data['blood_type'] ?? '') === 'B-') ? 'selected' : ''; ?>>B-</option>
<option value="AB+" <?php echo (($form_data['blood_type'] ?? '') === 'AB+') ? 'selected' : ''; ?>>AB+</option>
<option value="AB-" <?php echo (($form_data['blood_type'] ?? '') === 'AB-') ? 'selected' : ''; ?>>AB-</option>
<option value="O+" <?php echo (($form_data['blood_type'] ?? '') === 'O+') ? 'selected' : ''; ?>>O+</option>
<option value="O-" <?php echo (($form_data['blood_type'] ?? '') === 'O-') ? 'selected' : ''; ?>>O-</option>
</select>
</label>
<label class="flex-1">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Gender</span>
<select class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 transition-all" name="gender">
<option value="Prefer not to say" <?php echo (($form_data['gender'] ?? 'Prefer not to say') === 'Prefer not to say') ? 'selected' : ''; ?>>Prefer not to say</option>
<option value="Male" <?php echo (($form_data['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
<option value="Female" <?php echo (($form_data['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
<option value="Other" <?php echo (($form_data['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
</select>
</label>
</div>
<label class="block">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Allergies (Optional)</span>
<div id="allergies-container" class="space-y-2">
<?php
$existing_allergies = $form_data['allergies'] ?? [];
if (empty($existing_allergies)) {
    $existing_allergies = [''];
}
foreach ($existing_allergies as $index => $allergy): ?>
<div class="flex gap-2 items-center">
<input class="flex-1 rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 transition-all" placeholder="e.g., Penicillin, Latex, Peanuts" type="text" name="allergies[]" value="<?php echo htmlspecialchars($allergy); ?>"/>
<?php if ($index > 0): ?>
<button type="button" onclick="removeAllergy(this)" class="rounded-lg border border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/30 h-12 w-12 flex items-center justify-center transition-colors">
<span class="material-symbols-outlined text-[20px]">close</span>
</button>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<button type="button" onclick="addAllergyField()" class="mt-2 text-sm text-primary hover:underline flex items-center gap-1">
<span class="material-symbols-outlined text-[18px]">add</span>
<span>Add another allergy</span>
</button>
</label>

<!-- Parent Consent Form (shown for minors) -->
<div id="parent-consent-section" class="hidden space-y-4 p-4 bg-amber-50 dark:bg-amber-900/20 border-2 border-amber-200 dark:border-amber-800 rounded-lg">
<div class="flex items-start gap-2">
<span class="material-symbols-outlined text-amber-600 dark:text-amber-400 text-xl">family_restroom</span>
<div class="flex-1">
<h3 class="text-lg font-bold text-amber-900 dark:text-amber-200 mb-1">Parent/Guardian Consent Required</h3>
<p class="text-sm text-amber-800 dark:text-amber-300 mb-4">Since you are under 18 years old, we require parent/guardian consent to proceed with registration.</p>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<label class="md:col-span-2">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Parent/Guardian Full Name <span class="text-red-500">*</span></span>
<input class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 transition-all" placeholder="John Doe" type="text" name="parent_name" value="<?php echo htmlspecialchars($form_data['parent_name'] ?? ''); ?>" id="parent_name"/>
</label>
<label>
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Relationship to Patient <span class="text-red-500">*</span></span>
<select class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 transition-all" name="parent_relationship" id="parent_relationship">
<option value="">Select relationship</option>
<option value="Parent" <?php echo (($form_data['parent_relationship'] ?? '') === 'Parent') ? 'selected' : ''; ?>>Parent</option>
<option value="Guardian" <?php echo (($form_data['parent_relationship'] ?? '') === 'Guardian') ? 'selected' : ''; ?>>Guardian</option>
<option value="Legal Guardian" <?php echo (($form_data['parent_relationship'] ?? '') === 'Legal Guardian') ? 'selected' : ''; ?>>Legal Guardian</option>
<option value="Other" <?php echo (($form_data['parent_relationship'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
</select>
</label>
<label>
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Parent/Guardian Phone <span class="text-red-500">*</span></span>
<input class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 transition-all" placeholder="(555) 123-4567" type="tel" name="parent_phone" value="<?php echo htmlspecialchars($form_data['parent_phone'] ?? ''); ?>" id="parent_phone"/>
</label>
<label class="md:col-span-2">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Parent/Guardian Email <span class="text-red-500">*</span></span>
<input class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 transition-all" placeholder="parent@example.com" type="email" name="parent_email" value="<?php echo htmlspecialchars($form_data['parent_email'] ?? ''); ?>" id="parent_email"/>
</label>
<label class="md:col-span-2 flex items-start gap-2">
<input type="checkbox" name="parent_consent_agreed" value="1" id="parent_consent_agreed" class="mt-1 rounded border-[#cfd9e7] text-primary focus:ring-primary" <?php echo (isset($form_data['parent_consent_agreed']) && $form_data['parent_consent_agreed'] === '1') ? 'checked' : ''; ?> required/>
<div class="flex-1">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-1">I consent to the registration and treatment of this minor patient <span class="text-red-500">*</span></span>
<p class="text-xs text-[#4c6c9a] dark:text-gray-400">By checking this box, I confirm that I am the parent/guardian and authorize this registration.</p>
</div>
</label>
</div>
</div>

<div class="flex flex-col md:flex-row gap-5">
<label class="flex-1 relative">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Password</span>
<div class="relative">
<input class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 pr-10 transition-all" placeholder="Create password" type="password" name="password" id="password" required/>
<button class="absolute right-3 top-1/2 -translate-y-1/2 text-[#4c6c9a] hover:text-primary transition-colors" type="button" onclick="togglePassword('password', 'password-toggle')">
<span class="material-symbols-outlined text-[20px]" id="password-toggle">visibility_off</span>
</button>
</div>
</label>
<label class="flex-1 relative">
<span class="block text-sm font-semibold text-[#0d131b] dark:text-gray-200 mb-2">Confirm Password</span>
<div class="relative">
<input class="w-full rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 pr-10 transition-all" placeholder="Confirm password" type="password" name="confirm_password" id="confirm_password" required/>
<button class="absolute right-3 top-1/2 -translate-y-1/2 text-[#4c6c9a] hover:text-primary transition-colors" type="button" onclick="togglePassword('confirm_password', 'confirm-password-toggle')">
<span class="material-symbols-outlined text-[20px]" id="confirm-password-toggle">visibility_off</span>
</button>
</div>
</label>
</div>
<div class="pt-4">
<button class="group relative w-full flex justify-center py-3.5 px-4 border border-transparent text-sm font-bold rounded-lg text-white bg-primary hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors shadow-lg shadow-blue-500/30" type="submit">
                                Create Account
                                <span class="material-symbols-outlined absolute right-4 group-hover:translate-x-1 transition-transform">arrow_forward</span>
                            </button>
<div class="mt-6 text-center">
<p class="text-sm text-gray-600 dark:text-gray-400">
                                Already a patient? 
                                <a class="font-bold text-primary hover:underline" href="login.php">Log In</a>
                            </p>
</div>
</div>
</form>
<div class="pt-8 text-center border-t border-gray-100 dark:border-gray-800">
<p class="text-xs text-gray-400">
                            © 2024 Ivy Dental Clinic. All rights reserved. <br/>
<a class="hover:text-primary" href="#">HIPAA Compliant</a> • <a class="hover:text-primary" href="#">Secure Connection</a>
</p>
</div>
</div>
</div>
</div>
</div>
<!-- Flatpickr JS -->
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
// Initialize date picker
flatpickr("#dob", {
    dateFormat: "m/d/Y",
    maxDate: "today",
    defaultDate: null,
    allowInput: true,
    clickOpens: true,
    onChange: function(selectedDates, dateStr, instance) {
        checkIfMinor(dateStr);
    }
});

// Function to check if patient is a minor and show/hide parent consent form
function checkIfMinor(dobString) {
    const parentSection = document.getElementById('parent-consent-section');
    const parentName = document.getElementById('parent_name');
    const parentRelationship = document.getElementById('parent_relationship');
    const parentPhone = document.getElementById('parent_phone');
    const parentEmail = document.getElementById('parent_email');
    const parentConsent = document.getElementById('parent_consent_agreed');
    
    if (!dobString || !parentSection) return;
    
    // Parse date (MM/DD/YYYY format)
    const dateParts = dobString.split('/');
    if (dateParts.length !== 3) return;
    
    const month = parseInt(dateParts[0]);
    const day = parseInt(dateParts[1]);
    const year = parseInt(dateParts[2]);
    
    // Calculate age
    const birthDate = new Date(year, month - 1, day);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    
    // Calculate age in months for infant check
    const ageInMonths = (today.getFullYear() - birthDate.getFullYear()) * 12 + (today.getMonth() - birthDate.getMonth());
    if (today.getDate() < birthDate.getDate()) {
        ageInMonths--;
    }
    
    // Show error if infant (below 12 months)
    if (ageInMonths < 12) {
        const errorMsg = document.getElementById('infant-error');
        if (!errorMsg) {
            const dobInput = document.getElementById('dob');
            const errorDiv = document.createElement('div');
            errorDiv.id = 'infant-error';
            errorDiv.className = 'mt-2 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-400';
            errorDiv.innerHTML = '<p class="text-sm">We do not accept patients below 12 months of age. Please contact the clinic directly for infant care.</p>';
            dobInput.parentElement.appendChild(errorDiv);
        }
        parentSection.classList.add('hidden');
        // Make parent fields not required
        if (parentName) parentName.removeAttribute('required');
        if (parentRelationship) parentRelationship.removeAttribute('required');
        if (parentPhone) parentPhone.removeAttribute('required');
        if (parentEmail) parentEmail.removeAttribute('required');
        if (parentConsent) parentConsent.removeAttribute('required');
    } else {
        // Remove infant error if exists
        const errorMsg = document.getElementById('infant-error');
        if (errorMsg) errorMsg.remove();
        
        // Show parent consent if minor (under 18)
        if (age < 18) {
            parentSection.classList.remove('hidden');
            // Make parent fields required
            if (parentName) parentName.setAttribute('required', 'required');
            if (parentRelationship) parentRelationship.setAttribute('required', 'required');
            if (parentPhone) parentPhone.setAttribute('required', 'required');
            if (parentEmail) parentEmail.setAttribute('required', 'required');
            if (parentConsent) parentConsent.setAttribute('required', 'required');
        } else {
            parentSection.classList.add('hidden');
            // Make parent fields not required
            if (parentName) parentName.removeAttribute('required');
            if (parentRelationship) parentRelationship.removeAttribute('required');
            if (parentPhone) parentPhone.removeAttribute('required');
            if (parentEmail) parentEmail.removeAttribute('required');
            if (parentConsent) parentConsent.removeAttribute('required');
        }
    }
}

// Check on page load if DOB is already filled
document.addEventListener('DOMContentLoaded', function() {
    const dobInput = document.getElementById('dob');
    if (dobInput && dobInput.value) {
        checkIfMinor(dobInput.value);
    }
});

function togglePassword(inputId, toggleId) {
    const passwordInput = document.getElementById(inputId);
    const toggleIcon = document.getElementById(toggleId);
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.textContent = 'visibility';
    } else {
        passwordInput.type = 'password';
        toggleIcon.textContent = 'visibility_off';
    }
}

function addAllergyField() {
    const container = document.getElementById('allergies-container');
    const newField = document.createElement('div');
    newField.className = 'flex gap-2 items-center';
    newField.innerHTML = `
        <input class="flex-1 rounded-lg border border-[#cfd9e7] bg-white dark:bg-[#1a2634] dark:border-gray-700 text-[#0d131b] dark:text-white placeholder:text-[#4c6c9a] focus:ring-2 focus:ring-primary/20 focus:border-primary h-12 px-4 transition-all" placeholder="e.g., Penicillin, Latex, Peanuts" type="text" name="allergies[]" value=""/>
        <button type="button" onclick="removeAllergy(this)" class="rounded-lg border border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-900/30 h-12 w-12 flex items-center justify-center transition-colors">
            <span class="material-symbols-outlined text-[20px]">close</span>
        </button>
    `;
    container.appendChild(newField);
}

function removeAllergy(button) {
    button.parentElement.remove();
}
</script>
</body>
</html>
