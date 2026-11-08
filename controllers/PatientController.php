<?php
/**
 * PatientController.php — Profile update, password change, and file upload logic.
 *
 * Usage in profile.php:
 *   require_once ROOT_PATH . '/controllers/PatientController.php';
 *   $errors = PatientController::handleProfileUpdate($_POST, $_SESSION['user_id']);
 */

require_once ROOT_PATH . '/models/PatientModel.php';
require_once ROOT_PATH . '/models/StaffModel.php';

class PatientController
{
    // ─────────────────────────────────────────────────────────────────────────
    // PROFILE UPDATE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Handle patient profile update (POST).
     * Returns [] on success or ['field' => 'message', ...] errors.
     */
    public static function handlePatientProfileUpdate(array $post, int $patient_id): array
    {
        $fn         = trim($post['first_name']    ?? '');
        $ln         = trim($post['last_name']     ?? '');
        $email      = trim(strtolower($post['email'] ?? ''));
        $phone      = trim($post['primary_phone'] ?? '');
        $dob        = trim($post['dob']           ?? '');
        $gender     = trim($post['gender']        ?? 'Prefer not to say');
        $blood_type = trim($post['blood_type']    ?? 'Unknown');
        $allergies  = array_filter(array_map('trim', $post['allergies'] ?? []));

        $errors = [];

        if ($fn === '') $errors['first_name'] = 'First name is required.';
        if ($ln === '') $errors['last_name']  = 'Last name is required.';

        if ($dob === '') {
            $errors['dob'] = 'Date of birth is required.';
        } else {
            // Handle MM/DD/YYYY → YYYY-MM-DD
            $parts = explode('/', $dob);
            if (count($parts) === 3) {
                $dob = sprintf('%04d-%02d-%02d', $parts[2], $parts[0], $parts[1]);
            }
            $dt = DateTime::createFromFormat('Y-m-d', $dob);
            if (!$dt || $dt->format('Y-m-d') !== $dob) {
                $errors['dob'] = 'Invalid date format.';
            }
        }

        if ($phone === '') $errors['phone'] = 'Phone number is required.';

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email address is required.';
        } elseif (PatientModel::emailExists($email, $patient_id)) {
            $errors['email'] = 'This email address is already registered by another user.';
        }

        if (!empty($errors)) return $errors;

        PatientModel::updateProfile($patient_id, [
            'first_name'    => $fn,
            'last_name'     => $ln,
            'email'         => $email,
            'primary_phone' => $phone,
            'dob'           => $dob,
            'gender'        => $gender,
            'blood_type'    => $blood_type,
        ]);

        PatientModel::syncAllergies($patient_id, $allergies);

        // Update session name
        $_SESSION['user_name']  = "$fn $ln";
        $_SESSION['user_email'] = $email;

        set_flash('Profile updated successfully.', 'success');
        return [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STAFF PROFILE UPDATE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Handle staff profile update (POST).
     * Returns [] on success or errors.
     */
    public static function handleStaffProfileUpdate(array $post, int $staff_id, bool $is_dentist = false): array
    {
        $fn             = trim($post['first_name']    ?? '');
        $ln             = trim($post['last_name']     ?? '');
        $email          = trim(strtolower($post['email'] ?? ''));
        $phone_number   = trim($post['phone_number']  ?? '');
        $license_number = trim($post['license_number'] ?? '');

        $errors = [];

        if ($fn === '') $errors['first_name'] = 'First name is required.';
        if ($ln === '') $errors['last_name']  = 'Last name is required.';

        if ($phone_number === '') $errors['phone_number'] = 'Phone number is required.';

        if ($is_dentist && $license_number === '') {
            $errors['license_number'] = 'License number is required for dentists.';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email address is required.';
        } elseif (StaffModel::emailExists($email, $staff_id)) {
            $errors['email'] = 'This email is already registered by another staff member.';
        }

        if (!empty($errors)) return $errors;

        StaffModel::updateProfile($staff_id, [
            'first_name'     => $fn,
            'last_name'      => $ln,
            'email'          => $email,
            'phone_number'   => $phone_number,
            'license_number' => $license_number,
        ]);

        $_SESSION['user_name']  = "$fn $ln";
        $_SESSION['user_email'] = $email;

        set_flash('Profile updated successfully.', 'success');
        return [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PASSWORD CHANGE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Change a patient's password.
     * Verifies old password, hashes new one.
     * Returns [] on success or errors.
     */
    public static function handlePasswordChange(array $post, int $patient_id): array
    {
        $old_pass     = $post['current_password'] ?? '';
        $new_pass     = $post['new_password']     ?? '';
        $confirm_pass = $post['confirm_password'] ?? '';

        $errors = [];

        $patient = PatientModel::findById($patient_id);
        if (!$patient) return ['Patient not found.'];

        if (!password_verify($old_pass, $patient['password'] ?? '')) {
            $errors['current_password'] = 'Current password is incorrect.';
        }

        if (strlen($new_pass) < 8) {
            $errors['new_password'] = 'New password must be at least 8 characters.';
        } elseif ($new_pass !== $confirm_pass) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        if (!empty($errors)) return $errors;

        PatientModel::updatePassword($patient_id, password_hash($new_pass, PASSWORD_DEFAULT));
        set_flash('Password changed successfully.', 'success');
        return [];
    }
}

