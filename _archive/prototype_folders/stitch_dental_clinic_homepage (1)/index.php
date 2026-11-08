<?php
require_once 'config/db.php';

// Route users based on login status and role
if (!isset($_SESSION['user_id'])) {
    // Not logged in - redirect to homepage
    header("Location: stitch_dental_clinic_homepage/dental_clinic_homepage/code.html");
    exit();
}

// User is logged in - route based on role
$user_type = $_SESSION['user_type'] ?? 'patient';
$user_role = $_SESSION['user_role'] ?? '';

// Route admin/staff to admin dashboard
if ($user_type === 'staff' && ($user_role === 'Admin' || $user_role === 'Dentist')) {
    header("Location: php/admin_dashboard.php");
    exit();
}

// Route patients to landing page
if ($user_type === 'patient') {
    header("Location: php/landing.php");
    exit();
}

// Route other staff to landing page
header("Location: php/landing.php");
exit();
?>

