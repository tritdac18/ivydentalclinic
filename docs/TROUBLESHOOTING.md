# Troubleshooting Guide

## Quick Diagnostic Steps

1. **Visit the diagnostic page**: Go to `error_check.php` in your browser
   - This will show you exactly what's wrong

2. **Check common issues**:
   - Database connection errors
   - Missing files
   - PHP version compatibility
   - Session issues

## Common Problems and Solutions

### Problem: "Connection failed" or database errors
**Solution**: 
- Check if your database credentials in `config/db.php` are correct
- Verify your hosting provider allows remote MySQL connections
- Some hosts require specific hostnames (like `localhost` instead of the remote host)

### Problem: "File not found" errors
**Solution**:
- Make sure all files are uploaded to the correct directory
- Check that `config/db.php` exists in the `config` folder

### Problem: Blank page or "500 Internal Server Error"
**Solution**:
- Check PHP error logs on your hosting provider
- Visit `error_check.php` to see specific errors
- Make sure PHP version is 7.4 or higher

### Problem: Redirect loops
**Solution**:
- Clear your browser cookies/session
- Make sure `homepage.php` exists and is accessible

## Testing Your Website

1. **Test Homepage**: Visit `homepage.php` directly
   - Should show the dental clinic homepage
   - No database connection needed

2. **Test Registration**: 
   - Click "Register" or "Book Appointment" from homepage
   - Fill out the form
   - Should create account and redirect to login

3. **Test Login**:
   - Use the credentials you just created
   - Should redirect to dashboard

4. **Test Logout**:
   - Click logout from dashboard
   - Should redirect to login page

## File Structure

```
ivydentalclinic/
├── index.php              (Main entry point - routes users)
├── homepage.php           (Public homepage - no login needed)
├── login.php              (Login form + processing)
├── register.php           (Registration form + processing)
├── logout.php             (Logout handler)
├── landing.php            (Patient dashboard - requires login)
├── admin_dashboard.php    (Admin dashboard - requires login)
├── config/
│   └── db.php            (Database configuration)
├── error_check.php        (Diagnostic tool)
└── test.php              (Simple PHP test)
```

## Next Steps

1. Visit `error_check.php` to identify the specific issue
2. Check the error messages
3. Fix the issues shown
4. Test each page individually
5. Once working, you can delete `error_check.php` and `test.php`

