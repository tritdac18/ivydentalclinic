<?php
/**
 * Maintenance Reminder Cron Job
 * 
 * This script should be run daily (via cron) to check for patients who need
 * maintenance reminders and send them via email.
 * 
 * Reminder Schedule:
 * - First reminder: 7-10 days before next due date
 * - Second reminder: 3-5 days before next due date
 * - Overdue reminder: 7 days after missed window
 * 
 * Usage (cron):
 * 0 9 * * * /usr/bin/php /path/to/send_maintenance_reminders.php
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/services/email_service.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

// Log file for tracking
$log_file = __DIR__ . '/logs/maintenance_reminders.log';
$log_dir = dirname($log_file);
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

function logMessage($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[{$timestamp}] {$message}\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    echo $log_entry;
}

try {
    logMessage("=== Starting Maintenance Reminder Check ===");
    
    $conn = getDBConnection();
    
    // Check if required tables exist
    $tables_check = $conn->query("SHOW TABLES LIKE 'tbl_patient_maintenance'");
    if (!$tables_check || $tables_check->num_rows === 0) {
        logMessage("ERROR: tbl_patient_maintenance table does not exist. Please run create_tbl_patient_maintenance.sql");
        exit(1);
    }
    
    $reminders_check = $conn->query("SHOW TABLES LIKE 'tbl_maintenance_reminders'");
    if (!$reminders_check || $reminders_check->num_rows === 0) {
        logMessage("ERROR: tbl_maintenance_reminders table does not exist. Please run create_tbl_maintenance_reminders.sql");
        exit(1);
    }
    
    // Fetch active maintenance plans with upcoming or overdue visits
    $today = date('Y-m-d');
    $query = "
        SELECT 
            pm.maintenance_id,
            pm.patient_id,
            pm.service_id,
            pm.last_visit_date,
            pm.recommended_next_visit,
            pm.status,
            p.first_name,
            p.last_name,
            p.email,
            sv.service_name,
            sv.maintenance_interval
        FROM tbl_patient_maintenance pm
        INNER JOIN tbl_patients p ON pm.patient_id = p.patient_id
        INNER JOIN tbl_service_catalog sv ON pm.service_id = sv.service_id
        WHERE pm.status != 'cancelled'
        AND pm.recommended_next_visit IS NOT NULL
        AND p.email IS NOT NULL
        AND p.email != ''
    ";
    
    $result = $conn->query($query);
    $reminders_sent = 0;
    $reminders_failed = 0;
    
    if ($result && $result->num_rows > 0) {
        logMessage("Found " . $result->num_rows . " active maintenance plan(s)");
        
        $emailService = new EmailService();
        
        while ($row = $result->fetch_assoc()) {
            $next_visit = new DateTime($row['recommended_next_visit']);
            $today_obj = new DateTime($today);
            $days_until = $today_obj->diff($next_visit)->days;
            $is_past = $next_visit < $today_obj;
            
            // Determine which reminder to send
            $reminder_type = null;
            $should_send = false;
            
            if ($is_past) {
                // Overdue: 7+ days after due date
                $days_overdue = $today_obj->diff($next_visit)->days;
                if ($days_overdue >= 7) {
                    $reminder_type = 'overdue';
                    $should_send = true;
                }
            } else {
                // First reminder: 7-10 days before
                if ($days_until >= 7 && $days_until <= 10) {
                    $reminder_type = 'first';
                    $should_send = true;
                }
                // Second reminder: 3-5 days before
                elseif ($days_until >= 3 && $days_until <= 5) {
                    $reminder_type = 'second';
                    $should_send = true;
                }
            }
            
            if ($should_send && $reminder_type) {
                // Check if we've already sent this type of reminder for this maintenance
                $check_stmt = $conn->prepare("
                    SELECT reminder_id FROM tbl_maintenance_reminders 
                    WHERE maintenance_id = ? 
                    AND reminder_type = ? 
                    AND email_sent = 1
                    AND DATE(scheduled_send_date) = ?
                ");
                $scheduled_date = $today;
                $check_stmt->bind_param("iss", $row['maintenance_id'], $reminder_type, $scheduled_date);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $check_stmt->close();
                
                if ($check_result->num_rows === 0) {
                    // Send reminder email
                    try {
                        $patient_name = $row['first_name'];
                        $emailService->sendMaintenanceReminderEmail(
                            $row['email'],
                            $patient_name,
                            $row['service_name'],
                            $row['recommended_next_visit'],
                            $reminder_type
                        );
                        
                        // Record reminder in database
                        $insert_stmt = $conn->prepare("
                            INSERT INTO tbl_maintenance_reminders 
                            (maintenance_id, patient_id, reminder_type, scheduled_send_date, sent_at, email_sent) 
                            VALUES (?, ?, ?, ?, NOW(), 1)
                        ");
                        $insert_stmt->bind_param("iiss", 
                            $row['maintenance_id'], 
                            $row['patient_id'], 
                            $reminder_type, 
                            $scheduled_date
                        );
                        $insert_stmt->execute();
                        $insert_stmt->close();
                        
                        $reminders_sent++;
                        logMessage("✓ Sent {$reminder_type} reminder to {$row['email']} for {$row['service_name']} (Maintenance ID: {$row['maintenance_id']})");
                    } catch (Exception $e) {
                        $reminders_failed++;
                        logMessage("✗ Failed to send reminder to {$row['email']}: " . $e->getMessage());
                        
                        // Record failed attempt
                        $insert_stmt = $conn->prepare("
                            INSERT INTO tbl_maintenance_reminders 
                            (maintenance_id, patient_id, reminder_type, scheduled_send_date, email_sent, email_error) 
                            VALUES (?, ?, ?, ?, 0, ?)
                        ");
                        $error_msg = substr($e->getMessage(), 0, 500);
                        $insert_stmt->bind_param("iisss", 
                            $row['maintenance_id'], 
                            $row['patient_id'], 
                            $reminder_type, 
                            $scheduled_date,
                            $error_msg
                        );
                        $insert_stmt->execute();
                        $insert_stmt->close();
                    }
                } else {
                    logMessage("- Skipped: {$reminder_type} reminder already sent today for Maintenance ID {$row['maintenance_id']}");
                }
            }
        }
    } else {
        logMessage("No active maintenance plans found");
    }
    
    $conn->close();
    
    logMessage("=== Completed ===");
    logMessage("Reminders sent: {$reminders_sent}");
    logMessage("Reminders failed: {$reminders_failed}");
    
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}

