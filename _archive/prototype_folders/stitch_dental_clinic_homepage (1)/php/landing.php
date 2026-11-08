<?php
require_once '../config/database.php';

// Check if user is logged in (session validation)
if (!isset($_SESSION['user_id'])) {
    header("Location: ../stitch_dental_clinic_homepage/login_page/login.php");
    exit();
}

// Security: Verify user still exists in database and fetch fresh data (like your old code)
$conn = getDBConnection();
$user_info = null;
$user_type = $_SESSION['user_type'] ?? 'patient';

try {
    if ($user_type === 'patient') {
        // Fetch patient data from database using prepared statement
        $stmt = $conn->prepare("SELECT patient_id, first_name, last_name, email FROM tbl_patients WHERE patient_id = ? LIMIT 1");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user_info = $result->fetch_assoc();
        } else {
            // User not found in database - session invalid, logout
            session_destroy();
            header("Location: ../stitch_dental_clinic_homepage/login_page/login.php");
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
        } else {
            // User not found in database - session invalid, logout
            session_destroy();
            header("Location: ../stitch_dental_clinic_homepage/login_page/login.php");
            exit();
        }
        $stmt->close();
    }
    
    $conn->close();
} catch (Exception $e) {
    // Database error - logout for security
    session_destroy();
    header("Location: ../stitch_dental_clinic_homepage/login_page/login.php");
    exit();
}

// If user not found, redirect to login
if (!$user_info) {
    session_destroy();
    header("Location: ../stitch_dental_clinic_homepage/login_page/login.php");
    exit();
}

