<?php
/**
 * PaymentModel.php — All DB operations relating to payments, invoices, and discounts.
 *
 * Usage:
 *   require_once ROOT_PATH . '/models/PaymentModel.php';
 *   $payments = PaymentModel::forPatient($patient_id);
 */

class PaymentModel
{
    private static function conn(): mysqli
    {
        return getDBConnection();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────────────────

    /** All payments for a patient (unified table). */
    public static function forPatient(int $patient_id): array
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            SELECT p.*,
                   CONCAT(pt.first_name, ' ', pt.last_name) AS patient_name,
                   sv.service_name,
                   a.scheduled_datetime
            FROM tbl_payments p
            LEFT JOIN tbl_patients pt ON p.patient_id = pt.patient_id
            LEFT JOIN tbl_appointments a ON p.appointment_id = a.appointment_id
            LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
            WHERE p.patient_id = ?
            ORDER BY p.payment_date DESC
        ");
        $stmt->bind_param('i', $patient_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $conn->close();
        return $rows;
    }

    /** All payments — staff view. */
    public static function all(): array
    {
        $conn   = self::conn();
        $result = $conn->query("
            SELECT p.*,
                   CONCAT(pt.first_name, ' ', pt.last_name) AS patient_name,
                   sv.service_name,
                   a.scheduled_datetime
            FROM tbl_payments p
            LEFT JOIN tbl_patients pt ON p.patient_id = pt.patient_id
            LEFT JOIN tbl_appointments a ON p.appointment_id = a.appointment_id
            LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
            ORDER BY p.payment_date DESC
        ");
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $conn->close();
        return $rows;
    }

    /** Single payment row. */
    public static function findById(int $id): ?array
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            SELECT p.*,
                   CONCAT(pt.first_name, ' ', pt.last_name) AS patient_name,
                   sv.service_name
            FROM tbl_payments p
            LEFT JOIN tbl_patients pt ON p.patient_id = pt.patient_id
            LEFT JOIN tbl_appointments a ON p.appointment_id = a.appointment_id
            LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
            WHERE p.payment_id = ? LIMIT 1
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $row ?: null;
    }

    /** Payments for a specific appointment. */
    public static function forAppointment(int $appointment_id): array
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            SELECT * FROM tbl_payments
            WHERE appointment_id = ?
            ORDER BY payment_date ASC
        ");
        $stmt->bind_param('i', $appointment_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $conn->close();
        return $rows;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Insert a cash downpayment (walk-in flow).
     * Handles both unified tbl_payments and legacy tbl_appointment_downpayments.
     * Returns new payment_id or false.
     */
    public static function createCashDownpayment(
        int    $patient_id,
        int    $appointment_id,
        float  $amount,
        int    $staff_id
    ): int|false {
        $conn          = self::conn();
        $transaction_id = 'CASH-' . date('YmdHis') . '-' . $appointment_id;

        $col_check = $conn->query("SHOW COLUMNS FROM tbl_payments LIKE 'payment_type'");
        if ($col_check && $col_check->num_rows > 0) {
            $stmt = $conn->prepare("
                INSERT INTO tbl_payments
                    (patient_id, appointment_id, payment_type, amount,
                     payment_method, payment_status, payment_date,
                     transaction_id, staff_validator_id)
                VALUES (?, ?, 'Downpayment', ?, 'Cash', 'Completed', NOW(), ?, ?)
            ");
            $stmt->bind_param('iidsi', $patient_id, $appointment_id, $amount, $transaction_id, $staff_id);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO tbl_appointment_downpayments
                    (appointment_id, amount, payment_method, payment_status, transaction_id, staff_validator_id)
                VALUES (?, ?, 'Cash', 'Completed', ?, ?)
            ");
            $stmt->bind_param('idsi', $appointment_id, $amount, $transaction_id, $staff_id);
        }

        $ok = $stmt->execute();
        $id = $ok ? (int)$conn->insert_id : false;
        $stmt->close();
        $conn->close();
        return $id;
    }

    /**
     * Record a PayMongo online payment.
     * Returns new payment_id or false.
     */
    public static function createOnlinePayment(array $data): int|false
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            INSERT INTO tbl_payments
                (patient_id, appointment_id, payment_type, amount,
                 payment_method, payment_status, payment_date,
                 transaction_id, paymongo_payment_intent_id)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)
        ");
        $stmt->bind_param(
            'iissdss',
            $data['patient_id'],
            $data['appointment_id'],
            $data['payment_type'],
            $data['amount'],
            $data['payment_method'],
            $data['payment_status'],
            $data['transaction_id'],
            $data['payment_intent_id']
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

    public static function updateStatus(int $payment_id, string $status): bool
    {
        $conn = self::conn();
        $stmt = $conn->prepare("UPDATE tbl_payments SET payment_status = ? WHERE payment_id = ?");
        $stmt->bind_param('si', $status, $payment_id);
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $ok;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DISCOUNTS
    // ─────────────────────────────────────────────────────────────────────────

    /** Active discount for a patient of a given type. */
    public static function getActiveDiscount(int $patient_id, string $discount_type): ?array
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            SELECT discount_id, id_number FROM tbl_discounts
            WHERE patient_id = ? AND discount_type = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->bind_param('is', $patient_id, $discount_type);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $row ?: null;
    }

    /**
     * Upsert a discount record (create or update id_number).
     * Returns true on success.
     */
    public static function upsertDiscount(
        int    $patient_id,
        string $discount_type,
        string $id_number,
        int    $staff_id
    ): bool {
        $conn     = self::conn();
        $existing = self::getActiveDiscount($patient_id, $discount_type);

        if ($existing) {
            $stmt = $conn->prepare("
                UPDATE tbl_discounts
                   SET id_number = ?, verified_by_staff_id = ?, verified_at = NOW()
                 WHERE discount_id = ?
            ");
            $did = $existing['discount_id'];
            $stmt->bind_param('sii', $id_number, $staff_id, $did);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO tbl_discounts
                    (patient_id, discount_type, id_number, is_active, verified_by_staff_id, verified_at)
                VALUES (?, ?, ?, 1, ?, NOW())
            ");
            $stmt->bind_param('issi', $patient_id, $discount_type, $id_number, $staff_id);
        }

        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $ok;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INVOICES
    // ─────────────────────────────────────────────────────────────────────────

    /** All invoices for a patient. */
    public static function invoicesForPatient(int $patient_id): array
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            SELECT i.*,
                   sv.service_name,
                   a.scheduled_datetime,
                   CONCAT(s.first_name, ' ', s.last_name) AS dentist_name
            FROM tbl_invoices i
            LEFT JOIN tbl_appointments a ON i.appointment_id = a.appointment_id
            LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
            LEFT JOIN tbl_staff s ON a.dentist_id = s.staff_id
            WHERE i.patient_id = ?
            ORDER BY i.created_at DESC
        ");
        $stmt->bind_param('i', $patient_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $conn->close();
        return $rows;
    }

    /** All invoices — staff view. */
    public static function allInvoices(): array
    {
        $conn   = self::conn();
        $result = $conn->query("
            SELECT i.*,
                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                   sv.service_name,
                   a.scheduled_datetime
            FROM tbl_invoices i
            LEFT JOIN tbl_patients p ON i.patient_id = p.patient_id
            LEFT JOIN tbl_appointments a ON i.appointment_id = a.appointment_id
            LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
            ORDER BY i.created_at DESC
        ");
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $conn->close();
        return $rows;
    }

    /** Single invoice by ID. */
    public static function findInvoiceById(int $id): ?array
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            SELECT i.*,
                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                   sv.service_name,
                   a.scheduled_datetime,
                   CONCAT(s.first_name, ' ', s.last_name) AS dentist_name
            FROM tbl_invoices i
            LEFT JOIN tbl_patients p ON i.patient_id = p.patient_id
            LEFT JOIN tbl_appointments a ON i.appointment_id = a.appointment_id
            LEFT JOIN tbl_service_catalog sv ON a.service_id = sv.service_id
            LEFT JOIN tbl_staff s ON a.dentist_id = s.staff_id
            WHERE i.invoice_id = ? LIMIT 1
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $row ?: null;
    }
}

