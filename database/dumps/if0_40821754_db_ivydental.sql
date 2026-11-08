-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: sql205.infinityfree.com
-- Generation Time: Jan 18, 2026 at 12:52 PM
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

--
-- Dumping data for table `tbl_appointments`
--

INSERT INTO `tbl_appointments` (`appointment_id`, `patient_id`, `dentist_id`, `scheduled_datetime`, `duration_minutes`, `service_type`, `price`, `service_id`, `triage_urgency`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2026-01-23 10:00:00', 20, NULL, '300.00', 4, 'Low', 'Pending', '2026-01-16 13:40:31', '2026-01-16 14:39:57'),
(2, 1, 1, '2026-01-30 10:00:00', 30, NULL, '1000.00', 3, 'Low', 'Cancelled', '2026-01-16 14:09:00', '2026-01-16 14:13:16'),
(3, 1, 1, '2026-01-22 09:30:00', 30, NULL, '1000.00', 3, 'Low', 'Pending', '2026-01-16 14:40:20', '2026-01-16 14:40:20'),
(4, 2, 1, '2026-01-22 10:00:00', 20, NULL, '300.00', 4, 'Low', 'Completed', '2026-01-16 14:56:28', '2026-01-16 15:24:51'),
(5, 2, 1, '2026-01-22 10:00:00', 20, NULL, '300.00', 4, 'Low', 'Completed', '2026-01-16 15:31:12', '2026-01-16 15:32:56'),
(6, 2, 1, '2026-01-20 09:30:00', 90, NULL, '3500.00', 6, 'Low', 'Pending', '2026-01-16 16:48:04', '2026-01-16 16:48:04'),
(7, 2, 1, '2026-01-20 09:30:00', 90, NULL, '3500.00', 6, 'Low', 'Scheduled', '2026-01-16 16:48:05', '2026-01-16 16:48:10'),
(8, 2, 1, '2026-01-20 10:00:00', 45, NULL, '800.00', 2, 'Low', 'Completed', '2026-01-16 16:48:43', '2026-01-17 14:27:29'),
(9, 2, 1, '2026-01-24 10:00:00', 120, NULL, '15000.00', 7, 'Low', 'Scheduled', '2026-01-16 17:01:53', '2026-01-16 17:02:20'),
(11, 3, 1, '2026-01-18 11:00:00', 120, NULL, '15000.00', 7, 'Low', 'Scheduled', '2026-01-16 17:22:59', '2026-01-16 17:23:05'),
(12, 7, 1, '2026-01-29 09:30:00', 20, NULL, '300.00', 4, 'Low', 'Pending', '2026-01-17 10:21:36', '2026-01-17 10:21:36'),
(13, 7, 1, '2026-01-18 09:30:00', 20, NULL, '300.00', 4, 'Low', 'Pending', '2026-01-17 11:39:43', '2026-01-17 11:39:43'),
(14, 7, 1, '2026-01-23 09:30:00', 45, NULL, '800.00', 2, 'Low', 'Completed', '2026-01-17 11:43:08', '2026-01-17 15:05:01'),
(15, 7, 1, '2026-01-21 09:30:00', 45, NULL, '800.00', 2, 'Low', 'Completed', '2026-01-17 14:28:15', '2026-01-17 15:51:13'),
(16, 7, 1, '2026-01-18 09:30:00', 20, NULL, '300.00', 4, 'Low', 'Scheduled', '2026-01-17 14:29:15', '2026-01-17 14:29:49'),
(17, 7, 1, '2026-01-22 09:30:00', 30, NULL, '1000.00', 3, 'Low', 'Scheduled', '2026-01-17 14:41:15', '2026-01-17 14:41:21'),
(18, 7, 1, '2026-01-30 09:30:00', 20, NULL, '300.00', 4, 'Low', 'Scheduled', '2026-01-17 17:38:23', '2026-01-17 17:38:49'),
(19, 15, 1, '2026-01-21 11:30:00', 20, NULL, '300.00', 4, 'Low', 'Scheduled', '2026-01-18 15:31:35', '2026-01-18 16:53:26'),
(20, 15, 1, '2026-01-20 09:00:00', 30, NULL, '1000.00', 3, 'Low', 'Scheduled', '2026-01-18 16:04:29', '2026-01-18 16:53:25'),
(21, 15, 1, '2026-01-31 15:00:00', 120, NULL, '5000.00', 5, 'Low', 'Scheduled', '2026-01-18 16:33:31', '2026-01-18 16:53:24'),
(22, 15, 1, '2026-02-01 09:00:00', 45, NULL, '800.00', 2, 'Low', 'Pending', '2026-01-18 16:53:59', '2026-01-18 16:53:59'),
(23, 15, 1, '2026-02-01 09:00:00', 45, NULL, '800.00', 2, 'Low', 'Pending', '2026-01-18 17:00:09', '2026-01-18 17:00:09'),
(24, 15, 1, '2026-01-27 10:30:00', 90, NULL, '3500.00', 6, 'Low', 'Pending', '2026-01-18 17:13:52', '2026-01-18 17:13:52'),
(25, 15, 1, '2026-02-02 10:00:00', 30, NULL, '500.00', 1, 'Low', 'Pending', '2026-01-18 17:37:34', '2026-01-18 17:37:34'),
(26, 15, 1, '2026-02-06 10:30:00', 45, NULL, '800.00', 2, 'Low', 'Pending', '2026-01-18 17:45:31', '2026-01-18 17:45:31');

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

--
-- Dumping data for table `tbl_email_verification`
--

INSERT INTO `tbl_email_verification` (`verification_id`, `email`, `verification_code`, `created_at`, `expires_at`, `verified_at`, `is_used`) VALUES
(1, 'sstrkq@gmail.com', '350429', '2026-01-18 11:09:10', '2026-01-18 14:24:10', NULL, 0),
(2, 'sstrkq@gmail.com', '852424', '2026-01-18 11:09:34', '2026-01-18 14:24:34', NULL, 0),
(3, 'sstrkq@gmail.com', '860749', '2026-01-18 11:09:41', '2026-01-18 14:24:41', NULL, 0),
(4, 'sstrkq@gmail.com', '814562', '2026-01-18 11:16:03', '2026-01-18 14:31:03', NULL, 0),
(5, 'sstrkq@gmail.com', '116277', '2026-01-18 11:18:55', '2026-01-18 14:33:55', NULL, 0),
(6, 'sstrkq@gmail.com', '896031', '2026-01-18 11:19:34', '2026-01-18 14:34:34', NULL, 0),
(7, 'sstrkq@gmail.com', '381135', '2026-01-18 11:22:07', '2026-01-18 14:37:07', NULL, 0),
(8, 'sstrkq@gmail.com', '810223', '2026-01-18 13:53:30', '2026-01-18 17:08:30', NULL, 0),
(9, 'sstrkq@gmail.com', '510463', '2026-01-18 14:02:17', '2026-01-18 17:17:17', NULL, 0),
(10, 'andder288@gmail.com', '424468', '2026-01-18 14:03:42', '2026-01-18 17:18:42', '2026-01-18 14:03:56', 1);

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

--
-- Dumping data for table `tbl_invoices`
--

INSERT INTO `tbl_invoices` (`invoice_id`, `patient_id`, `appointment_id`, `total_amount`, `payment_status`, `generated_at`, `created_at`, `updated_at`) VALUES
(7, 2, 4, '150.00', 'Paid', '2026-01-16 00:00:00', '2026-01-16 15:30:07', '2026-01-16 15:30:25'),
(8, 2, 5, '150.00', 'Paid', '2026-01-16 00:00:00', '2026-01-16 15:32:10', '2026-01-16 15:32:56'),
(9, 2, 8, '400.00', 'Paid', '2026-01-16 00:00:00', '2026-01-16 16:49:03', '2026-01-17 14:27:29'),
(10, 2, 7, '1750.00', 'Unpaid', '2026-01-16 00:00:00', '2026-01-16 16:49:08', '2026-01-16 16:49:08'),
(11, 7, 14, '400.00', 'Paid', '2026-01-17 00:00:00', '2026-01-17 14:47:34', '2026-01-17 15:05:01'),
(12, 7, 15, '520.00', 'Paid', '2026-01-17 00:00:00', '2026-01-17 15:50:14', '2026-01-17 15:51:13'),
(13, 7, 17, '650.00', 'Partial', '2026-01-17 00:00:00', '2026-01-17 15:58:31', '2026-01-17 15:58:31'),
(14, 2, 9, '7500.00', 'Unpaid', '2026-01-17 00:00:00', '2026-01-17 16:17:39', '2026-01-17 16:17:39');

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
  `pwd_id_number` varchar(50) DEFAULT NULL COMMENT 'PWD ID number for discount eligibility',
  `pwd_verified_by_staff_id` int(11) DEFAULT NULL COMMENT 'Staff ID who verified PWD ID',
  `pwd_verified_at` timestamp NULL DEFAULT NULL COMMENT 'When PWD ID was verified',
  `senior_citizen_id_number` varchar(50) DEFAULT NULL COMMENT 'Senior Citizen ID number for discount eligibility',
  `senior_citizen_verified_by_staff_id` int(11) DEFAULT NULL COMMENT 'Staff ID who verified Senior Citizen ID',
  `senior_citizen_verified_at` timestamp NULL DEFAULT NULL COMMENT 'When Senior Citizen ID was verified',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('pending_verification','active','inactive') DEFAULT 'pending_verification'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_patients`
--

INSERT INTO `tbl_patients` (`patient_id`, `username`, `first_name`, `last_name`, `dob`, `primary_phone`, `email`, `password`, `blood_type`, `gender`, `pwd_id_number`, `pwd_verified_by_staff_id`, `pwd_verified_at`, `senior_citizen_id_number`, `senior_citizen_verified_by_staff_id`, `senior_citizen_verified_at`, `created_at`, `updated_at`, `status`) VALUES
(1, '', 'Elizabeth Rose', 'Bloodflame', NULL, '00000000000', 'erb@bloodflame.co', '', 'Unknown', 'Prefer not to say', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-16 13:40:31', '2026-01-16 13:40:31', 'active'),
(2, 'jinsoulzone', 'Trisha', 'Dela Cruz', '2005-07-18', '09151768016', 'yooo@gmail.com', '$2y$10$ChZRHKkPnzAUjr5B01TtVeU4x4YVlxTJXMhL.W/OZQUMAMVKiLaPe', 'AB+', 'Prefer not to say', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-16 13:55:58', '2026-01-16 13:55:58', 'active'),
(3, 'lizrose', 'Elizabeth Rose', 'Bloodflame', '2005-07-18', '09151768016', 'anderspamacc@gmail.com', '$2y$10$kxcO.GcaETtionCn/FeCtu1rj9M9/vmktZtcOJU2v8tqa6NuuVSry', 'A+', 'Female', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-16 17:09:47', '2026-01-16 17:09:47', 'active'),
(7, 'testpatient', 'Nerissa', 'Bloodflame', '2005-11-21', '09151768016', 'nerissa@ravencroft.com', '$2y$10$.YqoV5q7hPaSKK5JyepXuOzX6oiwOQZjrw3JvB8pNd1V8ziH3Ur.a', 'Unknown', 'Prefer not to say', '242423434234', 1, '2026-01-17 18:05:28', '242423434234', 1, '2026-01-17 15:49:15', '2026-01-17 10:21:03', '2026-01-17 18:26:26', 'active'),
(15, 'intone', 'Jinsoul', 'Jung', '1997-06-13', '09151768016', 'andder288@gmail.com', '$2y$10$wZCfXeQlQ1JfGMGnxJK2k.5WyKsD4wR3zti9PboN9j.Xq0y2hUI.G', 'A-', 'Female', NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-18 14:03:42', '2026-01-18 14:03:56', 'active');

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

--
-- Dumping data for table `tbl_patient_allergies`
--

INSERT INTO `tbl_patient_allergies` (`allergy_id`, `patient_id`, `allergy_name`, `created_at`, `updated_at`) VALUES
(1, 2, 'Peanuts', '2026-01-16 13:55:58', '2026-01-16 13:55:58'),
(2, 2, 'Shrimp', '2026-01-16 13:55:58', '2026-01-16 13:55:58'),
(3, 3, 'Peanuts', '2026-01-16 17:09:47', '2026-01-16 17:09:47'),
(4, 3, 'Latex', '2026-01-16 17:09:47', '2026-01-16 17:09:47'),
(8, 7, 'Peanuts', '2026-01-17 20:45:17', '2026-01-17 20:45:17'),
(14, 15, 'Peanuts', '2026-01-18 14:03:42', '2026-01-18 14:03:42');

-- --------------------------------------------------------

--
-- Table structure for table `tbl_patient_documents`
--

CREATE TABLE `tbl_patient_documents` (
  `document_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `document_type` enum('Parent Consent') NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp(),
  `uploaded_by` int(11) DEFAULT NULL COMMENT 'Staff ID who uploaded the document',
  `notes` text DEFAULT NULL
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
  `payment_method` enum('Cash','E-Wallet','Online','Credit Card','Debit Card','GCash') DEFAULT 'GCash',
  `transaction_id` varchar(255) DEFAULT NULL,
  `payment_reference_number` varchar(100) DEFAULT NULL,
  `payer_phone_number` varchar(20) DEFAULT NULL,
  `payer_account_name` varchar(100) DEFAULT NULL,
  `payment_status` enum('Pending','Completed','Failed','Refunded','Cancelled','Pending Verification') DEFAULT 'Pending Verification',
  `payment_date` datetime DEFAULT NULL,
  `external_txn_id` varchar(100) DEFAULT NULL,
  `processor_name` varchar(50) DEFAULT NULL,
  `staff_validator_id` int(11) DEFAULT NULL,
  `is_reconciled` tinyint(1) DEFAULT 0,
  `is_subject_to_discount_verification` tinyint(1) DEFAULT 0 COMMENT '1 if payment is subject to discount verification (downpayments at standard rate)',
  `discount_verified_at` timestamp NULL DEFAULT NULL COMMENT 'When discount was verified and applied',
  `discount_verified_by_staff_id` int(11) DEFAULT NULL COMMENT 'Staff who verified the discount',
  `discount_amount` decimal(10,2) DEFAULT 0.00 COMMENT 'Discount amount applied after verification',
  `overpayment_credit` decimal(10,2) DEFAULT 0.00 COMMENT 'Overpayment amount credited to patient account',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_payments`
--

INSERT INTO `tbl_payments` (`payment_id`, `payment_type`, `appointment_id`, `invoice_id`, `patient_id`, `amount`, `payment_method`, `transaction_id`, `payment_reference_number`, `payer_phone_number`, `payer_account_name`, `payment_status`, `payment_date`, `external_txn_id`, `processor_name`, `staff_validator_id`, `is_reconciled`, `is_subject_to_discount_verification`, `discount_verified_at`, `discount_verified_by_staff_id`, `discount_amount`, `overpayment_credit`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'Downpayment', 4, NULL, 2, '150.00', 'GCash', 'DP-1768575422-4', '324234424', '0912555677', 'Trisha A Dela Cruz', 'Completed', '2026-01-16 06:57:02', NULL, NULL, NULL, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-16 14:57:02', '2026-01-16 14:57:02'),
(3, 'Invoice Payment', NULL, 7, 2, '150.00', 'Cash', NULL, NULL, NULL, NULL, 'Completed', '2026-01-16 10:30:19', '', '', 1, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-16 15:30:25', '2026-01-16 15:30:25'),
(4, 'Downpayment', 5, NULL, 2, '150.00', 'GCash', 'DP-1768577484-5', '2312412412412', '09545677335', 'Trisha A Dela Cruz', 'Completed', '2026-01-16 07:31:24', NULL, NULL, NULL, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-16 15:31:24', '2026-01-16 15:31:24'),
(5, 'Invoice Payment', NULL, 8, 2, '150.00', 'Cash', NULL, NULL, NULL, NULL, 'Completed', '2026-01-16 10:32:17', '', '', 1, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-16 15:32:56', '2026-01-16 15:32:56'),
(6, 'Downpayment', 7, NULL, 2, '1750.00', 'GCash', 'DP-1768582090-7', '2312412412412', '09545677335', 'Trisha A Dela Cruz', 'Completed', '2026-01-16 08:48:10', NULL, NULL, NULL, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-16 16:48:10', '2026-01-16 16:48:10'),
(7, 'Downpayment', 8, NULL, 2, '400.00', 'GCash', 'DP-1768582127-8', '2312412412412', '09545677335', 'Trisha A Dela Cruz', 'Completed', '2026-01-16 08:48:47', NULL, NULL, NULL, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-16 16:48:47', '2026-01-16 16:48:47'),
(8, 'Downpayment', 9, NULL, 2, '7500.00', 'GCash', 'DP-1768582920-9', '2312412412412', '09545677335', 'Trisha A Dela Cruz', 'Completed', '2026-01-16 09:02:00', NULL, NULL, NULL, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-16 17:02:00', '2026-01-16 17:02:00'),
(10, 'Downpayment', 11, NULL, 3, '7500.00', 'GCash', 'DP-1768584185-11', '2312412412412', '09545677335', 'Trisha A Dela Cruz', 'Completed', '2026-01-16 09:23:05', NULL, NULL, NULL, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-16 17:23:05', '2026-01-16 17:23:05'),
(11, 'Downpayment', 14, NULL, 7, '400.00', 'GCash', 'DP-1768650192-14', '2312412412412', '09545677335', 'Trisha A Dela Cruz', 'Completed', '2026-01-17 03:43:12', NULL, NULL, NULL, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-17 11:43:12', '2026-01-17 11:43:12'),
(12, 'Invoice Payment', NULL, 9, 2, '400.00', 'Cash', NULL, NULL, NULL, NULL, 'Completed', '2026-01-17 09:27:01', '', '', 1, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-17 14:27:29', '2026-01-17 14:27:29'),
(13, 'Downpayment', 15, NULL, 7, '280.00', 'GCash', 'DP-1768660100-15', '2312412412412', '09545677335', 'Trisha A Dela Cruz', 'Completed', '2026-01-17 06:28:20', NULL, NULL, NULL, 0, 1, NULL, NULL, '0.00', '0.00', NULL, '2026-01-17 14:28:20', '2026-01-17 14:28:20'),
(14, 'Downpayment', 16, NULL, 7, '105.00', 'GCash', 'DP-1768660189-16', '2312412412412', '09545677335', 'Nerissa', 'Completed', '2026-01-17 06:29:49', NULL, NULL, NULL, 0, 1, NULL, NULL, '0.00', '0.00', NULL, '2026-01-17 14:29:49', '2026-01-17 14:29:49'),
(15, 'Downpayment', 17, NULL, 7, '350.00', 'GCash', 'DP-1768660881-17', '2312412412412', '09545677335', 'Nerissa', 'Completed', '2026-01-17 06:41:21', NULL, NULL, NULL, 0, 1, NULL, NULL, '0.00', '0.00', NULL, '2026-01-17 14:41:21', '2026-01-17 14:41:21'),
(16, 'Invoice Payment', NULL, 11, 7, '400.00', 'Cash', NULL, NULL, NULL, NULL, 'Completed', '2026-01-17 10:04:49', '', '', 1, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-17 15:05:01', '2026-01-17 15:05:01'),
(17, 'Invoice Payment', NULL, 12, 7, '520.00', 'Cash', NULL, NULL, NULL, NULL, 'Completed', '2026-01-17 10:50:16', '', '', 1, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-17 15:51:13', '2026-01-17 15:51:13'),
(18, 'Downpayment', 18, NULL, 7, '105.00', 'GCash', 'DP-1768671529-18', '6465465466', '0987875653', 'Nerissa Ravencroft', 'Completed', '2026-01-17 09:38:49', NULL, NULL, NULL, 0, 1, NULL, NULL, '0.00', '0.00', NULL, '2026-01-17 17:38:49', '2026-01-17 17:38:49'),
(19, 'Downpayment', 19, NULL, 15, '105.00', 'E-Wallet', 'PM-LINK-link_rfueUFyKvxTwCd71H73bPaMQ', NULL, NULL, NULL, 'Completed', '2026-01-18 08:53:26', NULL, NULL, NULL, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-18 15:49:49', '2026-01-18 16:53:26'),
(20, 'Downpayment', 20, NULL, 15, '350.00', 'E-Wallet', 'PM-LINK-uo9UGndxcSQ7nc3K2PV2DYJf', NULL, NULL, NULL, 'Completed', '2026-01-18 08:53:25', NULL, NULL, NULL, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-18 16:04:36', '2026-01-18 16:53:25'),
(21, 'Downpayment', 21, NULL, 15, '1750.00', 'E-Wallet', 'PM-LINK-LAtWMEXYaKodfpUajH7JYJbv', NULL, NULL, NULL, 'Completed', '2026-01-18 08:53:24', NULL, NULL, NULL, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-18 16:33:35', '2026-01-18 16:53:24'),
(22, 'Downpayment', 22, NULL, 15, '280.00', 'E-Wallet', 'PM-LINK-PmSTiuaitc3MCR4Es34bTGtH', NULL, NULL, NULL, 'Pending', '2026-01-18 08:54:02', NULL, NULL, NULL, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-18 16:54:02', '2026-01-18 16:54:02'),
(23, 'Downpayment', 23, NULL, 15, '280.00', 'E-Wallet', 'PM-LINK-rnWhHebN36AoFqoxVgw847kS', NULL, NULL, NULL, 'Pending', '2026-01-18 09:00:15', NULL, NULL, NULL, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-18 17:00:15', '2026-01-18 17:00:15'),
(24, 'Downpayment', 24, NULL, 15, '1225.00', 'E-Wallet', 'PM-LINK-3mPcztd2ddrFtPvuDFWPdfLJ', NULL, NULL, NULL, 'Pending', '2026-01-18 09:13:55', NULL, NULL, NULL, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-18 17:13:55', '2026-01-18 17:13:55'),
(25, 'Downpayment', 25, NULL, 15, '175.00', 'E-Wallet', 'PM-LINK-qSLz9cCA8MUMq7ZasktqJebj', NULL, NULL, NULL, 'Pending', '2026-01-18 09:37:39', NULL, NULL, NULL, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-18 17:37:39', '2026-01-18 17:37:39'),
(26, 'Downpayment', 26, NULL, 15, '280.00', 'E-Wallet', 'PM-LINK-Nyb8ifQmg9mA7oyKL4hzCigw', NULL, NULL, NULL, 'Pending', '2026-01-18 09:45:36', NULL, NULL, NULL, 0, 0, NULL, NULL, '0.00', '0.00', NULL, '2026-01-18 17:45:36', '2026-01-18 17:45:36');

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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_service_catalog`
--

INSERT INTO `tbl_service_catalog` (`service_id`, `service_name`, `base_price`, `default_duration_minutes`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'General Checkup', '500.00', 30, 1, '2026-01-16 10:55:40', '2026-01-16 10:55:40'),
(2, 'Cleaning', '800.00', 45, 1, '2026-01-16 10:55:40', '2026-01-16 10:55:40'),
(3, 'Extraction', '1000.00', 30, 1, '2026-01-16 10:55:40', '2026-01-16 10:55:40'),
(4, 'Consultation', '300.00', 20, 1, '2026-01-16 10:55:40', '2026-01-16 10:55:40'),
(5, 'Root Canal', '5000.00', 120, 1, '2026-01-16 10:55:40', '2026-01-16 10:55:40'),
(6, 'Teeth Whitening', '3500.00', 90, 1, '2026-01-16 10:55:40', '2026-01-16 10:55:40'),
(7, 'Braces Attachment', '15000.00', 120, 1, '2026-01-16 17:00:54', '2026-01-16 17:00:54');

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

--
-- Dumping data for table `tbl_service_prerequisites`
--

INSERT INTO `tbl_service_prerequisites` (`prerequisite_id`, `service_id`, `required_service_id`, `is_mandatory`, `created_at`, `validity_period_days`) VALUES
(1, 7, 4, 1, '2026-01-16 17:01:16', 30);

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
  `password` varchar(255) NOT NULL,
  `role` enum('Manager','Dentist','Receptionist') NOT NULL,
  `license_number` varchar(50) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tbl_staff`
--

INSERT INTO `tbl_staff` (`staff_id`, `username`, `first_name`, `last_name`, `email`, `password`, `role`, `license_number`, `phone_number`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'Admin', 'User', 'admin1@ivydental.com', '$2y$10$ZaMZAY8G9ymlhBDl2SAbf.SisFuOF7w1oA/sMhpDlsrD6Hd6HWU7S', 'Manager', NULL, '+63 912 345 6789', '2026-01-16 11:01:51', '2026-01-16 11:01:51');

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
-- Indexes for table `tbl_patients`
--
ALTER TABLE `tbl_patients`
  ADD PRIMARY KEY (`patient_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `pwd_verified_by_staff_id` (`pwd_verified_by_staff_id`),
  ADD KEY `senior_citizen_verified_by_staff_id` (`senior_citizen_verified_by_staff_id`),
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
  ADD KEY `idx_staff_validator_id` (`staff_validator_id`),
  ADD KEY `idx_is_reconciled` (`is_reconciled`),
  ADD KEY `idx_discount_verification` (`is_subject_to_discount_verification`,`discount_verified_at`),
  ADD KEY `discount_verified_by_staff_id` (`discount_verified_by_staff_id`);

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
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `tbl_service_prerequisites`
--
ALTER TABLE `tbl_service_prerequisites`
  ADD PRIMARY KEY (`prerequisite_id`),
  ADD UNIQUE KEY `uq_service_prerequisite` (`service_id`,`required_service_id`),
  ADD KEY `idx_service_id` (`service_id`),
  ADD KEY `idx_required_service_id` (`required_service_id`);

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
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `tbl_appointments`
--
ALTER TABLE `tbl_appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `tbl_email_verification`
--
ALTER TABLE `tbl_email_verification`
  MODIFY `verification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `tbl_invoices`
--
ALTER TABLE `tbl_invoices`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `tbl_patients`
--
ALTER TABLE `tbl_patients`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `tbl_patient_allergies`
--
ALTER TABLE `tbl_patient_allergies`
  MODIFY `allergy_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `tbl_patient_documents`
--
ALTER TABLE `tbl_patient_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_payments`
--
ALTER TABLE `tbl_payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `tbl_pending_registrations`
--
ALTER TABLE `tbl_pending_registrations`
  MODIFY `pending_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_service_catalog`
--
ALTER TABLE `tbl_service_catalog`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `tbl_service_prerequisites`
--
ALTER TABLE `tbl_service_prerequisites`
  MODIFY `prerequisite_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tbl_staff`
--
ALTER TABLE `tbl_staff`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
-- Constraints for table `tbl_invoices`
--
ALTER TABLE `tbl_invoices`
  ADD CONSTRAINT `fk_invoice_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `tbl_appointments` (`appointment_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_invoice_patient` FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients` (`patient_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tbl_patients`
--
ALTER TABLE `tbl_patients`
  ADD CONSTRAINT `tbl_patients_ibfk_1` FOREIGN KEY (`pwd_verified_by_staff_id`) REFERENCES `tbl_staff` (`staff_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_patients_ibfk_2` FOREIGN KEY (`senior_citizen_verified_by_staff_id`) REFERENCES `tbl_staff` (`staff_id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
-- Constraints for table `tbl_payments`
--
ALTER TABLE `tbl_payments`
  ADD CONSTRAINT `fk_payment_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `tbl_appointments` (`appointment_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payment_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `tbl_invoices` (`invoice_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payment_patient` FOREIGN KEY (`patient_id`) REFERENCES `tbl_patients` (`patient_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payment_staff` FOREIGN KEY (`staff_validator_id`) REFERENCES `tbl_staff` (`staff_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `tbl_payments_ibfk_1` FOREIGN KEY (`discount_verified_by_staff_id`) REFERENCES `tbl_staff` (`staff_id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
