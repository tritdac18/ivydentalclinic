<?php
/**
 * views/layout/sidebar_patient.php
 *
 * Shared sidebar for PATIENT pages: landing, profile, book_appointment,
 * appointment_history, transaction_history, payments.
 *
 * Required variable (set by the including page):
 *   $user_name  — patient's display name (first + last)
 */
?>
<aside class="w-64 bg-white dark:bg-[#1a2634] border-r border-[#e7ecf3] dark:border-slate-700 hidden lg:flex flex-col fixed h-full z-20">

    <div class="h-16 flex items-center px-6 border-b border-[#e7ecf3] dark:border-slate-700">
        <div class="flex items-center gap-3">
            <div class="size-8 text-primary">
                <span class="material-symbols-outlined text-3xl">dentistry</span>
            </div>
            <div>
                <h2 class="text-[#0d131b] dark:text-white text-lg font-bold leading-tight tracking-[-0.015em]">Ivy Dental Clinic</h2>
                <p class="text-xs font-semibold text-[#4c6c9a] dark:text-slate-400">Patient Portal</p>
            </div>
        </div>
    </div>

    <div class="p-4 flex flex-col gap-1 flex-1 overflow-y-auto">
        <a class="<?php echo nav_class('landing.php'); ?>" href="landing.php">
            <span class="material-symbols-outlined">dashboard</span>
            <span>Dashboard</span>
        </a>
        <a class="<?php echo nav_class('profile.php'); ?>" href="profile.php">
            <span class="material-symbols-outlined">person</span>
            <span>My Profile</span>
        </a>
        <a class="<?php echo nav_class('book_appointment.php'); ?>" href="book_appointment.php">
            <span class="material-symbols-outlined">calendar_add_on</span>
            <span>Book Appointment</span>
        </a>
        <a class="<?php echo nav_class('appointments.php'); ?>" href="appointments.php">
            <span class="material-symbols-outlined">calendar_month</span>
            <span>Appointments</span>
        </a>
        <a class="<?php echo nav_class('payments.php'); ?>" href="payments.php">
            <span class="material-symbols-outlined">payments</span>
            <span>Payments</span>
        </a>
        <a class="<?php echo nav_class('transaction_history.php'); ?>" href="transaction_history.php">
            <span class="material-symbols-outlined">receipt_long</span>
            <span>Transaction History</span>
        </a>
    </div>

    <div class="p-4 border-t border-[#e7ecf3] dark:border-slate-700">
        <a class="flex items-center gap-3 px-4 py-3 text-[#4c6c9a] dark:text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 dark:hover:text-red-400 rounded-lg transition-colors group text-sm font-bold"
           href="#" onclick="confirmLogout(event)">
            <span class="material-symbols-outlined group-hover:text-red-600 dark:group-hover:text-red-400">logout</span>
            <span>Log Out</span>
        </a>
    </div>

</aside>

