<?php
/**
 * Maintenance Service
 * 
 * Handles creation and updates of patient maintenance records
 * when appointments are completed.
 */

require_once __DIR__ . '/../config/db.php';

class MaintenanceService {
    
    /**
     * Create or update maintenance record when appointment is completed
     * 
     * @param int $appointment_id The completed appointment ID
     * @return bool Success status
     */
    public static function handleAppointmentCompletion($appointment_id) {
        try {
            $conn = getDBConnection();
            
            // Check if tables exist
            $maintenance_check = $conn->query("SHOW TABLES LIKE 'tbl_patient_maintenance'");
            if (!$maintenance_check || $maintenance_check->num_rows === 0) {
                error_log("MaintenanceService: tbl_patient_maintenance table does not exist");
                return false;
            }
            
            // Fetch appointment details
            $appt_stmt = $conn->prepare("
                SELECT 
                    a.appointment_id,
                    a.patient_id,
                    a.service_id,
                    a.scheduled_datetime,
                    sv.service_name,
                    sv.maintenance_period_months,
                    sv.maintenance_interval
                FROM tbl_appointments a
                INNER JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
                WHERE a.appointment_id = ?
                AND a.status = 'Completed'
            ");
            $appt_stmt->bind_param("i", $appointment_id);
            $appt_stmt->execute();
            $appt_result = $appt_stmt->get_result();
            
            if ($appt_result->num_rows === 0) {
                $appt_stmt->close();
                $conn->close();
                return false; // Appointment not found or not completed
            }
            
            $appointment = $appt_result->fetch_assoc();
            $appt_stmt->close();
            
            // Only create maintenance if service has maintenance requirements
            if (empty($appointment['maintenance_period_months']) && empty($appointment['maintenance_interval'])) {
                $conn->close();
                return true; // No maintenance required for this service
            }
            
            $patient_id = $appointment['patient_id'];
            $service_id = $appointment['service_id'];
            $appointment_date = new DateTime($appointment['scheduled_datetime']);
            $appointment_date_str = $appointment_date->format('Y-m-d');
            
            // Calculate next visit date based on maintenance interval
            $next_visit_date = null;
            if ($appointment['maintenance_interval']) {
                $next_visit = clone $appointment_date;
                
                // Handle both database enum values (lowercase) and form values (capitalized)
                $interval = strtolower($appointment['maintenance_interval']);
                $interval = str_replace(['-', '_'], '', $interval); // Normalize separators
                
                switch ($interval) {
                    case 'weekly':
                        $next_visit->modify('+1 week');
                        break;
                    case 'biweekly':
                        $next_visit->modify('+2 weeks');
                        break;
                    case 'monthly':
                        $next_visit->modify('+1 month');
                        break;
                    case 'bimonthly':
                        $next_visit->modify('+2 months');
                        break;
                    case 'quarterly':
                        $next_visit->modify('+3 months');
                        break;
                    case 'semiannually':
                        $next_visit->modify('+6 months');
                        break;
                    case 'annually':
                        $next_visit->modify('+1 year');
                        break;
                    default:
                        // Default to monthly if unknown
                        $next_visit->modify('+1 month');
                }
                
                $next_visit_date = $next_visit->format('Y-m-d');
            }
            
            // Check if maintenance record already exists for this patient and service
            $check_stmt = $conn->prepare("
                SELECT maintenance_id, status 
                FROM tbl_patient_maintenance 
                WHERE patient_id = ? 
                AND service_id = ? 
                AND status != 'cancelled'
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $check_stmt->bind_param("ii", $patient_id, $service_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_stmt->close();
            
            if ($check_result->num_rows > 0) {
                // Update existing maintenance record
                $existing = $check_result->fetch_assoc();
                $maintenance_id = $existing['maintenance_id'];
                
                $update_stmt = $conn->prepare("
                    UPDATE tbl_patient_maintenance 
                    SET last_visit_date = ?,
                        recommended_next_visit = ?,
                        status = 'pending',
                        updated_at = NOW()
                    WHERE maintenance_id = ?
                ");
                $update_stmt->bind_param("ssi", $appointment_date_str, $next_visit_date, $maintenance_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                error_log("MaintenanceService: Updated maintenance record #{$maintenance_id} for patient #{$patient_id}, service #{$service_id}");
            } else {
                // Create new maintenance record
                $insert_stmt = $conn->prepare("
                    INSERT INTO tbl_patient_maintenance 
                    (patient_id, service_id, related_appointment_id, last_visit_date, recommended_next_visit, status) 
                    VALUES (?, ?, ?, ?, ?, 'pending')
                ");
                $insert_stmt->bind_param("iiiss", 
                    $patient_id, 
                    $service_id, 
                    $appointment_id,
                    $appointment_date_str, 
                    $next_visit_date
                );
                $insert_stmt->execute();
                $maintenance_id = $conn->insert_id;
                $insert_stmt->close();
                
                error_log("MaintenanceService: Created maintenance record #{$maintenance_id} for patient #{$patient_id}, service #{$service_id}");
            }
            
            $conn->close();
            return true;
            
        } catch (Exception $e) {
            error_log("MaintenanceService Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update maintenance status when patient schedules next appointment
     * 
     * @param int $maintenance_id Maintenance record ID
     * @param int $appointment_id New appointment ID
     * @return bool Success status
     */
    public static function updateMaintenanceWithAppointment($maintenance_id, $appointment_id) {
        try {
            $conn = getDBConnection();
            
            // Get appointment date
            $appt_stmt = $conn->prepare("SELECT scheduled_datetime FROM tbl_appointments WHERE appointment_id = ?");
            $appt_stmt->bind_param("i", $appointment_id);
            $appt_stmt->execute();
            $appt_result = $appt_stmt->get_result();
            
            if ($appt_result->num_rows === 0) {
                $appt_stmt->close();
                $conn->close();
                return false;
            }
            
            $appointment = $appt_result->fetch_assoc();
            $appt_stmt->close();
            
            $appointment_date = new DateTime($appointment['scheduled_datetime']);
            $appointment_date_str = $appointment_date->format('Y-m-d');
            
            // Update maintenance record
            $update_stmt = $conn->prepare("
                UPDATE tbl_patient_maintenance 
                SET recommended_next_visit = ?,
                    status = 'pending',
                    updated_at = NOW()
                WHERE maintenance_id = ?
            ");
            $update_stmt->bind_param("si", $appointment_date_str, $maintenance_id);
            $update_stmt->execute();
            $update_stmt->close();
            
            $conn->close();
            return true;
            
        } catch (Exception $e) {
            error_log("MaintenanceService Error: " . $e->getMessage());
            return false;
        }
    }
}

