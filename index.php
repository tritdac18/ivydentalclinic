<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Route users based on login status and role
if (!isset($_SESSION['user_id'])) {
    // Not logged in - redirect to homepage
    header("Location: homepage.php");
    exit();
}

// Load database config only if user is logged in
require_once 'config/db.php';

// User is logged in - route based on role
$user_type = $_SESSION['user_type'] ?? 'patient';
$user_role = $_SESSION['user_role'] ?? '';

// Route admin/staff/receptionist to admin dashboard
if ($user_type === 'staff' && ($user_role === 'Admin' || $user_role === 'Dentist' || $user_role === 'Receptionist')) {
    header("Location: admin_dashboard.php");
    exit();
}

// Route patients to landing page
if ($user_type === 'patient') {
    header("Location: landing.php");
    exit();
}

// Route other staff to landing page
header("Location: landing.php");
exit();
?>

