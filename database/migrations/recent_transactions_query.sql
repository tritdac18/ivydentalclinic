-- ============================================
-- Recent Transactions Query (Last 15 Minutes)
-- Shows completed payments for appointments processed in the last 15 minutes
-- ============================================

SELECT 
    p.payment_id,
    p.payment_type,
    p.amount,
    p.payment_method,
    p.transaction_id,
    p.payment_status,
    p.payment_date,
    p.created_at as payment_created_at,
    
    -- Appointment Details
    a.appointment_id,
    a.scheduled_datetime,
    a.status as appointment_status,
    a.price as appointment_price,
    
    -- Patient Details
    pt.patient_id,
    CONCAT(pt.first_name, ' ', pt.last_name) as patient_name,
    pt.email as patient_email,
    pt.phone_number as patient_phone,
    
    -- Service Details
    sc.service_name,
    sc.base_price as service_base_price,
    
    -- Dentist Details
    s.staff_id as dentist_id,
    CONCAT(s.first_name, ' ', s.last_name) as dentist_name,
    
    -- Time Calculations
    TIMESTAMPDIFF(MINUTE, p.payment_date, NOW()) as minutes_ago,
    p.payment_date as transaction_time

FROM tbl_payments p

-- Join with appointments
INNER JOIN tbl_appointments a 
    ON p.appointment_id = a.appointment_id

-- Join with patients
INNER JOIN tbl_patients pt 
    ON p.patient_id = pt.patient_id

-- Join with service catalog (if available)
LEFT JOIN tbl_service_catalog sc 
    ON a.service_id = sc.service_id

-- Join with staff (dentist)
LEFT JOIN tbl_staff s 
    ON a.dentist_id = s.staff_id

WHERE 
    -- Only completed payments
    p.payment_status = 'Completed'
    
    -- Only payments with appointment_id (paid appointments)
    AND p.appointment_id IS NOT NULL
    
    -- Only payments processed in the last 15 minutes
    AND p.payment_date >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    
    -- Alternative: Use created_at if payment_date is NULL
    -- AND (p.payment_date >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) 
    --      OR (p.payment_date IS NULL AND p.created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)))

ORDER BY 
    p.payment_date DESC,
    p.created_at DESC;

-- ============================================
-- Alternative Query: If payment_date might be NULL, use created_at
-- ============================================

/*
SELECT 
    p.payment_id,
    p.payment_type,
    p.amount,
    p.payment_method,
    p.transaction_id,
    p.payment_status,
    COALESCE(p.payment_date, p.created_at) as transaction_time,
    
    a.appointment_id,
    a.scheduled_datetime,
    a.status as appointment_status,
    
    CONCAT(pt.first_name, ' ', pt.last_name) as patient_name,
    pt.email as patient_email,
    
    sc.service_name,
    
    TIMESTAMPDIFF(MINUTE, COALESCE(p.payment_date, p.created_at), NOW()) as minutes_ago

FROM tbl_payments p
INNER JOIN tbl_appointments a ON p.appointment_id = a.appointment_id
INNER JOIN tbl_patients pt ON p.patient_id = pt.patient_id
LEFT JOIN tbl_service_catalog sc ON a.service_id = sc.service_id

WHERE 
    p.payment_status = 'Completed'
    AND p.appointment_id IS NOT NULL
    AND COALESCE(p.payment_date, p.created_at) >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)

ORDER BY COALESCE(p.payment_date, p.created_at) DESC;
*/

-- ============================================
-- Simplified Query: Just the essentials
-- ============================================

/*
SELECT 
    p.payment_id,
    p.amount,
    p.payment_method,
    p.payment_date,
    a.appointment_id,
    CONCAT(pt.first_name, ' ', pt.last_name) as patient_name,
    TIMESTAMPDIFF(MINUTE, p.payment_date, NOW()) as minutes_ago
FROM tbl_payments p
INNER JOIN tbl_appointments a ON p.appointment_id = a.appointment_id
INNER JOIN tbl_patients pt ON p.patient_id = pt.patient_id
WHERE 
    p.payment_status = 'Completed'
    AND p.appointment_id IS NOT NULL
    AND p.payment_date >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
ORDER BY p.payment_date DESC;
*/

