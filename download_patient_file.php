<?php
require_once 'config/db.php';
require_once 'config/security.php';

// Set security headers and check login
requireLogin();

$user_type = $_SESSION['user_type'] ?? 'patient';
$user_role = $_SESSION['user_role'] ?? '';

// Only allow Dentist, Manager, Admin, or the patient themselves to download files
$allowed = false;
if ($user_type === 'staff' && in_array($user_role, ['Dentist', 'Manager', 'Admin'])) {
    $allowed = true;
} elseif ($user_type === 'patient') {
    $allowed = true; // Patients can download their own files
}

if (!$allowed) {
    header("HTTP/1.1 403 Forbidden");
    die("Access denied.");
}

$file_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($file_id <= 0) {
    header("HTTP/1.1 400 Bad Request");
    die("Invalid file ID.");
}

try {
    $conn = getDBConnection();
    
    // Fetch file information
    $stmt = $conn->prepare("
        SELECT d.file_path, d.file_name, d.patient_id, p.patient_id as patient_exists
        FROM tbl_patient_documents d
        INNER JOIN tbl_patients p ON d.patient_id = p.patient_id
        WHERE d.document_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        $conn->close();
        header("HTTP/1.1 404 Not Found");
        die("File not found.");
    }
    
    $file_data = $result->fetch_assoc();
    $stmt->close();
    
    // Verify access
    if ($user_type === 'patient') {
        // Patient can only download their own files
        if ($file_data['patient_id'] != $_SESSION['user_id']) {
            $conn->close();
            header("HTTP/1.1 403 Forbidden");
            die("Access denied.");
        }
    } elseif ($user_type === 'staff' && $user_role === 'Dentist') {
        // Dentist can only download files for their assigned patients
        $check_stmt = $conn->prepare("
            SELECT COUNT(*) as count
            FROM tbl_appointments a
            WHERE a.patient_id = ? AND a.dentist_id = ?
            LIMIT 1
        ");
        $check_stmt->bind_param("ii", $file_data['patient_id'], $_SESSION['user_id']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_data = $check_result->fetch_assoc();
        $check_stmt->close();
        
        if ($check_data['count'] == 0) {
            $conn->close();
            header("HTTP/1.1 403 Forbidden");
            die("Access denied.");
        }
    }
    
    $conn->close();
    
    // Get full file path
    $file_path = __DIR__ . '/' . $file_data['file_path'];
    $file_name = $file_data['file_name'];
    
    // Security: Check if file exists and is within allowed directory
    if (!file_exists($file_path)) {
        header("HTTP/1.1 404 Not Found");
        die("File not found on server.");
    }
    
    // Security: Prevent directory traversal
    $real_path = realpath($file_path);
    $base_path = realpath(__DIR__ . '/uploads/patient_documents');
    if (strpos($real_path, $base_path) !== 0) {
        header("HTTP/1.1 403 Forbidden");
        die("Access denied.");
    }
    
    // Get file MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_path);
    finfo_close($finfo);
    
    // Set headers for file download
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . htmlspecialchars($file_name) . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    
    // Output file
    readfile($file_path);
    exit();
    
} catch (Exception $e) {
    error_log("Error downloading file: " . $e->getMessage());
    header("HTTP/1.1 500 Internal Server Error");
    die("Error downloading file.");
}

