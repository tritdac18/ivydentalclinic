<?php
/**
 * views/layout/topbar.php
 *
 * Shared top header bar shown on every authenticated page (both portals).
 * Contains: mobile menu toggle, page title, user avatar + name.
 *
 * Required variables (set before including):
 *   $page_title   — string, e.g. 'Dashboard'
 *   $user_name    — logged-in user's display name
 *   $user_role    — 'Admin' | 'Manager' | 'Dentist' | 'Receptionist' | 'Patient'
 */

$_display_role = match ($user_role ?? '') {
    'Admin', 'Manager'   => 'Manager',
    'Dentist'            => 'Dentist',
    'Receptionist'       => 'Receptionist',
    default              => 'Patient',
};
?>
<header class="sticky top-0 z-10 bg-white dark:bg-[#1a2634] border-b border-[#e7ecf3] dark:border-slate-700 px-6 py-3 shadow-sm lg:shadow-none">
    <div class="flex items-center justify-between h-10">

        <!-- Mobile: menu + logo -->
        <div class="lg:hidden flex items-center gap-3">
            <button class="text-[#0d131b] dark:text-white" id="mobile-menu-toggle" aria-label="Open menu">
                <span class="material-symbols-outlined">menu</span>
            </button>
            <div class="size-8 text-primary">
                <span class="material-symbols-outlined text-3xl">dentistry</span>
            </div>
        </div>

        <!-- Desktop: page title -->
        <h1 class="hidden lg:block text-xl font-bold text-[#0d131b] dark:text-white">
            <?php echo e($page_title ?? 'Dashboard'); ?>
        </h1>

        <!-- Right: user chip -->
        <div class="flex items-center gap-4 ml-auto">
            <div class="relative group cursor-pointer flex items-center gap-2 pl-2 border-l border-[#e7ecf3] dark:border-slate-700">
                <div class="text-right hidden md:block">
                    <p class="text-sm font-bold text-[#0d131b] dark:text-white"><?php echo e($user_name ?? ''); ?></p>
                    <p class="text-xs text-[#4c6c9a]"><?php echo e($_display_role); ?></p>
                </div>
                <a href="profile.php" aria-label="View profile">
                    <div class="bg-primary/10 text-primary rounded-full size-9 border-2 border-white shadow-sm flex items-center justify-center hover:bg-primary/20 transition-colors">
                        <span class="material-symbols-outlined">person</span>
                    </div>
                </a>
            </div>
        </div>

    </div>
</header>

