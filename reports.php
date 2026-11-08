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

// Only allow Admin/Manager and Dentist roles to access reports page
if ($user_type !== 'staff' || ($user_role !== 'Manager' && $user_role !== 'Admin' && $user_role !== 'Dentist')) {
    // Not authorized - redirect to landing page
    header("Location: landing.php");
    exit();
}

try {
    // Fetch staff data from database using prepared statement
    $stmt = $conn->prepare("SELECT staff_id, first_name, last_name, email, role FROM tbl_staff WHERE staff_id = ? LIMIT 1");
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
$admin_name = $user_info['first_name'] . ' ' . $user_info['last_name'];
$admin_email = $user_info['email'] ?? '';
$admin_role = $user_info['role'] ?? 'Manager';

// Get time period from request (default: this_month)
$time_period = $_GET['period'] ?? 'this_month';

// Calculate date ranges based on time period
$now = new DateTime();
$start_date = clone $now;
$end_date = clone $now;

switch ($time_period) {
    case 'this_week':
        $start_date->modify('monday this week');
        $start_date->setTime(0, 0, 0);
        $end_date = clone $start_date;
        $end_date->modify('+6 days');
        break;
    case 'this_month':
        $start_date->modify('first day of this month');
        $start_date->setTime(0, 0, 0);
        $end_date->modify('last day of this month');
        break;
    case 'last_30_days':
        $start_date->modify('-30 days');
        $start_date->setTime(0, 0, 0);
        // keep end_date as today
        break;
    case 'last_quarter':
        $current_month = (int)$now->format('n');
        $quarter_start_month = (floor(($current_month - 1) / 3) * 3) + 1;
        $start_date->setDate($now->format('Y'), $quarter_start_month, 1);
        $start_date->modify('-3 months');
        $start_date->setTime(0, 0, 0);
        $end_date = clone $start_date;
        $end_date->modify('+3 months');
        $end_date->modify('-1 day');
        break;
    case 'this_year':
        $start_date->modify('first day of january this year');
        $start_date->setTime(0, 0, 0);
        $end_date->modify('last day of december this year');
        break;
    default:
        $start_date->modify('first day of this month');
        $start_date->setTime(0, 0, 0);
}

$end_date->setTime(23, 59, 59);
$start_date_str = $start_date->format('Y-m-d H:i:s');
$end_date_str = $end_date->format('Y-m-d H:i:s');

// Initialize stats
$stats = [
    'revenue' => 0,
    'daily_visits' => 0,
    'bookings' => 0,
    'new_customers' => 0,
    'revenue_prev' => 0,
    'visits_prev' => 0,
    'bookings_prev' => 0,
    'customers_prev' => 0
];

// Get active tab from URL (default to 'appointment')
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'appointment';

// Initialize filter parameters
$trans_search = isset($_GET['trans_search']) ? trim($_GET['trans_search']) : '';
$trans_status = isset($_GET['trans_status']) ? $_GET['trans_status'] : 'all';
$trans_staff = isset($_GET['trans_staff']) ? intval($_GET['trans_staff']) : 0;
$trans_service = isset($_GET['trans_service']) ? intval($_GET['trans_service']) : 0;
$trans_date_from = isset($_GET['trans_date_from']) ? $_GET['trans_date_from'] : '';
$trans_date_to = isset($_GET['trans_date_to']) ? $_GET['trans_date_to'] : '';

$apt_search = isset($_GET['apt_search']) ? trim($_GET['apt_search']) : '';
$apt_status = isset($_GET['apt_status']) ? $_GET['apt_status'] : 'all';
$apt_staff = isset($_GET['apt_staff']) ? intval($_GET['apt_staff']) : 0;
$apt_service = isset($_GET['apt_service']) ? intval($_GET['apt_service']) : 0;
$apt_date_from = isset($_GET['apt_date_from']) ? $_GET['apt_date_from'] : '';
$apt_date_to = isset($_GET['apt_date_to']) ? $_GET['apt_date_to'] : '';

// Initialize appointment pagination variables
$appointments_page = isset($_GET['apt_page']) ? max(1, intval($_GET['apt_page'])) : 1;
$appointments_per_page = 10;
$appointments_total = 0;
$appointments_total_pages = 0;
$appointments = [];

// Initialize transactions pagination variables
$transactions_page = isset($_GET['trans_page']) ? max(1, intval($_GET['trans_page'])) : 1;
$transactions_per_page = 10;
$transactions_total = 0;
$transactions_total_pages = 0;
$transactions = [];

// Calculate previous period for comparison (based on selected period)
$prev_start = clone $start_date;
$prev_end = clone $end_date;

switch ($time_period) {
    case 'this_week':
        $prev_start->modify('-7 days');
        $prev_end->modify('-7 days');
        break;
    case 'this_month':
        $prev_start->modify('first day of last month');
        $prev_end->modify('last day of last month');
        break;
    case 'last_30_days':
        $prev_start->modify('-30 days');
        $prev_end = clone $start_date;
        $prev_end->modify('-1 day');
        break;
    case 'last_quarter':
        $prev_start->modify('-3 months');
        $prev_end = clone $start_date;
        $prev_end->modify('-1 day');
        break;
    case 'this_year':
        $prev_start->modify('first day of january last year');
        $prev_end->modify('last day of december last year');
        break;
    default:
        $prev_start->modify('first day of last month');
        $prev_end->modify('last day of last month');
}

$prev_start->setTime(0, 0, 0);
$prev_end->setTime(23, 59, 59);
$prev_start_str = $prev_start->format('Y-m-d H:i:s');
$prev_end_str = $prev_end->format('Y-m-d H:i:s');

try {
    // 1. Core Summary Statistics
try {
    // Revenue: Sum of completed payments in period
    $revenue_stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM tbl_payments 
        WHERE payment_status = 'Completed' 
        AND payment_date >= ? AND payment_date <= ?
    ");
        if ($revenue_stmt) {
    $revenue_stmt->bind_param("ss", $start_date_str, $end_date_str);
    $revenue_stmt->execute();
    $revenue_result = $revenue_stmt->get_result();
    if ($row = $revenue_result->fetch_assoc()) {
        $stats['revenue'] = floatval($row['total']);
    }
    $revenue_stmt->close();
        }
    
    // Previous period revenue
    $revenue_prev_stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM tbl_payments 
        WHERE payment_status = 'Completed' 
        AND payment_date >= ? AND payment_date <= ?
    ");
        if ($revenue_prev_stmt) {
    $revenue_prev_stmt->bind_param("ss", $prev_start_str, $prev_end_str);
    $revenue_prev_stmt->execute();
    $revenue_prev_result = $revenue_prev_stmt->get_result();
    if ($row = $revenue_prev_result->fetch_assoc()) {
        $stats['revenue_prev'] = floatval($row['total']);
    }
    $revenue_prev_stmt->close();
        }
    
    // Daily Visits: Count of completed appointments in period
    $visits_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tbl_appointments 
        WHERE status = 'Completed' 
        AND scheduled_datetime >= ? AND scheduled_datetime <= ?
    ");
        if ($visits_stmt) {
    $visits_stmt->bind_param("ss", $start_date_str, $end_date_str);
    $visits_stmt->execute();
    $visits_result = $visits_stmt->get_result();
    if ($row = $visits_result->fetch_assoc()) {
        $stats['daily_visits'] = intval($row['count']);
    }
    $visits_stmt->close();
        }
    
    // Previous period visits (completed)
    $visits_prev_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tbl_appointments 
        WHERE status = 'Completed' 
        AND scheduled_datetime >= ? AND scheduled_datetime <= ?
    ");
        if ($visits_prev_stmt) {
    $visits_prev_stmt->bind_param("ss", $prev_start_str, $prev_end_str);
    $visits_prev_stmt->execute();
    $visits_prev_result = $visits_prev_stmt->get_result();
    if ($row = $visits_prev_result->fetch_assoc()) {
        $stats['visits_prev'] = intval($row['count']);
    }
    $visits_prev_stmt->close();
        }
    
        // Amount of Bookings: Count of all appointments based on scheduled date
    $bookings_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tbl_appointments 
            WHERE scheduled_datetime >= ? AND scheduled_datetime <= ?
    ");
        if ($bookings_stmt) {
    $bookings_stmt->bind_param("ss", $start_date_str, $end_date_str);
    $bookings_stmt->execute();
    $bookings_result = $bookings_stmt->get_result();
    if ($row = $bookings_result->fetch_assoc()) {
        $stats['bookings'] = intval($row['count']);
    }
    $bookings_stmt->close();
        }
    
        // Previous period bookings (based on scheduled date)
    $bookings_prev_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tbl_appointments 
            WHERE scheduled_datetime >= ? AND scheduled_datetime <= ?
    ");
        if ($bookings_prev_stmt) {
    $bookings_prev_stmt->bind_param("ss", $prev_start_str, $prev_end_str);
    $bookings_prev_stmt->execute();
    $bookings_prev_result = $bookings_prev_stmt->get_result();
    if ($row = $bookings_prev_result->fetch_assoc()) {
        $stats['bookings_prev'] = intval($row['count']);
    }
    $bookings_prev_stmt->close();
        }
    
    // New Customers: Count of new patients in period
    $customers_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tbl_patients 
        WHERE created_at >= ? AND created_at <= ?
    ");
        if ($customers_stmt) {
    $customers_stmt->bind_param("ss", $start_date_str, $end_date_str);
    $customers_stmt->execute();
    $customers_result = $customers_stmt->get_result();
    if ($row = $customers_result->fetch_assoc()) {
        $stats['new_customers'] = intval($row['count']);
    }
    $customers_stmt->close();
        }
    
    // Previous period customers
    $customers_prev_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tbl_patients 
        WHERE created_at >= ? AND created_at <= ?
    ");
        if ($customers_prev_stmt) {
    $customers_prev_stmt->bind_param("ss", $prev_start_str, $prev_end_str);
    $customers_prev_stmt->execute();
    $customers_prev_result = $customers_prev_stmt->get_result();
    if ($row = $customers_prev_result->fetch_assoc()) {
        $stats['customers_prev'] = intval($row['count']);
    }
    $customers_prev_stmt->close();
        }
    } catch (Exception $e) {
        error_log("Summary stats error: " . $e->getMessage());
    }
    
    // 2. Transaction History (Payments)
    try {
    $transactions_offset = ($transactions_page - 1) * $transactions_per_page;
    
    // Build WHERE clause for transactions
    $trans_where = [];
    $trans_params = [];
    $trans_types = [];
    
        // Add time period filter based on created_at (matches PDF)
    $trans_where[] = "p.created_at >= ? AND p.created_at <= ?";
    $trans_params[] = $start_date_str;
    $trans_params[] = $end_date_str;
        $trans_types[] = 's';
        $trans_types[] = 's';
    
    if (!empty($trans_search)) {
            $trans_where[] = "(CONCAT(pt.first_name, ' ', pt.last_name) LIKE ? OR CONCAT(COALESCE(s_dentist.first_name, s_dentist_inv.first_name, s_validator.first_name), ' ', COALESCE(s_dentist.last_name, s_dentist_inv.last_name, s_validator.last_name)) LIKE ?)";
        $search_term = '%' . $trans_search . '%';
        $trans_params[] = $search_term;
        $trans_params[] = $search_term;
            $trans_types[] = 's';
            $trans_types[] = 's';
    }
    
    if ($trans_status !== 'all') {
        $trans_where[] = "p.payment_status = ?";
        $trans_params[] = $trans_status;
        $trans_types[] = 's';
    }
    
    if ($trans_staff > 0) {
            $trans_where[] = "(COALESCE(s_dentist.staff_id, s_dentist_inv.staff_id, s_validator.staff_id) = ?)";
        $trans_params[] = $trans_staff;
        $trans_types[] = 'i';
    }
    
    if ($trans_service > 0) {
        $trans_where[] = "sv.service_id = ?";
        $trans_params[] = $trans_service;
        $trans_types[] = 'i';
    }
    
    if (!empty($trans_date_from)) {
        $trans_where[] = "DATE(p.payment_date) >= ?";
        $trans_params[] = $trans_date_from;
        $trans_types[] = 's';
    }
    
    if (!empty($trans_date_to)) {
        $trans_where[] = "DATE(p.payment_date) <= ?";
        $trans_params[] = $trans_date_to;
        $trans_types[] = 's';
    }
    
    $trans_where_clause = !empty($trans_where) ? 'WHERE ' . implode(' AND ', $trans_where) : '';
    
        // Count Query
    $transactions_count_sql = "SELECT COUNT(*) as total FROM tbl_payments p
        LEFT JOIN tbl_patients pt ON p.patient_id = pt.patient_id
        LEFT JOIN tbl_appointments a ON p.appointment_id = a.appointment_id
        LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
        LEFT JOIN tbl_staff s_dentist ON a.dentist_id = s_dentist.staff_id
        LEFT JOIN tbl_invoices i ON p.invoice_id = i.invoice_id
        LEFT JOIN tbl_appointments a_inv ON i.appointment_id = a_inv.appointment_id
        LEFT JOIN tbl_service_catalog sv_inv ON a_inv.service_id = sv_inv.service_id
        LEFT JOIN tbl_staff s_dentist_inv ON a_inv.dentist_id = s_dentist_inv.staff_id
            LEFT JOIN tbl_staff s_validator ON p.staff_validator_id = s_validator.staff_id
        $trans_where_clause";
    
    $transactions_count_stmt = $conn->prepare($transactions_count_sql);
        if ($transactions_count_stmt) {
    if (!empty($trans_params)) {
        $trans_count_types = implode('', $trans_types);
        $transactions_count_stmt->bind_param($trans_count_types, ...$trans_params);
    }
    $transactions_count_stmt->execute();
    $transactions_count_result = $transactions_count_stmt->get_result();
    if ($row = $transactions_count_result->fetch_assoc()) {
        $transactions_total = intval($row['total']);
    }
    $transactions_count_stmt->close();
        }
    
    // Fetch paginated transactions with filters
        // Simplified query to ensure results show up
    $transactions_sql = "SELECT 
            p.payment_id,
            p.payment_type,
            p.amount,
            p.payment_method,
            p.payment_status,
                COALESCE(p.payment_date, p.created_at) as payment_date,
            p.transaction_id,
            p.created_at,
            p.invoice_id,
            pt.first_name as patient_first_name,
            pt.last_name as patient_last_name,
            pt.email as patient_email,
            COALESCE(a.appointment_id, a_inv.appointment_id) as appointment_id,
            COALESCE(a.scheduled_datetime, a_inv.scheduled_datetime) as scheduled_datetime,
                COALESCE(sv.service_name, sv_inv.service_name, a.service_type, a_inv.service_type) as service_name,
                COALESCE(s_dentist.first_name, s_dentist_inv.first_name, s_validator.first_name) as staff_first_name,
                COALESCE(s_dentist.last_name, s_dentist_inv.last_name, s_validator.last_name) as staff_last_name,
                COALESCE(s_dentist.staff_id, s_dentist_inv.staff_id, s_validator.staff_id) as staff_id
        FROM tbl_payments p
        LEFT JOIN tbl_patients pt ON p.patient_id = pt.patient_id
        LEFT JOIN tbl_appointments a ON p.appointment_id = a.appointment_id
        LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
        LEFT JOIN tbl_staff s_dentist ON a.dentist_id = s_dentist.staff_id
        LEFT JOIN tbl_invoices i ON p.invoice_id = i.invoice_id
        LEFT JOIN tbl_appointments a_inv ON i.appointment_id = a_inv.appointment_id
        LEFT JOIN tbl_service_catalog sv_inv ON a_inv.service_id = sv_inv.service_id
        LEFT JOIN tbl_staff s_dentist_inv ON a_inv.dentist_id = s_dentist_inv.staff_id
            LEFT JOIN tbl_staff s_validator ON p.staff_validator_id = s_validator.staff_id
        $trans_where_clause
        ORDER BY p.payment_id DESC
        LIMIT ? OFFSET ?";
    
    $transactions_stmt = $conn->prepare($transactions_sql);
        if ($transactions_stmt) {
    $trans_params_with_pagination = array_merge($trans_params, [$transactions_per_page, $transactions_offset]);
    $trans_types_with_pagination = implode('', $trans_types) . 'ii';
    $transactions_stmt->bind_param($trans_types_with_pagination, ...$trans_params_with_pagination);
    $transactions_stmt->execute();
    $transactions_result = $transactions_stmt->get_result();
    while ($row = $transactions_result->fetch_assoc()) {
        $transactions[] = $row;
    }
    $transactions_stmt->close();
        }
    
    $transactions_total_pages = ceil($transactions_total / $transactions_per_page);
    } catch (Exception $e) {
        error_log("Transactions error: " . $e->getMessage());
    }
    
    // 3. Appointment History
    try {
    $appointments_offset = ($appointments_page - 1) * $appointments_per_page;
    
    // Build WHERE clause for appointments
    $apt_where = [];
    $apt_params = [];
    $apt_types = [];
    
        // Add time period filter based on scheduled_datetime (matches PDF)
        $apt_where[] = "a.scheduled_datetime >= ? AND a.scheduled_datetime <= ?";
    $apt_params[] = $start_date_str;
    $apt_params[] = $end_date_str;
        $apt_types[] = 's';
        $apt_types[] = 's';
    
    if (!empty($apt_search)) {
        $apt_where[] = "(CONCAT(p.first_name, ' ', p.last_name) LIKE ? OR CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR sv.service_name LIKE ?)";
        $search_term = '%' . $apt_search . '%';
        $apt_params[] = $search_term;
        $apt_params[] = $search_term;
        $apt_params[] = $search_term;
            $apt_types[] = 's';
            $apt_types[] = 's';
            $apt_types[] = 's';
    }
    
    if ($apt_status !== 'all') {
        $apt_where[] = "a.status = ?";
        $apt_params[] = $apt_status;
        $apt_types[] = 's';
    }
    
    if ($apt_staff > 0) {
        $apt_where[] = "s.staff_id = ?";
        $apt_params[] = $apt_staff;
        $apt_types[] = 'i';
    }
    
    if ($apt_service > 0) {
        $apt_where[] = "sv.service_id = ?";
        $apt_params[] = $apt_service;
        $apt_types[] = 'i';
    }
    
    if (!empty($apt_date_from)) {
        $apt_where[] = "DATE(a.scheduled_datetime) >= ?";
        $apt_params[] = $apt_date_from;
        $apt_types[] = 's';
    }
    
    if (!empty($apt_date_to)) {
        $apt_where[] = "DATE(a.scheduled_datetime) <= ?";
        $apt_params[] = $apt_date_to;
        $apt_types[] = 's';
    }
    
    $apt_where_clause = !empty($apt_where) ? 'WHERE ' . implode(' AND ', $apt_where) : '';
    
    // Get total count with filters
    $appointments_count_sql = "SELECT COUNT(*) as total 
        FROM tbl_appointments a
        LEFT JOIN tbl_patients p ON a.patient_id = p.patient_id
        LEFT JOIN tbl_staff s ON a.dentist_id = s.staff_id
        LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
        $apt_where_clause";
    
    $appointments_count_stmt = $conn->prepare($appointments_count_sql);
        if ($appointments_count_stmt) {
    if (!empty($apt_params)) {
        $apt_count_types = implode('', $apt_types);
        $appointments_count_stmt->bind_param($apt_count_types, ...$apt_params);
    }
    $appointments_count_stmt->execute();
    $appointments_count_result = $appointments_count_stmt->get_result();
    if ($row = $appointments_count_result->fetch_assoc()) {
        $appointments_total = intval($row['total']);
    }
    $appointments_count_stmt->close();
        }
    
    // Fetch paginated appointments with filters
    $appointments_sql = "SELECT 
            a.appointment_id,
            a.scheduled_datetime,
            a.duration_minutes,
            a.status,
            a.price,
            p.first_name as patient_first_name,
            p.last_name as patient_last_name,
            p.email as patient_email,
            s.first_name as dentist_first_name,
            s.last_name as dentist_last_name,
            s.staff_id,
            sv.service_name,
            sv.service_id
        FROM tbl_appointments a
        LEFT JOIN tbl_patients p ON a.patient_id = p.patient_id
        LEFT JOIN tbl_staff s ON a.dentist_id = s.staff_id
        LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
        $apt_where_clause
        ORDER BY a.appointment_id DESC
        LIMIT ? OFFSET ?";
    
    $appointments_stmt = $conn->prepare($appointments_sql);
        if ($appointments_stmt) {
    $apt_params_with_pagination = array_merge($apt_params, [$appointments_per_page, $appointments_offset]);
    $apt_types_with_pagination = implode('', $apt_types) . 'ii';
    $appointments_stmt->bind_param($apt_types_with_pagination, ...$apt_params_with_pagination);
    $appointments_stmt->execute();
    $appointments_result = $appointments_stmt->get_result();
    while ($row = $appointments_result->fetch_assoc()) {
        $appointments[] = $row;
    }
    $appointments_stmt->close();
        }
    
    $appointments_total_pages = ceil($appointments_total / $appointments_per_page);
    } catch (Exception $e) {
        error_log("Appointments error: " . $e->getMessage());
    }
    
    // 4. Dropdown data
    try {
    $services = [];
    $services_stmt = $conn->prepare("SELECT service_id, service_name FROM tbl_service_catalog WHERE is_active = 1 ORDER BY service_name");
        if ($services_stmt) {
    $services_stmt->execute();
    $services_result = $services_stmt->get_result();
    while ($row = $services_result->fetch_assoc()) {
        $services[] = $row;
    }
    $services_stmt->close();
        }
    
    $staff_list = [];
    $staff_stmt = $conn->prepare("SELECT staff_id, first_name, last_name FROM tbl_staff WHERE role = 'Dentist' ORDER BY first_name, last_name");
        if ($staff_stmt) {
    $staff_stmt->execute();
    $staff_result = $staff_stmt->get_result();
    while ($row = $staff_result->fetch_assoc()) {
        $staff_list[] = $row;
    }
    $staff_stmt->close();
        }
    } catch (Exception $e) {
        error_log("Dropdown data error: " . $e->getMessage());
    }
    
    $conn->close();
} catch (Exception $e) {
    error_log("General reports fetching error: " . $e->getMessage());
}

// Calculate percentage changes
$revenue_change = $stats['revenue_prev'] > 0 ? (($stats['revenue'] - $stats['revenue_prev']) / $stats['revenue_prev']) * 100 : 0;
$visits_change = $stats['visits_prev'] > 0 ? (($stats['daily_visits'] - $stats['visits_prev']) / $stats['visits_prev']) * 100 : 0;
$bookings_change = $stats['bookings_prev'] > 0 ? (($stats['bookings'] - $stats['bookings_prev']) / $stats['bookings_prev']) * 100 : 0;
$customers_change = $stats['customers_prev'] > 0 ? (($stats['new_customers'] - $stats['customers_prev']) / $stats['customers_prev']) * 100 : 0;

// Output cache prevention meta
outputCachePreventionMeta();
?>
<?php
$page_title = 'Reports';
$user_name  = $admin_name;
include ROOT_PATH . '/views/layout/header.php';
?>
<div class="flex min-h-screen">
    <?php include ROOT_PATH . '/views/layout/sidebar_staff.php'; ?>
    <div class="flex-1 flex flex-col lg:ml-64">
        <?php include ROOT_PATH . '/views/layout/topbar.php'; ?>
<main class="flex-grow w-full max-w-7xl mx-auto px-4 md:px-8 py-8">
<div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
<div>
<h1 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white">Staff Sales & Performance Reports</h1>
<p class="mt-1 text-slate-500 dark:text-slate-400">Overview of clinic revenue and appointment metrics.</p>
</div>
</div>

<div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center bg-white dark:bg-[#1a2634] p-4 rounded-xl border border-[#e7ecf3] dark:border-slate-700">
<div class="flex items-center gap-3 w-full sm:w-auto">
<div class="relative w-full sm:w-64">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">calendar_today</span>
<select id="timePeriod" class="h-10 w-full cursor-pointer rounded-lg border-slate-300 bg-white dark:bg-slate-800 pl-10 pr-8 text-sm font-medium text-slate-700 dark:text-slate-200 hover:border-slate-400 focus:border-primary focus:ring-primary dark:border-slate-700" onchange="window.location.href='reports.php?period=' + this.value + '&tab=<?php echo $active_tab; ?>'">
<option value="this_week" <?php echo $time_period === 'this_week' ? 'selected' : ''; ?>>This Week</option>
<option value="this_month" <?php echo $time_period === 'this_month' ? 'selected' : ''; ?>>This Month</option>
<option value="last_30_days" <?php echo $time_period === 'last_30_days' ? 'selected' : ''; ?>>Last 30 Days</option>
<option value="last_quarter" <?php echo $time_period === 'last_quarter' ? 'selected' : ''; ?>>Last Quarter</option>
<option value="this_year" <?php echo $time_period === 'this_year' ? 'selected' : ''; ?>>This Year</option>
</select>
</div>
</div>
<a href="export_reports_pdf.php?<?php echo http_build_query([
    'period' => $time_period,
    'tab' => $active_tab,
    'trans_search' => $trans_search,
    'trans_status' => $trans_status,
    'trans_staff' => $trans_staff,
    'trans_service' => $trans_service,
    'trans_date_from' => $trans_date_from,
    'trans_date_to' => $trans_date_to,
    'apt_search' => $apt_search,
    'apt_status' => $apt_status,
    'apt_staff' => $apt_staff,
    'apt_service' => $apt_service,
    'apt_date_from' => $apt_date_from,
    'apt_date_to' => $apt_date_to
]); ?>" class="flex h-10 w-full sm:w-auto justify-center items-center gap-2 rounded-lg bg-primary px-4 text-sm font-medium text-white transition-colors hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
<span class="material-symbols-outlined text-[20px]">download</span>
Export
</a>
</div>

