<?php
/**
 * PatientModel.php — All DB operations relating to patient records.
 *
 * Usage:
 *   require_once ROOT_PATH . '/models/PatientModel.php';
 *   $patient = PatientModel::findById($id);
 */

class PatientModel
{
    private static function conn(): mysqli
    {
        return getDBConnection();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────────────────

    /** Full profile row for a patient. */
    public static function findById(int $id): ?array
    {
        $conn = self::conn();
        $stmt = $conn->prepare(
            "SELECT * FROM tbl_patients WHERE patient_id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $row ?: null;
    }

    /** Search patients by name, email, or phone (for walk-in booking). */
    public static function search(string $query, int $limit = 20): array
    {
        $conn  = self::conn();
        $term  = '%' . $query . '%';
        $stmt  = $conn->prepare("
            SELECT patient_id, first_name, last_name, email, primary_phone
            FROM tbl_patients
            WHERE first_name LIKE ?
               OR last_name LIKE ?
               OR email LIKE ?
               OR CONCAT(first_name, ' ', last_name) LIKE ?
               OR CONCAT(last_name, ', ', first_name) LIKE ?
            ORDER BY last_name, first_name
            LIMIT ?
        ");
        $stmt->bind_param('sssssi', $term, $term, $term, $term, $term, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $conn->close();
        return $rows;
    }

    /** All patients (admin list view). */
    public static function all(): array
    {
        $conn = self::conn();
        $result = $conn->query(
            "SELECT patient_id, first_name, last_name, email, primary_phone, status, created_at
             FROM tbl_patients ORDER BY last_name, first_name"
        );
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $conn->close();
        return $rows;
    }

    /** Allergies for a patient. */
    public static function getAllergies(int $patient_id): array
    {
        $conn = self::conn();
        $stmt = $conn->prepare(
            "SELECT allergy_name FROM tbl_patient_allergies WHERE patient_id = ? ORDER BY allergy_name"
        );
        $stmt->bind_param('i', $patient_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $conn->close();
        return array_column($rows, 'allergy_name');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Insert a new patient (self-registration flow).
     * Returns new patient_id on success, false on failure.
     */
    public static function create(array $data): int|false
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            INSERT INTO tbl_patients
                (username, first_name, last_name, email, primary_phone, dob,
                 gender, blood_type, password, status, is_minor,
                 email_verification_token, email_verification_expires,
                 guardian_name, guardian_relationship, guardian_phone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            'ssssssssssissss',
            $data['username'],
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['primary_phone'],
            $data['dob'],
            $data['gender'],
            $data['blood_type'],
            $data['password'],
            $data['status'],
            $data['is_minor'],
            $data['email_verification_token'],
            $data['email_verification_expires'],
            $data['guardian_name'],
            $data['guardian_relationship'],
            $data['guardian_phone']
        );
        $ok = $stmt->execute();
        $id = $ok ? (int)$conn->insert_id : false;
        $stmt->close();
        $conn->close();
        return $id;
    }

    /**
     * Quick-create a walk-in patient (staff creates the record on the spot).
     * Returns new patient_id or false.
     */
    public static function createWalkIn(array $data): int|false
    {
        $conn   = self::conn();
        $stmt   = $conn->prepare("
            INSERT INTO tbl_patients
                (username, first_name, last_name, email, primary_phone, dob,
                 gender, blood_type, password, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->bind_param(
            'sssssssss',
            $data['username'],
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['primary_phone'],
            $data['dob'],
            $data['gender'],
            $data['blood_type'],
            $data['password']
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

    /** Update basic profile fields for a patient. */
    public static function updateProfile(int $id, array $data): bool
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            UPDATE tbl_patients
               SET first_name = ?, last_name = ?, email = ?,
                   primary_phone = ?, dob = ?, gender = ?, blood_type = ?
             WHERE patient_id = ?
        ");
        $stmt->bind_param(
            'sssssssi',
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['primary_phone'],
            $data['dob'],
            $data['gender'],
            $data['blood_type'],
            $id
        );
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $ok;
    }

    /** Replace all allergy records for a patient. */
    public static function syncAllergies(int $patient_id, array $allergies): void
    {
        $conn = self::conn();

        // Delete existing
        $del = $conn->prepare("DELETE FROM tbl_patient_allergies WHERE patient_id = ?");
        $del->bind_param('i', $patient_id);
        $del->execute();
        $del->close();

        // Insert new
        if (!empty($allergies)) {
            $ins = $conn->prepare("INSERT INTO tbl_patient_allergies (patient_id, allergy_name) VALUES (?, ?)");
            foreach ($allergies as $name) {
                $name = trim($name);
                if ($name !== '') {
                    $ins->bind_param('is', $patient_id, $name);
                    $ins->execute();
                }
            }
            $ins->close();
        }

        $conn->close();
    }

    /** Update password hash. */
    public static function updatePassword(int $id, string $hash): bool
    {
        $conn = self::conn();
        $stmt = $conn->prepare("UPDATE tbl_patients SET password = ? WHERE patient_id = ?");
        $stmt->bind_param('si', $hash, $id);
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $ok;
    }

    /** Mark email as verified and activate account. */
    public static function verifyEmail(int $id): bool
    {
        $conn = self::conn();
        $stmt = $conn->prepare(
            "UPDATE tbl_patients
                SET status = 'active',
                    email_verification_token = NULL,
                    email_verification_expires = NULL
              WHERE patient_id = ?"
        );
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $ok;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    /** Check whether a username is already taken (excluding a given patient_id). */
    public static function usernameExists(string $username, int $exclude_id = 0): bool
    {
        $conn = self::conn();
        $stmt = $conn->prepare(
            "SELECT patient_id FROM tbl_patients WHERE username = ? AND patient_id != ? LIMIT 1"
        );
        $stmt->bind_param('si', $username, $exclude_id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        $conn->close();
        return $exists;
    }

    /** Check whether an email is already registered (excluding a given patient_id). */
    public static function emailExists(string $email, int $exclude_id = 0): bool
    {
        $conn = self::conn();
        $stmt = $conn->prepare(
            "SELECT patient_id FROM tbl_patients WHERE email = ? AND patient_id != ? LIMIT 1"
        );
        $stmt->bind_param('si', $email, $exclude_id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        $conn->close();
        return $exists;
    }

    /**
     * Generate a unique username from a base string.
     * Appends an incrementing number if the base is already taken.
     */
    public static function generateUniqueUsername(string $base): string
    {
        $conn     = self::conn();
        $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $base));
        $counter  = 1;

        while (true) {
            $stmt = $conn->prepare("SELECT patient_id FROM tbl_patients WHERE username = ? LIMIT 1");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $found = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if (!$found) {
                break;
            }
            $username = $base . $counter++;
        }

        $conn->close();
        return $username;
    }
}

