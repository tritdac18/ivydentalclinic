<?php
// Database configuration - PDO Version (Compatible with your old code)
// You can use this instead of database.php if you prefer PDO

$host = 'sql100.infinityfree.com';
$db = 'if0_40566949_trisha_db';
$user = 'if0_40566949';
$pass = 'UEF4FGvVMD28';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to get PDO connection (for compatibility)
function getDBConnection() {
    global $pdo;
    return $pdo;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

