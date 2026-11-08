<?php
/**
 * admin_dashboard.php — Staff Dashboard (Admin / Manager / Dentist / Receptionist)
 *
 * ── Bootstrap ──────────────────────────────────────────────────────────────
 */
require_once 'config/db.php';
require_once 'config/security.php';

// Guard: staff only
requireLogin();

$user_type = $_SESSION['user_type'] ?? 'patient';
$user_role = $_SESSION['user_role'] ?? '';

if ($user_type !== 'staff' || !in_array($user_role, ['Manager', 'Admin', 'Dentist', 'Receptionist'], true)) {
    header('Location: landing.php');
    exit();
}

$is_receptionist = ($user_role === 'Receptionist');
$is_dentist      = ($user_role === 'Dentist');

// ── Fetch current staff user ────────────────────────────────────────────────
$conn      = getDBConnection();
$stmt      = $conn->prepare("SELECT staff_id, first_name, last_name, email, role, phone_number FROM tbl_staff WHERE staff_id = ? LIMIT 1");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$user_info = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$user_info) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Refresh role from DB
$_SESSION['user_role'] = $user_info['role'];
$user_role       = $user_info['role'];
$is_receptionist = ($user_role === 'Receptionist');
$is_dentist      = ($user_role === 'Dentist');
$dentist_id      = $is_dentist ? (int)$user_info['staff_id'] : null;

$admin_name       = $user_info['first_name'] . ' ' . $user_info['last_name'];
$admin_first_name = $user_info['first_name'];
$admin_email      = $user_info['email'] ?? '';
$admin_role       = $user_info['role'] ?? 'Manager';
$user_name        = $admin_name; // expected by topbar.php

// ── Profile completeness check ──────────────────────────────────────────────
function isStaffProfileComplete(int $staff_id): bool
{
    try {
        $conn  = getDBConnection();
        $stmt  = $conn->prepare("SELECT first_name, last_name, email, phone_number FROM tbl_staff WHERE staff_id = ? LIMIT 1");
        $stmt->bind_param('i', $staff_id);
        $stmt->execute();
        $staff = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$staff) { $conn->close(); return false; }
        if (empty($staff['first_name']) || empty($staff['last_name']) || empty($staff['email']) || empty($staff['phone_number'])) {
            $conn->close(); return false;
        }

        $edu = $conn->prepare("SELECT COUNT(*) AS cnt FROM tbl_staff_education WHERE staff_id = ?");
        $edu->bind_param('i', $staff_id);
        $edu->execute();
        $cnt = (int)$edu->get_result()->fetch_assoc()['cnt'];
        $edu->close();
        $conn->close();
        return $cnt > 0;
    } catch (Exception $e) {
        return false;
    }
}
$profile_complete = isStaffProfileComplete((int)$_SESSION['user_id']);