<div class="mb-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
<div class="relative overflow-hidden rounded-xl border border-[#e7ecf3] dark:border-slate-700 bg-white dark:bg-[#1a2634] p-6 shadow-sm">
<div class="flex items-center justify-between">
<div>
<p class="text-sm font-medium text-slate-500 dark:text-slate-400">Total Revenue</p>
<p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">â‚±<?php echo number_format($stats['revenue'], 2); ?></p>
</div>
<div class="flex h-12 w-12 items-center justify-center rounded-full bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400">
<span class="material-symbols-outlined">payments</span>
</div>
</div>
<div class="mt-4 flex items-center text-sm">
<?php 
$revenue_trend = $revenue_change >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
$revenue_icon = $revenue_change >= 0 ? 'trending_up' : 'trending_down';
?>
<span class="flex items-center <?php echo $revenue_trend; ?> font-medium">
<span class="material-symbols-outlined mr-1 text-base"><?php echo $revenue_icon; ?></span>
<?php echo number_format(abs($revenue_change), 1); ?>%
</span>
<span class="ml-2 text-slate-500 dark:text-slate-400">vs previous period</span>
</div>
</div>

<div class="relative overflow-hidden rounded-xl border border-[#e7ecf3] dark:border-slate-700 bg-white dark:bg-[#1a2634] p-6 shadow-sm">
<div class="flex items-center justify-between">
<div>
<p class="text-sm font-medium text-slate-500 dark:text-slate-400">Daily Visits</p>
<p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white"><?php echo number_format($stats['daily_visits']); ?></p>
</div>
<div class="flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400">
<span class="material-symbols-outlined">event_available</span>
</div>
</div>
<div class="mt-4 flex items-center text-sm">
<?php 
$visits_trend = $visits_change >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
$visits_icon = $visits_change >= 0 ? 'trending_up' : 'trending_down';
?>
<span class="flex items-center <?php echo $visits_trend; ?> font-medium">
<span class="material-symbols-outlined mr-1 text-base"><?php echo $visits_icon; ?></span>
<?php echo number_format(abs($visits_change), 1); ?>%
</span>
<span class="ml-2 text-slate-500 dark:text-slate-400">vs previous period</span>
</div>
</div>