// Set user variables from fresh database data
$user_name = $user_info['first_name'] . ' ' . $user_info['last_name'];
$user_email = $user_info['email'] ?? '';
if ($user_type === 'staff' && isset($user_info['role'])) {
    $_SESSION['user_role'] = $user_info['role'];
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Welcome - Ivy Dental Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#136dec",
                        "accent": "#10b981",
                        "background-light": "#f6f7f8",
                        "background-dark": "#101822",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
</head>
<body class="font-display bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-50 antialiased">
    <header class="sticky top-0 z-50 w-full border-b border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md">
        <div class="px-4 md:px-10 py-3 max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center size-8 rounded-lg bg-primary/10 text-primary">
                    <span class="material-symbols-outlined">dentistry</span>
                </div>
                <h2 class="text-slate-900 dark:text-white text-lg font-bold leading-tight tracking-tight">Ivy Dental Clinic</h2>
            </div>
            <nav class="hidden md:flex items-center gap-6">
                <span class="text-slate-600 dark:text-slate-300 text-sm">Welcome, <?php echo htmlspecialchars($user_name); ?>!</span>
                <a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary text-sm font-medium transition-colors" href="landing.php">Dashboard</a>
                <?php if ($user_type === 'patient'): ?>
                    <a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary text-sm font-medium transition-colors" href="#">Appointments</a>
                <?php endif; ?>
                <a class="text-primary hover:text-primary/80 text-sm font-medium transition-colors" href="logout.php">Logout</a>
            </nav>
            <div class="md:hidden flex items-center gap-2">
                <span class="text-sm text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></span>
                <a class="text-primary text-sm font-medium" href="logout.php">Logout</a>
            </div>
        </div>
    </header>

    <main class="min-h-screen">
        <!-- Welcome Section -->
        <section class="bg-gradient-to-br from-primary to-blue-700 text-white py-16 md:py-24">
            <div class="max-w-7xl mx-auto px-4 md:px-10">
                <div class="text-center max-w-3xl mx-auto">
                    <div class="inline-flex items-center rounded-full border border-white/20 bg-white/10 backdrop-blur-md px-4 py-2 text-sm font-semibold mb-6">
                        <span class="material-symbols-outlined text-lg mr-2">check_circle</span>
                        Successfully Logged In
                    </div>
                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-black leading-tight mb-6">
                        Welcome Back, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>!
                    </h1>
                    <p class="text-blue-100 text-lg md:text-xl leading-relaxed mb-8">
                        You're now logged in to your <?php echo $user_type === 'patient' ? 'patient' : 'staff'; ?> account. 
                        Manage your appointments, view your profile, and access all our services.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <?php if ($user_type === 'patient'): ?>
                            <a href="#" class="h-12 px-8 rounded-lg bg-white text-primary font-bold hover:bg-slate-50 transition-colors shadow-lg flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined">calendar_month</span>
                                Book Appointment
                            </a>
                            <a href="#" class="h-12 px-8 rounded-lg bg-blue-800/40 text-white font-semibold hover:bg-blue-800/60 transition-colors backdrop-blur-sm border border-blue-400/30 flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined">person</span>
                                View Profile
                            </a>
                        <?php else: ?>
                            <a href="#" class="h-12 px-8 rounded-lg bg-white text-primary font-bold hover:bg-slate-50 transition-colors shadow-lg flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined">dashboard</span>
                                Staff Dashboard
                            </a>
                            <a href="#" class="h-12 px-8 rounded-lg bg-blue-800/40 text-white font-semibold hover:bg-blue-800/60 transition-colors backdrop-blur-sm border border-blue-400/30 flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined">schedule</span>
                                View Schedule
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Actions Section -->
        <section class="py-16 bg-white dark:bg-slate-900">
            <div class="max-w-7xl mx-auto px-4 md:px-10">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white mb-8 text-center">Quick Actions</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if ($user_type === 'patient'): ?>
                        <div class="group flex flex-col p-6 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800 shadow-sm hover:shadow-md transition-all cursor-pointer">
                            <div class="size-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined text-2xl">calendar_month</span>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Book Appointment</h3>
                            <p class="text-slate-500 dark:text-slate-400 text-sm">Schedule your next dental visit</p>
                        </div>
                        <div class="group flex flex-col p-6 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800 shadow-sm hover:shadow-md transition-all cursor-pointer">
                            <div class="size-12 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-500 dark:text-emerald-400 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined text-2xl">history</span>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Appointment History</h3>
                            <p class="text-slate-500 dark:text-slate-400 text-sm">View your past appointments</p>
                        </div>
                        <div class="group flex flex-col p-6 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800 shadow-sm hover:shadow-md transition-all cursor-pointer">
                            <div class="size-12 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-500 dark:text-blue-400 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined text-2xl">person</span>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">My Profile</h3>
                            <p class="text-slate-500 dark:text-slate-400 text-sm">Update your personal information</p>
                        </div>
                    <?php else: ?>
                        <div class="group flex flex-col p-6 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800 shadow-sm hover:shadow-md transition-all cursor-pointer">
                            <div class="size-12 rounded-xl bg-primary/10 text-primary flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined text-2xl">schedule</span>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Today's Schedule</h3>
                            <p class="text-slate-500 dark:text-slate-400 text-sm">View your appointments for today</p>
                        </div>
                        <div class="group flex flex-col p-6 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800 shadow-sm hover:shadow-md transition-all cursor-pointer">
                            <div class="size-12 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 text-emerald-500 dark:text-emerald-400 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined text-2xl">people</span>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Patient List</h3>
                            <p class="text-slate-500 dark:text-slate-400 text-sm">Manage patient records</p>
                        </div>
                        <div class="group flex flex-col p-6 rounded-2xl bg-slate-50 dark:bg-slate-950 border border-slate-100 dark:border-slate-800 shadow-sm hover:shadow-md transition-all cursor-pointer">
                            <div class="size-12 rounded-xl bg-blue-50 dark:bg-blue-900/20 text-blue-500 dark:text-blue-400 flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined text-2xl">assessment</span>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Reports</h3>
                            <p class="text-slate-500 dark:text-slate-400 text-sm">View sales and appointment reports</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Account Info Section -->
        <section class="py-16 bg-slate-50 dark:bg-slate-950">
            <div class="max-w-7xl mx-auto px-4 md:px-10">
                <div class="max-w-2xl mx-auto bg-white dark:bg-slate-900 rounded-2xl p-8 shadow-lg border border-slate-200 dark:border-slate-800">
                    <h2 class="text-2xl font-bold text-slate-900 dark:text-white mb-6">Account Information</h2>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between py-3 border-b border-slate-200 dark:border-slate-800">
                            <span class="text-slate-600 dark:text-slate-400 font-medium">Name:</span>
                            <span class="text-slate-900 dark:text-white font-semibold"><?php echo htmlspecialchars($user_name); ?></span>
                        </div>
                        <div class="flex items-center justify-between py-3 border-b border-slate-200 dark:border-slate-800">
                            <span class="text-slate-600 dark:text-slate-400 font-medium">Email:</span>
                            <span class="text-slate-900 dark:text-white font-semibold"><?php echo htmlspecialchars($user_email); ?></span>
                        </div>
                        <div class="flex items-center justify-between py-3 border-b border-slate-200 dark:border-slate-800">
                            <span class="text-slate-600 dark:text-slate-400 font-medium">Account Type:</span>
                            <span class="text-slate-900 dark:text-white font-semibold capitalize"><?php echo htmlspecialchars($user_type); ?></span>
                        </div>
                        <?php if ($user_type === 'staff' && isset($_SESSION['user_role'])): ?>
                        <div class="flex items-center justify-between py-3">
                            <span class="text-slate-600 dark:text-slate-400 font-medium">Role:</span>
                            <span class="text-slate-900 dark:text-white font-semibold"><?php echo htmlspecialchars($_SESSION['user_role']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-background-dark text-slate-300 py-8 border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-4 md:px-10 text-center text-sm">
            <p>© 2024 Ivy Dental Clinic. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>

