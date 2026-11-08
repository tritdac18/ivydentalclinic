<?php
/**
 * AJAX endpoint to check dentist availability
 * Flow:
 * 1. Select dentist + service -> Check if service specialization matches dentist specialization
 * 2. Select date -> Check if dentist has schedule for that day_of_week
 * 3. Select time -> Block time periods consumed by existing appointments
 */

// Enable error reporting for debugging (but don't display errors to user)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Wrap require statements in try-catch
try {
    // Get the directory of this file
    $base_dir = dirname(__FILE__);
    
    // Load required files with error checking
    $db_file = $base_dir . '/config/db.php';
    $security_file = $base_dir . '/config/security.php';
    // dentist_availability.php is in the functions folder
    $availability_file = $base_dir . '/functions/dentist_availability.php';
    
    if (!file_exists($db_file)) {
        throw new Exception("db.php not found at: " . $db_file);
    }
    if (!file_exists($security_file)) {
        throw new Exception("security.php not found at: " . $security_file);
    }
    if (!file_exists($availability_file)) {
        throw new Exception("dentist_availability.php not found at: " . $availability_file);
    }
    
    require_once $db_file;
    require_once $security_file;
    require_once $availability_file;
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Configuration error: ' . $e->getMessage()]);
    exit;
} catch (Error $e) {
    echo json_encode(['error' => 'Fatal error: ' . $e->getMessage()]);
    exit;
}

