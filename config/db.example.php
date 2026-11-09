<?php

// ── Project root path ─────────────────────────────────────────────────
// Use ROOT_PATH everywhere instead of relative paths or __DIR__ gymnastics.
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 1));   // one level up from /config
}

// ── Auto-load shared helpers ──────────────────────────────────────────
if (!function_exists('e')) {
    require_once ROOT_PATH . '/functions/helpers.php';
}

// ── Session ───────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 900);   // 15 minutes
    ini_set('session.cookie_lifetime', 900);
    session_start();
}

// Database configuration
// Copy this file to db.php and fill in your actual credentials.
$is_local = (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false))
            || (php_sapi_name() === 'cli');

if ($is_local) {
    $host    = '127.0.0.1';
    $db      = 'your_local_database_name';   // <-- fill in
    $user    = 'root';
    $pass    = 'your_local_password';        // <-- fill in
    $charset = 'utf8mb4';
} else {
    $host    = 'your_production_db_host';    // <-- fill in
    $db      = 'your_production_db_name';    // <-- fill in
    $user    = 'your_production_db_user';    // <-- fill in
    $pass    = 'your_production_db_pass';    // <-- fill in
    $charset = 'utf8mb4';
}

// PDO Connection
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    $pdo = null;
}

// MySQLi Connection function
function getDBConnection() {
    global $host, $user, $pass, $db;

    $conn = new mysqli($host, $user, $pass, $db);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $conn->set_charset("utf8mb4");
    @$conn->query("SET time_zone = '+08:00'");

    $utc_offset_seconds = 8 * 3600;
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
    if (!$timezone_set) {
        date_default_timezone_set('UTC');
    }

    return $conn;
}

?>
