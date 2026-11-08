<?php
/**
 * views/layout/sidebar_staff.php
 *
 * Shared sidebar navigation for all STAFF pages:
 *   admin_dashboard, appointments, invoices, payments, accounts,
 *   reports, add_service, profile, dentist pages, etc.
 *
 * Required variables (set by the including controller/page):
 *   $user_role   — 'Admin' | 'Manager' | 'Dentist' | 'Receptionist'
 *
 * Relies on nav_class() from functions/helpers.php
 */

$_role     = $user_role ?? current_user_role() ?? '';
$_isDentist     = ($_role === 'Dentist');
$_isReceptionist= ($_role === 'Receptionist');
$_isManager     = ($_role === 'Manager' || $_role === 'Admin');

$_portalLabel = match (true) {
    $_isDentist      => 'Dentist Portal',
    $_isReceptionist => 'Staff Portal',
    default          => 'Manager Portal',
};
?>
<aside class="w-64 bg-white dark:bg-[#1a2634] border-r border-[#e7ecf3] dark:border-slate-700 hidden lg:flex flex-col fixed h-full z-20">

    <?php /* Logo / clinic name */ ?>
    <div class="h-16 flex items-center px-6 border-b border-[#e7ecf3] dark:border-slate-700">
        <div class="flex items-center gap-3">
            <div class="size-8 text-primary">
                <span class="material-symbols-outlined text-3xl">dentistry</span>
            </div>
            <div>
                <h2 class="text-[#0d131b] dark:text-white text-lg font-bold leading-tight tracking-[-0.015em]">Ivy Dental Clinic</h2>
                <p class="text-xs font-semibold text-[#4c6c9a] dark:text-slate-400"><?php echo e($_portalLabel); ?></p>
            </div>
        </div>
    </div>

    <?php /* Navigation links */ ?>
    <div class="p-4 flex flex-col gap-1 flex-1 overflow-y-auto">

        <?php if ($_isDentist): ?>
        <?php /* Dentist nav */ ?>
            <a class="<?php echo nav_class('admin_dashboard.php'); ?>" href="admin_dashboard.php">
                <span class="material-symbols-outlined">dashboard</span>
                <span>Dashboard</span>
            </a>
            <a class="<?php echo nav_class('profile.php'); ?>" href="profile.php">
                <span class="material-symbols-outlined">person</span>
                <span>Profile</span>
            </a>
            <a class="<?php echo nav_class('appointments.php'); ?>" href="appointments.php">
                <span class="material-symbols-outlined">calendar_month</span>
                <span>Appointments</span>
            </a>
            <a class="<?php echo nav_class('dentist_patients.php'); ?>" href="dentist_patients.php">
                <span class="material-symbols-outlined">people</span>
                <span>My Patients</span>
            </a>
            <a class="<?php echo nav_class('dentist_upload_files.php'); ?>" href="dentist_upload_files.php">
                <span class="material-symbols-outlined">upload_file</span>
                <span>Upload Files</span>
            </a>
            <a class="<?php echo nav_class('appointment_history.php'); ?>" href="appointment_history.php">
                <span class="material-symbols-outlined">history</span>
                <span>Appointment History</span>
            </a>

        <?php elseif ($_isReceptionist): ?>
            <?php /* Receptionist nav */ ?>
            <a class="<?php echo nav_class('admin_dashboard.php'); ?>" href="admin_dashboard.php">
                <span class="material-symbols-outlined">dashboard</span>
                <span>Dashboard</span>
            </a>
            <a class="<?php echo nav_class('profile.php'); ?>" href="profile.php">
                <span class="material-symbols-outlined">person</span>
                <span>Profile</span>
            </a>
            <a class="<?php echo nav_class('appointments.php'); ?>" href="appointments.php">
                <span class="material-symbols-outlined">calendar_month</span>
                <span>Appointments</span>
            </a>
            <a class="<?php echo nav_class('invoices.php'); ?>" href="invoices.php">
                <span class="material-symbols-outlined">receipt_long</span>
                <span>Invoices</span>
            </a>
            <a class="<?php echo nav_class('payments.php'); ?>" href="payments.php">
                <span class="material-symbols-outlined">payments</span>
                <span>Payments</span>
            </a>

        <?php else: ?>
            <?php /* Admin / Manager nav */ ?>
            <a class="<?php echo nav_class('admin_dashboard.php'); ?>" href="admin_dashboard.php">
                <span class="material-symbols-outlined">dashboard</span>
                <span>Dashboard</span>
            </a>
            <a class="<?php echo nav_class('profile.php'); ?>" href="profile.php">
                <span class="material-symbols-outlined">person</span>
                <span>Profile</span>
            </a>
            <a class="<?php echo nav_class('appointments.php'); ?>" href="appointments.php">
                <span class="material-symbols-outlined">calendar_month</span>
                <span>Appointments</span>
            </a>
            <a class="<?php echo nav_class('invoices.php'); ?>" href="invoices.php">
                <span class="material-symbols-outlined">receipt_long</span>
                <span>Invoices</span>
            </a>
            <a class="<?php echo nav_class('payments.php'); ?>" href="payments.php">
                <span class="material-symbols-outlined">payments</span>
                <span>Payments</span>
            </a>
            <a class="<?php echo nav_class('reports.php'); ?>" href="reports.php">
                <span class="material-symbols-outlined">description</span>
                <span>Reports</span>
            </a>
            <a class="<?php echo nav_class('accounts.php'); ?>" href="accounts.php">
                <span class="material-symbols-outlined">group</span>
                <span>Accounts</span>
            </a>
            <a class="<?php echo nav_class('add_service.php'); ?>" href="add_service.php">
                <span class="material-symbols-outlined">add_business</span>
                <span>Service Management</span>
            </a>
        <?php endif; ?>

    </div>

    <?php /* Logout */ ?>
    <div class="p-4 border-t border-[#e7ecf3] dark:border-slate-700">
        <a class="flex items-center gap-3 px-4 py-3 text-[#4c6c9a] dark:text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 dark:hover:text-red-400 rounded-lg transition-colors group text-sm font-bold"
           href="#" onclick="confirmLogout(event)">
            <span class="material-symbols-outlined group-hover:text-red-600 dark:group-hover:text-red-400">logout</span>
            <span>Log Out</span>
        </a>
    </div>

</aside>

