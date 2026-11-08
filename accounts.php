<?php
require_once 'config/db.php';
require_once 'config/security.php';

// Set security headers and check login
requireLogin();

// Security: Verify user still exists in database and fetch fresh data
$conn = getDBConnection();
$user_info = null;
$user_type = $_SESSION['user_type'] ?? 'patient';
$user_role = $_SESSION['user_role'] ?? '';

// Only allow Admin/Manager to access accounts page
if ($user_type !== 'staff' || ($user_role !== 'Manager' && $user_role !== 'Admin')) {
    header("Location: landing.php");
    exit();
}

try {
    // Fetch admin data from database
    $stmt = $conn->prepare("SELECT staff_id, first_name, last_name, email, role FROM tbl_staff WHERE staff_id = ? LIMIT 1");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user_info = $result->fetch_assoc();
    } else {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Set user variables
$admin_name = $user_info['first_name'] . ' ' . $user_info['last_name'];
$admin_email = $user_info['email'] ?? '';
    $admin_role = $user_info['role'] ?? 'Manager';

// Handle staff creation
$action_message = '';
$action_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_staff') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $email = trim(strtolower($_POST['email'] ?? ''));
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($username) || empty($role) || empty($email)) {
        $action_error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $action_error = "Invalid email address.";
    } elseif (!in_array($role, ['Dentist', 'Receptionist'])) {
        $action_error = "Role must be either Dentist or Receptionist.";
    } else {
        try {
            $conn = getDBConnection();
            
            // Check if username already exists
            $check_stmt = $conn->prepare("SELECT staff_id FROM tbl_staff WHERE username = ? LIMIT 1");
            $check_stmt->bind_param("s", $username);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $action_error = "Username already exists.";
                $check_stmt->close();
            } else {
                $check_stmt->close();
                
                // Check if email already exists
                $check_stmt = $conn->prepare("SELECT staff_id FROM tbl_staff WHERE email = ? LIMIT 1");
                $check_stmt->bind_param("s", $email);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $action_error = "Email already exists.";
                    $check_stmt->close();
                } else {
                    $check_stmt->close();
                    
                    // Generate activation token
                    $activation_token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 day'));
                    
                    // Create staff record with inactive status (no password yet)
                    $insert_stmt = $conn->prepare("INSERT INTO tbl_staff (first_name, last_name, username, email, role, phone_number, license_number) VALUES (?, ?, ?, ?, ?, '', '')");
                    $insert_stmt->bind_param("sssss", $first_name, $last_name, $username, $email, $role);
                    
                    if ($insert_stmt->execute()) {
                        $staff_id = $insert_stmt->insert_id;
                        
                        // Create activation token record
                        $token_stmt = $conn->prepare("INSERT INTO tbl_staff_activation_tokens (staff_id, activation_token, expires_at, created_at) VALUES (?, ?, ?, NOW())");
                        $token_stmt->bind_param("iss", $staff_id, $activation_token, $expires_at);
                        $token_stmt->execute();
                        $token_stmt->close();
                        
                        // Send activation email
                        require_once 'services/email_service.php';
                        $activation_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/activate_staff.php?token=" . $activation_token;
                        
                        try {
                            $emailService = new EmailService();
                            $emailService->sendStaffActivationEmail($email, $first_name, $activation_url);
                            $action_message = "Staff account created successfully. Activation email sent to {$email}.";
                        } catch (Exception $e) {
                            error_log("Email sending error: " . $e->getMessage());
                            // Store activation URL in session to display to admin
                            $_SESSION['last_activation_url'] = $activation_url;
                            $_SESSION['last_activation_email'] = $email;
                            $_SESSION['last_activation_name'] = $first_name;
                            $action_message = "Staff account created successfully, but activation email failed to send. Error: " . htmlspecialchars($e->getMessage()) . ". Please provide the activation link manually.";
                        }
                        
                        $insert_stmt->close();
                    } else {
                        $action_error = "Failed to create staff account: " . $conn->error;
                    }
                }
            }
            $conn->close();
        } catch (Exception $e) {
            $action_error = "Error: " . $e->getMessage();
        }
    }
}

