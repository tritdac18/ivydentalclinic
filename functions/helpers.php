<?php
/**
 * helpers.php — Shared Helper Functions
 *
 * Centralises all reusable utilities so every page/controller can call them
 * without copy-pasting the same logic. Include once at the top of any file
 * that needs these:
 *
 *   require_once __DIR__ . '/../functions/helpers.php';
 *   (or from root)  require_once 'functions/helpers.php';
 */

// ─────────────────────────────────────────────
// 1. OUTPUT ESCAPING
// ─────────────────────────────────────────────

/**
 * Safely escape a value for HTML output.
 * Use this everywhere a PHP variable is echoed inside HTML.
 *
 * Usage: echo e($user_name);
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ─────────────────────────────────────────────
// 2. REDIRECTS
// ─────────────────────────────────────────────

/**
 * Perform an HTTP redirect and stop execution.
 *
 * Usage: redirect_to('login.php');
 *        redirect_to('admin_dashboard.php');
 */
function redirect_to(string $location): never
{
    header('Location: ' . $location);
    exit();
}

/**
 * Redirect back to previous page (or fallback URL).
 */
function redirect_back(string $fallback = 'index.php'): never
{
    $ref = $_SERVER['HTTP_REFERER'] ?? $fallback;
    redirect_to($ref);
}

// ─────────────────────────────────────────────
// 3. FLASH MESSAGES  (set → redirect → get → show)
// ─────────────────────────────────────────────

/**
 * Store a one-time message in the session to show after a redirect.
 *
 * @param string $message  Human-readable message text
 * @param string $type     'success' | 'error' | 'warning' | 'info'
 *
 * Usage: set_flash('Patient record saved.', 'success');
 *        redirect_to('accounts.php');
 */
function set_flash(string $message, string $type = 'info'): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['_flash_message'] = $message;
    $_SESSION['_flash_type']    = $type;
}

/**
 * Retrieve and clear the stored flash message.
 * Returns ['message' => '...', 'type' => '...'] or empty strings if none.
 *
 * Usage: $flash = get_flash();
 *        render_alert($flash['message'], $flash['type']);
 */
function get_flash(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $message = $_SESSION['_flash_message'] ?? '';
    $type    = $_SESSION['_flash_type']    ?? 'info';
    unset($_SESSION['_flash_message'], $_SESSION['_flash_type']);
    return ['message' => $message, 'type' => $type];
}

/**
 * Returns true if a flash message is currently stored.
 */
function has_flash(): bool
{
    return !empty($_SESSION['_flash_message']);
}

// ─────────────────────────────────────────────
// 4. ALERT HTML RENDERING
// ─────────────────────────────────────────────

/**
 * Echo a styled Tailwind alert box.
 * Outputs nothing when $message is empty.
 *
 * @param string $message  The text to display
 * @param string $type     'success' | 'error' | 'warning' | 'info'
 *
 * Usage: render_alert($flash['message'], $flash['type']);
 *        render_alert('Changes saved!', 'success');
 */
function render_alert(string $message, string $type = 'info'): void
{
    if ($message === '') {
        return;
    }

    $styles = [
        'success' => 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-700 dark:text-green-400',
        'error'   => 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-700 dark:text-red-400',
        'warning' => 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800 text-yellow-700 dark:text-yellow-400',
        'info'    => 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-400',
    ];

    $icons = [
        'success' => 'check_circle',
        'error'   => 'error',
        'warning' => 'warning',
        'info'    => 'info',
    ];

    $style = $styles[$type] ?? $styles['info'];
    $icon  = $icons[$type]  ?? $icons['info'];

    echo '<div class="flex items-start gap-3 border px-4 py-3 rounded-lg mb-4 ' . $style . '">';
    echo '<span class="material-symbols-outlined text-xl mt-0.5">' . $icon . '</span>';
    echo '<p class="text-sm font-medium">' . e($message) . '</p>';
    echo '</div>';
}

/**
 * Convenience wrapper — render any pending flash message then clear it.
 * Call once at the top of main page content.
 *
 * Usage: render_flash();
 */
function render_flash(): void
{
    $flash = get_flash();
    if ($flash['message'] !== '') {
        render_alert($flash['message'], $flash['type']);
    }
}

// ─────────────────────────────────────────────
// 5. CURRENT-PAGE HELPER (for sidebar nav)
// ─────────────────────────────────────────────

