<?php
require_once 'config/db.php';
require_once 'config/security.php';

// Set security headers and check login
requireLogin();

$user_type = $_SESSION['user_type'] ?? 'patient';
$user_role = $_SESSION['user_role'] ?? '';

// Only allow Dentist to access this page
if ($user_type !== 'staff' || $user_role !== 'Dentist') {
    header("Location: landing.php");
    exit();
}

$dentist_id = $_SESSION['user_id'];

// Fetch dentist info
$conn = getDBConnection();
$user_info = null;
try {
    $stmt = $conn->prepare("SELECT staff_id, first_name, last_name, email, role FROM tbl_staff WHERE staff_id = ? LIMIT 1");
    $stmt->bind_param("i", $dentist_id);
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

$user_name = $user_info['first_name'] . ' ' . $user_info['last_name'];

// Fetch patients assigned to this dentist
$patients = [];
try {
    $conn = getDBConnection();
    
    // Get distinct patients from appointments assigned to this dentist
    $patient_stmt = $conn->prepare("
        SELECT DISTINCT 
            p.patient_id, 
            p.first_name, 
            p.last_name, 
            p.email, 
            p.primary_phone, 
            p.dob,
            COUNT(DISTINCT a.appointment_id) as appointment_count
        FROM tbl_patients p
        INNER JOIN tbl_appointments a ON p.patient_id = a.patient_id
        WHERE a.dentist_id = ?
        GROUP BY p.patient_id
        ORDER BY p.last_name, p.first_name
    ");
    $patient_stmt->bind_param("i", $dentist_id);
    $patient_stmt->execute();
    $patient_result = $patient_stmt->get_result();
    while ($row = $patient_result->fetch_assoc()) {
        $patients[] = $row;
    }
    $patient_stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log("Error fetching patients: " . $e->getMessage());
}
?>
<?php
$page_title = 'My Patients';
$user_name  = $user_name;
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
<div class="max-w-7xl mx-auto">
<div class="mb-6">
<h2 class="text-2xl font-bold text-[#0d131b] dark:text-white">My Patients</h2>
<p class="text-sm text-[#4c6c9a] dark:text-slate-400 mt-1">Patients assigned to you</p>
</div>

<?php if (empty($patients)): ?>
<div class="rounded-xl border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 shadow-sm p-8 text-center">
<p class="text-[#4c6c9a] dark:text-slate-400">No patients assigned yet.</p>
</div>
<?php else: ?>
<div class="rounded-xl border border-[#e7ecf3] bg-white dark:bg-[#1a2634] dark:border-slate-700 shadow-sm overflow-hidden">
<div class="overflow-x-auto">
<table class="w-full">
<thead class="bg-slate-50 dark:bg-slate-800/50 border-b border-[#e7ecf3] dark:border-slate-700">
<tr>
<th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Patient Name</th>
<th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Email</th>
<th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Phone</th>
<th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Date of Birth</th>
<th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Appointments</th>
<th class="px-6 py-3 text-left text-xs font-bold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-[#e7ecf3] dark:divide-slate-700">
<?php foreach ($patients as $patient): ?>
<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
<td class="px-6 py-4 whitespace-nowrap">
<div class="text-sm font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></div>
</td>
<td class="px-6 py-4 whitespace-nowrap">
<div class="text-sm text-[#4c6c9a] dark:text-slate-400"><?php echo htmlspecialchars($patient['email'] ?? 'N/A'); ?></div>
</td>
<td class="px-6 py-4 whitespace-nowrap">
<div class="text-sm text-[#4c6c9a] dark:text-slate-400"><?php echo htmlspecialchars($patient['primary_phone'] ?? 'N/A'); ?></div>
</td>
<td class="px-6 py-4 whitespace-nowrap">
<div class="text-sm text-[#4c6c9a] dark:text-slate-400">
<?php 
if (!empty($patient['dob'])) {
    $dob = new DateTime($patient['dob']);
    echo $dob->format('M d, Y');
} else {
    echo 'N/A';
}
?>
</div>
</td>
<td class="px-6 py-4 whitespace-nowrap">
<div class="text-sm font-medium text-[#0d131b] dark:text-white"><?php echo $patient['appointment_count']; ?></div>
</td>
<td class="px-6 py-4 whitespace-nowrap">
<div class="flex items-center gap-3">
<a href="profile.php?patient_id=<?php echo $patient['patient_id']; ?>" class="inline-flex items-center gap-2 text-sm text-primary hover:text-primary-dark font-medium">
<span class="material-symbols-outlined text-[18px]">visibility</span>
View Profile
</a>
<a href="dentist_view_files.php?patient_id=<?php echo $patient['patient_id']; ?>" class="inline-flex items-center gap-2 text-sm text-primary hover:text-primary-dark font-medium">
<span class="material-symbols-outlined text-[18px]">folder</span>
View Files
</a>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>
</div>
</main>
        </main><!-- /.main -->
    </div><!-- /flex-1 -->
</div><!-- /flex min-h-screen -->
<?php include ROOT_PATH . '/views/layout/footer.php'; ?>