// ── Auto-verify pending PayMongo payments ───────────────────────────────────
try {
    require_once __DIR__ . '/services/payment_service.php';
    $paymentService = new PaymentService();
    $conn = getDBConnection();

    $stmt = $conn->prepare("
        SELECT payment_id, appointment_id, invoice_id, patient_id,
               amount, transaction_id, payment_type, payment_status, payment_method
        FROM tbl_payments
        WHERE payment_method = 'E-Wallet'
          AND transaction_id LIKE 'PM-LINK-%'
          AND payment_status != 'Completed'
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $pendingPayments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($pendingPayments as $payment) {
        $linkId = str_replace(['PM-LINK-', 'link_'], '', $payment['transaction_id']);

        $linkStatus = $paymentService->getPaymentLinkStatus($linkId)
                   ?: $paymentService->getPaymentLinkStatus('link_' . $linkId)
                   ?: (strpos($payment['transaction_id'], 'link_') !== false
                        ? $paymentService->getPaymentLinkStatus($payment['transaction_id'])
                        : null);

        if ($linkStatus && $linkStatus['success'] && $linkStatus['is_paid'] && $payment['payment_status'] !== 'Completed') {
            $upd = $conn->prepare("UPDATE tbl_payments SET payment_status = 'Completed', payment_date = NOW() WHERE payment_id = ? AND payment_status != 'Completed'");
            $upd->bind_param('i', $payment['payment_id']);
            $upd->execute();
            $affected = $conn->affected_rows;
            $upd->close();

            if ($affected > 0) {
                if ($payment['payment_type'] === 'Downpayment' && $payment['appointment_id']) {
                    $ua = $conn->prepare("UPDATE tbl_appointments SET status = 'Scheduled' WHERE appointment_id = ?");
                    $ua->bind_param('i', $payment['appointment_id']);
                    $ua->execute(); $ua->close();
                } elseif ($payment['payment_type'] === 'Invoice Payment' && $payment['invoice_id']) {
                    $ts = $conn->prepare("SELECT i.total_amount, COALESCE(SUM(CASE WHEN p.payment_status = 'Completed' THEN p.amount ELSE 0 END),0) AS total_paid FROM tbl_invoices i LEFT JOIN tbl_payments p ON i.invoice_id = p.invoice_id WHERE i.invoice_id = ? GROUP BY i.invoice_id");
                    $ts->bind_param('i', $payment['invoice_id']);
                    $ts->execute();
                    $inv = $ts->get_result()->fetch_assoc();
                    $ts->close();
                    if ($inv) {
                        $new_inv_status = ((float)$inv['total_paid'] >= ((float)$inv['total_amount'] - 0.01)) ? 'Paid' : 'Partial';
                        $ui = $conn->prepare("UPDATE tbl_invoices SET payment_status = ? WHERE invoice_id = ?");
                        $ui->bind_param('si', $new_inv_status, $payment['invoice_id']);
                        $ui->execute(); $ui->close();
                        if ($new_inv_status === 'Paid') {
                            $ua2 = $conn->prepare("UPDATE tbl_appointments SET status = 'Completed' WHERE appointment_id = (SELECT appointment_id FROM tbl_invoices WHERE invoice_id = ?) AND status != 'Completed'");
                            $ua2->bind_param('i', $payment['invoice_id']);
                            $ua2->execute(); $ua2->close();
                        }
                    }
                }
            }
        }
    }
    $conn->close();
} catch (Exception $e) {
    error_log('PayMongo auto-verification error: ' . $e->getMessage());
}

// ── Fetch today's appointments & stats ──────────────────────────────────────
$today_appointments = [];
$stats = ['today_count' => 0, 'new_patients' => 0, 'pending_count' => 0, 'revenue_mtd' => 0];
$action_message = $_SESSION['action_message'] ?? '';
unset($_SESSION['action_message']);

try {
    $conn = getDBConnection();

    $base_select = "
        SELECT a.appointment_id, a.scheduled_datetime, a.duration_minutes, a.status,
               sv.service_name,
               p.first_name AS patient_first_name, p.last_name AS patient_last_name,
               s.first_name AS dentist_first_name, s.last_name AS dentist_last_name
        FROM tbl_appointments a
        INNER JOIN tbl_patients p ON a.patient_id = p.patient_id
        INNER JOIN tbl_staff s ON a.dentist_id = s.staff_id
        LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
        WHERE DATE(a.scheduled_datetime) = CURDATE()
          AND a.status IN ('Scheduled','Confirmed','Appointing','Proceed to Payment','Completed')
    ";

    if ($is_dentist && $dentist_id) {
        $ts = $conn->prepare($base_select . " AND a.dentist_id = ? ORDER BY a.scheduled_datetime ASC");
        $ts->bind_param('i', $dentist_id);
    } else {
        $ts = $conn->prepare($base_select . " ORDER BY a.scheduled_datetime ASC");
    }
    $ts->execute();
    $today_appointments = $ts->get_result()->fetch_all(MYSQLI_ASSOC);
    $ts->close();

    // Stats
    if ($is_dentist && $dentist_id) {
        $s1 = $conn->prepare("SELECT COUNT(*) AS cnt FROM tbl_appointments WHERE DATE(scheduled_datetime)=CURDATE() AND dentist_id=? AND status IN ('Scheduled','Confirmed','Completed')");
        $s1->bind_param('i', $dentist_id); $s1->execute();
        $stats['today_count'] = (int)$s1->get_result()->fetch_assoc()['cnt']; $s1->close();

        $s2 = $conn->prepare("SELECT COUNT(DISTINCT p.patient_id) AS cnt FROM tbl_patients p INNER JOIN tbl_appointments a ON p.patient_id=a.patient_id WHERE DATE(p.created_at)=CURDATE() AND a.dentist_id=?");
        $s2->bind_param('i', $dentist_id); $s2->execute();
        $stats['new_patients'] = (int)$s2->get_result()->fetch_assoc()['cnt']; $s2->close();
    } else {
        $s1 = $conn->query("SELECT COUNT(*) AS cnt FROM tbl_appointments WHERE DATE(scheduled_datetime)=CURDATE() AND status IN ('Scheduled','Confirmed','Completed')");
        $stats['today_count'] = (int)$s1->fetch_assoc()['cnt'];

        $s2 = $conn->query("SELECT COUNT(*) AS cnt FROM tbl_patients WHERE DATE(created_at)=CURDATE()");
        $stats['new_patients'] = (int)$s2->fetch_assoc()['cnt'];
    }

    $s3 = $conn->query("SELECT COUNT(*) AS cnt FROM tbl_appointments WHERE status='Scheduled'");
    $stats['pending_count'] = (int)$s3->fetch_assoc()['cnt'];

    $s4 = $conn->query("SELECT SUM(price) AS total FROM tbl_appointments WHERE MONTH(scheduled_datetime)=MONTH(CURDATE()) AND YEAR(scheduled_datetime)=YEAR(CURDATE()) AND status='Completed'");
    $stats['revenue_mtd'] = (float)($s4->fetch_assoc()['total'] ?? 0);

    $conn->close();
} catch (Exception $e) {
    error_log('Dashboard data error: ' . $e->getMessage());
}

// ── View variables ──────────────────────────────────────────────────────────
$page_title = 'Dashboard';

// ── Render view ────────────────────────────────────────────────────────────
include ROOT_PATH . '/views/staff/dashboard.php';
