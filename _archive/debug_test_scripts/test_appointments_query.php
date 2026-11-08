<?php
// Debug script to test appointment queries
require_once 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Please log in first");
}

$user_type = $_SESSION['user_type'] ?? 'patient';
$user_id = $_SESSION['user_id'];

echo "<h2>Appointment Query Debug</h2>";
echo "<p>User Type: " . htmlspecialchars($user_type) . "</p>";
echo "<p>User ID: " . htmlspecialchars($user_id) . "</p>";

$conn = getDBConnection();

if ($user_type === 'patient') {
    // Get patient_id
    $stmt = $conn->prepare("SELECT patient_id FROM tbl_patients WHERE patient_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $patient = $result->fetch_assoc();
        $patient_id = $patient['patient_id'];
        echo "<p>✓ Patient ID: " . $patient_id . "</p>";
        
        // Check all appointments for this patient
        $all_stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_appointments WHERE patient_id = ?");
        $all_stmt->bind_param("i", $patient_id);
        $all_stmt->execute();
        $all_result = $all_stmt->get_result();
        $all_count = $all_result->fetch_assoc();
        echo "<p>Total appointments for this patient: " . $all_count['count'] . "</p>";
        $all_stmt->close();
        
        // Check pending appointments
        $pending_stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_appointments WHERE patient_id = ? AND status = 'Pending'");
        $pending_stmt->bind_param("i", $patient_id);
        $pending_stmt->execute();
        $pending_result = $pending_stmt->get_result();
        $pending_count = $pending_result->fetch_assoc();
        echo "<p>Pending appointments: " . $pending_count['count'] . "</p>";
        $pending_stmt->close();
        
        // Try the actual query
        echo "<h3>Testing Upcoming Appointments Query:</h3>";
        $appointment_stmt = $conn->prepare("
            SELECT 
                a.appointment_id,
                a.scheduled_datetime,
                a.duration_minutes,
                a.status,
                a.price,
                sv.service_id,
                sv.service_name,
                b.name as branch_name,
                b.address as branch_address,
                s.first_name as dentist_first_name,
                s.last_name as dentist_last_name
            FROM tbl_appointments a
            INNER JOIN tbl_branches b ON a.branch_id = b.branch_id
            INNER JOIN tbl_staff s ON a.dentist_id = s.staff_id
            INNER JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
            WHERE a.patient_id = ? 
            AND DATE(a.scheduled_datetime) >= CURDATE()
            AND a.status IN ('Pending', 'Scheduled', 'Confirmed')
            ORDER BY a.scheduled_datetime ASC
            LIMIT 10
        ");
        $appointment_stmt->bind_param("i", $patient_id);
        
        if ($appointment_stmt->execute()) {
            $appointment_result = $appointment_stmt->get_result();
            echo "<p>✓ Query executed successfully</p>";
            echo "<p>Rows returned: " . $appointment_result->num_rows . "</p>";
            
            if ($appointment_result->num_rows > 0) {
                echo "<table border='1' cellpadding='5'>";
                echo "<tr><th>ID</th><th>Date/Time</th><th>Status</th><th>Service</th><th>Dentist</th><th>Branch</th></tr>";
                while ($row = $appointment_result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['appointment_id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['scheduled_datetime']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['service_name'] ?? 'N/A') . "</td>";
                    echo "<td>" . htmlspecialchars(($row['dentist_first_name'] ?? '') . ' ' . ($row['dentist_last_name'] ?? '')) . "</td>";
                    echo "<td>" . htmlspecialchars($row['branch_name'] ?? 'N/A') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No appointments found. Checking all appointments regardless of date/status:</p>";
                $all_app_stmt = $conn->prepare("SELECT * FROM tbl_appointments WHERE patient_id = ? ORDER BY scheduled_datetime DESC LIMIT 5");
                $all_app_stmt->bind_param("i", $patient_id);
                $all_app_stmt->execute();
                $all_app_result = $all_app_stmt->get_result();
                
                if ($all_app_result->num_rows > 0) {
                    echo "<table border='1' cellpadding='5'>";
                    echo "<tr><th>ID</th><th>Date/Time</th><th>Status</th></tr>";
                    while ($row = $all_app_result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['appointment_id']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['scheduled_datetime']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                } else {
                    echo "<p>No appointments found at all for this patient.</p>";
                }
                $all_app_stmt->close();
            }
        } else {
            echo "<p>✗ Query failed: " . $conn->error . "</p>";
        }
        $appointment_stmt->close();
    } else {
        echo "<p>✗ Patient not found</p>";
    }
    $stmt->close();
}

$conn->close();
?>

