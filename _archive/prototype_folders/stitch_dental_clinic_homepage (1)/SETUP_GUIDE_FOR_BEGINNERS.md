# 🦷 Dental Clinic Website Setup Guide
## Step-by-Step Instructions (For Beginners)

This guide will help you set up your dental clinic website from scratch. Follow each step carefully!

---

## 📋 What You Need First

Before we start, you need to have these installed on your computer:

1. **XAMPP** (or WAMP/MAMP) - This gives you PHP and MySQL
2. **A web browser** (Chrome, Firefox, or Edge)
3. **A text editor** (Notepad++ or VS Code - but Notepad works too!)

---

## Step 1: Install XAMPP (If You Don't Have It)

### What is XAMPP?
XAMPP is like a toolbox that gives you everything you need to run PHP websites on your computer.

### How to Install:
1. Go to: https://www.apachefriends.org/download.html
2. Download XAMPP for Windows (or Mac if you have a Mac)
3. Run the installer
4. Click "Next" through all the steps (use default settings)
5. Click "Finish" when done

**✅ Checkpoint**: You should see a XAMPP Control Panel icon on your desktop.

---

## Step 2: Start XAMPP Services

1. **Open XAMPP Control Panel** (double-click the icon)
2. You'll see buttons like "Start" next to Apache and MySQL
3. **Click "Start"** next to **Apache** (turns green when running)
4. **Click "Start"** next to **MySQL** (turns green when running)

**✅ Checkpoint**: Both Apache and MySQL should show "Running" in green.

**⚠️ If you get an error**: 
- Port 80 or 3306 might be in use
- Click "Config" → "httpd.conf" and change "Listen 80" to "Listen 8080"
- Or just close the program using that port (like Skype)

---

## Step 3: Put Your Files in the Right Place

1. **Find your project folder**: `stitch_dental_clinic_homepage (1)`
2. **Copy the entire folder**
3. **Go to**: `C:\xampp\htdocs\` (or wherever you installed XAMPP)
4. **Paste your folder** there
5. **Rename it** to something simple like: `dental_clinic`

**Your files should now be at**: `C:\xampp\htdocs\dental_clinic\`

**✅ Checkpoint**: You should see your folders (php, config, database, etc.) inside `C:\xampp\htdocs\dental_clinic\`

---

## Step 4: Create the Database

### Option A: Using phpMyAdmin (Easiest Way)

1. **Open your web browser**
2. **Type in the address bar**: `http://localhost/phpmyadmin`
3. You should see a page with a left sidebar

#### Create the Database:
1. Click on **"New"** in the left sidebar (or "Databases" tab at the top)
2. Under "Create database", type: `dental_clinic_db`
3. Click **"Create"** button
4. You should see `dental_clinic_db` appear in the left sidebar

#### Import the Tables:
1. **Click on** `dental_clinic_db` in the left sidebar (to select it)
2. Click the **"Import"** tab at the top
3. Click **"Choose File"** button
4. Navigate to: `C:\xampp\htdocs\dental_clinic\database\schema.sql`
5. Select the file and click **"Open"**
6. Scroll down and click **"Go"** button at the bottom
7. You should see a success message!

**✅ Checkpoint**: 
- Click on `dental_clinic_db` in the left sidebar
- You should see 3 tables: `tbl_branches`, `tbl_patients`, `tbl_staff`

---

## Step 5: Check Database Settings

1. **Open** the file: `C:\xampp\htdocs\dental_clinic\config\database.php`
2. **Open it with Notepad** (right-click → Open with → Notepad)

You should see something like this:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'dental_clinic_db');
```

**Most likely, these settings are already correct!** 
- If you didn't set a MySQL password, leave `DB_PASS` as empty `''`
- If you did set a password, put it between the quotes: `define('DB_PASS', 'yourpassword');`

**✅ Checkpoint**: Save the file if you made any changes (Ctrl+S)

---

## Step 6: Test Your Website!

1. **Open your web browser**
2. **Type in the address bar**: `http://localhost/dental_clinic/stitch_dental_clinic_homepage/registration_page/register.php`
3. **Press Enter**

**You should see**: A beautiful registration form!

**✅ Checkpoint**: The page loads without errors

---

## Step 7: Test Registration

1. **Fill out the registration form**:
   - First Name: `John`
   - Last Name: `Doe`
   - Date of Birth: `01/15/1990` (use MM/DD/YYYY format)
   - Phone Number: `(555) 123-4567`
   - Email: `john.doe@example.com`
   - Password: `password123` (at least 8 characters)
   - Confirm Password: `password123`
   - Check the "Terms" checkbox

2. **Click "Create Account"**

**What should happen**:
- If everything is correct → You'll be redirected to the login page with a success message
- If there's an error → You'll see red error messages (fix them and try again)