<div class="relative overflow-hidden rounded-xl border border-[#e7ecf3] dark:border-slate-700 bg-white dark:bg-[#1a2634] p-6 shadow-sm">
<div class="flex items-center justify-between">
<div>
<p class="text-sm font-medium text-slate-500 dark:text-slate-400">Amount of Bookings</p>
<p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white"><?php echo number_format($stats['bookings']); ?></p>
</div>
<div class="flex h-12 w-12 items-center justify-center rounded-full bg-purple-50 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400">
<span class="material-symbols-outlined">book_online</span>
</div>
</div>
<div class="mt-4 flex items-center text-sm">
<?php 
$bookings_trend = $bookings_change >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
$bookings_icon = $bookings_change >= 0 ? 'trending_up' : 'trending_down';
?>
<span class="flex items-center <?php echo $bookings_trend; ?> font-medium">
<span class="material-symbols-outlined mr-1 text-base"><?php echo $bookings_icon; ?></span>
<?php echo number_format(abs($bookings_change), 1); ?>%
</span>
<span class="ml-2 text-slate-500 dark:text-slate-400">vs previous period</span>
</div>
</div>

<div class="relative overflow-hidden rounded-xl border border-[#e7ecf3] dark:border-slate-700 bg-white dark:bg-[#1a2634] p-6 shadow-sm">
<div class="flex items-center justify-between">
<div>
<p class="text-sm font-medium text-slate-500 dark:text-slate-400">New Customers</p>
<p class="mt-2 text-3xl font-bold text-slate-900 dark:text-white"><?php echo number_format($stats['new_customers']); ?></p>
</div>
<div class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400">
<span class="material-symbols-outlined">pending_actions</span>
</div>
</div>
<div class="mt-4 flex items-center text-sm">
<?php 
$customers_trend = $customers_change >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
$customers_icon = $customers_change >= 0 ? 'trending_up' : 'trending_down';
?>
<span class="flex items-center <?php echo $customers_trend; ?> font-medium">
<span class="material-symbols-outlined mr-1 text-base"><?php echo $customers_icon; ?></span>
<?php echo number_format(abs($customers_change), 1); ?>%
</span>
<span class="ml-2 text-slate-500 dark:text-slate-400">vs previous period</span>
</div>
</div>

