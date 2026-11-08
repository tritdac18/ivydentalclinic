<?php
/**
 * Dentist Availability Functions
 * 
 * Functions to check and manage dentist availability for appointments
 */

// Only require db.php if not already included
if (!function_exists('getDBConnection')) {
    // Try multiple possible paths for db.php
    $db_paths = [
        __DIR__ . '/../config/db.php',
        dirname(__DIR__) . '/config/db.php'
    ];
    
    $db_loaded = false;
    foreach ($db_paths as $db_path) {
        if (file_exists($db_path)) {
            require_once $db_path;
            $db_loaded = true;
            break;
        }
    }
    
    if (!$db_loaded && !function_exists('getDBConnection')) {
        // If still not loaded, try relative path from current working directory
        if (file_exists('config/db.php')) {
            require_once 'config/db.php';
        }
    }
}

/**
 * Check if a dentist is available at a given time and duration
 * 
 * @param int $dentist_id The staff_id of the dentist
 * @param string $scheduled_datetime The appointment datetime (Y-m-d H:i:s)
 * @param int $duration_minutes The duration of the appointment in minutes
 * @param int $exclude_appointment_id Optional: exclude this appointment_id (for updates)
 * @return bool True if available, False if not
 */
function isDentistAvailable($dentist_id, $scheduled_datetime, $duration_minutes, $exclude_appointment_id = null) {
    $conn = getDBConnection();
    
    // Calculate end time
    $start_time = new DateTime($scheduled_datetime);
    $end_time = clone $start_time;
    $end_time->modify("+{$duration_minutes} minutes");
    
    // Check for overlapping appointments
    // An appointment overlaps if:
    // - Existing appointment starts before new appointment ends AND
    // - Existing appointment ends after new appointment starts
    // - Status is not Cancelled or No-Show
    
    $exclude_clause = $exclude_appointment_id ? "AND a.appointment_id != ?" : "";
    $end_datetime = $end_time->format('Y-m-d H:i:s');
    
    $query = "
        SELECT COUNT(*) as conflict_count
        FROM tbl_appointments a
        WHERE a.dentist_id = ?
        AND a.status NOT IN ('Cancelled', 'No-Show')
        AND a.scheduled_datetime < ?
        AND DATE_ADD(a.scheduled_datetime, INTERVAL a.duration_minutes MINUTE) > ?
        {$exclude_clause}
    ";
    
    $stmt = $conn->prepare($query);
    
    if ($exclude_appointment_id) {
        // Parameters: dentist_id, end_datetime (new), scheduled_datetime (new), exclude_appointment_id
        $stmt->bind_param("issi", $dentist_id, $end_datetime, $scheduled_datetime, $exclude_appointment_id);
    } else {
        // Parameters: dentist_id, end_datetime (new), scheduled_datetime (new)
        $stmt->bind_param("iss", $dentist_id, $end_datetime, $scheduled_datetime);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    return ($row['conflict_count'] == 0);
}

/**
 * Find an available dentist for a given time and duration
 * 
 * @param int|null $branch_id Deprecated: kept for backward compatibility, ignored
 * @param string $scheduled_datetime The appointment datetime (Y-m-d H:i:s)
 * @param int $duration_minutes The duration of the appointment in minutes
 * @return int|null Returns staff_id of available dentist, or null if none found
 */
function findAvailableDentist($branch_id, $scheduled_datetime, $duration_minutes) {
    $conn = getDBConnection();
    
    // Get all dentists
    $query = "
        SELECT s.staff_id, s.first_name, s.last_name
        FROM tbl_staff s
        WHERE s.role IN ('Dentist', 'Admin')
        ORDER BY s.staff_id ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $available_dentist = null;
    
    while ($row = $result->fetch_assoc()) {
        $dentist_id = $row['staff_id'];
        
        // Check if this dentist is available
        if (isDentistAvailable($dentist_id, $scheduled_datetime, $duration_minutes)) {
            $available_dentist = $dentist_id;
            break;
        }
    }
    
    $stmt->close();
    $conn->close();
    
    return $available_dentist;
}

/**
 * Get dentist's schedule for a specific date
 * 
 * @param int $dentist_id The staff_id of the dentist
 * @param string $date The date (Y-m-d)
 * @return array Array of appointments for that day
 */
function getDentistSchedule($dentist_id, $date) {
    $conn = getDBConnection();
    
    $query = "
        SELECT 
            a.appointment_id,
            a.scheduled_datetime,
            a.duration_minutes,
            a.status,
            s.service_name,
            p.first_name as patient_first_name,
            p.last_name as patient_last_name
        FROM tbl_appointments a
        INNER JOIN tbl_service_catalog s ON a.service_id = s.service_id
        INNER JOIN tbl_patients p ON a.patient_id = p.patient_id
        WHERE a.dentist_id = ?
        AND DATE(a.scheduled_datetime) = ?
        AND a.status NOT IN ('Cancelled', 'No-Show')
        ORDER BY a.scheduled_datetime ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $dentist_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $schedule = [];
    while ($row = $result->fetch_assoc()) {
        $schedule[] = $row;
    }
    
    $stmt->close();
    $conn->close();
    
    return $schedule;
}

/**
 * Get available time slots for a dentist on a specific date
 * 
 * @param int $dentist_id The staff_id of the dentist
 * @param string $date The date (Y-m-d)
 * @param int $duration_minutes Required duration for the appointment
 * @param string $start_time Start of working hours (default: 09:00)
 * @param string $end_time End of working hours (default: 17:00)
 * @return array Array of available time slots
 */
function getAvailableTimeSlots($dentist_id, $date, $duration_minutes, $start_time = '09:00', $end_time = '17:00') {
    $conn = getDBConnection();
    
    // Get all appointments for this dentist on this date
    $query = "
        SELECT 
            scheduled_datetime,
            duration_minutes
        FROM tbl_appointments
        WHERE dentist_id = ?
        AND DATE(scheduled_datetime) = ?
        AND status NOT IN ('Cancelled', 'No-Show')
        ORDER BY scheduled_datetime ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("is", $dentist_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appt_start = new DateTime($row['scheduled_datetime']);
        $appt_end = clone $appt_start;
        $appt_end->modify("+{$row['duration_minutes']} minutes");
        
        $appointments[] = [
            'start' => $appt_start,
            'end' => $appt_end
        ];
    }
    $stmt->close();
    $conn->close();
    
    // Generate available time slots
    $available_slots = [];
    $work_start = new DateTime("{$date} {$start_time}");
    $work_end = new DateTime("{$date} {$end_time}");
    
    $current_time = clone $work_start;
    
    while ($current_time < $work_end) {
        $slot_end = clone $current_time;
        $slot_end->modify("+{$duration_minutes} minutes");
        
        // Check if this slot conflicts with any appointment
        $conflicts = false;
        foreach ($appointments as $appt) {
            if (($current_time >= $appt['start'] && $current_time < $appt['end']) ||
                ($slot_end > $appt['start'] && $slot_end <= $appt['end']) ||
                ($current_time <= $appt['start'] && $slot_end >= $appt['end'])) {
                $conflicts = true;
                break;
            }
        }
        
        if (!$conflicts && $slot_end <= $work_end) {
            $available_slots[] = $current_time->format('H:i');
        }
        
        // Move to next 30-minute slot
        $current_time->modify('+30 minutes');
    }
    
    return $available_slots;
}

/**
 * Check if a dentist is working on a specific date and time based on their schedule
 * 
 * @param int $dentist_id The staff_id of the dentist
 * @param string $scheduled_datetime The appointment datetime (Y-m-d H:i:s)
 * @return bool True if dentist is working at that time, False otherwise
 */
function isDentistWorking($dentist_id, $scheduled_datetime) {
    try {
        $conn = getDBConnection();
        
        // Get day of week - Database uses same format as PHP: 0=Sunday, 1=Monday, ..., 6=Saturday
        $datetime = new DateTime($scheduled_datetime);
        $php_day = (int)$datetime->format('w'); // PHP: 0=Sunday, 1=Monday, ..., 6=Saturday
        // Database uses same format: 0=Sunday, 1=Monday, ..., 6=Saturday
        $day_of_week = $php_day;
        $time = $datetime->format('H:i:s'); // HH:MM:SS format
        
        error_log("DEBUG isDentistWorking: PHP day={$php_day}, DB day_of_week={$day_of_week}, datetime={$scheduled_datetime}");
        
        // Check if dentist has a schedule for this day
        $query = "
            SELECT start_time, end_time
            FROM tbl_dentist_schedules
            WHERE dentist_id = ?
            AND (day_of_week = ? OR (day_of_week = 7 AND ? = 0))
            AND is_active = 1
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("isDentistWorking prepare failed: " . $conn->error);
            $conn->close();
            // If schedule table doesn't exist or error, dentist is NOT available (strict mode)
            return false;
        }
        
        // Pass day_of_week twice (once for main check, once for the OR check)
        $stmt->bind_param("iii", $dentist_id, $day_of_week, $day_of_week);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // STRICT: If no schedule found for this day, dentist is NOT available
        if ($result->num_rows === 0) {
            $stmt->close();
            $conn->close();
            // No schedule set for this day - dentist is NOT available
            error_log("isDentistWorking: No schedule found for dentist_id={$dentist_id}, day_of_week={$day_of_week}");
            return false;
        }
        
        $schedule = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        
        // Check if the time falls within working hours
        $start_time = $schedule['start_time'];
        $end_time = $schedule['end_time'];
        
        // Convert times to comparable format
        $appt_time = strtotime($time);
        $work_start = strtotime($start_time);
        $work_end = strtotime($end_time);
        
        // Handle case where end_time might be next day (e.g., 23:00 to 01:00)
        if ($work_end < $work_start) {
            // Schedule spans midnight
            $is_working = ($appt_time >= $work_start || $appt_time <= $work_end);
        } else {
            // Normal schedule - check if appointment time is within working hours
            $is_working = ($appt_time >= $work_start && $appt_time < $work_end);
        }
        
        if (!$is_working) {
            error_log("isDentistWorking: Time {$time} is outside working hours {$start_time} - {$end_time} for dentist_id={$dentist_id}, day_of_week={$day_of_week}");
        }
        
        return $is_working;
    } catch (Exception $e) {
        error_log("isDentistWorking error: " . $e->getMessage());
        // On error, dentist is NOT available (strict mode)
        return false;
    }
}

