<?php

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

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false, // Better security
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

