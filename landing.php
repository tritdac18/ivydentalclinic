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

try {
    if ($user_type === 'patient') {
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
    } else {
        // Fetch staff data from database using prepared statement
        $stmt = $conn->prepare("SELECT staff_id, first_name, last_name, email, role FROM tbl_staff WHERE staff_id = ? LIMIT 1");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user_info = $result->fetch_assoc();
            $_SESSION['user_role'] = $user_info['role'];
            $user_role = $user_info['role'];
        } else {
            // User not found in database - session invalid, logout
            session_destroy();
            header("Location: login.php");
            exit();
        }
        $stmt->close();
    }
    
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
$user_id = $user_type === 'patient' ? $user_info['patient_id'] : $user_info['staff_id'];

// Redirect admins/managers and dentists to manager dashboard
if ($user_type === 'staff' && ($user_role === 'Manager' || $user_role === 'Admin' || $user_role === 'Dentist')) {
    header("Location: admin_dashboard.php");
    exit();
}

// Portal label for sidebar (defensive: in case a staff user ever hits this page)
if ($user_type === 'staff') {
    if ($user_role === 'Dentist') {
        $portal_label = 'Dentist Portal';
    } elseif ($user_role === 'Manager') {
        $portal_label = 'Manager Portal';
    } elseif ($user_role === 'Admin') {
        $portal_label = 'Admin Portal';
    } else {
        $portal_label = 'Staff Portal';
    }
} else {
    $portal_label = 'Patient Portal';
}

