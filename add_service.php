<?php
// Start output buffering to prevent any output before headers
ob_start();

require_once 'config/db.php';
require_once 'config/security.php';

// Set security headers and check login
requireLogin();

$user_type = $_SESSION['user_type'] ?? 'patient';
$user_role = $_SESSION['user_role'] ?? '';

// Only Admin/Manager can access
if ($user_type !== 'staff' || ($user_role !== 'Admin' && $user_role !== 'Manager')) {
    header("Location: landing.php");
    exit();
}

// Fetch admin info for display
$conn = getDBConnection();
$admin_info = null;
try {
    $stmt = $conn->prepare("SELECT staff_id, first_name, last_name, email, role FROM tbl_staff WHERE staff_id = ? LIMIT 1");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $admin_info = $result->fetch_assoc();
    }
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log("Error fetching admin info: " . $e->getMessage());
}

$admin_name = $admin_info ? ($admin_info['first_name'] . ' ' . $admin_info['last_name']) : 'Admin';
$admin_role = $admin_info['role'] ?? 'Admin';

$errors = [];
$success = '';

// Check for session success message (from redirects)
if (isset($_SESSION['service_success_message'])) {
    $success = $_SESSION['service_success_message'];
    unset($_SESSION['service_success_message']);
}

// Fetch all existing services (for display and prerequisite selection)
$existing_services = [];
$all_services = [];
$service_prerequisites = []; // Store prerequisites for each service
$editing_service_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$editing_service = null;

