<?php
/**
 * Diagnostic script to check if registration setup is correct
 * Run this to verify your database is properly configured for email verification
 */

require_once 'config/db.php';

echo "<h2>Registration System Diagnostic</h2>\n";
echo "<pre>\n";

try {
    $conn = getDBConnection();
    
    // Check 1: Does tbl_patients have status column?
    echo "1. Checking tbl_patients status column...\n";
    $status_check = $conn->query("SHOW COLUMNS FROM tbl_patients LIKE 'status'");
    if ($status_check && $status_check->num_rows > 0) {
        $status_col = $status_check->fetch_assoc();
        echo "   ✓ Status column exists\n";
        echo "   Type: " . $status_col['Type'] . "\n";
        echo "   Default: " . ($status_col['Default'] ?? 'NULL') . "\n";
    } else {
        echo "   ✗ Status column DOES NOT EXIST!\n";
        echo "   Run: ALTER TABLE tbl_patients ADD COLUMN status ENUM('pending_verification', 'active', 'inactive') DEFAULT 'active' AFTER updated_at;\n";
    }
    
    // Check 2: Does tbl_pending_registrations table exist?
    echo "\n2. Checking tbl_pending_registrations table...\n";
    $table_check = $conn->query("SHOW TABLES LIKE 'tbl_pending_registrations'");
    if ($table_check && $table_check->num_rows > 0) {
        echo "   ✓ Table exists\n";
        
        // Check structure
        $columns = $conn->query("SHOW COLUMNS FROM tbl_pending_registrations");
        echo "   Columns:\n";
        while ($col = $columns->fetch_assoc()) {
            echo "     - " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
        
        // Check if has patient_id (new structure)
        $patient_id_check = $conn->query("SHOW COLUMNS FROM tbl_pending_registrations LIKE 'patient_id'");
        if ($patient_id_check && $patient_id_check->num_rows > 0) {
            echo "   ✓ Has patient_id column (new structure)\n";
        } else {
            echo "   ⚠ Has old structure (no patient_id column)\n";
        }
    } else {
        echo "   ✗ Table DOES NOT EXIST!\n";
        echo "   Run: REFACTOR_pending_registrations.sql\n";
    }
    
    // Check 3: Does tbl_email_verification table exist?
    echo "\n3. Checking tbl_email_verification table...\n";
    $email_table_check = $conn->query("SHOW TABLES LIKE 'tbl_email_verification'");
    if ($email_table_check && $email_table_check->num_rows > 0) {
        echo "   ✓ Table exists\n";
    } else {
        echo "   ✗ Table DOES NOT EXIST!\n";
        echo "   Run: create_tbl_email_verification.sql\n";
    }
    
    // Check 4: Check for patients with pending_verification status
    echo "\n4. Checking for pending verifications...\n";
    $pending_check = $conn->query("SELECT COUNT(*) as count FROM tbl_patients WHERE status = 'pending_verification'");
    if ($pending_check) {
        $pending_count = $pending_check->fetch_assoc()['count'];
        echo "   Found $pending_count patients with pending_verification status\n";
        
        if ($pending_count > 0) {
            $pending_patients = $conn->query("SELECT patient_id, email, username, created_at FROM tbl_patients WHERE status = 'pending_verification' LIMIT 5");
            echo "   Sample pending patients:\n";
            while ($patient = $pending_patients->fetch_assoc()) {
                echo "     - ID: {$patient['patient_id']}, Email: {$patient['email']}, Username: {$patient['username']}, Created: {$patient['created_at']}\n";
            }
        }
    }
    
    // Check 5: Check for orphaned pending registrations
    echo "\n5. Checking for orphaned pending registrations...\n";
    $orphaned_check = $conn->query("
        SELECT pr.* FROM tbl_pending_registrations pr
        LEFT JOIN tbl_patients p ON pr.patient_id = p.patient_id
        WHERE p.patient_id IS NULL
        LIMIT 5
    ");
    if ($orphaned_check && $orphaned_check->num_rows > 0) {
        echo "   ⚠ Found orphaned pending registrations (no matching patient)\n";
    } else {
        echo "   ✓ No orphaned records found\n";
    }
    
    $conn->close();
    
    echo "\n=== Summary ===\n";
    echo "If you see any ✗ marks above, those need to be fixed.\n";
    echo "Run the appropriate SQL scripts to set up the missing components.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>\n";

