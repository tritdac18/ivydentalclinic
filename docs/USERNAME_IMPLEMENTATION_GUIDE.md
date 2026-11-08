# Username Implementation Guide

## Overview
This guide explains the changes made to add username support to the Ivy Dental Clinic system.

## Changes Made

### 1. Database Changes
Two SQL scripts have been created to add username columns to `tbl_patients` and `tbl_staff`:

#### Option A: Safe Migration (Recommended if you have existing data)
**File:** `add_username_safe.sql`
- Adds username column to existing tables without dropping them
- Generates default usernames for existing records
- Preserves all existing data

#### Option B: Fresh Start (Use if starting fresh or want to recreate tables)
**File:** `add_username_columns.sql`
- Drops and recreates tables with username support
- **WARNING:** This will delete all existing data!

### 2. Registration Page (`register.php`)
**Changes:**
- Added username input field (required)
- Username validation:
  - 3-20 characters
  - Only letters, numbers, and underscores allowed
  - Must be unique across both `tbl_patients` and `tbl_staff`
- Checks for duplicate usernames before registration
- Username is saved to database during registration

### 3. Login Page (`login.php`)
**Changes:**
- Now supports login with **either** username **or** email
- Automatically detects if input is email (contains @) or username
- Uses `password_verify()` for secure password verification (already implemented)
- Updated label to "Email address or Username"

### 4. Profile Page (`profile.php`)
**Changes:**
- Username displayed above First Name and Last Name in Personal Information section
- Username can be edited in edit mode
- Username validation when editing:
  - 3-20 characters
  - Only letters, numbers, and underscores
  - Must be unique (checks both patients and staff tables)
- Username is fetched from database and displayed/edited

## Installation Steps

### Step 1: Run Database Migration
Choose one of the following:

**If you have existing data (RECOMMENDED):**
```sql
-- Run this in your database:
-- File: add_username_safe.sql
```

**If you're starting fresh:**
```sql
-- Run this in your database:
-- File: add_username_columns.sql
```

### Step 2: Verify Database Changes
After running the SQL script, verify the changes:
```sql
-- Check tbl_patients structure
DESCRIBE tbl_patients;
-- Should show 'username' column

-- Check tbl_staff structure
DESCRIBE tbl_staff;
-- Should show 'username' column
```

### Step 3: Test the System
1. **Registration:**
   - Go to `register.php`
   - Fill in the form including username
   - Verify username validation works
   - Try registering with duplicate username (should show error)

2. **Login:**
   - Try logging in with username
   - Try logging in with email
   - Both should work

3. **Profile:**
   - View profile page
   - Username should be displayed above first/last name
   - Edit profile and change username
   - Verify username validation works

## Important Notes

1. **Username Uniqueness:**
   - Usernames must be unique across BOTH `tbl_patients` and `tbl_staff` tables
   - The system checks both tables when validating username

2. **Password Security:**
   - `login.php` already uses `password_verify()` for secure password verification
   - No changes needed for password handling

3. **Existing Users:**
   - If using `add_username_safe.sql`, existing users will get default usernames like `patient_1`, `patient_2`, etc.
   - Users should update their usernames through the profile page

4. **Data Migration:**
   - If you have existing appointments, they will remain intact
   - Foreign key relationships are preserved

## Files Modified

1. `register.php` - Added username field and validation
2. `login.php` - Added username/email login support
3. `profile.php` - Added username display and editing
4. `add_username_columns.sql` - SQL script for fresh start
5. `add_username_safe.sql` - SQL script for safe migration

## Troubleshooting

### Error: "Column 'username' doesn't exist"
- Make sure you've run the SQL migration script
- Check that the username column was added to both tables

### Error: "Duplicate username"
- Username must be unique across both patients and staff
- Choose a different username

### Login not working with username
- Verify the username column exists in the database
- Check that the login query is using the correct field name
- Ensure username is not NULL in the database

## Support
If you encounter any issues, check:
1. Database structure matches expected schema
2. All PHP files are uploaded correctly
3. Database connection is working
4. Session management is functioning

