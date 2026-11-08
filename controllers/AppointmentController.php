<?php
/**
 * AppointmentController.php — Business logic for appointment-related actions.
 *
 * Usage in appointments.php:
 *   require_once ROOT_PATH . '/controllers/AppointmentController.php';
 *   $result = AppointmentController::handleBookWalkin($_POST, $user_id);
 */

require_once ROOT_PATH . '/models/AppointmentModel.php';
require_once ROOT_PATH . '/models/PatientModel.php';
require_once ROOT_PATH . '/models/ServiceModel.php';
require_once ROOT_PATH . '/models/StaffModel.php';
require_once ROOT_PATH . '/models/PaymentModel.php';

class AppointmentController
{
    // ─────────────────────────────────────────────────────────────────────────
    // AJAX HANDLERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * AJAX: search patients by name / email.
     * Outputs JSON and exits.
     */
    public static function ajaxSearchPatients(): never
    {
        header('Content-Type: application/json');
        $query = trim($_GET['query'] ?? '');

        if (strlen($query) < 2) {
            echo json_encode(['patients' => []]);
            exit();
        }

        $patients = PatientModel::search($query);
        echo json_encode(['patients' => $patients]);
        exit();
    }

    /**
     * AJAX: check whether a patient satisfies prerequisites for a service.
     * Outputs JSON and exits.
     */
    public static function ajaxCheckPrerequisites(): never
    {
        header('Content-Type: application/json');
        $patient_id = (int)($_GET['patient_id'] ?? 0);
        $service_id = (int)($_GET['service_id'] ?? 0);

        if ($patient_id <= 0 || $service_id <= 0) {
            echo json_encode(['has_valid_prerequisite' => false]);
            exit();
        }

        $has = self::checkPrerequisites($patient_id, $service_id);
        echo json_encode(['has_valid_prerequisite' => $has]);
        exit();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PREREQUISITE CHECKING
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns true if the patient satisfies all mandatory prerequisites
     * for the given service (or if none are required).
     */
    public static function checkPrerequisites(int $patient_id, int $service_id): bool
    {
        // Skip prerequisite checks if the patient already has an active maintenance plan
        if (AppointmentModel::hasActiveMaintenance($patient_id, $service_id)) {
            return true;
        }

        $prerequisites = AppointmentModel::getPrerequisites($service_id);
        if (empty($prerequisites)) {
            return true; // No prerequisites
        }

        foreach ($prerequisites as $prereq) {
            $completed = AppointmentModel::hasCompletedPrerequisite(
                $patient_id,
                (int)$prereq['required_service_id'],
                (int)$prereq['validity_period_days']
            );
            if (!$completed) {
                return false;
            }
        }

        return true;
    }

    /**
     * Returns an array of error strings for unmet prerequisites.
     * Empty array = all prerequisites met.
     */
    public static function prerequisiteErrors(int $patient_id, int $service_id, string $service_name = ''): array
    {
        if (AppointmentModel::hasActiveMaintenance($patient_id, $service_id)) {
            return [];
        }

        $prerequisites = AppointmentModel::getPrerequisites($service_id);
        if (empty($prerequisites)) {
            return [];
        }

        $errors = [];
        foreach ($prerequisites as $prereq) {
            $validity_days      = (int)$prereq['validity_period_days'];
            $req_service_id     = (int)$prereq['required_service_id'];
            $req_service_name   = $prereq['required_service_name'];

            $completed = AppointmentModel::hasCompletedPrerequisite($patient_id, $req_service_id, $validity_days);

            if (!$completed) {
                if ($validity_days > 0) {
                    $errors[] = "Patient's {$req_service_name} appointment has expired (must be within the last {$validity_days} days). "
                              . "Please schedule a new {$req_service_name} before booking {$service_name}.";
                } else {
                    $errors[] = "Patient must complete a {$req_service_name} appointment before booking {$service_name}. "
                              . "Please book a {$req_service_name} first.";
                }
            }
        }

        return $errors;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BOOK WALK-IN
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Handles the full walk-in booking flow for staff.
     * Returns [] on success (and redirects) or ['error', ...] on failure.
     */
    public static function handleBookWalkin(array $post, int $staff_user_id): array
    {
        $errors = [];

        $registration_type = $post['registration_type'] ?? '';
        $patient_id        = (int)($post['patient_id'] ?? 0);
        $service_id        = (int)($post['service_id'] ?? 0);
        $scheduled_date    = trim($post['scheduled_date'] ?? '');
        $scheduled_time    = trim($post['scheduled_time'] ?? '');

        // Validation
        if ($registration_type === 'existing' && $patient_id <= 0) {
            $errors[] = 'Please select a patient.';
        }

        if ($registration_type === 'new') {
            $fn    = trim($post['first_name'] ?? '');
            $ln    = trim($post['last_name']  ?? '');
            $email = trim(strtolower($post['email'] ?? ''));
            $phone = trim($post['phone'] ?? '');
            $dob   = trim($post['dob'] ?? '');

            if (!$fn || !$ln || !$email || !$phone || !$dob) {
                $errors[] = 'All patient information fields are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email address format.';
            } else {
                $cleaned = preg_replace('/[\D]/', '', $phone);
                if (strlen($cleaned) < 7 || strlen($cleaned) > 20) {
                    $errors[] = 'Invalid phone number format (7–20 digits).';
                }
            }
        }

        if ($service_id <= 0)                        $errors[] = 'Please select a service.';
        if (!$scheduled_date || !$scheduled_time)    $errors[] = 'Please select date and time.';

        if ($scheduled_date && $scheduled_time) {
            $dt_check = new DateTime($scheduled_date . ' ' . $scheduled_time);
            if ($dt_check < new DateTime()) {
                $errors[] = 'Cannot book appointments in the past.';
            }
        }

        if (!empty($errors)) return $errors;

        // ── Resolve / create patient ────────────────────────────────────────
        if ($registration_type === 'new') {
            $email = trim(strtolower($post['email'] ?? ''));
            // Check if patient already exists (by email)
            $existing = PatientModel::findPatientByEmail($email);
            if ($existing) {
                $patient_id = (int)$existing['patient_id'];
            } else {
                $base_username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', ($post['first_name'] ?? '') . ($post['last_name'] ?? '')));
                $username      = PatientModel::generateUniqueUsername($base_username);

                $pid = PatientModel::createWalkIn([
                    'username'      => $username,
                    'first_name'    => trim($post['first_name'] ?? ''),
                    'last_name'     => trim($post['last_name']  ?? ''),
                    'email'         => $email,
                    'primary_phone' => trim($post['phone']     ?? ''),
                    'dob'           => trim($post['dob']       ?? ''),
                    'gender'        => $post['gender']     ?? 'Prefer not to say',
                    'blood_type'    => $post['blood_type'] ?? 'Unknown',
                    'password'      => password_hash('Patient123!', PASSWORD_DEFAULT),
                ]);

                if (!$pid) {
                    return ['Failed to create patient record.'];
                }

                // Insert allergies if provided
                $allergies = $post['allergies'] ?? [];
                if (!empty($allergies)) {
                    PatientModel::syncAllergies($pid, $allergies);
                }

                $patient_id = $pid;
            }
        }

        if ($patient_id <= 0) return ['Patient not found.'];

        // ── Load service ────────────────────────────────────────────────────
        $service = ServiceModel::findById($service_id);
        if (!$service) return ['Service not found or inactive.'];

        $scheduled_datetime = $scheduled_date . ' ' . $scheduled_time . ':00';

        // ── Resolve dentist ─────────────────────────────────────────────────
        $dentist_id = (int)($post['dentist_id'] ?? 0);

        if ($dentist_id > 0) {
            // Validate dentist can perform service
            $can_perform_ids = ServiceModel::dentistCanPerform($dentist_id);
            if (!in_array($service_id, $can_perform_ids)) {
                // Double-check: service might have no required specializations
                $spec_count_conn = getDBConnection();
                $spec_check      = $spec_count_conn->prepare("SELECT COUNT(*) AS cnt FROM tbl_service_specializations WHERE service_id = ?");
                $spec_check->bind_param('i', $service_id);
                $spec_check->execute();
                $cnt = (int)$spec_check->get_result()->fetch_assoc()['cnt'];
                $spec_check->close();
                $spec_count_conn->close();

                if ($cnt > 0) {
                    $errors[] = 'Selected dentist does not specialize in this service. Please select a different dentist or service.';
                    return $errors;
                }
            }

            // Check availability
            require_once ROOT_PATH . '/functions/dentist_availability.php';
            $avail = checkDentistFullAvailability($dentist_id, $scheduled_datetime, (int)$service['default_duration_minutes']);
            if (!$avail['available']) {
                return ['Dentist is not available: ' . $avail['reason']];
            }
        } else {
            // Auto-select first available dentist
            $dentists = StaffModel::activeDentists();
            foreach ($dentists as $d) {
                require_once ROOT_PATH . '/functions/dentist_availability.php';
                $avail = checkDentistFullAvailability((int)$d['staff_id'], $scheduled_datetime, (int)$service['default_duration_minutes']);
                if ($avail['available']) {
                    $dentist_id = (int)$d['staff_id'];
                    break;
                }
            }
            if (!$dentist_id) return ['No dentist available at the selected time.'];
        }

        // ── Prerequisite check ──────────────────────────────────────────────
        if ($registration_type === 'existing') {
            $prereq_errors = self::prerequisiteErrors($patient_id, $service_id, $service['service_name']);
            if (!empty($prereq_errors)) {
                return $prereq_errors;
            }
        }

        // ── Create appointment ──────────────────────────────────────────────
        $price       = (float)$service['base_price'];
        $duration    = (int)$service['default_duration_minutes'];
        $downpayment = $price * 0.35;

        $appt_id = AppointmentModel::create([
            'patient_id'         => $patient_id,
            'dentist_id'         => $dentist_id,
            'service_id'         => $service_id,
            'scheduled_datetime' => $scheduled_datetime,
            'duration_minutes'   => $duration,
            'status'             => 'Pending',
            'price'              => $price,
        ]);

        if (!$appt_id) return ['Failed to create appointment.'];

        // Store pending payment info in session for cash payment modal
        $_SESSION['walkin_pending_appointment'] = [
            'appointment_id'    => $appt_id,
            'service_name'      => $service['service_name'],
            'service_price'     => $price,
            'downpayment_amount' => $downpayment,
            'scheduled_datetime' => $scheduled_datetime,
            'duration_minutes'  => $duration,
            'patient_id'        => $patient_id,
        ];

        redirect_to('appointments.php?walkin_payment=1');
        return [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PROCESS WALK-IN CASH PAYMENT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Process the cash downpayment for a walk-in appointment.
     * Returns [] on success (and redirects) or errors on failure.
     */
    public static function handleWalkinCashPayment(array $post, int $staff_id): array
    {
        $appointment_id    = (int)($post['appointment_id'] ?? 0);
        $amount_received   = (float)($post['amount_received'] ?? 0);
        $downpayment_amount = (float)($post['downpayment_amount'] ?? 0);

        if ($appointment_id <= 0 || $amount_received <= 0 || $downpayment_amount <= 0) {
            return ['Invalid payment details.'];
        }

        $change = $amount_received - $downpayment_amount;
        if ($change < 0) {
            return ['Amount received is less than downpayment amount.'];
        }

        // Verify appointment exists
        $appt = AppointmentModel::findById($appointment_id);
        if (!$appt) return ['Appointment not found.'];

        // Insert payment
        $pid = PaymentModel::createCashDownpayment(
            (int)$appt['patient_id'],
            $appointment_id,
            $downpayment_amount,
            $staff_id
        );

        if (!$pid) return ['Failed to process payment.'];

        // Update appointment status to Scheduled
        AppointmentModel::updateStatus($appointment_id, 'Scheduled');

        unset($_SESSION['walkin_pending_appointment']);
        set_flash('Walk-in appointment created and payment processed successfully!', 'success');
        redirect_to('appointments.php');
        return [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RESCHEDULE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Reschedule an appointment (patient only, 1 day advance notice required).
     * Returns [] on success or error strings on failure.
     */
    public static function handleReschedule(array $post, int $patient_id): array
    {
        $appointment_id = (int)($post['appointment_id'] ?? 0);
        $new_datetime   = trim($post['new_datetime']   ?? '');
        $new_duration   = (int)($post['new_duration']  ?? 0);

        $appt = AppointmentModel::findById($appointment_id);
        if (!$appt) return ['Appointment not found.'];
        if ((int)$appt['patient_id'] !== $patient_id) return ['Not authorized to reschedule this appointment.'];

        $existing_dt = new DateTime($appt['scheduled_datetime']);
        $days_until  = ($existing_dt->getTimestamp() - time()) / 86400;
        if ($days_until < 1) {
            return ['You can only reschedule at least 1 day before the scheduled date.'];
        }

        // Determine new status (keep Scheduled if already paid)
        $has_paid  = AppointmentModel::hasCompletedDownpayment($appointment_id);
        $new_status = $has_paid ? 'Scheduled' : 'Pending';

        $ok = AppointmentModel::reschedule($appointment_id, $new_datetime, $new_duration, $new_status);
        if (!$ok) return ['Failed to reschedule appointment.'];

        set_flash('Appointment rescheduled successfully.', 'success');
        redirect_to('appointments.php');
        return [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CLOSURE DATES
    // ─────────────────────────────────────────────────────────────────────────

    public static function handleAddClosure(array $post, int $manager_id): array
    {
        $date   = trim($post['closure_date'] ?? '');
        $reason = trim($post['reason']       ?? '');

        if (!$date) return ['Closure date is required.'];

        $count = AppointmentModel::countAppointmentsOnDate($date);
        if ($count > 0) {
            return ["Cannot set closure date: There are {$count} appointment(s) on this date. Please reschedule or cancel them first."];
        }

        AppointmentModel::addClosure($date, $reason, $manager_id);
        set_flash('Closure date added successfully.', 'success');
        redirect_to('appointments.php');
        return [];
    }

    public static function handleRemoveClosure(array $post): array
    {
        $closure_id = (int)($post['closure_id'] ?? 0);
        if ($closure_id <= 0) return ['Invalid closure ID.'];

        AppointmentModel::removeClosure($closure_id);
        set_flash('Closure date removed successfully.', 'success');
        redirect_to('appointments.php');
        return [];
    }
}