// Wrap main code in try-catch to handle errors gracefully
try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    // Get parameters - make date and time optional for step-by-step checking
    $dentist_id = isset($_GET['dentist_id']) ? intval($_GET['dentist_id']) : 0;
    $date = isset($_GET['date']) ? trim($_GET['date']) : '';
    $time = isset($_GET['time']) ? trim($_GET['time']) : '';
    $duration_minutes = isset($_GET['duration']) ? intval($_GET['duration']) : 60;
    $service_id = isset($_GET['service_id']) ? intval($_GET['service_id']) : null;

    // Minimum required: dentist_id
    if ($dentist_id <= 0) {
        echo json_encode(['error' => 'Invalid dentist ID']);
        exit;
    }

    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Initialize response structure
    $response = [
        'available' => true,
        'service_available' => true,
        'day_available' => true,
        'time_available' => true,
        'service_reason' => '',
        'day_reason' => '',
        'time_reason' => '',
        'reason' => '',
        'schedule' => null,
        'occupied_times' => []
    ];

    // ============================================
    // STEP 1: Check Service Specialization Match
    // Rule: If service has required specializations, dentist must have at least one matching specialization
    // If service has no required specializations, any dentist can perform it
    // ============================================
    if ($service_id && $service_id > 0) {
        // First, verify the service exists and is active
        $service_check_stmt = $conn->prepare("SELECT service_id, service_name FROM tbl_service_catalog WHERE service_id = ? AND is_active = 1");
        $service_exists = false;
        $service_name = '';
        if ($service_check_stmt) {
            $service_check_stmt->bind_param("i", $service_id);
            $service_check_stmt->execute();
            $service_check_result = $service_check_stmt->get_result();
            if ($service_check_result->num_rows > 0) {
                $service_row = $service_check_result->fetch_assoc();
                $service_exists = true;
                $service_name = $service_row['service_name'];
            }
            $service_check_stmt->close();
        }
        
        if (!$service_exists) {
            $response['service_available'] = false;
            $response['service_reason'] = 'Service not found or is inactive.';
            $response['available'] = false;
        } else {
            // Get service specializations (what specializations are required for this service)
            $service_specializations = [];
            $service_spec_stmt = $conn->prepare("SELECT specialization_id FROM tbl_service_specializations WHERE service_id = ?");
            if ($service_spec_stmt) {
                $service_spec_stmt->bind_param("i", $service_id);
                $service_spec_stmt->execute();
                $service_spec_result = $service_spec_stmt->get_result();
                while ($row = $service_spec_result->fetch_assoc()) {
                    $service_specializations[] = (int)$row['specialization_id'];
                }
                $service_spec_stmt->close();
            }

            // Get dentist specializations (what specializations does this dentist have)
            $dentist_specializations = [];
            $dentist_spec_stmt = $conn->prepare("SELECT specialization_id FROM tbl_staff_specializations WHERE staff_id = ?");
            if ($dentist_spec_stmt) {
                $dentist_spec_stmt->bind_param("i", $dentist_id);
                $dentist_spec_stmt->execute();
                $dentist_spec_result = $dentist_spec_stmt->get_result();
                while ($row = $dentist_spec_result->fetch_assoc()) {
                    $dentist_specializations[] = (int)$row['specialization_id'];
                }
                $dentist_spec_stmt->close();
            }

            // Check if service specialization matches dentist specialization
            if (!empty($service_specializations)) {
                // Service HAS required specializations - dentist MUST have at least one matching specialization
                $has_matching_spec = false;
                $matching_spec_id = null;
                foreach ($service_specializations as $req_spec_id) {
                    if (in_array($req_spec_id, $dentist_specializations, true)) {
                        $has_matching_spec = true;
                        $matching_spec_id = $req_spec_id;
                        break;
                    }
                }
                
                if (!$has_matching_spec) {
                    // Dentist doesn't have ANY of the required specializations
                    $response['service_available'] = false;
                    $response['service_reason'] = 'Dentist does not perform this service. This service requires a specialization that the dentist does not have.';
                    $response['available'] = false;
                } else {
                    // Dentist has at least one matching specialization
                    $response['service_available'] = true;
                    $response['service_reason'] = 'Dentist can perform this service (has required specialization).';
                }
            } else {
                // Service has NO required specializations - any dentist can do it
                $response['service_available'] = true;
                $response['service_reason'] = 'Service is available (no specialization required).';
            }
        }
    } else {
        // No service selected - service check is not applicable
        $response['service_available'] = true;
        $response['service_reason'] = '';
    }

    // ============================================
    // STEP 2: Check Day Availability (if date provided)
    // ============================================
    if (!empty($date)) {
        // Validate date format
        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$date_obj || $date_obj->format('Y-m-d') !== $date) {
            $response['day_available'] = false;
            $response['day_reason'] = 'Invalid date format.';
            $response['available'] = false;
        } else {
            // Get day of week: PHP format 0=Sunday, 1=Monday, ..., 6=Saturday
            $php_day = (int)$date_obj->format('w');
            $day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $day_name = $day_names[$php_day];
            
            // Check if dentist has a schedule for this day_of_week
            // Database uses same format: 0=Sunday, 1=Monday, ..., 6=Saturday
            // Also handle legacy data where Sunday might be stored as 7
            $schedule_stmt = $conn->prepare("
                SELECT start_time, end_time, day_of_week 
                FROM tbl_dentist_schedules 
                WHERE dentist_id = ? 
                AND (day_of_week = ? OR (day_of_week = 7 AND ? = 0)) 
                AND is_active = 1 
                LIMIT 1
            ");
            
            if ($schedule_stmt) {
                $schedule_stmt->bind_param("iii", $dentist_id, $php_day, $php_day);
                $schedule_stmt->execute();
                $schedule_result = $schedule_stmt->get_result();
                
                if ($schedule_result->num_rows === 0) {
                    // Dentist does NOT have a schedule for this day
                    $response['day_available'] = false;
                    $response['day_reason'] = "Dentist is not available on {$day_name}. The dentist does not have a schedule for this day.";
                    $response['available'] = false;
                } else {
                    // Dentist has a schedule for this day
                    $schedule_info = $schedule_result->fetch_assoc();
                    $response['schedule'] = [
                        'start_time' => $schedule_info['start_time'],
                        'end_time' => $schedule_info['end_time'],
                        'day_of_week' => $schedule_info['day_of_week']
                    ];
                    $response['day_available'] = true;
                    $response['day_reason'] = "Dentist is available on {$day_name} ({$schedule_info['start_time']} - {$schedule_info['end_time']}).";
                }
                $schedule_stmt->close();
            } else {
                $response['day_available'] = false;
                $response['day_reason'] = 'Error checking dentist schedule.';
                $response['available'] = false;
            }
        }
    }

    // ============================================
    // STEP 3: Generate Occupied Times (if date provided)
    // Always generate occupied times when date is available so frontend can block them
    // ============================================
    if (!empty($date) && $response['day_available']) {
        // Get dentist's schedule for this day to determine working hours
        $schedule_start = '09:00';
        $schedule_end = '17:00';
        
        if ($response['schedule']) {
            $schedule_start = $response['schedule']['start_time'];
            $schedule_end = $response['schedule']['end_time'];
        } else {
            // Fetch schedule if not already fetched
            $date_obj = DateTime::createFromFormat('Y-m-d', $date);
            if ($date_obj) {
                $php_day = (int)$date_obj->format('w');
                $schedule_check = $conn->prepare("
                    SELECT start_time, end_time 
                    FROM tbl_dentist_schedules 
                    WHERE dentist_id = ? 
                    AND (day_of_week = ? OR (day_of_week = 7 AND ? = 0)) 
                    AND is_active = 1 
                    LIMIT 1
                ");
                if ($schedule_check) {
                    $schedule_check->bind_param("iii", $dentist_id, $php_day, $php_day);
                    $schedule_check->execute();
                    $schedule_res = $schedule_check->get_result();
                    if ($schedule_res->num_rows > 0) {
                        $schedule_row = $schedule_res->fetch_assoc();
                        $schedule_start = $schedule_row['start_time'];
                        $schedule_end = $schedule_row['end_time'];
                        $response['schedule'] = [
                            'start_time' => $schedule_row['start_time'],
                            'end_time' => $schedule_row['end_time']
                        ];
                    }
                    $schedule_check->close();
                }
            }
        }
        
        // Get all existing appointments for this dentist on this date
        $occupied_stmt = $conn->prepare("
            SELECT scheduled_datetime, duration_minutes
            FROM tbl_appointments
            WHERE dentist_id = ?
            AND DATE(scheduled_datetime) = ?
            AND status NOT IN ('Cancelled', 'No-Show')
            ORDER BY scheduled_datetime ASC
        ");
        
        if ($occupied_stmt) {
            $occupied_stmt->bind_param("is", $dentist_id, $date);
            $occupied_stmt->execute();
            $occupied_result = $occupied_stmt->get_result();
            
            // Generate all time slots for the day (30-minute intervals)
            $all_time_slots = [];
            $start_hour = (int)substr($schedule_start, 0, 2);
            $start_min = (int)substr($schedule_start, 3, 2);
            $end_hour = (int)substr($schedule_end, 0, 2);
            $end_min = (int)substr($schedule_end, 3, 2);
            
            $current_hour = $start_hour;
            $current_min = $start_min;
            
            while (($current_hour < $end_hour) || ($current_hour == $end_hour && $current_min < $end_min)) {
                $time_str = sprintf('%02d:%02d', $current_hour, $current_min);
                $all_time_slots[] = $time_str;
                
                $current_min += 30;
                if ($current_min >= 60) {
                    $current_min = 0;
                    $current_hour++;
                }
            }
            
            // Check each appointment and block overlapping time slots
            $occupied_times = [];
            while ($row = $occupied_result->fetch_assoc()) {
                $appt_start = new DateTime($row['scheduled_datetime']);
                $appt_end = clone $appt_start;
                $appt_end->modify("+{$row['duration_minutes']} minutes");
                
                // Check each time slot to see if it overlaps with this appointment
                foreach ($all_time_slots as $slot_time) {
                    $slot_start = new DateTime($date . ' ' . $slot_time . ':00');
                    $slot_end = clone $slot_start;
                    $slot_end->modify('+30 minutes'); // Standard slot duration
                    
                    // Slot overlaps if: slot_start < appt_end AND slot_end > appt_start
                    if ($slot_start < $appt_end && $slot_end > $appt_start) {
                        $occupied_times[] = $slot_time;
                    }
                }
            }
            
            $response['occupied_times'] = array_unique($occupied_times);
            $occupied_stmt->close();
        }
    }

    // ============================================
    // STEP 4: Check Specific Time Availability (if date and time provided)
    // ============================================
    if (!empty($date) && !empty($time)) {
        // First, make sure day is available
        if (!$response['day_available']) {
            $response['time_available'] = false;
            $response['time_reason'] = 'Cannot check time availability - dentist is not available on this day.';
            $response['available'] = false;
        } else {
            // Combine date and time
            $scheduled_datetime = $date . ' ' . $time . ':00';
            
            // Validate datetime format
            $datetime_obj = DateTime::createFromFormat('Y-m-d H:i:s', $scheduled_datetime);
            if (!$datetime_obj || $datetime_obj->format('Y-m-d H:i:s') !== $scheduled_datetime) {
                $response['time_available'] = false;
                $response['time_reason'] = 'Invalid date or time format.';
                $response['available'] = false;
            } else {
                // Get dentist's schedule for this day to determine working hours
                $schedule_start = '09:00';
                $schedule_end = '17:00';
                
                if ($response['schedule']) {
                    $schedule_start = $response['schedule']['start_time'];
                    $schedule_end = $response['schedule']['end_time'];
                }
                
                // Check if selected time is within working hours
                $appt_time = strtotime($time);
                $work_start = strtotime($schedule_start);
                $work_end = strtotime($schedule_end);
                
                $is_time_within_hours = false;
                if ($work_end < $work_start) {
                    // Schedule spans midnight
                    $is_time_within_hours = ($appt_time >= $work_start || $appt_time <= $work_end);
                } else {
                    // Normal schedule
                    $is_time_within_hours = ($appt_time >= $work_start && $appt_time < $work_end);
                }
                
                if (!$is_time_within_hours) {
                    $response['time_available'] = false;
                    $response['time_reason'] = "Selected time {$time} is outside dentist's working hours ({$schedule_start} - {$schedule_end}).";
                    $response['available'] = false;
                } else {
                    // Check if appointment end time is within working hours
                    $start_time = new DateTime($scheduled_datetime);
                    $end_time = clone $start_time;
                    $end_time->modify("+{$duration_minutes} minutes");
                    $end_time_str = $end_time->format('H:i:s');
                    $end_appt_time = strtotime($end_time_str);
                    
                    $is_end_time_within_hours = false;
                    if ($work_end < $work_start) {
                        $is_end_time_within_hours = ($end_appt_time >= $work_start || $end_appt_time <= $work_end);
                    } else {
                        $is_end_time_within_hours = ($end_appt_time >= $work_start && $end_appt_time <= $work_end);
                    }
                    
                    if (!$is_end_time_within_hours) {
                        $response['time_available'] = false;
                        $response['time_reason'] = "Appointment end time extends beyond dentist's working hours ({$schedule_start} - {$schedule_end}).";
                        $response['available'] = false;
                    } else {
                        // Check if the selected time conflicts with existing appointments
                        $occupied_stmt = $conn->prepare("
                            SELECT scheduled_datetime, duration_minutes
                            FROM tbl_appointments
                            WHERE dentist_id = ?
                            AND DATE(scheduled_datetime) = ?
                            AND status NOT IN ('Cancelled', 'No-Show')
                            ORDER BY scheduled_datetime ASC
                        ");
                        
                        if ($occupied_stmt) {
                            $occupied_stmt->bind_param("is", $dentist_id, $date);
                            $occupied_stmt->execute();
                            $occupied_result = $occupied_stmt->get_result();
                            
                            while ($row = $occupied_result->fetch_assoc()) {
                                $appt_start = new DateTime($row['scheduled_datetime']);
                                $appt_end = clone $appt_start;
                                $appt_end->modify("+{$row['duration_minutes']} minutes");
                                
                                // Check if the selected time conflicts with this appointment
                                if ($start_time < $appt_end && $end_time > $appt_start) {
                                    $response['time_available'] = false;
                                    $response['time_reason'] = 'Dentist already has an appointment at this time.';
                                    $response['available'] = false;
                                    break;
                                }
                            }
                            
                            $occupied_stmt->close();
                        }
                        
                        if ($response['time_available']) {
                            $response['time_reason'] = 'Time slot is available.';
                        }
                    }
                }
            }
        }
    }

    // Build final reason message and ensure overall availability is correct
    $reasons = [];
    if (!$response['service_available']) {
        $reasons[] = $response['service_reason'];
        $response['available'] = false; // Ensure available is false if service is not available
    }
    if (!$response['day_available']) {
        $reasons[] = $response['day_reason'];
        $response['available'] = false; // Ensure available is false if day is not available
    }
    if (!$response['time_available']) {
        $reasons[] = $response['time_reason'];
        $response['available'] = false; // Ensure available is false if time is not available
    }
    
    // Final check: overall available should be false if ANY check failed
    if (!$response['service_available'] || !$response['day_available'] || !$response['time_available']) {
        $response['available'] = false;
    }
    
    if (!empty($reasons)) {
        $response['reason'] = implode(' ', $reasons);
    } else {
        $response['reason'] = 'Dentist is available.';
        $response['available'] = true; // All checks passed
    }

    $conn->close();
    echo json_encode($response);

} catch (Exception $e) {
    // Catch any unhandled exceptions
    error_log("check_dentist_availability.php error: " . $e->getMessage());
    echo json_encode([
        'error' => 'Server error occurred. Please try again.',
        'available' => false,
        'reason' => 'Error checking availability: ' . $e->getMessage()
    ]);
}
?>
