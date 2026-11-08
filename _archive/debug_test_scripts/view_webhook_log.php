<?php
/**
 * View Webhook Log
 * Visit: https://ivydentalbckup.gt.tc/view_webhook_log.php
 */

$logFile = __DIR__ . '/paymongo_webhook_incoming.log';

echo "<h1>PayMongo Webhook Log</h1>";
echo "<p>Log file: $logFile</p>";

if (file_exists($logFile)) {
    echo "<p style='color: green;'>✅ Log file exists!</p>";
    echo "<p>File size: " . filesize($logFile) . " bytes</p>";
    echo "<p>Last modified: " . date('Y-m-d H:i:s', filemtime($logFile)) . "</p>";
    
    echo "<hr>";
    echo "<h2>Log Contents:</h2>";
    echo "<pre style='background: #f5f5f5; padding: 20px; border: 1px solid #ddd; overflow-x: auto;'>";
    echo htmlspecialchars(file_get_contents($logFile));
    echo "</pre>";
    
    echo "<hr>";
    echo "<p><a href='?clear=1'>Clear Log</a> | <a href='?refresh=1'>Refresh</a></p>";
    
    if (isset($_GET['clear'])) {
        file_put_contents($logFile, '');
        echo "<p style='color: green;'>✅ Log cleared!</p>";
        echo "<meta http-equiv='refresh' content='1'>";
    }
} else {
    echo "<p style='color: red;'>❌ Log file does NOT exist yet.</p>";
    echo "<p>This means PayMongo has not called your webhook yet.</p>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ol>";
    echo "<li>Make a test payment</li>";
    echo "<li>Refresh this page</li>";
    echo "<li>If log still doesn't appear, PayMongo isn't calling your webhook</li>";
    echo "</ol>";
}

echo "<hr>";
echo "<p><a href='book_appointment.php'>Book Appointment</a> | <a href='auto_verify_payments.php'>Run Auto-Verify</a></p>";
?>