// Pagination and search parameters
$patients_page = isset($_GET['patients_page']) ? max(1, intval($_GET['patients_page'])) : 1;
$staff_page = isset($_GET['staff_page']) ? max(1, intval($_GET['staff_page'])) : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$items_per_page = 10;

// Fetch patients and staff with pagination and search
$patients = [];
$staff_members = [];
$total_patients = 0;
$total_staff = 0;
$total_patient_pages = 0;
$total_staff_pages = 0;

try {
    $conn = getDBConnection();
    
    // Build patient search query
    $patient_search_where = '';
    $patient_search_params = [];
    $patient_search_types = '';
    
    if (!empty($search_query)) {
        $search_term = '%' . $search_query . '%';
        $patient_search_where = "WHERE first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR primary_phone LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?";
        $patient_search_params = [$search_term, $search_term, $search_term, $search_term, $search_term];
        $patient_search_types = 'sssss';
    }
    
    // Count total patients (for pagination)
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_patients " . $patient_search_where);
    if (!empty($patient_search_params)) {
        $count_stmt->bind_param($patient_search_types, ...$patient_search_params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $total_patients = $count_row['total'];
    $total_patient_pages = ceil($total_patients / $items_per_page);
    $count_stmt->close();
    
    // Fetch patients with pagination
    $patient_offset = ($patients_page - 1) * $items_per_page;
    $patient_query = "SELECT patient_id, first_name, last_name, email, primary_phone, dob FROM tbl_patients " . $patient_search_where . " ORDER BY last_name, first_name LIMIT ? OFFSET ?";
    $patient_stmt = $conn->prepare($patient_query);
    
    if (!empty($patient_search_params)) {
        $patient_search_params[] = $items_per_page;
        $patient_search_params[] = $patient_offset;
        $patient_search_types .= 'ii';
        $patient_stmt->bind_param($patient_search_types, ...$patient_search_params);
    } else {
        $patient_stmt->bind_param("ii", $items_per_page, $patient_offset);
    }
    
    $patient_stmt->execute();
    $patient_result = $patient_stmt->get_result();
    while ($row = $patient_result->fetch_assoc()) {
        $patients[] = $row;
    }
    $patient_stmt->close();
    
    // Build staff search query
    $staff_search_where = '';
    $staff_search_params = [];
    $staff_search_types = '';
    
    if (!empty($search_query)) {
        $search_term = '%' . $search_query . '%';
        $staff_search_where = "WHERE first_name LIKE ? OR last_name LIKE ? OR username LIKE ? OR email LIKE ? OR role LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?";
        $staff_search_params = [$search_term, $search_term, $search_term, $search_term, $search_term, $search_term];
        $staff_search_types = 'ssssss';
    }
    
    // Count total staff (for pagination)
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_staff " . $staff_search_where);
    if (!empty($staff_search_params)) {
        $count_stmt->bind_param($staff_search_types, ...$staff_search_params);
    }
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $count_row = $count_result->fetch_assoc();
    $total_staff = $count_row['total'];
    $total_staff_pages = ceil($total_staff / $items_per_page);
    $count_stmt->close();
    
    // Fetch staff with pagination
    $staff_offset = ($staff_page - 1) * $items_per_page;
    $staff_query = "SELECT staff_id, first_name, last_name, username, email, role, phone_number FROM tbl_staff " . $staff_search_where . " ORDER BY role, last_name, first_name LIMIT ? OFFSET ?";
    $staff_stmt = $conn->prepare($staff_query);
    
    if (!empty($staff_search_params)) {
        $staff_search_params[] = $items_per_page;
        $staff_search_params[] = $staff_offset;
        $staff_search_types .= 'ii';
        $staff_stmt->bind_param($staff_search_types, ...$staff_search_params);
    } else {
        $staff_stmt->bind_param("ii", $items_per_page, $staff_offset);
    }
    
    $staff_stmt->execute();
    $staff_result = $staff_stmt->get_result();
    while ($row = $staff_result->fetch_assoc()) {
        $staff_members[] = $row;
    }
    $staff_stmt->close();
    
    $conn->close();
} catch (Exception $e) {
    error_log("Error fetching accounts: " . $e->getMessage());
}

// Helper function for building pagination URLs
function buildPaginationUrl($base_params, $page_param, $page_value) {
    $params = $base_params;
    $params[$page_param] = $page_value;
    return 'accounts.php?' . http_build_query($params);
}
?>
<?php
$page_title = 'Staff Accounts';
$user_name  = $admin_name;
include ROOT_PATH . '/views/layout/header.php';
?>
<div class="flex min-h-screen">
    <?php include ROOT_PATH . '/views/layout/sidebar_staff.php'; ?>
    <div class="flex-1 flex flex-col lg:ml-64">
        <?php include ROOT_PATH . '/views/layout/topbar.php'; ?>
<main class="flex-grow w-full max-w-7xl mx-auto px-4 md:px-8 py-8">
<?php if ($action_message): ?>
<div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-400 mb-4">
<p class="text-sm font-medium"><?php echo htmlspecialchars($action_message); ?></p>
<?php if (isset($_SESSION['last_activation_url'])): ?>
<div class="mt-3 p-3 bg-white dark:bg-slate-800 rounded border border-green-300 dark:border-green-700">
<p class="text-xs font-semibold mb-2">Activation Link (for manual distribution):</p>
<div class="flex items-center gap-2">
<input type="text" id="activationLink" value="<?php echo htmlspecialchars($_SESSION['last_activation_url']); ?>" readonly class="flex-1 text-xs px-2 py-1 border border-slate-300 dark:border-slate-600 rounded bg-white dark:bg-slate-700 text-slate-900 dark:text-white">
<button onclick="copyActivationLink()" class="px-3 py-1 bg-primary text-white text-xs rounded hover:bg-blue-700 transition-colors">Copy</button>
</div>
<p class="text-xs text-slate-600 dark:text-slate-400 mt-2">Send this link to: <?php echo htmlspecialchars($_SESSION['last_activation_email'] ?? ''); ?></p>
</div>
<?php 
unset($_SESSION['last_activation_url']);
unset($_SESSION['last_activation_email']);
unset($_SESSION['last_activation_name']);
endif; ?>
</div>
<?php endif; ?>
<?php if ($action_error): ?>
<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-400 mb-4">
<p class="text-sm font-medium"><?php echo htmlspecialchars($action_error); ?></p>
</div>
<?php endif; ?>
<div class="flex flex-col gap-8">
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
<h2 class="text-2xl font-bold text-slate-900 dark:text-white">Accounts</h2>
<div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
<!-- Search Bar -->
<form method="GET" action="" class="flex-1 md:flex-initial">
<div class="relative">
<input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search patients or staff..." class="w-full md:w-64 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 pr-10 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" />
<button type="submit" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary dark:text-slate-500 dark:hover:text-primary">
<span class="material-symbols-outlined text-xl">search</span>
</button>
</div>
<?php if (!empty($search_query)): ?>
<a href="accounts.php" class="mt-2 text-xs text-primary hover:text-blue-700 dark:hover:text-blue-400">Clear search</a>
<?php endif; ?>
</form>
<button onclick="openCreateStaffModal()" class="flex items-center gap-2 bg-primary text-white hover:bg-blue-700 font-semibold transition-colors px-4 py-2 rounded-lg shadow-sm whitespace-nowrap">
<span class="material-symbols-outlined text-lg">add</span>
<span>Create Staff</span>
</button>
</div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
<!-- Patients Section -->
<div class="bg-white dark:bg-[#1a2634] rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
<div class="flex justify-between items-center mb-4">
<h3 class="text-lg font-bold text-slate-900 dark:text-white">Patients</h3>
<?php if ($total_patients > 0): ?>
<span class="text-sm text-slate-500 dark:text-slate-400"><?php echo $total_patients; ?> total</span>
<?php endif; ?>
</div>
<div class="overflow-x-auto">
<table class="w-full">
<thead>
<tr class="border-b border-slate-200 dark:border-slate-700">
<th class="text-left py-3 px-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Name</th>
<th class="text-left py-3 px-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Email</th>
<th class="text-left py-3 px-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Phone</th>
<th class="text-center py-3 px-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Actions</th>
</tr>
</thead>
<tbody>
<?php if (empty($patients)): ?>
<tr>
<td colspan="4" class="py-8 text-center text-slate-500 dark:text-slate-400"><?php echo !empty($search_query) ? 'No patients found matching your search.' : 'No patients found.'; ?></td>
</tr>
<?php else: ?>
<?php foreach ($patients as $patient): ?>
<tr class="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
<td class="py-3 px-4">
<span class="text-sm text-slate-900 dark:text-white"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></span>
</td>
<td class="py-3 px-4">
<span class="text-sm text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?></span>
</td>
<td class="py-3 px-4">
<span class="text-sm text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($patient['primary_phone'] ?? 'N/A'); ?></span>
</td>
<td class="py-3 px-4 text-center">
<a href="profile.php?patient_id=<?php echo $patient['patient_id']; ?>" class="inline-flex items-center gap-1 text-sm text-primary hover:text-blue-700 dark:hover:text-blue-400 font-medium transition-colors">
<span class="material-symbols-outlined text-base">visibility</span>
<span>View Profile</span>
</a>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
<?php if ($total_patient_pages > 1): ?>
<div class="mt-4 flex items-center justify-between">
<div class="text-sm text-slate-600 dark:text-slate-400">
Showing <?php echo (($patients_page - 1) * $items_per_page) + 1; ?> to <?php echo min($patients_page * $items_per_page, $total_patients); ?> of <?php echo $total_patients; ?> patients
</div>
<div class="flex items-center gap-2">
<?php
$patients_base_params = [];
if (!empty($search_query)) {
    $patients_base_params['search'] = $search_query;
}
if ($staff_page > 1) {
    $patients_base_params['staff_page'] = $staff_page;
}
?>
<a href="<?php echo buildPaginationUrl($patients_base_params, 'patients_page', $patients_page - 1); ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors <?php echo $patients_page <= 1 ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
<span class="material-symbols-outlined text-sm">chevron_left</span>
</a>
<span class="text-sm text-slate-700 dark:text-slate-300">Page <?php echo $patients_page; ?> of <?php echo $total_patient_pages; ?></span>
<a href="<?php echo buildPaginationUrl($patients_base_params, 'patients_page', $patients_page + 1); ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors <?php echo $patients_page >= $total_patient_pages ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
<span class="material-symbols-outlined text-sm">chevron_right</span>
</a>
</div>
</div>
<?php endif; ?>
</div>
<!-- Staff Section -->
<div class="bg-white dark:bg-[#1a2634] rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
<div class="flex justify-between items-center mb-4">
<h3 class="text-lg font-bold text-slate-900 dark:text-white">Staff</h3>
<?php if ($total_staff > 0): ?>
<span class="text-sm text-slate-500 dark:text-slate-400"><?php echo $total_staff; ?> total</span>
<?php endif; ?>
</div>
<div class="overflow-x-auto">
<table class="w-full">
<thead>
<tr class="border-b border-slate-200 dark:border-slate-700">
<th class="text-left py-3 px-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Name</th>
<th class="text-left py-3 px-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Username</th>
<th class="text-left py-3 px-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Role</th>
<th class="text-left py-3 px-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Email</th>
<th class="text-center py-3 px-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Actions</th>
</tr>
</thead>
<tbody>
<?php if (empty($staff_members)): ?>
<tr>
<td colspan="5" class="py-8 text-center text-slate-500 dark:text-slate-400"><?php echo !empty($search_query) ? 'No staff members found matching your search.' : 'No staff members found.'; ?></td>
</tr>
<?php else: ?>
<?php foreach ($staff_members as $staff): ?>
<tr class="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
<td class="py-3 px-4">
<span class="text-sm text-slate-900 dark:text-white"><?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?></span>
</td>
<td class="py-3 px-4">
<span class="text-sm text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($staff['username']); ?></span>
</td>
<td class="py-3 px-4">
<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php 
    echo $staff['role'] === 'Manager' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400' : 
    ($staff['role'] === 'Dentist' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400' : 
    'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400');
?>">
<?php echo htmlspecialchars($staff['role']); ?>
</span>
</td>
<td class="py-3 px-4">
<span class="text-sm text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($staff['email']); ?></span>
</td>
<td class="py-3 px-4 text-center">
<a href="profile.php?staff_id=<?php echo $staff['staff_id']; ?>" class="inline-flex items-center gap-1 text-sm text-primary hover:text-blue-700 dark:hover:text-blue-400 font-medium transition-colors">
<span class="material-symbols-outlined text-base">visibility</span>
<span>View Profile</span>
</a>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
<?php if ($total_staff_pages > 1): ?>
<div class="mt-4 flex items-center justify-between">
<div class="text-sm text-slate-600 dark:text-slate-400">
Showing <?php echo (($staff_page - 1) * $items_per_page) + 1; ?> to <?php echo min($staff_page * $items_per_page, $total_staff); ?> of <?php echo $total_staff; ?> staff
</div>
<div class="flex items-center gap-2">
<?php
$staff_base_params = [];
if (!empty($search_query)) {
    $staff_base_params['search'] = $search_query;
}
if ($patients_page > 1) {
    $staff_base_params['patients_page'] = $patients_page;
}
?>
<a href="<?php echo buildPaginationUrl($staff_base_params, 'staff_page', $staff_page - 1); ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors <?php echo $staff_page <= 1 ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
<span class="material-symbols-outlined text-sm">chevron_left</span>
</a>
<span class="text-sm text-slate-700 dark:text-slate-300">Page <?php echo $staff_page; ?> of <?php echo $total_staff_pages; ?></span>
<a href="<?php echo buildPaginationUrl($staff_base_params, 'staff_page', $staff_page + 1); ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors <?php echo $staff_page >= $total_staff_pages ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''; ?>">
<span class="material-symbols-outlined text-sm">chevron_right</span>
</a>
</div>
</div>
<?php endif; ?>
</div>
</div>
</div>
</main>
        </main><!-- /.main -->
    </div><!-- /flex-1 -->
</div><!-- /flex min-h-screen -->

<!-- Create Staff Modal -->
<div id="createStaffModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" onclick="closeCreateStaffModal(event)">
<div class="bg-white dark:bg-[#1a2634] rounded-xl shadow-xl max-w-md w-full p-6" onclick="event.stopPropagation()">
<div class="flex items-center justify-between mb-4">
<h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Create Staff Account</h3>
<button onclick="closeCreateStaffModal()" class="text-[#4c6c9a] hover:text-[#0d131b] dark:text-slate-400 dark:hover:text-white">
<span class="material-symbols-outlined">close</span>
</button>
</div>
<form method="POST" action="" id="createStaffForm">
<input type="hidden" name="action" value="create_staff">
<div class="space-y-4">
<div>
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">First Name <span class="text-red-500">*</span></label>
<input type="text" name="first_name" required class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Enter first name"/>
</div>
<div>
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Last Name <span class="text-red-500">*</span></label>
<input type="text" name="last_name" required class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Enter last name"/>
</div>
<div>
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Username <span class="text-red-500">*</span></label>
<input type="text" name="username" required class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Enter username"/>
</div>
<div>
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Role <span class="text-red-500">*</span></label>
<select name="role" required class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
<option value="">Select role</option>
<option value="Dentist">Dentist</option>
<option value="Receptionist">Receptionist</option>
</select>
</div>
<div>
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Email <span class="text-red-500">*</span></label>
<input type="email" name="email" required class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Enter email address"/>
</div>
</div>
<div class="mt-6 flex justify-end gap-2">
<button type="button" onclick="closeCreateStaffModal()" class="px-4 py-2 border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-lg text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
Cancel
</button>
<button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-blue-600 transition-colors">
Create Staff
</button>
</div>
</form>
</div>
</div>

<?php
$extra_scripts = '<script>
function openCreateStaffModal() {
    document.getElementById(\"createStaffModal\").classList.remove(\"hidden\");
    document.getElementById(\"createStaffModal\").classList.add(\"flex\");
}
function closeCreateStaffModal(event) {
    if (event && event.target !== event.currentTarget) return;
    const modal = document.getElementById(\"createStaffModal\");
    if (modal) { modal.classList.add(\"hidden\"); modal.classList.remove(\"flex\"); }
}
function copyActivationLink() {
    const linkInput = document.getElementById(\"activationLink\");
    if (linkInput) {
        linkInput.select();
        linkInput.setSelectionRange(0, 99999);
        document.execCommand(\"copy\");
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = \"Copied!\";
        button.classList.add(\"bg-green-600\");
        setTimeout(() => { button.textContent = originalText; button.classList.remove(\"bg-green-600\"); }, 2000);
    }
}
</script>';
include ROOT_PATH . '/views/layout/footer.php';
