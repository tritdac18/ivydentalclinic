# Dental Clinic Website - Backend Setup

This is the backend implementation for the dental clinic website, including login, registration, and landing page functionality.

## Project Structure

```
.
├── config/
│   └── database.php          # Database configuration
├── database/
│   └── schema.sql            # MySQL database schema
├── php/
│   ├── login.php            # Login handler
│   ├── register.php         # Registration handler
│   ├── logout.php           # Logout handler
│   └── landing.php          # Landing page after login
└── stitch_dental_clinic_homepage/
    ├── login_page/
    │   └── login.php        # Login page (PHP version)
    └── registration_page/
        └── register.php     # Registration page (PHP version)
```

## Database Setup

### Step 1: Create the Database

1. Open phpMyAdmin or your MySQL client
2. Import the SQL file: `database/schema.sql`
   - This will create the database `dental_clinic_db`
   - Create all necessary tables: `tbl_branches`, `tbl_staff`, `tbl_patients`
   - Insert sample branch data

### Step 2: Configure Database Connection

Edit `config/database.php` and update the database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Your MySQL username
define('DB_PASS', '');            // Your MySQL password
define('DB_NAME', 'dental_clinic_db');
```

## Database Schema

### Tables Created

1. **tbl_branches**
   - `branch_id` (INT, PK)
   - `name` (VARCHAR(100))
   - `address` (VARCHAR(255))
   - `contact_number` (VARCHAR(20))
   - `is_active` (TINYINT(1))

2. **tbl_staff**
   - `staff_id` (INT, PK)
   - `primary_branch_id` (INT, FK to tbl_branches)
   - `first_name` (VARCHAR(50))
   - `last_name` (VARCHAR(50))
   - `role` (ENUM: Dentist, Admin, Nurse, Receptionist, Hygienist)
   - `license_number` (VARCHAR(50))
   - `email` (VARCHAR(150), UNIQUE)
   - `phone_number` (VARCHAR(20))
   - `password` (VARCHAR(255))

3. **tbl_patients**
   - `patient_id` (INT, PK)
   - `first_name` (VARCHAR(50))
   - `last_name` (VARCHAR(50))
   - `dob` (DATE)
   - `primary_phone` (VARCHAR(20))
   - `email` (VARCHAR(150), UNIQUE)
   - `password` (VARCHAR(255))
   - `medical_history_summary` (TEXT)

## Features Implemented

### 1. User Registration
- **File**: `stitch_dental_clinic_homepage/registration_page/register.php`
- **Handler**: `php/register.php`
- **Fields**: First Name, Last Name, Date of Birth, Phone Number, Email, Password
- **Validation**: 
  - All required fields
  - Email format validation
  - Password minimum 8 characters
  - Password confirmation match
  - Email uniqueness check
  - Date format validation (MM/DD/YYYY)

### 2. User Login
- **File**: `stitch_dental_clinic_homepage/login_page/login.php`
- **Handler**: `php/login.php`
- **Features**:
  - Supports both patient and staff login
  - Password verification using bcrypt
  - Session management
  - Error handling

### 3. Landing Page
- **File**: `php/landing.php`
- **Features**:
  - Displays welcome message with user's name
  - Shows account information
  - Different views for patients and staff
  - Quick action buttons
  - Logout functionality

### 4. Session Management
- Sessions are automatically started
- User information stored in session variables:
  - `user_id`: User's ID
  - `user_type`: 'patient' or 'staff'
  - `user_name`: Full name
  - `user_email`: Email address
  - `user_role`: Staff role (if staff)

## Usage

### Accessing the Pages

1. **Registration Page**: 
   ```
   http://localhost/stitch_dental_clinic_homepage/registration_page/register.php
   ```

2. **Login Page**: 
   ```
   http://localhost/stitch_dental_clinic_homepage/login_page/login.php
   ```

3. **Landing Page** (after login): 
   ```
   http://localhost/php/landing.php
   ```

### Testing the System

1. **Register a new patient**:
   - Go to registration page
   - Fill in all required fields
   - Date format: MM/DD/YYYY (e.g., 01/15/1990)
   - Password must be at least 8 characters
   - Submit the form

2. **Login**:
   - Use the email and password from registration
   - You'll be redirected to the landing page

3. **Logout**:
   - Click the "Logout" link on the landing page

## Security Features

- Passwords are hashed using `password_hash()` with bcrypt
- SQL injection prevention using prepared statements
- XSS protection using `htmlspecialchars()`
- Session-based authentication
- Input validation on both client and server side

## MySQL Queries Reference

### Insert Patient
```sql
INSERT INTO tbl_patients (first_name, last_name, dob, primary_phone, email, password) 
VALUES (?, ?, ?, ?, ?, ?);
```

### Select Patient by Email
```sql
SELECT patient_id, first_name, last_name, email, password 
FROM tbl_patients 
WHERE email = ?;
```

### Select Staff by Email
```sql
SELECT staff_id, first_name, last_name, email, password, role 
FROM tbl_staff 
WHERE email = ?;
```

### Check Email Exists
```sql
SELECT patient_id FROM tbl_patients WHERE email = ?;
```

## Notes

- Make sure PHP sessions are enabled in your `php.ini`
- The database uses UTF-8 encoding for proper character support
- All timestamps are automatically managed (created_at, updated_at)
- The system supports both patient and staff accounts
- Staff accounts require a branch assignment (primary_branch_id)

## Troubleshooting

1. **Database Connection Error**: 
   - Check database credentials in `config/database.php`
   - Ensure MySQL service is running
   - Verify database name exists

2. **Session Not Working**:
   - Check PHP session configuration
   - Ensure `session_start()` is called before any output

3. **Registration Fails**:
   - Check all required fields are filled
   - Verify email format is correct
   - Ensure password meets requirements (8+ characters)
   - Check if email already exists in database

4. **Login Fails**:
   - Verify email and password are correct
   - Check if account exists in database
   - Ensure password was hashed during registration

## Next Steps

To extend the system, you can:
- Add password reset functionality
- Implement email verification
- Add profile editing features
- Create appointment booking system
- Add admin dashboard for staff management

