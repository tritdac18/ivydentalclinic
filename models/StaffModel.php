<?php
/**
 * StaffModel.php — All DB operations relating to staff (Dentist, Receptionist, Admin, Manager).
 *
 * Usage:
 *   require_once ROOT_PATH . '/models/StaffModel.php';
 *   $all = StaffModel::all();
 */

class StaffModel
{
    private static function conn(): mysqli
    {
        return getDBConnection();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────────────────

    /** All staff members (admin list). */
    public static function all(): array
    {
        $conn = self::conn();
        $result = $conn->query(
            "SELECT staff_id, first_name, last_name, email, username, role,
                    phone_number, license_number, is_active
             FROM tbl_staff ORDER BY last_name, first_name"
        );
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $conn->close();
        return $rows;
    }

    /** All active dentists (for booking dropdowns). */
    public static function activeDentists(): array
    {
        $conn = self::conn();
        $result = $conn->query(
            "SELECT staff_id, first_name, last_name, role
             FROM tbl_staff
             WHERE role IN ('Dentist', 'Admin') AND (is_active = 1 OR is_active IS NULL)
             ORDER BY last_name, first_name"
        );
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $conn->close();
        return $rows;
    }

    /** Single staff row by ID. */
    public static function findById(int $id): ?array
    {
        $conn = self::conn();
        $stmt = $conn->prepare(
            "SELECT staff_id, first_name, last_name, email, username, role,
                    phone_number, license_number, is_active
             FROM tbl_staff WHERE staff_id = ? LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $row ?: null;
    }

    /** Find by activation token (for set-password flow). */
    public static function findByActivationToken(string $token): ?array
    {
        $conn = self::conn();
        $stmt = $conn->prepare(
            "SELECT staff_id, first_name, last_name, email, role, activation_token, activation_expires
             FROM tbl_staff WHERE activation_token = ? LIMIT 1"
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $row ?: null;
    }

    /** Specializations for a dentist. */
    public static function getSpecializations(int $staff_id): array
    {
        $conn = self::conn();
        // Guard: table may not exist in older schema versions
        $check = $conn->query("SHOW TABLES LIKE 'tbl_staff_specializations'");
        if (!$check || $check->num_rows === 0) {
            $conn->close();
            return [];
        }

        $stmt = $conn->prepare(
            "SELECT ss.specialization_id, s.name as specialization_name
             FROM tbl_staff_specializations ss
             JOIN tbl_specializations s ON ss.specialization_id = s.specialization_id
             WHERE ss.staff_id = ?
             ORDER BY s.name"
        );
        $stmt->bind_param('i', $staff_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $conn->close();
        return $rows;
    }

    /** Weekly schedule rows for a dentist. */
    public static function getSchedule(int $dentist_id): array
    {
        $conn = self::conn();
        $check = $conn->query("SHOW TABLES LIKE 'tbl_dentist_schedules'");
        if (!$check || $check->num_rows === 0) {
            $conn->close();
            return [];
        }

        $stmt = $conn->prepare(
            "SELECT day_of_week, start_time, end_time
             FROM tbl_dentist_schedules
             WHERE dentist_id = ?
             ORDER BY day_of_week"
        );
        $stmt->bind_param('i', $dentist_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $conn->close();
        return $rows;
    }

    /** Educational background for a staff member. */
    public static function getEducation(int $staff_id): array
    {
        $conn = self::conn();
        $check = $conn->query("SHOW TABLES LIKE 'tbl_staff_education'");
        if (!$check || $check->num_rows === 0) {
            $conn->close();
            return [];
        }
        $stmt = $conn->prepare(
            "SELECT education_id, institution_name, degree, graduation_year
             FROM tbl_staff_education
             WHERE staff_id = ?
             ORDER BY graduation_year DESC"
        );
        $stmt->bind_param('i', $staff_id);
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
     * Insert a new staff member (no password yet — activation email sent later).
     * Returns new staff_id or false.
     */
    public static function create(array $data): int|false
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            INSERT INTO tbl_staff
                (first_name, last_name, username, email, role,
                 phone_number, license_number,
                 activation_token, activation_expires)
            VALUES (?, ?, ?, ?, ?, '', '', ?, ?)
        ");
        $stmt->bind_param(
            'sssssss',
            $data['first_name'],
            $data['last_name'],
            $data['username'],
            $data['email'],
            $data['role'],
            $data['activation_token'],
            $data['activation_expires']
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

    /** Set password and activate account (complete-registration flow). */
    public static function activateAccount(int $id, string $password_hash): bool
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            UPDATE tbl_staff
               SET password = ?, activation_token = NULL, activation_expires = NULL, is_active = 1
             WHERE staff_id = ?
        ");
        $stmt->bind_param('si', $password_hash, $id);
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $ok;
    }

    /** Update basic profile fields. */
    public static function updateProfile(int $id, array $data): bool
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            UPDATE tbl_staff
               SET first_name = ?, last_name = ?, email = ?,
                   phone_number = ?, license_number = ?
             WHERE staff_id = ?
        ");
        $stmt->bind_param(
            'sssssi',
            $data['first_name'],
            $data['last_name'],
            $data['email'],
            $data['phone_number'],
            $data['license_number'],
            $id
        );
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $ok;
    }

    /** Enable or disable a staff account. */
    public static function setActive(int $id, bool $active): bool
    {
        $conn  = self::conn();
        $value = $active ? 1 : 0;
        $stmt  = $conn->prepare("UPDATE tbl_staff SET is_active = ? WHERE staff_id = ?");
        $stmt->bind_param('ii', $value, $id);
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $ok;
    }

    /** Replace weekly schedule for a dentist. */
    public static function saveSchedule(int $dentist_id, array $schedules): void
    {
        $conn = self::conn();

        // Check if is_active column exists
        $col_check   = $conn->query("SHOW COLUMNS FROM tbl_dentist_schedules LIKE 'is_active'");
        $has_active  = $col_check && $col_check->num_rows > 0;

        // Soft-delete or hard-delete existing
        if ($has_active) {
            $del = $conn->prepare("UPDATE tbl_dentist_schedules SET is_active = 0 WHERE dentist_id = ?");
        } else {
            $del = $conn->prepare("DELETE FROM tbl_dentist_schedules WHERE dentist_id = ?");
        }
        $del->bind_param('i', $dentist_id);
        $del->execute();
        $del->close();

        // Insert enabled slots
        $sql = $has_active
            ? "INSERT INTO tbl_dentist_schedules (dentist_id, day_of_week, start_time, end_time, is_active) VALUES (?, ?, ?, ?, 1)"
            : "INSERT INTO tbl_dentist_schedules (dentist_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)";
        $ins = $conn->prepare($sql);

        foreach ($schedules as $slot) {
            if (empty($slot['enabled']) || $slot['enabled'] != '1') continue;
            $day   = (int)($slot['day'] ?? -1);
            $start = trim($slot['start_time'] ?? '');
            $end   = trim($slot['end_time']   ?? '');
            if ($day < 0 || $day > 6 || !$start || !$end) continue;
            if (strlen($start) === 5) $start .= ':00';
            if (strlen($end)   === 5) $end   .= ':00';
            $ins->bind_param('iiss', $dentist_id, $day, $start, $end);
            $ins->execute();
        }
        $ins->close();
        $conn->close();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    public static function usernameExists(string $username, int $exclude_id = 0): bool
    {
        $conn = self::conn();
        $stmt = $conn->prepare("SELECT staff_id FROM tbl_staff WHERE username = ? AND staff_id != ? LIMIT 1");
        $stmt->bind_param('si', $username, $exclude_id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        $conn->close();
        return $exists;
    }

    public static function emailExists(string $email, int $exclude_id = 0): bool
    {
        $conn = self::conn();
        $stmt = $conn->prepare("SELECT staff_id FROM tbl_staff WHERE email = ? AND staff_id != ? LIMIT 1");
        $stmt->bind_param('si', $email, $exclude_id);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        $conn->close();
        return $exists;
    }
}