// Fetch upcoming appointments for the logged-in patient
$upcoming_appointments = [];
$appointment_error = null;
if ($user_type === 'patient' && isset($user_info['patient_id'])) {
    try {
        $conn = getDBConnection();
        // Get appointments that are scheduled for future dates or today, ordered by date
        // Using DATE() function to compare dates properly with DATETIME field
        // Check if unified payments table exists
        $table_check = $conn->query("SHOW TABLES LIKE 'tbl_payments'");
        $has_payment_type = false;
        if ($table_check && $table_check->num_rows > 0) {
            $col_check = $conn->query("SHOW COLUMNS FROM tbl_payments LIKE 'payment_type'");
            $has_payment_type = $col_check && $col_check->num_rows > 0;
        }
        
        // Only show appointments with 'Scheduled' status
        if ($has_payment_type) {
            $appointment_stmt = $conn->prepare("
                SELECT 
                    a.appointment_id,
                    a.scheduled_datetime,
                    a.duration_minutes,
                    a.status,
                    a.price,
                    sv.service_id,
                    sv.service_name,
                    s.first_name as dentist_first_name,
                    s.last_name as dentist_last_name
                FROM tbl_appointments a
                INNER JOIN tbl_staff s ON a.dentist_id = s.staff_id
                INNER JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
                WHERE a.patient_id = ? 
                AND DATE(a.scheduled_datetime) >= CURDATE()
                AND a.status = 'Scheduled'
                ORDER BY a.scheduled_datetime ASC
                LIMIT 10
            ");
        } else {
            // Fallback for old table structure
        $appointment_stmt = $conn->prepare("
            SELECT 
                a.appointment_id,
                a.scheduled_datetime,
                a.duration_minutes,
                a.status,
                a.price,
                    sv.service_id,
                    sv.service_name,
                s.first_name as dentist_first_name,
                s.last_name as dentist_last_name
            FROM tbl_appointments a
                INNER JOIN tbl_staff s ON a.dentist_id = s.staff_id
                INNER JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
            WHERE a.patient_id = ? 
            AND DATE(a.scheduled_datetime) >= CURDATE()
            AND a.status = 'Scheduled'
            ORDER BY a.scheduled_datetime ASC
            LIMIT 10
        ");
        }
        $appointment_stmt->bind_param("i", $user_info['patient_id']);
        $appointment_stmt->execute();
        $appointment_result = $appointment_stmt->get_result();
        
        while ($row = $appointment_result->fetch_assoc()) {
            $upcoming_appointments[] = $row;
        }
        $appointment_stmt->close();
        $conn->close();
    } catch (Exception $e) {
        // Store error for debugging
        $appointment_error = $e->getMessage();
        $upcoming_appointments = [];
    } catch (mysqli_sql_exception $e) {
        // Store SQL error for debugging
        $appointment_error = $e->getMessage();
        $upcoming_appointments = [];
    }
}

// Fetch invoices for the logged-in patient
$pending_invoices = [];
$paid_invoices = [];
$invoice_error = null;
if ($user_type === 'patient' && isset($user_info['patient_id'])) {
    try {
        $conn = getDBConnection();
        // Check if tbl_invoices exists
        $table_check = $conn->query("SHOW TABLES LIKE 'tbl_invoices'");
        if ($table_check && $table_check->num_rows > 0) {
            // Fetch all invoices for this patient
            $invoice_stmt = $conn->prepare("
                SELECT 
                    i.invoice_id,
                    i.patient_id,
                    i.appointment_id,
                    i.total_amount,
                    i.payment_status,
                    i.generated_at,
                    a.scheduled_datetime,
                    sv.service_name
                FROM tbl_invoices i
                LEFT JOIN tbl_appointments a ON i.appointment_id = a.appointment_id
                LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
                WHERE i.patient_id = ?
                ORDER BY i.generated_at DESC
            ");
            $invoice_stmt->bind_param("i", $user_info['patient_id']);
            $invoice_stmt->execute();
            $invoice_result = $invoice_stmt->get_result();
            
            while ($row = $invoice_result->fetch_assoc()) {
                if ($row['payment_status'] === 'Paid') {
                    $paid_invoices[] = $row;
                } else {
                    $pending_invoices[] = $row;
                }
            }
            $invoice_stmt->close();
        }
        $conn->close();
    } catch (Exception $e) {
        $invoice_error = $e->getMessage();
    } catch (mysqli_sql_exception $e) {
        $invoice_error = $e->getMessage();
    }
}

// Fetch treatment plan progress for the logged-in patient
$treatment_plans = [];
$treatment_plan_error = null;
if ($user_type === 'patient' && isset($user_info['patient_id'])) {
    try {
        $conn = getDBConnection();
        
        // First, get all appointments with services that have maintenance plans
        // This includes both completed appointments (with maintenance records) and scheduled appointments
        $appointments_with_maintenance = [];
        $appt_stmt = $conn->prepare("
            SELECT 
                a.appointment_id,
                a.patient_id,
                a.service_id,
                a.scheduled_datetime,
                a.status as appointment_status,
                sv.service_name,
                sv.maintenance_period_months,
                sv.maintenance_interval
            FROM tbl_appointments a
            INNER JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
            WHERE a.patient_id = ?
            AND sv.maintenance_period_months IS NOT NULL
            AND sv.maintenance_interval IS NOT NULL
            AND a.status IN ('Scheduled', 'Confirmed', 'Completed')
            ORDER BY a.scheduled_datetime ASC
        ");
        $appt_stmt->bind_param("i", $user_info['patient_id']);
        $appt_stmt->execute();
        $appt_result = $appt_stmt->get_result();
        
        while ($appt_row = $appt_result->fetch_assoc()) {
            $appointments_with_maintenance[] = $appt_row;
        }
        $appt_stmt->close();
        
        // Get maintenance records if table exists
        $maintenance_records = [];
        $table_check = $conn->query("SHOW TABLES LIKE 'tbl_patient_maintenance'");
        if ($table_check && $table_check->num_rows > 0) {
            $maintenance_stmt = $conn->prepare("
                SELECT 
                    pm.maintenance_id,
                    pm.patient_id,
                    pm.service_id,
                    pm.related_appointment_id,
                    pm.last_visit_date,
                    pm.recommended_next_visit,
                    pm.status,
                    pm.notes,
                    pm.created_at
                FROM tbl_patient_maintenance pm
                WHERE pm.patient_id = ? 
                AND pm.status != 'cancelled'
            ");
            $maintenance_stmt->bind_param("i", $user_info['patient_id']);
            $maintenance_stmt->execute();
            $maintenance_result = $maintenance_stmt->get_result();
            
            while ($maint_row = $maintenance_result->fetch_assoc()) {
                $maintenance_records[$maint_row['service_id']] = $maint_row;
            }
            $maintenance_stmt->close();
        }
        
        // Group appointments by service_id to avoid duplicates (one treatment plan per service)
        $grouped_appointments = [];
        foreach ($appointments_with_maintenance as $appt) {
            $service_id = $appt['service_id'];
            if (!isset($grouped_appointments[$service_id])) {
                $grouped_appointments[$service_id] = [
                    'service_id' => $service_id,
                    'service_name' => $appt['service_name'],
                    'maintenance_period_months' => $appt['maintenance_period_months'],
                    'maintenance_interval' => $appt['maintenance_interval'],
                    'appointments' => []
                ];
            }
            $grouped_appointments[$service_id]['appointments'][] = $appt;
        }
        
        // Process each service (one treatment plan per service)
        foreach ($grouped_appointments as $service_id => $service_data) {
            $maintenance_record = $maintenance_records[$service_id] ?? null;
            
            // Find the earliest appointment date for this service
            $earliest_appointment = null;
            foreach ($service_data['appointments'] as $appt) {
                $appt_date = new DateTime($appt['scheduled_datetime']);
                if ($earliest_appointment === null || $appt_date < $earliest_appointment) {
                    $earliest_appointment = $appt_date;
                }
            }
            
            // Determine start date (earliest appointment date or maintenance record creation)
            $start_date = $earliest_appointment;
            if ($maintenance_record && $maintenance_record['created_at']) {
                $maint_created = new DateTime($maintenance_record['created_at']);
                if ($maint_created < $start_date) {
                    $start_date = $maint_created;
                }
            }
            
            // Calculate end date
            $end_date = clone $start_date;
            if ($service_data['maintenance_period_months']) {
                $end_date->modify('+' . $service_data['maintenance_period_months'] . ' months');
            }
            
            // Calculate progress based on current date
            $now = new DateTime();
            $total_days = $start_date->diff($end_date)->days;
            $elapsed_days = $start_date->diff($now)->days;
            $progress_percentage = null;
            if ($total_days > 0) {
                $progress_percentage = min(100, max(0, ($elapsed_days / $total_days) * 100));
            }
            
            // Calculate expected visits based on maintenance interval
            $expected_visits = [];
            if ($service_data['maintenance_interval'] && $service_data['maintenance_period_months']) {
                $interval_map = [
                    'weekly' => 7,
                    'biweekly' => 14,
                    'monthly' => 30,
                    'bimonthly' => 60,
                    'quarterly' => 90,
                    'semiannually' => 180,
                    'annually' => 365
                ];
                
                $interval_days = $interval_map[strtolower($service_data['maintenance_interval'])] ?? 30;
                $current_date = clone $start_date;
                $visit_number = 1;
                
                while ($current_date <= $end_date && $visit_number <= 50) { // Limit to 50 visits max
                    $expected_visits[] = [
                        'date' => clone $current_date,
                        'visit_number' => $visit_number,
                        'is_past' => $current_date < $now,
                        'is_upcoming' => $current_date >= $now && $current_date <= $end_date
                    ];
                    
                    // Add interval days
                    if ($interval_days < 30) {
                        $current_date->modify('+' . $interval_days . ' days');
                    } else {
                        $months = round($interval_days / 30);
                        $current_date->modify('+' . $months . ' months');
                    }
                    $visit_number++;
                }
            }
            
            // Get completed appointments for this service to mark visits as completed
            $completed_visits = [];
            $completed_visits_data = [];
            $completed_stmt = $conn->prepare("
                SELECT 
                    a.appointment_id, 
                    a.scheduled_datetime, 
                    a.price,
                    a.duration_minutes,
                    a.status,
                    s.first_name as dentist_first_name,
                    s.last_name as dentist_last_name,
                    s.staff_id as dentist_id
                FROM tbl_appointments a
                LEFT JOIN tbl_staff s ON a.dentist_id = s.staff_id
                WHERE a.patient_id = ?
                AND a.service_id = ?
                AND a.status = 'Completed'
                ORDER BY a.scheduled_datetime DESC
            ");
            $completed_stmt->bind_param("ii", $user_info['patient_id'], $service_id);
            $completed_stmt->execute();
            $completed_result = $completed_stmt->get_result();
            while ($comp_row = $completed_result->fetch_assoc()) {
                $comp_date = new DateTime($comp_row['scheduled_datetime']);
                $completed_visits[] = $comp_date;
                $dentist_name = '';
                if ($comp_row['dentist_first_name'] && $comp_row['dentist_last_name']) {
                    $dentist_name = 'Dr. ' . trim($comp_row['dentist_first_name'] . ' ' . $comp_row['dentist_last_name']);
                } elseif ($comp_row['dentist_first_name']) {
                    $dentist_name = 'Dr. ' . $comp_row['dentist_first_name'];
                }
                $completed_visits_data[] = [
                    'appointment_id' => $comp_row['appointment_id'],
                    'date' => $comp_date->format('Y-m-d H:i:s'),
                    'date_formatted' => $comp_date->format('M d, Y'),
                    'time_formatted' => $comp_date->format('h:i A'),
                    'price' => $comp_row['price'] ?? null,
                    'duration_minutes' => $comp_row['duration_minutes'] ?? null,
                    'status' => $comp_row['status'] ?? 'Completed',
                    'dentist_name' => $dentist_name,
                    'dentist_first_name' => $comp_row['dentist_first_name'] ?? null,
                    'dentist_last_name' => $comp_row['dentist_last_name'] ?? null,
                    'dentist_id' => $comp_row['dentist_id'] ?? null
                ];
            }
            $completed_stmt->close();
            
            // Mark expected visits as completed if they match completed appointments
            $completed_visits_count = 0;
            foreach ($expected_visits as &$visit) {
                $visit['is_completed'] = false;
                foreach ($completed_visits as $comp_visit) {
                    $diff_days = abs($visit['date']->diff($comp_visit)->days);
                    if ($diff_days <= 7) { // Within 7 days, consider it completed
                        $visit['is_completed'] = true;
                        $completed_visits_count++;
                        break;
                    }
                }
            }
            unset($visit);
            
            // Recalculate progress based on completed visits vs total expected visits
            // Primary metric: visits completed (more accurate)
            // Secondary metric: time elapsed (fallback if no visits yet)
            if (count($expected_visits) > 0) {
                $visit_progress = ($completed_visits_count / count($expected_visits)) * 100;
                // Use visit-based progress as primary, but also consider time elapsed
                if ($progress_percentage !== null && $completed_visits_count > 0) {
                    // If visits are completed, prioritize visit progress (70%) with time progress (30%)
                    $progress_percentage = ($visit_progress * 0.7) + ($progress_percentage * 0.3);
                } elseif ($completed_visits_count > 0) {
                    // Only visit progress available
                    $progress_percentage = $visit_progress;
                }
                // If no visits completed yet, keep time-based progress (already calculated above)
            }
            
            // Ensure progress is always set (default to 0 if nothing calculated)
            if ($progress_percentage === null) {
                $progress_percentage = 0;
            }
            
            // Round to 1 decimal place
            $progress_percentage = round($progress_percentage, 1);
            
            // Determine next recommended visit
            $next_visit_date = null;
            if ($maintenance_record && $maintenance_record['recommended_next_visit']) {
                $next_visit_date = new DateTime($maintenance_record['recommended_next_visit']);
            } else {
                // Calculate from last visit or start date
                $last_visit = $maintenance_record && $maintenance_record['last_visit_date'] 
                    ? new DateTime($maintenance_record['last_visit_date'])
                    : $start_date;
                
                if ($service_data['maintenance_interval']) {
                    $interval = strtolower($service_data['maintenance_interval']);
                    $next_visit = clone $last_visit;
                    
                    switch ($interval) {
                        case 'weekly':
                            $next_visit->modify('+1 week');
                            break;
                        case 'biweekly':
                            $next_visit->modify('+2 weeks');
                            break;
                        case 'monthly':
                            $next_visit->modify('+1 month');
                            break;
                        case 'bimonthly':
                            $next_visit->modify('+2 months');
                            break;
                        case 'quarterly':
                            $next_visit->modify('+3 months');
                            break;
                        case 'semiannually':
                            $next_visit->modify('+6 months');
                            break;
                        case 'annually':
                            $next_visit->modify('+1 year');
                            break;
                    }
                    
                    if ($next_visit <= $end_date) {
                        $next_visit_date = $next_visit;
                    }
                }
            }
            
            // Determine status
            $status = $maintenance_record && isset($maintenance_record['status']) ? $maintenance_record['status'] : 'pending';
            if ($next_visit_date && $next_visit_date < $now) {
                $status = 'overdue';
            } elseif ($progress_percentage !== null && $progress_percentage >= 100) {
                $status = 'done';
            }
            
            // Get the first appointment ID for reference
            $first_appointment_id = $service_data['appointments'][0]['appointment_id'];
            
            $treatment_plans[] = [
                'maintenance_id' => $maintenance_record ? ($maintenance_record['maintenance_id'] ?? null) : null,
                'service_id' => $service_id,
                'service_name' => $service_data['service_name'],
                'maintenance_period_months' => $service_data['maintenance_period_months'],
                'maintenance_interval' => $service_data['maintenance_interval'],
                'start_date' => $start_date,
                'end_date' => $end_date,
                'last_visit_date' => $maintenance_record && isset($maintenance_record['last_visit_date']) && $maintenance_record['last_visit_date']
                    ? new DateTime($maintenance_record['last_visit_date']) 
                    : null,
                'recommended_next_visit' => $next_visit_date,
                'status' => $status,
                'notes' => $maintenance_record ? ($maintenance_record['notes'] ?? null) : null,
                'progress_percentage' => $progress_percentage,
                'expected_visits' => $expected_visits,
                'completed_visits_count' => $completed_visits_count,
                'total_expected_visits' => count($expected_visits),
                'related_appointment_id' => $first_appointment_id
            ];
        }
        
        // Sort by next visit date (upcoming first) or end date
        usort($treatment_plans, function($a, $b) {
            $a_date = $a['recommended_next_visit'] ?? $a['end_date'];
            $b_date = $b['recommended_next_visit'] ?? $b['end_date'];
            return $a_date <=> $b_date;
        });
        
        $conn->close();
    } catch (Exception $e) {
        $treatment_plan_error = $e->getMessage();
        error_log("Treatment plan error: " . $e->getMessage());
    } catch (mysqli_sql_exception $e) {
        $treatment_plan_error = $e->getMessage();
        error_log("Treatment plan SQL error: " . $e->getMessage());
    }
}
?>
<?php
$page_title = 'Patient Dashboard';
include ROOT_PATH . '/views/layout/header.php';
?>
<div class="flex min-h-screen">
    <?php include ROOT_PATH . '/views/layout/sidebar_patient.php'; ?>
    <div class="flex-1 flex flex-col lg:ml-64">
        <?php include ROOT_PATH . '/views/layout/topbar.php'; ?>
<main class="flex-grow w-full max-w-7xl mx-auto px-4 md:px-8 py-8">
<div class="max-w-6xl mx-auto flex flex-col gap-8">
<?php
if (isset($_SESSION['booking_success'])) {
    echo '<div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg dark:bg-green-900/20 dark:border-green-800 dark:text-green-400">';
    echo '<p class="text-sm font-medium">' . htmlspecialchars($_SESSION['booking_success']) . '</p>';
    unset($_SESSION['booking_success']);
    echo '</div>';
}
?>
<div class="flex flex-col md:flex-row justify-between items-start md:items-end gap-4">
<div>
<h1 class="text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Welcome back, <?php echo htmlspecialchars($user_first_name); ?> ðŸ‘‹</h1>
<p class="text-slate-500 mt-2 dark:text-slate-400">Manage your profile, view appointments, and access your dashboard.</p>
</div>
<div class="flex gap-3">
<a class="flex items-center gap-2 bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-900 font-semibold transition-colors px-4 py-2 rounded-lg shadow-sm border border-slate-200 dark:bg-[#1c2430] dark:border-gray-700 dark:text-slate-300 dark:hover:text-white" href="profile.php">
<span class="material-symbols-outlined text-lg">person</span>
<span>My Profile</span>
</a>
<a class="flex items-center gap-2 bg-primary text-white hover:bg-blue-700 font-semibold transition-colors px-4 py-2 rounded-lg shadow-sm border border-transparent" href="book_appointment.php">
<span class="material-symbols-outlined text-lg">add</span>
<span>New Appointment</span>
</a>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
<div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm dark:bg-[#1c2430] dark:border-gray-700 flex flex-col h-full col-span-1 md:col-span-2">
<div class="flex justify-between items-center mb-6">
<h3 class="text-lg font-bold text-slate-900 dark:text-white">Upcoming Appointments</h3>
<a class="text-primary text-sm font-semibold hover:underline" href="appointments.php">View All</a>
</div>
<div class="mb-4">
<div class="relative">
<input type="text" id="appointmentSearch" class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Search appointments..."/>
<span class="material-symbols-outlined absolute right-3 top-2.5 text-slate-400 text-lg">search</span>
</div>
</div>
<div class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
<p class="text-xs text-amber-700 dark:text-amber-400 flex items-center gap-2">
<span class="material-symbols-outlined text-sm">info</span>
<span>Minor Patients must bring their registered guardians during the appointment.</span>
</p>
</div>
<?php 
// Debug: Show error if query failed (remove in production)
if ($appointment_error && isset($_GET['debug'])) {
    echo '<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">';
    echo '<p class="text-sm">Error: ' . htmlspecialchars($appointment_error) . '</p>';
    echo '<p class="text-xs mt-1">Patient ID: ' . htmlspecialchars($user_info['patient_id'] ?? 'N/A') . '</p>';
    echo '</div>';
}
?>
<?php if (empty($upcoming_appointments)): ?>
<div class="flex items-center justify-center p-8 text-center border-2 border-dashed border-slate-200 rounded-lg dark:border-slate-700">
<div>
<span class="material-symbols-outlined text-slate-300 text-4xl mb-2">event_available</span>
<p class="text-slate-500 text-sm">No upcoming appointments scheduled.</p>
<?php if (isset($_GET['debug'])): ?>
<p class="text-xs text-slate-400 mt-2">Patient ID: <?php echo htmlspecialchars($user_info['patient_id'] ?? 'N/A'); ?></p>
<?php endif; ?>
</div>
</div>
<?php else: ?>
<div class="overflow-x-auto">
<table class="w-full">
<thead>
<tr class="border-b border-slate-200 dark:border-slate-700">
<th class="text-left py-3 px-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Date & Time</th>
<th class="text-left py-3 px-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Service</th>
<th class="text-left py-3 px-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Dentist</th>
<th class="text-left py-3 px-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Status</th>
</tr>
</thead>
<tbody>
<?php foreach ($upcoming_appointments as $appointment): 
    $datetime = new DateTime($appointment['scheduled_datetime']);
    $formatted_date = $datetime->format('M d, Y');
    $formatted_time = $datetime->format('h:i A');
    $dentist_name = trim(($appointment['dentist_first_name'] ?? '') . ' ' . ($appointment['dentist_last_name'] ?? ''));
    if (empty(trim($dentist_name))) {
        $dentist_name = 'TBD';
    } else {
        $dentist_name = 'Dr. ' . $dentist_name;
    }
    
    // Status badge colors
    $status_class = '';
    $status_text = ucfirst(strtolower($appointment['status']));
    switch(strtolower($appointment['status'])) {
        case 'confirmed':
            $status_class = 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
            break;
        case 'scheduled':
            $status_class = 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
            break;
        case 'pending':
            $status_class = 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400';
            break;
        default:
            $status_class = 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400';
    }
?>
<tr class="border-b border-slate-100 dark:border-slate-800 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors appointment-row" data-search-text="<?php echo htmlspecialchars(strtolower($formatted_date . ' ' . $formatted_time . ' ' . ($appointment['service_name'] ?? '') . ' ' . $dentist_name . ' ' . $status_text)); ?>">
<td class="py-3 px-4">
<div class="flex flex-col">
<span class="text-sm font-medium text-slate-900 dark:text-white"><?php echo htmlspecialchars($formatted_date); ?></span>
<span class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($formatted_time); ?></span>
</div>
</td>
<td class="py-3 px-4">
<div class="flex flex-col">
<span class="text-sm font-medium text-slate-900 dark:text-white"><?php echo htmlspecialchars($appointment['service_name'] ?? 'N/A'); ?></span>
<?php if (isset($appointment['price']) && $appointment['price'] > 0): ?>
<span class="text-xs text-slate-500 dark:text-slate-400">â‚±<?php echo number_format($appointment['price'], 2); ?></span>
<?php endif; ?>
</div>
</td>
<td class="py-3 px-4">
<span class="text-sm text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($dentist_name); ?></span>
</td>
<td class="py-3 px-4">
<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium <?php echo $status_class; ?>">
<?php echo htmlspecialchars($status_text); ?>
</span>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
</div>
<div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm dark:bg-[#1c2430] dark:border-gray-700 flex flex-col h-full">
<div class="flex justify-between items-center mb-4">
<h3 class="text-lg font-bold text-slate-900 dark:text-white">Transactions</h3>
</div>
<div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
<p class="text-xs text-blue-700 dark:text-blue-400 flex items-center gap-2">
<span class="material-symbols-outlined text-sm">info</span>
<span>Please head to the counter to process your invoices.</span>
</p>
</div>
<div class="mb-4">
<div class="relative">
<input type="text" id="transactionSearch" class="w-full rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white px-4 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Search transactions..."/>
<span class="material-symbols-outlined absolute right-3 top-2.5 text-slate-400 text-lg">search</span>
</div>
</div>
<div class="flex gap-2 mb-4 border-b border-slate-200 dark:border-slate-700">
<button class="tab-button px-4 py-2 text-sm font-semibold border-b-2 border-primary text-primary transition-colors" data-tab="pending">
Pending Invoices
</button>
<button class="tab-button px-4 py-2 text-sm font-semibold text-slate-500 dark:text-slate-400 border-b-2 border-transparent hover:text-slate-700 dark:hover:text-slate-300 transition-colors" data-tab="paid">
Paid Invoices
</button>
</div>
<div id="pendingTab" class="tab-content">
<?php if (empty($pending_invoices)): ?>
<div class="flex items-center justify-center p-8 text-center border-2 border-dashed border-slate-200 rounded-lg dark:border-slate-700">
<div>
<span class="material-symbols-outlined text-slate-300 text-4xl mb-2">receipt_long</span>
<p class="text-slate-500 text-sm">No pending invoices.</p>
</div>
</div>
<?php else: ?>
<div class="space-y-2 max-h-[400px] overflow-y-auto">
<?php foreach ($pending_invoices as $invoice): 
    $invoice_id_display = 'INV-' . date('Y', strtotime($invoice['generated_at'])) . '-' . str_pad($invoice['invoice_id'], 3, '0', STR_PAD_LEFT);
    $invoice_date = date('M d, Y', strtotime($invoice['generated_at']));
    $search_text = strtolower($invoice_id_display . ' ' . ($invoice['service_name'] ?? '') . ' ' . $invoice_date . ' ' . number_format($invoice['total_amount'], 2));
?>
<div class="invoice-row p-3 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer transition-colors" data-search-text="<?php echo htmlspecialchars($search_text); ?>" onclick="viewInvoiceDetails(<?php echo htmlspecialchars(json_encode($invoice)); ?>)">
<div class="flex justify-between items-start">
<div class="flex-1">
<p class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars($invoice_id_display); ?></p>
<p class="text-xs text-slate-500 dark:text-slate-400 mt-1"><?php echo htmlspecialchars($invoice['service_name'] ?? 'Appointment'); ?> â€¢ <?php echo htmlspecialchars($invoice_date); ?></p>
</div>
<div class="text-right">
<p class="text-sm font-bold text-slate-900 dark:text-white">â‚±<?php echo number_format($invoice['total_amount'], 2); ?></p>
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 mt-1">
<?php echo htmlspecialchars($invoice['payment_status']); ?>
</span>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<div id="paidTab" class="tab-content hidden">
<?php if (empty($paid_invoices)): ?>
<div class="flex items-center justify-center p-8 text-center border-2 border-dashed border-slate-200 rounded-lg dark:border-slate-700">
<div>
<span class="material-symbols-outlined text-slate-300 text-4xl mb-2">receipt_long</span>
<p class="text-slate-500 text-sm">No paid invoices.</p>
</div>
</div>
<?php else: ?>
<div class="space-y-2 max-h-[400px] overflow-y-auto">
<?php foreach ($paid_invoices as $invoice): 
    $invoice_id_display = 'INV-' . date('Y', strtotime($invoice['generated_at'])) . '-' . str_pad($invoice['invoice_id'], 3, '0', STR_PAD_LEFT);
    $invoice_date = date('M d, Y', strtotime($invoice['generated_at']));
    $search_text = strtolower($invoice_id_display . ' ' . ($invoice['service_name'] ?? '') . ' ' . $invoice_date . ' ' . number_format($invoice['total_amount'], 2));
?>
<div class="invoice-row p-3 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer transition-colors" data-search-text="<?php echo htmlspecialchars($search_text); ?>" onclick="viewInvoiceDetails(<?php echo htmlspecialchars(json_encode($invoice)); ?>)">
<div class="flex justify-between items-start">
<div class="flex-1">
<p class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars($invoice_id_display); ?></p>
<p class="text-xs text-slate-500 dark:text-slate-400 mt-1"><?php echo htmlspecialchars($invoice['service_name'] ?? 'Appointment'); ?> â€¢ <?php echo htmlspecialchars($invoice_date); ?></p>
</div>
<div class="text-right">
<p class="text-sm font-bold text-slate-900 dark:text-white">â‚±<?php echo number_format($invoice['total_amount'], 2); ?></p>
<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 mt-1">
<?php echo htmlspecialchars($invoice['payment_status']); ?>
</span>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</div>
</div>
<?php if ($user_type === 'patient' && !empty($treatment_plans)): ?>
<div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm dark:bg-[#1c2430] dark:border-gray-700">
<div class="flex justify-between items-center mb-6">
<h3 class="text-lg font-bold text-slate-900 dark:text-white">Treatment Plan Progress Monitor</h3>
</div>
<div class="space-y-6">
<?php foreach ($treatment_plans as $plan): 
    $now = new DateTime();
    $next_visit_date = $plan['recommended_next_visit'];
    $last_visit_date = $plan['last_visit_date'];
    $start_date = $plan['start_date'];
    $end_date = $plan['end_date'];
    $is_overdue = $next_visit_date && $next_visit_date < $now;
    $is_completed = $plan['status'] === 'done' || ($plan['progress_percentage'] !== null && $plan['progress_percentage'] >= 100);
    
    $status_color = 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400';
    $status_text = ucfirst($plan['status']);
    
    if ($is_overdue) {
        $status_color = 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400';
        $status_text = 'Overdue';
    } elseif ($is_completed) {
        $status_color = 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';
        $status_text = 'Completed';
    }
    
    // Calculate days remaining
    $days_remaining = null;
    if ($end_date > $now) {
        $days_remaining = $now->diff($end_date)->days;
    }
?>
<div class="p-5 rounded-lg border border-slate-200 dark:border-slate-700 <?php echo $is_overdue ? 'bg-red-50 dark:bg-red-900/10 border-red-200 dark:border-red-800' : ($is_completed ? 'bg-green-50 dark:bg-green-900/10 border-green-200 dark:border-green-800' : 'bg-slate-50 dark:bg-slate-800/50'); ?>">
<div class="flex justify-between items-start mb-4">
<div class="flex-1">
<h4 class="text-lg font-bold text-slate-900 dark:text-white mb-2"><?php echo htmlspecialchars($plan['service_name']); ?></h4>
<div class="flex flex-wrap gap-3 text-xs text-slate-600 dark:text-slate-400">
<?php if ($plan['maintenance_period_months']): ?>
<span class="flex items-center gap-1">
<span class="material-symbols-outlined text-sm">calendar_today</span>
<span>Duration: <?php echo $plan['maintenance_period_months']; ?> months</span>
</span>
<?php endif; ?>
<?php if ($plan['maintenance_interval']): ?>
<span class="flex items-center gap-1">
<span class="material-symbols-outlined text-sm">schedule</span>
<span>Interval: <?php echo ucfirst(str_replace(['_', '-'], ' ', $plan['maintenance_interval'])); ?></span>
</span>
<?php endif; ?>
<?php if ($plan['total_expected_visits'] > 0): ?>
<span class="flex items-center gap-1">
<span class="material-symbols-outlined text-sm">event</span>
<span><?php echo $plan['completed_visits_count']; ?> of <?php echo $plan['total_expected_visits']; ?> visits completed</span>
</span>
<?php endif; ?>
</div>
</div>
<span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold <?php echo $status_color; ?>">
<?php echo htmlspecialchars($status_text); ?>
</span>
</div>

<?php if ($plan['progress_percentage'] !== null): ?>
<div class="mb-4">
<div class="flex justify-between items-center mb-2">
<span class="text-sm font-semibold text-slate-700 dark:text-slate-300">Overall Progress</span>
<span class="text-sm font-bold text-slate-900 dark:text-white"><?php echo number_format($plan['progress_percentage'], 1); ?>%</span>
</div>
<div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-3 overflow-hidden">
<div class="bg-gradient-to-r from-primary to-blue-600 h-3 rounded-full transition-all duration-500 flex items-center justify-end pr-1" style="width: <?php echo min(100, max(0, $plan['progress_percentage'])); ?>%">
<?php if ($plan['progress_percentage'] > 10): ?>
<span class="text-xs text-white font-semibold"><?php echo number_format($plan['progress_percentage'], 0); ?>%</span>
<?php endif; ?>
</div>
</div>
<?php if ($days_remaining !== null): ?>
<p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
<?php if ($days_remaining > 0): ?>
<?php echo $days_remaining; ?> day<?php echo $days_remaining !== 1 ? 's' : ''; ?> remaining in treatment plan
<?php else: ?>
Treatment plan period has ended
<?php endif; ?>
</p>
<?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($plan['expected_visits'])): ?>
<div class="mb-4">
<h5 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Visit Timeline</h5>
<div class="relative">
<div class="flex flex-wrap gap-2">
<?php 
// Show all visits, but prioritize showing upcoming ones
$all_visits = $plan['expected_visits'];
// Sort by date
usort($all_visits, function($a, $b) {
    return $a['date'] <=> $b['date'];
});
?>
<?php foreach ($all_visits as $visit): 
    $is_past = $visit['date'] < $now;
    $is_completed = $visit['is_completed'] ?? false;
    $is_upcoming = !$is_past && $visit['date'] <= $plan['end_date'];
?>
<div class="flex items-center gap-2 px-3 py-2 rounded-lg border <?php 
    echo $is_completed 
        ? 'bg-green-100 dark:bg-green-900/30 border-green-300 dark:border-green-700' 
        : ($is_past 
            ? 'bg-slate-100 dark:bg-slate-800 border-slate-300 dark:border-slate-600' 
            : 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-700');
?>">
<?php if ($is_completed): ?>
<span class="material-symbols-outlined text-sm text-green-600 dark:text-green-400">check_circle</span>
<?php elseif ($is_past): ?>
<span class="material-symbols-outlined text-sm text-slate-400">radio_button_unchecked</span>
<?php else: ?>
<span class="material-symbols-outlined text-sm text-blue-600 dark:text-blue-400">schedule</span>
<?php endif; ?>
<div class="flex flex-col">
<span class="text-xs font-semibold <?php echo $is_completed ? 'text-green-800 dark:text-green-300' : ($is_upcoming ? 'text-blue-800 dark:text-blue-300' : 'text-slate-600 dark:text-slate-400'); ?>">
Visit <?php echo $visit['visit_number']; ?>
</span>
<span class="text-xs <?php echo $is_completed ? 'text-green-700 dark:text-green-400' : ($is_upcoming ? 'text-blue-700 dark:text-blue-400' : 'text-slate-500 dark:text-slate-500'); ?>">
<?php echo $visit['date']->format('M d, Y'); ?>
</span>
</div>
</div>
<?php endforeach; ?>
</div>
<p class="text-xs text-slate-500 dark:text-slate-400 mt-2">
Total: <?php echo count($plan['expected_visits']); ?> scheduled visits. <?php echo $plan['completed_visits_count']; ?> completed.
</p>
</div>
</div>
<?php endif; ?>


<div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
<div class="p-3 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
<p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Treatment Start</p>
<p class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo $start_date->format('M d, Y'); ?></p>
</div>
<?php if ($end_date): ?>
<div class="p-3 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
<p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Expected Completion</p>
<p class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo $end_date->format('M d, Y'); ?></p>
</div>
<?php endif; ?>
<?php if ($last_visit_date): ?>
<div class="p-3 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
<p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Last Visit</p>
<p class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo $last_visit_date->format('M d, Y'); ?></p>
</div>
<?php endif; ?>
<?php if ($next_visit_date): ?>
<div class="p-3 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 <?php echo $is_overdue ? 'border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-900/20' : ''; ?>">
<p class="text-xs text-slate-500 dark:text-slate-400 mb-1">Next Recommended Visit</p>
<p class="text-sm font-semibold <?php echo $is_overdue ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-white'; ?>">
<?php echo $next_visit_date->format('M d, Y'); ?>
<?php if ($is_overdue): ?>
<span class="ml-2 text-xs font-normal">(Overdue)</span>
<?php endif; ?>
</p>
<?php if ($is_overdue): ?>
<p class="text-xs text-red-600 dark:text-red-400 mt-1">
Please schedule your next appointment soon.
</p>
<?php endif; ?>
</div>
<?php endif; ?>
</div>

<?php if ($plan['notes']): ?>
<div class="mt-4 p-3 bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
<p class="text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Notes</p>
<p class="text-sm text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($plan['notes']); ?></p>
</div>
<?php endif; ?>

<?php if (!$is_completed && $next_visit_date): ?>
<div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
<a href="book_appointment.php?service_id=<?php echo $plan['service_id']; ?>" class="inline-flex items-center gap-2 bg-primary text-white hover:bg-blue-700 font-semibold transition-colors px-4 py-2 rounded-lg text-sm">
<span class="material-symbols-outlined text-lg">add</span>
<span>Schedule Next Visit</span>
</a>
</div>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
</main>
        </main><!-- /.main -->
    </div><!-- /flex-1 -->
</div><!-- /flex min-h-screen -->
<?php ob_start(); ?>

<!-- Confirmation Modal for Logout -->
<div id="confirmModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" onclick="closeConfirmModal(event)">
<div class="bg-white dark:bg-[#1a2634] rounded-xl shadow-xl max-w-md w-full p-6" onclick="event.stopPropagation()">
<div id="confirmModalContent">
<!-- Content will be populated by JavaScript -->
</div>
</div>
</div>

<!-- Invoice Details Modal -->
<div id="invoiceModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" onclick="closeInvoiceModal(event)">
<div class="bg-white dark:bg-[#1a2634] rounded-xl shadow-xl max-w-2xl w-full p-6 max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
<div id="invoiceModalContent">
<!-- Content will be populated by JavaScript -->
</div>
</div>
</div>


<script>
function closeConfirmModal(event) {
    if (event && event.target !== event.currentTarget) return;
    const modal = document.getElementById('confirmModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

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

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeConfirmModal();
        closeInvoiceModal();
    }
});

// Tab switching functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabContents = document.querySelectorAll('.tab-content');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Update button styles
            tabButtons.forEach(btn => {
                btn.classList.remove('border-primary', 'text-primary');
                btn.classList.add('border-transparent', 'text-slate-500', 'dark:text-slate-400');
            });
            this.classList.remove('border-transparent', 'text-slate-500', 'dark:text-slate-400');
            this.classList.add('border-primary', 'text-primary');
            
            // Show/hide tab contents
            tabContents.forEach(content => {
                content.classList.add('hidden');
            });
            document.getElementById(targetTab + 'Tab').classList.remove('hidden');
            
            // Clear search when switching tabs
            const searchInput = document.getElementById('transactionSearch');
            if (searchInput) {
                searchInput.value = '';
                filterTransactions('');
            }
        });
    });
    
    // Appointment search functionality
    const appointmentSearch = document.getElementById('appointmentSearch');
    if (appointmentSearch) {
        appointmentSearch.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            filterAppointments(searchTerm);
        });
    }
    
    // Transaction search functionality
    const transactionSearch = document.getElementById('transactionSearch');
    if (transactionSearch) {
        transactionSearch.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase().trim();
            filterTransactions(searchTerm);
        });
    }
});