try {
    $conn = getDBConnection();
    // Fetch active services for prerequisite selection
    $service_stmt = $conn->prepare("SELECT service_id, service_name FROM tbl_service_catalog WHERE is_active = 1 ORDER BY service_name");
    if ($service_stmt) {
        $service_stmt->execute();
        $service_result = $service_stmt->get_result();
        while ($row = $service_result->fetch_assoc()) {
            $existing_services[] = $row;
        }
        $service_stmt->close();
    }
    // Fetch all services for management display
    $all_service_stmt = $conn->prepare("SELECT service_id, service_name, base_price, default_duration_minutes, is_active FROM tbl_service_catalog ORDER BY service_name");
    if ($all_service_stmt) {
        $all_service_stmt->execute();
        $all_service_result = $all_service_stmt->get_result();
        while ($row = $all_service_result->fetch_assoc()) {
            $all_services[] = $row;
        }
        $all_service_stmt->close();
    }
    // Fetch prerequisites for all services
    $prereq_stmt = $conn->prepare("SELECT service_id, required_service_id, validity_period_days FROM tbl_service_prerequisites");
    if ($prereq_stmt) {
        $prereq_stmt->execute();
        $prereq_result = $prereq_stmt->get_result();
        while ($row = $prereq_result->fetch_assoc()) {
            if (!isset($service_prerequisites[$row['service_id']])) {
                $service_prerequisites[$row['service_id']] = [];
            }
            $service_prerequisites[$row['service_id']][] = $row;
        }
        $prereq_stmt->close();
    }
    // Fetch all specializations
    $specializations = [];
    $table_check = $conn->query("SHOW TABLES LIKE 'tbl_specializations'");
    if ($table_check && $table_check->num_rows > 0) {
        $spec_stmt = $conn->prepare("SELECT specialization_id, name FROM tbl_specializations WHERE is_active = 1 ORDER BY name");
        if ($spec_stmt) {
            $spec_stmt->execute();
            $spec_result = $spec_stmt->get_result();
            while ($row = $spec_result->fetch_assoc()) {
                $specializations[] = $row;
            }
            $spec_stmt->close();
        }
    }
    // Fetch service specializations for editing
    $service_specializations = [];
    if ($editing_service_id > 0) {
        $table_check = $conn->query("SHOW TABLES LIKE 'tbl_service_specializations'");
        if ($table_check && $table_check->num_rows > 0) {
            $service_spec_stmt = $conn->prepare("SELECT specialization_id FROM tbl_service_specializations WHERE service_id = ?");
            $service_spec_stmt->bind_param("i", $editing_service_id);
            $service_spec_stmt->execute();
            $service_spec_result = $service_spec_stmt->get_result();
            while ($row = $service_spec_result->fetch_assoc()) {
                $service_specializations[] = $row['specialization_id'];
            }
            $service_spec_stmt->close();
        }
    }
    // Fetch service being edited
    if ($editing_service_id > 0) {
        $edit_stmt = $conn->prepare("SELECT service_id, service_name, base_price, default_duration_minutes, is_active, maintenance_period_months, maintenance_interval FROM tbl_service_catalog WHERE service_id = ?");
        $edit_stmt->bind_param("i", $editing_service_id);
        $edit_stmt->execute();
        $edit_result = $edit_stmt->get_result();
        if ($edit_result->num_rows === 1) {
            $editing_service = $edit_result->fetch_assoc();
        }
        $edit_stmt->close();
    }
    $conn->close();
} catch (Exception $e) {
    error_log("Error fetching services: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle service deletion
    if (isset($_POST['action']) && $_POST['action'] === 'delete_service') {
        $service_id = intval($_POST['service_id'] ?? 0);
        if ($service_id > 0) {
            try {
                $conn = getDBConnection();
                // Check if service is used in appointments
                $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_appointments WHERE service_id = ?");
                $check_stmt->bind_param("i", $service_id);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $check_data = $check_result->fetch_assoc();
                $check_stmt->close();
                
                if ($check_data['count'] > 0) {
                    $errors[] = "Cannot delete service. It is being used in " . $check_data['count'] . " appointment(s).";
                    $conn->close();
                } else {
                    // Delete prerequisites first
                    $prereq_stmt = $conn->prepare("DELETE FROM tbl_service_prerequisites WHERE service_id = ? OR required_service_id = ?");
                    $prereq_stmt->bind_param("ii", $service_id, $service_id);
                    $prereq_stmt->execute();
                    $prereq_stmt->close();
                    
                    // Delete the service
                    $delete_stmt = $conn->prepare("DELETE FROM tbl_service_catalog WHERE service_id = ?");
                    $delete_stmt->bind_param("i", $service_id);
                    if ($delete_stmt->execute()) {
                        $delete_stmt->close();
                        $conn->close();
                        // Clear output buffer and redirect
                        ob_end_clean();
                        // Store success message in session and redirect
                        $_SESSION['service_success_message'] = "Service deleted successfully.";
                        header("Location: add_service.php");
                        exit();
                    } else {
                        $errors[] = "Failed to delete service: " . $delete_stmt->error;
                        $delete_stmt->close();
                        $conn->close();
                    }
                }
            } catch (Exception $e) {
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'add_service') {
        // Handle service creation
        $name = trim($_POST['service_name'] ?? '');
        $price = trim($_POST['base_price'] ?? '');
        $duration = trim($_POST['default_duration_minutes'] ?? '');
        $is_active = 1; // Services are active by default
        $prerequisites = isset($_POST['prerequisites']) ? $_POST['prerequisites'] : [];
        $validity_period = isset($_POST['validity_period_days']) && !empty($_POST['validity_period_days']) ? intval($_POST['validity_period_days']) : 30;
        $specializations = isset($_POST['specializations']) ? $_POST['specializations'] : [];
        $maintenance_period = isset($_POST['maintenance_period_months']) && !empty($_POST['maintenance_period_months']) ? intval($_POST['maintenance_period_months']) : null;
        $maintenance_interval = isset($_POST['maintenance_interval']) && !empty($_POST['maintenance_interval']) ? trim($_POST['maintenance_interval']) : null;

        if ($name === '') {
            $errors[] = "Service name is required.";
        }
        if (!is_numeric($price) || $price < 0) {
            $errors[] = "Base price must be a non-negative number.";
        }
        if (!ctype_digit($duration) || intval($duration) <= 0) {
            $errors[] = "Duration must be a positive whole number (minutes).";
        }
        if ($validity_period < 1) {
            $errors[] = "Validity period must be at least 1 day.";
        }
        if (empty($specializations) || !is_array($specializations) || count($specializations) === 0) {
            $errors[] = "At least one specialization must be selected.";
        }
        if ($maintenance_period !== null && $maintenance_period < 1) {
            $errors[] = "Maintenance period must be at least 1 month if specified.";
        }
        if ($maintenance_period !== null && empty($maintenance_interval)) {
            $errors[] = "Maintenance interval is required when maintenance period is specified.";
        }
        if (empty($maintenance_period) && !empty($maintenance_interval)) {
            $errors[] = "Maintenance period is required when maintenance interval is specified.";
        }

        if (empty($errors)) {
            try {
                $conn = getDBConnection();
                $stmt = $conn->prepare("INSERT INTO tbl_service_catalog (service_name, base_price, default_duration_minutes, is_active, maintenance_period_months, maintenance_interval) VALUES (?, ?, ?, ?, ?, ?)");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $duration_int = intval($duration);
                $stmt->bind_param("sdiiis", $name, $price, $duration_int, $is_active, $maintenance_period, $maintenance_interval);
                if ($stmt->execute()) {
                    $service_id = $conn->insert_id;
                    $stmt->close();
                    
                    // Insert prerequisites if any selected
                    if (!empty($prerequisites) && is_array($prerequisites)) {
                        $prereq_stmt = $conn->prepare("INSERT INTO tbl_service_prerequisites (service_id, required_service_id, is_mandatory, validity_period_days) VALUES (?, ?, 1, ?)");
                        if ($prereq_stmt) {
                            foreach ($prerequisites as $required_service_id) {
                                $req_id = intval($required_service_id);
                                if ($req_id > 0 && $req_id != $service_id) { // Prevent self-reference
                                    $prereq_stmt->bind_param("iii", $service_id, $req_id, $validity_period);
                                    $prereq_stmt->execute();
                                }
                            }
                            $prereq_stmt->close();
                        }
                    }
                    
                    // Insert specializations if any selected
                    if (!empty($specializations) && is_array($specializations)) {
                        $table_check = $conn->query("SHOW TABLES LIKE 'tbl_service_specializations'");
                        if ($table_check && $table_check->num_rows > 0) {
                            $spec_stmt = $conn->prepare("INSERT INTO tbl_service_specializations (service_id, specialization_id) VALUES (?, ?)");
                            if ($spec_stmt) {
                                foreach ($specializations as $specialization_id) {
                                    $spec_id = intval($specialization_id);
                                    if ($spec_id > 0) {
                                        $spec_stmt->bind_param("ii", $service_id, $spec_id);
                                        $spec_stmt->execute();
                                    }
                                }
                                $spec_stmt->close();
                            }
                        }
                    }
                    
                    $success_msg = "Service added successfully" . (!empty($prerequisites) ? " with " . count($prerequisites) . " prerequisite(s)" : "") . ".";
                    // Close connection before redirect
                    $conn->close();
                    // Clear output buffer and redirect
                    ob_end_clean();
                    // Store success message in session and redirect
                    $_SESSION['service_success_message'] = $success_msg;
                    header("Location: add_service.php");
                    exit();
                } else {
                    $errors[] = "Failed to add service: " . $stmt->error;
                }
                if (isset($stmt)) $stmt->close();
                $conn->close();
            } catch (Exception $e) {
                $errors[] = "Database error: " . $e->getMessage();
                if (isset($conn)) {
                    $conn->close();
                }
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update_service') {
        // Handle service update
        $service_id = intval($_POST['service_id'] ?? 0);
        $name = trim($_POST['service_name'] ?? '');
        $price = trim($_POST['base_price'] ?? '');
        $duration = trim($_POST['default_duration_minutes'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $prerequisites = isset($_POST['prerequisites']) ? $_POST['prerequisites'] : [];
        $validity_period = isset($_POST['validity_period_days']) && !empty($_POST['validity_period_days']) ? intval($_POST['validity_period_days']) : 30;
        $specializations = isset($_POST['specializations']) ? $_POST['specializations'] : [];
        $maintenance_period = isset($_POST['maintenance_period_months']) && !empty($_POST['maintenance_period_months']) ? intval($_POST['maintenance_period_months']) : null;
        $maintenance_interval = isset($_POST['maintenance_interval']) && !empty($_POST['maintenance_interval']) ? trim($_POST['maintenance_interval']) : null;

        if ($service_id <= 0) {
            $errors[] = "Invalid service ID.";
        }
        if ($name === '') {
            $errors[] = "Service name is required.";
        }
        if (!is_numeric($price) || $price < 0) {
            $errors[] = "Base price must be a non-negative number.";
        }
        if (!ctype_digit($duration) || intval($duration) <= 0) {
            $errors[] = "Duration must be a positive whole number (minutes).";
        }
        if ($validity_period < 1) {
            $errors[] = "Validity period must be at least 1 day.";
        }
        if (empty($specializations) || !is_array($specializations) || count($specializations) === 0) {
            $errors[] = "At least one specialization must be selected.";
        }
        if ($maintenance_period !== null && $maintenance_period < 1) {
            $errors[] = "Maintenance period must be at least 1 month if specified.";
        }
        if ($maintenance_period !== null && empty($maintenance_interval)) {
            $errors[] = "Maintenance interval is required when maintenance period is specified.";
        }
        if (empty($maintenance_period) && !empty($maintenance_interval)) {
            $errors[] = "Maintenance period is required when maintenance interval is specified.";
        }

        if (empty($errors)) {
            try {
                $conn = getDBConnection();
                // Update the service
                $stmt = $conn->prepare("UPDATE tbl_service_catalog SET service_name = ?, base_price = ?, default_duration_minutes = ?, is_active = ?, maintenance_period_months = ?, maintenance_interval = ? WHERE service_id = ?");
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                $duration_int = intval($duration);
                $stmt->bind_param("sdiiisi", $name, $price, $duration_int, $is_active, $maintenance_period, $maintenance_interval, $service_id);
                if ($stmt->execute()) {
                    $stmt->close();
                    
                    // Delete existing prerequisites
                    $delete_prereq_stmt = $conn->prepare("DELETE FROM tbl_service_prerequisites WHERE service_id = ?");
                    $delete_prereq_stmt->bind_param("i", $service_id);
                    $delete_prereq_stmt->execute();
                    $delete_prereq_stmt->close();
                    
                    // Insert new prerequisites if any selected
                    if (!empty($prerequisites) && is_array($prerequisites)) {
                        $prereq_stmt = $conn->prepare("INSERT INTO tbl_service_prerequisites (service_id, required_service_id, is_mandatory, validity_period_days) VALUES (?, ?, 1, ?)");
                        if ($prereq_stmt) {
                            foreach ($prerequisites as $required_service_id) {
                                $req_id = intval($required_service_id);
                                if ($req_id > 0 && $req_id != $service_id) { // Prevent self-reference
                                    $prereq_stmt->bind_param("iii", $service_id, $req_id, $validity_period);
                                    $prereq_stmt->execute();
                                }
                            }
                            $prereq_stmt->close();
                        }
                    }
                    
                    // Delete existing service specializations
                    $table_check = $conn->query("SHOW TABLES LIKE 'tbl_service_specializations'");
                    if ($table_check && $table_check->num_rows > 0) {
                        $delete_spec_stmt = $conn->prepare("DELETE FROM tbl_service_specializations WHERE service_id = ?");
                        $delete_spec_stmt->bind_param("i", $service_id);
                        $delete_spec_stmt->execute();
                        $delete_spec_stmt->close();
                        
                        // Insert new specializations if any selected
                        if (!empty($specializations) && is_array($specializations)) {
                            $spec_stmt = $conn->prepare("INSERT INTO tbl_service_specializations (service_id, specialization_id) VALUES (?, ?)");
                            if ($spec_stmt) {
                                foreach ($specializations as $specialization_id) {
                                    $spec_id = intval($specialization_id);
                                    if ($spec_id > 0) {
                                        $spec_stmt->bind_param("ii", $service_id, $spec_id);
                                        $spec_stmt->execute();
                                    }
                                }
                                $spec_stmt->close();
                            }
                        }
                    }
                    
                    $success_msg = "Service updated successfully" . (!empty($prerequisites) ? " with " . count($prerequisites) . " prerequisite(s)" : "") . ".";
                    // Close connection before redirect
                    $conn->close();
                    // Clear output buffer and redirect
                    ob_end_clean();
                    // Store success message in session and redirect to remove edit parameter
                    $_SESSION['service_success_message'] = $success_msg;
                    header("Location: add_service.php");
                    exit();
                } else {
                    $errors[] = "Failed to update service: " . $stmt->error;
                }
                if (isset($stmt)) $stmt->close();
                $conn->close();
            } catch (Exception $e) {
                $errors[] = "Database error: " . $e->getMessage();
                if (isset($conn)) {
                    $conn->close();
                }
            }
        }
    }
}

// End output buffering if not already ended (for normal page loads)
if (ob_get_level() > 0 && !headers_sent()) {
    ob_end_flush();
}
?>
<?php
$page_title = 'Service Management';
$user_name  = $admin_name;
include ROOT_PATH . '/views/layout/header.php';
?>
<div class="flex min-h-screen">
    <?php include ROOT_PATH . '/views/layout/sidebar_staff.php'; ?>
    <div class="flex-1 flex flex-col lg:ml-64">
        <?php include ROOT_PATH . 
'
/views/layout/topbar.php
'
; ?>
<main class="flex-grow w-full max-w-7xl mx-auto px-4 md:px-8 py-8">
<?php if (!empty($success)): ?>
<div class="max-w-6xl mx-auto mb-4">
<div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-400">
<p class="text-sm font-medium"><?php echo htmlspecialchars($success); ?></p>
</div>
</div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<div class="max-w-6xl mx-auto mb-4">
<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg dark:bg-red-900/20 dark:border-red-800 dark:text-red-400">
<?php foreach ($errors as $err): ?>
<p class="text-sm font-medium"><?php echo htmlspecialchars($err); ?></p>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
<div class="max-w-6xl mx-auto space-y-6">
<!-- Existing Services List -->
<div class="bg-white dark:bg-[#1a2634] rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
<h2 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Existing Services</h2>
<?php if (empty($all_services)): ?>
<p class="text-sm text-slate-500 dark:text-slate-400">No services found.</p>
<?php else: ?>
<div class="overflow-x-auto">
<table class="w-full text-sm">
<thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700">
<tr>
<th class="text-left py-3 px-4 font-semibold text-slate-700 dark:text-slate-300">Service Name</th>
<th class="text-left py-3 px-4 font-semibold text-slate-700 dark:text-slate-300">Price</th>
<th class="text-left py-3 px-4 font-semibold text-slate-700 dark:text-slate-300">Duration</th>
<th class="text-center py-3 px-4 font-semibold text-slate-700 dark:text-slate-300">Status</th>
<th class="text-center py-3 px-4 font-semibold text-slate-700 dark:text-slate-300">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-200 dark:divide-slate-700">
<?php foreach ($all_services as $service): ?>
<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
<td class="py-3 px-4 font-medium text-slate-900 dark:text-white"><?php echo htmlspecialchars($service['service_name']); ?></td>
<td class="py-3 px-4 text-slate-700 dark:text-slate-300">â‚±<?php echo number_format($service['base_price'], 2); ?></td>
<td class="py-3 px-4 text-slate-700 dark:text-slate-300"><?php echo $service['default_duration_minutes']; ?> min</td>
<td class="py-3 px-4 text-center">
<?php if ($service['is_active']): ?>
<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">Active</span>
<?php else: ?>
<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400">Inactive</span>
<?php endif; ?>
</td>
<td class="py-3 px-4 text-center">
<div class="flex items-center justify-center gap-2">
<a href="add_service.php?edit=<?php echo $service['service_id']; ?>" class="text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors" title="Edit Service">
<span class="material-symbols-outlined text-base">edit</span>
</a>
<form method="POST" action="add_service.php" onsubmit="return confirm('Are you sure you want to delete this service? This action cannot be undone.');" class="inline">
<input type="hidden" name="action" value="delete_service">
<input type="hidden" name="service_id" value="<?php echo $service['service_id']; ?>">
<button type="submit" class="text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition-colors" title="Delete Service">
<span class="material-symbols-outlined text-base">delete</span>
</button>
</form>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>

<!-- Add/Edit Service Form -->
<div class="bg-white dark:bg-[#1a2634] rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-6">
<h2 class="text-lg font-bold text-slate-900 dark:text-white mb-6"><?php echo $editing_service ? 'Edit Service' : 'Add New Service'; ?></h2>
<?php if ($editing_service): ?>
<div class="mb-4">
<a href="add_service.php" class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 inline-flex items-center gap-1">
<span class="material-symbols-outlined text-base">arrow_back</span>
Back to Service List
</a>
</div>
<?php endif; ?>
<form method="POST" action="add_service.php" class="space-y-6">
<input type="hidden" name="action" value="<?php echo $editing_service ? 'update_service' : 'add_service'; ?>">
<?php if ($editing_service): ?>
<input type="hidden" name="service_id" value="<?php echo $editing_service['service_id']; ?>">
<?php endif; ?>
<div>
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Service Name</label>
<input type="text" name="service_name" value="<?php echo htmlspecialchars($editing_service ? $editing_service['service_name'] : ($_POST['service_name'] ?? '')); ?>" required class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="e.g., General Checkup" />
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
<div>
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Base Price (â‚±)</label>
<input type="number" step="0.01" min="0" name="base_price" value="<?php echo htmlspecialchars($editing_service ? $editing_service['base_price'] : ($_POST['base_price'] ?? '')); ?>" required class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="0.00" />
</div>
<div>
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Default Duration (minutes)</label>
<input type="number" min="1" name="default_duration_minutes" value="<?php echo htmlspecialchars($editing_service ? $editing_service['default_duration_minutes'] : ($_POST['default_duration_minutes'] ?? '')); ?>" required class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="60" />
</div>
</div>
<?php if ($editing_service): ?>
<div class="flex items-center gap-2">
<input type="checkbox" id="is_active" name="is_active" class="rounded border-slate-200 dark:border-slate-600 text-primary focus:ring-primary" <?php echo $editing_service['is_active'] ? 'checked' : ''; ?>>
<label for="is_active" class="text-sm text-slate-700 dark:text-slate-300">Service is active and available for booking</label>
</div>
<?php else: ?>
<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-3">
<p class="text-sm text-blue-700 dark:text-blue-400">
<span class="material-symbols-outlined text-base align-middle mr-1">info</span>
<span class="align-middle">New services are automatically set as active and available for booking. You can change this later if needed.</span>
</p>
</div>
<?php endif; ?>
<div class="pt-4 border-t border-slate-200 dark:border-slate-700">
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">Required Specializations <span class="text-red-500">*</span></label>
<p class="text-xs text-slate-500 dark:text-slate-400 mb-3">Select which specializations are required for staff to perform this service. At least one specialization must be selected.</p>
<?php if (!empty($specializations)): ?>
<div class="space-y-2 max-h-48 overflow-y-auto border border-slate-200 dark:border-slate-600 rounded-lg p-3 bg-slate-50 dark:bg-slate-800/50">
<?php 
$current_specs = [];
if ($editing_service && isset($service_specializations)) {
    $current_specs = $service_specializations;
}
foreach ($specializations as $spec): 
    $is_checked = false;
    if ($editing_service) {
        $is_checked = in_array($spec['specialization_id'], $current_specs);
    } else {
        $is_checked = (isset($_POST['specializations']) && is_array($_POST['specializations']) && in_array($spec['specialization_id'], $_POST['specializations']));
    }
?>
<div class="flex items-center gap-2">
<input type="checkbox" id="spec_<?php echo $spec['specialization_id']; ?>" name="specializations[]" value="<?php echo $spec['specialization_id']; ?>" class="rounded border-slate-200 dark:border-slate-600 text-primary focus:ring-primary" <?php echo $is_checked ? 'checked' : ''; ?>>
<label for="spec_<?php echo $spec['specialization_id']; ?>" class="text-sm text-slate-700 dark:text-slate-300 cursor-pointer"><?php echo htmlspecialchars($spec['name']); ?></label>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<p class="text-sm text-slate-500 dark:text-slate-400 italic">No specializations available. Please add specializations first.</p>
<?php endif; ?>
</div>
<div class="pt-4 border-t border-slate-200 dark:border-slate-700">
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-3">Prerequisites (Optional)</label>
<p class="text-xs text-slate-500 dark:text-slate-400 mb-3">Select which services must be completed before this service can be booked. Patients will need to complete these prerequisite services first.</p>
<?php if (!empty($existing_services)): ?>
<div class="space-y-2 max-h-48 overflow-y-auto border border-slate-200 dark:border-slate-600 rounded-lg p-3 bg-slate-50 dark:bg-slate-800/50">
<?php 
$current_prereqs = [];
if ($editing_service && isset($service_prerequisites[$editing_service['service_id']])) {
    foreach ($service_prerequisites[$editing_service['service_id']] as $prereq) {
        $current_prereqs[] = $prereq['required_service_id'];
    }
}
$current_validity = 30;
if ($editing_service && isset($service_prerequisites[$editing_service['service_id']]) && !empty($service_prerequisites[$editing_service['service_id']])) {
    $current_validity = $service_prerequisites[$editing_service['service_id']][0]['validity_period_days'];
}
foreach ($existing_services as $service): 
    // Skip the service being edited from prerequisites list
    if ($editing_service && $service['service_id'] == $editing_service['service_id']) continue;
    $is_checked = false;
    if ($editing_service) {
        $is_checked = in_array($service['service_id'], $current_prereqs);
    } else {
        $is_checked = (isset($_POST['prerequisites']) && is_array($_POST['prerequisites']) && in_array($service['service_id'], $_POST['prerequisites']));
    }
?>
<div class="flex items-center gap-2">
<input type="checkbox" id="prereq_<?php echo $service['service_id']; ?>" name="prerequisites[]" value="<?php echo $service['service_id']; ?>" class="rounded border-slate-200 dark:border-slate-600 text-primary focus:ring-primary" <?php echo $is_checked ? 'checked' : ''; ?>>
<label for="prereq_<?php echo $service['service_id']; ?>" class="text-sm text-slate-700 dark:text-slate-300 cursor-pointer"><?php echo htmlspecialchars($service['service_name']); ?></label>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<p class="text-sm text-slate-500 dark:text-slate-400 italic">No existing services available. Prerequisites can be added after creating more services.</p>
<?php endif; ?>
<div class="mt-4">
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Prerequisite Validity Period (days)</label>
<input type="number" min="1" name="validity_period_days" value="<?php echo htmlspecialchars($editing_service ? $current_validity : ($_POST['validity_period_days'] ?? '30')); ?>" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="30" />
<p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Number of days a prerequisite appointment remains valid. Default: 30 days.</p>
</div>
</div>

<!-- Treatment Plan Section -->
<div class="pt-4 border-t border-slate-200 dark:border-slate-700">
<h3 class="text-base font-semibold text-slate-700 dark:text-slate-300 mb-3">Treatment Plan</h3>
<p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Configure maintenance requirements for this service. Leave blank if no maintenance is required.</p>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
<div>
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Maintenance Period (months)</label>
<input type="number" min="1" name="maintenance_period_months" value="<?php echo htmlspecialchars($editing_service ? ($editing_service['maintenance_period_months'] ?? '') : ($_POST['maintenance_period_months'] ?? '')); ?>" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="e.g., 6" />
<p class="text-xs text-slate-500 dark:text-slate-400 mt-1">How long the treatment plan lasts (in months). Leave blank if no maintenance required.</p>
</div>
<div>
<label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">Maintenance Interval</label>
<select name="maintenance_interval" class="w-full rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
<option value="">-- No Maintenance --</option>
<option value="weekly" <?php echo ($editing_service && ($editing_service['maintenance_interval'] ?? '') === 'weekly') || (isset($_POST['maintenance_interval']) && $_POST['maintenance_interval'] === 'weekly') ? 'selected' : ''; ?>>Every Week</option>
<option value="biweekly" <?php echo ($editing_service && ($editing_service['maintenance_interval'] ?? '') === 'biweekly') || (isset($_POST['maintenance_interval']) && $_POST['maintenance_interval'] === 'biweekly') ? 'selected' : ''; ?>>Every 2 Weeks</option>
<option value="monthly" <?php echo ($editing_service && ($editing_service['maintenance_interval'] ?? '') === 'monthly') || (isset($_POST['maintenance_interval']) && $_POST['maintenance_interval'] === 'monthly') ? 'selected' : ''; ?>>Every Month</option>
<option value="bimonthly" <?php echo ($editing_service && ($editing_service['maintenance_interval'] ?? '') === 'bimonthly') || (isset($_POST['maintenance_interval']) && $_POST['maintenance_interval'] === 'bimonthly') ? 'selected' : ''; ?>>Every 2 Months</option>
<option value="quarterly" <?php echo ($editing_service && ($editing_service['maintenance_interval'] ?? '') === 'quarterly') || (isset($_POST['maintenance_interval']) && $_POST['maintenance_interval'] === 'quarterly') ? 'selected' : ''; ?>>Every 3 Months (Quarterly)</option>
<option value="semiannually" <?php echo ($editing_service && ($editing_service['maintenance_interval'] ?? '') === 'semiannually') || (isset($_POST['maintenance_interval']) && $_POST['maintenance_interval'] === 'semiannually') ? 'selected' : ''; ?>>Every 6 Months (Semi-annually)</option>
<option value="annually" <?php echo ($editing_service && ($editing_service['maintenance_interval'] ?? '') === 'annually') || (isset($_POST['maintenance_interval']) && $_POST['maintenance_interval'] === 'annually') ? 'selected' : ''; ?>>Every Year (Annually)</option>
</select>
<p class="text-xs text-slate-500 dark:text-slate-400 mt-1">How often patients should return for maintenance visits.</p>
</div>
</div>
</div>

<div class="flex justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
<?php if ($editing_service): ?>
<a href="add_service.php" class="px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors text-sm font-medium">Cancel</a>
<button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white font-semibold hover:bg-blue-700 transition-colors text-sm">Update Service</button>
<?php else: ?>
<a href="admin_dashboard.php" class="px-4 py-2 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors text-sm font-medium">Cancel</a>
<button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white font-semibold hover:bg-blue-700 transition-colors text-sm">Add Service</button>
<?php endif; ?>
</div>
</form>
</div>
</div>
</main>
        </main><!-- /.main -->
    </div><!-- /flex-1 -->
</div><!-- /flex min-h-screen -->

<?php include ROOT_PATH . '/views/layout/footer.php'; ?>
