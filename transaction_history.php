<?php
require_once 'config/db.php';
require_once 'config/security.php';

// Set security headers and check login
requireLogin();

// Allow all staff roles (Admin, Dentist, Receptionist)
$user_type = $_SESSION['user_type'] ?? 'patient';
$user_role = $_SESSION['user_role'] ?? '';

if ($user_type !== 'staff' || ($user_role !== 'Admin' && $user_role !== 'Dentist' && $user_role !== 'Receptionist')) {
    header("Location: landing.php");
    exit();
}

// Function to check if staff profile is complete
function isStaffProfileComplete($staff_id) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT first_name, last_name, email, phone_number FROM tbl_staff WHERE staff_id = ? LIMIT 1");
        $stmt->bind_param("i", $staff_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $stmt->close();
            $conn->close();
            return false;
        }
        
        $staff = $result->fetch_assoc();
        $stmt->close();
        
        if (empty($staff['first_name']) || empty($staff['last_name']) || empty($staff['email']) || empty($staff['phone_number'])) {
            $conn->close();
            return false;
        }
        
        $edu_stmt = $conn->prepare("SELECT COUNT(*) as count FROM tbl_staff_education WHERE staff_id = ?");
        $edu_stmt->bind_param("i", $staff_id);
        $edu_stmt->execute();
        $edu_result = $edu_stmt->get_result();
        $edu_data = $edu_result->fetch_assoc();
        $edu_stmt->close();
        $conn->close();
        
        return $edu_data['count'] > 0;
    } catch (Exception $e) {
        error_log("Error checking staff profile completion: " . $e->getMessage());
        return false;
    }
}

// Only check profile completion for non-admin staff (Receptionist, Dentist)
// Admins can access transaction history even if profile is incomplete
if ($user_role !== 'Admin') {
    $profile_complete = isStaffProfileComplete($_SESSION['user_id']);
    
    if (!$profile_complete) {
        header("Location: profile.php");
        exit();
    }
}

$conn = getDBConnection();
$user_info = null;

try {
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
} catch (Exception $e) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$admin_name = $user_info['first_name'] . ' ' . $user_info['last_name'];
$admin_role = $user_info['role'] ?? 'Receptionist';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Fetch payments
$payments = [];
$total_payments = 0;

