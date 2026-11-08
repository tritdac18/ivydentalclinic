<?php
require_once 'config/db.php';
require_once 'config/security.php';

// Set security headers and check login
requireLogin();

$user_type = $_SESSION['user_type'] ?? 'patient';
$user_role = $_SESSION['user_role'] ?? '';

// Define admin flags
$is_admin = ($user_type === 'staff' && ($user_role === 'Manager' || $user_role === 'Admin' || $user_role === 'Dentist' || $user_role === 'Receptionist'));
$is_receptionist = ($user_type === 'staff' && $user_role === 'Receptionist');

// Only allow staff to access this debug page
if ($user_type !== 'staff') {
    die("Access denied. Staff only.");
}

$conn = getDBConnection();
$debug_info = [];

// Check 1: What roles exist in the database
$debug_info['available_roles'] = [];
$roles_stmt = $conn->query("SELECT DISTINCT role, COUNT(*) as count FROM tbl_staff GROUP BY role");
if ($roles_stmt) {
    while ($row = $roles_stmt->fetch_assoc()) {
        $debug_info['available_roles'][] = $row;
    }
}

// Check 2: All staff members
$debug_info['all_staff'] = [];
$all_staff_stmt = $conn->query("SELECT staff_id, first_name, last_name, role FROM tbl_staff ORDER BY role, last_name, first_name");
if ($all_staff_stmt) {
    while ($row = $all_staff_stmt->fetch_assoc()) {
        $debug_info['all_staff'][] = $row;
    }
}

// Check 3: Try Query 1 - Exact match (Dentist, Admin)
$debug_info['query1_results'] = [];
$query1_stmt = $conn->prepare("SELECT staff_id, first_name, last_name, role FROM tbl_staff WHERE role IN ('Dentist', 'Admin') ORDER BY last_name, first_name");
if ($query1_stmt) {
    if ($query1_stmt->execute()) {
        $result1 = $query1_stmt->get_result();
        while ($row = $result1->fetch_assoc()) {
            $debug_info['query1_results'][] = $row;
        }
    } else {
        $debug_info['query1_error'] = $query1_stmt->error;
    }
    $query1_stmt->close();
} else {
    $debug_info['query1_error'] = $conn->error;
}

// Check 4: Try Query 2 - Lowercase 'dentist'
$debug_info['query2_results'] = [];
$query2_stmt = $conn->prepare("SELECT staff_id, first_name, last_name, role FROM tbl_staff WHERE role = 'dentist' ORDER BY last_name, first_name");
if ($query2_stmt) {
    if ($query2_stmt->execute()) {
        $result2 = $query2_stmt->get_result();
        while ($row = $result2->fetch_assoc()) {
            $debug_info['query2_results'][] = $row;
        }
    } else {
        $debug_info['query2_error'] = $query2_stmt->error;
    }
    $query2_stmt->close();
} else {
    $debug_info['query2_error'] = $conn->error;
}

// Check 5: Try Query 3 - Case-insensitive
$debug_info['query3_results'] = [];
$query3_stmt = $conn->prepare("SELECT staff_id, first_name, last_name, role FROM tbl_staff WHERE LOWER(TRIM(role)) IN ('dentist', 'admin') ORDER BY last_name, first_name");
if ($query3_stmt) {
    if ($query3_stmt->execute()) {
        $result3 = $query3_stmt->get_result();
        while ($row = $result3->fetch_assoc()) {
            $debug_info['query3_results'][] = $row;
        }
    } else {
        $debug_info['query3_error'] = $query3_stmt->error;
    }
    $query3_stmt->close();
} else {
    $debug_info['query3_error'] = $conn->error;
}