/**
 * Check if a dentist is fully available (both schedule and no conflicts)
 * 
 * @param int $dentist_id The staff_id of the dentist
 * @param string $scheduled_datetime The appointment datetime (Y-m-d H:i:s)
 * @param int $duration_minutes The duration of the appointment in minutes
 * @param int $exclude_appointment_id Optional: exclude this appointment_id (for updates)
 * @return array ['available' => bool, 'reason' => string] Availability status and reason
 */
/**
 * Check dentist availability with detailed breakdown (day, time, specialization)
 * 
 * @param int $dentist_id The staff_id of the dentist
 * @param string $scheduled_datetime The appointment datetime (Y-m-d H:i:s)
 * @param int $duration_minutes The duration of the appointment in minutes
 * @param int $service_id Optional: service_id to check specialization
 * @param array $dentist_services Optional: array of dentist specializations (dentist_id => service_ids or 'all')
 * @param int $exclude_appointment_id Optional: exclude this appointment_id (for updates)
 * @return array ['available' => bool, 'day_available' => bool, 'time_available' => bool, 'service_available' => bool, 'day_reason' => string, 'time_reason' => string, 'service_reason' => string, 'reason' => string]
 */
function checkDentistFullAvailabilityDetailed($dentist_id, $scheduled_datetime, $duration_minutes, $service_id = null, $dentist_services = null, $exclude_appointment_id = null) {
    // Get day name for better error messages
    $day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $datetime = new DateTime($scheduled_datetime);
    $php_day = (int)$datetime->format('w'); // PHP: 0=Sunday, 1=Monday, ..., 6=Saturday
    $day_name = $day_names[$php_day];
    // Database uses same format: 0=Sunday, 1=Monday, ..., 6=Saturday
    $day_of_week = $php_day;
    $time = $datetime->format('H:i');
    
    $result = [
        'available' => true,
        'day_available' => true,
        'time_available' => true,
        'service_available' => true,
        'day_reason' => '',
        'time_reason' => '',
        'service_reason' => '',
        'reason' => 'Dentist is available.'
    ];
    
    // CHECK 1: Day Availability - Does dentist have a schedule for this day?
    $conn = getDBConnection();
    // Check for both 0 and 7 for Sunday to handle legacy/inconsistent data
    $schedule_check = $conn->prepare("SELECT start_time, end_time FROM tbl_dentist_schedules WHERE dentist_id = ? AND (day_of_week = ? OR (day_of_week = 7 AND ? = 0)) AND is_active = 1 LIMIT 1");
    if ($schedule_check) {
        $schedule_check->bind_param("iii", $dentist_id, $day_of_week, $day_of_week);
        $schedule_check->execute();
        $schedule_result = $schedule_check->get_result();
        
        if ($schedule_result->num_rows === 0) {
            // NO SCHEDULE EXISTS FOR THIS DAY
            $result['day_available'] = false;
            $result['day_reason'] = "Dentist is not available on {$day_name} (Schedule missing).";
            $result['available'] = false;
            $schedule_check->close();
            $conn->close();
            return $result;
        } else {
            $schedule = $schedule_result->fetch_assoc();
            $schedule_start = $schedule['start_time'];
            $schedule_end = $schedule['end_time'];
            
            // CHECK 2: Time Availability - Is the time within working hours?
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
                $result['time_available'] = false;
                $result['time_reason'] = "Selected time {$time} is outside dentist's working hours on {$day_name} ({$schedule_start} - {$schedule_end}).";
                $result['available'] = false;
            }
            
            // Also check if appointment end time is within working hours
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
                $result['time_available'] = false;
                $result['time_reason'] = "Appointment end time extends beyond dentist's working hours on {$day_name} ({$schedule_start} - {$schedule_end}).";
                $result['available'] = false;
            }
            
            $schedule_check->close();
        }
    } else {
        // Query failed - assume not available
        $result['day_available'] = false;
        $result['day_reason'] = "Unable to check dentist schedule for {$day_name}.";
        $result['available'] = false;
    }
    $conn->close();
    
    // CHECK 3: Service Specialization - Can dentist perform this service?
    if ($service_id && $service_id > 0 && $dentist_services !== null) {
        $can_perform = false;
        
        if (isset($dentist_services[$dentist_id])) {
            if ($dentist_services[$dentist_id] === 'all') {
                // Dentist can perform all services
                $can_perform = true;
            } elseif (is_array($dentist_services[$dentist_id]) && in_array($service_id, $dentist_services[$dentist_id])) {
                // Dentist has this service in specialization
                $can_perform = true;
            }
        } else {
            // If dentist not in array, check if it's because they have no specializations
            // In that case, allow them to perform services (backward compatibility)
            // This handles the case where dentist_services array might not include all dentists
            $can_perform = true;
        }
        
        if (!$can_perform) {
            // Check if service actually HAS any required specializations
            $has_requirements = false;
            $conn = getDBConnection(); // Re-open connection if needed
            $req_check = $conn->prepare("SELECT COUNT(*) as count FROM tbl_service_specializations WHERE service_id = ?");
            if ($req_check) {
                $req_check->bind_param("i", $service_id);
                $req_check->execute();
                $req_res = $req_check->get_result();
                $req_row = $req_res->fetch_assoc();
                if ($req_row['count'] > 0) {
                    $has_requirements = true;
                }
                $req_check->close();
            }
            $conn->close();

            if ($has_requirements) {
                $result['service_available'] = false;
                $result['service_reason'] = "Dentist cannot perform service ID {$service_id} (Missing required specialization).";
                $result['available'] = false;
            } else {
                // No requirements, so dentist CAN perform it
                $result['service_available'] = true;
                $can_perform = true;
            }
        }
    }
    
    // CHECK 4: Conflicting Appointments
    if ($result['day_available'] && $result['time_available']) {
        if (!isDentistAvailable($dentist_id, $scheduled_datetime, $duration_minutes, $exclude_appointment_id)) {
            $result['time_available'] = false;
            $result['time_reason'] = "Dentist already has an appointment at this time.";
            $result['available'] = false;
        }
    }
    
    // Build final reason message
    $reasons = [];
    if (!$result['day_available']) {
        $reasons[] = $result['day_reason'];
    }
    if (!$result['time_available']) {
        $reasons[] = $result['time_reason'];
    }
    if (!$result['service_available']) {
        $reasons[] = $result['service_reason'];
    }
    
    if (!empty($reasons)) {
        $result['reason'] = implode(' ', $reasons);
    }
    
    return $result;
}

