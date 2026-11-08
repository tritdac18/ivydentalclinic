<?php
// Debug script to test book_appointment.php functionality
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/db.php';
require_once 'config/security.php';

echo "<h1>Book Appointment Debug Test</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .success { color: green; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .error { color: red; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
    .info { color: blue; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
    pre { background: #fff; padding: 10px; border-radius: 5px; overflow-x: auto; }
    ul { background: #fff; padding: 15px; border-radius: 5px; }
</style>";

try {
    // Test 1: Database Connection
    echo "<h2>Test 1: Database Connection</h2>";
    $conn = getDBConnection();
    
    if (!$conn) {
        die("<div class='error'>✗ Database connection failed</div>");
    }
    echo "<div class='success'>✓ Database connected successfully</div>";
    
    // Test 2: Check Required Tables
    echo "<h2>Test 2: Required Tables</h2>";
    $required_tables = ['tbl_appointments', 'tbl_staff', 'tbl_patients', 'tbl_service_catalog'];
    $tables_exist = [];
    
    foreach ($required_tables as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check && $check->num_rows > 0) {
            echo "<div class='success'>✓ Table $table exists</div>";
            $tables_exist[$table] = true;
        } else {
            echo "<div class='error'>✗ Table $table does NOT exist</div>";
            $tables_exist[$table] = false;
        }
    }
    
    // Test 3: Check tbl_appointments Structure
    echo "<h2>Test 3: tbl_appointments Table Structure</h2>";
    if ($tables_exist['tbl_appointments']) {
        $columns = $conn->query("SHOW COLUMNS FROM tbl_appointments");
        if ($columns) {
            echo "<ul>";
            $col_names = [];
            while ($col = $columns->fetch_assoc()) {
                $col_names[] = $col['Field'];
                echo "<li><strong>" . $col['Field'] . "</strong> (" . $col['Type'] . ") " . 
                     ($col['Null'] === 'YES' ? '<span style="color:orange">NULL</span>' : '<span style="color:green">NOT NULL</span>') . 
                     "</li>";
            }
            echo "</ul>";
            
            // Check for branch-related columns
            $branch_cols = ['branch_id', 'primary_branch_id'];
            $found_branch = false;
            foreach ($branch_cols as $bc) {
                if (in_array($bc, $col_names)) {
                    echo "<div class='error'>✗ Found branch-related column: $bc (should be removed!)</div>";
                    $found_branch = true;
                }
            }
            if (!$found_branch) {
                echo "<div class='success'>✓ No branch-related columns found</div>";
            }
            
            // Check required columns
            $required_cols = ['appointment_id', 'patient_id', 'dentist_id', 'scheduled_datetime', 'duration_minutes', 'status'];
            $missing_cols = [];
            foreach ($required_cols as $rc) {
                if (!in_array($rc, $col_names)) {
                    $missing_cols[] = $rc;
                }
            }
            if (empty($missing_cols)) {
                echo "<div class='success'>✓ All required columns exist</div>";
            } else {
                echo "<div class='error'>✗ Missing required columns: " . implode(', ', $missing_cols) . "</div>";
            }
        } else {
            echo "<div class='error'>✗ Error getting columns: " . $conn->error . "</div>";
        }
    }
    
    // Test 4: Check tbl_staff Structure
    echo "<h2>Test 4: tbl_staff Table Structure</h2>";
    if ($tables_exist['tbl_staff']) {
        $columns = $conn->query("SHOW COLUMNS FROM tbl_staff");
        if ($columns) {
            $col_names = [];
            while ($col = $columns->fetch_assoc()) {
                $col_names[] = $col['Field'];
            }
            
            if (in_array('primary_branch_id', $col_names)) {
                echo "<div class='error'>✗ Found primary_branch_id column (should be removed!)</div>";
            } else {
                echo "<div class='success'>✓ No primary_branch_id column found</div>";
            }
            
            $required_cols = ['staff_id', 'username', 'first_name', 'last_name', 'email', 'password', 'role'];
            $missing_cols = [];
            foreach ($required_cols as $rc) {
                if (!in_array($rc, $col_names)) {
                    $missing_cols[] = $rc;
                }
            }
            if (empty($missing_cols)) {
                echo "<div class='success'>✓ All required columns exist</div>";
            } else {
                echo "<div class='error'>✗ Missing required columns: " . implode(', ', $missing_cols) . "</div>";
            }
        }
    }
    
    // Test 5: Check for tbl_branches
    echo "<h2>Test 5: Check for tbl_branches</h2>";
    $branch_check = $conn->query("SHOW TABLES LIKE 'tbl_branches'");
    if ($branch_check && $branch_check->num_rows > 0) {
        echo "<div class='error'>✗ tbl_branches table still exists (should be dropped!)</div>";
    } else {
        echo "<div class='success'>✓ tbl_branches table does not exist (good!)</div>";
    }
    
    // Test 6: Test Dentist Availability Functions
    echo "<h2>Test 6: Dentist Availability Functions</h2>";
    
    // Try multiple possible paths
    $possible_paths = [
        'functions/dentist_availability.php',
        __DIR__ . '/functions/dentist_availability.php',
        './functions/dentist_availability.php',
        '../functions/dentist_availability.php'
    ];
    
    $function_file = null;
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $function_file = $path;
            echo "<div class='success'>✓ Found at: $path</div>";
            break;
        }
    }
    
    if ($function_file) {
        try {
            require_once $function_file;
            echo "<div class='success'>✓ File loaded successfully</div>";
            
            if (function_exists('findAvailableDentist')) {
                echo "<div class='success'>✓ findAvailableDentist() function exists</div>";
            } else {
                echo "<div class='error'>✗ findAvailableDentist() function NOT found after loading file</div>";
            }
            
            if (function_exists('isDentistAvailable')) {
                echo "<div class='success'>✓ isDentistAvailable() function exists</div>";
            } else {
                echo "<div class='error'>✗ isDentistAvailable() function NOT found after loading file</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>✗ Error loading file: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='error'>✗ functions/dentist_availability.php does NOT exist in any of these locations:</div>";
        echo "<ul>";
        foreach ($possible_paths as $path) {
            $exists = file_exists($path) ? '✓' : '✗';
            echo "<li>$exists $path</li>";
        }
        echo "</ul>";
        echo "<div class='info'>Current working directory: " . getcwd() . "</div>";
        echo "<div class='info'>Script directory: " . __DIR__ . "</div>";
    }
    
    // Test 7: Test Basic Queries
    echo "<h2>Test 7: Test Basic Queries</h2>";
    
    // Test query for appointments
    if ($tables_exist['tbl_appointments'] && $tables_exist['tbl_staff']) {
        $test_query = "
            SELECT 
                a.appointment_id,
                a.scheduled_datetime,
                a.duration_minutes,
                a.status,
                s.first_name as dentist_first_name,
                s.last_name as dentist_last_name
            FROM tbl_appointments a
            INNER JOIN tbl_staff s ON a.dentist_id = s.staff_id
            LIMIT 1
        ";
        
        $result = $conn->query($test_query);
        if ($result) {
            echo "<div class='success'>✓ Basic appointment query works</div>";
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                echo "<div class='info'>Sample appointment data:</div>";
                echo "<pre>" . print_r($row, true) . "</pre>";
            } else {
                echo "<div class='info'>No appointments found (this is okay for a fresh database)</div>";
            }
        } else {
            echo "<div class='error'>✗ Basic appointment query failed: " . $conn->error . "</div>";
        }
    }
    
    // Test 8: Check for Dentists
    echo "<h2>Test 8: Check for Available Dentists</h2>";
    if ($tables_exist['tbl_staff']) {
        $dentist_query = "SELECT staff_id, first_name, last_name, role FROM tbl_staff WHERE role IN ('Dentist', 'Admin') LIMIT 5";
        $result = $conn->query($dentist_query);
        if ($result) {
            if ($result->num_rows > 0) {
                echo "<div class='success'>✓ Found " . $result->num_rows . " dentist(s)/admin(s)</div>";
                echo "<ul>";
                while ($row = $result->fetch_assoc()) {
                    echo "<li>ID: {$row['staff_id']} - {$row['first_name']} {$row['last_name']} ({$row['role']})</li>";
                }
                echo "</ul>";
            } else {
                echo "<div class='error'>✗ No dentists found! You need at least one dentist/admin to book appointments.</div>";
            }
        } else {
            echo "<div class='error'>✗ Query failed: " . $conn->error . "</div>";
        }
    }
    
    // Test 9: Test Service Catalog
    echo "<h2>Test 9: Service Catalog</h2>";
    if ($tables_exist['tbl_service_catalog']) {
        $service_query = "SELECT COUNT(*) as count FROM tbl_service_catalog WHERE is_active = 1";
        $result = $conn->query($service_query);
        if ($result) {
            $row = $result->fetch_assoc();
            if ($row['count'] > 0) {
                echo "<div class='success'>✓ Found " . $row['count'] . " active service(s)</div>";
            } else {
                echo "<div class='error'>✗ No active services found in catalog</div>";
            }
        }
    } else {
        echo "<div class='info'>ℹ tbl_service_catalog doesn't exist (will use fallback services)</div>";
    }
    
    // Test 10: Test INSERT Query Structure
    echo "<h2>Test 10: Test INSERT Query Structure</h2>";
    if ($tables_exist['tbl_appointments'] && $tables_exist['tbl_staff'] && $tables_exist['tbl_patients']) {
        // Check if we can prepare an INSERT statement (without executing)
        $test_insert = $conn->prepare("
            INSERT INTO tbl_appointments 
            (patient_id, dentist_id, scheduled_datetime, duration_minutes, status) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        if ($test_insert) {
            echo "<div class='success'>✓ Basic INSERT query can be prepared</div>";
            $test_insert->close();
        } else {
            echo "<div class='error'>✗ Cannot prepare INSERT query: " . $conn->error . "</div>";
        }
    }
    
    // Test 11: Check Foreign Key Constraints
    echo "<h2>Test 11: Foreign Key Constraints</h2>";
    $fk_query = "
        SELECT 
            CONSTRAINT_NAME,
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'tbl_appointments'
          AND REFERENCED_TABLE_NAME IS NOT NULL
    ";
    
    $result = $conn->query($fk_query);
    if ($result) {
        $fks = [];
        while ($row = $result->fetch_assoc()) {
            $fks[] = $row;
        }
        
        if (empty($fks)) {
            echo "<div class='error'>✗ No foreign key constraints found (might be an issue)</div>";
        } else {
            echo "<div class='success'>✓ Found " . count($fks) . " foreign key constraint(s):</div>";
            echo "<ul>";
            foreach ($fks as $fk) {
                $ref_branch = ($fk['REFERENCED_TABLE_NAME'] === 'tbl_branches');
                $color = $ref_branch ? 'red' : 'green';
                echo "<li style='color:$color'>" . 
                     $fk['CONSTRAINT_NAME'] . ": " . 
                     $fk['TABLE_NAME'] . "." . $fk['COLUMN_NAME'] . 
                     " → " . $fk['REFERENCED_TABLE_NAME'] . "." . $fk['REFERENCED_COLUMN_NAME'] .
                     ($ref_branch ? " <strong>(BRANCH REFERENCE - SHOULD BE REMOVED!)</strong>" : "") .
                     "</li>";
            }
            echo "</ul>";
        }
    }
    
    $conn->close();
    
    echo "<h2>Summary</h2>";
    echo "<div class='info'>Debug test completed. Review the results above to identify any issues.</div>";
    echo "<div class='info'><strong>Next Steps:</strong></div>";
    echo "<ul>";
    echo "<li>If you see branch-related errors, make sure you've run the SQL scripts to remove branches</li>";
    echo "<li>If tables are missing, run the CREATE TABLE scripts</li>";
    echo "<li>If columns are missing, check your table structure matches create_tbl_appointments.sql</li>";
    echo "<li>If functions are missing, check functions/dentist_availability.php exists</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h3>FATAL ERROR:</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}