/**
 * Returns the CSS classes for a nav link, highlighting the active page.
 *
 * @param string $page  Filename to match, e.g. 'appointments.php'
 *
 * Usage:
 *   <a class="<?php echo nav_class('appointments.php'); ?>" href="appointments.php">
 */
function nav_class(string $page): string
{
    $current = basename($_SERVER['PHP_SELF']);
    $base    = 'flex items-center gap-3 px-4 py-3 rounded-lg transition-colors text-sm font-bold';
    if ($current === $page) {
        return $base . ' bg-primary/10 text-primary';
    }
    return $base . ' text-[#4c6c9a] dark:text-slate-400 hover:bg-[#f0f4f8] dark:hover:bg-slate-800';
}

// ─────────────────────────────────────────────
// 6. SESSION / AUTH HELPERS
// ─────────────────────────────────────────────

/**
 * Returns the currently logged-in user's ID from the session.
 */
function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

/**
 * Returns the currently logged-in user's type: 'patient' | 'staff' | null
 */
function current_user_type(): ?string
{
    return $_SESSION['user_type'] ?? null;
}

/**
 * Returns the currently logged-in staff member's role, or null for patients.
 */
function current_user_role(): ?string
{
    return $_SESSION['user_role'] ?? null;
}

/**
 * Returns true if the current session user has one of the given roles.
 *
 * Usage: if (has_role('Admin', 'Manager')) { ... }
 */
function has_role(string ...$roles): bool
{
    $role = current_user_role();
    return $role !== null && in_array($role, $roles, true);
}

/**
 * Returns true if a user is currently logged in.
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

// ─────────────────────────────────────────────
// 7. FORMATTING HELPERS
// ─────────────────────────────────────────────

/**
 * Format a decimal number as Philippine Peso currency.
 *
 * Usage: echo currency(1500.50); // "₱1,500.50"
 */
function currency(float|int|string $amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

/**
 * Format a datetime string for display.
 *
 * Usage: echo format_date('2025-11-08 14:30:00'); // "Nov 8, 2025 2:30 PM"
 */
function format_date(string $datetime, string $format = 'M j, Y g:i A'): string
{
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
        return '—';
    }
    try {
        return (new DateTime($datetime))->format($format);
    } catch (Exception) {
        return $datetime;
    }
}

/**
 * Format a date-only string.
 *
 * Usage: echo format_date_only('2025-11-08'); // "Nov 8, 2025"
 */
function format_date_only(string $date, string $format = 'M j, Y'): string
{
    return format_date($date, $format);
}

/**
 * Returns a human-readable "time ago" string.
 *
 * Usage: echo time_ago('2025-11-07 10:00:00'); // "1 day ago"
 */
function time_ago(string $datetime): string
{
    if (empty($datetime)) return '—';
    try {
        $diff = (new DateTime())->diff(new DateTime($datetime));
        if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
        if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
        if ($diff->d > 0) return $diff->d . ' day'   . ($diff->d > 1 ? 's' : '') . ' ago';
        if ($diff->h > 0) return $diff->h . ' hour'  . ($diff->h > 1 ? 's' : '') . ' ago';
        if ($diff->i > 0) return $diff->i . ' minute'. ($diff->i > 1 ? 's' : '') . ' ago';
        return 'just now';
    } catch (Exception) {
        return $datetime;
    }
}

/**
 * Truncate a string to a given length, appending "..." if truncated.
 *
 * Usage: echo truncate($long_text, 80);
 */
function truncate(string $text, int $length = 100): string
{
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . '…';
}

// ─────────────────────────────────────────────
// 8. STATUS BADGE
// ─────────────────────────────────────────────

/**
 * Echo a coloured Tailwind status badge span.
 *
 * Usage: echo status_badge('Scheduled');
 *        echo status_badge('Completed');
 */
function status_badge(string $status): string
{
    $map = [
        'Scheduled'           => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Confirmed'           => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
        'Completed'           => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        'Cancelled'           => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        'No-Show'             => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400',
        'Pending'             => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        'active'              => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        'inactive'            => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
        'pending_verification'=> 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
        'Paid'                => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        'Partial'             => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        'Unpaid'              => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    ];

    $class = $map[$status] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold ' . $class . '">' . e($status) . '</span>';
}