// Check 6: Check user permissions
$debug_info['user_info'] = [
    'user_type' => $user_type,
    'user_role' => $user_role,
    'is_admin' => $is_admin,
    'is_receptionist' => $is_receptionist,
    'user_id' => $_SESSION['user_id'] ?? 'N/A'
];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dentist Query Debug - Ivy Dental Clinic</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .section {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #136dec;
            border-bottom: 3px solid #136dec;
            padding-bottom: 10px;
        }
        h2 {
            color: #333;
            margin-top: 0;
            border-left: 4px solid #136dec;
            padding-left: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #136dec;
            color: white;
        }
        tr:hover {
            background: #f9f9f9;
        }
        .success {
            color: #10b981;
            font-weight: bold;
        }
        .error {
            color: #ef4444;
            font-weight: bold;
        }
        .warning {
            color: #f59e0b;
            font-weight: bold;
        }
        .info-box {
            background: #e0f2fe;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <h1>🔍 Dentist Query Debug Information</h1>
    
    <div class="section">
        <h2>👤 Current User Information</h2>
        <div class="info-box">
            <strong>User Type:</strong> <?php echo htmlspecialchars($debug_info['user_info']['user_type']); ?><br>
            <strong>User Role:</strong> <?php echo htmlspecialchars($debug_info['user_info']['user_role']); ?><br>
            <strong>User ID:</strong> <?php echo htmlspecialchars($debug_info['user_info']['user_id']); ?><br>
            <strong>Is Admin:</strong> <?php echo $debug_info['user_info']['is_admin'] ? '<span class="success">Yes</span>' : '<span class="error">No</span>'; ?><br>
            <strong>Is Receptionist:</strong> <?php echo $debug_info['user_info']['is_receptionist'] ? '<span class="success">Yes</span>' : '<span class="error">No</span>'; ?>
        </div>
    </div>

    <div class="section">
        <h2>📊 Available Roles in Database</h2>
        <?php if (!empty($debug_info['available_roles'])): ?>
            <table>
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($debug_info['available_roles'] as $role_info): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($role_info['role']); ?></code></td>
                        <td><?php echo $role_info['count']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="error">No roles found in database!</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>👥 All Staff Members</h2>
        <?php if (!empty($debug_info['all_staff'])): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($debug_info['all_staff'] as $staff): ?>
                    <tr>
                        <td><?php echo $staff['staff_id']; ?></td>
                        <td><?php echo htmlspecialchars($staff['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($staff['last_name']); ?></td>
                        <td><code><?php echo htmlspecialchars($staff['role']); ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="error">No staff members found!</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>🔍 Query 1: <code>role IN ('Dentist', 'Admin')</code></h2>
        <?php if (isset($debug_info['query1_error'])): ?>
            <p class="error">❌ Error: <?php echo htmlspecialchars($debug_info['query1_error']); ?></p>
        <?php elseif (!empty($debug_info['query1_results'])): ?>
            <p class="success">✅ Found <?php echo count($debug_info['query1_results']); ?> result(s)</p>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($debug_info['query1_results'] as $dentist): ?>
                    <tr>
                        <td><?php echo $dentist['staff_id']; ?></td>
                        <td><?php echo htmlspecialchars($dentist['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($dentist['last_name']); ?></td>
                        <td><code><?php echo htmlspecialchars($dentist['role']); ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="warning">⚠️ No results found</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>🔍 Query 2: <code>role = 'dentist'</code></h2>
        <?php if (isset($debug_info['query2_error'])): ?>
            <p class="error">❌ Error: <?php echo htmlspecialchars($debug_info['query2_error']); ?></p>
        <?php elseif (!empty($debug_info['query2_results'])): ?>
            <p class="success">✅ Found <?php echo count($debug_info['query2_results']); ?> result(s)</p>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($debug_info['query2_results'] as $dentist): ?>
                    <tr>
                        <td><?php echo $dentist['staff_id']; ?></td>
                        <td><?php echo htmlspecialchars($dentist['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($dentist['last_name']); ?></td>
                        <td><code><?php echo htmlspecialchars($dentist['role']); ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="warning">⚠️ No results found</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>🔍 Query 3: <code>LOWER(TRIM(role)) IN ('dentist', 'admin')</code></h2>
        <?php if (isset($debug_info['query3_error'])): ?>
            <p class="error">❌ Error: <?php echo htmlspecialchars($debug_info['query3_error']); ?></p>
        <?php elseif (!empty($debug_info['query3_results'])): ?>
            <p class="success">✅ Found <?php echo count($debug_info['query3_results']); ?> result(s)</p>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($debug_info['query3_results'] as $dentist): ?>
                    <tr>
                        <td><?php echo $dentist['staff_id']; ?></td>
                        <td><?php echo htmlspecialchars($dentist['first_name']); ?></td>
                        <td><?php echo htmlspecialchars($dentist['last_name']); ?></td>
                        <td><code><?php echo htmlspecialchars($dentist['role']); ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="warning">⚠️ No results found</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>💡 Recommendations</h2>
        <div class="info-box">
            <?php 
            $working_query = null;
            if (!empty($debug_info['query1_results'])) {
                $working_query = "Query 1 (role IN ('Dentist', 'Admin'))";
            } elseif (!empty($debug_info['query2_results'])) {
                $working_query = "Query 2 (role = 'dentist')";
            } elseif (!empty($debug_info['query3_results'])) {
                $working_query = "Query 3 (LOWER(TRIM(role)) IN ('dentist', 'admin'))";
            }
            
            if ($working_query): 
            ?>
                <p class="success"><strong>✅ Working Query:</strong> <?php echo $working_query; ?></p>
                <p>Use this query in <code>appointments.php</code> for the walk-in booking form.</p>
            <?php else: ?>
                <p class="error"><strong>❌ No queries returned results!</strong></p>
                <p>Check the "Available Roles" section above to see what roles actually exist in your database.</p>
                <p>You may need to update the role values in your <code>tbl_staff</code> table to match one of the query formats.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="section">
        <p><a href="appointments.php">← Back to Appointments</a></p>
    </div>
</body>
</html>

