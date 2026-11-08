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

// Only allow patients to access this page
if ($user_type !== 'patient') {
    // Not authorized - redirect to appropriate dashboard
    if ($user_type === 'staff' && ($user_role === 'Admin' || $user_role === 'Dentist')) {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: landing.php");
    }
    exit();
}

try {
    // Fetch patient data from database using prepared statement
    $stmt = $conn->prepare("SELECT patient_id, first_name, last_name, email, primary_phone FROM tbl_patients WHERE patient_id = ? LIMIT 1");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user_info = $result->fetch_assoc();
    } else {
        // User not found in database - session invalid, logout
        session_destroy();
        header("Location: login.php");
        exit();
    }
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    // Database error - logout for security
    session_destroy();
    header("Location: login.php");
    exit();
}

// If user not found, redirect to login
if (!$user_info) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Set user variables from fresh database data
$user_name = $user_info['first_name'] . ' ' . $user_info['last_name'];
$user_first_name = $user_info['first_name'];
$user_email = $user_info['email'] ?? '';
$patient_id = $user_info['patient_id'];

// Initialize stats with placeholder values (backend calculations not implemented yet)
$stats = [
    'total_paid' => 0.00,
    'completed_appointments' => 0,
    'total_appointments' => 0,
    'total_transactions' => 0,
    'average_transaction' => 0.00
];

$transactions = [];

// Output cache prevention meta
outputCachePreventionMeta();
?>
<?php
$page_title = 'Customer Reports';
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
<div class="mb-8">
<h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">My Reports</h1>
<p class="mt-1 text-slate-500 dark:text-slate-400">Overview of your clinic visits, transactions, and account activity.</p>
</div>

<div class="mb-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
<div class="relative overflow-hidden rounded-xl border border-[#e7ecf3] dark:border-slate-700 bg-white dark:bg-[#1a2634] p-6 shadow-sm">
<div class="flex items-center justify-between">
<div>
<p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Amount Paid</p>
<p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">â‚±<?php echo number_format($stats['total_paid'], 2); ?></p>
</div>
<div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400">
<span class="material-symbols-outlined">payments</span>
</div>
</div>
<p class="mt-4 text-sm text-slate-500 dark:text-slate-400">All-time payments to the clinic</p>
</div>

<div class="relative overflow-hidden rounded-xl border border-[#e7ecf3] dark:border-slate-700 bg-white dark:bg-[#1a2634] p-6 shadow-sm">
<div class="flex items-center justify-between">
<div>
<p class="text-sm font-medium text-slate-500 dark:text-slate-400">Completed Appointments</p>
<p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white"><?php echo $stats['completed_appointments']; ?></p>
</div>
<div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400">
<span class="material-symbols-outlined">event_available</span>
</div>
</div>
<p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Out of <?php echo $stats['total_appointments']; ?> total appointments</p>
</div>

<div class="relative overflow-hidden rounded-xl border border-[#e7ecf3] dark:border-slate-700 bg-white dark:bg-[#1a2634] p-6 shadow-sm">
<div class="flex items-center justify-between">
<div>
<p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Transactions</p>
<p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white"><?php echo $stats['total_transactions']; ?></p>
</div>
<div class="flex h-12 w-12 items-center justify-center rounded-full bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400">
<span class="material-symbols-outlined">receipt_long</span>
</div>
</div>
<p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Paid invoices</p>
</div>

<div class="relative overflow-hidden rounded-xl border border-[#e7ecf3] dark:border-slate-700 bg-white dark:bg-[#1a2634] p-6 shadow-sm">
<div class="flex items-center justify-between">
<div>
<p class="text-sm font-medium text-slate-500 dark:text-slate-400">Average Transaction</p>
<p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">â‚±<?php echo number_format($stats['average_transaction'], 2); ?></p>
</div>
<div class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400">
<span class="material-symbols-outlined">trending_up</span>
</div>
</div>
<p class="mt-4 text-sm text-slate-500 dark:text-slate-400">Per transaction</p>
</div>
</div>

<div class="rounded-xl border border-[#e7ecf3] dark:border-slate-700 bg-white dark:bg-[#1a2634] shadow-sm">
<div class="border-b border-[#e7ecf3] dark:border-slate-700 px-6 pt-4">
<h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4">Transaction History</h2>
<div class="flex flex-col gap-4 md:flex-row md:items-center mb-4">
<div class="relative flex-1">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
<input id="searchInput" class="h-10 w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 pl-10 pr-4 text-sm text-slate-900 dark:text-white dark:placeholder-slate-400 focus:border-primary focus:ring-primary" placeholder="Search by service name or transaction ID..." type="text"/>
</div>
</div>
</div>

<div class="overflow-x-auto">
<table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
<thead class="bg-slate-50 dark:bg-slate-800/50">
<tr>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">Transaction ID</th>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">Date</th>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">Service</th>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">Amount</th>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">Payment Method</th>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">Status</th>
</tr>
</thead>
<tbody id="transactionsTableBody" class="divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-900">
<tr>
<td colspan="6" class="text-center py-8 text-slate-500 dark:text-slate-400">No completed transactions yet.</td>
</tr>
</tbody>
</table>
</div>
</div>
</main>
        </main><!-- /.main -->
    </div><!-- /flex-1 -->
</div><!-- /flex min-h-screen -->

<script>
// Logout confirmation
<?php include ROOT_PATH . '/views/layout/footer.php'; ?>
