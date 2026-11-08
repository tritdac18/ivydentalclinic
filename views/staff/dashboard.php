<?php
/**
 * views/staff/dashboard.php
 * Dashboard content view — included by admin_dashboard.php after data is loaded.
 *
 * Expected variables (set by admin_dashboard.php):
 *   $page_title        string   "Dashboard"
 *   $user_name         string   Full name of logged-in staff
 *   $user_role         string   e.g. "Manager", "Dentist", "Receptionist"
 *   $admin_first_name  string   First name (for greeting)
 *   $admin_role        string   Role label
 *   $is_dentist        bool
 *   $is_receptionist   bool
 *   $profile_complete  bool
 *   $stats             array    ['today_count', 'new_patients', 'pending_count', 'revenue_mtd']
 *   $today_appointments array   Rows from tbl_appointments joined with patient + dentist + service
 *   $action_message    string   Optional flash/status message
 */
?>

<?php include ROOT_PATH . '/views/layout/header.php'; ?>

<div class="flex min-h-screen">

    <?php include ROOT_PATH . '/views/layout/sidebar_staff.php'; ?>

    <div class="flex-1 flex flex-col lg:ml-64">

        <?php include ROOT_PATH . '/views/layout/topbar.php'; ?>

        <main class="flex-grow w-full max-w-7xl mx-auto px-4 md:px-8 py-8">

            <?php if ($action_message): ?>
            <div class="max-w-7xl mx-auto mb-4">
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-700 dark:text-green-400 px-4 py-3 rounded-lg">
                    <?php echo e($action_message); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!$profile_complete): ?>
            <div class="max-w-7xl mx-auto mb-4">
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 text-amber-700 dark:text-amber-400 px-4 py-3 rounded-lg flex items-start gap-3">
                    <span class="material-symbols-outlined text-xl">warning</span>
                    <div class="flex-1">
                        <p class="font-semibold mb-1">Profile Incomplete</p>
                        <p class="text-sm">Please complete your profile to be properly prepared for certain features.
                            <a href="profile.php" class="underline font-medium">Update Profile</a></p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="max-w-7xl mx-auto space-y-8">

                <!-- Page heading -->
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-bold text-text-main dark:text-white tracking-tight">Dashboard Overview</h2>
                        <p class="text-text-muted dark:text-slate-400 mt-1">
                            Welcome back, <?php echo e($admin_first_name); ?>! Here's what's happening at the clinic today.
                        </p>
                    </div>
                    <div class="flex items-center gap-2 text-sm font-medium text-text-muted dark:text-slate-400 bg-white dark:bg-surface-dark px-4 py-2 rounded-lg border border-border-light dark:border-border-dark shadow-sm">
                        <span class="material-symbols-outlined text-[20px]">calendar_today</span>
                        <span><?php echo date('M d, Y'); ?></span>
                    </div>
                </div>

                <!-- Stats cards (hidden for Receptionist) -->
                <?php if (!$is_receptionist): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                    <!-- Today's appointments -->
                    <div class="bg-surface-light dark:bg-surface-dark p-6 rounded-xl border border-border-light dark:border-border-dark shadow-sm flex flex-col gap-3 relative overflow-hidden group">
                        <div class="absolute right-0 top-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity text-primary">
                            <span class="material-symbols-outlined text-[80px]">calendar_clock</span>
                        </div>
                        <p class="text-text-muted dark:text-slate-400 text-sm font-medium">Today's Appts</p>
                        <div class="flex items-baseline gap-2">
                            <h3 class="text-3xl font-bold text-text-main dark:text-white"><?php echo (int)$stats['today_count']; ?></h3>
                            <span class="text-xs font-semibold text-green-600 bg-green-100 dark:bg-green-900/30 dark:text-green-400 px-2 py-0.5 rounded-full flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">trending_up</span> 20%
                            </span>
                        </div>
                    </div>

                    <!-- New patients -->
                    <div class="bg-surface-light dark:bg-surface-dark p-6 rounded-xl border border-border-light dark:border-border-dark shadow-sm flex flex-col gap-3 relative overflow-hidden group">
                        <div class="absolute right-0 top-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity text-blue-500">
                            <span class="material-symbols-outlined text-[80px]">person_add</span>
                        </div>
                        <p class="text-text-muted dark:text-slate-400 text-sm font-medium">New Patients</p>
                        <div class="flex items-baseline gap-2">
                            <h3 class="text-3xl font-bold text-text-main dark:text-white"><?php echo (int)$stats['new_patients']; ?></h3>
                            <span class="text-xs font-semibold text-green-600 bg-green-100 dark:bg-green-900/30 dark:text-green-400 px-2 py-0.5 rounded-full flex items-center gap-1">
                                <span class="material-symbols-outlined text-[14px]">trending_up</span> 10%
                            </span>
                        </div>
                    </div>

                </div>
                <?php endif; ?>

                <!-- Today's Schedule table -->
                <div class="grid grid-cols-1 gap-8">
                    <div class="flex flex-col gap-4">

                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-text-main dark:text-white">Today's Schedule</h3>
                            <a href="appointments.php" class="text-sm font-medium text-primary hover:text-primary-dark transition-colors">View All</a>
                        </div>

                        <div class="bg-surface-light dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark overflow-hidden shadow-sm">
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left">
                                    <thead class="bg-slate-50 dark:bg-slate-800/50 text-text-muted dark:text-slate-400 font-medium border-b border-border-light dark:border-border-dark">
                                        <tr>
                                            <th class="px-6 py-4 whitespace-nowrap">Time</th>
                                            <th class="px-6 py-4 whitespace-nowrap">Patient</th>
                                            <th class="px-6 py-4 whitespace-nowrap">Doctor</th>
                                            <th class="px-6 py-4 whitespace-nowrap">Treatment</th>
                                            <th class="px-6 py-4 whitespace-nowrap">Status</th>
                                            <th class="px-6 py-4 whitespace-nowrap text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border-light dark:divide-border-dark">
                                        <?php if (empty($today_appointments)): ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-8 text-center text-text-muted dark:text-slate-400">
                                                No appointments scheduled for today.
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($today_appointments as $apt): ?>
                                        <?php
                                            $apt_time        = date('h:i A', strtotime($apt['scheduled_datetime']));
                                            $initials        = strtoupper(substr($apt['patient_first_name'], 0, 1) . substr($apt['patient_last_name'], 0, 1));
                                            $dentist_display = 'Dr. ' . e($apt['dentist_first_name'] . ' ' . $apt['dentist_last_name']);
                                            $status_map = [
                                                'pending'   => ['bg-yellow-50 dark:bg-yellow-900/20 text-yellow-800 dark:text-yellow-500 ring-yellow-600/20',  'Pending'],
                                                'scheduled' => ['bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 ring-blue-700/10',           'Scheduled'],
                                                'confirmed' => ['bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 ring-green-600/20',       'Confirmed'],
                                                'completed' => ['bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 ring-green-600/20',       'Completed'],
                                                'cancelled' => ['bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 ring-red-600/10',                 'Cancelled'],
                                            ];
                                            [$s_class, $s_label] = $status_map[strtolower($apt['status'])] ?? ['bg-slate-100 text-slate-600', e($apt['status'])];
                                        ?>
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                                            <td class="px-6 py-4 font-medium text-text-main dark:text-white whitespace-nowrap"><?php echo $apt_time; ?></td>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center gap-3">
                                                    <div class="size-8 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xs font-bold">
                                                        <?php echo e($initials); ?>
                                                    </div>
                                                    <div class="flex flex-col">
                                                        <span class="text-text-main dark:text-white font-medium"><?php echo e($apt['patient_first_name'] . ' ' . $apt['patient_last_name']); ?></span>
                                                        <span class="text-xs text-text-muted dark:text-slate-500">#<?php echo (int)$apt['appointment_id']; ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 text-text-muted dark:text-slate-400"><?php echo $dentist_display; ?></td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center rounded-md bg-slate-100 dark:bg-slate-800 px-2 py-1 text-xs font-medium text-slate-600 dark:text-slate-400 ring-1 ring-inset ring-slate-500/10">
                                                    <?php echo e($apt['service_name'] ?? '—'); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <span class="inline-flex items-center rounded-full <?php echo $s_class; ?> px-2 py-1 text-xs font-medium ring-1 ring-inset">
                                                    <?php echo $s_label; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <a href="appointments.php?highlight=<?php echo (int)$apt['appointment_id']; ?>"
                                                   class="text-text-muted hover:text-primary dark:text-slate-500 dark:hover:text-white transition-colors p-1 inline-block"
                                                   title="View appointment">
                                                    <span class="material-symbols-outlined text-[20px]">open_in_new</span>
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="p-4 border-t border-border-light dark:border-border-dark bg-slate-50/50 dark:bg-slate-800/20 flex justify-center">
                                <a href="appointments.php" class="text-sm text-text-muted hover:text-primary dark:text-slate-400 dark:hover:text-white transition-colors font-medium flex items-center gap-1">
                                    View all appointments <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                                </a>
                            </div>
                        </div><!-- /table card -->

                    </div>
                </div>

            </div><!-- /space-y-8 -->

        </main><!-- /.main -->

        <?php include ROOT_PATH . '/views/layout/footer.php'; ?>

    </div><!-- /flex-1 -->

</div><!-- /flex min-h-screen -->
