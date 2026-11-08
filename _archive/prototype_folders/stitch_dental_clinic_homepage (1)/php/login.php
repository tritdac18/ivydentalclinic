<?php
require_once '../config/database.php';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $errors = [];
    
    // Validate login fields (no empty inputs)
    if (empty($email) || trim($email) === '') {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }
    
    if (empty($password) || trim($password) === '') {
        $errors[] = "Password is required.";
    }
    
    // Only proceed if no validation errors
    if (empty($errors)) {
        $conn = getDBConnection();
        $user = null;
        $user_type = null;
        
        // Check patients table first
        $stmt = $conn->prepare("SELECT patient_id, first_name, last_name, email, password FROM tbl_patients WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $user_type = 'patient';
            $user_id_field = 'patient_id';
        }
        $stmt->close();
        
        // If not found in patients, check staff table
        if (!$user) {
            $stmt = $conn->prepare("SELECT staff_id, first_name, last_name, email, password, role FROM tbl_staff WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $user_type = 'staff';
                $user_id_field = 'staff_id';
            }
            $stmt->close();
        }
        
        // Verify user exists and password is correct
        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID for security (prevents session fixation attacks)
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['user_id'] = $user[$user_id_field];
            $_SESSION['user_type'] = $user_type;
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['login_time'] = time(); // Track login time
            
            // Add role for staff
            if ($user_type === 'staff' && isset($user['role'])) {
                $_SESSION['user_role'] = $user['role'];
            }
            
            // Close database connection
            $conn->close();
            
                // Redirect based on user type
                if ($user_type === 'staff' && ($user['role'] === 'Admin' || $user['role'] === 'Dentist')) {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: landing.php");
                }
                exit();
        } else {
            // Invalid credentials - don't reveal which (email or password) is wrong
            $errors[] = "Invalid email or password.";
        }
        
        $conn->close();
    }
    
    // If there are errors, redirect back with error messages
    if (!empty($errors)) {
        $_SESSION['login_errors'] = $errors;
        header("Location: ../stitch_dental_clinic_homepage/login_page/login.php");
        exit();
    }
} else {
    // If not POST request, redirect to login page
    header("Location: ../stitch_dental_clinic_homepage/login_page/login.php");
    exit();
}
?>