**✅ Checkpoint**: You can register successfully!

---

## Step 8: Test Login

1. **Go to the login page**: `http://localhost/dental_clinic/stitch_dental_clinic_homepage/login_page/login.php`
2. **Enter your credentials**:
   - Email: `john.doe@example.com` (the one you just registered)
   - Password: `password123`

3. **Click "Sign in"**

**What should happen**:
- You'll be redirected to a landing page that says "Welcome Back, John!"
- You'll see your account information

**✅ Checkpoint**: You can log in successfully!

---

## Step 9: Test Logout

1. **On the landing page**, click **"Logout"** (usually in the top right)
2. **You should be redirected** back to the login page

**✅ Checkpoint**: Logout works!

---

## 🎉 Congratulations!

Your dental clinic website backend is now working! 

---

## 🔧 Troubleshooting (Common Problems)

### Problem 1: "Connection failed" error
**Solution**: 
- Make sure MySQL is running in XAMPP Control Panel
- Check that the database name in `config/database.php` matches your database name

### Problem 2: Page shows "404 Not Found"
**Solution**:
- Make sure Apache is running in XAMPP
- Check that your files are in `C:\xampp\htdocs\dental_clinic\`
- Make sure the URL is correct: `http://localhost/dental_clinic/...`

### Problem 3: "Email already registered" when trying to register
**Solution**:
- This is normal! The email is already in the database
- Try using a different email address
- Or delete the old record from phpMyAdmin (go to `tbl_patients` table and delete the row)

### Problem 4: Can't see the database in phpMyAdmin
**Solution**:
- Make sure MySQL is running
- Click "Refresh" in phpMyAdmin
- Try creating the database again (Step 4)

### Problem 5: "Access denied" for database
**Solution**:
- Check your `config/database.php` file
- Make sure `DB_USER` is `'root'`
- Make sure `DB_PASS` matches your MySQL password (or is empty if you didn't set one)

### Problem 6: Registration form doesn't submit
**Solution**:
- Make sure all required fields are filled
- Check that the form action points to the correct file
- Look at the browser's developer console (F12) for errors

---

## 📁 File Structure (For Reference)

```
C:\xampp\htdocs\dental_clinic\
├── config\
│   └── database.php          (Database connection settings)
├── database\
│   ├── schema.sql            (Database structure)
│   └── mysql_queries.sql     (SQL queries reference)
├── php\
│   ├── login.php             (Login handler)
│   ├── register.php          (Registration handler)
│   ├── logout.php            (Logout handler)
│   └── landing.php           (Page after login)
└── stitch_dental_clinic_homepage\
    ├── login_page\
    │   └── login.php          (Login form page)
    └── registration_page\
        └── register.php       (Registration form page)
```

---

## 🎓 What Each File Does (Simple Explanation)

- **database.php**: Like a phone book - tells PHP where to find the database
- **schema.sql**: Like a blueprint - creates the tables in your database
- **register.php** (in php folder): The "worker" that processes registration
- **register.php** (in registration_page folder): The "form" you see on screen
- **login.php** (in php folder): The "worker" that checks your login
- **login.php** (in login_page folder): The "form" you see on screen
- **landing.php**: The "welcome page" after you log in

---

## 🚀 Next Steps (After Everything Works)

1. **Test all features**:
   - Register multiple users
   - Try logging in with wrong password (should show error)
   - Try registering with duplicate email (should show error)
   - Try registering with invalid email (should show error)

2. **Customize it**:
   - Change colors in the HTML files
   - Add more fields to registration
   - Modify the landing page

3. **Learn more**:
   - Study the code to understand how it works
   - Try modifying small things and see what happens
   - Read PHP and MySQL tutorials online

---

## 📞 Need Help?

If you're stuck:
1. Check the error message (it usually tells you what's wrong)
2. Make sure all steps above are completed
3. Check the Troubleshooting section
4. Make sure XAMPP (Apache and MySQL) is running

---

## ✅ Final Checklist

Before you consider it "done", make sure:

- [ ] XAMPP is installed
- [ ] Apache and MySQL are running (green in XAMPP)
- [ ] Files are in `C:\xampp\htdocs\dental_clinic\`
- [ ] Database `dental_clinic_db` is created
- [ ] Tables are imported (tbl_branches, tbl_patients, tbl_staff)
- [ ] Registration page loads: `http://localhost/dental_clinic/stitch_dental_clinic_homepage/registration_page/register.php`
- [ ] Can register a new user successfully
- [ ] Login page loads: `http://localhost/dental_clinic/stitch_dental_clinic_homepage/login_page/login.php`
- [ ] Can log in with registered credentials
- [ ] Landing page shows after login
- [ ] Logout works

**If all checkboxes are checked → You're done! 🎉**

---

Good luck! You've got this! 💪

