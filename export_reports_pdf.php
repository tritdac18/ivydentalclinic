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
if ($user_type !== 'staff' || ($user_role !== 'Admin' && $user_role !== 'Manager' && $user_role !== 'Dentist')) {
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

if (!$user_info) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$admin_name = $user_info['first_name'] . ' ' . $user_info['last_name'];

// Get time period from request (default: this_month)
$time_period = $_GET['period'] ?? 'this_month';
$active_tab = $_GET['tab'] ?? 'sales';

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
        $period_label = 'This Week';
        break;
    case 'this_month':
        $start_date->modify('first day of this month');
        $start_date->setTime(0, 0, 0);
        $end_date->modify('last day of this month');
        $period_label = 'This Month';
        break;
    case 'last_30_days':
        $start_date->modify('-30 days');
        $start_date->setTime(0, 0, 0);
        // keep end_date as today
        $period_label = 'Last 30 Days';
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
        $period_label = 'Last Quarter';
        break;
    case 'this_year':
        $start_date->modify('first day of january this year');
        $start_date->setTime(0, 0, 0);
        $end_date->modify('last day of december this year');
        $period_label = 'This Year';
        break;
    default:
        $start_date->modify('first day of this month');
        $start_date->setTime(0, 0, 0);
        $period_label = 'This Month';
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

// Calculate previous period for comparison
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
    // Revenue
    $revenue_stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM tbl_payments 
        WHERE payment_status = 'Completed' 
        AND payment_date >= ? AND payment_date <= ?
    ");
    $revenue_stmt->bind_param("ss", $start_date_str, $end_date_str);
    $revenue_stmt->execute();
    $revenue_result = $revenue_stmt->get_result();
    if ($row = $revenue_result->fetch_assoc()) {
        $stats['revenue'] = floatval($row['total']);
    }
    $revenue_stmt->close();
    
    // Previous period revenue
    $revenue_prev_stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total 
        FROM tbl_payments 
        WHERE payment_status = 'Completed' 
        AND payment_date >= ? AND payment_date <= ?
    ");
    $revenue_prev_stmt->bind_param("ss", $prev_start_str, $prev_end_str);
    $revenue_prev_stmt->execute();
    $revenue_prev_result = $revenue_prev_stmt->get_result();
    if ($row = $revenue_prev_result->fetch_assoc()) {
        $stats['revenue_prev'] = floatval($row['total']);
    }
    $revenue_prev_stmt->close();
    
    // Daily Visits
    $visits_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tbl_appointments 
        WHERE status = 'Completed' 
        AND scheduled_datetime >= ? AND scheduled_datetime <= ?
    ");
    $visits_stmt->bind_param("ss", $start_date_str, $end_date_str);
    $visits_stmt->execute();
    $visits_result = $visits_stmt->get_result();
    if ($row = $visits_result->fetch_assoc()) {
        $stats['daily_visits'] = intval($row['count']);
    }
    $visits_stmt->close();
    
    // Previous period visits
    $visits_prev_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tbl_appointments 
        WHERE status = 'Completed' 
        AND scheduled_datetime >= ? AND scheduled_datetime <= ?
    ");
    $visits_prev_stmt->bind_param("ss", $prev_start_str, $prev_end_str);
    $visits_prev_stmt->execute();
    $visits_prev_result = $visits_prev_stmt->get_result();
    if ($row = $visits_prev_result->fetch_assoc()) {
        $stats['visits_prev'] = intval($row['count']);
    }
    $visits_prev_stmt->close();
    
    // Amount of Bookings: Count of all appointments based on scheduled date
    $bookings_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tbl_appointments 
        WHERE scheduled_datetime >= ? AND scheduled_datetime <= ?
    ");
    $bookings_stmt->bind_param("ss", $start_date_str, $end_date_str);
    $bookings_stmt->execute();
    $bookings_result = $bookings_stmt->get_result();
    if ($row = $bookings_result->fetch_assoc()) {
        $stats['bookings'] = intval($row['count']);
    }
    $bookings_stmt->close();
    
    // Previous period bookings (based on scheduled date)
    $bookings_prev_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tbl_appointments 
        WHERE scheduled_datetime >= ? AND scheduled_datetime <= ?
    ");
    $bookings_prev_stmt->bind_param("ss", $prev_start_str, $prev_end_str);
    $bookings_prev_stmt->execute();
    $bookings_prev_result = $bookings_prev_stmt->get_result();
    if ($row = $bookings_prev_result->fetch_assoc()) {
        $stats['bookings_prev'] = intval($row['count']);
    }
    $bookings_prev_stmt->close();
    
    // New Customers
    $customers_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tbl_patients 
        WHERE created_at >= ? AND created_at <= ?
    ");
    $customers_stmt->bind_param("ss", $start_date_str, $end_date_str);
    $customers_stmt->execute();
    $customers_result = $customers_stmt->get_result();
    if ($row = $customers_result->fetch_assoc()) {
        $stats['new_customers'] = intval($row['count']);
    }
    $customers_stmt->close();
    
    // Previous period customers
    $customers_prev_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM tbl_patients 
        WHERE created_at >= ? AND created_at <= ?
    ");
    $customers_prev_stmt->bind_param("ss", $prev_start_str, $prev_end_str);
    $customers_prev_stmt->execute();
    $customers_prev_result = $customers_prev_stmt->get_result();
    if ($row = $customers_prev_result->fetch_assoc()) {
        $stats['customers_prev'] = intval($row['count']);
    }
    $customers_prev_stmt->close();
    
    // Fetch transactions filtered by selected time period (based on payment_date/created_at)
    $transactions = [];
    $transactions_stmt = $conn->prepare("SELECT 
            p.payment_id,
            p.payment_type,
            p.amount,
            p.payment_method,
            p.payment_status,
            COALESCE(p.payment_date, p.created_at) as payment_date,
            p.created_at,
            pt.first_name as patient_first_name,
            pt.last_name as patient_last_name,
            COALESCE(sv.service_name, sv_inv.service_name, a.service_type, a_inv.service_type) as service_name,
            COALESCE(s_dentist.first_name, s_dentist_inv.first_name, s_validator.first_name) as staff_first_name,
            COALESCE(s_dentist.last_name, s_dentist_inv.last_name, s_validator.last_name) as staff_last_name
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
        WHERE p.created_at >= ? AND p.created_at <= ?
        ORDER BY p.payment_id DESC
        LIMIT 1000");
    
    $transactions_stmt->bind_param("ss", $start_date_str, $end_date_str);
    $transactions_stmt->execute();
    $transactions_result = $transactions_stmt->get_result();
    while ($row = $transactions_result->fetch_assoc()) {
        $transactions[] = $row;
    }
    $transactions_stmt->close();
    
    // Fetch appointments filtered by selected time period (based on created_at)
    $appointments = [];
    $appointments_stmt = $conn->prepare("SELECT 
            a.appointment_id,
            a.scheduled_datetime,
            a.status,
            a.price,
            a.created_at,
            p.first_name as patient_first_name,
            p.last_name as patient_last_name,
            s.first_name as dentist_first_name,
            s.last_name as dentist_last_name,
            sv.service_name
        FROM tbl_appointments a
        LEFT JOIN tbl_patients p ON a.patient_id = p.patient_id
        LEFT JOIN tbl_staff s ON a.dentist_id = s.staff_id
        LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
        WHERE a.scheduled_datetime >= ? AND a.scheduled_datetime <= ?
        ORDER BY a.appointment_id DESC
        LIMIT 1000");
    
    $appointments_stmt->bind_param("ss", $start_date_str, $end_date_str);
    $appointments_stmt->execute();
    $appointments_result = $appointments_stmt->get_result();
    while ($row = $appointments_result->fetch_assoc()) {
        $appointments[] = $row;
    }
    $appointments_stmt->close();
    
    $conn->close();
} catch (Exception $e) {
    error_log("Error fetching reports data: " . $e->getMessage());
}

// Calculate percentage changes
$revenue_change = $stats['revenue_prev'] > 0 ? (($stats['revenue'] - $stats['revenue_prev']) / $stats['revenue_prev']) * 100 : 0;
$visits_change = $stats['visits_prev'] > 0 ? (($stats['daily_visits'] - $stats['visits_prev']) / $stats['visits_prev']) * 100 : 0;
$bookings_change = $stats['bookings_prev'] > 0 ? (($stats['bookings'] - $stats['bookings_prev']) / $stats['bookings_prev']) * 100 : 0;
$customers_change = $stats['customers_prev'] > 0 ? (($stats['new_customers'] - $stats['customers_prev']) / $stats['customers_prev']) * 100 : 0;

// Always generate downloadable PDF using html2pdf.js (client-side)
header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Sales & Appointment Reports - Ivy Dental Clinic</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { text-align: center; color: #136dec; }
            h2 { color: #0d131b; margin-top: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f0f4f8; font-weight: bold; }
            .stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin: 20px 0; }
            .stat-box { border: 1px solid #ddd; padding: 15px; background-color: #f9fafb; }
            .stat-label { font-size: 12px; color: #64748b; }
            .stat-value { font-size: 24px; font-weight: bold; color: #0d131b; }
            @media print {
                body { margin: 0; }
                .no-print { display: none; }
            }
        </style>
    </head>
    <body>
        <h1>Ivy Dental Clinic</h1>
        <h2 style="text-align: center;">Sales & Appointment Reports</h2>
        <p style="text-align: center;"><strong>Period:</strong> <?php echo $period_label; ?> (<?php echo $start_date->format('M d, Y'); ?> - <?php echo $end_date->format('M d, Y'); ?>)</p>
        <p style="text-align: center;"><strong>Generated by:</strong> <?php echo htmlspecialchars($admin_name); ?> | <strong>Date:</strong> <?php echo date('M d, Y h:i A'); ?></p>
        
        <h2>Summary Statistics</h2>
        <div class="stats">
            <div class="stat-box">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value">₱<?php echo number_format($stats['revenue'], 2); ?></div>
                <div style="font-size: 11px; color: #64748b; margin-top: 5px;">
                    <?php echo $revenue_change >= 0 ? '+' : ''; ?><?php echo number_format($revenue_change, 1); ?>% vs previous period
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Daily Visits</div>
                <div class="stat-value"><?php echo number_format($stats['daily_visits']); ?></div>
                <div style="font-size: 11px; color: #64748b; margin-top: 5px;">
                    <?php echo $visits_change >= 0 ? '+' : ''; ?><?php echo number_format($visits_change, 1); ?>% vs previous period
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-label">Amount of Bookings</div>
                <div class="stat-value"><?php echo number_format($stats['bookings']); ?></div>
                <div style="font-size: 11px; color: #64748b; margin-top: 5px;">
                    <?php echo $bookings_change >= 0 ? '+' : ''; ?><?php echo number_format($bookings_change, 1); ?>% vs previous period
                </div>
            </div>
            <div class="stat-box">
                <div class="stat-label">New Customers</div>
                <div class="stat-value"><?php echo number_format($stats['new_customers']); ?></div>
                <div style="font-size: 11px; color: #64748b; margin-top: 5px;">
                    <?php echo $customers_change >= 0 ? '+' : ''; ?><?php echo number_format($customers_change, 1); ?>% vs previous period
                </div>
            </div>
        </div>
        
        <h2>Sales/Transactions</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Patient</th>
                    <th>Dentist</th>
                    <th>Treatment</th>
                    <th>Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transactions)): ?>
                <tr>
                    <td colspan="7" style="text-align: center;">No transactions found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($transactions as $trans): ?>
                <tr>
                    <td>#<?php echo htmlspecialchars($trans['payment_id']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($trans['payment_date'])); ?></td>
                    <td><?php 
                        if ($trans['patient_first_name'] && $trans['patient_last_name']) {
                            echo htmlspecialchars($trans['patient_first_name'] . ' ' . $trans['patient_last_name']);
                        } else {
                            echo 'N/A';
                        }
                    ?></td>
                    <td><?php 
                        if ($trans['staff_first_name'] && $trans['staff_last_name']) {
                            echo 'Dr. ' . htmlspecialchars($trans['staff_first_name'] . ' ' . $trans['staff_last_name']);
                        } else {
                            echo 'N/A';
                        }
                    ?></td>
                    <td><?php echo htmlspecialchars($trans['service_name'] ?? 'N/A'); ?></td>
                    <td>₱<?php echo number_format($trans['amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars($trans['payment_status']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <h2>Appointment History</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date & Time</th>
                    <th>Patient</th>
                    <th>Dentist</th>
                    <th>Service</th>
                    <th>Status</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($appointments)): ?>
                <tr>
                    <td colspan="7" style="text-align: center;">No appointments found</td>
                </tr>
                <?php else: ?>
                <?php foreach ($appointments as $apt): ?>
                <tr>
                    <td>#<?php echo htmlspecialchars($apt['appointment_id']); ?></td>
                    <td><?php 
                        $apt_date = new DateTime($apt['scheduled_datetime']);
                        echo $apt_date->format('M d, Y h:i A');
                    ?></td>
                    <td><?php 
                        if ($apt['patient_first_name'] && $apt['patient_last_name']) {
                            echo htmlspecialchars($apt['patient_first_name'] . ' ' . $apt['patient_last_name']);
                        } else {
                            echo 'N/A';
                        }
                    ?></td>
                    <td><?php 
                        if ($apt['dentist_first_name'] && $apt['dentist_last_name']) {
                            echo 'Dr. ' . htmlspecialchars($apt['dentist_first_name'] . ' ' . $apt['dentist_last_name']);
                        } else {
                            echo 'N/A';
                        }
                    ?></td>
                    <td><?php echo htmlspecialchars($apt['service_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($apt['status']); ?></td>
                    <td>₱<?php echo number_format($apt['price'] ?? 0, 2); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <script>
            window.onload = function() {
                // Get the element to convert
                const element = document.body;
                const filename = 'reports_<?php echo date('Y-m-d'); ?>.pdf';
                
                // Configure html2pdf options
                const options = {
                    margin: [10, 10, 10, 10],
                    filename: filename,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 2, useCORS: true },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                };
                
                // Generate and download PDF
                html2pdf().set(options).from(element).save().then(function() {
                    // Close the window after PDF is downloaded
                    setTimeout(function() {
                        window.close();
                    }, 1000);
                });
            };
        </script>
    </body>
    </html>
    <?php
    exit();
?>

