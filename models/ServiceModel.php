<?php
/**
 * ServiceModel.php — All DB operations relating to the service catalogue.
 *
 * Usage:
 *   require_once ROOT_PATH . '/models/ServiceModel.php';
 *   $services = ServiceModel::allActive();
 */

class ServiceModel
{
    private static function conn(): mysqli
    {
        return getDBConnection();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // READ
    // ─────────────────────────────────────────────────────────────────────────

    /** All active services. */
    public static function allActive(): array
    {
        $conn   = self::conn();
        $result = $conn->query("
            SELECT service_id, service_name, base_price,
                   default_duration_minutes, description,
                   maintenance_period_months, maintenance_interval
            FROM tbl_service_catalog
            WHERE is_active = 1
            ORDER BY service_name
        ");
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $conn->close();
        return $rows;
    }

    /** All services including inactive (admin view). */
    public static function all(): array
    {
        $conn   = self::conn();
        $result = $conn->query("
            SELECT service_id, service_name, base_price,
                   default_duration_minutes, description, is_active,
                   maintenance_period_months, maintenance_interval
            FROM tbl_service_catalog
            ORDER BY service_name
        ");
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $conn->close();
        return $rows;
    }

    /** Single service by ID. */
    public static function findById(int $id): ?array
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            SELECT service_id, service_name, base_price,
                   default_duration_minutes, description, is_active,
                   maintenance_period_months, maintenance_interval
            FROM tbl_service_catalog
            WHERE service_id = ? LIMIT 1
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        return $row ?: null;
    }

    /**
     * Services that a given dentist can perform (via specialization matching).
     * Returns array of service_ids the dentist is qualified for.
     */
    public static function dentistCanPerform(int $dentist_id): array
    {
        $conn = self::conn();

        // Get dentist's specializations
        $dentist_specs = [];
        $check = $conn->query("SHOW TABLES LIKE 'tbl_staff_specializations'");
        if ($check && $check->num_rows > 0) {
            $stmt = $conn->prepare("SELECT specialization_id FROM tbl_staff_specializations WHERE staff_id = ?");
            $stmt->bind_param('i', $dentist_id);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            $dentist_specs = array_column($rows, 'specialization_id');
        }

        // Get all active service → specialization mappings
        $service_specs = [];
        $check2 = $conn->query("SHOW TABLES LIKE 'tbl_service_specializations'");
        if ($check2 && $check2->num_rows > 0) {
            $res = $conn->query("SELECT service_id, specialization_id FROM tbl_service_specializations");
            while ($row = $res->fetch_assoc()) {
                $service_specs[$row['service_id']][] = $row['specialization_id'];
            }
        }

        // Get all active service IDs
        $all_services = [];
        $res = $conn->query("SELECT service_id FROM tbl_service_catalog WHERE is_active = 1");
        while ($row = $res->fetch_assoc()) {
            $all_services[] = (int)$row['service_id'];
        }

        $conn->close();

        // Determine which services the dentist can perform
        $can_perform = [];
        foreach ($all_services as $sid) {
            $req_specs = $service_specs[$sid] ?? [];
            if (empty($req_specs)) {
                // No required specializations → any dentist can do it
                $can_perform[] = $sid;
            } else {
                foreach ($req_specs as $rs) {
                    if (in_array($rs, $dentist_specs)) {
                        $can_perform[] = $sid;
                        break;
                    }
                }
            }
        }

        return $can_perform;
    }

    /**
     * Whether a service has maintenance attributes.
     */
    public static function hasMaintenance(int $service_id): bool
    {
        $row = self::findById($service_id);
        return $row && !empty($row['maintenance_period_months']) && !empty($row['maintenance_interval']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE / UPDATE
    // ─────────────────────────────────────────────────────────────────────────

    /** Insert a new service. Returns new service_id or false. */
    public static function create(array $data): int|false
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            INSERT INTO tbl_service_catalog
                (service_name, base_price, default_duration_minutes, description, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->bind_param(
            'sdis',
            $data['service_name'],
            $data['base_price'],
            $data['default_duration_minutes'],
            $data['description']
        );
        $ok = $stmt->execute();
        $id = $ok ? (int)$conn->insert_id : false;
        $stmt->close();
        $conn->close();
        return $id;
    }

    /** Update a service. */
    public static function update(int $id, array $data): bool
    {
        $conn = self::conn();
        $stmt = $conn->prepare("
            UPDATE tbl_service_catalog
               SET service_name = ?, base_price = ?,
                   default_duration_minutes = ?, description = ?, is_active = ?
             WHERE service_id = ?
        ");
        $stmt->bind_param(
            'sdisii',
            $data['service_name'],
            $data['base_price'],
            $data['default_duration_minutes'],
            $data['description'],
            $data['is_active'],
            $id
        );
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();
        return $ok;
    }
}

