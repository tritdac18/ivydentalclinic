<?php
/**
 * Test Maintenance Reminder Email - All Types
 * 
 * This script sends test maintenance reminder emails for all reminder types
 * to demonstrate the email system.
 * 
 * Usage:
 * php test_maintenance_reminder_email.php
 * 
 * The script will send 5 test emails to sstrkq@gmail.com:
 * 1. 2 weeks ahead - 72 hours before (17 days)
 * 2. 2 weeks ahead - 48 hours before (16 days)
 * 3. 2 weeks ahead - 24 hours before (15 days)
 * 4. 1 week ahead (7 days)
 * 5. Overdue reminder
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/services/email_service.php';

// Test email address
$test_email = 'sstrkq@gmail.com';
$patient_name = 'Test Patient';
$service_name = 'Braces Treatment';

// Set timezone
date_default_timezone_set('Asia/Manila');

echo "=== Maintenance Reminder Email Test ===\n";
echo "Recipient: {$test_email}\n";
echo "Patient: {$patient_name}\n";
echo "Service: {$service_name}\n";
echo "\n";

// Calculate test dates
$today = new DateTime();
$appointment_date = clone $today;
$appointment_date->modify('+20 days'); // Appointment in 20 days

$reminder_types = [
    'two_weeks_72h' => [
        'name' => '2 Weeks Ahead - 72 Hours Before (17 days)',
        'date_offset' => 17,
        'description' => 'First reminder sent 72 hours before the 2-week mark'
    ],
    'two_weeks_48h' => [
        'name' => '2 Weeks Ahead - 48 Hours Before (16 days)',
        'date_offset' => 16,
        'description' => 'Second reminder sent 48 hours before the 2-week mark'
    ],
    'two_weeks_24h' => [
        'name' => '2 Weeks Ahead - 24 Hours Before (15 days)',
        'date_offset' => 15,
        'description' => 'Final reminder sent 24 hours before the 2-week mark'
    ],
    'one_week' => [
        'name' => '1 Week Ahead (7 days)',
        'date_offset' => 7,
        'description' => 'Reminder sent 1 week before the appointment'
    ],
    'overdue' => [
        'name' => 'Overdue Reminder',
        'date_offset' => -5,
        'description' => 'Reminder sent after appointment date has passed'
    ]
];

$results = [];
$success_count = 0;
$fail_count = 0;

try {
    $emailService = new EmailService();
    
    echo "Sending test emails...\n";
    echo str_repeat('-', 60) . "\n";
    
    foreach ($reminder_types as $reminder_type => $info) {
        // Calculate next due date based on offset
        $next_due_date = clone $appointment_date;
        if ($info['date_offset'] > 0) {
            // For future dates, subtract the offset to get the "recommended" date
            $next_due_date->modify('-' . $info['date_offset'] . ' days');
        } else {
            // For overdue, the date is in the past
            $next_due_date->modify($info['date_offset'] . ' days');
        }
        
        $next_due_date_str = $next_due_date->format('Y-m-d');
        $formatted_date = $next_due_date->format('F d, Y');
        
        echo "\n[{$reminder_type}]\n";
        echo "Type: {$info['name']}\n";
        echo "Description: {$info['description']}\n";
        echo "Recommended Visit Date: {$formatted_date}\n";
        echo "Sending email...\n";
        
        try {
            $result = $emailService->sendMaintenanceReminderEmail(
                $test_email,
                $patient_name,
                $service_name,
                $next_due_date_str,
                $reminder_type
            );
            
            if ($result) {
                echo "✓ SUCCESS: Email sent!\n";
                $success_count++;
                $results[] = [
                    'type' => $reminder_type,
                    'status' => 'success',
                    'message' => 'Email sent successfully'
                ];
            } else {
                echo "✗ ERROR: Email service returned false\n";
                $fail_count++;
                $results[] = [
                    'type' => $reminder_type,
                    'status' => 'failed',
                    'message' => 'Email service returned false'
                ];
            }
        } catch (Exception $e) {
            echo "✗ ERROR: " . $e->getMessage() . "\n";
            $fail_count++;
            $results[] = [
                'type' => $reminder_type,
                'status' => 'failed',
                'message' => $e->getMessage()
            ];
        }
        
        // Small delay between emails
        sleep(2);
    }
    
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "=== Test Summary ===\n";
    echo "Total emails attempted: " . count($reminder_types) . "\n";
    echo "Successful: {$success_count}\n";
    echo "Failed: {$fail_count}\n";
    echo "\n";
    
    if ($success_count > 0) {
        echo "✓ Please check the inbox for: {$test_email}\n";
        echo "  If you don't see the emails, check the spam folder.\n";
        echo "\n";
        echo "You should receive {$success_count} email(s) with different reminder types:\n";
        foreach ($results as $result) {
            if ($result['status'] === 'success') {
                $info = $reminder_types[$result['type']];
                echo "  - {$info['name']}\n";
            }
        }
    }
    
    if ($fail_count > 0) {
        echo "\n";
        echo "✗ Failed emails:\n";
        foreach ($results as $result) {
            if ($result['status'] === 'failed') {
                $info = $reminder_types[$result['type']];
                echo "  - {$info['name']}: {$result['message']}\n";
            }
        }
        echo "\n";
        echo "Common issues:\n";
        echo "1. Gmail API not configured (check config/gmail_config.php)\n";
        echo "2. Token expired (run gmail_oauth.php to refresh)\n";
        echo "3. Invalid email address\n";
    }
    
} catch (Exception $e) {
    echo "\n✗ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== Test Complete ===\n";

