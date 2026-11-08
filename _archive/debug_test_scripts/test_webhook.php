<?php
/**
 * Test if webhook.php is accessible
 */

echo "✅ Webhook endpoint is accessible!\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n";
echo "URL: " . $_SERVER['REQUEST_URI'] . "\n";
echo "Method: " . $_SERVER['REQUEST_METHOD'] . "\n";

// Log this test
error_log("Webhook test accessed at " . date('Y-m-d H:i:s'));

// Test if we can write to log
$logFile = __DIR__ . '/../../paymongo_webhook_test.log';
file_put_contents($logFile, "Test at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
echo "\n✅ Log file writable at: $logFile\n";
