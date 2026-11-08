<?php
/**
 * Security Functions
 * Prevents browser back button access after logout
 */

/**
 * Set security headers to prevent caching and back button access
 * Call this function at the start of every protected page
 */
function setSecurityHeaders() {
    // Prevent caching
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Additional security headers
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("X-XSS-Protection: 1; mode=block");
}

/**
 * Check if user is logged in and redirect if not
 * Also sets security headers and checks for session expiry (15 minutes inactivity)
 */
function requireLogin() {
    // Set security headers first
    setSecurityHeaders();
    
    // Check if session is started
    if (session_status() === PHP_SESSION_NONE) {
        // Configure session timeout (15 minutes = 900 seconds)
        ini_set('session.gc_maxlifetime', 900);
        ini_set('session.cookie_lifetime', 900);
        session_start();
    }
    
    // Session timeout in seconds (15 minutes)
    $session_timeout = 900; // 15 minutes
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        // Clear any session data
        session_unset();
        session_destroy();
        
        // Redirect to login
        header("Location: login.php");
        exit();
    }
    
    // Check for session expiry due to inactivity
    $current_time = time();
    
    // Initialize last activity time if not set (for existing sessions)
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = $current_time;
    }
    
    // Check if session has expired (15 minutes of inactivity)
    if (isset($_SESSION['last_activity']) && ($current_time - $_SESSION['last_activity']) > $session_timeout) {
        // Session expired - destroy session and redirect to login
        session_unset();
        session_destroy();
        
        // Clear session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Start new session to store expiry message
        session_start();
        $_SESSION['session_expired'] = true;
        $_SESSION['expiry_message'] = 'Your session has expired due to inactivity. Please login again.';
        
        // Redirect to login
        header("Location: login.php?expired=1");
        exit();
    }
    
    // Update last activity time on each page load/request
    $_SESSION['last_activity'] = $current_time;
}

/**
 * Enhanced logout with proper cache clearing
 */
function performLogout() {
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set headers to prevent caching
    setSecurityHeaders();
    
    // Destroy all session data
    session_unset();
    session_destroy();
    
    // Clear session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Start new session for messages
    session_start();
}

/**
 * Output HTML meta tags to prevent caching
 * Call this in the <head> section of protected pages
 */
function outputCachePreventionMeta() {
    echo '<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">' . "\n";
    echo '<meta http-equiv="Pragma" content="no-cache">' . "\n";
    echo '<meta http-equiv="Expires" content="0">' . "\n";
}

?>

