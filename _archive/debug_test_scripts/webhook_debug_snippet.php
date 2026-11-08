<?php
/**
 * PayMongo Webhook Test - Check if webhook is receiving events
 * Place this temporarily at the top of webhook.php to log ALL incoming requests
 */

// Log ALL webhook requests
$logFile = __DIR__ . '/../../paymongo_webhook_debug.log';
$timestamp = date('Y-m-d H:i:s');
$payload = @file_get_contents('php://input');
$headers = getallheaders();

$logEntry = "\n=== WEBHOOK REQUEST at $timestamp ===\n";
$logEntry .= "Headers: " . json_encode($headers, JSON_PRETTY_PRINT) . "\n";
$logEntry .= "Payload: " . $payload . "\n";
$logEntry .= "GET params: " . json_encode($_GET) . "\n";
$logEntry .= "POST params: " . json_encode($_POST) . "\n";
$logEntry .= "=== END ===\n\n";

file_put_contents($logFile, $logEntry, FILE_APPEND);

// Continue with normal webhook processing...