try {
    // Get total count
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM tbl_payments ORDER BY payment_date DESC, created_at DESC");
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_payments = $count_result->fetch_assoc()['total'];
    $count_stmt->close();
    
    // Get paginated payments
    $payments_stmt = $conn->prepare("
        SELECT 
            p.payment_id,
            p.payment_type,
            p.amount,
            p.payment_method,
            p.payment_status,
            p.payment_date,
            p.transaction_id,
            p.payment_reference_number,
            p.created_at,
            pt.first_name as patient_first_name,
            pt.last_name as patient_last_name,
            pt.email as patient_email,
            a.appointment_id,
            a.scheduled_datetime
        FROM tbl_payments p
        LEFT JOIN tbl_patients pt ON p.patient_id = pt.patient_id
        LEFT JOIN tbl_appointments a ON p.appointment_id = a.appointment_id
        ORDER BY p.payment_date DESC, p.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $payments_stmt->bind_param("ii", $per_page, $offset);
    $payments_stmt->execute();
    $payments_result = $payments_stmt->get_result();
    
    while ($row = $payments_result->fetch_assoc()) {
        $payments[] = $row;
    }
    $payments_stmt->close();
} catch (Exception $e) {
    error_log("Error fetching payments: " . $e->getMessage());
}

$total_pages = ceil($total_payments / $per_page);

$conn->close();
?>
<?php
$page_title = 'Transaction History';
$user_name  = $user_name;
include ROOT_PATH . '/views/layout/header.php';
?>
<div class="flex min-h-screen">
    <?php include ROOT_PATH . '/views/layout/sidebar_patient.php'; ?>
    <div class="flex-1 flex flex-col lg:ml-64">
        <?php include ROOT_PATH . 
'
/views/layout/topbar.php
'
; ?>
<main class="flex-grow w-full max-w-7xl mx-auto px-4 md:px-8 py-8">
<div class="max-w-7xl mx-auto space-y-8">
<div class="bg-white dark:bg-[#1a2634] rounded-xl border border-[#e7ecf3] dark:border-slate-700 shadow-sm p-6">
<h2 class="text-xl font-bold text-[#0d131b] dark:text-white mb-6">All Payments</h2>
<?php if (empty($payments)): ?>
<div class="text-center py-12">
<p class="text-[#4c6c9a] dark:text-slate-400">No payments found.</p>
</div>
<?php else: ?>
<div class="overflow-x-auto">
<table class="w-full">
<thead>
<tr class="border-b border-[#e7ecf3] dark:border-slate-700">
<th class="text-left py-3 px-4 text-sm font-semibold text-[#4c6c9a] dark:text-slate-400">Date</th>
<th class="text-left py-3 px-4 text-sm font-semibold text-[#4c6c9a] dark:text-slate-400">Patient</th>
<th class="text-left py-3 px-4 text-sm font-semibold text-[#4c6c9a] dark:text-slate-400">Type</th>
<th class="text-right py-3 px-4 text-sm font-semibold text-[#4c6c9a] dark:text-slate-400">Amount</th>
<th class="text-left py-3 px-4 text-sm font-semibold text-[#4c6c9a] dark:text-slate-400">Method</th>
<th class="text-left py-3 px-4 text-sm font-semibold text-[#4c6c9a] dark:text-slate-400">Status</th>
<th class="text-left py-3 px-4 text-sm font-semibold text-[#4c6c9a] dark:text-slate-400">Reference</th>
</tr>
</thead>
<tbody>
<?php foreach ($payments as $payment): ?>
<tr class="border-b border-[#e7ecf3] dark:border-slate-700 hover:bg-[#f0f4f8] dark:hover:bg-slate-800/50">
<td class="py-3 px-4 text-sm text-[#0d131b] dark:text-white">
<?php 
if ($payment['payment_date']) {
    echo date('M d, Y H:i', strtotime($payment['payment_date']));
} else {
    echo date('M d, Y H:i', strtotime($payment['created_at']));
}
?>
</td>
<td class="py-3 px-4 text-sm text-[#0d131b] dark:text-white">
<?php 
if ($payment['patient_first_name']) {
    echo htmlspecialchars($payment['patient_first_name'] . ' ' . $payment['patient_last_name']);
} else {
    echo 'N/A';
}
?>
</td>
<td class="py-3 px-4 text-sm text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($payment['payment_type'] ?? 'N/A'); ?></td>
<td class="py-3 px-4 text-sm text-right font-semibold text-[#0d131b] dark:text-white">â‚±<?php echo number_format($payment['amount'], 2); ?></td>
<td class="py-3 px-4 text-sm text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></td>
<td class="py-3 px-4">
<?php
$status = $payment['payment_status'] ?? 'Pending';
$status_colors = [
    'Completed' => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    'Pending' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
    'Pending Verification' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    'Failed' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    'Cancelled' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
    'Refunded' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400'
];
$status_class = $status_colors[$status] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400';
?>
<span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $status_class; ?>"><?php echo htmlspecialchars($status); ?></span>
</td>
<td class="py-3 px-4 text-sm text-[#4c6c9a] dark:text-slate-400">
<?php echo htmlspecialchars($payment['payment_reference_number'] ?? $payment['transaction_id'] ?? 'N/A'); ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php if ($total_pages > 1): ?>
<div class="flex items-center justify-between mt-6 pt-6 border-t border-[#e7ecf3] dark:border-slate-700">
<div class="text-sm text-[#4c6c9a] dark:text-slate-400">
Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $total_payments); ?> of <?php echo $total_payments; ?> payments
</div>
<div class="flex gap-2">
<?php if ($page > 1): ?>
<a href="?page=<?php echo $page - 1; ?>" class="px-4 py-2 rounded-lg border border-[#e7ecf3] dark:border-slate-700 text-sm font-medium text-[#4c6c9a] dark:text-slate-400 hover:bg-[#f0f4f8] dark:hover:bg-slate-800 transition-colors">Previous</a>
<?php endif; ?>
<?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
<a href="?page=<?php echo $i; ?>" class="px-4 py-2 rounded-lg <?php echo $i === $page ? 'bg-primary text-white' : 'border border-[#e7ecf3] dark:border-slate-700 text-sm font-medium text-[#4c6c9a] dark:text-slate-400 hover:bg-[#f0f4f8] dark:hover:bg-slate-800'; ?> transition-colors"><?php echo $i; ?></a>
<?php endfor; ?>
<?php if ($page < $total_pages): ?>
<a href="?page=<?php echo $page + 1; ?>" class="px-4 py-2 rounded-lg border border-[#e7ecf3] dark:border-slate-700 text-sm font-medium text-[#4c6c9a] dark:text-slate-400 hover:bg-[#f0f4f8] dark:hover:bg-slate-800 transition-colors">Next</a>
<?php endif; ?>
</div>
</div>
<?php endif; ?>
<?php endif; ?>
</div>
</div>
</main>
        </main><!-- /.main -->
    </div><!-- /flex-1 -->
</div><!-- /flex min-h-screen -->
<?php include ROOT_PATH . '/views/layout/footer.php'; ?>