</div>

<div class="rounded-xl border border-[#e7ecf3] dark:border-slate-700 bg-white dark:bg-[#1a2634] shadow-sm">
<div class="border-b border-[#e7ecf3] dark:border-slate-700 px-6 pt-4">
<nav aria-label="Tabs" class="-mb-px flex space-x-8">
<a id="appointmentHistoryTabLink" class="<?php echo $active_tab === 'appointment' ? 'border-primary text-primary dark:text-primary-400' : 'border-transparent text-slate-500 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-600 hover:text-slate-700 dark:hover:text-slate-300'; ?> group inline-flex items-center border-b-2 py-4 px-1 text-sm font-medium cursor-pointer" onclick="switchTab('appointment')" <?php echo $active_tab === 'appointment' ? 'aria-current="page"' : ''; ?>>
<span class="material-symbols-outlined mr-2 text-[20px]">history</span>
Appointment History
</a>
<a id="salesTransactionsTabLink" <?php echo $active_tab === 'sales' ? 'aria-current="page" class="border-primary text-primary dark:text-primary-400' : 'class="border-transparent text-slate-500 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-600 hover:text-slate-700 dark:hover:text-slate-300'; ?> group inline-flex items-center border-b-2 py-4 px-1 text-sm font-medium cursor-pointer" onclick="switchTab('sales')">
<span class="material-symbols-outlined mr-2 text-[20px]">monetization_on</span>
Sales/Transactions
</a>
</nav>
</div>

