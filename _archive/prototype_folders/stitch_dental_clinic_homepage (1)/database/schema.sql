-- Dental Clinic Database Schema
-- Create database
CREATE DATABASE IF NOT EXISTS dental_clinic_db;
USE dental_clinic_db;

-- Table: tbl_branches
CREATE TABLE IF NOT EXISTS tbl_branches (
    branch_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address VARCHAR(255),
    contact_number VARCHAR(20),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tbl_staff
CREATE TABLE IF NOT EXISTS tbl_staff (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    primary_branch_id INT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('Dentist', 'Admin', 'Nurse', 'Receptionist', 'Hygienist') NOT NULL,
    license_number VARCHAR(50),
    email VARCHAR(150) NOT NULL UNIQUE,
    phone_number VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (primary_branch_id) REFERENCES tbl_branches(branch_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tbl_patients
CREATE TABLE IF NOT EXISTS tbl_patients (
    patient_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    dob DATE,
    primary_phone VARCHAR(20) NOT NULL,
    email VARCHAR(150) UNIQUE,
    password VARCHAR(255) NOT NULL,
    medical_history_summary TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample branch data
INSERT INTO tbl_branches (name, address, contact_number, is_active) VALUES
('Main Branch', '123 Dental Street, Suite 100, New York, NY 10001', '(555) 123-4567', 1),
('Downtown Branch', '456 Health Avenue, Floor 2, New York, NY 10002', '(555) 234-5678', 1);

-- Create indexes for better performance
CREATE INDEX idx_patient_email ON tbl_patients(email);
CREATE INDEX idx_staff_email ON tbl_staff(email);
CREATE INDEX idx_staff_branch ON tbl_staff(primary_branch_id);

