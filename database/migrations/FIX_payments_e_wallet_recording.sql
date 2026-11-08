-- Fix: Ensure E-wallet payments properly record patient_id, invoice_id, and appointment_id
-- This script checks and fixes any existing records that are missing these fields

-- Check for payments missing critical fields
SELECT 
    payment_id,
    payment_type,
    payment_method,
    patient_id,
    invoice_id,
    appointment_id,
    transaction_id,
    payment_status,
    created_at
FROM tbl_payments
WHERE payment_method = 'E-Wallet'
AND (
    patient_id IS NULL 
    OR invoice_id IS NULL 
    OR (payment_type = 'Invoice Payment' AND appointment_id IS NULL)
)
ORDER BY created_at DESC;

-- Note: This is a diagnostic query. The actual fix is in the PHP code.
-- Run this to see which records need manual fixing.

