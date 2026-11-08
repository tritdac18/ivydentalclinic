<?php
require_once 'config/db.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = getDBConnection();
echo "<h1>Verification Status</h1>";

$success = true;

// 1. Check tbl_discounts
echo "<h3>1. Checking tbl_discounts</h3>";
$check = $conn->query("SHOW TABLES LIKE 'tbl_discounts'");
if ($check->num_rows > 0) {
    echo "<p style='color:green'>✅ tbl_discounts exists.</p>";
    
    // Check columns
    $cols = [];
    $res = $conn->query("DESCRIBE tbl_discounts");
    while($row = $res->fetch_assoc()) $cols[] = $row['Field'];
    
    if (in_array('discount_type', $cols) && in_array('id_number', $cols)) {
        echo "<p style='color:green'>✅ tbl_discounts structure looks correct.</p>";
    } else {
        echo "<p style='color:red'>❌ tbl_discounts structure incorrect.</p>";
        $success = false;
    }
} else {
    echo "<p style='color:red'>❌ tbl_discounts does not exist.</p>";
    $success = false;
}

// 2. Check tbl_payments schema
echo "<h3>2. Checking tbl_payments Schema</h3>";
$cols = [];
$res = $conn->query("DESCRIBE tbl_payments");
while($row = $res->fetch_assoc()) $cols[] = $row['Field'];

$required = ['payment_type', 'appointment_id', 'patient_id', 'transaction_id'];
foreach ($required as $req) {
    if (in_array($req, $cols)) {
        echo "<p style='color:green'>✅ Column '$req' exists.</p>";
    } else {
        echo "<p style='color:red'>❌ Column '$req' is missing.</p>";
        $success = false;
    }
}

if (!in_array('is_reconciled', $cols)) {
    echo "<p style='color:green'>✅ Column 'is_reconciled' successfully removed.</p>";
} else {
    echo "<p style='color:orange'>⚠️ Column 'is_reconciled' still exists (not critical but planned for removal).</p>";
}

// 3. Check Discount Migration
echo "<h3>3. Checking Discount Data</h3>";
$res = $conn->query("SELECT COUNT(*) as count FROM tbl_discounts");
$row = $res->fetch_assoc();
if ($row['count'] > 0) {
    echo "<p style='color:green'>✅ Found " . $row['count'] . " records in tbl_discounts (Migration likely succeeded).</p>";
} else {
    echo "<p style='color:orange'>⚠️ No records in tbl_discounts. Migration might be needed or no data existed.</p>";
}

if ($success) {
    echo "<h2 style='color:green'>SYSTEM READY</h2>";
    echo "<p>You can now test payments.</p>";
} else {
    echo "<h2 style='color:red'>SYSTEM ISSUES DETECTED</h2>";
    echo "<p>Please run apply_schema_updates.php again.</p>";
}
?>
