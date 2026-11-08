-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql205.infinityfree.com
-- Generation Time: Jan 21, 2026 at 05:30 AM
-- Server version: 11.4.9-MariaDB
-- PHP Version: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `if0_40821754_db_ivydental`
--

-- --------------------------------------------------------

--
-- Table structure for table `tbl_appointments`
--

CREATE TABLE `tbl_appointments` (
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `dentist_id` int(11) NOT NULL,
  `scheduled_datetime` datetime NOT NULL,
  `duration_minutes` int(11) DEFAULT 60,
  `service_type` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `triage_urgency` enum('Low','Medium','High','Emergency') DEFAULT 'Low',
  `status` enum('Pending','Scheduled','Confirmed','Completed','Cancelled','No-Show') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_clinic_closures`
--

CREATE TABLE `tbl_clinic_closures` (
  `closure_id` int(11) NOT NULL,
  `closure_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_dentist_schedules`
--

CREATE TABLE `tbl_dentist_schedules` (
  `schedule_id` int(11) NOT NULL,
  `dentist_id` int(11) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_discounts`
--

CREATE TABLE `tbl_discounts` (
  `discount_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL COMMENT 'Patient who has this discount',
  `discount_type` enum('PWD','Senior Citizen','Other') NOT NULL COMMENT 'Type of discount',
  `id_number` varchar(100) NOT NULL COMMENT 'Discount ID number (PWD ID, Senior Citizen ID, etc.)',
  `discount_percentage` decimal(5,2) DEFAULT 20.00 COMMENT 'Discount percentage (default 20% for PWD/Senior)',
  `is_verified` tinyint(1) DEFAULT 0 COMMENT 'Whether this discount has been verified by staff',
  `verified_by_staff_id` int(11) DEFAULT NULL COMMENT 'Staff member who verified this discount',
  `verified_at` datetime DEFAULT NULL COMMENT 'When the discount was verified',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Whether this discount is currently active',
  `notes` text DEFAULT NULL COMMENT 'Additional notes about the discount',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_email_verification`
--

CREATE TABLE `tbl_email_verification` (
  `verification_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `verification_code` varchar(6) NOT NULL COMMENT '6-digit code',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL COMMENT 'Code expires 15 minutes after creation',
  `verified_at` timestamp NULL DEFAULT NULL COMMENT 'When code was verified',
  `is_used` tinyint(1) DEFAULT 0 COMMENT '1 if code has been used'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_invoices`
--

CREATE TABLE `tbl_invoices` (
  `invoice_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `payment_status` enum('Unpaid','Paid','Partial','Collection') DEFAULT NULL,
  `generated_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_maintenance_reminders`
--

CREATE TABLE `tbl_maintenance_reminders` (
  `reminder_id` int(11) NOT NULL,
  `maintenance_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `reminder_type` enum('first','second','overdue') NOT NULL COMMENT 'Type of reminder: first (7-10 days before), second (3-5 days before), overdue (7 days after)',
  `scheduled_send_date` date NOT NULL COMMENT 'The date when this reminder should be sent',
  `sent_at` datetime DEFAULT NULL COMMENT 'When the reminder was actually sent',
  `email_sent` tinyint(1) DEFAULT 0 COMMENT 'Whether the email was successfully sent',
  `email_error` text DEFAULT NULL COMMENT 'Error message if email sending failed',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_patients`
--

CREATE TABLE `tbl_patients` (
  `patient_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `dob` date DEFAULT NULL,
  `primary_phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') DEFAULT 'Unknown',
  `gender` enum('Male','Female','Other','Prefer not to say') DEFAULT 'Prefer not to say',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('pending_verification','active','inactive') DEFAULT 'pending_verification'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_patient_allergies`
--

CREATE TABLE `tbl_patient_allergies` (
  `allergy_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `allergy_name` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_patient_documents`
--

CREATE TABLE `tbl_patient_documents` (
  `document_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `document_type` enum('Parent Consent','X-Ray','Medical Record','Consent Form','Treatment Plan','Prescription','Lab Result','Insurance Document','Other') NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp(),
  `uploaded_by` int(11) DEFAULT NULL COMMENT 'Staff ID who uploaded the document',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_patient_maintenance`
--

CREATE TABLE `tbl_patient_maintenance` (
  `maintenance_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `related_appointment_id` int(11) DEFAULT NULL COMMENT 'The appointment that initiated this maintenance plan',
  `last_visit_date` date DEFAULT NULL,
  `recommended_next_visit` date DEFAULT NULL,
  `status` enum('pending','done','overdue','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_payments`
--

CREATE TABLE `tbl_payments` (
  `payment_id` int(11) NOT NULL,
  `payment_type` enum('Downpayment','Invoice Payment','Full Payment','Refund') DEFAULT 'Invoice Payment',
  `appointment_id` int(11) DEFAULT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','E-Wallet','Online','Credit Card','Debit Card','GCash','PayMongo') DEFAULT 'Cash',
  `transaction_id` varchar(255) DEFAULT NULL,
  `payment_status` enum('Pending','Completed','Failed','Refunded','Cancelled','Pending Verification') DEFAULT 'Pending Verification',
  `payment_date` datetime DEFAULT NULL,
  `staff_validator_id` int(11) DEFAULT NULL,
  `overpayment_credit` decimal(10,2) DEFAULT 0.00 COMMENT 'Overpayment amount credited to patient account',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_pending_registrations`
--

CREATE TABLE `tbl_pending_registrations` (
  `pending_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL COMMENT 'Foreign key to tbl_patients',
  `verification_code` varchar(6) NOT NULL COMMENT '6-digit verification code',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `expires_at` timestamp NOT NULL COMMENT 'Registration expires 24 hours after creation',
  `verified_at` timestamp NULL DEFAULT NULL COMMENT 'When email was verified'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_service_catalog`
--

CREATE TABLE `tbl_service_catalog` (
  `service_id` int(11) NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `default_duration_minutes` int(11) DEFAULT 60 COMMENT 'Default duration for this service in minutes',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Whether this service is currently available',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `maintenance_period_months` int(11) DEFAULT NULL COMMENT 'How long the service treatment plan lasts (in months). NULL means no maintenance required.',
  `maintenance_interval` enum('weekly','biweekly','monthly','bimonthly','quarterly','semiannually','annually') DEFAULT NULL COMMENT 'How often patients should return for maintenance visits. NULL means no maintenance interval.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_service_prerequisites`
--

CREATE TABLE `tbl_service_prerequisites` (
  `prerequisite_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `required_service_id` int(11) NOT NULL,
  `is_mandatory` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `validity_period_days` int(11) DEFAULT 30 COMMENT 'Number of days the prerequisite appointment remains valid. NULL means no expiration.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_service_specializations`
--

CREATE TABLE `tbl_service_specializations` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `specialization_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_specializations`
--

CREATE TABLE `tbl_specializations` (
  `specialization_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_staff`
--

CREATE TABLE `tbl_staff` (
  `staff_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('Manager','Dentist','Receptionist') NOT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_staff_activation_tokens`
--

CREATE TABLE `tbl_staff_activation_tokens` (
  `token_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `activation_token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_staff_education`
--

CREATE TABLE `tbl_staff_education` (
  `education_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `institution_name` varchar(255) NOT NULL,
  `degree` varchar(255) NOT NULL,
  `field_of_study` varchar(255) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tbl_staff_specializations`
--

CREATE TABLE `tbl_staff_specializations` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `specialization_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `tbl_appointments`
--
ALTER TABLE `tbl_appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_dentist_id` (`dentist_id`),
  ADD KEY `idx_scheduled_datetime` (`scheduled_datetime`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_service_id` (`service_id`);

--
-- Indexes for table `tbl_clinic_closures`
--
ALTER TABLE `tbl_clinic_closures`
  ADD PRIMARY KEY (`closure_id`),
  ADD UNIQUE KEY `closure_date` (`closure_date`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `tbl_dentist_schedules`
--
ALTER TABLE `tbl_dentist_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `dentist_id` (`dentist_id`),
  ADD KEY `day_of_week` (`day_of_week`);

--
-- Indexes for table `tbl_discounts`
--
ALTER TABLE `tbl_discounts`
  ADD PRIMARY KEY (`discount_id`),
  ADD UNIQUE KEY `unique_patient_discount_type` (`patient_id`,`discount_type`,`is_active`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_discount_type` (`discount_type`),
  ADD KEY `idx_is_verified` (`is_verified`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_verified_by` (`verified_by_staff_id`);

--
-- Indexes for table `tbl_email_verification`
--
ALTER TABLE `tbl_email_verification`
  ADD PRIMARY KEY (`verification_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_code` (`verification_code`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `tbl_invoices`
--
ALTER TABLE `tbl_invoices`
  ADD PRIMARY KEY (`invoice_id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_appointment_id` (`appointment_id`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_generated_at` (`generated_at`);

--
-- Indexes for table `tbl_maintenance_reminders`
--
ALTER TABLE `tbl_maintenance_reminders`
  ADD PRIMARY KEY (`reminder_id`),
  ADD KEY `idx_maintenance_id` (`maintenance_id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_scheduled_send_date` (`scheduled_send_date`),
  ADD KEY `idx_email_sent` (`email_sent`);

--
-- Indexes for table `tbl_patients`
--
ALTER TABLE `tbl_patients`
  ADD PRIMARY KEY (`patient_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `tbl_patient_allergies`
--
ALTER TABLE `tbl_patient_allergies`
  ADD PRIMARY KEY (`allergy_id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_allergy_name` (`allergy_name`);

--
-- Indexes for table `tbl_patient_documents`
--
ALTER TABLE `tbl_patient_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_document_type` (`document_type`);

--
-- Indexes for table `tbl_patient_maintenance`
--
ALTER TABLE `tbl_patient_maintenance`
  ADD PRIMARY KEY (`maintenance_id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_service_id` (`service_id`),
  ADD KEY `idx_appointment_id` (`related_appointment_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_next_visit` (`recommended_next_visit`);

--
-- Indexes for table `tbl_payments`
--
ALTER TABLE `tbl_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `idx_appointment_id` (`appointment_id`),
  ADD KEY `idx_invoice_id` (`invoice_id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_payment_type` (`payment_type`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_transaction_id` (`transaction_id`),
  ADD KEY `idx_payment_date` (`payment_date`),
  ADD KEY `idx_staff_validator_id` (`staff_validator_id`);

--
-- Indexes for table `tbl_pending_registrations`
--
ALTER TABLE `tbl_pending_registrations`
  ADD PRIMARY KEY (`pending_id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_verification_code` (`verification_code`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `tbl_service_catalog`
--
ALTER TABLE `tbl_service_catalog`
  ADD PRIMARY KEY (`service_id`),
  ADD KEY `idx_service_name` (`service_name`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_maintenance_period` (`maintenance_period_months`);

--
-- Indexes for table `tbl_service_prerequisites`
--
ALTER TABLE `tbl_service_prerequisites`
  ADD PRIMARY KEY (`prerequisite_id`),
  ADD UNIQUE KEY `uq_service_prerequisite` (`service_id`,`required_service_id`),
  ADD KEY `idx_service_id` (`service_id`),
  ADD KEY `idx_required_service_id` (`required_service_id`);

--
-- Indexes for table `tbl_service_specializations`
--
ALTER TABLE `tbl_service_specializations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `service_spec_unique` (`service_id`,`specialization_id`),
  ADD KEY `idx_service` (`service_id`),
  ADD KEY `idx_specialization` (`specialization_id`);

--
-- Indexes for table `tbl_specializations`
--
ALTER TABLE `tbl_specializations`
  ADD PRIMARY KEY (`specialization_id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `tbl_staff`
--
ALTER TABLE `tbl_staff`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`);

--
-- Indexes for table `tbl_staff_activation_tokens`
--
ALTER TABLE `tbl_staff_activation_tokens`
  ADD PRIMARY KEY (`token_id`),
  ADD UNIQUE KEY `activation_token` (`activation_token`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `expires_at` (`expires_at`);

--
-- Indexes for table `tbl_staff_education`
--
ALTER TABLE `tbl_staff_education`
  ADD PRIMARY KEY (`education_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `tbl_staff_specializations`
--
ALTER TABLE `tbl_staff_specializations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `staff_spec_unique` (`staff_id`,`specialization_id`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_spec` (`specialization_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_appointments`
--
ALTER TABLE `tbl_appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_clinic_closures`
--
ALTER TABLE `tbl_clinic_closures`
  MODIFY `closure_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_dentist_schedules`
--
ALTER TABLE `tbl_dentist_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_discounts`
--
ALTER TABLE `tbl_discounts`
  MODIFY `discount_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_email_verification`
--
ALTER TABLE `tbl_email_verification`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_invoices`
--
ALTER TABLE `tbl_invoices`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_maintenance_reminders`
--
ALTER TABLE `tbl_maintenance_reminders`
  MODIFY `reminder_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_patients`
--
ALTER TABLE `tbl_patients`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_patient_allergies`
--
ALTER TABLE `tbl_patient_allergies`
  MODIFY `allergy_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_patient_documents`
--
ALTER TABLE `tbl_patient_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_patient_maintenance`
--
ALTER TABLE `tbl_patient_maintenance`
  MODIFY `maintenance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_payments`
--
ALTER TABLE `tbl_payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_pending_registrations`
--
ALTER TABLE `tbl_pending_registrations`
  MODIFY `pending_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_service_catalog`
--
ALTER TABLE `tbl_service_catalog`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_service_prerequisites`
--
ALTER TABLE `tbl_service_prerequisites`
  MODIFY `prerequisite_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_service_specializations`
--
ALTER TABLE `tbl_service_specializations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_specializations`
--
ALTER TABLE `tbl_specializations`
  MODIFY `specialization_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_staff`
--
ALTER TABLE `tbl_staff`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_staff_activation_tokens`
--
ALTER TABLE `tbl_staff_activation_tokens`
  MODIFY `token_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_staff_education`
--
ALTER TABLE `tbl_staff_education`
  MODIFY `education_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tbl_staff_specializations`
--
ALTER TABLE `tbl_staff_specializations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tbl_appointments`
--
ALTER TABLE `tbl_appointments`
  ADD CONSTRAINT `tbl_appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_appointments_ibfk_2` FOREIGN KEY (`dentist_id`) REFERENCES `tbl_staff` (`staff_id`) ON UPDATE CASCADE;

--
-- Constraints for table `tbl_clinic_closures`
--
ALTER TABLE `tbl_clinic_closures`
  ADD CONSTRAINT `tbl_clinic_closures_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `tbl_staff` (`staff_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_dentist_schedules`
--
ALTER TABLE `tbl_dentist_schedules`
  ADD CONSTRAINT `tbl_dentist_schedules_ibfk_1` FOREIGN KEY (`dentist_id`) REFERENCES `tbl_staff` (`staff_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_discounts`
--
ALTER TABLE `tbl_discounts`
  ADD CONSTRAINT `fk_discount_patient` FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_discount_verifier` FOREIGN KEY (`verified_by_staff_id`) REFERENCES `tbl_staff` (`staff_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_invoices`
--
ALTER TABLE `tbl_invoices`
  ADD CONSTRAINT `fk_invoice_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `tbl_appointments` (`appointment_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_invoice_patient` FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients` (`patient_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_maintenance_reminders`
--
ALTER TABLE `tbl_maintenance_reminders`
  ADD CONSTRAINT `fk_reminder_maintenance` FOREIGN KEY (`maintenance_id`) REFERENCES `tbl_patient_maintenance` (`maintenance_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reminder_patient` FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients` (`patient_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_patient_allergies`
--
ALTER TABLE `tbl_patient_allergies`
  ADD CONSTRAINT `tbl_patient_allergies_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_patient_documents`
--
ALTER TABLE `tbl_patient_documents`
  ADD CONSTRAINT `tbl_patient_documents_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_patient_maintenance`
--
ALTER TABLE `tbl_patient_maintenance`
  ADD CONSTRAINT `fk_patient_maintenance_appointment` FOREIGN KEY (`related_appointment_id`) REFERENCES `tbl_appointments` (`appointment_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_patient_maintenance_patient` FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients` (`patient_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_patient_maintenance_service` FOREIGN KEY (`service_id`) REFERENCES `tbl_service_catalog` (`service_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_payments`
--
ALTER TABLE `tbl_payments`
  ADD CONSTRAINT `fk_payment_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `tbl_appointments` (`appointment_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payment_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `tbl_invoices` (`invoice_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payment_patient` FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients` (`patient_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payment_staff` FOREIGN KEY (`staff_validator_id`) REFERENCES `tbl_staff` (`staff_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_pending_registrations`
--
ALTER TABLE `tbl_pending_registrations`
  ADD CONSTRAINT `tbl_pending_registrations_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_service_prerequisites`
--
ALTER TABLE `tbl_service_prerequisites`
  ADD CONSTRAINT `tbl_service_prerequisites_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `tbl_service_catalog` (`service_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_service_prerequisites_ibfk_2` FOREIGN KEY (`required_service_id`) REFERENCES `tbl_service_catalog` (`service_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tbl_service_specializations`
--
ALTER TABLE `tbl_service_specializations`
  ADD CONSTRAINT `fk_service_specializations_service` FOREIGN KEY (`service_id`) REFERENCES `tbl_service_catalog` (`service_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_service_specializations_spec` FOREIGN KEY (`specialization_id`) REFERENCES `tbl_specializations` (`specialization_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_staff_activation_tokens`
--
ALTER TABLE `tbl_staff_activation_tokens`
  ADD CONSTRAINT `tbl_staff_activation_tokens_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `tbl_staff` (`staff_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_staff_education`
--
ALTER TABLE `tbl_staff_education`
  ADD CONSTRAINT `tbl_staff_education_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `tbl_staff` (`staff_id`) ON DELETE CASCADE;

--
-- Constraints for table `tbl_staff_specializations`
--
ALTER TABLE `tbl_staff_specializations`
  ADD CONSTRAINT `fk_staff_specializations_spec` FOREIGN KEY (`specialization_id`) REFERENCES `tbl_specializations` (`specialization_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_staff_specializations_staff` FOREIGN KEY (`staff_id`) REFERENCES `tbl_staff` (`staff_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
