<?php

// ── Project root path ────────────────────────────────────────────────────────
// Use ROOT_PATH everywhere instead of relative paths or __DIR__ gymnastics.
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 1));   // one level up from /config
}

// ── Auto-load shared helpers ─────────────────────────────────────────────────
// Loaded once here so every file that includes config/db.php gets the helpers
// (e, redirect_to, set_flash, render_alert, nav_class, currency, etc.)
if (!function_exists('e')) {
    require_once ROOT_PATH . '/functions/helpers.php';
}

// ── Session ──────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 900);   // 15 minutes
    ini_set('session.cookie_lifetime', 900);
    session_start();
}

// Database configuration
$is_local = (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) 
            || (php_sapi_name() === 'cli');

if ($is_local) {
    $host = '127.0.0.1';
    $db = 'if0_40821754_db_ivydental';
    $user = 'root';
    $pass = '12345';
    $charset = 'utf8mb4';
} else {
    $host = 'sql205.infinityfree.com';
    $db = 'if0_40821754_db_ivydental';
    $user = 'if0_40821754';
    $pass = 'EVTbG7Thcba';
    $charset = 'utf8mb4';
}

// PDO Connection (for future use)
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false, // Better security
];

// PDO Connection - only create if needed (for future use)
// Don't die on error, just set to null so pages can still load
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Don't die - let pages load even if PDO fails
    // Pages using MySQLi will still work
    $pdo = null;
    // Uncomment for debugging: error_log("PDO Connection failed: " . $e->getMessage());
}

// MySQLi Connection function (for current codebase)
function getDBConnection() {
    global $host, $user, $pass, $db;
    
    $conn = new mysqli($host, $user, $pass, $db);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Set charset to utf8mb4
    $conn->set_charset("utf8mb4");
    
    // Set MySQL timezone using UTC offset directly (+08:00)
    // This ensures all NOW(), CURRENT_TIMESTAMP, etc. use the correct timezone
    @$conn->query("SET time_zone = '+08:00'");
    
    // Set PHP timezone to match MySQL (+08:00) without hardcoding "Manila"
    // Use a timezone that's +08:00 - try common ones in order
    $utc_offset_seconds = 8 * 3600; // +08:00 in seconds
    $possible_timezones = ['Asia/Singapore', 'Asia/Hong_Kong', 'Asia/Shanghai', 'Asia/Taipei', 'Asia/Kuala_Lumpur'];
    $timezone_set = false;
    foreach ($possible_timezones as $tz) {
        try {
            $dt = new DateTime('now', new DateTimeZone($tz));
            if ($dt->getOffset() == $utc_offset_seconds) {
                date_default_timezone_set($tz);
                $timezone_set = true;
                break;
            }
        } catch (Exception $e) {
            continue;
        }
    }
    // Fallback to UTC if no matching timezone found (unlikely)
    if (!$timezone_set) {
        date_default_timezone_set('UTC');
    }
    
    return $conn;
}

?>