function filterAppointments(searchTerm) {
    const rows = document.querySelectorAll('.appointment-row');
    rows.forEach(row => {
        const searchText = row.getAttribute('data-search-text') || '';
        if (searchText.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function filterTransactions(searchTerm) {
    const activeTab = document.querySelector('.tab-button.border-primary')?.getAttribute('data-tab') || 'pending';
    const rows = document.querySelectorAll(`#${activeTab}Tab .invoice-row`);
    rows.forEach(row => {
        const searchText = row.getAttribute('data-search-text') || '';
        if (searchText.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function closeInvoiceModal(event) {
    if (event && event.target !== event.currentTarget) return;
    const modal = document.getElementById('invoiceModal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}



async function viewInvoiceDetails(invoice) {
    const modal = document.getElementById('invoiceModal');
    const content = document.getElementById('invoiceModalContent');
    
    if (!modal || !content) return;
    
    // Show loading state
    content.innerHTML = `
        <div class="flex items-center justify-center p-8">
            <div class="text-slate-500">Loading...</div>
        </div>
    `;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    try {
        // Fetch payment information
        const response = await fetch('get_invoice_payment.php?invoice_id=' + invoice.invoice_id);
        const paymentData = await response.json();
        
        const invoice_id_display = 'INV-' + new Date(invoice.generated_at).getFullYear() + '-' + String(invoice.invoice_id).padStart(3, '0');
        const invoice_date = new Date(invoice.generated_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        
        let paymentHtml = '';
        if (paymentData.payments && paymentData.payments.length > 0) {
            paymentHtml = `
                <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
                    <h4 class="text-sm font-semibold text-slate-900 dark:text-white mb-3">Payment Information</h4>
                    <div class="space-y-3">
                        ${paymentData.payments.map(payment => {
                            const payment_date = new Date(payment.transaction_date || payment.payment_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
                            const paymentMethod = payment.payment_method || 'Cash';
                            const isCash = paymentMethod.toLowerCase() === 'cash';
                            
                            return `
                                <div class="p-4 bg-slate-50 dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700">
                                    <div class="space-y-2">
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Payment Method</span>
                                            <span class="text-sm font-semibold text-slate-900 dark:text-white">${paymentMethod}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Amount Paid</span>
                                            <span class="text-sm font-bold text-slate-900 dark:text-white">â‚±${parseFloat(payment.amount).toFixed(2)}</span>
                                        </div>
                                        ${isCash ? `
                                        <div class="flex justify-between items-center pt-2 border-t border-slate-200 dark:border-slate-700">
                                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Amount Received</span>
                                            <span class="text-sm font-medium text-slate-900 dark:text-white">â‚±${parseFloat(payment.amount).toFixed(2)}</span>
                                        </div>
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Change</span>
                                            <span class="text-sm font-medium text-green-600 dark:text-green-400">â‚±0.00</span>
                                        </div>
                                        ` : ''}
                                        <div class="pt-2 border-t border-slate-200 dark:border-slate-700">
                                            <p class="text-xs text-slate-500 dark:text-slate-400">${payment_date}</p>
                                            ${payment.processor_name ? `<p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Processor: ${payment.processor_name}</p>` : ''}
                                            ${payment.external_txn_id ? `<p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Transaction ID: ${payment.external_txn_id}</p>` : ''}
                                            ${payment.staff_first_name ? `<p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Processed by: ${payment.staff_first_name} ${payment.staff_last_name || ''}</p>` : ''}
                                        </div>
                                    </div>
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        } else {
            paymentHtml = `
                <div class="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
                    <p class="text-sm text-slate-500 dark:text-slate-400">No payment information available.</p>
                </div>
            `;
        }
        
        content.innerHTML = `
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Invoice Details</h3>
                <button onclick="closeInvoiceModal()" class="text-[#4c6c9a] hover:text-[#0d131b] dark:text-slate-400 dark:hover:text-white">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Invoice ID</p>
                    <p class="text-sm font-semibold text-slate-900 dark:text-white">${invoice_id_display}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Date</p>
                    <p class="text-sm text-slate-900 dark:text-white">${invoice_date}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Service</p>
                    <p class="text-sm text-slate-900 dark:text-white">${invoice.service_name || 'Appointment'}</p>
                </div>
                ${invoice.service_price && parseFloat(invoice.total_amount) > parseFloat(invoice.service_price) ? `
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Service Price</p>
                    <p class="text-sm text-slate-900 dark:text-white">â‚±${parseFloat(invoice.service_price).toFixed(2)}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Additional Charges</p>
                    <p class="text-sm text-slate-900 dark:text-white">â‚±${(parseFloat(invoice.total_amount) - parseFloat(invoice.service_price)).toFixed(2)}</p>
                </div>
                ` : ''}
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Total Amount</p>
                    <p class="text-lg font-bold text-slate-900 dark:text-white">â‚±${parseFloat(invoice.total_amount).toFixed(2)}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-1">Payment Status</p>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium ${
                        invoice.payment_status === 'Paid' 
                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400'
                            : 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400'
                    }">
                        ${invoice.payment_status}
                    </span>
                </div>
                ${paymentHtml}
            </div>
            <div class="mt-6 flex justify-end">
                <button onclick="closeInvoiceModal()" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-blue-600 transition-colors">
                    Close
                </button>
            </div>
        `;
    } catch (error) {
        content.innerHTML = `
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Invoice Details</h3>
                <button onclick="closeInvoiceModal()" class="text-[#4c6c9a] hover:text-[#0d131b] dark:text-slate-400 dark:hover:text-white">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <p class="text-sm text-red-600 dark:text-red-400">Error loading payment information.</p>
            <div class="mt-6 flex justify-end">
                <button onclick="closeInvoiceModal()" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-blue-600 transition-colors">
                    Close
                </button>
            </div>
        `;
    }
}

// AUTOMATIC PAYMENT VERIFICATION
// Runs automatically when page loads - verifies pending PayMongo payments
(function() {
    fetch('verify_my_payments.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.verified > 0) {
                console.log('âœ… Auto-verified ' + data.verified + ' payment(s)');
                setTimeout(() => window.location.reload(), 1000);
            }
        })
        .catch(error => {
            console.log('Payment verification check completed');
        });
<?php $extra_scripts = ob_get_clean(); include ROOT_PATH . '/views/layout/footer.php'; ?>