<div id="salesTransactionsContent" <?php echo $active_tab === 'sales' ? '' : 'class="hidden"'; ?>>
<form method="GET" action="reports.php" class="flex flex-col gap-4 border-b border-[#e7ecf3] dark:border-slate-700 p-6">
<input type="hidden" name="tab" value="sales">
<input type="hidden" name="period" value="<?php echo htmlspecialchars($time_period); ?>">
<input type="hidden" name="apt_page" value="<?php echo $appointments_page; ?>">
<input type="hidden" name="apt_search" value="<?php echo htmlspecialchars($apt_search); ?>">
<input type="hidden" name="apt_status" value="<?php echo htmlspecialchars($apt_status); ?>">
<input type="hidden" name="apt_staff" value="<?php echo $apt_staff; ?>">
<input type="hidden" name="apt_service" value="<?php echo $apt_service; ?>">
<input type="hidden" name="apt_date_from" value="<?php echo htmlspecialchars($apt_date_from); ?>">
<input type="hidden" name="apt_date_to" value="<?php echo htmlspecialchars($apt_date_to); ?>">
<div class="flex flex-col gap-4 md:flex-row md:items-center">
<div class="relative flex-1">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
<input name="trans_search" value="<?php echo htmlspecialchars($trans_search); ?>" class="h-10 w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 pl-10 pr-4 text-sm text-slate-900 dark:text-white dark:placeholder-slate-400 focus:border-primary focus:ring-primary" placeholder="Search by Staff or Patient Name" type="text"/>
</div>
<div class="flex gap-3 flex-wrap">
<div class="relative">
<select name="trans_status" onchange="this.form.submit()" class="h-10 cursor-pointer rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 text-sm font-medium text-slate-700 dark:text-slate-200 hover:border-slate-400 focus:border-primary focus:ring-primary">
<option value="all" <?php echo $trans_status === 'all' ? 'selected' : ''; ?>>Status: All</option>
<option value="Completed" <?php echo $trans_status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
<option value="Pending" <?php echo $trans_status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
<option value="Failed" <?php echo $trans_status === 'Failed' ? 'selected' : ''; ?>>Failed</option>
<option value="Cancelled" <?php echo $trans_status === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
</select>
</div>
<div class="relative">
<select name="trans_staff" onchange="this.form.submit()" class="h-10 cursor-pointer rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 text-sm font-medium text-slate-700 dark:text-slate-200 hover:border-slate-400 focus:border-primary focus:ring-primary">
<option value="0">Staff: All</option>
<?php foreach ($staff_list as $staff): ?>
<option value="<?php echo $staff['staff_id']; ?>" <?php echo $trans_staff == $staff['staff_id'] ? 'selected' : ''; ?>>
Dr. <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="relative">
<select name="trans_service" onchange="this.form.submit()" class="h-10 cursor-pointer rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 text-sm font-medium text-slate-700 dark:text-slate-200 hover:border-slate-400 focus:border-primary focus:ring-primary">
<option value="0">Service: All</option>
<?php foreach ($services as $service): ?>
<option value="<?php echo $service['service_id']; ?>" <?php echo $trans_service == $service['service_id'] ? 'selected' : ''; ?>>
<?php echo htmlspecialchars($service['service_name']); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="relative">
<input name="trans_date_from" type="date" value="<?php echo htmlspecialchars($trans_date_from); ?>" onchange="this.form.submit()" class="h-10 cursor-pointer rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 text-sm font-medium text-slate-700 dark:text-slate-200 hover:border-slate-400 focus:border-primary focus:ring-primary" placeholder="Date From"/>
</div>
<div class="relative">
<input name="trans_date_to" type="date" value="<?php echo htmlspecialchars($trans_date_to); ?>" onchange="this.form.submit()" class="h-10 cursor-pointer rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 text-sm font-medium text-slate-700 dark:text-slate-200 hover:border-slate-400 focus:border-primary focus:ring-primary" placeholder="Date To"/>
</div>
<button type="submit" class="h-10 px-4 rounded-lg bg-primary text-white text-sm font-medium hover:bg-blue-600">Apply</button>
</div>
</div>
</form>

<div class="overflow-x-auto">
<table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
<thead class="bg-slate-50 dark:bg-slate-800/50">
<tr>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">ID</th>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">Date</th>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">Dentist</th>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">Patient</th>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">Treatment</th>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">Amount</th>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">Status</th>
<th class="relative px-6 py-3" scope="col">
<span class="sr-only">Actions</span>
</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-900">
<?php if (empty($transactions)): ?>
<tr class="group hover:bg-slate-50 dark:hover:bg-slate-800/50">
<td colspan="8" class="text-center py-8 text-slate-500 dark:text-slate-400">No transactions found</td>
</tr>
<?php else: ?>
<?php foreach ($transactions as $trans): ?>
<tr class="group hover:bg-slate-50 dark:hover:bg-slate-800/50">
<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-white">#<?php echo htmlspecialchars($trans['payment_id']); ?></td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400"><?php echo date('M d, Y', strtotime($trans['payment_date'])); ?></td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
<?php 
if ($trans['staff_first_name'] && $trans['staff_last_name']) {
    echo 'Dr. ' . htmlspecialchars($trans['staff_first_name'] . ' ' . $trans['staff_last_name']);
} else {
    echo 'N/A';
}
?>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
<?php 
if ($trans['patient_first_name'] && $trans['patient_last_name']) {
    echo htmlspecialchars($trans['patient_first_name'] . ' ' . $trans['patient_last_name']);
} else {
    echo 'N/A';
}
?>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
<?php 
// Use service_name from catalog if available, otherwise use service_type from appointment, otherwise N/A
if (!empty($trans['service_name'])) {
    echo htmlspecialchars($trans['service_name']);
} elseif (!empty($trans['apt_service_type'])) {
    echo htmlspecialchars($trans['apt_service_type']);
} else {
    echo 'N/A';
}
?>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-white">â‚±<?php echo number_format($trans['amount'], 2); ?></td>
<td class="px-6 py-4 whitespace-nowrap">
<?php
$status = strtolower($trans['payment_status']);
$status_class = 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
if ($status === 'pending') {
    $status_class = 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
} elseif ($status === 'failed' || $status === 'cancelled') {
    $status_class = 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
}
?>
<span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium <?php echo $status_class; ?>">
<?php echo htmlspecialchars(ucfirst($trans['payment_status'])); ?>
</span>
</td>
<td class="relative px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
<button onclick="showTransactionDetails(<?php echo htmlspecialchars(json_encode($trans), ENT_QUOTES, 'UTF-8'); ?>)" class="text-primary hover:text-primary-dark">View</button>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>