function checkDentistFullAvailability($dentist_id, $scheduled_datetime, $duration_minutes, $exclude_appointment_id = null) {
    // Get day name for better error messages
    $day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $datetime = new DateTime($scheduled_datetime);
    $php_day = (int)$datetime->format('w'); // PHP: 0=Sunday, 1=Monday, ..., 6=Saturday
    $day_name = $day_names[$php_day];
    // Use PHP format (0=Sunday) directly to match DB and checkDentistFullAvailabilityDetailed
    $day_of_week = $php_day;
    $time = $datetime->format('H:i');
    
    // STRICT: Check if dentist is working at this time (checks both day and time)
    $is_working_result = isDentistWorking($dentist_id, $scheduled_datetime);
    
    // DEBUG: Log the availability check
    error_log("DEBUG checkDentistFullAvailability: dentist_id={$dentist_id}, datetime={$scheduled_datetime}, day={$day_name} (PHP={$php_day}, DB={$day_of_week}), is_working=" . ($is_working_result ? 'true' : 'false'));
    
    if (!$is_working_result) {
        // Get more specific reason - check if schedule exists for this day
        $conn = getDBConnection();
        // Check for both 0 and 7 for Sunday
        $schedule_check = $conn->prepare("SELECT start_time, end_time FROM tbl_dentist_schedules WHERE dentist_id = ? AND (day_of_week = ? OR (day_of_week = 7 AND ? = 0)) AND is_active = 1 LIMIT 1");
        if ($schedule_check) {
            $schedule_check->bind_param("iii", $dentist_id, $day_of_week, $day_of_week);
            $schedule_check->execute();
            $schedule_result = $schedule_check->get_result();
            
            if ($schedule_result->num_rows === 0) {
                // NO SCHEDULE EXISTS FOR THIS DAY - STRICT: NOT AVAILABLE
                error_log("DEBUG checkDentistFullAvailability: No schedule found for dentist_id={$dentist_id}, day_of_week={$day_of_week} ({$day_name})");
                $conn->close();
                return [
                    'available' => false,
                    'reason' => "Dentist does not have a schedule for {$day_name}."
                ];
            } else {
                // Schedule exists but time is outside working hours
                $schedule = $schedule_result->fetch_assoc();
                error_log("DEBUG checkDentistFullAvailability: Schedule exists but time {$time} is outside working hours {$schedule['start_time']} - {$schedule['end_time']}");
                $conn->close();
                return [
                    'available' => false,
                    'reason' => "Dentist is not working at {$time} on {$day_name}. Working hours: {$schedule['start_time']} - {$schedule['end_time']}."
                ];
            }
        }
        $conn->close();
        
        // Fallback error
        error_log("DEBUG checkDentistFullAvailability: Could not check schedule (query failed)");
        return [
            'available' => false,
            'reason' => "Dentist does not have a schedule for {$day_name} or is not working at this time."
        ];
    }
    
    // Check if dentist has conflicting appointments
    if (!isDentistAvailable($dentist_id, $scheduled_datetime, $duration_minutes, $exclude_appointment_id)) {
        return [
            'available' => false,
            'reason' => 'Dentist already has an appointment at this time.'
        ];
    }
    
    // Check if appointment end time is within working hours
    $start_time = new DateTime($scheduled_datetime);
    $end_time = clone $start_time;
    $end_time->modify("+{$duration_minutes} minutes");
    
    // Get the day of week and time for end time
    $end_php_day = (int)$end_time->format('w'); // PHP: 0=Sunday, 1=Monday, ..., 6=Saturday
    // Use PHP format (0=Sunday) to match start day
    $end_day_of_week = $end_php_day;
    $end_time_str = $end_time->format('H:i:s');
    
    // Get day names for better error messages
    $day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $start_php_day = (int)$start_time->format('w');
    $start_day_name = $day_names[$start_php_day];
    $end_day_name = $day_names[$end_php_day];
    
    // Check if appointment spans multiple days
    $start_day_of_week = $php_day;
    if ($start_day_of_week !== $end_day_of_week) {
        // Appointment spans multiple days - check both days have schedules
        // We already checked start day, now check end day
        if (!isDentistWorking($dentist_id, $end_time->format('Y-m-d H:i:s'))) {
            return [
                'available' => false,
                'reason' => "Dentist does not have a schedule for {$end_day_name} (appointment end day)."
            ];
        }
    }
    
    // Check if dentist is working at end time (allow exact end time match)
    try {
        $conn = getDBConnection();
        $end_schedule_query = "
            SELECT start_time, end_time
            FROM tbl_dentist_schedules
            WHERE dentist_id = ?
            AND (day_of_week = ? OR (day_of_week = 7 AND ? = 0))
            AND is_active = 1
            LIMIT 1
        ";
        $end_stmt = $conn->prepare($end_schedule_query);
        if ($end_stmt) {
            $end_stmt->bind_param("iii", $dentist_id, $end_day_of_week, $end_day_of_week);
            $end_stmt->execute();
            $end_result = $end_stmt->get_result();
            
            if ($end_result->num_rows > 0) {
                $end_schedule = $end_result->fetch_assoc();
                $work_start = strtotime($end_schedule['start_time']);
                $work_end = strtotime($end_schedule['end_time']);
                $appt_end_time = strtotime($end_time_str);
                
                // Allow appointments to end exactly when work ends (<= instead of <)
                if ($work_end < $work_start) {
                    // Schedule spans midnight
                    $within_hours = ($appt_end_time >= $work_start || $appt_end_time <= $work_end);
                } else {
                    // Normal schedule - allow exact end time match
                    $within_hours = ($appt_end_time >= $work_start && $appt_end_time <= $work_end);
                }
                
                if (!$within_hours) {
                    $end_stmt->close();
                    $conn->close();
                    return [
                        'available' => false,
                        'reason' => "Appointment end time extends beyond dentist's working hours on {$end_day_name}."
                    ];
                }
            } else {
                // STRICT: If no schedule found for end day, dentist is NOT available
                $end_stmt->close();
                $conn->close();
                return [
                    'available' => false,
                    'reason' => "Dentist does not have a schedule for {$end_day_name} (appointment end day)."
                ];
            }
            $end_stmt->close();
        } else {
            error_log("Error preparing end schedule query: " . $conn->error);
        }
        $conn->close();
    } catch (Exception $e) {
        // On error, dentist is NOT available (strict mode)
        error_log("Error checking end time availability: " . $e->getMessage());
        return [
            'available' => false,
            'reason' => "Error checking dentist schedule for {$end_day_name}."
        ];
    }
    
    return [
        'available' => true,
        'reason' => 'Dentist is available.'
    ];
}

