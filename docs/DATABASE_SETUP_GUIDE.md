# Database Setup Guide for Appointment Booking

## What You Need to Do

You need to run SQL queries to create **2 new tables** in your database:

1. **`tbl_branches`** - Stores clinic locations
2. **`tbl_appointments`** - Stores appointment bookings

## Prerequisites

Make sure these tables already exist (they should from your registration/login system):
- ✅ `tbl_patients` - Should already exist
- ✅ `tbl_staff` - Should already exist

## Step-by-Step Instructions

### Option 1: Run the Combined SQL File (Recommended)

1. Open your database management tool (phpMyAdmin, MySQL Workbench, etc.)
2. Select your database: `if0_40821147_db_ivydental`
3. Go to the SQL tab/query editor
4. Copy and paste the entire contents of `setup_appointments_tables.sql`
5. Click "Execute" or "Run"

This will:
- Create `tbl_branches` table
- Insert 3 default branches (Baliwag, San Miguel, Sta. Maria)
- Create `tbl_appointments` table with all foreign keys

### Option 2: Run Individual Files

If you prefer to run them separately:

1. First run `create_tbl_branches.sql`
2. Then run `create_tbl_appointments.sql`

## What Gets Created

### `tbl_branches` Table
- Stores clinic branch locations
- Auto-inserts 3 default branches

### `tbl_appointments` Table
- Links to `tbl_patients` (patient_id)
- Links to `tbl_staff` (dentist_id)
- Links to `tbl_branches` (branch_id)
- Stores appointment date/time, duration, status, etc.

## Verification

After running the SQL, verify the tables exist:

```sql
SHOW TABLES LIKE 'tbl_%';
```

You should see:
- tbl_patients
- tbl_staff
- tbl_branches (NEW)
- tbl_appointments (NEW)

## Troubleshooting

### Error: "Table 'tbl_patients' doesn't exist"
- Make sure your registration system is working first
- The `tbl_patients` table should already exist

### Error: "Table 'tbl_staff' doesn't exist"
- Make sure your staff/admin login works
- The `tbl_staff` table should already exist

### Error: "Foreign key constraint fails"
- Make sure `tbl_patients` and `tbl_staff` tables exist first
- Check that they have `patient_id` and `staff_id` as primary keys

## After Setup

Once the tables are created:
1. The booking page (`book_appointment.php`) will work
2. Appointments will be saved to the database
3. You can view/manage appointments later

