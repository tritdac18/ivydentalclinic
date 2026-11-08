<?php
require_once '../config/db.php';

// Handle registration
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and get form data (security: trim all inputs)
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $dob = $_POST['dob'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? '')); // Convert to lowercase for consistency
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation - Check all required fields (no empty inputs)
    $errors = [];
    
    // Validate first name (required, no empty after trim)
    if (empty($first_name) || trim($first_name) === '') {
        $errors[] = "First name is required.";
    }
    
    // Validate last name (required, no empty after trim)
    if (empty($last_name) || trim($last_name) === '') {
        $errors[] = "Last name is required.";
    }
    
    // Validate date of birth (required)
    if (empty($dob) || trim($dob) === '') {
        $errors[] = "Date of birth is required.";
    } else {
        // Validate and convert date format (MM/DD/YYYY to YYYY-MM-DD)
        $date_parts = explode('/', $dob);
        if (count($date_parts) === 3) {
            $month = str_pad($date_parts[0], 2, '0', STR_PAD_LEFT);
            $day = str_pad($date_parts[1], 2, '0', STR_PAD_LEFT);
            $year = $date_parts[2];
            $dob = $year . '-' . $month . '-' . $day;
            
            // Validate the date
            if (!checkdate((int)$month, (int)$day, (int)$year)) {
                $errors[] = "Invalid date of birth format. Please use MM/DD/YYYY.";
                $dob = ''; // Reset to prevent invalid date insertion
            }
        } else {
            // Try to parse as YYYY-MM-DD format
            if (!strtotime($dob)) {
                $errors[] = "Invalid date format. Please use MM/DD/YYYY.";
            }
        }
    }
    
    // Validate phone (required, no empty after trim)
    if (empty($phone) || trim($phone) === '') {
        $errors[] = "Phone number is required.";
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
    
    // Check if email/username already exists in BOTH patients and staff tables (prevent duplicate usernames)
    if (empty($errors)) {
        try {
            $conn = getDBConnection();
            
            // Check in patients table (security: use prepared statement)
            $stmt = $conn->prepare("SELECT patient_id FROM tbl_patients WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors[] = "This email address is already registered. Please use a different email or login.";
            }
            $stmt->close();
            
            // Check in staff table (prevent duplicate usernames across both tables)
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
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            // Hash password before storing (security: never store plain text passwords)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Use prepared statement to prevent SQL injection
            $stmt = $conn->prepare("INSERT INTO tbl_patients (first_name, last_name, dob, primary_phone, email, password) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $first_name, $last_name, $dob, $phone, $email, $hashed_password);
            
            if ($stmt->execute()) {
                // Registration successful - clear any sensitive data from memory
                unset($password);
                unset($confirm_password);
                unset($hashed_password);
                
                // Set success message in session
                $_SESSION['success_message'] = "Registration successful! Please login to continue.";
                
                // Close database resources
                $stmt->close();
                $conn->close();
                
                // Redirect to login page
                header("Location: ../stitch_dental_clinic_homepage/login_page/login.php?registered=1");
                exit();
            } else {
                throw new Exception("Database insert failed");
            }
        } catch (Exception $e) {
            // Log error (in production, log to file, not display to user)
            $errors[] = "Registration failed. Please try again.";
            
            // Close database resources on error
            if (isset($stmt)) $stmt->close();
            if (isset($conn)) $conn->close();
        }
    }
    
    // If there are errors, redirect back with error messages
    if (!empty($errors)) {
        $_SESSION['registration_errors'] = $errors;
        $_SESSION['registration_data'] = $_POST; // Preserve form data
        header("Location: ../stitch_dental_clinic_homepage/registration_page/register.php");
        exit();
    }
} else {
    // If not POST request, redirect to registration page
    header("Location: ../stitch_dental_clinic_homepage/registration_page/register.php");
    exit();
}
?>

