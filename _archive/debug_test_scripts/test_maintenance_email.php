<?php
/**
 * Test Maintenance Reminder Email
 * 
 * This script sends a test maintenance reminder email to verify
 * the email service is working correctly.
 * 
 * Usage:
 * php test_maintenance_email.php [email] [reminder_type]
 * 
 * Arguments:
 *   email (required) - Your actual email address to receive the test email
 *   reminder_type (optional) - Type of reminder: 'first', 'second', or 'overdue' (default: 'first')
 * 
 * Examples:
 *   php test_maintenance_email.php your-email@example.com first
 *   php test_maintenance_email.php your-email@example.com second
 *   php test_maintenance_email.php your-email@example.com overdue
 * 
 * Note: If no email is provided, it will use 'test@example.com' (placeholder)
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/services/email_service.php';

// Get email from command line or use default
$test_email = $argv[1] ?? 'test@example.com';
$reminder_type = $argv[2] ?? 'first'; // first, second, or overdue

// Warn if using placeholder email
if ($test_email === 'test@example.com') {
    echo "⚠ WARNING: Using placeholder email 'test@example.com'\n";
    echo "Please provide your actual email address as the first argument:\n";
    echo "  php test_maintenance_email.php your-email@example.com [reminder_type]\n\n";
    echo "Continuing with test email anyway...\n\n";
}

// Validate email format
if (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
    echo "ERROR: Invalid email address format: {$test_email}\n";
    exit(1);
}

// Validate reminder type
if (!in_array($reminder_type, ['first', 'second', 'overdue'])) {
    echo "ERROR: Invalid reminder type. Must be 'first', 'second', or 'overdue'\n";
    exit(1);
}

// Calculate test dates
$today = new DateTime();
$next_due_date = clone $today;

if ($reminder_type === 'first') {
    // 7-10 days in the future
    $next_due_date->modify('+8 days');
} elseif ($reminder_type === 'second') {
    // 3-5 days in the future
    $next_due_date->modify('+4 days');
} else {
    // Overdue: 7+ days in the past
    $next_due_date->modify('-10 days');
}

$next_due_date_str = $next_due_date->format('Y-m-d');

echo "=== Testing Maintenance Reminder Email ===\n";
echo "Recipient: {$test_email}\n";
echo "Reminder Type: {$reminder_type}\n";
echo "Service: Braces (Test)\n";
echo "Next Due Date: {$next_due_date_str}\n";
echo "\n";

try {
    $emailService = new EmailService();
    
    echo "Sending email...\n";
    $result = $emailService->sendMaintenanceReminderEmail(
        $test_email,
        'Test Patient',
        'Braces',
        $next_due_date_str,
        $reminder_type
    );
    
    if ($result) {
        echo "✓ SUCCESS: Email sent successfully!\n";
        echo "\n";
        echo "Please check the inbox for: {$test_email}\n";
        echo "If you don't see it, check the spam folder.\n";
    } else {
        echo "✗ ERROR: Email service returned false\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "\n";
    echo "Common issues:\n";
    echo "1. Gmail API not configured (check config/gmail_config.php)\n";
    echo "2. Token expired (run gmail_oauth.php to refresh)\n";
    echo "3. Invalid email address\n";
    echo "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== Test Complete ===\n";

