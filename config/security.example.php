<?php
/**
 * Security Functions — EXAMPLE / TEMPLATE
 * Copy this file to security.php.
 * No actual secrets — this file contains only logic.
 * Prevents browser back button access after logout.
 */

function setSecurityHeaders() {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: 0");
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
}

function requireLogin() {
    setSecurityHeaders();

    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.gc_maxlifetime', 900);
        ini_set('session.cookie_lifetime', 900);
        session_start();
    }

    $session_timeout = 900;

    if (!isset($_SESSION['user_id'])) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }

    $current_time = time();

    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = $current_time;
    }

    if (isset($_SESSION['last_activity']) && ($current_time - $_SESSION['last_activity']) > $session_timeout) {
        session_unset();
        session_destroy();

        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        session_start();
        $_SESSION['session_expired'] = true;
        $_SESSION['expiry_message']  = 'Your session has expired due to inactivity. Please login again.';

        header("Location: login.php?expired=1");
        exit();
    }

    $_SESSION['last_activity'] = $current_time;
}

function performLogout() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    setSecurityHeaders();
    session_unset();
    session_destroy();

    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    session_start();
}

function outputCachePreventionMeta() {
    echo '<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">' . "\n";
    echo '<meta http-equiv="Pragma" content="no-cache">' . "\n";
    echo '<meta http-equiv="Expires" content="0">' . "\n";
}

?>