<div class="flex items-center justify-between border-t border-[#e7ecf3] dark:border-slate-700 px-6 py-4">
<div class="text-sm text-slate-500 dark:text-slate-400">
Showing <span class="font-medium"><?php echo $transactions_total > 0 ? (($transactions_page - 1) * $transactions_per_page + 1) : 0; ?></span> to <span class="font-medium"><?php echo min($transactions_page * $transactions_per_page, $transactions_total); ?></span> of <span class="font-medium"><?php echo $transactions_total; ?></span> results
</div>
<div class="flex gap-2">
<?php
$trans_query_params = http_build_query([
    'tab' => 'sales',
    'period' => $time_period,
    'trans_search' => $trans_search,
    'trans_status' => $trans_status,
    'trans_staff' => $trans_staff,
    'trans_service' => $trans_service,
    'trans_date_from' => $trans_date_from,
    'trans_date_to' => $trans_date_to,
    'apt_page' => $appointments_page,
    'apt_search' => $apt_search,
    'apt_status' => $apt_status,
    'apt_staff' => $apt_staff,
    'apt_service' => $apt_service,
    'apt_date_from' => $apt_date_from,
    'apt_date_to' => $apt_date_to
]);
?>
<button onclick="window.location.href='reports.php?<?php echo $trans_query_params; ?>&trans_page=<?php echo max(1, $transactions_page - 1); ?>'" class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors" <?php echo $transactions_page <= 1 ? 'disabled' : ''; ?>>
Previous
</button>
<button onclick="window.location.href='reports.php?<?php echo $trans_query_params; ?>&trans_page=<?php echo min($transactions_total_pages, $transactions_page + 1); ?>'" class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors" <?php echo $transactions_page >= $transactions_total_pages ? 'disabled' : ''; ?>>
Next
</button>
</div>
</div>
</div>

<!-- Appointment History Tab Content -->
<div id="appointmentHistoryTab" <?php echo $active_tab === 'appointment' ? '' : 'class="hidden"'; ?>>
<form method="GET" action="reports.php" class="flex flex-col gap-4 border-b border-[#e7ecf3] dark:border-slate-700 p-6">
<input type="hidden" name="tab" value="appointment">
<input type="hidden" name="period" value="<?php echo htmlspecialchars($time_period); ?>">
<input type="hidden" name="trans_page" value="<?php echo $transactions_page; ?>">
<input type="hidden" name="trans_search" value="<?php echo htmlspecialchars($trans_search); ?>">
<input type="hidden" name="trans_status" value="<?php echo htmlspecialchars($trans_status); ?>">
<input type="hidden" name="trans_staff" value="<?php echo $trans_staff; ?>">
<input type="hidden" name="trans_service" value="<?php echo $trans_service; ?>">
<input type="hidden" name="trans_date_from" value="<?php echo htmlspecialchars($trans_date_from); ?>">
<input type="hidden" name="trans_date_to" value="<?php echo htmlspecialchars($trans_date_to); ?>">
<div class="flex flex-col gap-4 md:flex-row md:items-center">
<div class="relative flex-1">
<span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
<input name="apt_search" value="<?php echo htmlspecialchars($apt_search); ?>" class="h-10 w-full rounded-lg border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 pl-10 pr-4 text-sm text-slate-900 dark:text-white dark:placeholder-slate-400 focus:border-primary focus:ring-primary" placeholder="Search by Patient, Dentist, or Service" type="text"/>
</div>
<div class="flex gap-3 flex-wrap">
<div class="relative">
<select name="apt_status" onchange="this.form.submit()" class="h-10 cursor-pointer rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 text-sm font-medium text-slate-700 dark:text-slate-200 hover:border-slate-400 focus:border-primary focus:ring-primary">
<option value="all" <?php echo $apt_status === 'all' ? 'selected' : ''; ?>>Status: All</option>
<option value="Scheduled" <?php echo $apt_status === 'Scheduled' ? 'selected' : ''; ?>>Scheduled</option>
<option value="Completed" <?php echo $apt_status === 'Completed' ? 'selected' : ''; ?>>Completed</option>
<option value="Missed" <?php echo $apt_status === 'Missed' ? 'selected' : ''; ?>>Missed</option>
<option value="Cancelled" <?php echo $apt_status === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
</select>
</div>
<div class="relative">
<select name="apt_staff" onchange="this.form.submit()" class="h-10 cursor-pointer rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 text-sm font-medium text-slate-700 dark:text-slate-200 hover:border-slate-400 focus:border-primary focus:ring-primary">
<option value="0">Dentist: All</option>
<?php foreach ($staff_list as $staff): ?>
<option value="<?php echo $staff['staff_id']; ?>" <?php echo $apt_staff == $staff['staff_id'] ? 'selected' : ''; ?>>
Dr. <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="relative">
<select name="apt_service" onchange="this.form.submit()" class="h-10 cursor-pointer rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 text-sm font-medium text-slate-700 dark:text-slate-200 hover:border-slate-400 focus:border-primary focus:ring-primary">
<option value="0">Service: All</option>
<?php foreach ($services as $service): ?>
<option value="<?php echo $service['service_id']; ?>" <?php echo $apt_service == $service['service_id'] ? 'selected' : ''; ?>>
<?php echo htmlspecialchars($service['service_name']); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="relative">
<input name="apt_date_from" type="date" value="<?php echo htmlspecialchars($apt_date_from); ?>" onchange="this.form.submit()" class="h-10 cursor-pointer rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 text-sm font-medium text-slate-700 dark:text-slate-200 hover:border-slate-400 focus:border-primary focus:ring-primary" placeholder="Date From"/>
</div>
<div class="relative">
<input name="apt_date_to" type="date" value="<?php echo htmlspecialchars($apt_date_to); ?>" onchange="this.form.submit()" class="h-10 cursor-pointer rounded-lg border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 text-sm font-medium text-slate-700 dark:text-slate-200 hover:border-slate-400 focus:border-primary focus:ring-primary" placeholder="Date To"/>
</div>
<button type="submit" class="h-10 px-4 rounded-lg bg-primary text-white text-sm font-medium hover:bg-blue-600">Apply</button>
</div>
</div>
</form>
<div class="overflow-x-auto">
<table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
<thead class="bg-slate-50 dark:bg-slate-800/50">
<tr>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">ID</th>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">Date & Time</th>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">Patient</th>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">Dentist</th>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">Service</th>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">Status</th>
<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400" scope="col">Amount</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-200 dark:divide-slate-800 bg-white dark:bg-slate-900">
<?php if (empty($appointments)): ?>
<tr class="group hover:bg-slate-50 dark:hover:bg-slate-800/50">
<td colspan="7" class="text-center py-8 text-slate-500 dark:text-slate-400">No appointments found</td>
</tr>
<?php else: ?>
<?php foreach ($appointments as $apt): ?>
<tr class="group hover:bg-slate-50 dark:hover:bg-slate-800/50">
<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-white">#<?php echo htmlspecialchars($apt['appointment_id']); ?></td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
<?php 
$apt_date = new DateTime($apt['scheduled_datetime']);
echo $apt_date->format('M d, Y h:i A');
?>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900 dark:text-white">
<?php 
if ($apt['patient_first_name'] && $apt['patient_last_name']) {
    echo htmlspecialchars($apt['patient_first_name'] . ' ' . $apt['patient_last_name']);
} else {
    echo 'N/A';
}
?>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
<?php 
if ($apt['dentist_first_name'] && $apt['dentist_last_name']) {
    echo 'Dr. ' . htmlspecialchars($apt['dentist_first_name'] . ' ' . $apt['dentist_last_name']);
} else {
    echo 'N/A';
}
?>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500 dark:text-slate-400">
<?php echo htmlspecialchars($apt['service_name'] ?? 'N/A'); ?>
</td>
<td class="px-6 py-4 whitespace-nowrap">
<?php
$status = strtolower($apt['status']);
$status_class = 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
if ($status === 'completed') {
    $status_class = 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
} elseif ($status === 'missed') {
    $status_class = 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
} elseif ($status === 'cancelled') {
    $status_class = 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400';
}
?>
<span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium <?php echo $status_class; ?>">
<?php echo htmlspecialchars(ucfirst($apt['status'])); ?>
</span>
</td>
<td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900 dark:text-white">â‚±<?php echo number_format($apt['price'] ?? 0, 2); ?></td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- Pagination for Appointment History -->
<div class="flex items-center justify-between border-t border-[#e7ecf3] dark:border-slate-700 px-6 py-4">
<div class="text-sm text-slate-500 dark:text-slate-400">
Showing <span class="font-medium"><?php echo $appointments_total > 0 ? (($appointments_page - 1) * $appointments_per_page + 1) : 0; ?></span> to <span class="font-medium"><?php echo min($appointments_page * $appointments_per_page, $appointments_total); ?></span> of <span class="font-medium"><?php echo $appointments_total; ?></span> results
</div>
<div class="flex gap-2">
<?php
$apt_query_params = http_build_query([
    'tab' => 'appointment',
    'period' => $time_period,
    'trans_page' => $transactions_page,
    'trans_search' => $trans_search,
    'trans_status' => $trans_status,
    'trans_staff' => $trans_staff,
    'trans_service' => $trans_service,
    'trans_date_from' => $trans_date_from,
    'trans_date_to' => $trans_date_to,
    'apt_search' => $apt_search,
    'apt_status' => $apt_status,
    'apt_staff' => $apt_staff,
    'apt_service' => $apt_service,
    'apt_date_from' => $apt_date_from,
    'apt_date_to' => $apt_date_to
]);
?>
<button onclick="window.location.href='reports.php?<?php echo $apt_query_params; ?>&apt_page=<?php echo max(1, $appointments_page - 1); ?>'" class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors" <?php echo $appointments_page <= 1 ? 'disabled' : ''; ?>>
Previous
</button>
<button onclick="window.location.href='reports.php?<?php echo $apt_query_params; ?>&apt_page=<?php echo min($appointments_total_pages, $appointments_page + 1); ?>'" class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors" <?php echo $appointments_page >= $appointments_total_pages ? 'disabled' : ''; ?>>
Next
</button>
</div>
</div>
</div>
</div>
</main>
        </main><!-- /.main -->
    </div><!-- /flex-1 -->
