<?php
/**
 * AppointmentModel.php — All DB operations relating to appointments.
 *
 * Usage:
 *   require_once ROOT_PATH . '/models/AppointmentModel.php';
 *   $appts = AppointmentModel::forPatient($patient_id);
 */

class AppointmentModel
{
    private static function conn(): mysqli
    {
        return getDBConnection();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // READ — appointments list
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * All appointments with joined patient, dentist, and service data.
     * Used by Admin / Receptionist view.
     */
    public static function allWithDetails(): array
    {
        $conn = self::conn();
        $result = $conn->query("
            SELECT a.*,
                   CONCAT(s.first_name, ' ', s.last_name) AS dentist_name,
                   s.first_name AS dentist_first_name,
                   s.last_name  AS dentist_last_name,
                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                   p.first_name AS patient_first_name,
                   p.last_name  AS patient_last_name,
                   p.patient_id,
                   sv.service_name
            FROM tbl_appointments a
            JOIN tbl_staff   s  ON a.dentist_id  = s.staff_id
            JOIN tbl_patients p ON a.patient_id  = p.patient_id
            LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
            ORDER BY a.scheduled_datetime ASC
        ");
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $conn->close();
        return $rows;
    }

    /**
     * Appointments for a specific dentist.
     */
    public static function forDentist(int $dentist_id): array
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            SELECT a.*,
                   CONCAT(s.first_name, ' ', s.last_name) AS dentist_name,
                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                   p.first_name AS patient_first_name,
                   p.last_name  AS patient_last_name,
                   p.patient_id,
                   sv.service_name
            FROM tbl_appointments a
            JOIN tbl_staff   s  ON a.dentist_id  = s.staff_id
            JOIN tbl_patients p ON a.patient_id  = p.patient_id
            LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
            WHERE a.dentist_id = ?
            ORDER BY a.scheduled_datetime ASC
        ");
        $stmt->bind_param('i', $dentist_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $conn->close();
        return $rows;
    }

    /**
     * Appointments for a specific patient.
     */
    public static function forPatient(int $patient_id): array
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            SELECT a.*,
                   CONCAT(s.first_name, ' ', s.last_name) AS dentist_name,
                   sv.service_name
            FROM tbl_appointments a
            JOIN tbl_staff s ON a.dentist_id = s.staff_id
            LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
            WHERE a.patient_id = ?
            ORDER BY a.scheduled_datetime DESC
        ");
        $stmt->bind_param('i', $patient_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $conn->close();
        return $rows;
    }

    /** Single appointment row (with joins). */
    public static function findById(int $appointment_id): ?array
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            SELECT a.*,
                   CONCAT(s.first_name, ' ', s.last_name) AS dentist_name,
                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                   sv.service_name
            FROM tbl_appointments a
            JOIN tbl_staff   s  ON a.dentist_id  = s.staff_id
            JOIN tbl_patients p ON a.patient_id  = p.patient_id
            LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
            WHERE a.appointment_id = ? LIMIT 1
        ");
        $stmt->bind_param('i', $appointment_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $row ?: null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // READ — clinic closures
    // ─────────────────────────────────────────────────────────────────────────

    public static function getClosures(): array
    {
        $conn   = self::conn();
        $result = $conn->query(
            "SELECT closure_id, closure_date, reason FROM tbl_clinic_closures ORDER BY closure_date"
        );
        $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        $conn->close();
        return $rows;
    }

    public static function countAppointmentsOnDate(string $date): int
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS cnt FROM tbl_appointments
            WHERE DATE(scheduled_datetime) = ?
              AND status NOT IN ('cancelled', 'completed')
        ");
        $stmt->bind_param('s', $date);
        $stmt->execute();
        $cnt = (int)$stmt->get_result()->fetch_assoc()['cnt'];
        $stmt->close();
        $conn->close();
        return $cnt;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // READ — prerequisites
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns prerequisite rows for a service (is_mandatory = 1 only).
     */
    public static function getPrerequisites(int $service_id): array
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            SELECT sp.required_service_id,
                   sc.service_name AS required_service_name,
                   COALESCE(sp.validity_period_days, 30) AS validity_period_days
            FROM tbl_service_prerequisites sp
            INNER JOIN tbl_service_catalog sc ON sp.required_service_id = sc.service_id
            WHERE sp.service_id = ? AND sp.is_mandatory = 1
        ");
        $stmt->bind_param('i', $service_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $conn->close();
        return $rows;
    }

    /**
     * Check whether a patient has completed a prerequisite service within the validity window.
     * Pass $validity_days = 0 for "ever completed".
     */
    public static function hasCompletedPrerequisite(int $patient_id, int $service_id, int $validity_days): bool
    {
        $conn = self::conn();

        if ($validity_days > 0) {
            $since = date('Y-m-d H:i:s', strtotime("-{$validity_days} days"));
            $stmt  = $conn->prepare("
                SELECT appointment_id FROM tbl_appointments
                WHERE patient_id = ? AND service_id = ? AND status = 'Completed'
                  AND scheduled_datetime >= ?
                LIMIT 1
            ");
            $stmt->bind_param('iis', $patient_id, $service_id, $since);
        } else {
            $stmt = $conn->prepare("
                SELECT appointment_id FROM tbl_appointments
                WHERE patient_id = ? AND service_id = ? AND status = 'Completed'
                LIMIT 1
            ");
            $stmt->bind_param('ii', $patient_id, $service_id);
        }

        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        $conn->close();
        return $found;
    }

    /**
     * Whether the patient already has an active maintenance plan for a service.
     * If true, prerequisite checks are skipped for that service.
     */
    public static function hasActiveMaintenance(int $patient_id, int $service_id): bool
    {
        $conn  = self::conn();
        $check = $conn->query("SHOW TABLES LIKE 'tbl_patient_maintenance'");
        if (!$check || $check->num_rows === 0) {
            $conn->close();
            return false;
        }
        $stmt = $conn->prepare("
            SELECT maintenance_id FROM tbl_patient_maintenance
            WHERE patient_id = ? AND service_id = ? AND status != 'cancelled'
            LIMIT 1
        ");
        $stmt->bind_param('ii', $patient_id, $service_id);
        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        $conn->close();
        return $found;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Insert a new appointment row.
     * Returns new appointment_id or false.
     */
    public static function create(array $data): int|false
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            INSERT INTO tbl_appointments
                (patient_id, dentist_id, service_id,
                 scheduled_datetime, duration_minutes, status, price)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            'iiisisd',
            $data['patient_id'],
            $data['dentist_id'],
            $data['service_id'],
            $data['scheduled_datetime'],
            $data['duration_minutes'],
            $data['status'],
            $data['price']
        );
        $ok = $stmt->execute();
        $id = $ok ? (int)$conn->insert_id : false;
        $stmt->close();
        $conn->close();
        return $id;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE
    // ─────────────────────────────────────────────────────────────────────────

    public static function updateStatus(int $appointment_id, string $status): bool
    {
        $conn = self::conn();
        $stmt = $conn->prepare("UPDATE tbl_appointments SET status = ? WHERE appointment_id = ?");
        $stmt->bind_param('si', $status, $appointment_id);
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $ok;
    }

    public static function reschedule(int $appointment_id, string $new_datetime, int $duration, string $status): bool
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            UPDATE tbl_appointments
               SET scheduled_datetime = ?, duration_minutes = ?, status = ?
             WHERE appointment_id = ?
        ");
        $stmt->bind_param('sisi', $new_datetime, $duration, $status, $appointment_id);
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $ok;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CLOSURE DATES
    // ─────────────────────────────────────────────────────────────────────────

    public static function addClosure(string $date, string $reason, int $created_by): bool
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            INSERT INTO tbl_clinic_closures (closure_date, reason, created_by)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE reason = ?, created_by = ?
        ");
        $stmt->bind_param('ssiss', $date, $reason, $created_by, $reason, $created_by);
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $ok;
    }

    public static function removeClosure(int $closure_id): bool
    {
        $conn = self::conn();
        $stmt = $conn->prepare("DELETE FROM tbl_clinic_closures WHERE closure_id = ?");
        $stmt->bind_param('i', $closure_id);
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $ok;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Does this appointment have a completed downpayment?
     * Checks both the unified tbl_payments and legacy tbl_appointment_downpayments.
     */
    public static function hasCompletedDownpayment(int $appointment_id): bool
    {
        $conn = self::conn();

        // Check unified table first
        $col_check = $conn->query("SHOW COLUMNS FROM tbl_payments LIKE 'payment_type'");
        if ($col_check && $col_check->num_rows > 0) {
            $stmt = $conn->prepare("
                SELECT payment_id FROM tbl_payments
                WHERE appointment_id = ? AND payment_status = 'Completed'
                LIMIT 1
            ");
        } else {
            $stmt = $conn->prepare("
                SELECT payment_id FROM tbl_appointment_downpayments
                WHERE appointment_id = ? AND payment_status = 'Completed'
                LIMIT 1
            ");
        }

        $stmt->bind_param('i', $appointment_id);
        $stmt->execute();
        $found = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        $conn->close();
        return $found;
    }
}

