<?php
/**
 * UserModel.php — Shared auth queries for both patients and staff.
 *
 * Usage:
 *   require_once ROOT_PATH . '/models/UserModel.php';
 *   $user = UserModel::findPatientByEmail('jane@example.com');
 */

class UserModel
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function conn(): mysqli
    {
        return getDBConnection();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PATIENT LOOKUPS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Find a patient by email or username (supports both).
     * Returns the row array or null.
     */
    public static function findPatientByLogin(string $identifier): ?array
    {
        $conn = self::conn();
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $stmt = $conn->prepare(
            "SELECT patient_id, first_name, last_name, email, username, password, status
             FROM tbl_patients
             WHERE $field = ? LIMIT 1"
        );
        $stmt->bind_param('s', $identifier);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $row ?: null;
    }

    /** Find patient by primary key. */
    public static function findPatientById(int $id): ?array
    {
        $conn = self::conn();
        $stmt = $conn->prepare(
            "SELECT patient_id, first_name, last_name, email, username, status
             FROM tbl_patients WHERE patient_id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $row ?: null;
    }

    /** Find patient by email. */
    public static function findPatientByEmail(string $email): ?array
    {
        $conn = self::conn();
        $stmt = $conn->prepare(
            "SELECT patient_id, first_name, last_name, email, username, password, status
             FROM tbl_patients WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $row ?: null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STAFF LOOKUPS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Find a staff member by email or username (supports both).
     */
    public static function findStaffByLogin(string $identifier): ?array
    {
        $conn = self::conn();
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $stmt = $conn->prepare(
            "SELECT staff_id, first_name, last_name, email, username, password, role
             FROM tbl_staff WHERE $field = ? LIMIT 1"
        );
        $stmt->bind_param('s', $identifier);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $row ?: null;
    }

    /** Find staff by primary key. */
    public static function findStaffById(int $id): ?array
    {
        $conn = self::conn();
        $stmt = $conn->prepare(
            "SELECT staff_id, first_name, last_name, email, username, role, phone_number, license_number
             FROM tbl_staff WHERE staff_id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $row ?: null;
    }

    /** Find staff by email. */
    public static function findStaffByEmail(string $email): ?array
    {
        $conn = self::conn();
        $stmt = $conn->prepare(
            "SELECT staff_id, first_name, last_name, email, username, role
             FROM tbl_staff WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $row ?: null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UNIFIED: resolve the current logged-in user (patient or staff)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns current user data based on $_SESSION.
     * Returns null and destroys session if user no longer exists.
     */
    public static function currentUser(): ?array
    {
        $type = $_SESSION['user_type'] ?? null;
        $id   = $_SESSION['user_id']   ?? null;

        if (!$type || !$id) {
            return null;
        }

        return $type === 'patient'
            ? self::findPatientById((int)$id)
            : self::findStaffById((int)$id);
    }
}