</div><!-- /flex min-h-screen -->
<?php ob_start(); ?>

<script>
// Logout confirmation
function confirmLogout(event) {
    event.preventDefault();
    const modal = document.getElementById('confirmModal');
    const content = document.getElementById('confirmModalContent');
    if (modal && content) {
        content.innerHTML = `
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Log Out?</h3>
                <button onclick="closeConfirmModal()" class="text-[#4c6c9a] hover:text-[#0d131b] dark:text-slate-400 dark:hover:text-white">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <p class="text-sm text-[#4c6c9a] dark:text-slate-400 mb-4">Are you sure you want to log out?</p>
            <div class="flex gap-2">
                <button type="button" onclick="closeConfirmModal()" class="flex-1 rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white py-2 text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    No
                </button>
                <a href="logout.php" class="flex-1 rounded-lg bg-red-600 text-white py-2 text-sm font-semibold hover:bg-red-700 transition-colors text-center">
                    Yes
                </a>
            </div>
        `;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeConfirmModal(event) {
    if (event && event.target !== event.currentTarget) return;
    const modal = document.getElementById('confirmModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeConfirmModal();
        closeTransactionModal();
    }
});

// Tab switching functionality
function switchTab(tab) {
    const appointmentTab = document.getElementById('appointmentHistoryTab');
    const salesTab = document.getElementById('salesTransactionsContent');
    const appointmentLink = document.getElementById('appointmentHistoryTabLink');
    const salesLink = document.getElementById('salesTransactionsTabLink');
    
    // Update URL without reloading
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.pushState({}, '', url);
    
    if (tab === 'appointment') {
        appointmentTab.classList.remove('hidden');
        salesTab.classList.add('hidden');
        appointmentLink.classList.remove('border-transparent', 'text-slate-500', 'dark:text-slate-400');
        appointmentLink.classList.add('border-primary', 'text-primary', 'dark:text-primary-400');
        salesLink.classList.remove('border-primary', 'text-primary', 'dark:text-primary-400');
        salesLink.classList.add('border-transparent', 'text-slate-500', 'dark:text-slate-400');
        salesLink.removeAttribute('aria-current');
        appointmentLink.setAttribute('aria-current', 'page');
    } else {
        appointmentTab.classList.add('hidden');
        salesTab.classList.remove('hidden');
        salesLink.classList.remove('border-transparent', 'text-slate-500', 'dark:text-slate-400');
        salesLink.classList.add('border-primary', 'text-primary', 'dark:text-primary-400');
        appointmentLink.classList.remove('border-primary', 'text-primary', 'dark:text-primary-400');
        appointmentLink.classList.add('border-transparent', 'text-slate-500', 'dark:text-slate-400');
        appointmentLink.removeAttribute('aria-current');
        salesLink.setAttribute('aria-current', 'page');
    }
}

// Initialize tab state on page load
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab') || 'sales';
    if (activeTab === 'appointment') {
        switchTab('appointment');
    } else {
        switchTab('sales');
    }
});

