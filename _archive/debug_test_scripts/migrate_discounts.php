<?php
require_once 'config/db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = getDBConnection();
echo "<h1>Migrating Discounts</h1>";

// Check if tbl_discounts exists
$check = $conn->query("SHOW TABLES LIKE 'tbl_discounts'");
if ($check->num_rows == 0) {
    die("tbl_discounts does not exist. Please run apply_schema_updates.php first.");
}

// Migrate PWD
echo "<h2>Migrating PWD...</h2>";
$pwd_query = "SELECT patient_id, pwd_id_number, pwd_verified_by_staff_id, pwd_verified_at FROM tbl_patients WHERE pwd_id_number IS NOT NULL AND pwd_id_number != ''";
$result = $conn->query($pwd_query);
if ($result) {
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $stmt = $conn->prepare("INSERT INTO tbl_discounts (patient_id, discount_type, id_number, is_verified, verified_by_staff_id, verified_at) VALUES (?, 'PWD', ?, ?, ?, ?)");
        $is_verified = !empty($row['pwd_verified_by_staff_id']) ? 1 : 0;
        $stmt->bind_param("isiis", $row['patient_id'], $row['pwd_id_number'], $is_verified, $row['pwd_verified_by_staff_id'], $row['pwd_verified_at']);
        if ($stmt->execute()) {
            $count++;
        }
        $stmt->close();
    }
    echo "Migrated $count PWD records.<br>";
} else {
    echo "Error fetching PWDs: " . $conn->error . "<br>";
}

// Migrate Senior
echo "<h2>Migrating Senior Citizens...</h2>";
$senior_query = "SELECT patient_id, senior_citizen_id_number, senior_citizen_verified_by_staff_id, senior_citizen_verified_at FROM tbl_patients WHERE senior_citizen_id_number IS NOT NULL AND senior_citizen_id_number != ''";
$result = $conn->query($senior_query);
if ($result) {
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $stmt = $conn->prepare("INSERT INTO tbl_discounts (patient_id, discount_type, id_number, is_verified, verified_by_staff_id, verified_at) VALUES (?, 'Senior Citizen', ?, ?, ?, ?)");
        $is_verified = !empty($row['senior_citizen_verified_by_staff_id']) ? 1 : 0;
        $stmt->bind_param("isiis", $row['patient_id'], $row['senior_citizen_id_number'], $is_verified, $row['senior_citizen_verified_by_staff_id'], $row['senior_citizen_verified_at']);
        if ($stmt->execute()) {
            $count++;
        }
        $stmt->close();
    }
    echo "Migrated $count Senior Citizen records.<br>";
} else {
    echo "Error fetching Seniors: " . $conn->error . "<br>";
}

echo "<p>Migration Complete.</p>";
?>
