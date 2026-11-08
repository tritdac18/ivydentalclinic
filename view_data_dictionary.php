<?php
/**
 * Data Dictionary Viewer
 * This file displays the database schema in a formatted table.
 * No database connection is required as data is hardcoded for visualization.
 */

$data_dictionary = [
    'tbl_appointments' => [
        ['name' => 'appointment_id', 'contents' => 'Unique ID for each dental appointment', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'patient_id', 'contents' => 'Unique identifier for the patient (linked to tbl_patients)', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_patients'],
        ['name' => 'dentist_id', 'contents' => 'Unique identifier for the dentist/staff (linked to tbl_staff)', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_staff'],
        ['name' => 'scheduled_datetime', 'contents' => 'Specific date and time for the scheduled appointment', 'type' => 'DATETIME', 'format' => 'YYYY-MM-DD HH:MM:SS', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'duration_minutes', 'contents' => 'Planned duration of the appointment in minutes', 'type' => 'INT(11)', 'format' => '999', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'service_type', 'contents' => 'Category or description of the dental service provided', 'type' => 'VARCHAR(100)', 'format' => 'X(100)', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'price', 'contents' => 'Total cost or price for the specific appointment/service', 'type' => 'DECIMAL(10,2)', 'format' => '999999.99', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'service_id', 'contents' => 'Unique identifier for the specific service from catalog', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'triage_urgency', 'contents' => 'Urgency level (Low, Medium, High, Emergency)', 'type' => 'ENUM', 'format' => '(List)', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'status', 'contents' => 'Current state (Pending, Scheduled, Confirmed, etc.)', 'type' => 'ENUM', 'format' => '(List)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'created_at', 'contents' => 'Automatic timestamp when the record was created', 'type' => 'TIMESTAMP', 'format' => 'Auto', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'updated_at', 'contents' => 'Automatic timestamp when the record was last modified', 'type' => 'TIMESTAMP', 'format' => 'Auto', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
    ],
    'tbl_staff' => [
        ['name' => 'staff_id', 'contents' => 'Unique ID for Staff', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'username', 'contents' => 'Unique login credential for staff members', 'type' => 'VARCHAR(50)', 'format' => 'X(50)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'first_name', 'contents' => 'Staff member\'s first name', 'type' => 'VARCHAR(100)', 'format' => 'X(100)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'last_name', 'contents' => 'Staff member\'s last name', 'type' => 'VARCHAR(100)', 'format' => 'X(100)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'email', 'contents' => 'Login credential and primary email contact', 'type' => 'VARCHAR(100)', 'format' => 'X(100)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'password', 'contents' => 'Securely hashed password for account access', 'type' => 'VARCHAR(255)', 'format' => 'X(255)', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'role', 'contents' => 'Staff function (Dentist, Admin, etc.)', 'type' => 'ENUM', 'format' => '(List)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'license_number', 'contents' => 'Professional license number (Nurse/Dentists)', 'type' => 'VARCHAR(50)', 'format' => 'X(50)', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'phone_number', 'contents' => 'Staff\'s primary contact number', 'type' => 'VARCHAR(20)', 'format' => 'X(20)', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'created_at', 'contents' => 'Record creation timestamp', 'type' => 'TIMESTAMP', 'format' => 'Auto', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'updated_at', 'contents' => 'Last modification timestamp', 'type' => 'TIMESTAMP', 'format' => 'Auto', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
    ],
    'tbl_patients' => [
        ['name' => 'patient_id', 'contents' => 'Unique ID for each registered patient', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'username', 'contents' => 'Patient\'s unique login username', 'type' => 'VARCHAR(50)', 'format' => 'X(50)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'first_name', 'contents' => 'Patient\'s official first name', 'type' => 'VARCHAR(100)', 'format' => 'X(100)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'last_name', 'contents' => 'Patient\'s official last name', 'type' => 'VARCHAR(100)', 'format' => 'X(100)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'dob', 'contents' => 'Patient\'s legal date of birth', 'type' => 'DATE', 'format' => 'YYYY-MM-DD', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'primary_phone', 'contents' => 'Main contact phone number', 'type' => 'VARCHAR(20)', 'format' => 'X(20)', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'email', 'contents' => 'Patient\'s primary email address', 'type' => 'VARCHAR(100)', 'format' => 'X(100)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'password', 'contents' => 'Encrypted password for access', 'type' => 'VARCHAR(255)', 'format' => 'X(255)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'blood_type', 'contents' => 'Patient\'s blood group (A+, B-, etc.)', 'type' => 'ENUM', 'format' => '(List)', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'gender', 'contents' => 'Patient\'s gender identification', 'type' => 'ENUM', 'format' => '(List)', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'status', 'contents' => 'Current account status', 'type' => 'ENUM', 'format' => '(List)', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
    ],
    'tbl_payments' => [
        ['name' => 'payment_id', 'contents' => 'Unique ID for each payment transaction', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'payment_type', 'contents' => 'Category (Downpayment, Invoice, Full Payment)', 'type' => 'ENUM', 'format' => '(List)', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'appointment_id', 'contents' => 'Associated treatment ID', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'N', 'pk_fk' => 'FK', 'ref' => 'tbl_appointments'],
        ['name' => 'invoice_id', 'contents' => 'Associated billing ID', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'N', 'pk_fk' => 'FK', 'ref' => 'tbl_invoices'],
        ['name' => 'patient_id', 'contents' => 'ID of the payer', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'N', 'pk_fk' => 'FK', 'ref' => 'tbl_patients'],
        ['name' => 'amount', 'contents' => 'Total amount of currency paid', 'type' => 'DECIMAL(10,2)', 'format' => '999999.99', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'payment_method', 'contents' => 'Mechanism (Cash, GCash, PayMongo, etc.)', 'type' => 'ENUM', 'format' => '(List)', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'transaction_id', 'contents' => 'Reference number from external processor', 'type' => 'VARCHAR(255)', 'format' => 'X(255)', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'payment_status', 'contents' => 'Current state of processing', 'type' => 'ENUM', 'format' => '(List)', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'payment_date', 'contents' => 'Official timestamp of payment', 'type' => 'DATETIME', 'format' => 'YYYY-MM-DD HH:MM:SS', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'staff_validator_id', 'contents' => 'ID of staff who verified manual pay', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'N', 'pk_fk' => 'FK', 'ref' => 'tbl_staff'],
        ['name' => 'overpayment_credit', 'contents' => 'Change credited back to patient', 'type' => 'DECIMAL(10,2)', 'format' => '9999.99', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
    ],
    'tbl_invoices' => [
        ['name' => 'invoice_id', 'contents' => 'Unique ID for each billing invoice', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'patient_id', 'contents' => 'Reference to billed patient', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'N', 'pk_fk' => 'FK', 'ref' => 'tbl_patients'],
        ['name' => 'appointment_id', 'contents' => 'Reference to treated appointment', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'N', 'pk_fk' => 'FK', 'ref' => 'tbl_appointments'],
        ['name' => 'total_amount', 'contents' => 'Gross amount due inclusive of taxes', 'type' => 'DECIMAL(10,2)', 'format' => '999999.99', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'payment_status', 'contents' => 'Billing state (Unpaid, Paid, Partial)', 'type' => 'ENUM', 'format' => '(List)', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'generated_at', 'contents' => 'Time when the invoice document was created', 'type' => 'DATETIME', 'format' => 'YYYY-MM-DD HH:MM:SS', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
    ],
    'tbl_maintenance_reminders' => [
        ['name' => 'reminder_id', 'contents' => 'Unique identifier for automated notifications', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'maintenance_id', 'contents' => 'Reference to treatment plan being monitored', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_patient_maintenance'],
        ['name' => 'patient_id', 'contents' => 'Target patient for notification', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_patients'],
        ['name' => 'reminder_type', 'contents' => 'Strategy (First, Second, Overdue)', 'type' => 'ENUM', 'format' => '(List)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'scheduled_send_date', 'contents' => 'Trigger date for the notification', 'type' => 'DATE', 'format' => 'YYYY-MM-DD', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'email_sent', 'contents' => 'Delivery success flag', 'type' => 'TINYINT(1)', 'format' => '0/1', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
    ],
    'tbl_clinic_closures' => [
        ['name' => 'closure_id', 'contents' => 'Unique ID for blacked-out clinic dates', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'closure_date', 'contents' => 'The specific date the facility is closed', 'type' => 'DATE', 'format' => 'YYYY-MM-DD', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'reason', 'contents' => 'Justification (Holiday, Renovation, etc.)', 'type' => 'VARCHAR(255)', 'format' => 'X(255)', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'created_by', 'contents' => 'Staff ID who recorded the closure', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_staff'],
    ],
    'tbl_dentist_schedules' => [
        ['name' => 'schedule_id', 'contents' => 'Unique ID for professional shifts', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'dentist_id', 'contents' => 'Reference to scheduled professional', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_staff'],
        ['name' => 'day_of_week', 'contents' => 'Specific day (0-6 representation)', 'type' => 'TINYINT(1)', 'format' => '0-6', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'start_time', 'contents' => 'Clock-in time for shift', 'type' => 'TIME', 'format' => 'HH:MM', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'end_time', 'contents' => 'Clock-out time for shift', 'type' => 'TIME', 'format' => 'HH:MM', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'is_active', 'contents' => 'Current viability of this shift', 'type' => 'TINYINT(1)', 'format' => '0/1', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
    ],
    'tbl_discounts' => [
        ['name' => 'discount_id', 'contents' => 'Unique ID for welfare/subsidy programs', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'patient_id', 'contents' => 'Recipient of the discount benefit', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_patients'],
        ['name' => 'discount_type', 'contents' => 'Group (Senior Citizen, PWD, Employee)', 'type' => 'ENUM', 'format' => '(List)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'id_number', 'contents' => 'Verification ID provided by patient', 'type' => 'VARCHAR(100)', 'format' => 'X(100)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
    ],
    'tbl_email_verification' => [
        ['name' => 'verification_id', 'contents' => 'Unique trace for identity checks', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'email', 'contents' => 'Destination for verification payload', 'type' => 'VARCHAR(255)', 'format' => 'X(255)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'verification_code', 'contents' => 'Temporal 6-digit access code', 'type' => 'VARCHAR(6)', 'format' => 'X(6)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
    ],
    'tbl_patient_allergies' => [
        ['name' => 'allergy_id', 'contents' => 'Clinical safety identification record', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'patient_id', 'contents' => 'Patient with specific drug/substance sensitivity', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_patients'],
        ['name' => 'allergy_name', 'contents' => 'Medical name of allergen (e.g. Latex)', 'type' => 'VARCHAR(255)', 'format' => 'X(255)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
    ],
    'tbl_patient_documents' => [
        ['name' => 'document_id', 'contents' => 'Asset identification for digital medical records', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'patient_id', 'contents' => 'Legal owner of the document asset', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_patients'],
        ['name' => 'document_type', 'contents' => 'Classification (X-Ray, Prescription, Consent)', 'type' => 'ENUM', 'format' => '(List)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'file_path', 'contents' => 'Logical server uri to access the media', 'type' => 'VARCHAR(500)', 'format' => 'X(500)', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
    ],
    'tbl_patient_maintenance' => [
        ['name' => 'maintenance_id', 'contents' => 'Tracking for recurring clinical requirements', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'patient_id', 'contents' => 'Patient on long-term treatment monitoring', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_patients'],
        ['name' => 'service_id', 'contents' => 'The treatment service requiring monitoring', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_service_catalog'],
        ['name' => 'status', 'contents' => 'Maintenance lifecycle (Pending, Done, Cancelled)', 'type' => 'ENUM', 'format' => '(List)', 'required' => 'N', 'pk_fk' => '', 'ref' => ''],
    ],
    'tbl_pending_registrations' => [
        ['name' => 'pending_id', 'contents' => 'Unique ID for stage-1 registration records', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'patient_id', 'contents' => 'Temporary reference for registration lifecycle', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_patients'],
        ['name' => 'verification_code', 'contents' => 'Code used to finalize registration', 'type' => 'VARCHAR(6)', 'format' => 'X(6)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
    ],
    'tbl_service_catalog' => [
        ['name' => 'service_id', 'contents' => 'Product identification code for services', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'service_name', 'contents' => 'Human-readable name of service', 'type' => 'VARCHAR(150)', 'format' => 'X(150)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'base_price', 'contents' => 'Standard non-discounted pricing', 'type' => 'DECIMAL(10,2)', 'format' => '99999.99', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
    ],
    'tbl_service_prerequisites' => [
        ['name' => 'prerequisite_id', 'contents' => 'Identification of treatment dependencies', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'service_id', 'contents' => 'Treatment that is currently locked', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_service_catalog'],
        ['name' => 'required_service_id', 'contents' => 'Treatment acting as a functional requirement', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_service_catalog'],
    ],
    'tbl_service_specializations' => [
        ['name' => 'id', 'contents' => 'Mapping ID for treatment categorization', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'service_id', 'contents' => 'Reference to the treatement offering', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_service_catalog'],
        ['name' => 'specialization_id', 'contents' => 'Referece to the professional field', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_specializations'],
    ],
    'tbl_specializations' => [
        ['name' => 'specialization_id', 'contents' => 'Unique ID for professional specializations', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'name', 'contents' => 'Field of expertise (Endodontics, Periodontics, etc.)', 'type' => 'VARCHAR(150)', 'format' => 'X(150)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
    ],
    'tbl_staff_activation_tokens' => [
        ['name' => 'token_id', 'contents' => 'Unique ID for account onboarding payloads', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'staff_id', 'contents' => 'Beneficiary of the activation payload', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_staff'],
        ['name' => 'activation_token', 'contents' => 'Unique hash for secure onboarding link', 'type' => 'VARCHAR(64)', 'format' => 'X(64)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
    ],
    'tbl_staff_education' => [
        ['name' => 'education_id', 'contents' => 'Identification for professional credentials', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'staff_id', 'contents' => 'The clinician with these credentials', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_staff'],
        ['name' => 'institution_name', 'contents' => 'University or Medical Board legal name', 'type' => 'VARCHAR(255)', 'format' => 'X(255)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
        ['name' => 'degree', 'contents' => 'Earned professional title/qualification', 'type' => 'VARCHAR(255)', 'format' => 'X(255)', 'required' => 'Y', 'pk_fk' => '', 'ref' => ''],
    ],
    'tbl_staff_specializations' => [
        ['name' => 'id', 'contents' => 'Mapping ID for clinician field alignment', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'PK', 'ref' => ''],
        ['name' => 'staff_id', 'contents' => 'ID of the specific staff member', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_staff'],
        ['name' => 'specialization_id', 'contents' => 'ID of their clinical expertise area', 'type' => 'INT(11)', 'format' => '99999', 'required' => 'Y', 'pk_fk' => 'FK', 'ref' => 'tbl_specializations'],
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data Dictionary - Ivy Dental Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --header-bg: #f5c898; 
            --row-bg: #fff8eb;    
            --text-color: #333;
            --border-color: #555;
            --accent-color: #e67e22;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #fdfdfd;
            margin: 0;
            padding: 20px;
            color: var(--text-color);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        header {
            margin-bottom: 20px;
        }

        h1 {
            color: #d35400;
            font-weight: 700;
            margin: 0;
            font-size: 28px;
            border-left: 5px solid var(--accent-color);
            padding-left: 15px;
        }

        .meta-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        input[type="text"] {
            padding: 12px 20px;
            border: 2px solid #d1b18a;
            border-radius: 8px;
            width: 450px;
            font-size: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .table-wrapper {
            background: white;
            border-radius: 4px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 2px solid #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        th {
            background-color: var(--header-bg);
            color: #000;
            text-align: left;
            padding: 15px;
            font-weight: 700;
            text-transform: uppercase;
            border: 1px solid #555;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        td {
            padding: 12px 15px;
            border: 1px solid #555;
            background-color: var(--row-bg);
            vertical-align: top;
            word-wrap: break-word;
        }

        /* Responsive Column Widths based on User Image Layout */
        th:nth-child(1), td:nth-child(1) { width: 12%; } /* TABLE NAME */
        th:nth-child(2), td:nth-child(2) { width: 12%; } /* ATTRIBUTE NAME */
        th:nth-child(3), td:nth-child(3) { width: 25%; } /* CONTENTS */
        th:nth-child(4), td:nth-child(4) { width: 12%; } /* TYPE */
        th:nth-child(5), td:nth-child(5) { width: 10%; } /* FORMAT */
        th:nth-child(6), td:nth-child(6) { width: 7%; text-align: center; } /* REQUIRED */
        th:nth-child(7), td:nth-child(7) { width: 7%; text-align: center; } /* PK OR FK */
        th:nth-child(8), td:nth-child(8) { width: 15%; } /* REFERENCE */

        .table-title { font-weight: 700; color: #000; }
        .attr-name { font-weight: 400; color: #000; }
        .pk-label, .fk-label, .required-y { font-weight: 700; }

        tr:hover td { background-color: #fcecd7 !important; }

        .table-separator {
            height: 12px;
            background-color: #444; 
        }

        @media print {
            .meta-info { display: none; }
            body { padding: 0; }
            th { position: static; }
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>Data Dictionary</h1>
    </header>

    <div class="meta-info">
        <div>
            <strong>Database:</strong> <code>if0_40821754_db_ivydental</code> | 
            <strong>Tables:</strong> <?php echo count($data_dictionary); ?> | 
            <strong>Format:</strong> Detailed Business Style
        </div>
        <input type="text" id="searchInput" placeholder="Search dictionary (Tables, Columns, Content)..." onkeyup="filterTable()">
    </div>

    <div class="table-wrapper">
        <table id="dictionaryTable">
            <thead>
                <tr>
                    <th>TABLE NAME</th>
                    <th>ATTRIBUTE NAME</th>
                    <th>CONTENTS</th>
                    <th>TYPE</th>
                    <th>FORMAT</th>
                    <th>REQUIRED</th>
                    <th>PK OR FK</th>
                    <th>FK REFERENCED TABLE</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data_dictionary as $tableName => $attributes): ?>
                    <?php 
                    $first = true; 
                    foreach ($attributes as $attr): 
                    ?>
                        <tr class="attr-row" data-table="<?php echo $tableName; ?>">
                            <td class="table-title"><?php echo $first ? $tableName : ""; ?></td>
                            <td class="attr-name"><?php echo $attr['name']; ?></td>
                            <td><?php echo $attr['contents']; ?></td>
                            <td><?php echo $attr['type']; ?></td>
                            <td style="font-family: monospace;"><?php echo $attr['format']; ?></td>
                            <td class="<?php echo $attr['required'] === 'Y' ? 'required-y' : ''; ?>"><?php echo $attr['required']; ?></td>
                            <td class="<?php echo !empty($attr['pk_fk']) ? 'pk-label' : ''; ?>"><?php echo $attr['pk_fk']; ?></td>
                            <td><?php echo $attr['ref']; ?></td>
                        </tr>
                    <?php 
                    $first = false;
                    endforeach; 
                    ?>
                    <!-- Table Separator row -->
                    <tr class="table-separator" data-table="<?php echo $tableName; ?>_sep">
                        <td colspan="8" style="padding: 0; border: none; background-color: #666;"></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    function filterTable() {
        var input = document.getElementById("searchInput");
        var filter = input.value.toUpperCase();
        var table = document.getElementById("dictionaryTable");
        var rows = table.getElementsByClassName("attr-row");
        var separators = table.getElementsByClassName("table-separator");

        for (var i = 0; i < rows.length; i++) {
            var text = rows[i].textContent || rows[i].innerText;
            if (text.toUpperCase().indexOf(filter) > -1) {
                rows[i].style.display = "";
            } else {
                rows[i].style.display = "none";
            }
        }
        
        // Hide separators if all rows for the preceding table are hidden
        for (var j = 0; j < separators.length; j++) {
            var tableName = separators[j].getAttribute('data-table').replace('_sep', '');
            var visible = false;
            for (var k = 0; k < rows.length; k++) {
                if (rows[k].getAttribute('data-table') === tableName && rows[k].style.display === "") {
                    visible = true;
                    break;
                }
            }
            separators[j].style.display = visible ? "" : "none";
        }
    }
</script>

</body>
</html>