// Transaction Details Modal
function showTransactionDetails(trans) {
    const modal = document.getElementById('transactionModal');
    const content = document.getElementById('transactionModalContent');
    
    if (!modal || !content) return;
    
    // Format payment date
    const paymentDate = trans.payment_date ? new Date(trans.payment_date).toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }) : 'N/A';
    
    // Format scheduled datetime if available
    const scheduledDate = trans.scheduled_datetime ? new Date(trans.scheduled_datetime).toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    }) : 'N/A';
    
    // Get staff name
    const staffName = (trans.staff_first_name && trans.staff_last_name) 
        ? 'Dr. ' + trans.staff_first_name + ' ' + trans.staff_last_name 
        : 'N/A';
    
    // Get patient name
    const patientName = (trans.patient_first_name && trans.patient_last_name)
        ? trans.patient_first_name + ' ' + trans.patient_last_name
        : 'N/A';
    
    // Get service/treatment name
    const serviceName = trans.service_name || trans.apt_service_type || 'N/A';

    // Core monetary values for invoice-like breakdown
    const paymentAmount = parseFloat(trans.amount || 0);
    const servicePrice = parseFloat(trans.service_price || trans.appointment_price || trans.invoice_total_amount || paymentAmount || 0);
    const totalDownpayment = parseFloat(trans.total_downpayment || 0);
    const hasPwd = !!trans.pwd_id_number;
    const hasSenior = !!trans.senior_citizen_id_number;
    const discountPct = (hasPwd || hasSenior) ? 0.20 : 0;
    const discountLabel = hasPwd ? 'PWD Discount (20%)' : (hasSenior ? 'Senior Citizen Discount (20%)' : 'Discounts');

    // Balance after downpayment but before discount
    const balanceBeforeDiscount = Math.max(0, servicePrice - totalDownpayment);
    const discountAmount = balanceBeforeDiscount * discountPct;
    const remainingBalance = Math.max(0, balanceBeforeDiscount - discountAmount);
    const balanceAfterPayment = Math.max(0, remainingBalance - paymentAmount);
    
    // Get payment status badge class
    const status = (trans.payment_status || '').toLowerCase();
    let statusClass = 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
    if (status === 'pending' || status === 'pending verification') {
        statusClass = 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400';
    } else if (status === 'failed' || status === 'cancelled') {
        statusClass = 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
    }
    
    content.innerHTML = `
        <div id="transactionReceiptCard" class="space-y-4">
            <!-- Clinic Header + Actions -->
            <div class="flex items-start justify-between mb-4 border-b border-slate-200 dark:border-slate-700 pb-3">
                <div>
                    <h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Ivy Dental Clinic</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Sales / Payment Receipt</p>
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">This serves as an official record of payment for the services rendered.</p>
                    <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500 print:hidden">Tip: Click "Print Receipt" and choose "Save as PDF" in the print dialog to download a PDF copy.</p>
                </div>
                <div class="flex flex-col items-end gap-2">
                    <button type="button"
                            class="inline-flex items-center gap-1 px-3 py-1 rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-100 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                            onclick="printTransactionReceipt()">
                        <span class="material-symbols-outlined text-sm">print</span>
                        Print Receipt
                    </button>
                    <button onclick="closeTransactionModal()" class="text-[#4c6c9a] hover:text-[#0d131b] dark:text-slate-400 dark:hover:text-white">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
            </div>

            <!-- Header Meta (similar to invoice header) -->
            <div class="grid grid-cols-2 gap-4 text-xs text-slate-600 dark:text-slate-300">
                <div class="space-y-1">
                    <p><span class="font-semibold">Receipt No:</span> ${trans.payment_id ? 'TX-' + trans.payment_id : 'N/A'}</p>
                    <p><span class="font-semibold">Payment Date:</span> ${paymentDate}</p>
                    <p><span class="font-semibold">Payment Method:</span> ${trans.payment_method || 'N/A'}</p>
                </div>
                <div class="space-y-1 text-right">
                    <p><span class="font-semibold">Status:</span> ${(trans.payment_status || 'N/A').charAt(0).toUpperCase() + (trans.payment_status || 'N/A').slice(1)}</p>
                    <p><span class="font-semibold">Payment Type:</span> ${trans.payment_type || 'N/A'}</p>
                    <p><span class="font-semibold">Reference:</span> ${trans.transaction_id || 'N/A'}</p>
                </div>
            </div>
            
            <!-- Patient Information -->
            <div class="border-t border-slate-200 dark:border-slate-700 pt-4">
                <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Patient Information</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Patient Name</p>
                        <p class="text-sm font-medium text-slate-900 dark:text-white">${patientName}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Email</p>
                        <p class="text-sm font-medium text-slate-900 dark:text-white">${trans.patient_email || 'N/A'}</p>
                    </div>
                </div>
            </div>
            
            <!-- Appointment / Treatment Information -->
            <div class="border-t border-slate-200 dark:border-slate-700 pt-4">
                <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Appointment Information</h4>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Appointment ID</p>
                        <p class="text-sm font-medium text-slate-900 dark:text-white">${trans.appointment_id ? '#' + trans.appointment_id : 'N/A'}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Scheduled Date</p>
                        <p class="text-sm font-medium text-slate-900 dark:text-white">${scheduledDate}</p>
                    </div>
                    <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Treatment / Service</p>
                        <p class="text-sm font-medium text-slate-900 dark:text-white">${serviceName}</p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Dentist</p>
                        <p class="text-sm font-medium text-slate-900 dark:text-white">${staffName}</p>
                    </div>
                </div>
            </div>
            
            ${trans.transaction_id ? `
            <div class="border-t border-slate-200 dark:border-slate-700 pt-4">
                <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Transaction Reference</h4>
                <div class="grid grid-cols-2 gap-4">
                    ${trans.transaction_id ? `
                    <div>
                        <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Transaction ID</p>
                        <p class="text-sm font-medium text-slate-900 dark:text-white font-mono">${trans.transaction_id}</p>
                    </div>
                    ` : ''}
                </div>
            </div>
            ` : ''}

            <!-- Invoice-style breakdown (mirrors payments.php style but simplified) -->
            <div class="border-t border-slate-200 dark:border-slate-700 pt-4 mt-2">
                <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-3">Invoice Breakdown</h4>
                <div class="border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/60">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold text-slate-600 dark:text-slate-300">Description</th>
                                <th class="px-3 py-2 text-right font-semibold text-slate-600 dark:text-slate-300">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-slate-900/60">
                            <tr>
                                <td class="px-3 py-2 text-slate-800 dark:text-slate-100">Service Price</td>
                                <td class="px-3 py-2 text-right text-slate-800 dark:text-slate-100">â‚±${servicePrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            </tr>
                            ${totalDownpayment > 0 ? `
                            <tr>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">Less: Downpayment</td>
                                <td class="px-3 py-2 text-right text-slate-800 dark:text-slate-100">-â‚±${totalDownpayment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            </tr>
                            ` : ''}
                            <tr>
                                <td class="px-3 py-2 text-slate-800 dark:text-slate-100 font-semibold">Balance Before Discount</td>
                                <td class="px-3 py-2 text-right text-slate-800 dark:text-slate-100 font-semibold">â‚±${balanceBeforeDiscount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">${discountLabel}</td>
                                <td class="px-3 py-2 text-right text-slate-800 dark:text-slate-100">${discountAmount > 0 ? '-â‚±' + discountAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 'â‚±0.00'}</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 text-slate-800 dark:text-slate-100 font-semibold">Remaining Balance</td>
                                <td class="px-3 py-2 text-right text-slate-800 dark:text-slate-100 font-semibold">â‚±${remainingBalance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 text-slate-800 dark:text-slate-100">This Payment</td>
                                <td class="px-3 py-2 text-right text-slate-800 dark:text-slate-100">â‚±${paymentAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            </tr>
                            <tr>
                                <td class="px-3 py-2 text-slate-800 dark:text-slate-100">Balance After Payment</td>
                                <td class="px-3 py-2 text-right text-slate-800 dark:text-slate-100">â‚±${balanceAfterPayment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-slate-50 dark:bg-slate-800/60">
                            <tr>
                                <td class="px-3 py-2 text-right font-semibold text-slate-900 dark:text-white">Total Paid</td>
                                <td class="px-3 py-2 text-right font-bold text-primary">â‚±${paymentAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    `;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeTransactionModal(event) {
    if (event && event.target !== event.currentTarget) return;
    const modal = document.getElementById('transactionModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Print helper for transaction receipt
function printTransactionReceipt() {
    try {
        window.print();
    } catch (e) {
        console.error('Error printing receipt:', e);
        alert('Unable to open print dialog. Please try again.');
    }
}
</script>

<!-- Transaction Details Modal -->
<div id="transactionModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" onclick="closeTransactionModal(event)">
<div class="bg-white dark:bg-[#1a2634] rounded-xl shadow-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
<div id="transactionModalContent">
<!-- Content will be populated by JavaScript -->
</div>
</div>
</div>

<!-- Confirmation Modal for Logout -->
<div id="confirmModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" onclick="closeConfirmModal(event)">
<div class="bg-white dark:bg-[#1a2634] rounded-xl shadow-xl max-w-md w-full p-6" onclick="event.stopPropagation()">
<div id="confirmModalContent">
<!-- Content will be populated by JavaScript -->
</div>
<?php $extra_scripts = ob_get_clean(); include ROOT_PATH . '/views/layout/footer.php'; ?>
