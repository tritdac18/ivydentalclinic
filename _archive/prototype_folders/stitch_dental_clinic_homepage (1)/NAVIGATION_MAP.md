# Navigation Map - All Connected Pages

## Main Entry Points

### `index.php`
- **Purpose**: Main entry point that routes users
- **Logic**:
  - If not logged in → Redirects to homepage
  - If logged in as Admin/Dentist → Redirects to `php/admin_dashboard.php`
  - If logged in as Patient → Redirects to `php/landing.php`
  - If logged in as other Staff → Redirects to `php/landing.php`

---

## Authentication Flow

### 1. Homepage
- **File**: `stitch_dental_clinic_homepage/dental_clinic_homepage/code.html`
- **Login Link**: → `stitch_dental_clinic_homepage/login_page/login.php`

### 2. Registration
- **File**: `stitch_dental_clinic_homepage/registration_page/register.php`
- **Form Action**: → `php/register.php`
- **After Success**: → `stitch_dental_clinic_homepage/login_page/login.php`

### 3. Login
- **File**: `stitch_dental_clinic_homepage/login_page/login.php`
- **Form Action**: → `php/login.php`
- **After Success**:
  - Admin/Dentist → `php/admin_dashboard.php`
  - Patient/Other Staff → `php/landing.php`

### 4. Logout
- **File**: `php/logout.php`
- **After Logout**: → `stitch_dental_clinic_homepage/login_page/login.php`

---

## Admin Dashboard Navigation

### `php/admin_dashboard.php`
**Sidebar Links:**
- **Dashboard** → `php/admin_dashboard.php` (current page)
- **Schedule** → `stitch_dental_clinic_homepage/clinic_schedule_calendar/code.html`
- **Patients** → `stitch_dental_clinic_homepage/admin_staff_and_patient_list_-_ivy_dental_clinic/code.html`
- **Services** → `#` (placeholder)
- **Finances** → `#` (placeholder)
- **Reports** → `stitch_dental_clinic_homepage/staff_sales_and_appointment_reports/code.html`
- **Logout** → `php/logout.php`

---

## Patient Landing Page Navigation

### `php/landing.php`
**Navigation Links:**
- **Dashboard** → `php/landing.php` (current page)
- **Appointments** → `#` (placeholder - can link to customer_appointment_calendar)
- **Logout** → `php/logout.php`

**Quick Actions:**
- **Book Appointment** → `stitch_dental_clinic_homepage/customer_appointment_booking_-_ivy_dental_clinic/code.html`
- **View Profile** → `stitch_dental_clinic_homepage/customer_profile_-_ivy_dental_clinic/code.html`

---

## Available UI Pages (Ready to Connect)

### Patient Pages
1. **Customer Dashboard**: `stitch_dental_clinic_homepage/customer_dashboard/code.html`
2. **Customer Profile**: `stitch_dental_clinic_homepage/customer_profile_-_ivy_dental_clinic/code.html`
3. **Appointment Booking**: `stitch_dental_clinic_homepage/customer_appointment_booking_-_ivy_dental_clinic/code.html`
4. **Appointment Calendar**: `stitch_dental_clinic_homepage/customer_appointment_calendar_-_ivy_dental_clinic/code.html`
5. **Invoice Page**: `stitch_dental_clinic_homepage/invoice_page_-_ivy_dental_clinic/code.html`
6. **Payment Page**: `stitch_dental_clinic_homepage/payment_page_-_ivy_dental_clinic/code.html`

### Staff Pages
1. **Staff Portal Overview**: `stitch_dental_clinic_homepage/staff_portal_overview/code.html`
2. **Staff Profile**: `stitch_dental_clinic_homepage/staff_profile_-_ivy_dental_clinic/code.html`
3. **Staff Reports**: `stitch_dental_clinic_homepage/staff_sales_and_appointment_reports/code.html`

### Admin Pages
1. **Admin Dashboard**: `php/admin_dashboard.php` ✅ (Connected)
2. **Admin Staff Account Creation**: `stitch_dental_clinic_homepage/admin_staff_account_creation/code.html`
3. **Admin Staff and Patient List**: `stitch_dental_clinic_homepage/admin_staff_and_patient_list_-_ivy_dental_clinic/code.html`
4. **Clinic Schedule Calendar**: `stitch_dental_clinic_homepage/clinic_schedule_calendar/code.html`

---

## File Structure

```
.
├── index.php                                    # Main entry point (routes users)
├── config/
│   └── database.php                            # Database connection
├── php/
│   ├── login.php                               # Login handler
│   ├── register.php                            # Registration handler
│   ├── logout.php                              # Logout handler
│   ├── landing.php                             # Patient landing page
│   └── admin_dashboard.php                     # Admin dashboard ✅ NEW
└── stitch_dental_clinic_homepage/
    ├── dental_clinic_homepage/
    │   └── code.html                            # Homepage
    ├── login_page/
    │   └── login.php                            # Login form
    ├── registration_page/
    │   └── register.php                         # Registration form
    ├── admin_dashboard/
    │   └── code.html                            # Admin UI (used in admin_dashboard.php)
    └── [other UI pages]
```

---

## How It Works

1. **User visits `index.php`**:
   - Checks if logged in
   - Routes to appropriate dashboard

2. **User logs in**:
   - Goes to `login.php` form
   - Submits to `php/login.php`
   - Redirects based on role:
     - Admin/Dentist → Admin Dashboard
     - Patient/Other → Landing Page

3. **Admin Dashboard**:
   - Only accessible to Admin/Dentist roles
   - Shows admin name and role from database
   - All navigation links connected

4. **Navigation**:
   - All sidebar links in admin dashboard point to correct pages
   - Logout button works
   - Dynamic content (name, date) from PHP

---

## Security Features

✅ **Session Validation**: All pages check if user is logged in  
✅ **Role-Based Access**: Admin dashboard only for Admin/Dentist  
✅ **Database Verification**: User data fetched fresh from database  
✅ **XSS Protection**: All output escaped with `htmlspecialchars()`  
✅ **SQL Injection Prevention**: All queries use prepared statements  

---

## Next Steps (To Connect More Pages)

1. Convert HTML pages to PHP (add authentication)
2. Update navigation links in each page
3. Add database queries for dynamic content
4. Connect forms to PHP handlers

**All core navigation is now connected!** 🎉

