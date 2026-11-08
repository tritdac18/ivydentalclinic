<?php
/**
 * StaffController.php — Account management for Admin/Manager users.
 *
 * Usage in accounts.php:
 *   require_once ROOT_PATH . '/controllers/StaffController.php';
 *   $errors = StaffController::handleCreateStaff($_POST);
 */

require_once ROOT_PATH . '/models/StaffModel.php';

class StaffController
{
    // ─────────────────────────────────────────────────────────────────────────
    // CREATE STAFF
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a new staff account (sends activation email).
     * Returns [] on success or error strings.
     */
    public static function handleCreateStaff(array $post, callable $send_activation_email): array
    {
        $fn       = trim($post['first_name'] ?? '');
        $ln       = trim($post['last_name']  ?? '');
        $username = trim($post['username']   ?? '');
        $role     = trim($post['role']       ?? '');
        $email    = trim(strtolower($post['email'] ?? ''));

        $errors = [];

        if (!$fn || !$ln || !$username || !$role || !$email) {
            $errors[] = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address.';
        } elseif (!in_array($role, ['Dentist', 'Receptionist'])) {
            $errors[] = 'Role must be either Dentist or Receptionist.';
        } elseif (StaffModel::usernameExists($username)) {
            $errors[] = 'Username already exists.';
        } elseif (StaffModel::emailExists($email)) {
            $errors[] = 'Email already exists.';
        }

        if (!empty($errors)) return $errors;

        $token      = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 day'));

        $staff_id = StaffModel::create([
            'first_name'          => $fn,
            'last_name'           => $ln,
            'username'            => $username,
            'email'               => $email,
            'role'                => $role,
            'activation_token'    => $token,
            'activation_expires'  => $expires_at,
        ]);

        if (!$staff_id) return ['Failed to create staff account. Please try again.'];

        // Send activation email (injected callable for testability)
        $send_activation_email($email, $fn, $ln, $token, $role);

        set_flash("Staff account created. An activation email has been sent to {$email}.", 'success');
        redirect_to('accounts.php');
        return [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ACTIVATE / DEACTIVATE
    // ─────────────────────────────────────────────────────────────────────────

    public static function handleActivate(int $staff_id): void
    {
        StaffModel::setActive($staff_id, true);
        set_flash('Staff account activated.', 'success');
        redirect_to('accounts.php');
    }

    public static function handleDeactivate(int $staff_id): void
    {
        StaffModel::setActive($staff_id, false);
        set_flash('Staff account deactivated.', 'info');
        redirect_to('accounts.php');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DENTIST SCHEDULE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Save a dentist's weekly schedule.
     * Returns [] on success or error strings.
     */
    public static function handleSaveSchedule(array $post): array
    {
        $dentist_id = (int)($post['dentist_id'] ?? 0);
        $schedules  = $post['schedules'] ?? [];

        if ($dentist_id <= 0 || !is_array($schedules)) {
            return ['Invalid dentist ID or schedule data.'];
        }

        StaffModel::saveSchedule($dentist_id, $schedules);
        set_flash('Dentist schedule saved successfully.', 'success');
        redirect_to('appointments.php');
        return [];
    }
}

