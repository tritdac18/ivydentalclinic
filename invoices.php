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

// Allow Admin, Dentist, and Receptionist roles to access invoice page (Manager should not access)
if ($user_type !== 'staff' || ($user_role !== 'Admin' && $user_role !== 'Dentist' && $user_role !== 'Receptionist')) {
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

// Handle invoice creation
$action_message = '';

// Check for success message from redirect
if (isset($_SESSION['invoice_success_message'])) {
    $action_message = $_SESSION['invoice_success_message'];
    unset($_SESSION['invoice_success_message']);
}

// Handle invoice actions (create, edit, delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_invoice') {
        $patient_id = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : null;
        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : null;
        // Get total_amount from form - ensure it's properly read
        $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0.00;
        
        // If total_amount is 0, try to get it from JavaScript calculated value or appointment price
        if ($total_amount <= 0 && $appointment_id) {
            // Get service price from appointment
            $price_check = $conn->prepare("SELECT price FROM tbl_appointments WHERE appointment_id = ? LIMIT 1");
            $price_check->bind_param("i", $appointment_id);
            $price_check->execute();
            $price_result = $price_check->get_result();
            if ($price_row = $price_result->fetch_assoc()) {
                $total_amount = floatval($price_row['price'] ?? 0);
            }
            $price_check->close();
        }
    // Default payment status to 'Unpaid' if not provided
    $payment_status = 'Unpaid';
    $generated_at = isset($_POST['generated_at']) ? $_POST['generated_at'] : date('Y-m-d H:i:s');
    
    try {
        // Check if appointment has a downpayment and deduct it from total
        $downpayment_amount = 0.00;
        if ($appointment_id) {
            $downpayment_check = $conn->prepare("
                SELECT COALESCE(SUM(amount), 0) as total_downpayment 
                FROM tbl_payments 
                WHERE appointment_id = ? 
                AND payment_type = 'Downpayment' 
                AND payment_status = 'Completed'
            ");
            $downpayment_check->bind_param("i", $appointment_id);
            $downpayment_check->execute();
            $downpayment_result = $downpayment_check->get_result();
            if ($downpayment_row = $downpayment_result->fetch_assoc()) {
                $downpayment_amount = floatval($downpayment_row['total_downpayment']);
            }
            $downpayment_check->close();
        }
        
        // Store the FULL total amount in invoice (not deducted amount)
        // The invoice total_amount should be the full service price
        // Downpayment deduction is shown in payment status, not in the invoice amount
        $invoice_total = $total_amount; // Use the full amount from form
        
        // Calculate remaining balance after downpayment for status determination
        $remaining_balance = max(0.00, $total_amount - $downpayment_amount);
        
        // Set payment status based on remaining balance
        // Note: Invoice is only marked as 'Paid' when payment is processed, not during creation
        if ($downpayment_amount > 0 && $remaining_balance > 0.01) {
            $payment_status = 'Partial';
        } else {
            // Default to 'Unpaid' - invoice will be marked as 'Paid' when payment is processed
            $payment_status = 'Unpaid';
        }
        
        $insert_stmt = $conn->prepare("INSERT INTO tbl_invoices (patient_id, appointment_id, total_amount, payment_status, generated_at) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("iidss", $patient_id, $appointment_id, $invoice_total, $payment_status, $generated_at);
        
        if ($insert_stmt->execute()) {
            // Store success message in session and redirect to prevent form resubmission
            $success_msg = "Invoice created successfully! Total amount: â‚±" . number_format($invoice_total, 2);
            if ($downpayment_amount > 0) {
                $success_msg .= " (Downpayment of â‚±" . number_format($downpayment_amount, 2) . " already paid. Remaining balance: â‚±" . number_format($remaining_balance, 2) . ")";
            }
            $_SESSION['invoice_success_message'] = $success_msg;
            $insert_stmt->close();
            header("Location: invoices.php");
            exit();
        } else {
            $action_message = "Error creating invoice: " . $insert_stmt->error;
        }
        $insert_stmt->close();
    } catch (Exception $e) {
        $action_message = "Error: " . $e->getMessage();
    }
    } elseif ($_POST['action'] === 'edit_invoice') {
        // Edit invoice
        $invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
        $patient_id = isset($_POST['patient_id']) ? intval($_POST['patient_id']) : null;
        $appointment_id = isset($_POST['appointment_id']) ? intval($_POST['appointment_id']) : null;
        $total_amount = isset($_POST['total_amount']) ? floatval($_POST['total_amount']) : 0.00;
        $payment_status = isset($_POST['payment_status']) ? $_POST['payment_status'] : 'Unpaid';
        
        try {
            // Check if appointment has a downpayment and deduct it from total
            $downpayment_amount = 0.00;
            if ($appointment_id) {
                $downpayment_check = $conn->prepare("
                    SELECT COALESCE(SUM(amount), 0) as total_downpayment 
                    FROM tbl_payments 
                    WHERE appointment_id = ? 
                    AND payment_type = 'Downpayment' 
                    AND payment_status = 'Completed'
                ");
                $downpayment_check->bind_param("i", $appointment_id);
                $downpayment_check->execute();
                $downpayment_result = $downpayment_check->get_result();
                if ($downpayment_row = $downpayment_result->fetch_assoc()) {
                    $downpayment_amount = floatval($downpayment_row['total_downpayment']);
                }
                $downpayment_check->close();
            }
            
            // Store the FULL total amount in invoice (the amount to be paid)
            $invoice_total = $total_amount; // Use the full amount from form
            
            // Calculate remaining balance after downpayment for status determination
            $remaining_balance = max(0.00, $total_amount - $downpayment_amount);
            
            // Auto-update payment status if not manually set
            // Note: Invoice is only marked as 'Paid' when payment is processed, not during editing
            if ($payment_status === 'Unpaid' || empty($payment_status)) {
                if ($downpayment_amount > 0 && $remaining_balance > 0.01) {
                    $payment_status = 'Partial';
                } else {
                    // Keep as 'Unpaid' - invoice will be marked as 'Paid' when payment is processed
                    $payment_status = 'Unpaid';
                }
            }
            
            $update_stmt = $conn->prepare("UPDATE tbl_invoices SET patient_id = ?, appointment_id = ?, total_amount = ?, payment_status = ? WHERE invoice_id = ?");
            $update_stmt->bind_param("iidss", $patient_id, $appointment_id, $invoice_total, $payment_status, $invoice_id);
            
            if ($update_stmt->execute()) {
                $success_msg = "Invoice updated successfully! Total amount: â‚±" . number_format($invoice_total, 2);
                if ($downpayment_amount > 0) {
                    $success_msg .= " (Downpayment of â‚±" . number_format($downpayment_amount, 2) . " already paid. Remaining balance: â‚±" . number_format($remaining_balance, 2) . ")";
                }
                $_SESSION['invoice_success_message'] = $success_msg;
                $update_stmt->close();
                header("Location: invoices.php");
                exit();
            } else {
                $action_message = "Error updating invoice: " . $update_stmt->error;
            }
            $update_stmt->close();
        } catch (Exception $e) {
            $action_message = "Error: " . $e->getMessage();
        }
    } elseif ($_POST['action'] === 'delete_invoice') {
        // Delete invoice
        $invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
        
        try {
            $delete_stmt = $conn->prepare("DELETE FROM tbl_invoices WHERE invoice_id = ?");
            $delete_stmt->bind_param("i", $invoice_id);
            
            if ($delete_stmt->execute()) {
                $_SESSION['invoice_success_message'] = "Invoice deleted successfully!";
                $delete_stmt->close();
                header("Location: invoices.php");
                exit();
            } else {
                $action_message = "Error deleting invoice: " . $delete_stmt->error;
            }
            $delete_stmt->close();
        } catch (Exception $e) {
            $action_message = "Error: " . $e->getMessage();
        }
    }
}

// Fetch invoices with patient and appointment details
$invoices = [];
$stats = [
    'outstanding_balance' => 0.00,
    'daily_revenue' => 0.00,
    'invoices_generated' => 0
];

try {
    // Check if tbl_invoices exists
    $table_check = $conn->query("SHOW TABLES LIKE 'tbl_invoices'");
    if ($table_check && $table_check->num_rows > 0) {
        // Fetch all invoices with patient and appointment info
        $invoice_stmt = $conn->prepare("
            SELECT 
                i.invoice_id,
                i.patient_id,
                i.appointment_id,
                i.total_amount,
                i.payment_status,
                i.generated_at,
                p.first_name as patient_first_name,
                p.last_name as patient_last_name,
                a.scheduled_datetime,
                a.service_id,
                sv.service_name
            FROM tbl_invoices i
            LEFT JOIN tbl_patients p ON i.patient_id = p.patient_id
            LEFT JOIN tbl_appointments a ON i.appointment_id = a.appointment_id
            LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
            ORDER BY i.generated_at DESC
            LIMIT 50
        ");
        
        if ($invoice_stmt) {
            $invoice_stmt->execute();
            $invoice_result = $invoice_stmt->get_result();
            
            while ($row = $invoice_result->fetch_assoc()) {
                $invoices[] = $row;
            }
            $invoice_stmt->close();
        }
        
        // Calculate statistics
        $today = date('Y-m-d');
        
        // Outstanding balance (Unpaid + Partial + Collection)
        $outstanding_stmt = $conn->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total 
            FROM tbl_invoices 
            WHERE payment_status IN ('Unpaid', 'Partial', 'Collection')
        ");
        $outstanding_stmt->execute();
        $outstanding_result = $outstanding_stmt->get_result();
        if ($outstanding_row = $outstanding_result->fetch_assoc()) {
            $stats['outstanding_balance'] = floatval($outstanding_row['total']);
        }
        $outstanding_stmt->close();
        
        // Daily revenue (Paid invoices from today)
        $daily_stmt = $conn->prepare("
            SELECT COALESCE(SUM(total_amount), 0) as total 
            FROM tbl_invoices 
            WHERE payment_status = 'Paid' 
            AND DATE(generated_at) = ?
        ");
        $daily_stmt->bind_param("s", $today);
        $daily_stmt->execute();
        $daily_result = $daily_stmt->get_result();
        if ($daily_row = $daily_result->fetch_assoc()) {
            $stats['daily_revenue'] = floatval($daily_row['total']);
        }
        $daily_stmt->close();
        
        // Total invoices generated
        $count_stmt = $conn->query("SELECT COUNT(*) as total FROM tbl_invoices");
        if ($count_row = $count_stmt->fetch_assoc()) {
            $stats['invoices_generated'] = intval($count_row['total']);
        }
    }
    
    // Fetch patients for dropdown
    $patients = [];
    $patient_stmt = $conn->prepare("SELECT patient_id, first_name, last_name, email FROM tbl_patients ORDER BY last_name, first_name");
    $patient_stmt->execute();
    $patient_result = $patient_stmt->get_result();
    while ($row = $patient_result->fetch_assoc()) {
        $patients[] = $row;
    }
    $patient_stmt->close();
    
    // Fetch appointments for dropdown with service price (exclude appointments that already have invoices)
    // Also fetch downpayment amount for each appointment
    $appointments = [];
    $appt_stmt = $conn->prepare("
        SELECT a.appointment_id, a.patient_id, a.scheduled_datetime, a.price as appointment_price, 
               p.first_name, p.last_name, sv.service_name, sv.base_price as service_price,
               COALESCE(SUM(CASE WHEN pay.payment_type = 'Downpayment' AND pay.payment_status = 'Completed' THEN pay.amount ELSE 0 END), 0) as downpayment_amount
        FROM tbl_appointments a
        LEFT JOIN tbl_patients p ON a.patient_id = p.patient_id
        LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
        LEFT JOIN tbl_invoices i ON a.appointment_id = i.appointment_id
        LEFT JOIN tbl_payments pay ON a.appointment_id = pay.appointment_id
        WHERE a.status IN ('Scheduled', 'Confirmed', 'Completed')
        AND i.invoice_id IS NULL
        GROUP BY a.appointment_id, a.patient_id, a.scheduled_datetime, a.price, 
                 p.first_name, p.last_name, sv.service_name, sv.base_price
        ORDER BY a.scheduled_datetime DESC
        LIMIT 100
    ");
    $appt_stmt->execute();
    $appt_result = $appt_stmt->get_result();
    while ($row = $appt_result->fetch_assoc()) {
        $appointments[] = $row;
    }
    $appt_stmt->close();
    
    $conn->close();
} catch (Exception $e) {
    $action_message = "Error fetching data: " . $e->getMessage();
}

// Generate next invoice ID
$next_invoice_id = 'INV-' . date('Y') . '-' . str_pad($stats['invoices_generated'] + 1, 3, '0', STR_PAD_LEFT);

// Output cache prevention meta
outputCachePreventionMeta();
?>
<?php
$page_title = 'Invoices';
$user_name  = $admin_name;
include ROOT_PATH . '/views/layout/header.php';
?>
<div class="flex min-h-screen">
    <?php include ROOT_PATH . '/views/layout/sidebar_staff.php'; ?>
    <div class="flex-1 flex flex-col lg:ml-64">
        <?php include ROOT_PATH . '/views/layout/topbar.php'; ?>
<main class="flex-grow w-full max-w-7xl mx-auto px-4 md:px-8 py-8">
<?php if ($action_message): ?>
<div class="max-w-7xl mx-auto mb-4">
<div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 px-4 py-3 rounded-lg">
<p class="text-sm font-medium"><?php echo htmlspecialchars($action_message); ?></p>
</div>
</div>
<?php endif; ?>
<div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
<div>
<h1 class="text-3xl font-black text-[#0d131b] dark:text-white tracking-tight mb-2">Invoice Management</h1>
<p class="text-[#4c6c9a] dark:text-slate-400">Manage billing, create invoices, and track payments for Ivy Dental Clinic.</p>
</div>
<div class="flex gap-3">
<button class="flex items-center gap-2 px-4 py-2.5 rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white text-sm font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shadow-sm">
<span class="material-symbols-outlined text-[20px]">download</span>
Export Report
</button>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
<div class="rounded-xl border border-[#cfd9e7] dark:border-slate-700 bg-white dark:bg-[#1a2634] p-5 shadow-sm hover:shadow-md transition-shadow">
<div class="flex items-center gap-3 mb-2">
<div class="flex items-center justify-center p-2 rounded-lg bg-orange-100 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400">
<span class="material-symbols-outlined">pending_actions</span>
</div>
<p class="text-[#4c6c9a] dark:text-slate-400 text-sm font-medium">Outstanding Balance</p>
</div>
<div class="flex items-baseline gap-2">
<h3 class="text-2xl font-bold text-[#0d131b] dark:text-white">â‚±<?php echo number_format($stats['outstanding_balance'], 2); ?></h3>
</div>
</div>
<div class="rounded-xl border border-[#cfd9e7] dark:border-slate-700 bg-white dark:bg-[#1a2634] p-5 shadow-sm hover:shadow-md transition-shadow">
<div class="flex items-center gap-3 mb-2">
<div class="flex items-center justify-center p-2 rounded-lg bg-primary/10 text-primary">
<span class="material-symbols-outlined">payments</span>
</div>
<p class="text-[#4c6c9a] dark:text-slate-400 text-sm font-medium">Daily Revenue</p>
</div>
<div class="flex items-baseline gap-2">
<h3 class="text-2xl font-bold text-[#0d131b] dark:text-white">â‚±<?php echo number_format($stats['daily_revenue'], 2); ?></h3>
</div>
</div>
<div class="rounded-xl border border-[#cfd9e7] dark:border-slate-700 bg-white dark:bg-[#1a2634] p-5 shadow-sm hover:shadow-md transition-shadow">
<div class="flex items-center gap-3 mb-2">
<div class="flex items-center justify-center p-2 rounded-lg bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400">
<span class="material-symbols-outlined">receipt_long</span>
</div>
<p class="text-[#4c6c9a] dark:text-slate-400 text-sm font-medium">Invoices Generated</p>
</div>
<div class="flex items-baseline gap-2">
<h3 class="text-2xl font-bold text-[#0d131b] dark:text-white"><?php echo $stats['invoices_generated']; ?></h3>
</div>
</div>
</div>
<div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
<div class="xl:col-span-1">
<div class="rounded-xl border border-[#cfd9e7] dark:border-slate-700 bg-white dark:bg-[#1a2634] shadow-sm sticky top-24">
<div class="border-b border-[#e7ecf3] dark:border-slate-700 p-5">
<h3 class="text-lg font-bold text-[#0d131b] dark:text-white flex items-center gap-2">
<span class="material-symbols-outlined text-primary">add_circle</span>
Create New Invoice
</h3>
</div>
<form method="POST" action="" class="p-5 flex flex-col gap-5">
<input type="hidden" name="action" value="create_invoice" id="formAction">
<div class="grid grid-cols-2 gap-4">
<div class="space-y-1.5">
<label class="text-xs font-semibold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Invoice ID</label>
<div class="relative">
<input class="w-full rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-[#4c6c9a] dark:text-slate-400 text-sm focus:border-primary focus:ring-primary font-mono" readonly type="text" value="<?php echo htmlspecialchars($next_invoice_id); ?>"/>
<span class="material-symbols-outlined absolute right-3 top-2.5 text-slate-400 text-sm">lock</span>
</div>
</div>
<div class="space-y-1.5">
<label class="text-xs font-semibold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Date <span class="text-red-500">*</span></label>
<input class="w-full rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white text-sm focus:border-primary focus:ring-primary" type="date" name="generated_at" value="<?php echo date('Y-m-d'); ?>" required/>
</div>
</div>
<div class="space-y-1.5">
<label class="text-xs font-semibold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Patient <span class="text-red-500">*</span></label>
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400">person_search</span>
<input type="text" id="patientSearch" class="w-full rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white pl-10 text-sm focus:border-primary focus:ring-primary" placeholder="Type patient name..." autocomplete="off"/>
<input type="hidden" name="patient_id" id="patientId" required/>
<div id="patientDropdown" class="absolute z-50 w-full mt-1 bg-white dark:bg-[#1a2634] border border-[#cfd9e7] dark:border-slate-600 rounded-lg shadow-lg max-h-60 overflow-y-auto hidden">
<!-- Patient options will be populated here -->
</div>
</div>
</div>
<div class="space-y-1.5">
<label class="text-xs font-semibold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Appointment <span class="text-red-500">*</span></label>
<div class="relative">
<span class="material-symbols-outlined absolute left-3 top-2.5 text-slate-400">event_available</span>
<select id="appointmentSelect" class="w-full rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white pl-10 text-sm focus:border-primary focus:ring-primary" name="appointment_id" disabled>
<option value="">Select appointment...</option>
</select>
</div>
</div>
<div id="appointmentInfo" class="hidden space-y-2 p-3 bg-slate-50 dark:bg-slate-800 rounded-lg border border-[#cfd9e7] dark:border-slate-600">
<div class="text-xs text-[#4c6c9a] dark:text-slate-400">
<p><span class="font-semibold">Service:</span> <span id="appointmentService" class="text-[#0d131b] dark:text-white"></span></p>
<p><span class="font-semibold">Service Price:</span> <span id="appointmentPrice" class="text-[#0d131b] dark:text-white font-bold">â‚±0.00</span></p>
<div id="downpaymentInfo" class="hidden mt-2 pt-2 border-t border-[#cfd9e7] dark:border-slate-600">
<p class="text-green-600 dark:text-green-400"><span class="font-semibold">Downpayment Paid:</span> <span id="downpaymentAmountDisplay" class="font-bold">â‚±0.00</span></p>
<p class="text-xs text-[#4c6c9a] dark:text-slate-400 italic">This amount will be deducted from the invoice total</p>
</div>
</div>
</div>
<div class="space-y-1.5">
<label class="text-xs font-semibold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Additional Expenses (Optional)</label>
<div class="grid grid-cols-2 gap-2">
<select id="additionalExpenseType" class="w-full rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white text-sm focus:border-primary focus:ring-primary">
<option value="">Select type...</option>
<option value="doctor_consultation">Doctor Consultation</option>
</select>
<div class="relative">
<span class="absolute left-3 top-2.5 text-[#4c6c9a] dark:text-slate-400 font-semibold">â‚±</span>
<input id="additionalExpenseAmount" type="number" step="0.01" min="0" class="w-full rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white pl-8 text-sm focus:border-primary focus:ring-primary" placeholder="0.00"/>
</div>
</div>
</div>
<div class="space-y-1.5">
<label class="text-xs font-semibold text-[#4c6c9a] dark:text-slate-400 uppercase tracking-wider">Total Amount</label>
<div class="relative">
<span class="absolute left-3 top-2.5 text-[#4c6c9a] dark:text-slate-400 font-semibold">â‚±</span>
<input id="totalAmount" class="w-full rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-[#0d131b] dark:text-white pl-8 text-sm font-semibold focus:border-primary focus:ring-primary" placeholder="0.00" type="number" name="total_amount" step="0.01" min="0" readonly required value="0.00"/>
</div>
</div>
<div class="pt-4 flex flex-col gap-3">
<button id="submitInvoiceBtn" class="w-full rounded-lg bg-primary px-4 py-3 text-sm font-bold text-white shadow-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-all" type="submit">
Generate Invoice
</button>
</div>
</form>
</div>
</div>
<div class="xl:col-span-2 flex flex-col h-full">
<div class="rounded-xl border border-[#cfd9e7] dark:border-slate-700 bg-white dark:bg-[#1a2634] shadow-sm flex-1 flex flex-col">
<div class="border-b border-[#e7ecf3] dark:border-slate-700 p-5 flex flex-wrap items-center justify-between gap-4">
<h3 class="text-lg font-bold text-[#0d131b] dark:text-white">Recent Invoices</h3>
<div class="flex items-center gap-2">
<div class="relative">
<input id="invoiceFilter" class="rounded-lg border border-[#cfd9e7] dark:border-slate-600 bg-white dark:bg-[#1a2634] text-[#0d131b] dark:text-white py-1.5 pl-8 pr-3 text-xs focus:border-primary focus:ring-primary" placeholder="Filter invoices..." type="text"/>
<span class="material-symbols-outlined absolute left-2 top-1.5 text-slate-400 text-[18px]">filter_list</span>
</div>
<button class="p-1.5 rounded-lg text-[#4c6c9a] dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-primary transition-colors" onclick="location.reload()">
<span class="material-symbols-outlined">refresh</span>
</button>
</div>
</div>
<div class="overflow-x-auto flex-1">
<table class="w-full min-w-[700px] text-left text-sm text-[#4c6c9a] dark:text-slate-400">
<thead class="bg-slate-50 dark:bg-slate-800/50 text-xs uppercase font-semibold text-[#4c6c9a] dark:text-slate-400 sticky top-0">
<tr>
<th class="px-6 py-4 rounded-tl-lg">Invoice ID</th>
<th class="px-6 py-4">Patient</th>
<th class="px-6 py-4">Appt ID</th>
<th class="px-6 py-4 text-right">Amount</th>
<th class="px-6 py-4 text-center">Status</th>
<th class="px-6 py-4">Date</th>
<th class="px-6 py-4 text-right rounded-tr-lg">Actions</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100 dark:divide-slate-800" id="invoiceTableBody">
<?php if (empty($invoices)): ?>
<tr>
<td colspan="7" class="px-6 py-8 text-center text-[#4c6c9a] dark:text-slate-400">No invoices found. Create your first invoice using the form on the left.</td>
</tr>
<?php else: ?>
<?php foreach ($invoices as $invoice): 
    $invoice_id_display = 'INV-' . date('Y', strtotime($invoice['generated_at'])) . '-' . str_pad($invoice['invoice_id'], 3, '0', STR_PAD_LEFT);
    $patient_name = ($invoice['patient_first_name'] ?? '') . ' ' . ($invoice['patient_last_name'] ?? '');
    $status = $invoice['payment_status'] ?? 'Unpaid';
    $status_colors = [
        'Paid' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400',
        'Unpaid' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
        'Partial' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        'Collection' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
    ];
    $status_color = $status_colors[$status] ?? $status_colors['Unpaid'];
?>
<tr class="invoice-row hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group" data-search-text="<?php echo htmlspecialchars(strtolower($invoice_id_display . ' ' . $patient_name . ' ' . $status)); ?>">
<td class="px-6 py-4 font-mono font-medium text-primary">#<?php echo htmlspecialchars($invoice_id_display); ?></td>
<td class="px-6 py-4">
<div class="flex flex-col">
<span class="font-medium text-[#0d131b] dark:text-white"><?php echo htmlspecialchars($patient_name ?: 'N/A'); ?></span>
<?php if ($invoice['patient_id']): ?>
<span class="text-xs text-slate-400">ID: <?php echo $invoice['patient_id']; ?></span>
<?php endif; ?>
</div>
</td>
<td class="px-6 py-4 font-mono text-xs"><?php echo $invoice['appointment_id'] ? 'APT-' . $invoice['appointment_id'] : '-'; ?></td>
<td class="px-6 py-4 text-right font-medium text-[#0d131b] dark:text-white">â‚±<?php echo number_format($invoice['total_amount'] ?? 0, 2); ?></td>
<td class="px-6 py-4 text-center">
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium <?php echo $status_color; ?>">
<?php echo htmlspecialchars($status); ?>
</span>
</td>
<td class="px-6 py-4 font-mono text-xs text-slate-400"><?php echo $invoice['generated_at'] ? date('M d, Y', strtotime($invoice['generated_at'])) : '-'; ?></td>
<td class="px-6 py-4 text-right">
<div class="flex items-center justify-end gap-2">
<button onclick="editInvoice(<?php echo $invoice['invoice_id']; ?>, <?php echo htmlspecialchars(json_encode($invoice)); ?>)" class="text-slate-400 hover:text-primary transition-colors" title="Edit Invoice">
<span class="material-symbols-outlined text-[20px]">edit</span>
</button>
<button onclick="deleteInvoice(<?php echo $invoice['invoice_id']; ?>)" class="text-slate-400 hover:text-red-600 transition-colors" title="Delete Invoice">
<span class="material-symbols-outlined text-[20px]">delete</span>
</button>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
<div class="border-t border-[#e7ecf3] dark:border-slate-700 p-4 flex items-center justify-between">
<span class="text-xs text-[#4c6c9a] dark:text-slate-400">Showing <?php echo count($invoices); ?> invoice(s)</span>
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
// Patient and appointment data from PHP
const allPatients = <?php echo json_encode($patients); ?>;
const allAppointments = <?php echo json_encode($appointments); ?>;

</script>
<?php
$extra_scripts = ob_get_clean();
include ROOT_PATH . '/views/layout/footer.php';
