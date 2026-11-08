<?php
/**
 * AuthController.php — Handles login, logout, and registration POST logic.
 *
 * Pages call these static methods from their own POST handling blocks.
 * Each method either redirects (and exits) or returns an array of errors.
 *
 * Usage in login.php:
 *   require_once ROOT_PATH . '/controllers/AuthController.php';
 *   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 *       $errors = AuthController::handleLogin($_POST);
 *   }
 */

require_once ROOT_PATH . '/models/UserModel.php';

class AuthController
{
    // ─────────────────────────────────────────────────────────────────────────
    // LOGIN
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Validate credentials and start a session.
     * Returns [] on success (and redirects), or ['error message', ...] on failure.
     */
    public static function handleLogin(array $post): array
    {
        $identifier = trim($post['email'] ?? '');
        $password   = $post['password'] ?? '';

        $errors = [];

        if ($identifier === '') {
            $errors[] = 'Email address or username is required.';
        }
        if ($password === '') {
            $errors[] = 'Password is required.';
        }
        if (!empty($errors)) {
            return $errors;
        }

        // Try patient first
        $user      = UserModel::findPatientByLogin($identifier);
        $user_type = 'patient';
        $id_field  = 'patient_id';

        if ($user) {
            if (($user['status'] ?? '') === 'pending_verification') {
                return ['Please verify your email before logging in. Check your inbox for the verification code.'];
            }
        } else {
            // Try staff
            $user      = UserModel::findStaffByLogin($identifier);
            $user_type = 'staff';
            $id_field  = 'staff_id';
        }

        if (!$user || empty($user['password']) || !password_verify($password, $user['password'])) {
            return ['Invalid email or password.'];
        }

        // Start session
        session_regenerate_id(true);

        $_SESSION['user_id']       = $user[$id_field];
        $_SESSION['user_type']     = $user_type;
        $_SESSION['user_name']     = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['login_time']    = time();
        $_SESSION['last_activity'] = time();

        if ($user_type === 'staff' && isset($user['role'])) {
            $_SESSION['user_role'] = $user['role'];
        }

        // Redirect based on user type / role
        $role = $user['role'] ?? '';
        if ($user_type === 'staff' && in_array($role, ['Manager', 'Admin', 'Dentist', 'Receptionist'])) {
            redirect_to('admin_dashboard.php');
        } else {
            redirect_to('landing.php');
        }

        return []; // never reached
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LOGOUT
    // ─────────────────────────────────────────────────────────────────────────

    /** Destroy session and redirect to login. */
    public static function handleLogout(): never
    {
        session_destroy();
        redirect_to('login.php');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REGISTER
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Validate registration form data.
     * Returns [] if all good, or ['field' => 'message', ...] if invalid.
     * Does NOT insert — caller calls PatientModel::create() after.
     */
    public static function validateRegistration(array $post): array
    {
        $errors = [];

        $username = trim($post['username'] ?? '');
        $fn       = trim($post['first_name'] ?? '');
        $ln       = trim($post['last_name'] ?? '');
        $dob      = trim($post['dob'] ?? '');
        $phone    = trim($post['phone'] ?? '');
        $email    = trim(strtolower($post['email'] ?? ''));
        $password = $post['password'] ?? '';
        $confirm  = $post['confirm_password'] ?? '';

        if ($username === '') {
            $errors['username'] = 'Username is required.';
        } elseif (strlen($username) < 3 || strlen($username) > 20) {
            $errors['username'] = 'Username must be 3–20 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors['username'] = 'Username may only contain letters, numbers, and underscores.';
        }

        if ($fn === '') $errors['first_name'] = 'First name is required.';
        if ($ln === '') $errors['last_name']  = 'Last name is required.';

        if ($dob === '') {
            $errors['dob'] = 'Date of birth is required.';
        } else {
            // Accept MM/DD/YYYY or YYYY-MM-DD
            $parts = explode('/', $dob);
            if (count($parts) === 3) {
                $dob = sprintf('%04d-%02d-%02d', $parts[2], $parts[0], $parts[1]);
            }
            $dt = DateTime::createFromFormat('Y-m-d', $dob);
            if (!$dt || $dt->format('Y-m-d') !== $dob) {
                $errors['dob'] = 'Invalid date format.';
            } else {
                $age_months = (int)$dt->diff(new DateTime())->y * 12 + (int)$dt->diff(new DateTime())->m;
                if ($age_months < 12) {
                    $errors['dob'] = 'We do not accept patients below 12 months of age.';
                }
            }
        }

        if ($phone === '') {
            $errors['phone'] = 'Phone number is required.';
        } else {
            $clean = preg_replace('/[\s\-\(\)\+]/', '', $phone);
            if (!preg_match('/^[0-9]+$/', $clean) || strlen($clean) < 7 || strlen($clean) > 15) {
                $errors['phone'] = 'Invalid phone number format.';
            }
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email address is required.';
        }

        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }

        return $errors;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GUARDS (convenience wrappers used by pages)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Redirect to login if not logged in.
     * Call at the top of any protected page.
     */
    public static function requireLogin(): void
    {
        if (!is_logged_in()) {
            redirect_to('login.php');
        }

        // Session timeout (15 min inactivity)
        $timeout = 900;
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
            $_SESSION['expiry_message'] = 'Your session has expired due to inactivity. Please login again.';
            session_destroy();
            redirect_to('login.php?expired=1');
        }

        $_SESSION['last_activity'] = time();
    }

    /**
     * Require a specific user type. Redirects to landing if not matched.
     * @param string $type  'patient' | 'staff'
     */
    public static function requireUserType(string $type): void
    {
        self::requireLogin();
        if (current_user_type() !== $type) {
            redirect_to('landing.php');
        }
    }

    /**
     * Require one of the given roles.
     * Example: AuthController::requireRole('Admin', 'Manager');
     */
    public static function requireRole(string ...$roles): void
    {
        self::requireLogin();
        if (!has_role(...$roles)) {
            redirect_to('landing.php');
        }
    }
}

