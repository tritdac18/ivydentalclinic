<?php
/**
 * PaymentController.php — Payment processing, discount validation, and status updates.
 *
 * Usage in payments.php:
 *   require_once ROOT_PATH . '/controllers/PaymentController.php';
 *   $errors = PaymentController::handleDiscountValidation($_POST, $staff_id);
 */

require_once ROOT_PATH . '/models/PaymentModel.php';
require_once ROOT_PATH . '/models/AppointmentModel.php';

class PaymentController
{
    // ─────────────────────────────────────────────────────────────────────────
    // DISCOUNT VALIDATION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Validate and register a PWD / Senior Citizen discount ID.
     * Returns [] on success or error strings.
     */
    public static function handleDiscountValidation(array $post, int $staff_id): array
    {
        $patient_id      = (int)($post['patient_id'] ?? 0);
        $discount_type   = trim($post['discount_type'] ?? '');
        $id_number_raw   = trim($post['discount_id_number'] ?? '');

        if ($patient_id <= 0 || !$discount_type || !$id_number_raw) {
            return ['All discount fields are required.'];
        }

        // Sanitise — alphanumeric only
        $id_number = preg_replace('/[^A-Za-z0-9]/', '', $id_number_raw);
        $len = strlen($id_number);

        if ($len < 8)  return ['Discount ID number is too short (minimum 8 characters).'];
        if ($len > 12) return ['Discount ID number is too long (maximum 12 characters).'];

        // Map frontend value to DB enum
        $type_map = ['pwd' => 'PWD', 'senior' => 'Senior Citizen'];
        $db_type  = $type_map[$discount_type] ?? 'Other';

        $ok = PaymentModel::upsertDiscount($patient_id, $db_type, $id_number, $staff_id);

        if (!$ok) return ['Failed to save discount record.'];

        set_flash('Discount validated and saved successfully.', 'success');
        redirect_to('payments.php');
        return [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MARK PAYMENT AS VERIFIED / COMPLETED
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Manually mark a payment as Completed (staff verification).
     * Returns [] on success or errors.
     */
    public static function handleMarkCompleted(array $post, int $staff_id): array
    {
        $payment_id = (int)($post['payment_id'] ?? 0);
        if ($payment_id <= 0) return ['Invalid payment ID.'];

        $payment = PaymentModel::findById($payment_id);
        if (!$payment) return ['Payment not found.'];

        $ok = PaymentModel::updateStatus($payment_id, 'Completed');
        if (!$ok) return ['Failed to update payment status.'];

        // If this is a downpayment, update the linked appointment status
        if (isset($payment['appointment_id']) && $payment['appointment_id']) {
            AppointmentModel::updateStatus((int)$payment['appointment_id'], 'Scheduled');
        }

        set_flash('Payment marked as completed.', 'success');
        redirect_to('payments.php');
        return [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AUTO-VERIFY PENDING PAYMONGO PAYMENTS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fetch pending PayMongo payments older than N minutes and verify their status.
     * Intended to be called from auto_verify_payments.php (cron job).
     *
     * @param int $minutes_old  Only check payments older than this many minutes.
     */
    public static function autoVerifyPending(int $minutes_old = 15): void
    {
        $conn = getDBConnection();

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$minutes_old} minutes"));
        $result = $conn->prepare("
            SELECT payment_id, transaction_id, paymongo_payment_intent_id, appointment_id, amount
            FROM tbl_payments
            WHERE payment_status = 'Pending'
              AND payment_method != 'Cash'
              AND payment_date <= ?
        ");
        $result->bind_param('s', $cutoff);
        $result->execute();
        $pending = $result->get_result()->fetch_all(MYSQLI_ASSOC);
        $result->close();
        $conn->close();

        // Each entry must be verified via PayMongo API
        // The actual PayMongo HTTP call lives in services/payment_service.php
        // This method just orchestrates the loop.
        foreach ($pending as $payment) {
            // Delegate to payment_service.php if available
            if (function_exists('verifyPayMongoPayment')) {
                $status = verifyPayMongoPayment($payment['paymongo_payment_intent_id'] ?? $payment['transaction_id']);
                if ($status === 'paid') {
                    PaymentModel::updateStatus((int)$payment['payment_id'], 'Completed');
                    if ($payment['appointment_id']) {
                        AppointmentModel::updateStatus((int)$payment['appointment_id'], 'Scheduled');
                    }
                }
            }
        }
    }
}

