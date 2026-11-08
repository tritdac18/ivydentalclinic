<?php
/**
 * views/layout/PAGE_TEMPLATE.php
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * COPY THIS FILE to create any new page. Delete what you don't need.
 * This shows the standard structure every page MUST follow after the refactor.
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * RULE: PHP logic at the top, HTML at the bottom. Never mix.
 */

// ── 1. Bootstrap ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../../config/db.php';       // loads helpers + session
require_once __DIR__ . '/../../config/security.php'; // requireLogin()

requireLogin(); // redirect to login.php if not authenticated

// ── 2. Role guard (remove / change for pages open to all roles) ───────────────
if (!has_role('Admin', 'Manager')) {
    redirect_to('landing.php');
}

// ── 3. Fetch data from the database via a Model (not raw SQL here) ────────────
// require_once ROOT_PATH . '/models/AppointmentModel.php';
// $appointments = AppointmentModel::getUpcoming();

// ── 4. Handle POST actions ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'do_something') {
        // ... business logic ...
        set_flash('Action completed successfully.', 'success');
        redirect_to($_SERVER['PHP_SELF']); // PRG pattern — prevents re-POST on refresh
    }
}

// ── 5. Prepare view variables ─────────────────────────────────────────────────
$page_title = 'Example Page';
$user_name  = $_SESSION['user_name'] ?? 'User';
$user_role  = $_SESSION['user_role'] ?? '';

// ════════════════════════════════════════════════════════════════════════════
// HTML OUTPUT starts here — no PHP logic below except echo / foreach / if
// ════════════════════════════════════════════════════════════════════════════
include ROOT_PATH . '/views/layout/header.php';     // <!DOCTYPE html> ... <body>
include ROOT_PATH . '/views/layout/sidebar_staff.php'; // <aside> nav (or sidebar_patient.php)
?>

<div class="flex-1 flex flex-col lg:pl-64 min-h-screen">
    <?php include ROOT_PATH . '/views/layout/topbar.php'; ?>  <!-- sticky top bar -->

    <main class="flex-grow w-full max-w-7xl mx-auto px-4 md:px-8 py-8">

        <?php render_flash(); ?>  <!-- shows any queued flash message, then clears it -->

        <!-- ── Page content goes here ── -->
        <div class="bg-white dark:bg-[#1e293b] rounded-xl border border-slate-200 dark:border-slate-700 p-6 shadow-sm">
            <h2 class="text-xl font-bold text-slate-900 dark:text-white mb-4">
                <?php echo e($page_title); ?>
            </h2>

            <!-- Example of using status_badge() helper -->
            <p>Status: <?php echo status_badge('Completed'); ?></p>

            <!-- Example of using currency() helper -->
            <p>Amount: <?php echo e(currency(1500.00)); ?></p>

            <!-- Example of using format_date() helper -->
            <p>Date: <?php echo e(format_date('2025-11-08 14:30:00')); ?></p>
        </div>

    </main>
</div><!-- /.flex-1 -->

<?php include ROOT_PATH . '/views/layout/footer.php'; ?>  <!-- </body></html> + shared JS -->

