<?php
/**
 * Complete Registration After Email Verification
 * This creates the actual account after email is verified
 */

session_start();
require_once 'config/db.php';
require_once 'services/email_service.php';

$email = $_GET['email'] ?? $_SESSION['pending_email'] ?? '';
$errors = [];

if (empty($email)) {
    header("Location: register.php");
    exit();
}

// Check if email was verified
if (!isset($_SESSION['email_verified']) || $_SESSION['email_verified'] !== $email) {
    header("Location: verify_email.php?email=" . urlencode($email));
    exit();
}

try {
    $conn = getDBConnection();
    
    // Find patient with pending verification status
    $patient_stmt = $conn->prepare("
        SELECT p.*, pr.verification_code, pr.pending_id 
        FROM tbl_patients p
        LEFT JOIN tbl_pending_registrations pr ON p.patient_id = pr.patient_id
        WHERE p.email = ? AND p.status = 'pending_verification'
        ORDER BY p.created_at DESC 
        LIMIT 1
    ");
    $patient_stmt->bind_param("s", $email);
    $patient_stmt->execute();
    $patient_result = $patient_stmt->get_result();
    
    if ($patient_result->num_rows === 0) {
        // Check if using old structure (backward compatibility)
        $old_stmt = $conn->prepare("
            SELECT * FROM tbl_pending_registrations 
            WHERE email = ? AND verified_at IS NOT NULL AND expires_at > NOW()
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $old_stmt->bind_param("s", $email);
        $old_stmt->execute();
        $old_result = $old_stmt->get_result();
        
        if ($old_result->num_rows === 0) {
            $errors[] = "Pending registration not found or expired. Please register again.";
            $old_stmt->close();
        } else {
            // Old structure: migrate data to new structure
            $pending = $old_result->fetch_assoc();
            $old_stmt->close();
            
            // Check if account was already created
            $check_stmt = $conn->prepare("SELECT patient_id FROM tbl_patients WHERE email = ? OR username = ? LIMIT 1");
            $check_stmt->bind_param("ss", $email, $pending['username']);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                // Account already exists - delete pending registration
                $delete_stmt = $conn->prepare("DELETE FROM tbl_pending_registrations WHERE pending_id = ?");
                $delete_stmt->bind_param("i", $pending['pending_id']);
                $delete_stmt->execute();
                $delete_stmt->close();
                $check_stmt->close();
                $errors[] = "Account already exists. Please login instead.";
            } else {
                // Create account from old structure
                $insert_stmt = $conn->prepare("
                    INSERT INTO tbl_patients 
                    (username, first_name, last_name, dob, primary_phone, email, password, blood_type, gender, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                $insert_stmt->bind_param("sssssssss", 
                    $pending['username'], $pending['first_name'], $pending['last_name'], 
                    $pending['dob'], $pending['primary_phone'], $pending['email'], 
                    $pending['password_hash'], $pending['blood_type'], $pending['gender']
                );
                
                if ($insert_stmt->execute()) {
                    $patient_id = $conn->insert_id;
                    $insert_stmt->close();
                    
                    // Insert allergies
                    if (!empty($pending['allergies'])) {
                        $allergies = json_decode($pending['allergies'], true);
                        if (is_array($allergies)) {
                            $allergy_stmt = $conn->prepare("INSERT INTO tbl_patient_allergies (patient_id, allergy_name) VALUES (?, ?)");
                            foreach ($allergies as $allergy) {
                                if (!empty($allergy)) {
                                    $allergy_stmt->bind_param("is", $patient_id, $allergy);
                                    $allergy_stmt->execute();
                                }
                            }
                            $allergy_stmt->close();
                        }
                    }
                    
                    // Insert parent consent
                    if ($pending['parent_consent_agreed'] && !empty($pending['parent_name'])) {
                        $table_check = $conn->query("SHOW TABLES LIKE 'tbl_patient_documents'");
                        if ($table_check && $table_check->num_rows > 0) {
                            $consent_notes = "Parent/Guardian: {$pending['parent_name']} ({$pending['parent_relationship']})\nPhone: {$pending['parent_phone']}\nEmail: {$pending['parent_email']}\nConsent Date: " . date('Y-m-d H:i:s');
                            $doc_stmt = $conn->prepare("INSERT INTO tbl_patient_documents (patient_id, document_type, notes) VALUES (?, 'Parent Consent', ?)");
                            $doc_stmt->bind_param("is", $patient_id, $consent_notes);
                            $doc_stmt->execute();
                            $doc_stmt->close();
                        }
                    }
                    
                    // Delete old pending registration
                    $delete_stmt = $conn->prepare("DELETE FROM tbl_pending_registrations WHERE pending_id = ?");
                    $delete_stmt->bind_param("i", $pending['pending_id']);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                    $check_stmt->close();
                    
                    // Clear session and redirect
                    unset($_SESSION['pending_email']);
                    unset($_SESSION['email_verified']);
                    unset($_SESSION['pending_registration']);
                    $conn->close();
                    header("Location: login.php?registered=1&verified=1");
                    exit();
                }
            }
        }
    } else {
        // New structure: patient already exists, just update status
        $patient = $patient_result->fetch_assoc();
        $patient_id = $patient['patient_id'];
        $patient_stmt->close();
        
        // Update patient status to 'active'
        $update_stmt = $conn->prepare("UPDATE tbl_patients SET status = 'active' WHERE patient_id = ?");
        $update_stmt->bind_param("i", $patient_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Delete pending registration (foreign key will handle cascade if needed)
        $delete_stmt = $conn->prepare("DELETE FROM tbl_pending_registrations WHERE patient_id = ?");
        $delete_stmt->bind_param("i", $patient_id);
        $delete_stmt->execute();
        $delete_stmt->close();
        
        // Clear session
        unset($_SESSION['pending_email']);
        unset($_SESSION['email_verified']);
        unset($_SESSION['pending_registration']);
        
        $conn->close();
        
        // Redirect to login with success message
        header("Location: login.php?registered=1&verified=1");
        exit();
    }
    
    $conn->close();
} catch (Exception $e) {
    $errors[] = "Registration completion failed. Please try again.";
    error_log("Complete registration error: " . $e->getMessage());
    if (isset($conn)) $conn->close();
}

// If there are errors, redirect back to registration
if (!empty($errors)) {
    $_SESSION['registration_errors'] = $errors;
    header("Location: register.php");
    exit();
}

