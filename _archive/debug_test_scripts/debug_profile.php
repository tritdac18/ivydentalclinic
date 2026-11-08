<?php
// debug_profile.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Profile Debugging Script</h1>";

try {
    echo "<h3>1. Checking Prerequisites...</h3>";
    if (!file_exists('config/db.php')) {
        die("❌ config/db.php not found.");
    }
    if (!file_exists('config/security.php')) {
        die("❌ config/security.php not found.");
    }
    
    require_once 'config/db.php';
    require_once 'config/security.php';
    echo "✅ Config files loaded.<br>";

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    echo "<h3>2. Session State:</h3>";
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";

    if (!isset($_SESSION['user_id'])) {
        echo "⚠️ No user_id in session. Testing with a default ID if possible, or please log in.<br>";
        // For debugging only, you might want to uncomment this to force a test user
        // $_SESSION['user_id'] = 1; 
        // $_SESSION['user_type'] = 'patient';
    }

    $user_type = $_SESSION['user_type'] ?? 'patient';
    $user_id = $_SESSION['user_id'] ?? 0;

    echo "<h3>3. Testing Database Connection...</h3>";
    $conn = getDBConnection();
    if ($conn->connect_error) {
        die("❌ Connection failed: " . $conn->connect_error);
    }
    echo "✅ Database connected.<br>";

    echo "<h3>4. Testing User Info Query...</h3>";
    if ($user_type === 'patient') {
        $query = "SELECT patient_id, username, first_name, last_name, dob, primary_phone, email, blood_type, gender, pwd_id_number, pwd_verified_by_staff_id, pwd_verified_at, senior_citizen_id_number, senior_citizen_verified_by_staff_id, senior_citizen_verified_at FROM tbl_patients WHERE patient_id = ?";
    } else {
        $query = "SELECT staff_id, username, first_name, last_name, email, role, phone_number, license_number FROM tbl_staff WHERE staff_id = ?";
    }
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("❌ Query preparation failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user_info = $result->fetch_assoc();
        echo "✅ User info found.<br>";
    } else {
        echo "❌ User not found with ID: $user_id (Type: $user_type)<br>";
    }
    $stmt->close();

    echo "<h3>5. Testing Documents Table...</h3>";
    $table_check = $conn->query("SHOW TABLES LIKE 'tbl_patient_documents'");
    if ($table_check && $table_check->num_rows > 0) {
        echo "✅ tbl_patient_documents table exists.<br>";
        
        if ($user_type === 'patient') {
            $doc_stmt = $conn->prepare("SELECT document_type, file_name, file_path, uploaded_at, notes FROM tbl_patient_documents WHERE patient_id = ? ORDER BY uploaded_at DESC");
            if (!$doc_stmt) {
                echo "❌ Document query preparation failed: " . $conn->error . "<br>";
            } else {
                $doc_stmt->bind_param("i", $user_id);
                $doc_stmt->execute();
                $doc_result = $doc_stmt->get_result();
                echo "📊 Found " . $doc_result->num_rows . " documents for this patient.<br>";
                $doc_stmt->close();
            }
        }
    } else {
        echo "❌ tbl_patient_documents table does NOT exist.<br>";
    }

    echo "<h3>6. Syntax Check Simulation...</h3>";
    echo "This script is currently running, which means the basic PHP structure here is valid.<br>";
    echo "Please check the web server error logs for specific line numbers if you see a White Screen or generic 500 error on profile.php.<br>";

} catch (Exception $e) {
    echo "❌ Caught Exception: " . $e->getMessage() . "<br>";
    echo "Stack trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}
?>
